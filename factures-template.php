<?php

/**
 * Template Name: Example Page Template
 *
 * A template used to demonstrate how to include the template
 * using this plugin.
 *
 * @package PTE
 * @since 	1.0.0
 * @version	1.0.0
 */
defined('ABSPATH') or die();
include_once plugin_dir_path(__FILE__) . '/common.php';

?>

<?php
if (!is_user_logged_in()) {
    wp_redirect("/");
    exit;
}

global $wpdb;
$user_pk = get_current_user_id();
$query_invoices = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}consigne_caisse_factures WHERE user_id = " . intval($user_pk) . " ORDER BY date_ope DESC, ope_id, fournisseur, produit");
$balance = get_current_user_balance();
$invoices = array();
foreach ($query_invoices as $key => $invoice) {
    if (!array_key_exists($invoice->ope_id, $invoices)) {
        $invoices[$invoice->ope_id] = array("total" => 0.0, "date_ope" => $invoice->date_ope, "operations" => array());
    }

    $invoices[$invoice->ope_id]["total"] += (float) $invoice->prix * (float) $invoice->quantite;
    $invoices[$invoice->ope_id]["operations"][] = $invoice;
}
get_header();
?>
<section id="primary" class="content-area">
    <main id="main" class="site-main" role="main">

        <header class="page-header">
            <h1 class="page-title">Vos factures</h1>
        </header><!-- .page-header -->
        <div class="page-content">
            <div data-elementor-type="wp-post" data-elementor-settings="[]">
                <div class="elementor-inner">
                    <div class="elementor-section-wrap">
                        <?php
                        foreach ($invoices as $key => $invoice) {
                        ?>
                            <h4>
                                Commande du <?php $date_ope = DateTime::createFromFormat("Y-m-d 00:00:00", $invoice["date_ope"]);
                                            echo $date_ope->format("d m Y"); ?>
                            </h4>
                            <p>Vous avez acheté <?php echo count($invoice["operations"]); ?> produits pour un total de <?php echo number_format($invoice["total"], 2); ?>€</p>
                            <p onClick="hide_invoice('invoice<?php echo $key; ?>')" style="font-size:0.8em; padding-left: 10px; text-decoration: underline;"><em>... Cliquez ici pour voir le détail</em></p>
                            <table id="invoice<?php echo $key; ?>" style="display:none">
                                <tr>
                                    <th>Produit</th>
                                    <th>Quantité</th>
                                    <th>Prix</th>
                                    <th>Total</th>
                                </tr>
                                <?php
                                foreach ($invoice["operations"] as $key => $ope) {
                                ?>
                                    <tr>
                                        <td><?php echo $ope->produit; ?></td>
                                        <td><?php echo $ope->quantite; ?></td>
                                        <td><?php echo $ope->prix; ?></td>
                                        <td><?php echo $ope->quantite * $ope->prix; ?></td>
                                    </tr>
                                <?php
                                }
                                ?>
                            </table>
                        <?php
                        }

                        if (empty($invoices)) {
                            echo "<p>Aucune commande effectuée pour l'instant ou présente dans la base de données.</p>";
                        }
                        ?>
                    </div><!-- .page-content -->
                </div><!-- .page-content -->
            </div><!-- .page-content -->

        </div>
        <footer class="page-footer">
        </footer><!-- .page-footer -->

    </main><!-- #main -->

</section>
<script type="text/javascript">
    function hide_invoice(invoiceId) {
        var x = document.getElementById(invoiceId);
        if (x.style.display === "none") {
            x.style.display = "initial";
        } else {
            x.style.display = "none";
        }
    }
</script>
<?php get_sidebar(); ?>
<?php
get_footer();
