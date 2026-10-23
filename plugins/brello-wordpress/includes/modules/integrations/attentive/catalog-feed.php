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
     * Provisional: Attentive rejects local-only hosts (dev.brellohealth.test,
     * the brellohealth.test "production mirror", etc.) as "not a valid web
     * URL", so every link gets rewritten to the real live domain regardless
     * of which environment generated the feed. This is a stopgap for as long
     * as anything other than the actual live site can run this — once only
     * the live site ever generates/sends the catalog, this rewrite (and the
     * constant) can go away and get_permalink() can be trusted as-is.
     */
    const LIVE_DOMAIN = 'https://www.brellohealth.com';

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

        self::get_export_dir();

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

        $entry = array(
            'id'          => $catalog_id,
            'name'        => self::decode_name( $product->get_name() ),
            'description' => self::get_description( $product ),
            'link'        => self::to_live_domain( get_permalink( $product_id ) ),
            'lastUpdated' => self::get_last_updated( $product_id ),
            'variants'    => $variants,
        );

        $images = self::build_images( $product, $catalog_id );
        if ( ! empty( $images ) ) {
            $entry['images'] = $images;
        }

        return $entry;
    }

    /**
     * Images live at the product level per Attentive's schema (not per
     * variant): https://docs.attentive.com/reference/product-catalog. The
     * main product image goes in with no variantIds (applies to all
     * variants); a variation's own distinct image (when set and different
     * from the parent's) is added separately, scoped to that variant via
     * variantIds so Attentive shows the right photo for that option.
     */
    private static function build_images( $product, $catalog_id ) {
        $images        = array();
        $main_image_id = $product->get_image_id();

        if ( $main_image_id ) {
            $src = wp_get_attachment_image_url( $main_image_id, 'full' );
            if ( $src ) {
                $images[] = array( 'src' => self::to_live_domain( $src ) );
            }
        }

        if ( $product->is_type( 'variable' ) ) {
            foreach ( $product->get_children() as $variation_id ) {
                $variation = wc_get_product( $variation_id );
                if ( ! $variation || $variation->get_status() === 'trash' ) {
                    continue;
                }

                $variation_image_id = $variation->get_image_id();
                if ( ! $variation_image_id || $variation_image_id === $main_image_id ) {
                    continue;
                }

                $src = wp_get_attachment_image_url( $variation_image_id, 'full' );
                if ( $src ) {
                    $images[] = array(
                        'src'        => self::to_live_domain( $src ),
                        'variantIds' => array( $catalog_id . '-' . $variation_id ),
                    );
                }
            }
        }

        return $images;
    }

    private static function get_catalog_id( $product ) {
        $sku = $product->get_sku();
        return $sku !== '' ? $sku : (string) $product->get_id();
    }

    /**
     * Rewrites a get_permalink() URL from whatever host this environment
     * runs on to LIVE_DOMAIN. See the LIVE_DOMAIN docblock above.
     */
    private static function to_live_domain( $url ) {
        $current_origin = home_url();
        if ( strpos( $url, $current_origin ) === 0 ) {
            return self::LIVE_DOMAIN . substr( $url, strlen( $current_origin ) );
        }
        return $url;
    }

    private static function build_simple_variant( $product, $catalog_id ) {
        return array(
            'id'                   => $catalog_id . '-DEFAULT',
            'name'                 => self::decode_name( $product->get_name() ),
            'prices'               => array( self::build_price( $product ) ),
            'availableForPurchase' => self::is_available( $product ),
            'link'                 => self::to_live_domain( get_permalink( $product->get_id() ) ),
            'lastUpdated'          => self::get_last_updated( $product->get_id() ),
        );
    }

    private static function build_variation_variants( $product, $catalog_id ) {
        $variants  = array();
        $base_link = self::to_live_domain( get_permalink( $product->get_id() ) );

        foreach ( $product->get_children() as $variation_id ) {
            $variation = wc_get_product( $variation_id );
            if ( ! $variation || $variation->get_status() === 'trash' ) {
                continue;
            }

            $suffix = wc_get_formatted_variation( $variation, true, false );
            $name   = $suffix !== '' ? $product->get_name() . ' - ' . $suffix : $product->get_name();

            $variants[] = array(
                'id'                   => $catalog_id . '-' . $variation_id,
                'name'                 => self::decode_name( $name ),
                'prices'               => array( self::build_price( $variation ) ),
                'availableForPurchase' => self::is_available( $variation ),
                'link'                 => self::build_variation_link( $base_link, $variation ),
                'lastUpdated'          => self::get_last_updated( $variation_id ),
            );
        }

        return $variants;
    }

    /**
     * Appends all variation attributes to the base product link as query
     * args in a single add_query_arg() call. add_query_arg() concatenates
     * its values raw (it calls build_query() with urlencode disabled), so a
     * plain-text attribute value with spaces would otherwise land in the URL
     * unencoded and fail Attentive's URL validation — rawurlencode() fixes
     * that. Taxonomy attribute values (attribute_pa_*) are already URL-safe
     * slugs and pass through unchanged. Batching every attribute into one
     * call (instead of one add_query_arg() call per attribute) matters when
     * a variation has more than one: add_query_arg() re-parses and
     * re-encodes whatever query string already exists on each call, which
     * would silently turn an earlier attribute's %20 into a + instead.
     */
    private static function build_variation_link( $base_link, $variation ) {
        $args = array();
        foreach ( $variation->get_variation_attributes() as $attribute => $value ) {
            if ( $value !== '' ) {
                $args[ $attribute ] = rawurlencode( $value );
            }
        }
        return empty( $args ) ? $base_link : add_query_arg( $args, $base_link );
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

    /**
     * Decodes HTML entities in product/variation names — WooCommerce titles
     * can come back with entities already encoded (e.g. "Tirzepatide &amp;
     * NAD+"), and Attentive expects the plain-text name.
     */
    private static function decode_name( $name ) {
        return html_entity_decode( $name, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
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
