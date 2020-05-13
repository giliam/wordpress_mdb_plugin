<?php
defined('ABSPATH') or die();
function get_current_user_balance()
{
    if (is_user_logged_in()) {
        $user_pk = get_current_user_id();
        return get_user_balance($user_pk);
    } else {
        return "";
    }
}

function get_user_balance($user_pk, $email = NULL)
{
    global $wpdb;
    if (!empty($email)) {
        $query = $wpdb->get_row("SELECT balance FROM {$wpdb->prefix}consigne_caisse_soldes WHERE user_id = " . intval($user_pk) . " OR email = '" . esc_sql($email) . "'");
    } else {
        $query = $wpdb->get_row("SELECT balance FROM {$wpdb->prefix}consigne_caisse_soldes WHERE user_id = " . intval($user_pk));
    }
    if ($query) {
        return floatval($query->balance) . " €";
    } else {
        return "<em>Inconnu</em>";
    }
}
