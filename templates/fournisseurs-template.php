<?php

/**
 * Template Name: Fournisseurs list
 *
 * A template showing, in the member space, the fournisseurs available
 *
 * @package caisse_consigne
 * @since 	1.0.0
 * @version	1.0.0
 */
defined('ABSPATH') or die();
include_once plugin_dir_path(__FILE__) . '../common.php';

?>

<?php
if (!is_user_logged_in()) {
    wp_redirect("/membres/");
    exit;
}

global $wpdb;

function __print($d)
{
    echo "<pre>";
    var_dump($d);
    echo "</pre>";
}

get_header();
?>
<section id="primary" class="content-area">
    <main id="main" class="site-main" role="main">

        <header class="page-header">
            <h1 class="page-title">Les fournisseurs</h1>
        </header><!-- .page-header -->
        <div class="page-content">
            <div data-elementor-type="wp-post" data-elementor-settings="[]">
                <div class="elementor-inner">
                    <div class="elementor-section-wrap">
                        <?php
                        $terms = get_tags(array('taxonomy' => 'product_tag', 'hide_empty' => false));
                        $tag_id = array();
                        $tag_count_elements = array();
                        foreach ($terms as $tag) {
                            $tag_id[] = $tag->term_id;
                            $tag_count_elements[$tag->term_id] = 0;
                        }
                        $args_posts = array(
                            'post_type' => 'product',
                            'posts_per_page' => '-1',
                            'term__in' => $tag_id
                        );
                        $posts = get_posts($args_posts);
                        foreach ($posts as $key => $value) {
                            $terms_post = get_the_terms($value->ID, "product_tag");
                            foreach ($terms_post as $term) {
                                $tag_count_elements[$term->term_id]++;
                            }
                        }

                        ?>
                        <div class="product-tags">
                            <ul>
                                <?php foreach ($terms as $term) {
                                    $nb_prods = $tag_count_elements[$term->term_id]
                                ?>
                                    <li>
                                        <a href="<?php echo get_term_link($term->term_id, 'product_tag'); ?> " rel="tag"><?php echo $term->name; ?></a> - <?php echo $nb_prods;
                                                                                                                                                            echo " " . ($nb_prods > 1 ? "produits" : "produit"); ?>
                                    </li>
                                <?php } ?>
                            </ul>
                        </div>
                    </div><!-- .page-content -->
                </div><!-- .page-content -->
            </div><!-- .page-content -->

        </div>
        <footer class="page-footer">
        </footer><!-- .page-footer -->

    </main><!-- #main -->

</section>
<?php get_sidebar(); ?>
<?php
get_footer();
