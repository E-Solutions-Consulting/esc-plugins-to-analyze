<?php
/**
 * Lumen Integration - Configuration
 *
 * API keys are read from wp-config.php constants:
 *   define( 'LUMEN_API_KEY_STAGING',    'your-staging-key' );
 *   define( 'LUMEN_API_KEY_PRODUCTION', 'your-production-key' );
 *
 * @package BH_Features
 * @subpackage Integrations/Lumen
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( class_exists( 'AH_Lumen_Config' ) ) {
    return;
}

class AH_Lumen_Config {

    const OPTION_KEY          = 'ah_lumen_settings';
    const BASE_URL_PRODUCTION = 'https://gw.us.prod.lumen.me';       // ← confirmar con Lumen
    const BASE_URL_STAGING    = 'https://gw.us.stage.lumen.me'; // ← confirmar con Lumen

    public static function init() {
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
        add_action( 'admin_menu', [ __CLASS__, 'register_admin_page' ], 50 );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
    }

    // -------------------------------------------------------------------------
    // Settings access
    // -------------------------------------------------------------------------

    /**
     * Return merged settings with defaults.
     */
    public static function get_settings() {
        $defaults = [
            'mode'             => 'staging',
            'lumen_product_id' => '',
            'wc_product_ids'   => [],   // WC product IDs that trigger Lumen reporting
            'audience_segment' => '',
            'placement'        => 'order_bump',
            'fulfillment'      => true,
            'logging_enabled'  => 'yes',
            'max_retries'      => 3,
            'retry_delays'     => '5, 30',
        ];

        $saved = get_option( self::OPTION_KEY, [] );

        if ( ! is_array( $saved ) ) {
            $saved = [];
        }

        return wp_parse_args( $saved, $defaults );
    }

    /**
     * Get single setting value.
     */
    public static function get( $key, $default = null ) {
        $settings = self::get_settings();
        return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
    }

    /**
     * Return the WooCommerce product IDs that trigger a Lumen report.
     *
     * @return int[]
     */
    public static function get_wc_product_ids() {
        $ids = self::get( 'wc_product_ids', [] );
        return is_array( $ids ) ? array_map( 'intval', array_filter( $ids ) ) : [];
    }

    /**
     * Return max retry attempts.
     */
    public static function get_max_retries(): int {
        return (int) self::get( 'max_retries', 3 );
    }

    /**
     * Return retry delays in seconds as an indexed array.
     * Config stores minutes as comma-separated string, e.g. "5, 30".
     *
     * @return int[]
     */
    public static function get_retry_delays(): array {
        $raw = self::get( 'retry_delays', '5, 30' );
        $minutes = array_filter( array_map( 'intval', array_map( 'trim', explode( ',', $raw ) ) ) );
        return array_values( array_map( fn( $m ) => $m * 60, $minutes ) );
    }

    /**
     * Return the active API key from wp-config constants.
     */
    public static function get_api_key() {
        $mode = self::get( 'mode', 'staging' );

        if ( $mode === 'production' ) {
            return defined( 'LUMEN_API_KEY_PRODUCTION' ) ? LUMEN_API_KEY_PRODUCTION : '';
        }

        return defined( 'LUMEN_API_KEY_STAGING' ) ? LUMEN_API_KEY_STAGING : '';
    }

    /**
     * Return the active base URL.
     */
    public static function get_base_url() {
        $mode = self::get( 'mode', 'staging' );
        return $mode === 'production' ? self::BASE_URL_PRODUCTION : self::BASE_URL_STAGING;
    }

    /**
     * Return true if the integration is ready to fire.
     */
    public static function is_configured() {
        return ! empty( self::get_api_key() )
            && ! empty( self::get( 'lumen_product_id' ) )
            && ! empty( self::get_wc_product_ids() );
    }

    /**
     * Persist settings.
     */
    public static function update_settings( array $settings ) {
        update_option( self::OPTION_KEY, $settings );
    }

    // -------------------------------------------------------------------------
    // Admin scripts
    // -------------------------------------------------------------------------

    public static function enqueue_scripts( $hook ) {
        // No external JS dependencies needed.
    }

    // -------------------------------------------------------------------------
    // Admin page
    // -------------------------------------------------------------------------

    public static function register_admin_page() {

        add_submenu_page(
            PARENT_MENU_SLUG,
            'Lumen Integration',
            'Lumen',
            'manage_options',
            PARENT_MENU_SLUG . '--lumen-settings',
            [ __CLASS__, 'render_admin_page' ]
        );
    }

    public static function register_settings() {
        register_setting(
            'ah_lumen_settings_group',
            self::OPTION_KEY,
            [
                'type'              => 'array',
                'sanitize_callback' => [ __CLASS__, 'sanitize_settings' ],
                'default'           => [],
            ]
        );

        add_settings_section(
            'ah_lumen_keys',
            'API Keys & Environment',
            '__return_false',
            'ah-lumen-settings'
        );

        add_settings_section(
            'ah_lumen_products',
            'WooCommerce Products',
            [ __CLASS__, 'render_section_products_desc' ],
            'ah-lumen-settings'
        );

        add_settings_section(
            'ah_lumen_payload',
            'Payload Settings',
            '__return_false',
            'ah-lumen-settings'
        );

        add_settings_section(
            'ah_lumen_misc',
            'Miscellaneous',
            '__return_false',
            'ah-lumen-settings'
        );

        add_settings_section(
            'ah_lumen_retry',
            'Async Retry Settings',
            [ __CLASS__, 'render_section_retry_desc' ],
            'ah-lumen-settings'
        );

        // Keys section
        add_settings_field( 'ah_lumen_mode', 'Environment Mode', [ __CLASS__, 'render_field_mode' ], 'ah-lumen-settings', 'ah_lumen_keys' );

        // Products section
        add_settings_field( 'ah_lumen_wc_product_ids', 'Trigger Products', [ __CLASS__, 'render_field_wc_product_ids' ], 'ah-lumen-settings', 'ah_lumen_products' );

        // Payload section
        add_settings_field( 'ah_lumen_product_id',     'Lumen Product ID',  [ __CLASS__, 'render_field_lumen_product_id' ], 'ah-lumen-settings', 'ah_lumen_payload' );
        add_settings_field( 'ah_lumen_audience',       'Audience Segment',  [ __CLASS__, 'render_field_audience_segment' ], 'ah-lumen-settings', 'ah_lumen_payload' );
        add_settings_field( 'ah_lumen_placement',      'Placement',         [ __CLASS__, 'render_field_placement' ],        'ah-lumen-settings', 'ah_lumen_payload' );
        add_settings_field( 'ah_lumen_fulfillment',    'Fulfillment',       [ __CLASS__, 'render_field_fulfillment' ],      'ah-lumen-settings', 'ah_lumen_payload' );

        // Misc section
        add_settings_field( 'ah_lumen_logging', 'Logging', [ __CLASS__, 'render_field_logging_enabled' ], 'ah-lumen-settings', 'ah_lumen_misc' );

        // Retry section
        add_settings_field( 'ah_lumen_max_retries',  'Max retries',   [ __CLASS__, 'render_field_max_retries' ],  'ah-lumen-settings', 'ah_lumen_retry' );
        add_settings_field( 'ah_lumen_retry_delays', 'Retry delays',  [ __CLASS__, 'render_field_retry_delays' ], 'ah-lumen-settings', 'ah_lumen_retry' );
    }

    public static function sanitize_settings( $input ) {
        $clean = [];

        $clean['mode'] = in_array( $input['mode'] ?? '', [ 'staging', 'production' ], true )
            ? $input['mode']
            : 'staging';

        // WC product IDs — array of integers
        $raw_ids = $input['wc_product_ids'] ?? [];
        if ( is_string( $raw_ids ) ) {
            $raw_ids = array_filter( array_map( 'trim', explode( ',', $raw_ids ) ) );
        }
        $clean['wc_product_ids'] = array_values( array_map( 'absint', array_filter( (array) $raw_ids ) ) );

        $clean['lumen_product_id'] = sanitize_text_field( $input['lumen_product_id'] ?? '' );
        $clean['audience_segment'] = sanitize_text_field( $input['audience_segment'] ?? '' );

        $clean['placement'] = in_array( $input['placement'] ?? '', [ 'order_bump', 'post_checkout', 'email' ], true )
            ? $input['placement']
            : 'order_bump';

        $clean['fulfillment']     = isset( $input['fulfillment'] ) && $input['fulfillment'] === '1';
        $clean['logging_enabled'] = isset( $input['logging_enabled'] ) ? 'yes' : 'no';

        $clean['max_retries'] = min( 10, max( 1, (int) ( $input['max_retries'] ?? 3 ) ) );

        $raw_delays = sanitize_text_field( $input['retry_delays'] ?? '5, 30' );
        $delays_arr = array_filter( array_map( 'intval', array_map( 'trim', explode( ',', $raw_delays ) ) ) );
        $clean['retry_delays'] = implode( ', ', array_values( $delays_arr ) );

        return $clean;
    }

    // -------------------------------------------------------------------------
    // Field renderers
    // -------------------------------------------------------------------------

    public static function render_section_products_desc() {
        echo '<p>Select the WooCommerce product(s) that correspond to a Lumen device. '
           . 'When an order containing any of these products transitions to <strong>completed</strong>, '
           . 'a purchase report will be sent to Lumen. Add products one by one — currently evaluating one device.</p>';
    }

    public static function render_field_mode() {
        $val = self::get( 'mode', 'staging' );
        ?>
        <select name="<?php echo self::OPTION_KEY; ?>[mode]">
            <option value="staging"    <?php selected( $val, 'staging' ); ?>>Staging</option>
            <option value="production" <?php selected( $val, 'production' ); ?>>Production</option>
        </select>
        <?php
        $staging_ok    = defined( 'LUMEN_API_KEY_STAGING' )    && ! empty( LUMEN_API_KEY_STAGING );
        $production_ok = defined( 'LUMEN_API_KEY_PRODUCTION' ) && ! empty( LUMEN_API_KEY_PRODUCTION );
        echo '<p class="description">';
        echo 'Staging key: '    . ( $staging_ok    ? '<strong style="color:#46b450">✓ configured</strong>' : '<strong style="color:#dc3232">✗ LUMEN_API_KEY_STAGING missing in wp-config.php</strong>' );
        echo ' &nbsp;|&nbsp; ';
        echo 'Production key: ' . ( $production_ok ? '<strong style="color:#46b450">✓ configured</strong>' : '<strong style="color:#dc3232">✗ LUMEN_API_KEY_PRODUCTION missing in wp-config.php</strong>' );
        echo '</p>';
    }

    public static function render_field_wc_product_ids() {
        $saved_ids = self::get_wc_product_ids();
        
        // Get all published WooCommerce products
        $products = get_posts( array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC'
        ) );
        ?>
        <div style="max-width: 500px;">
            <select
                id="ah_lumen_wc_product_ids"
                name="<?php echo self::OPTION_KEY; ?>[wc_product_ids][]"
                multiple="multiple"
                style="width: 100%; height: 150px;"
            >
                <?php foreach ( $products as $product_post ) :
                    $product = wc_get_product( $product_post->ID );
                    if ( ! $product ) continue;
                    $selected = in_array( $product_post->ID, $saved_ids ) ? 'selected="selected"' : '';
                    ?>
                    <option value="<?php echo esc_attr( $product_post->ID ); ?>" <?php echo $selected; ?>>
                        <?php echo esc_html( $product->get_formatted_name() ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="description">
                These are the WooCommerce products that trigger a Lumen purchase report when an order is completed.
                Start with one — add more as Lumen onboards additional devices.
            </p>

            <?php if ( ! empty( $saved_ids ) ) : ?>
            <table class="widefat" style="margin-top: 12px; max-width: 500px;">
                <thead>
                    <tr>
                        <th style="width:80px">ID</th>
                        <th>Product Name</th>
                        <th style="width:80px">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $saved_ids as $pid ) :
                        $p = wc_get_product( $pid );
                        ?>
                        <tr>
                            <td><code><?php echo esc_html( $pid ); ?></code></td>
                            <td><?php echo $p ? esc_html( $p->get_name() ) : '<em style="color:#999">Product not found</em>'; ?></td>
                            <td><?php echo $p ? '<span style="color:#46b450">✓</span>' : '<span style="color:#dc3232">✗</span>'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function render_field_lumen_product_id() {
        $val = esc_attr( self::get( 'lumen_product_id', '' ) );
        echo '<input type="text" name="' . self::OPTION_KEY . '[lumen_product_id]" value="' . $val . '" class="regular-text" placeholder="LMN_123456" />';
        echo '<p class="description">Lumen\'s internal product ID, provided during onboarding. Goes in the <code>items[].productId</code> field of the API payload.</p>';
    }

    public static function render_field_audience_segment() {
        $val = esc_attr( self::get( 'audience_segment', '' ) );
        echo '<input type="text" name="' . self::OPTION_KEY . '[audience_segment]" value="' . $val . '" class="regular-text" />';
        echo '<p class="description">Must be a value from the pre-agreed closed list with Lumen. Goes in <code>additionalInfo.audienceSegment</code>.</p>';
    }

    public static function render_field_placement() {
        $val = self::get( 'placement', 'order_bump' );
        ?>
        <select name="<?php echo self::OPTION_KEY; ?>[placement]">
            <option value="order_bump"    <?php selected( $val, 'order_bump' ); ?>>Order Bump</option>
            <option value="post_checkout" <?php selected( $val, 'post_checkout' ); ?>>Post Checkout</option>
            <option value="email"         <?php selected( $val, 'email' ); ?>>Email</option>
        </select>
        <p class="description">Confirm exact string values with Lumen before going live.</p>
        <?php
    }

    public static function render_field_fulfillment() {
        $val = self::get( 'fulfillment', true );
        echo '<label><input type="checkbox" name="' . self::OPTION_KEY . '[fulfillment]" value="1" ' . checked( $val, true, false ) . ' /> Lumen handles shipping (<code>fulfillment = true</code>)</label>';
        echo '<p class="description">When enabled, <code>shippingAddress</code> is required and validated before sending.</p>';
    }

    public static function render_field_logging_enabled() {
        $val = self::get( 'logging_enabled', 'yes' );
        echo '<label><input type="checkbox" name="' . self::OPTION_KEY . '[logging_enabled]" value="yes" ' . checked( $val, 'yes', false ) . ' /> Enable logging</label>';
        echo '<p class="description">WooCommerce &rarr; Status &rarr; Logs &rarr; channel: <code>lumen</code></p>';
    }

    public static function render_section_retry_desc() {
        echo '<p>Purchases are sent to Lumen asynchronously via Action Scheduler. '
           . 'If the request fails due to a timeout or server error (5xx), it will be retried automatically. '
           . '4xx errors (bad data, duplicates) are not retried.</p>';
    }

    public static function render_field_max_retries() {
        $val = (int) self::get( 'max_retries', 3 );
        echo '<input type="number" name="' . self::OPTION_KEY . '[max_retries]" value="' . esc_attr( $val ) . '" min="1" max="10" style="width:70px;" />';
        echo '<p class="description">Maximum number of retry attempts after the initial failure. Recommended: 3.</p>';
    }

    public static function render_field_retry_delays() {
        $val = esc_attr( self::get( 'retry_delays', '5, 30' ) );
        echo '<input type="text" name="' . self::OPTION_KEY . '[retry_delays]" value="' . $val . '" class="regular-text" placeholder="5, 30" />';
        echo '<p class="description">Delay in <strong>minutes</strong> between each retry attempt, comma-separated. '
           . 'One value per retry — e.g. <code>5, 30</code> means retry 1 after 5 min, retry 2 after 30 min. '
           . 'If there are fewer values than max retries, the last value is reused.</p>';
    }

    // -------------------------------------------------------------------------
    // Admin page render
    // -------------------------------------------------------------------------

    public static function render_admin_page() {
        $configured = self::is_configured();
        $mode       = self::get( 'mode', 'staging' );
        ?>
        <div class="wrap">
            <h1>Lumen Integration</h1>

            <?php if ( ! $configured ) : ?>
            <div class="notice notice-warning inline">
                <p>
                    <strong>Not fully configured.</strong>
                    Set the API key constant(s) in <code>wp-config.php</code>,
                    enter the Lumen Product ID, and select at least one WooCommerce product.
                </p>
            </div>
            <?php else : ?>
            <div class="notice notice-success inline">
                <p>
                    <strong>✓ Ready</strong> — running in <strong><?php echo esc_html( strtoupper( $mode ) ); ?></strong> mode.
                </p>
            </div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php
                settings_fields( 'ah_lumen_settings_group' );
                do_settings_sections( 'ah-lumen-settings' );
                submit_button( 'Save Settings' );
                ?>
            </form>
        </div>
        <?php
    }
}