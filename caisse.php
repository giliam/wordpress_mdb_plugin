<?php
/**
 * Plugin Name: Caisse pour la consigne
 * Plugin URI: ...
 * Description: Caisse pour la consigne
 * Version: 1.0
 * Author: Giliam
 * Author URI: ...
 */
add_action( 'the_content', 'my_thank_you_text' );

function my_thank_you_text ( $content ) {
    return $content .= '<p>Thank you for reading!</p>';
}