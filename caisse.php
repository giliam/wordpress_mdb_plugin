<?php

/**
 * Plugin Name: Caisse pour la consigne
 * Plugin URI: https://github.com/giliam/wordpress_mdb_plugin/
 * Description: Caisse pour la consigne
 * Version: 2.0
 * Author: Giliam
 * Author URI: https://github.com/giliam/
 */
defined('ABSPATH') or die();
include_once plugin_dir_path(__FILE__) . '/common.php';
include_once plugin_dir_path(__FILE__) . '/caisse_widget.php';

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
        $this->users_failed = array();
        $this->users_missing = array();

        register_activation_hook(__FILE__, array('ConsignePlugin', 'install'));
        register_uninstall_hook(__FILE__, array('ConsignePlugin', 'uninstall'));

        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('widgets_init', function () {
            register_widget('ConsigneCaisseWidget');
        });
    }

    public static function install()
    {
        global $wpdb;

        $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}consigne_caisse (id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(255), balance DOUBLE PRECISION DEFAULT 0.0, last_updated DATETIME DEFAULT CURRENT_TIMESTAMP);");

        update_option('consigne_caisse_go_mail', "Courriel1");
        update_option('consigne_caisse_go_balance', "TotalRestant");
    }

    public static function uninstall()
    {
        global $wpdb;

        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}consigne_caisse;");
        if (get_option('consigne_caisse_db_pathfile_operations') && file_exists(get_option('consigne_caisse_db_pathfile_operations'))) {
            unlink(get_option('consigne_caisse_db_pathfile_operations'));
        }
        if (get_option('consigne_caisse_db_pathfile_contacts') && file_exists(get_option('consigne_caisse_db_pathfile_contacts'))) {
            unlink(get_option('consigne_caisse_db_pathfile_contacts'));
        }
        delete_option('consigne_caisse_last_updated');
        delete_option('consigne_caisse_db_pathfile_operations');
        delete_option('consigne_caisse_db_pathfile_contacts');
        delete_option('consigne_caisse_last_uploaded');
        delete_option('consigne_caisse_go_mail');
        delete_option('consigne_caisse_go_balance');
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
        <h2>Your balance</h2>
        <p><strong><?php echo $balance; ?></strong></p>
        <h2>Upload a file</h2>
        <p>Last upload: <em><?php echo empty($last_uploaded) ? "Inconnu" : $last_uploaded; ?></em></p>
        <form method="post" action="" enctype="multipart/form-data">
            <?php
            if ($this->uploadSucceeded) {
            ?>
                <div class="updated">
                    <p>Upload succeeded!</p>
                </div>
                <?php
            }
                ?><?php
                    if ($this->wrongFileExtension) {
                    ?>
                <div class="notice notice-error">
                    <p>Wrong file, sorry!</p>
                </div>
            <?php
                    }
            ?>
            <p><label>T_Operation csv file</label>
                <input type="file" name="consigne_caisse_upload_operations" /></p>
            <p><label>tContacts csv file</label>
                <input type="file" name="consigne_caisse_upload_contacts" /></p>
            <?php submit_button("Upload"); ?>
        </form>

        <h2>Update the balances</h2>
        <p style="<?php if ($last_uploaded && $last_updated && $last_updated < $last_uploaded) { ?>color:red<?php } ?>">Last update: <em><?php echo empty($last_updated) ? "Inconnu" : $last_updated; ?></em> <?php if ($last_uploaded && $last_updated && $last_updated < $last_uploaded) { ?><strong>(outdated)</strong><?php } ?></p>
        <?php
        if ($this->missingFile) {
        ?>
            <div class="notice notice-error">
                <p>A file is missing!</p>
            </div>
        <?php
        }
        if ($this->wrongFiles) {
        ?>
            <div class="notice notice-error">
                <p>Are you sure about your files? There should be <code><?php echo get_option("consigne_caisse_go_mail") ?></code> and <code>tContactsPK</code> headers in <em>Contacts file</em> and <code>IDFKContacts</code>, <code>DateOperation</code>, <code>HeureOperation</code>, <code><?php echo get_option("consigne_caisse_go_balance"); ?></code>, <code>tContactsPK</code> in <em>T_Operation file</em>.</p>
            </div>
        <?php
        }
        if ($this->uploadFirst) {
        ?>
            <div class="notice notice-error">
                <p>Upload the database first!</p>
            </div>
        <?php
        }
        if ($this->errorMessage) {
        ?>
            <div class="notice notice-error">
                <p>Error during the import...</p>
                <p><?php echo $this->errorMessage; ?></p>
            </div>
        <?php
        }
        ?>
        <form method="post" action="">
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
                <p>Updated balance of following users succeeded:</p>
                <ul>
                    <?php
                    foreach ($this->users_updated as $key => $user) {
                    ?><li>- <?php echo $user; ?></li><?php
                                                    }
                                                        ?>
                </ul>
            </div>
        <?php
        }
        if (!empty($this->users_missing)) {
        ?>

            <div class="notice notice-warning">
                <p>Following users' data missing:</p>
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
                <p>Following users' updates failed:</p>
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
            if (!get_option('consigne_caisse_db_pathfile_operations') && !get_option('consigne_caisse_db_pathfile_contacts')) {
                $this->uploadFirst = true;
            } else {
                global $wpdb;

                $column_mail = isset($_POST["consigne_caisse_go_mail"]) ? $_POST["consigne_caisse_go_mail"] : get_option("consigne_caisse_go_mail");
                $column_balance = isset($_POST["consigne_caisse_go_balance"]) ? $_POST["consigne_caisse_go_balance"] : get_option("consigne_caisse_go_balance");

                $filename_operations = get_option('consigne_caisse_db_pathfile_operations');
                $filename_contacts = get_option('consigne_caisse_db_pathfile_contacts');

                try {
                    $contacts = $this->get_csv_content($filename_contacts, array($column_mail, "tContactsPK"));
                    $operations = $this->get_csv_content($filename_operations, array("IDFKContacts", "DateOperation", "HeureOperation", $column_balance));
                } catch (FileNotFound $e) {
                    $this->missingFile = true;
                    return false;
                }

                if (!$contacts || !$operations) {
                    $this->wrongFiles = true;
                    return false;
                }
                $users_pk = array();

                foreach ($contacts as $key => $contact) {
                    $users_pk[esc_sql($contact[$column_mail])] = intval($contact["tContactsPK"]);
                }


                $users_balances = array();

                foreach ($operations as $key => $operation) {
                    $pk_user = intval($operation["IDFKContacts"]);
                    // $date = explode(" ", $operation["DateOperation"]);
                    // $time = explode(" ", $operation["HeureOperation"]);
                    // $timestamp = DateTime::createFromFormat('m/d/y H:i:s', $date[0] . " " . $time[1]);
                    $timestamp = DateTime::createFromFormat('d/m/Y', $operation["DateOperation"]);
                    // var_dump(DateTime::getLastErrors());

                    if (isset($users_balances[$pk_user]) && $users_balances[$pk_user]["date"] < $timestamp) {
                        // && $users_balances[$pk_user]["date"]
                        $users_balances[$pk_user] = array("date" => $timestamp, "balance" => floatval($operation[$column_balance]));
                    } else if (!isset($users_balances[$pk_user])) {
                        $users_balances[$pk_user] = array("date" => $timestamp, "balance" => floatval($operation[$column_balance]));
                    }
                }

                // In case users are missing => default value.
                // REMOVED: instead, no entry in the db.
                // foreach ($users_pk as $email => $pk) {
                //     if( !array_key_exists($pk, $users_balances) ) {
                //         $users_balances[$pk] = array("balance"=>0.0);
                //     }
                // }

                $wpdb->query("TRUNCATE {$wpdb->prefix}consigne_caisse;");
                $rows_users = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}users WHERE user_email IN ('" . implode("', '", array_keys($users_pk)) . "')");

                $this->users_updated = array();
                $this->users_failed = array();
                $this->users_missing = array();
                foreach ($rows_users as $key => $user) {
                    if (array_key_exists($users_pk[$user->user_email], $users_balances)) {
                        $res = $wpdb->insert("{$wpdb->prefix}consigne_caisse", array('email' => $user->user_email, 'id' => $user->ID, "balance" => $users_balances[$users_pk[$user->user_email]]["balance"]));
                        if ($res) {
                            $this->users_updated[] = $user->user_email;
                        } else {
                            $this->users_failed[] = $user->user_email;
                        }
                    } else {
                        $this->users_missing[] = $user->user_email;
                    }
                }
                update_option('consigne_caisse_last_updated', date("d/m/Y H:i:s"));
                update_option('consigne_caisse_go_mail', $column_mail);
                update_option('consigne_caisse_go_balance', $column_balance);
            }
        } else if (isset($_FILES["consigne_caisse_upload_operations"]) && isset($_FILES["consigne_caisse_upload_contacts"])) {
            $authorized_extensions = array("text/csv", "application/vnd.ms-excel");
            if (in_array($_FILES["consigne_caisse_upload_operations"]["type"], $authorized_extensions) && in_array($_FILES["consigne_caisse_upload_contacts"]["type"], $authorized_extensions)) {
                $movefile_operations = wp_handle_upload($_FILES["consigne_caisse_upload_operations"], array('test_form' => false));
                $movefile_contacts = wp_handle_upload($_FILES["consigne_caisse_upload_contacts"], array('test_form' => false));
                if (
                    $movefile_operations &&
                    !isset($movefile_operations['error']) &&
                    $movefile_contacts &&
                    !isset($movefile_contacts['error'])
                ) {
                    if (get_option('consigne_caisse_db_pathfile_operations')) {
                        unlink(get_option('consigne_caisse_db_pathfile_operations'));
                    }
                    if (get_option('consigne_caisse_db_pathfile_contacts')) {
                        unlink(get_option('consigne_caisse_db_pathfile_contacts'));
                    }
                    update_option('consigne_caisse_db_pathfile_operations', $movefile_operations["file"]);
                    update_option('consigne_caisse_db_pathfile_contacts', $movefile_contacts["file"]);
                    update_option('consigne_caisse_last_uploaded', date("d/m/Y H:i:s"));
                    $this->uploadSucceeded = true;
                }
            } else {
                $this->wrongFileExtension = true;
            }
        }
    }
}

new ConsignePlugin();
