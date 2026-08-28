<?php
/**
 * Attentive Integration - Catalog Feed Generator
 *
 * Builds the NDJSON product catalog feed Attentive polls from
 * uploads/bh-exports/attentive-catalog.ndjson. Lives under uploads instead of
 * the plugin folder because uploads is always writable by PHP and survives
 * plugin updates/reinstalls. Only products explicitly checked in the Catalog
 * Feed tab are included, since the store also holds test copies, drafts and
 * one-off offers that should never reach Attentive.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BH_Attentive_Catalog_Feed {

    const LAST_GENERATED_OPTION = 'bh_attentive_catalog_last_generated';
    const EXPORT_SUBDIR         = 'bh-exports';
    const FILENAME              = 'attentive-catalog.ndjson';

    /**
     * Absolute path to the uploads/bh-exports directory, creating it (with an
     * index.php stub to block directory listing) the first time it's needed.
     */
    private static function get_export_dir() {
        $upload_dir = wp_upload_dir();
        $dir        = trailingslashit( $upload_dir['basedir'] ) . self::EXPORT_SUBDIR;

        if ( ! file_exists( $dir ) ) {
            wp_mkdir_p( $dir );
            $index = $dir . '/index.php';
            if ( ! file_exists( $index ) ) {
                file_put_contents( $index, "<?php\n// Silence is golden.\n" );
            }
        }

        return $dir;
    }

    /**
     * Absolute path to the feed file.
     */
    public static function get_file_path() {
        return self::get_export_dir() . '/' . self::FILENAME;
    }

    /**
     * Public URL Attentive should poll — also used as the downloadable link
     * in the admin UI.
     */
    public static function get_feed_url() {
        $upload_dir = wp_upload_dir();
        return trailingslashit( $upload_dir['baseurl'] ) . self::EXPORT_SUBDIR . '/' . self::FILENAME;
    }

    /**
     * File size + mtime for display in the admin UI, or null if not
     * generated yet.
     */
    public static function get_file_info() {
        $path = self::get_file_path();
        if ( ! file_exists( $path ) ) {
            return null;
        }

        return array(
            'size'  => size_format( filesize( $path ) ),
            'mtime' => filemtime( $path ),
        );
    }

    /**
     * Selectable products for the admin checkbox table — simple and variable
     * products only, any status except trash, so a product can be pre-selected
     * before it's published.
     */
    public static function get_selectable_products() {
        $query = new WP_Query( array(
            'post_type'      => 'product',
            'post_status'    => array( 'publish', 'private', 'draft' ),
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'fields'         => 'ids',
        ) );

        $products = array();
        foreach ( $query->posts as $post_id ) {
            $product = wc_get_product( $post_id );
            if ( $product ) {
                $products[] = $product;
            }
        }

        return $products;
    }

    /**
     * Builds the ndjson lines from the checked product IDs and writes them
     * to the feed file.
     */
    public static function generate() {
        if ( ! function_exists( 'wc_get_product' ) ) {
            return array( 'success' => false, 'message' => 'WooCommerce is not active.' );
        }

        $settings    = BH_Attentive_Config::get_settings();
        $product_ids = array_filter( array_map( 'absint', (array) ( $settings['catalog_products'] ?? array() ) ) );

        if ( empty( $product_ids ) ) {
            return array( 'success' => false, 'message' => 'No products are checked. Check at least one product below, save settings, then generate.' );
        }

        $lines = array();
        foreach ( $product_ids as $product_id ) {
            $product = wc_get_product( $product_id );
            if ( ! $product || $product->get_status() === 'trash' ) {
                continue;
            }

            $entry = self::build_product_entry( $product );
            if ( $entry ) {
                $lines[] = wp_json_encode( $entry, JSON_UNESCAPED_SLASHES );
            }
        }

        if ( empty( $lines ) ) {
            return array( 'success' => false, 'message' => 'None of the checked products could be exported — they may have been deleted or moved to trash.' );
        }

        self::get_export_dir(); // ensure uploads/bh-exports exists before writing

        $written = file_put_contents( self::get_file_path(), implode( "\n", $lines ) . "\n", LOCK_EX );

        if ( $written === false ) {
            return array( 'success' => false, 'message' => 'Could not write ' . self::FILENAME . '. Check that ' . self::get_export_dir() . ' is writable by the web server.' );
        }

        update_option( self::LAST_GENERATED_OPTION, time() );

        return array(
            'success' => true,
            'count'   => count( $lines ),
            'message' => sprintf( 'Catalog feed generated with %d product(s).', count( $lines ) ),
        );
    }

    private static function build_product_entry( $product ) {
        $product_id = $product->get_id();
        $catalog_id = self::get_catalog_id( $product );

        $variants = $product->is_type( 'variable' )
            ? self::build_variation_variants( $product, $catalog_id )
            : array( self::build_simple_variant( $product, $catalog_id ) );

        if ( empty( $variants ) ) {
            return null;
        }

        return array(
            'id'          => $catalog_id,
            'name'        => $product->get_name(),
            'description' => self::get_description( $product ),
            'link'        => get_permalink( $product_id ),
            'lastUpdated' => self::get_last_updated( $product_id ),
            'variants'    => $variants,
        );
    }

    private static function get_catalog_id( $product ) {
        $sku = $product->get_sku();
        return $sku !== '' ? $sku : (string) $product->get_id();
    }

    private static function build_simple_variant( $product, $catalog_id ) {
        return array(
            'id'                   => $catalog_id . '-DEFAULT',
            'name'                 => $product->get_name(),
            'prices'               => array( self::build_price( $product ) ),
            'availableForPurchase' => self::is_available( $product ),
            'link'                 => get_permalink( $product->get_id() ),
            'lastUpdated'          => self::get_last_updated( $product->get_id() ),
        );
    }

    private static function build_variation_variants( $product, $catalog_id ) {
        $variants  = array();
        $base_link = get_permalink( $product->get_id() );

        foreach ( $product->get_children() as $variation_id ) {
            $variation = wc_get_product( $variation_id );
            if ( ! $variation || $variation->get_status() === 'trash' ) {
                continue;
            }

            $link = $base_link;
            foreach ( $variation->get_variation_attributes() as $attribute => $value ) {
                if ( $value !== '' ) {
                    $link = add_query_arg( $attribute, $value, $link );
                }
            }

            $suffix = wc_get_formatted_variation( $variation, true, false );

            $variants[] = array(
                'id'                   => $catalog_id . '-' . $variation_id,
                'name'                 => $suffix !== '' ? $product->get_name() . ' - ' . $suffix : $product->get_name(),
                'prices'               => array( self::build_price( $variation ) ),
                'availableForPurchase' => self::is_available( $variation ),
                'link'                 => $link,
                'lastUpdated'          => self::get_last_updated( $variation_id ),
            );
        }

        return $variants;
    }

    private static function build_price( $product ) {
        $price = $product->get_price();
        return array(
            'currencyCode' => get_woocommerce_currency(),
            'amount'       => $price !== '' ? number_format( (float) $price, 2, '.', '' ) : '0.00',
        );
    }

    private static function is_available( $product ) {
        return $product->is_purchasable() && $product->is_in_stock();
    }

    private static function get_description( $product ) {
        $description = $product->get_short_description();
        if ( trim( wp_strip_all_tags( $description ) ) === '' ) {
            $description = $product->get_description();
        }

        $description = wp_strip_all_tags( $description );
        return trim( preg_replace( '/\s+/', ' ', $description ) );
    }

    private static function get_last_updated( $post_id ) {
        $timestamp = get_post_time( 'U', true, $post_id );
        if ( ! $timestamp ) {
            $timestamp = time();
        }
        return gmdate( 'Y-m-d\TH:i:sP', $timestamp );
    }
}
