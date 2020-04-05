<?php
/**
 * Plugin Name: Caisse pour la consigne
 * Plugin URI: ...
 * Description: Caisse pour la consigne
 * Version: 1.0
 * Author: Giliam
 * Author URI: ...
 */
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
class ConsignePlugin
{
    public function __construct()
    {
        $this->uploadSucceeded = false;
        $this->users_updated = array();
        $this->users_failed = array();

        register_activation_hook(__FILE__, array('ConsignePlugin', 'install'));
        register_uninstall_hook(__FILE__, array('ConsignePlugin', 'uninstall'));

        add_action('admin_menu', array($this, 'add_admin_menu'));
    }

    public static function install()
    {
        global $wpdb;

        $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}consigne_caisse (id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(255), balance DOUBLE PRECISION DEFAULT 0.0);");
    }

    public static function uninstall()
    {
        global $wpdb;

        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}consigne_caisse;");
    }

    public function add_admin_menu()
    {
        $hook = add_menu_page('Consigne Caisse', 'Caisse', 'manage_options', 'consigne_caisse', array($this, 'menu_html'));
        add_action('load-'.$hook, array($this, 'process_action'));
    }

    public function menu_html()
    {
        echo '<h1>'.get_admin_page_title().'</h1>';
        ?>
        <h2>Upload a file</h2>
        <form method="post" action="" enctype="multipart/form-data">
            <?php 
            if( $this->uploadSucceeded ) {
            ?>
            <div class="updated">
                <p>Upload succeeded!</p>
            </div>
            <?php
            }
            ?>
            <label>File to upload</label>
            <input type="file" name="consigne_caisse_upload" />
            <?php submit_button("Upload"); ?>
        </form>

        <h2>Update the balances</h2>
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
            global $wpdb;

            $upload_dir = wp_upload_dir()["basedir"] . "/caisse/";
            $db = 'AssociationData.mdb';
 
            $dbName = $upload_dir . $db  ; 
            $driver = 'MDBTools'; 
             
            $dbh = new  PDO("odbc:Driver=" . $driver . ";DBQ=" . $dbName . ";");

            $sql = "SELECT tContactsPK, Courriel1 FROM tContacts";  // The rules are the same as above 
            $sth = $dbh->prepare($sql); 
            $sth->execute(); 

            $users_pk = array();

            while ($flg = $sth->fetch(PDO::FETCH_ASSOC)){ 
                $users_pk[esc_sql($flg["Courriel1"])] = intval($flg["tContactsPK"]);
            }

            $wpdb->query("TRUNCATE {$wpdb->prefix}consigne_caisse;");
            $rows_users = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}users WHERE user_email IN ('" . implode("', '", array_keys($users_pk)) . "')");

            $this->users_updated = array();
            $this->users_failed = array();
            foreach ($rows_users as $key => $user) {
                $res = $wpdb->insert("{$wpdb->prefix}consigne_caisse", array('email' => $user->user_email, 'id' => $user->ID, "balance" => $users_pk[$user->user_email]));
                if($res){
                    $this->users_updated[] = $user->user_email;
                }
                else {
                    $this->users_failed[] = $user->user_email;
                }
            }
        }
        else if( isset($_FILES["consigne_caisse_upload"])) {
            $upload_dir = wp_upload_dir()["basedir"] . "/caisse/";
            $uploadfile = $upload_dir . basename($_FILES['consigne_caisse_upload']['name']);

            if (move_uploaded_file($_FILES['consigne_caisse_upload']['tmp_name'], $uploadfile)) {
                $this->uploadSucceeded = true;
            }
        }
    }
}

new ConsignePlugin();