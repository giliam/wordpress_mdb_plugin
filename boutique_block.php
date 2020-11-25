<?php
function caisse_register_blocks()
{
    // Check if Gutenberg is active.
    if (!function_exists('register_block_type')) {
        return;
    }

    // Add block script.ae
    wp_enqueue_script(
        'boutique-tags-list',
        plugins_url('blocks/boutique_tags_list.js', __FILE__),
        ['wp-blocks', 'wp-editor', "wp-element"]
    );

    // Add block style.
    wp_enqueue_style(
        'boutique-tags-list',
        plugins_url('blocks/boutique_tags_list.css', __FILE__),
        []
    );

    // Register block script and style.
    register_block_type('caisse/boutique-tags-list', array(
        'style' => 'boutiquetagslist-css', // Loads both on editor and frontend.
        'editor_script' => 'boutiquetagslist-js', // Loads only on editor.
    ));
}
add_action('enqueue_block_editor_assets', 'caisse_register_blocks');
