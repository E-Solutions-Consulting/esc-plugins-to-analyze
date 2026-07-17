<?php
namespace Objectiv\Plugins\Checkout\Stats;

use Exception;
use Objectiv\Plugins\Checkout\Managers\SettingsManager;
use Objectiv\Plugins\Checkout\SingletonAbstract;

class StatCollection extends SingletonAbstract {
	/**
	 * The data to send to the CFW stat collection site
	 *
	 * @var array
	 */
	private $data = array();

	/**
	 * The stat collection url
	 *
	 * @var string
	 */
	private $stat_collection_url = 'https://stats.checkoutwc.com/api/v1/stats';

	/**
	 * The development stat collection url
	 *
	 * @var string
	 */
	private $dev_stat_collection_url = 'https://cstat22.test/api/v1/stats';

	/**
	 * The stat collection api key
	 *
	 * @var string
	 */
	private $stat_collection_api_key = '5fPiyDFwGrnZjiCHnLXAU6CLvLbM5vfOOaAa3xwQ';

	/**
	 * The list of settings from CFW to grab
	 *
	 * @var array
	 */
	private $approved_cfw_settings;

	/**
	 * The list of settings from WooCommerce to grab
	 *
	 * @var array
	 */
	private $approved_woocommerce_settings;

	private $allow_tracking_key    = 'allow_tracking';
	private $tracked_page_key      = 'purchase_page';
	private $tracking_notice_key   = 'tracking_notice';
	private $last_send_key         = 'tracking_last_send';
	private $tracking_action_param = 'cfw_tracking_action';
	private $cfw_home_site_url     = 'https://www.checkoutwc.com';
	private $woocommerce_settings  = array();
	private $settings_manager      = null;

	const CFW_TIMESTAMP_OPTION = 'woocommerce_admin_install_timestamp';

	const CFW_STORE_AGE_RANGES = array(
		'week-1'    => array(
			'start' => 0,
			'end'   => WEEK_IN_SECONDS,
		),
		'week-1-4'  => array(
			'start' => WEEK_IN_SECONDS,
			'end'   => WEEK_IN_SECONDS * 4,
		),
		'month-1-3' => array(
			'start' => MONTH_IN_SECONDS,
			'end'   => MONTH_IN_SECONDS * 3,
		),
		'month-3-6' => array(
			'start' => MONTH_IN_SECONDS * 3,
			'end'   => MONTH_IN_SECONDS * 6,
		),
		'month-6+'  => array(
			'start' => MONTH_IN_SECONDS * 6,
		),
	);


