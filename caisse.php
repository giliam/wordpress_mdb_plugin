<?php
/**
 * Plugin Name: Caisse pour la consigne
 * Plugin URI: ...
 * Description: Caisse pour la consigne
 * Version: 1.0
 * Author: Giliam
 * Author URI: ...
 */
defined( 'ABSPATH' ) or die();
include_once plugin_dir_path( __FILE__ ).'/common.php';
include_once plugin_dir_path( __FILE__ ).'/caisse_widget.php';
class ConsignePlugin
{
    public function __construct()
    {
        $this->uploadFirst = false;
        $this->errorMessage = false;
        $this->wrongFileExtension = false;
        $this->uploadSucceeded = false;
        $this->users_updated = array();
        $this->users_failed = array();

        register_activation_hook(__FILE__, array('ConsignePlugin', 'install'));
        register_uninstall_hook(__FILE__, array('ConsignePlugin', 'uninstall'));

        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('widgets_init', function(){register_widget('ConsigneCaisseWidget');});
    }

    public static function install()
    {
        global $wpdb;

        $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}consigne_caisse (id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(255), balance DOUBLE PRECISION DEFAULT 0.0, last_updated DATETIME DEFAULT CURRENT_TIMESTAMP);");
    }

    public static function uninstall()
    {
        global $wpdb;

        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}consigne_caisse;");
        if( get_option('consigne_caisse_db_folder') ) {
            unlink(get_option('consigne_caisse_db_folder'));
        }
        delete_option('consigne_caisse_last_updated');
        delete_option('consigne_caisse_db_folder');
        delete_option('consigne_caisse_last_uploaded');
    }

    public function add_admin_menu()
    {
        $hook = add_menu_page('Consigne Caisse', 'Caisse', 'manage_options', 'consigne_caisse', array($this, 'menu_html'));
        add_action('load-'.$hook, array($this, 'process_action'));
    }

    public function menu_html()
    {
        $balance = get_current_user_balance();
        $last_uploaded = get_option('consigne_caisse_last_uploaded');
        $last_updated = get_option('consigne_caisse_last_updated');

        echo '<h1>'.get_admin_page_title().'</h1>';
        ?>
        <h2>Your balance</h2>
        <p><strong><?php echo $balance; ?></strong></p>
        <h2>Upload a file</h2>
        <p>Last upload: <em><?php echo empty($last_uploaded) ? "Inconnu" : $last_uploaded; ?></em></p>
        <form method="post" action="" enctype="multipart/form-data">
            <?php 
            if( $this->uploadSucceeded ) {
            ?>
            <div class="updated">
                <p>Upload succeeded!</p>
            </div>
            <?php
            }
            ?><?php 
            if( $this->wrongFileExtension ) {
            ?>
            <div class="notice notice-error">
                <p>Wrong file, sorry!</p>
            </div>
            <?php
            }
            ?>
            <label>File to upload</label>
            <input type="file" name="consigne_caisse_upload" />
            <?php submit_button("Upload"); ?>
        </form>

        <h2>Update the balances</h2>
        <p style="<?php if( $last_uploaded && $last_updated && $last_updated < $last_uploaded ) { ?>color:red<?php } ?>">Last update: <em><?php echo empty($last_updated) ? "Inconnu" : $last_updated; ?></em> <?php if( $last_uploaded && $last_updated && $last_updated < $last_uploaded ) { ?><strong>(outdated)</strong><?php } ?></p>
            <?php 
            if( $this->uploadFirst ) {
            ?>
            <div class="notice notice-error">
                <p>Upload the database first!</p>
            </div>
            <?php
            }
            if( $this->errorMessage ) {
            ?>
            <div class="notice notice-error">
                <p>Error during the import...</p>
                <p><?php echo $this->errorMessage; ?></p>
            </div>
            <?php
            }
            ?>
        <form method="post" action="">
            <input type="hidden" name="update_balances" value="1"/>
            <?php submit_button("Go"); ?>
        </form>
        <?php 
        if( !empty($this->users_updated) ) {
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
        if( !empty($this->users_failed) ) {
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

    public function process_action()
    {
        if( isset($_POST["update_balances"]) ){
            if( !get_option('consigne_caisse_db_folder')) {
                $this->uploadFirst = true;
            }
            else {
                global $wpdb;
    
                $dbName = get_option('consigne_caisse_db_folder'); 
                 
                $dbh = new  PDO("odbc:Driver=" . $driver . ";DBQ=" . $dbName . ";");
                $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                try {
                    $sql = "SELECT tContactsPK, Courriel1 FROM tContacts";  // The rules are the same as above 
                    $sth = $dbh->prepare($sql); 
                    $sth->execute();
                }
                catch(PDOException $e) {
                    $this->errorMessage = 'Exception -> ' . $e->getMessage();
                    return false;
                }

                $users_pk = array();

                while ($flg = $sth->fetch(PDO::FETCH_ASSOC)){ 
                    $users_pk[esc_sql($flg["Courriel1"])] = intval($flg["tContactsPK"]);
                }

                // tAccomptes contient les "accomptes" versés par les personnes.

                try {
                    $sql = "SELECT * FROM T_Operation";  // The rules are the same as above 
                    $sth = $dbh->prepare($sql); 
                    $sth->execute(); 
                }
                catch(Exception $e) {
                    $this->errorMessage = 'Exception -> ' . $e->getMessage();
                    return false;
                }
                $users_balances = array();

                while ($flg = $sth->fetch(PDO::FETCH_ASSOC)){
                    $pk_user = intval($flg["IDFKContacts"]);
                    $date = explode(" ", $flg["DateOperation"]);
                    $time = explode(" ", $flg["HeureOperation"]);
                    $timestamp = DateTime::createFromFormat('m/d/y H:i:s', $date[0] . " " . $time[1]);
                    // var_dump(DateTime::getLastErrors());

                    if( isset($users_balances[$pk_user]) && $users_balances[$pk_user]["date"] < $timestamp ) {
                        // && $users_balances[$pk_user]["date"]
                        $users_balances[$pk_user] = array("date"=>$timestamp, "balance"=>floatval($flg["TotalRestant"]));
                    }
                    else if( !isset($users_balances[$pk_user]) ) {
                        $users_balances[$pk_user] = array("date"=>$timestamp, "balance"=>floatval($flg["TotalRestant"]));   
                    }
                }

                foreach ($users_pk as $email => $pk) {
                    if( !array_key_exists($pk, $users_balances) ) {
                        $users_balances[$pk] = array("balance"=>0.0);
                    }
                }

                $wpdb->query("TRUNCATE {$wpdb->prefix}consigne_caisse;");
                $rows_users = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}users WHERE user_email IN ('" . implode("', '", array_keys($users_pk)) . "')");

                $this->users_updated = array();
                $this->users_failed = array();
                foreach ($rows_users as $key => $user) {
                    $res = $wpdb->insert("{$wpdb->prefix}consigne_caisse", array('email' => $user->user_email, 'id' => $user->ID, "balance" => $users_balances[$users_pk[$user->user_email]]["balance"]));
                    if($res){
                        $this->users_updated[] = $user->user_email;
                    }
                    else {
                        $this->users_failed[] = $user->user_email;
                    }
                }
                update_option('consigne_caisse_last_updated', date("d/m/Y H:i:s"));
            }
        }
        else if( isset($_FILES["consigne_caisse_upload"])) {
            $upload_dir = plugin_dir_path( __FILE__ ) . "/uploads/";
            $uploadfile = $upload_dir . basename($_FILES['consigne_caisse_upload']['name']);
            if( $_FILES["consigne_caisse_upload"]["type"] == "application/vnd.ms-access") {
                define('ALLOW_UNFILTERED_UPLOADS', true);
                $movefile = wp_handle_upload($_FILES["consigne_caisse_upload"], array('test_form' => false));
                define('ALLOW_UNFILTERED_UPLOADS', false);
                if( $movefile && ! isset($movefile['error']) ) {
                    if( get_option('consigne_caisse_db_folder') ) {
                        unlink(get_option('consigne_caisse_db_folder'));
                    }
                    update_option('consigne_caisse_db_folder', $movefile["file"]);
                    update_option('consigne_caisse_last_uploaded', date("d/m/Y H:i:s"));
                    $this->uploadSucceeded = true;
                }
            }
            else {
                $this->wrongFileExtension = true;
            }
        }
    }
}

new ConsignePlugin();