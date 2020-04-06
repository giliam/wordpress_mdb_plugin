<?php
defined( 'ABSPATH' ) or die();
function get_current_user_balance()
{
    global $wpdb;
    $user_pk = get_current_user_id();
    $query = $wpdb->get_row("SELECT balance FROM {$wpdb->prefix}consigne_caisse WHERE id = " . intval($user_pk));
    if( $query ) {
        return floatval($query->balance) . " €";
    }
    else {
        return "<em>Inconnu</em>";
    }
}