	/**
	 * StatCollection constructor.
	 */
	public function init() {
		add_action( 'cfw_do_plugin_activation', array( $this, 'run_on_plugin_activation' ) );
		add_action( 'cfw_do_plugin_deactivation', array( $this, 'run_on_plugin_deactivation' ) );

		if ( defined( 'CFW_STATS_DEVELOP_URL' ) ) {
			$this->dev_stat_collection_url = CFW_STATS_DEVELOP_URL;
		}

		add_action( 'init', array( $this, 'schedule_send' ) );
		add_action( 'init', array( $this, 'tracking_opt_in_out_listener' ) );
		add_action( 'cfw_opt_into_tracking', array( $this, 'check_for_optin' ) );
		add_action( 'cfw_opt_out_of_tracking', array( $this, 'check_for_optout' ) );
		add_action( 'admin_notices', array( $this, 'admin_notice' ) );
		add_filter( 'cron_schedules', array( $this, 'add_schedules' ) );

		$this->approved_cfw_settings = array(
			'active_template' => (object) array(
				'rename' => false,
				'name'   => null,
				'action' => null,
			),
			'enable'          => (object) array(
				'rename' => false,
				'name'   => null,
				'action' => null,
			),
			'header_scripts'  => (object) array(
				'rename' => true,
				'name'   => 'header_scripts_empty',
				'action' => function ( $setting ) {
					return empty( $setting );
				},
			),
			'footer_scripts'  => (object) array(
				'rename' => true,
				'name'   => 'footer_scripts_empty',
				'action' => function ( $setting ) {
					return empty( $setting );
				},
			),
		);

		$this->approved_woocommerce_settings = array(
			'woocommerce_default_country',
			'woocommerce_default_customer_address',
			'woocommerce_calc_taxes',
			'woocommerce_enable_coupons',
			'woocommerce_calc_discounts_sequentially',
			'woocommerce_currency',
			'woocommerce_prices_include_tax',
			'woocommerce_tax_based_on',
			'woocommerce_tax_round_at_subtotal',
			'woocommerce_tax_classes',
			'woocommerce_tax_display_shop',
			'woocommerce_tax_display_cart',
			'woocommerce_tax_total_display',
			'woocommerce_enable_shipping_calc',
			'woocommerce_shipping_cost_requires_address',
			'woocommerce_ship_to_destination',
			'woocommerce_enable_guest_checkout',
			'woocommerce_enable_checkout_login_reminder',
			'woocommerce_enable_signup_and_login_from_checkout',
			'woocommerce_registration_generate_username',
			'woocommerce_registration_generate_password',
		);

		$this->settings_manager = SettingsManager::instance();

		if ( defined( 'CFW_DEV_MODE' ) && isset( $_POST['force-checkin'] ) ) {  // phpcs:ignore
			add_action(
				'init',
				function () {
					$this->send_checkin( true, true );
				}
			);
		}

		add_action(
			'cfw_general_admin_page_after_tracking_field',
			function () {
				if ( defined( 'CFW_DEV_MODE' ) && CFW_DEV_MODE ) {
					$this->setup_data();

					if ( function_exists( 'd' ) ) {
						d( $this->get_data() );
					}
					submit_button( 'Force Check-in', 'button', 'force-checkin' );
				}
			}
		);
	}

	/**
	 * Registers new cron schedules
	 *
	 * @param array $schedules The array of scheduled cron jobs.
	 *
	 * @return array
	 * @since 1.6
	 */
	public function add_schedules( $schedules = array() ): array {
		// Adds once weekly to the existing schedules.
		if ( ! isset( $schedules['weekly'] ) ) {
			$schedules['weekly'] = array(
				'interval' => 604800,
				'display'  => __( 'Once Weekly' ),
			);
		}

		return $schedules;
	}

	/**
	 * Schedule a weekly checkin
	 *
	 * We send once a week (while tracking is allowed) to check in, which can be
	 * used to determine active sites.
	 *
	 * @return void
	 */
	public function schedule_send() {
		add_action( 'cfw_weekly_scheduled_events_tracking', array( $this, 'send_checkin' ) );
	}

	/**
	 * Send the data to the EDD server
	 *
	 * @param bool $override If we should override the tracking setting.
	 * @param bool $ignore_last_checkin If we should ignore when the last check in was.
	 *
	 * @return bool
	 */
	public function send_checkin( bool $override = false, bool $ignore_last_checkin = false ): bool {

		$home_url = trailingslashit( home_url() );

		// Allows us to stop our own site from checking in, and a filter for our additional sites.
		if ( $home_url === $this->cfw_home_site_url ) {
			return false;
		}

		/**
		 * Filters whether to send a checkin.
		 *
		 * @since 7.8.8 d
		 * @param bool $override Whether to override the default behavior.
		 * @param string $home_url The home url.
		 */
		if ( apply_filters( 'cfw_disable_tracking_checkin', false, $home_url ) && ! $override ) {
			return false;
		}

		if ( ! $this->tracking_allowed() && ! $override ) {
			return false;
		}

		// Send a maximum of once per week.
		$last_send = $this->get_last_send();
		if ( is_numeric( $last_send ) && $last_send > strtotime( '-1 week' ) && ! $ignore_last_checkin ) {
			return false;
		}

		$this->setup_data();
		$remote_url = CFW_DEV_MODE ? $this->dev_stat_collection_url : $this->stat_collection_url;

		$result = wp_remote_request(
			$remote_url,
			array(
				'method'      => 'POST',
				'headers'     => array(
					'Content-Type' => 'application/json',
					'x-api-key'    => $this->stat_collection_api_key,
				),
				'timeout'     => 8,
				'redirection' => 5,
				'httpversion' => '1.1',
				'body'        => wp_json_encode( $this->data ),
				'user-agent'  => 'CFW/' . CFW_VERSION . '; ' . get_bloginfo( 'url' ),
				'sslverify'   => ! CFW_DEV_MODE,
			)
		);

		$this->settings_manager->update_setting( $this->last_send_key, time() );

		return true;
	}

