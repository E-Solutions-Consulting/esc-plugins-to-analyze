<?php
/**
 * Product Export (SKU + Main Image URL)
 *
 * A small admin tool for the marketing team: one button that generates and
 * downloads a CSV of every product's SKU and main image URL. Covers published
 * products and (optionally) their variations, so variable-product SKUs like the
 * plan bundles are included.
 *
 * Read-only — it only reads product data and streams a CSV; it never writes
 * anything. Lives under the Brello admin menu.
 *
 * @package    BH_Features
 * @subpackage WC/Products
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BH_Product_Export_Admin {

    const ACTION = 'bh_product_export';
    const NONCE  = 'bh_product_export_nonce';

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'add_admin_page' ], 60 );
        add_action( 'admin_post_' . self::ACTION, [ __CLASS__, 'handle_download' ] );
    }

    public static function add_admin_page() {
        $parent = defined( 'PARENT_MENU_SLUG' ) ? PARENT_MENU_SLUG : 'bh-features';
        add_submenu_page(
            $parent,
            'Product Export',
            'Product Export',
            'manage_woocommerce',
            $parent . '--product-export',
            [ __CLASS__, 'render_admin_page' ]
        );
    }

    /**
     * Settings-style page with a single download button.
     */
    public static function render_admin_page() {
        ?>
        <div class="wrap">
            <h1>Product Export</h1>
            <p class="description" style="max-width:760px;">
                Download a CSV of every product's <strong>SKU</strong> and
                <strong>main image URL</strong> (the featured image). Use this for the
                marketing team's asset list. Nothing is modified — this only reads product data.
            </p>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
                <?php wp_nonce_field( self::ACTION, self::NONCE ); ?>

                <p>
                    <label>
                        <input type="checkbox" name="include_variations" value="1" checked>
                        Include variations (recommended — variable products keep their SKUs on variations)
                    </label>
                </p>

                <?php submit_button( 'Download CSV', 'primary' ); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Build the CSV and stream it to the browser.
     */
    public static function handle_download() {

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to export products.', 'bh-features' ) );
        }

        check_admin_referer( self::ACTION, self::NONCE );

        if ( ! function_exists( 'wc_get_products' ) ) {
            wp_die( esc_html__( 'WooCommerce is not active.', 'bh-features' ) );
        }

        $include_variations = ! empty( $_POST['include_variations'] );

        // Stream the CSV — no buffering, so large catalogs don't blow memory.
        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=brello-products-' . gmdate( 'Ymd-His' ) . '.csv' );

        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, [ 'sku', 'product_id', 'type', 'name', 'main_image_url' ] );

        $paged = 1;
        $per   = 100;

        do {
            $products = wc_get_products( [
                'status'  => 'publish',
                'limit'   => $per,
                'page'    => $paged,
                'orderby' => 'ID',
                'order'   => 'ASC',
                'return'  => 'objects',
            ] );

            foreach ( $products as $product ) {

                $image_url = wp_get_attachment_image_url( $product->get_image_id(), 'full' );

                fputcsv( $out, [
                    $product->get_sku(),
                    $product->get_id(),
                    $product->get_type(),
                    $product->get_name(),
                    $image_url ?: '',
                ] );

                if ( $include_variations && $product->is_type( 'variable' ) ) {
                    foreach ( $product->get_children() as $variation_id ) {
                        $variation = wc_get_product( $variation_id );
                        if ( ! $variation ) {
                            continue;
                        }

                        $var_img = wp_get_attachment_image_url( $variation->get_image_id(), 'full' );
                        if ( ! $var_img ) {
                            $var_img = $image_url; // fall back to parent main image
                        }

                        fputcsv( $out, [
                            $variation->get_sku(),
                            $variation->get_id(),
                            'variation',
                            $variation->get_name(),
                            $var_img ?: '',
                        ] );
                    }
                }
            }

            $paged++;
        } while ( count( $products ) === $per );

        fclose( $out );
        exit;
    }
}

BH_Product_Export_Admin::init();
