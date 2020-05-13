<?php

/**
 * Plugin Name: Caisse pour la consigne
 * Plugin URI: ...
 * Description: Caisse pour la consigne
 * Version: 2.0
 * Author: Giliam
 * Author URI: ...
 */
defined('ABSPATH') or die();
include_once plugin_dir_path(__FILE__) . '/common.php';
include_once plugin_dir_path(__FILE__) . '/caisse_widget.php';
include_once plugin_dir_path(__FILE__) . '/factures.php';

class FileNotFound extends Exception
{
}

class ConsignePlugin
{
    public function __construct()
    {
        $this->uploadFirst = false;
        $this->missingFile = false;
        $this->wrongFiles = false;
        $this->errorMessage = false;
        $this->wrongFileExtension = false;
        $this->uploadSucceeded = false;
        $this->users_updated = array();
        // $this->users_updated_values = array();
        $this->users_failed = array();
        $this->users_missing = array();

        register_activation_hook(__FILE__, array('ConsignePlugin', 'install'));
        register_uninstall_hook(__FILE__, array('ConsignePlugin', 'uninstall'));

        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('widgets_init', function () {
            register_widget('ConsigneCaisseWidget');
        });
        add_action('plugins_loaded', array('PageTemplater', 'get_instance'));
    }

    public static function install()
    {
        global $wpdb;

        $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}consigne_caisse_soldes (email VARCHAR(255) PRIMARY KEY, user_id INT, balance DOUBLE PRECISION DEFAULT 0.0, last_updated DATETIME DEFAULT CURRENT_TIMESTAMP);");
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}consigne_caisse_factures (fac_id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(255), user_id INT, ope_id INT, date_ope DATETIME, produit VARCHAR(255), fournisseur INT DEFAULT 0, quantite DOUBLE PRECISION DEFAULT 0.0, prix DOUBLE PRECISION DEFAULT 0.0);");
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}consigne_caisse_accomptes (ac_id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(255), user_id INT, date_ope DATETIME, valeur DOUBLE PRECISION DEFAULT 0.0);");

        update_option('consigne_caisse_go_mail', "Courriel1");
        update_option('consigne_caisse_go_balance', "TotalRestant");
        update_option('consigne_caisse_go_format_date_ope', "d-m-y");
        update_option('consigne_caisse_go_format_date_accomptes', "d-m-y");
    }

    public static function uninstall()
    {
        global $wpdb;

        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}consigne_caisse_soldes;");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}consigne_caisse_factures;");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}consigne_caisse_accomptes;");
        if (get_option('consigne_caisse_db_pathfile_operations') && file_exists(get_option('consigne_caisse_db_pathfile_operations'))) {
            unlink(get_option('consigne_caisse_db_pathfile_operations'));
        }
        if (get_option('consigne_caisse_db_pathfile_accomptes') && file_exists(get_option('consigne_caisse_db_pathfile_accomptes'))) {
            unlink(get_option('consigne_caisse_db_pathfile_accomptes'));
        }
        if (get_option('consigne_caisse_db_pathfile_detail_operations') && file_exists(get_option('consigne_caisse_db_pathfile_detail_operations'))) {
            unlink(get_option('consigne_caisse_db_pathfile_detail_operations'));
        }
        if (get_option('consigne_caisse_db_pathfile_contacts') && file_exists(get_option('consigne_caisse_db_pathfile_contacts'))) {
            unlink(get_option('consigne_caisse_db_pathfile_contacts'));
        }
        delete_option('consigne_caisse_last_updated');
        delete_option('consigne_caisse_last_uploaded');

        delete_option('consigne_caisse_db_pathfile_accomptes');
        delete_option('consigne_caisse_db_pathfile_operations');
        delete_option('consigne_caisse_db_pathfile_detail_operations');
        delete_option('consigne_caisse_db_pathfile_contacts');

        delete_option('consigne_caisse_go_mail');
        delete_option('consigne_caisse_go_balance');
        delete_option('consigne_caisse_go_format_date_ope');
        delete_option('consigne_caisse_go_format_date_accomptes');
    }

    public function add_admin_menu()
    {
        $hook = add_menu_page('Consigne Caisse', 'Caisse', 'manage_options', 'consigne_caisse', array($this, 'menu_html'), plugins_url('caisse/images/icon.png'));
        add_action('load-' . $hook, array($this, 'process_action'));
    }

    public function menu_html()
    {
        $balance = get_current_user_balance();
        $last_uploaded = get_option('consigne_caisse_last_uploaded');
        $last_updated = get_option('consigne_caisse_last_updated');

        echo '<h1>' . get_admin_page_title() . '</h1>';
?>
        <h2>Votre balance</h2>
        <p><strong><?php echo $balance; ?></strong></p>
        <h2>Envoyer un fichier</h2>
        <p>Dernier envoi : <em><?php echo empty($last_uploaded) ? "Inconnu" : date("d/m/Y H:i:s", $last_uploaded); ?></em></p>
        <form method="post" action="" enctype="multipart/form-data">
            <?php
            if ($this->uploadSucceeded) {
            ?>
                <div class="updated">
                    <p>Envoi réussi !</p>
                </div>
                <?php
            }
                ?><?php
                    if ($this->wrongFileExtension) {
                    ?>
                <div class="notice notice-error">
                    <p>Mauvais format de fichier !</p>
                </div>
            <?php
                    }
            ?>
            <p><label>T_Operation.csv</label>
                <input type="file" name="consigne_caisse_upload_operations" /></p>
            <p><label>T_DetailOperation.csv</label>
                <input type="file" name="consigne_caisse_upload_detail_operations" /></p>
            <p><label>tContacts.csv</label>
                <input type="file" name="consigne_caisse_upload_contacts" /></p>
            <p><label>tAccomptes.csv</label>
                <input type="file" name="consigne_caisse_upload_accomptes" /></p>
            <?php submit_button("Upload"); ?>
        </form>

        <h2>Mettre-à-jour la base de données</h2>
        <p style="<?php if ($last_uploaded && $last_updated && $last_updated < $last_uploaded) { ?>color:red<?php } ?>">Last update: <em><?php echo empty($last_updated) ? "Inconnu" : date("d/m/Y H:i:s", $last_updated); ?></em> <?php if ($last_uploaded && $last_updated && $last_updated < $last_uploaded) { ?><strong>(outdated)</strong><?php } ?></p>
        <?php
        if ($this->missingFile) {
        ?>
            <div class="notice notice-error">
                <p>Il manque un fichier !</p>
            </div>
        <?php
        }
        if ($this->wrongFiles) {
        ?>
            <div class="notice notice-error">
                <p>Êtes-vous sûr de vos fichiers ? Il devrait y avoir les fichiers suivants :</p>
                <ul>
                    <li>
                        <code><?php echo get_option("consigne_caisse_go_mail") ?></code> et
                        <code>tContactsPK</code> comme entêtes dans le fichier des <em>Contacts</em>;
                    </li>
                    <li>
                        <code>IdOperation</code>, <code>IdProduit</code>, <code>DesignationProduit</code>,
                        <code>Quantite</code>, <code>PrixUnitaire</code>, <code>TauxTVA</code> dans le
                        fichier <em>T_DetailOperation</em>;
                    </li>
                    <li>
                        <code><?php echo get_option("consigne_caisse_go_balance"); ?></code>,
                        <code>IDFKContacts</code>,
                        <code>DateOperation</code> et
                        <code>HeureOperation</code>
                        dans le fichier <em>T_Operation</em>.
                    </li>
                    <li>
                        <code>tContactsFK</code>,
                        <code>AccompteDate</code> et
                        <code>AccompteMt</code>
                        dans le fichier <em>tAccomptes</em>.
                    </li>
                </ul>
            </div>
        <?php
        }
        if ($this->uploadFirst) {
        ?>
            <div class="notice notice-error">
                <p>Il faut envoyer les fichiers au préalable!</p>
            </div>
        <?php
        }
        if ($this->errorMessage) {
        ?>
            <div class="notice notice-error">
                <p>Erreurs durant l'importation...</p>
                <p><?php echo $this->errorMessage; ?></p>
            </div>
        <?php
        }
        ?>
        <form method="post" action="">
            <p><label>Format de la colonne des dates pour les opérations (d/m/Y pour 01/01/2020 ou d-m-y pour 01-01-20 ou d-m-Y pour 01-01-2020) :</label>
                <input type="text" name="consigne_caisse_go_format_date_ope" value="<?php echo empty(get_option("consigne_caisse_go_format_date_ope")) ? "d-m-y" : get_option("consigne_caisse_go_format_date_ope"); ?>" /></p>
            <p><label>Format de la colonne des dates pour les accomptes (d/m/Y pour 01/01/2020 ou d-m-y pour 01-01-20 ou d-m-Y pour 01-01-2020) :</label>
                <input type="text" name="consigne_caisse_go_format_date_accomptes" value="<?php echo empty(get_option("consigne_caisse_go_format_date_accomptes")) ? "d-m-y" : get_option("consigne_caisse_go_format_date_accomptes"); ?>" /></p>
            <p><label>Nom de la colonne contenant le courriel des utilisateurs</label>
                <input type="text" name="consigne_caisse_go_mail" value="<?php echo empty(get_option("consigne_caisse_go_mail")) ? "Courriel1" : get_option("consigne_caisse_go_mail"); ?>" /></p>
            <p><label>Nom de la colonne contenant le solde des utilisateurs</label>
                <input type="text" name="consigne_caisse_go_balance" value="<?php echo empty(get_option("consigne_caisse_go_balance")) ? "TotalRestant" : get_option("consigne_caisse_go_balance"); ?>" /></p>
            <input type="hidden" name="update_balances" value="1" />
            <?php submit_button("Go"); ?>
        </form>
        <?php
        if (!empty($this->users_updated)) {
        ?>
            <div class="notice notice-success">
                <p>A bien mis-à-jour les balances des utilisateurs suivants :</p>
                <ul>
                    <?php
                    foreach ($this->users_updated as $key => $user) {
                    ?><li>
                            - <?php echo $user; ?>
                            (<?php echo $this->users_updated_invoices[$user] . " facture(s)"; ?>)
                        </li><?php
                            }
                                ?>
                </ul>
            </div>
        <?php
        }
        if (!empty($this->users_missing)) {
        ?>

            <div class="notice notice-warning">
                <p>Il manque les données des utilisateurs suivants :</p>
                <ul>
                    <?php
                    foreach ($this->users_missing as $key => $user) {
                    ?><li>- <?php echo $user; ?></li><?php
                                                    }
                                                        ?>
                </ul>
            </div>
        <?php
        }
        if (!empty($this->users_failed)) {
        ?>

            <div class="notice notice-error">
                <p>La mise-à-jour des utilisateurs suivants a échoué :</p>
                <ul>
                    <?php
                    foreach ($this->users_failed as $key => $user) {
                    ?><li>- <?php echo $user; ?></li><?php
                                                    }
                                                        ?>
                </ul>
            </div>
        <?php
        }
        ?>

        <br class="clear">
<?php
    }

    public function get_csv_content($filename, $check_keys = array())
    {
        if (file_exists($filename)) {
            $file = fopen($filename, 'r');
            $array = array();
            $header = NULL;
            while (($line = fgetcsv($file)) !== FALSE) {
                if (empty($header)) {
                    $header = $line;
                    if (count(array_diff($header, $check_keys)) != count($header) - count($check_keys)) {
                        return false;
                    }
                } else {
                    $array[] = array_combine($header, $line);
                }
            }
            fclose($file);
            return $array;
        } else {
            throw FileNotFound();
        }
    }

    public function process_action()
    {
        if (isset($_POST["update_balances"])) {
            if (
                !get_option('consigne_caisse_db_pathfile_operations')
                && !get_option('consigne_caisse_db_pathfile_detail_operations')
                && !get_option('consigne_caisse_db_pathfile_contacts')
                && !get_option('consigne_caisse_db_pathfile_accomptes')
            ) {
                $this->uploadFirst = true;
            } else {
                global $wpdb;

                $column_mail = isset($_POST["consigne_caisse_go_mail"]) ? $_POST["consigne_caisse_go_mail"] : get_option("consigne_caisse_go_mail");
                $column_balance = isset($_POST["consigne_caisse_go_balance"]) ? $_POST["consigne_caisse_go_balance"] : get_option("consigne_caisse_go_balance");
                $format_date_ope = isset($_POST["consigne_caisse_go_format_date_ope"]) ? $_POST["consigne_caisse_go_format_date_ope"] : get_option("consigne_caisse_go_format_date_ope");
                $format_date_accomptes = isset($_POST["consigne_caisse_go_format_date_accomptes"]) ? $_POST["consigne_caisse_go_format_date_accomptes"] : get_option("consigne_caisse_go_format_date_accomptes");

                $filename_operations = get_option('consigne_caisse_db_pathfile_operations');
                $filename_accomptes = get_option('consigne_caisse_db_pathfile_accomptes');
                $filename_detail_operations = get_option('consigne_caisse_db_pathfile_detail_operations');
                $filename_contacts = get_option('consigne_caisse_db_pathfile_contacts');

                try {
                    $contacts = $this->get_csv_content($filename_contacts, array($column_mail, "tContactsPK"));
                    $detail_operations = $this->get_csv_content(
                        $filename_detail_operations,
                        array("IdOperation", "IdProduit", "DesignationProduit", "Quantite", "PrixUnitaire", "TauxTVA")
                    );
                    $operations = $this->get_csv_content($filename_operations, array("IDFKContacts", "DateOperation", "HeureOperation", $column_balance));
                    $accomptes = $this->get_csv_content($filename_accomptes, array("tContactsFK", "AccompteDate", "AccompteMt"));
                } catch (FileNotFound $e) {
                    $this->missingFile = true;
                    return false;
                }

                if (!$contacts || !$operations || !$detail_operations || !$accomptes) {
                    $this->wrongFiles = true;
                    return false;
                }
                $users_pk = array();

                foreach ($contacts as $key => $contact) {
                    if (!empty($contact[$column_mail])) {
                        $users_pk[esc_sql($contact[$column_mail])] = intval($contact["tContactsPK"]);
                    }
                }

                $accomptes_added = array();

                foreach ($accomptes as $key => $ope) {
                    $pk_user = intval($ope["tContactsFK"]);
                    if (!isset($accomptes_added[$pk_user])) {
                        $accomptes_added[$pk_user] = array();
                    }
                    $timestamp = DateTime::createFromFormat($format_date_accomptes, $ope["AccompteDate"]);
                    $accomptes_added[$pk_user][] = array(
                        "date" => $timestamp->format("Y-m-d"),
                        "valeur" => (float) $ope["AccompteMt"]
                    );
                }

                $users_balances = array();
                $users_operations = array();

                foreach ($operations as $key => $operation) {
                    $pk_user = intval($operation["IDFKContacts"]);
                    // $date = explode(" ", $operation["DateOperation"]);
                    // $time = explode(" ", $operation["HeureOperation"]);
                    // $timestamp = DateTime::createFromFormat('m/d/y H:i:s', $date[0] . " " . $time[1]);
                    $timestamp = DateTime::createFromFormat($format_date_ope, $operation["DateOperation"]);
                    // var_dump(DateTime::getLastErrors());

                    if (!isset($users_operations[$pk_user])) {
                        $users_operations[$pk_user] = array();
                    }
                    $users_operations[$pk_user][] = array("id" => $operation["IdOperation"], "date" => $timestamp->format("Y-m-d"));

                    if (isset($users_balances[$pk_user]) && $users_balances[$pk_user]["date"] < $timestamp) {
                        // && $users_balances[$pk_user]["date"]
                        $users_balances[$pk_user] = array("date" => $timestamp, "balance" => floatval($operation[$column_balance]));
                    } else if (!isset($users_balances[$pk_user])) {
                        $users_balances[$pk_user] = array("date" => $timestamp, "balance" => floatval($operation[$column_balance]));
                    }
                }

                $operations_details = array();

                foreach ($detail_operations as $key => $ope) {
                    if (!isset($operations_details[$ope["IdOperation"]])) {
                        $operations_details[$ope["IdOperation"]] = array();
                    }
                    $operations_details[$ope["IdOperation"]][] = array(
                        "produit" => $ope["DesignationProduit"],
                        "fournisseur" => $ope["idFournisseur"],
                        "quantite" => $ope["Quantite"],
                        "prix" => $ope["PrixUnitaire"]
                    );
                }

                // In case users are missing => default value.
                // REMOVED: instead, no entry in the db.
                // foreach ($users_pk as $email => $pk) {
                //     if( !array_key_exists($pk, $users_balances) ) {
                //         $users_balances[$pk] = array("balance"=>0.0);
                //     }
                // }

                $this->users_updated = array();
                $this->users_updated_values = array();
                $this->users_updated_invoices = array();
                $this->users_failed = array();
                $this->users_missing = array();

                $wpdb->query("TRUNCATE {$wpdb->prefix}consigne_caisse_soldes;");
                $wpdb->query("TRUNCATE {$wpdb->prefix}consigne_caisse_factures;");
                $wpdb->query("TRUNCATE {$wpdb->prefix}consigne_caisse_accomptes;");
                $rows_users = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}users WHERE user_email IN ('" . implode("', '", array_keys($users_pk)) . "')");
                $assoc_pk_users = array();

                foreach ($rows_users as $key => $user) {
                    $pk_user = $users_pk[$user->user_email];
                    $assoc_pk_users[$pk_user] = array("email" => $user->user_email, "wp_id" => $user->ID);
                }

                foreach ($users_pk as $email => $pk_user) {
                    if (array_key_exists($pk_user, $assoc_pk_users)) {
                        $id_user = $assoc_pk_users[$pk_user]["wp_id"];
                    } else {
                        $id_user = 0;
                    }

                    if (array_key_exists($pk_user, $users_operations)) {
                        $this->users_updated_invoices[$email] = 0;
                        foreach ($users_operations[$pk_user] as $ope_key => $ope_param) {
                            $id_ope = $ope_param["id"];
                            $date_ope = $ope_param["date"];
                            if (isset($operations_details[$id_ope]) && !empty($operations_details[$id_ope])) {
                                $ope_details = $operations_details[$id_ope];
                                $this->users_updated_invoices[$email]++;
                                foreach ($ope_details as $detail_key => $ope) {
                                    $res = $wpdb->insert(
                                        "{$wpdb->prefix}consigne_caisse_factures",
                                        array(
                                            'user_id' => $id_user,
                                            'email' => $email,
                                            'ope_id' => $id_ope,
                                            'date_ope' => $date_ope,
                                            'produit' => $ope["produit"],
                                            'fournisseur' => $ope["fournisseur"],
                                            'quantite' => $ope["quantite"],
                                            'prix' => $ope["prix"]
                                        )
                                    );
                                }
                            }
                        }
                    }

                    if (array_key_exists($pk_user, $users_balances)) {
                        $res = $wpdb->insert("{$wpdb->prefix}consigne_caisse_soldes", array('email' => $email, 'user_id' => $id_user, "balance" => $users_balances[$pk_user]["balance"]));
                        if ($res) {
                            $this->users_updated[] = $email;
                            $this->users_updated_values[] = $users_balances[$pk_user]["balance"];
                        } else {
                            $this->users_failed[] = $email;
                        }
                    } else {
                        $this->users_missing[] = $email;
                    }

                    if (array_key_exists($pk_user, $accomptes_added)) {
                        foreach ($accomptes_added[$pk_user] as $key => $ope) {
                            $res = $wpdb->insert("{$wpdb->prefix}consigne_caisse_accomptes", array('user_id' => $id_user, 'email' => $email, "date_ope" => $ope["date"], "valeur" => $ope["valeur"]));
                        }
                    }
                }
                update_option('consigne_caisse_last_updated', time());
                update_option('consigne_caisse_go_mail', $column_mail);
                update_option('consigne_caisse_go_balance', $column_balance);
                update_option('consigne_caisse_go_format_date_ope', $format_date_ope);
                update_option('consigne_caisse_go_format_date_accomptes', $format_date_accomptes);
            }
        } else if (isset($_FILES["consigne_caisse_upload_operations"], $_FILES["consigne_caisse_upload_contacts"], $_FILES["consigne_caisse_upload_detail_operations"], $_FILES["consigne_caisse_upload_accomptes"])) {
            $authorized_extensions = array("text/csv", "application/vnd.ms-excel");
            if (
                in_array($_FILES["consigne_caisse_upload_operations"]["type"], $authorized_extensions)
                && in_array($_FILES["consigne_caisse_upload_contacts"]["type"], $authorized_extensions)
                && in_array($_FILES["consigne_caisse_upload_accomptes"]["type"], $authorized_extensions)
                && in_array($_FILES["consigne_caisse_upload_detail_operations"]["type"], $authorized_extensions)
            ) {
                $movefile_operations = wp_handle_upload($_FILES["consigne_caisse_upload_operations"], array('test_form' => false));
                $movefile_detail_operations = wp_handle_upload($_FILES["consigne_caisse_upload_detail_operations"], array('test_form' => false));
                $movefile_contacts = wp_handle_upload($_FILES["consigne_caisse_upload_contacts"], array('test_form' => false));
                $movefile_accomptes = wp_handle_upload($_FILES["consigne_caisse_upload_accomptes"], array('test_form' => false));
                if (
                    $movefile_operations &&
                    !isset($movefile_operations['error']) &&
                    $movefile_detail_operations &&
                    !isset($movefile_detail_operations['error']) &&
                    $movefile_accomptes &&
                    !isset($movefile_accomptes['error']) &&
                    $movefile_contacts &&
                    !isset($movefile_contacts['error'])
                ) {
                    if (get_option('consigne_caisse_db_pathfile_operations') && file_exists(get_option('consigne_caisse_db_pathfile_operations'))) {
                        unlink(get_option('consigne_caisse_db_pathfile_operations'));
                    }
                    if (get_option('consigne_caisse_db_pathfile_detail_operations') && file_exists(get_option('consigne_caisse_db_pathfile_detail_operations'))) {
                        unlink(get_option('consigne_caisse_db_pathfile_detail_operations'));
                    }
                    if (get_option('consigne_caisse_db_pathfile_accomptes') && file_exists(get_option('consigne_caisse_db_pathfile_accomptes'))) {
                        unlink(get_option('consigne_caisse_db_pathfile_accomptes'));
                    }
                    if (get_option('consigne_caisse_db_pathfile_contacts') && file_exists(get_option('consigne_caisse_db_pathfile_contacts'))) {
                        unlink(get_option('consigne_caisse_db_pathfile_contacts'));
                    }
                    update_option('consigne_caisse_db_pathfile_accomptes', $movefile_accomptes["file"]);
                    update_option('consigne_caisse_db_pathfile_detail_operations', $movefile_detail_operations["file"]);
                    update_option('consigne_caisse_db_pathfile_operations', $movefile_operations["file"]);
                    update_option('consigne_caisse_db_pathfile_contacts', $movefile_contacts["file"]);
                    update_option('consigne_caisse_last_uploaded', time());
                    $this->uploadSucceeded = true;
                }
            } else {
                $this->wrongFileExtension = true;
            }
        }
    }
}

new ConsignePlugin();