	/**
	 * If the tracking get parameter exists on the page lets grab the acton name and fire it off
	 *
	 * @return void
	 */
	public function tracking_opt_in_out_listener() {
		if ( key_exists( $this->tracking_action_param, $_GET ) && ! empty( $_GET[ $this->tracking_action_param ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$tracking_action_param = sanitize_text_field( wp_unslash( $_GET[ $this->tracking_action_param ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			cfw_do_action( "cfw_{$tracking_action_param}" );
		} else {
			add_action( 'updated_option', array( $this, 'updated_option' ), 10, 3 );
		}
	}

	/**
	 * Check if the user has opted into tracking
	 *
	 * @return bool
	 */
	public function tracking_allowed(): bool {
		return md5( trailingslashit( home_url() ) ) === $this->get_option( $this->allow_tracking_key );
	}

	/**
	 * Setup the data that is going to be tracked
	 *
	 * @return void
	 */
	public function setup_data() {
		$data = array();

		// Retrieve memory limit info.
		$database_version = wc_get_server_database_version();
		$memory           = wc_let_to_num( WP_MEMORY_LIMIT );

		if ( function_exists( 'memory_get_usage' ) ) {
			$system_memory = wc_let_to_num( @ini_get( 'memory_limit' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$memory        = max( $memory, $system_memory );
		}

		$plugins                   = $this->get_plugins();
		$checkout_page             = $this->get_option( $this->tracked_page_key );
		$wp_data['wp_debug_mode']  = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? 'yes' : 'no';
		$wp_data['cfw_debug_mode'] = ( defined( 'CFW_DEV_MODE' ) && CFW_DEV_MODE ) ? 'yes' : 'no';

		$data['site_url']             = get_site_url();
		$data['site_md5']             = md5( get_site_url() );
		$data['php_version']          = phpversion();
		$data['cfw_version']          = CFW_VERSION;
		$data['wp_version']           = get_bloginfo( 'version' );
		$data['mysql_version']        = $database_version['number'];
		$data['server']               = sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ?? '' ) );
		$data['php_max_upload_size']  = size_format( wp_max_upload_size() );
		$data['php_default_timezone'] = date_default_timezone_get();
		$data['php_soap']             = class_exists( 'SoapClient' ) ? 'yes' : 'no';
		$data['php_fsockopen']        = function_exists( 'fsockopen' ) ? 'yes' : 'no';
		$data['php_curl']             = function_exists( 'curl_init' ) ? 'yes' : 'no';
		$data['memory_limit']         = size_format( $memory );
		$data['multisite']            = is_multisite();
		$data['locale']               = get_locale();
		$data['theme']                = $this->get_theme_info();
		$data['gateways']             = self::get_active_payment_gateways();
		$data['wc_order_stats']       = $this->get_woo_order_stats();
		$data['shipping_methods']     = self::get_active_shipping_methods();
		$data['wc_settings']          = $this->get_woo_site_settings();
		$data['cfw_settings']         = $this->get_cfw_settings();
		$data['inactive_plugins']     = $plugins['inactive'] ?? array();
		$data['active_plugins']       = $plugins['active'] ?? array();
		$data['debug_modes']          = $wp_data;
		$data['environment']          = wp_get_environment_type() ?? '';

		$this->data = $data;
	}

	public function get_cfw_settings() {
		// Filter function for the cfw settings list
		$filter_settings = \Closure::bind(
			function ( $setting ) {
				// Is the setting key in the settings approved list? Then allow it through
				return in_array( $setting, array_keys( $this->approved_cfw_settings ), true );
			},
			$this
		);

		$settings = array_filter( $this->settings_manager->settings, $filter_settings, ARRAY_FILTER_USE_KEY );

		return $this->prep_approved_cfw_settings( $settings );
	}

	/**
	 * Get list of active and inactive plugins
	 *
	 * @return array
	 */
	public function get_plugins() {
		// Retrieve current plugin information
		if ( ! function_exists( 'get_plugins' ) ) {
			include ABSPATH . '/wp-admin/includes/plugin.php';
		}

		$plugins        = array_keys( get_plugins() );
		$active_plugins = get_option( 'active_plugins', array() );

		$plugins_list = array();

		foreach ( $plugins as $key => $plugin ) {
			if ( in_array( $plugin, $active_plugins, true ) ) {
				// Remove active plugins from list so we can show active and inactive separately
				$plugins_list['active'][] = $plugins[ $key ];
			} else {
				$plugins_list['inactive'][] = $plugins[ $key ];
			}
		}

		return $plugins_list;
	}

	/**
	 * @param array $settings The settings.
	 *
	 * @return mixed
	 */
	public function prep_approved_cfw_settings( $settings ) {
		foreach ( $settings as $key => $value ) {
			$setting_metadata = $this->approved_cfw_settings[ $key ];

			if ( $setting_metadata->rename ) {
				unset( $settings[ $key ] );
				$settings[ $setting_metadata->name ] = $value;
				$key                                 = $setting_metadata->name;
			}

			if ( $setting_metadata->action ) {
				$func             = $setting_metadata->action;
				$settings[ $key ] = $func( $value );
			}
		}

		return $settings;
	}

	/**
	 * @return mixed
	 */
	public function get_woo_site_settings() {
		$settings_pages = \WC_Admin_Settings::get_settings_pages();

		array_walk(
			$settings_pages,
			function ( $item ) {
				if ( ! method_exists( $item, 'get_settings' ) ) {
					return;
				}

				$settings = $item->get_settings();

				array_walk(
					$settings,
					function ( $setting ) {
						if ( empty( $setting['id'] ) ) {
							return;
						}

						$stats    = StatCollection::instance();
						$settings = $stats->get_woocommerce_settings();
						$id       = $setting['id'];

						if ( strpos( $id, 'woocommerce_' ) !== 0 ) {
							$id = "woocommerce_{$id}";
						}

						if ( ! in_array( $id, $this->approved_woocommerce_settings, true ) ) {
							return;
						}

						$setting_name = $id;
						$settings[]   = $setting_name;
						$stats->set_woocommerce_settings( $settings );
					}
				);
			}
		);

		$options      = (object) array( 'ops' => array() );
		$woo_settings = $this->get_woocommerce_settings();

		array_walk(
			$woo_settings,
			\Closure::bind(
				function ( $setting ) {
					$op_value = get_option( $setting );

					if ( false === $op_value ) {
						return;
					}

					$this->ops[ $setting ] = get_option( $setting );
				},
				$options
			)
		);

		return $options->ops;
	}

	/**
	 * Get a list of all active shipping methods.
	 *
	 * @return array
	 */
	private static function get_active_shipping_methods(): array {
		$shipping_methods = WC()->shipping->load_shipping_methods();
		$active_methods   = array();
		foreach ( $shipping_methods as $id => $shipping_method ) {
			if ( isset( $shipping_method->enabled ) && 'yes' === $shipping_method->enabled ) {
				$method_title = ! empty( $shipping_method->title ) ? $shipping_method->title : $shipping_method->method_title;
				if ( 'international_delivery' === $id ) {
					$method_title .= ' (International)';
				}
				array_push( $active_methods, array( 'id' => $method_title ) );
			}
		}

		return $active_methods;
	}

	/**
	 * Get a list of all active payment gateways.
	 *
	 * @return array
	 */
	private static function get_active_payment_gateways(): array {
		$active_gateways = array();
		$gateways        = WC()->payment_gateways->payment_gateways();
		foreach ( $gateways as $id => $gateway ) {
			if ( isset( $gateway->enabled ) && 'yes' === $gateway->enabled ) {
				$active_gateways[] = $id;
			}
		}

		return $active_gateways;
	}

	/**
	 * Get the current theme info, theme name and version.
	 *
	 * @return array
	 */
	public static function get_theme_info() {
		$theme_data        = wp_get_theme();
		$theme_child_theme = wc_bool_to_string( is_child_theme() );
		$theme_wc_support  = wc_bool_to_string( current_theme_supports( 'woocommerce' ) );

		return array(
			'name'        => $theme_data->Name,
			'version'     => $theme_data->Version,
			'child_theme' => $theme_child_theme,
			'wc_support'  => $theme_wc_support,
		);
	}

	/**
	 * Get woo order stats
	 *
	 * @return array
	 * @throws Exception If the order is not found.
	 */
	public function get_woo_order_stats(): array {
		$is_hpos_enabled = cfw_is_hpos_enabled();

		// use php DateTime() to get the start and end dates of the last four weeks
		$w_1 = new \DateTime( '-1 week', new \DateTimeZone( 'GMT' ) );
		$w_1->setISODate( $w_1->format( 'o' ), $w_1->format( 'W' ) );
		$w_1->setTime( 0, 0 );
		$w_1 = \DateTimeImmutable::createFromMutable( $w_1 );

		$w_2 = new \DateTime( '-2 week', new \DateTimeZone( 'GMT' ) );
		$w_2->setISODate( $w_2->format( 'o' ), $w_2->format( 'W' ) );
		$w_2->setTime( 0, 0 );
		$w_2 = \DateTimeImmutable::createFromMutable( $w_2 );

		$w_3 = new \DateTime( '-3 week', new \DateTimeZone( 'GMT' ) );
		$w_3->setISODate( $w_3->format( 'o' ), $w_3->format( 'W' ) );
		$w_3->setTime( 0, 0 );
		$w_3 = \DateTimeImmutable::createFromMutable( $w_3 );

		$w_4 = new \DateTime( '-4 week', new \DateTimeZone( 'GMT' ) );
		$w_4->setISODate( $w_4->format( 'o' ), $w_4->format( 'W' ) );
		$w_4->setTime( 0, 0 );
		$w_4 = \DateTimeImmutable::createFromMutable( $w_4 );

		$weeks = array(
			'previous_1' => array(
				'end'   => $w_1->modify( '+7 days' )->modify( '-1 second' )->getTimestamp(),
				'start' => $w_1->getTimestamp(),
				'week'  => (int) $w_1->format( 'W' ),
				'year'  => (int) $w_1->format( 'o' ),
			),
			'previous_2' => array(
				'end'   => $w_2->modify( '+7 days' )->modify( '-1 second' )->getTimestamp(),
				'start' => $w_2->getTimestamp(),
				'week'  => (int) $w_2->format( 'W' ),
				'year'  => (int) $w_2->format( 'o' ),
			),
			'previous_3' => array(
				'end'   => $w_3->modify( '+7 days' )->modify( '-1 second' )->getTimestamp(),
				'start' => $w_3->getTimestamp(),
				'week'  => (int) $w_3->format( 'W' ),
				'year'  => (int) $w_3->format( 'o' ),
			),
			'previous_4' => array(
				'end'   => $w_4->modify( '+7 days' )->modify( '-1 second' )->getTimestamp(),
				'start' => $w_4->getTimestamp(),
				'week'  => (int) $w_4->format( 'W' ),
				'year'  => (int) $w_4->format( 'o' ),
			),
		);

		foreach ( $weeks as $week => $week_details ) {
			$order_query_args = array(
				'limit'        => - 1,
				'date_created' => "{$week_details['start']}...{$week_details['end']}",
				'status'       => array( 'wc-completed' ),
			);

			if ( $is_hpos_enabled ) {
				$order_query_args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_cfw',
						'value' => 'true',
					),
				);
			} else {
				$order_query_args['meta_key']   = '_cfw';
				$order_query_args['meta_value'] = 'true';
			}

			$orders = wc_get_orders( $order_query_args );

			// Sum up the report values from $orders
			$total_sales  = 0;
			$total_orders = 0;
			$total_items  = 0;

			foreach ( $orders as $order ) {
				$total_sales += $order->get_total();
				++$total_orders;
				$total_items += $order->get_item_count();
			}

			$weeks[ $week ]['total_sales']  = $total_sales;
			$weeks[ $week ]['total_orders'] = $total_orders;
			$weeks[ $week ]['total_items']  = $total_items;
		}

		return $weeks;
	}

	/**
	 * Check for a new opt-in via the admin notice
	 *
	 * @return void
	 */
	public function check_for_optin() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			return;
		}

		$this->settings_manager->update_setting( $this->allow_tracking_key, md5( trailingslashit( home_url() ) ) );

		$this->send_checkin( true );

		$this->settings_manager->update_setting( $this->tracking_notice_key, '1' );
	}

	/**
	 * Check for a new opt-in via the admin notice
	 *
	 * @return void
	 */
	public function check_for_optout() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			return;
		}

