<?php
defined('ABSPATH') or die();
include_once plugin_dir_path(__FILE__) . '/common.php';
class ConsigneCaisseWidget extends WP_Widget
{
    public function __construct()
    {
        parent::__construct('consigne_caisse_balance', 'Accompte restant', array('description' => 'Affiche le solde du compte.'));
    }

    public function widget($args, $instance)
    {
        if (is_user_logged_in()) {
            echo $args['before_widget'];
            echo $args['before_title'];
            echo apply_filters('widget_title', $instance['title']);
            echo $args['after_title'];
?>
            <p><strong>Votre solde :</strong> <?php echo get_current_user_balance(); ?></p>
            <p><a href="/membres/factures/">Historique de vos commandes</a></p>
        <?php
        }
    }

    public function form($instance)
    {
        $title = isset($instance['title']) ? $instance['title'] : '';
        ?>
        <p>
            <label for="<?php echo $this->get_field_name('title'); ?>"><?php _e('Title:'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo  $title; ?>" />
        </p>
<?php
    }
}
