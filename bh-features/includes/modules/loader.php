<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ============================================================
 *  SAFE PRODUCTION MODULE LOADER
 * ============================================================
 *
 * - Uses safe_require() to avoid fatal errors.
 * - Missing files are logged AND shown in WP Admin Notices.
 */
class BH_Modules_Loader {

    /**
     * Store missing modules for admin notice
     *
     * @var array
     */
    private $missing_modules = [];

    public function __construct() {

        $base = plugin_dir_path( __FILE__ );

        try {

        /**
         * Hook admin notices AFTER we try to load everything.
         */
        add_action( 'admin_notices', [ $this, 'show_missing_module_notices' ] );

        /**
         * ============================================================
         * COMMON (Utilities, shared state, helpers)
         * ============================================================
         */
        //$this->safe_require( $base . 'common/bh-common-states.php' );


        /**
         * ============================================================
         * UI (Login UI, frontend UI helpers)
         * ============================================================
         */
        //$this->safe_require( $base . 'ui/bh-ui.php' );


        /**
         * ============================================================
         * API (REST Endpoints)
         * ============================================================
         */
        //$this->safe_require( $base . 'api/bh-rest-api.php' );


        /**
         * ============================================================
         * US STATES
         * ============================================================
         */
        $this->safe_require( $base . 'states/licensed-states/licensed-states-manager.php' );
        $this->safe_require( $base . 'states/licensed-states/mapdata-generator.php' );
        $this->safe_require( $base . 'states/states.php' );
        $this->safe_require( $base . 'states/states-ui.php' );
        $this->safe_require( $base . 'states/states-admin.php' );


        /**
         * ============================================================
         * INTEGRATIONS (Tracking + External platforms)
         * ============================================================
         */

        // Server-side tracking
        //$this->safe_require( $base . 'integrations/tracking/container.php' );

        // Frontend tracking
        //$this->safe_require( $base . 'integrations/tracking/frontend.php' );

        // Friendbuy
        //$this->safe_require( $base . 'integrations/friendbuy/frontend-tracking.php' );

        // TripleWhale
        $this->safe_require( $base . 'integrations/triplewhale/loader.php' );



        /**
         * ============================================================
         * ADMIN (Roles & Permissions)
         * ============================================================
         */
        //$this->safe_require( $base . 'admin/bh-roles-and-permissions.php' );


        /**
         * ============================================================
         * PAGE RESTRICTIONS (Access control for pages)
         * ============================================================
         */
        //$this->safe_require( $base . 'pages/bh-restrictions.php' );


        /**
         * ============================================================
         * WOOCOMMERCE MODULES
         * ============================================================
         */

        /**
         * --------------------------------
         * WC Test Mode
         * --------------------------------
         */
        //$this->safe_require( $base . 'wc/bh-test-mode.php' );


        /**
         * --------------------------------
         * PRODUCTS
         * --------------------------------
         */
        //$this->safe_require( $base . 'wc/products/bh-products.php' );
        //$this->safe_require( $base . 'wc/products/bh-products-admin.php' );


        /**
         * --------------------------------
         * CART
         * --------------------------------
         */
        //$this->safe_require( $base . 'wc/cart/bh-cart.php' );


        /**
         * --------------------------------
         * CHECKOUT
         * --------------------------------
         */
        $this->safe_require( $base . 'wc/checkout/bh-checkout-validations.php' );
        $this->safe_require( $base . 'wc/checkout/bh-phone-standardization.php' );
        $this->safe_require( $base . 'wc/checkout/checkout-ui.php' );
        //$this->safe_require( $base . 'wc/checkout/bh-checkout.php' );
        // $this->safe_require( $base . 'wc/checkout/__bh-us-phone-standardization.php' );


        /**
         * --------------------------------
         * ORDERS
         * --------------------------------
         */
        //$this->safe_require( $base . 'wc/orders/bh-orders.php' );
        //$this->safe_require( $base . 'wc/orders/bh-orders-admin.php' );

        // Telegra integration
        $this->safe_require( $base . 'wc/orders/telegra/renewal-handler.php' );
        $this->safe_require( $base . 'wc/orders/telegra/renewal-blocker.php' );

        // Orders filters
        //$this->safe_require( $base . 'wc/filters/bh-date-range-filter-core.php' );


        /**
         * --------------------------------
         * SUBSCRIPTIONS
         * --------------------------------
         */
        $this->safe_require( $base . 'wc/subscriptions/bh-renewal-endpoint.php' );
        $this->safe_require( $base . 'wc/subscriptions/early-renewal-handler.php' );

        $this->safe_require( $base . 'wc/subscriptions/state-reactivation/loader.php' );
        
        //$this->safe_require( $base . 'wc/subscriptions/bh-subscriptions.php' );
        //$this->safe_require( $base . 'wc/subscriptions/bh-subscriptions-admin.php' );
        //$this->safe_require( $base . 'wc/subscriptions/bh-subscriptions-next-payment-date.php' );
        //$this->safe_require( $base . 'wc/subscriptions/bh-subscriptions-pause.php' );

        // Subscription filters
        //$this->safe_require( $base . 'wc/subscriptions/filters/bh-date-range-filter-subscriptions.php' );
        //$this->safe_require( $base . 'wc/subscriptions/filters/bh-state-filter-subscriptions.php' );


        /**
         * --------------------------------
         * UPSELLS
         * --------------------------------
         */
        //$this->safe_require( $base . 'wc/upsells/bh-upsells.php' );


        /**
         * --------------------------------
         * WC ADMIN
         * --------------------------------
         */
        $this->safe_require( $base . 'wc/admin/admin.php' );


        } catch ( Exception $e ) {
            error_log( '[BH Modules Loader] Exception during module loading: ' . $e->getMessage() );
        }

    }

    /**
     * Safely load PHP modules without breaking the site.
     * Logs missing files and registers them for admin notice.
     */
    private function safe_require( $path ) {

        if ( file_exists( $path ) ) {
            require_once $path;
            return;
        }

        // Track missing file
        $this->missing_modules[] = $path;

        // Log in error_log for debugging
        error_log( "[BH Modules Loader] Missing file: {$path}" );
    }


    /**
     * Display admin notice if there are missing modules.
     */
    public function show_missing_module_notices() {

        if ( empty( $this->missing_modules ) ) {
            return;
        }

        ?>
        <div class="notice notice-error">
            <p><strong>BH Modules Loader Warning:</strong></p>
            <p>The following module files are missing and could not be loaded:</p>
            <ul>
                <?php foreach ( $this->missing_modules as $file ) : ?>
                    <li><?php echo esc_html( $file ); ?></li>
                <?php endforeach; ?>
            </ul>
            <p>Please check your modules directory.</p>
        </div>
        <?php
    }
}

new BH_Modules_Loader();