		$this->settings_manager->delete_setting( $this->allow_tracking_key );
		$this->settings_manager->update_setting( $this->tracking_notice_key, '1' );
		wp_redirect( remove_query_arg( $this->tracking_action_param ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	/**
	 * Get the last time a checkin was sent
	 *
	 * @return false|string
	 */
	private function get_last_send() {
		return $this->get_option( $this->last_send_key );
	}

	/**
	 * Display the admin notice to users that have not opted-in or out
	 *
	 * @return void
	 */
	public function admin_notice() {
		$hide_notice = $this->get_option( $this->tracking_notice_key );

		if ( 1 === intval( $hide_notice ) ) {
			return;
		}

		if ( $this->tracking_allowed() ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			return;
		}

		if ( ! self::is_cfw_active_in_date_range( 'week-1-4' ) ) {
			return;
		}

		if (
			stristr( network_site_url( '/' ), '.test' ) !== false ||
			stristr( network_site_url( '/' ), '.dev' ) !== false ||
			stristr( network_site_url( '/' ), 'localhost' ) !== false ||
			stristr( network_site_url( '/' ), ':8888' ) !== false // This is common with MAMP on OS X
		) {
			return;
		}

		$optin_url  = add_query_arg( 'cfw_tracking_action', 'opt_into_tracking' );
		$optout_url = add_query_arg( 'cfw_tracking_action', 'opt_out_of_tracking' );
		?>
		<div class="notice notice-info" style="display: block !important;">
			<h3 style="font-weight: 500">
				<?php _e( 'Help us improve CheckoutWC.', 'checkout-wc' ); ?>
			</h3>
			<p>
				<?php _e( 'Gathering usage data helps us to improve CheckoutWC. Opt-out at anytime.' ); ?>
				<?php echo ' <a target="_blank" href="https://www.checkoutwc.com/checkout-for-woocommerce-usage-tracking/">' . esc_html__( 'Read more about what we collect.', 'woocommerce' ) . '</a>'; ?>
			</p>
			<p>
				<a href="<?php echo esc_url( $optin_url ); ?>" class="button-secondary">
					<?php _e( 'Allow', 'checkout-wc' ); ?>
				</a>
				&nbsp;
				<a href="<?php echo esc_url( $optout_url ); ?>" class="button-secondary">
					<?php _e( 'Do not allow', 'checkout-wc' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Get the number of seconds that the store has been active.
	 *
	 * @return number Number of seconds.
	 */
	public static function get_cfw_active_for_in_seconds() {
		$install_timestamp = get_option( self::CFW_TIMESTAMP_OPTION );

		if ( ! is_numeric( $install_timestamp ) ) {
			$install_timestamp = time();
			update_option( self::CFW_TIMESTAMP_OPTION, $install_timestamp );
		}

		return time() - $install_timestamp;
	}

	/**
	 * Test if WooCommerce Admin has been active within a pre-defined range.
	 *
	 * @param string $range range available in WC_ADMIN_STORE_AGE_RANGES.
	 * @param int    $custom_start custom start in range.
	 * @throws \InvalidArgumentException Throws exception when invalid $range is passed in.
	 * @return bool Whether or not WooCommerce admin has been active within the range.
	 */
	public static function is_cfw_active_in_date_range( $range, $custom_start = null ) {
		if ( ! array_key_exists( $range, self::CFW_STORE_AGE_RANGES ) ) {
			throw new \InvalidArgumentException(
				sprintf(
					'"%s" range is not supported, use one of: %s',
					esc_html( $range ),
					esc_html( implode( ', ', array_keys( self::CFW_STORE_AGE_RANGES ) ) )
				)
			);
		}
		$wc_admin_active_for = self::get_cfw_active_for_in_seconds();

		$range_data = self::CFW_STORE_AGE_RANGES[ $range ];
		$start      = null !== $custom_start ? $custom_start : $range_data['start'];
		if ( $range_data && $wc_admin_active_for >= $start ) {
			return ! isset( $range_data['end'] ) || $wc_admin_active_for < $range_data['end'];
		}
		return false;
	}

	/**
	 * @param string $key The option key.
	 *
	 * @return mixed
	 */
	public function get_option( $key ) {
		return $this->settings_manager->get_setting( $key );
	}

	/**
	 * @param string $key The option key.
	 * @param mixed  $old_value The old value.
	 * @param mixed  $value The new value.
	 */
	public function updated_option( $key, $old_value, $value ) {
		if ( '_cfw__settings' === $key ) {
			if ( is_array( $value ) && isset( $value[ $this->allow_tracking_key ] ) && null !== $value[ $this->allow_tracking_key ] && isset( $old_value[ $this->allow_tracking_key ] ) && $old_value[ $this->allow_tracking_key ] !== $value[ $this->allow_tracking_key ] ) {
				if ( '0' === $value[ $this->allow_tracking_key ] ) {
					$this->settings_manager->delete_setting( $this->allow_tracking_key );
					$this->settings_manager->update_setting( $this->tracking_notice_key, '0' );

					if ( isset( $_GET[ $this->tracking_action_param ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
						wp_redirect( remove_query_arg( $this->tracking_action_param ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
						exit;
					}
				}

				if ( $this->tracking_allowed() ) {
					if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore
						return;
					}

					$this->send_checkin( true );

					$this->settings_manager->update_setting( $this->tracking_notice_key, '1' );
				}
			}
		}
	}

	/**
	 * @param string $key   The option key.
	 * @param mixed  $value The option value.
	 */
	public function set_option( $key, $value ) {
		$this->settings_manager->add_setting( $key, $value );
	}

	/**
	 * @param string $key The option key.
	 */
	public function delete_option( $key ) {
		$this->settings_manager->delete_setting( $key );
	}

	/**
	 * @return array
	 */
	public function get_woocommerce_settings(): array {
		return $this->woocommerce_settings;
	}

	/**
	 * @param array $woocommerce_settings The WooCommerce settings.
	 */
	public function set_woocommerce_settings( array $woocommerce_settings ) {
		$this->woocommerce_settings = $woocommerce_settings;
	}

	/**
	 * @return mixed
	 */
	public function get_data(): array {
		return $this->data;
	}

	public function run_on_plugin_activation() {
		if ( wp_next_scheduled( 'cfw_weekly_scheduled_events_tracking' ) ) {
			return;
		}

		wp_schedule_event( time(), 'weekly', 'cfw_weekly_scheduled_events_tracking' );
	}

	public function run_on_plugin_deactivation() {
		wp_clear_scheduled_hook( 'cfw_weekly_scheduled_events_tracking' );
	}
}
