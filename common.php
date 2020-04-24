<?php
defined('ABSPATH') or die();
function get_current_user_balance()
{
    if (is_user_logged_in()) {
        global $wpdb;
        $user_pk = get_current_user_id();
        $query = $wpdb->get_row("SELECT balance FROM {$wpdb->prefix}consigne_caisse_soldes WHERE user_id = " . intval($user_pk));
        if ($query) {
            return floatval($query->balance) . " €";
        } else {
            return "<em>Inconnu</em>";
        }
    } else {
        return "";
    }
}
