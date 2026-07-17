<?php

namespace Objectiv\Plugins\Checkout\Features;

use Objectiv\Plugins\Checkout\Managers\SettingsManager;
use Objectiv\Plugins\Checkout\Shipping\CFW_Shipping_Local_Pickup;
use WC_Data_Exception;
use WC_Email;
use WC_Order;
use WC_Shipping_Local_Pickup;
use WC_Shipping_Method;
use WC_Shipping_Rate;
use WP_Roles;

class LocalPickup extends FeaturesAbstract {
	protected $current_order = null;

	protected function run_if_cfw_is_enabled() {
		// Show the delivery method selector
		add_action( 'cfw_checkout_before_customer_info_address', [ $this, 'render_delivery_methods' ] );
		add_filter( 'woocommerce_update_order_review_fragments', [ $this, 'delivery_method_fragment' ] );

		// Show the pickup method selector (maybe)
		$action = 'cfw_after_delivery_method';

		if ( SettingsManager::instance()->get_setting( 'enable_pickup_method_step' ) === 'yes' ) {
			$action = 'cfw_after_shipping_packages';
		}
		add_action( $action, [ $this, 'render_pickup_methods' ] );

		// Remember whether delivery method changed
		add_action( 'cfw_checkout_update_order_review', [ $this, 'maybe_change_delivery_method' ] );

		// Actions that happen when the delivery method changes
		add_action( 'cfw_delivery_method_changed', [ $this, 'on_delivery_method_changed' ] );

		// Limit which shipping methods are available based on whether pickup or delivery is selected
		add_filter( 'cfw_available_shipping_methods', [ $this, 'filter_available_shipping_methods' ], 10 );

		// Add a local pickup setting that we can access from JS
		add_filter( 'cfw_event_object', [ $this, 'add_localized_settings' ] );

		// Change labels based on whether pickup or delivery is selected
		add_filter( 'cfw_ship_to_label', [ $this, 'filter_ship_to_label' ], 10 );
		add_filter( 'cfw_get_review_pane_shipping_address', [ $this, 'filter_shipping_address' ], 10 );
		add_filter( 'cfw_show_shipping_tab', [ $this, 'filter_show_shipping_tab' ], 10 );
		add_filter( 'cfw_cart_totals_shipping_label', [ $this, 'filter_shipping_totals_label' ], 10 );
		add_filter( 'cfw_get_cart_totals_data', [ $this, 'maybe_remove_side_cart_total_line_item' ] );

		// Save the pickup location to the order
		add_action( 'woocommerce_checkout_update_order_meta', [ $this, 'handle_order_meta' ] );

		// Add pickup instructions to the thank you page
		add_action( 'cfw_thank_you_content', [ $this, 'pickup_instructions_wrapped' ], 60, 1 );

		// Show pickup location on orders list view and single order view
		add_filter( 'manage_edit-shop_order_columns', [ $this, 'add_pickup_location_column_header' ], 100 );
		add_action( 'manage_shop_order_posts_custom_column', [ $this, 'pickup_location_column_content' ], 10, 2 );
		add_action(
			'woocommerce_admin_order_data_after_shipping_address',
			[
				$this,
				'pickup_location_view_order',
			],
			10,
			1
		);

		// When pickup is selected, we don't need a shipping address so don't require one on the server side
		add_action( 'cfw_before_process_checkout', [ $this, 'submit_order_actions' ] );
		// Also handle payment gateway AJAX order creation (e.g. ppc-create-order from Payment Plugins for PayPal)
		add_filter( 'woocommerce_checkout_fields', [ $this, 'maybe_unrequire_checkout_fields_for_ajax' ] );
		add_action( 'woocommerce_checkout_posted_data', [ $this, 'handle_missing_shipping_country' ] );
		add_action(
			'woocommerce_countries_shipping_countries',
			[
				$this,
				'handle_invalid_shipping_country_shim',
			]
		);

		// Conditionally disable shipping and pickup methods based on available methods
		add_filter( 'cfw_local_pickup_disable_shipping_option', [ $this, 'maybe_disable_shipping_option' ] );
		add_filter( 'cfw_local_pickup_disable_pickup_option', [ $this, 'maybe_disable_pickup_option' ] );

		// Forcefully add local pickup to shipping methods
		add_action( 'woocommerce_shipping_methods', [ $this, 'add_local_pickup_methods' ], 10, 4 );

		// Forcefully change the default shipping method for a zone if the zone changes during order submit and local pickup is selected
		add_filter( 'woocommerce_shipping_chosen_method', [ $this, 'filter_default_shipping_method' ], 10, 2 );

		add_action(
			'woocommerce_email_before_order_table',
			function ( $order ) {
				$this->current_order = $order;
			}
		);
		add_shortcode( 'cfw_order_pickup_location_info', [ $this, 'pickup_location_shortcode' ] );

		// Add pickup location info to order emails
		add_action( 'woocommerce_email_after_order_table', [ $this, 'add_pickup_location_to_email' ], 9, 4 );

		// Hide shipping method review pane if pickup is selected
		add_filter( 'cfw_review_pane_show_shipping_method', [ $this, 'filter_show_shipping_method' ] );

		// Filter side cart shipping total to show "calculated at checkout"
		add_filter( 'cfw_shipping_free_text', [ $this, 'filter_side_cart_shipping_total' ], 5 );
	}

	public function on_delivery_method_changed() {
		// Clear WooCommerce shipping package cache so that shipping methods are reassessed
		foreach ( WC()->cart->get_shipping_packages() as $package_key => $package ) {
			WC()->session->set( 'shipping_for_package_' . $package_key, false );
		}
	}

	public function render_delivery_methods() {
		if ( ! WC()->cart->needs_shipping() ) {
			return;
		}
		?>
		<?php if ( function_exists( 'cfw_enable_accessibility_improvements' ) && \cfw_enable_accessibility_improvements() ) : ?>
		<h2>
			<?php esc_html_e( 'Delivery method', 'checkout-wc' ); ?>
		</h2>
		<?php else : ?>
		<h3>
			<?php esc_html_e( 'Delivery method', 'checkout-wc' ); ?>
		</h3>
		<?php endif; ?>
		<?php

		echo $this->get_delivery_methods_accordion_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		/**
		 * Fires after the delivery method radio buttons are rendered.
		 *
		 * @since 7.3.0
		 */
		do_action( 'cfw_after_delivery_method' );
	}

	public function delivery_method_fragment( $fragments ) {
		$fragments['#cfw-delivery-method'] = $this->get_delivery_methods_accordion_html();

		return $fragments;
	}

	public function get_delivery_methods_accordion_html() {
		$pickup_option_label = $this->settings_getter->get_setting( 'pickup_option_label' );

		if ( empty( $pickup_option_label ) ) {
			$pickup_option_label = __( 'Pick up', 'checkout-wc' );
		}

		/**
		 * Filters the local pickup option label
		 *
		 * @param string $pickup_option_label The pickup option label
		 *
		 * @since 7.3.1
		 */
		$pickup_option_label = apply_filters( 'cfw_local_pickup_option_label', $pickup_option_label );

		$ship_option_label = $this->settings_getter->get_setting( 'pickup_ship_option_label' );

		if ( empty( $ship_option_label ) ) {
			$ship_option_label = __( 'Ship', 'checkout-wc' );
		}

		/**
		 * Filters the local pickup shipping option label
		 *
		 * @param string $ship_option_label The shipping option label
		 *
		 * @since 7.3.1
		 */
		$ship_option_label = apply_filters( 'cfw_local_pickup_shipping_option_label', $ship_option_label );

		/**
		 * Filters whether the shipping option should be disabled
		 *
		 * @param bool $disable_shipping_option Whether the shipping option should be disabled
		 *
		 * @since 8.1.6
		 */
		$disable_shipping_option = apply_filters( 'cfw_local_pickup_disable_shipping_option', $this->settings_getter->get_setting( 'enable_pickup_ship_option' ) !== 'yes' );

		/**
		 * Filters whether the pickup option should be disabled
		 *
		 * @param bool $disable_pickup_option Whether the pickup option should be disabled
		 *
		 * @since 8.1.6
		 */
		$disable_pickup_option = apply_filters( 'cfw_local_pickup_disable_pickup_option', false );

		ob_start();
		?>
		<div id="cfw-delivery-method" class="cfw-module cfw-accordion">
			<ul class="cfw-radio-reveal-group">
				<?php if ( ! $disable_shipping_option ) : ?>
					<li class="cfw-radio-reveal-li cfw-no-reveal">
						<div class="cfw-radio-reveal-title-wrap">
							<input type="radio" name="cfw_delivery_method" id="cfw_delivery_method_ship_radio" data-persist="false"
									value="ship" class="garlic-auto-save" <?php echo ! self::pickup_is_selected() ? 'checked="checked"' : ''; ?> />

							<label for="cfw_delivery_method_ship_radio" class="cfw-radio-reveal-label">
								<div>
								<span class="cfw-radio-reveal-title">
									<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
										stroke="currentColor" stroke-width="2"><path
											d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/><path
											stroke-linecap="round" stroke-linejoin="round"
											d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/></svg>
									<?php echo esc_html( $ship_option_label ); ?>
								</span>
								</div>
							</label>
						</div>
					</li>
				<?php endif; ?>
				<?php if ( ! $disable_pickup_option ) : ?>
					<li class="cfw-radio-reveal-li">
						<div class="cfw-radio-reveal-title-wrap">
							<input type="radio" name="cfw_delivery_method" id="cfw_delivery_method_pickup_radio" data-persist="false"
									value="pickup"
									class="garlic-auto-save" <?php echo $disable_shipping_option || self::pickup_is_selected() ? 'checked="checked"' : ''; ?> />

							<label for="cfw_delivery_method_pickup_radio" class="cfw-radio-reveal-label">
								<div>
								<span class="cfw-radio-reveal-title">
									<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
										stroke="currentColor" stroke-width="2"><path stroke-linecap="round"
																						stroke-linejoin="round"
																						d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
									<?php echo esc_html( $pickup_option_label ); ?>
								</span>
								</div>
							</label>
						</div>
					</li>
				<?php endif; ?>
			</ul>
		</div>
		<?php
		return ob_get_clean();
	}

	public function render_pickup_methods() {
		if ( ! WC()->cart->needs_shipping() ) {
			return;
		}

		$pickup_locations = get_posts(
			[
				'post_type'        => self::get_post_type(),
				'posts_per_page'   => - 1, // gets all locations
				'suppress_filters' => false,
			]
		);

		if ( ! $pickup_locations ) {
			return;
		}

		$checked = reset( $pickup_locations )->ID;
		?>
		<div id="cfw-pickup-location-wrap">
			<h3>
				<?php esc_html_e( 'Pickup locations', 'checkout-wc' ); ?>
			</h3>
			<div id="cfw-pickup-location" class="cfw-module cfw-accordion">
				<ul class="cfw-radio-reveal-group">
					<?php
					foreach ( $pickup_locations as $pickup_location ) :
						$pickup_time = get_post_meta( $pickup_location->ID, 'cfw_pl_estimated_time', true );

						/**
						 * Filters the pickup location estimated time
						 *
						 * NOTE: Use cfw_pickup_times to extend the list of available pickup times
						 *
						 * @param string $pickup_time The estimated time
						 *
						 * @since 7.5.0
						 */
						$pickup_time = apply_filters( 'cfw_estimated_pickup_time', self::get_pickup_times()[ $pickup_time ] ?? '', $pickup_location->ID );
						?>
						<li class="cfw-radio-reveal-li cfw-no-reveal">
							<div class="cfw-radio-reveal-title-wrap cfw-align-top">
								<input type="radio" name="cfw_pickup_location"
										id="cfw_pickup_location_radio_<?php echo esc_attr( sanitize_title_with_dashes( $pickup_location->ID ) ); ?>"
										value="<?php echo esc_attr( $pickup_location->ID ); ?>" <?php checked( $pickup_location->ID, $checked, true ); ?> />

								<label
									for="cfw_pickup_location_radio_<?php echo esc_attr( sanitize_title_with_dashes( $pickup_location->ID ) ); ?>"
									class="cfw-radio-reveal-label cfw-align-top">
									<div style="align-items: flex-start">
								<span class="cfw-radio-reveal-title">
									<?php echo esc_html( $pickup_location->post_title ); ?>

									<?php $pickup_address = get_post_meta( $pickup_location->ID, 'cfw_pl_address', true ); ?>
									<?php if ( $pickup_address ) : ?>
										<div class="cfw-xtra-small mt-2">
										<?php
										// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
										echo wpautop( $pickup_address );
										// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
										?>
									</div>
									<?php endif; ?>
								</span>
										<div class="cfw-xtra-small">
											<?php
											// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
											echo wpautop( $pickup_time );
											// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
											?>
										</div>
									</div>
								</label>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
		<?php
	}

	public function maybe_change_delivery_method( $post_data ) {
		$saved_delivery_method = WC()->session->get( 'cfw_delivery_method' );

		parse_str( $post_data, $parsed_data );

		if ( isset( $parsed_data['cfw_delivery_method'] ) && $saved_delivery_method !== $parsed_data['cfw_delivery_method'] ) {
			WC()->session->set( 'cfw_delivery_method', $parsed_data['cfw_delivery_method'] );

			/**
			 * Fires when delivery method changes
			 *
			 * @param string $delivery_method The current delivery method
			 *
			 * @since 7.3.2
			 */
			do_action( 'cfw_delivery_method_changed', $parsed_data['cfw_delivery_method'] );
		}
	}

	public function filter_available_shipping_methods( $methods ): array {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		parse_str( wp_unslash( $_POST['post_data'] ?? '' ), $post_data ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$delivery_method = $post_data['cfw_delivery_method'] ?? wc_clean( wp_unslash( $_POST['cfw_delivery_method'] ?? 'none' ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( 'none' === $delivery_method ) {
			return (array) $methods;
		}

		$pickup_methods     = $this->get_pickup_methods( $methods );
		$pickup_is_selected = self::pickup_is_selected();

		// This prevents our shim from showing up if there are other local pickup methods available
		// This restores how it worked previously
		if ( $pickup_is_selected && count( $pickup_methods ) > 1 && isset( $pickup_methods['cfw_local_pickup'] ) ) {
			unset( $pickup_methods['cfw_local_pickup'] );
		}

		if ( $pickup_is_selected ) {
			$methods = array_intersect_key( $methods, $pickup_methods );
		} else {
			$methods = array_diff_key( $methods, $pickup_methods );
		}

		return $methods;
	}

	public function init() {
		parent::init();

		add_action( 'cfw_do_plugin_activation', [ $this, 'run_on_plugin_activation' ] );
		add_action( 'init', [ $this, 'register_post_type' ] );
	}

	public function run_on_plugin_activation() {
		$this->map_capabilities();
	}

	/**
	 * Get pickup methods
	 *
	 * @param array $methods The methods.
	 *
	 * @return array
	 */
	public function get_pickup_methods( array $methods = [] ): array {
		$raw_pickup_methods = (array) $this->settings_getter->get_setting( 'pickup_methods' );
		$pickup_methods     = [];
		$regex              = 'yes' === $this->settings_getter->get_setting( 'enable_pickup_shipping_method_other_regex' );
		$other_label        = (string) $this->settings_getter->get_setting( 'pickup_shipping_method_other_label' );

		foreach ( $raw_pickup_methods as $raw_pickup_method ) {
			$pickup_methods[ $raw_pickup_method ] = $raw_pickup_method;
		}

		// Handle other shipping methods.
		/**
		 * WC_Shipping_Rate instance
		 *
		 * @var WC_Shipping_Rate $method
		 */
		foreach ( $methods as $method ) {
			if ( ! $method instanceof WC_Shipping_Rate ) {
				continue;
			}

			$method_id = $method->get_id();

			// Our shim
			if ( 'cfw_local_pickup' === $method_id ) {
				$pickup_methods[ $method_id ] = $method_id;
				continue;
			}

			// Local Pickup Plus special method
			if ( 'local_pickup_plus' === $method_id ) {
				$pickup_methods[ $method_id ] = $method_id;
				continue;
			}

			if ( stripos( $method_id, 'local_pickup:' ) !== false ) {
				$pickup_methods[ $method_id ] = $method_id;
				continue;
			}

			// Bail if we don't have regex to check
			if ( ! isset( $pickup_methods['other'] ) ) {
				continue;
			}

			// Other method handling
			if (
				( $regex && preg_match( '/' . $other_label . '/i', $method->get_label() ) )
				|| $method->get_label() === $other_label
			) {
				$pickup_methods[ $method_id ] = $method_id;
				break;
			}
		}

		// Cleanup placeholder method
		unset( $pickup_methods['other'] );

		return $pickup_methods;
	}

	public function add_localized_settings( $event_data ): array {
		$event_data['settings']['local_pickup_enabled'] = $this->enabled;
		$event_data['settings']['hide_pickup_methods']  = SettingsManager::instance()->get_setting( 'hide_pickup_methods' ) === 'yes';

		return $event_data;
	}

	public function register_post_type() {
		$labels = [
			'name'               => __( 'Pickup Locations', 'checkout-wc' ),
			'singular_name'      => __( 'Pickup Location', 'checkout-wc' ),
			'menu_name'          => __( 'Pickup Locations', 'checkout-wc' ),
			'add_new'            => __( 'Add Pickup Location', 'checkout-wc' ),
			'add_new_item'       => __( 'Add New Pickup Location', 'checkout-wc' ),
			'edit'               => __( 'Edit', 'checkout-wc' ),
			'edit_item'          => __( 'Edit Pickup Location', 'checkout-wc' ),
			'new_item'           => __( 'New Pickup Location', 'checkout-wc' ),
			'view'               => __( 'View Pickup Locations', 'checkout-wc' ),
			'view_item'          => __( 'View Pickup Location', 'checkout-wc' ),
			'search_items'       => __( 'Search Pickup Locations', 'checkout-wc' ),
			'not_found'          => __( 'No Pickup Location found', 'checkout-wc' ),
			'not_found_in_trash' => __( 'No Pickup Locations found in trash', 'checkout-wc' ),
		];

		$post_type_args = [
			'labels'              => $labels,
			'description'         => __( 'This is where you can add new Pickup Locations.', 'checkout-wc' ),
			'public'              => false,
			'show_ui'             => true,
			'capability_type'     => self::get_post_type(),
			'map_meta_cap'        => true,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_in_menu'        => false,
			'hierarchical'        => false,
			'rewrite'             => false,
			'query_var'           => false,
			'supports'            => [
				'title',
			],
			'show_in_nav_menus'   => false,
		];

		register_post_type( self::get_post_type(), $post_type_args );
	}

	public function map_capabilities() {
		global $wp_roles;

		if ( ! $wp_roles instanceof WP_Roles && class_exists( 'WP_Roles' ) ) {
			// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
			$wp_roles = new WP_Roles();
			// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		if ( ! is_object( $wp_roles ) ) {
			return;
		}

		$args                  = new \stdClass();
		$args->map_meta_cap    = true;
		$args->capability_type = self::get_post_type();
		$args->capabilities    = [];

		foreach ( (array) get_post_type_capabilities( $args ) as $mapped ) {
			$wp_roles->add_cap( 'shop_manager', $mapped );
			$wp_roles->add_cap( 'administrator', $mapped );
		}

		$wp_roles->add_cap( 'shop_manager', 'cfw_manage_local_pickup' );
		$wp_roles->add_cap( 'administrator', 'cfw_manage_local_pickup' );
	}

	public static function get_post_type(): string {
		return 'cfw_pickup_location';
	}

	public static function pickup_is_selected(): bool {
		if ( ! cfw_is_checkout() ) {
			return false;
		}

		$delivery_method = self::get_delivery_method();

		return 'pickup' === $delivery_method;
	}

	public static function get_delivery_method( $default_method = 'ship' ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		parse_str( wp_unslash( $_POST['post_data'] ?? '' ), $post_data ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( WC()->session ) {
			$default_method = WC()->session->get( 'cfw_delivery_method', $default_method );
		}

		// $post_data['cfw_delivery_method'] is for update_checkout phpcs:ignore
		// $_POST['cfw_delivery_method'] is for complete_order
		return $post_data['cfw_delivery_method'] ?? wc_clean( wp_unslash( $_POST['cfw_delivery_method'] ?? $default_method ) );
	}

	public function filter_ship_to_label( $label ): string {

		if ( self::pickup_is_selected() ) {
			return __( 'Method', 'woocommerce' );
		}

		return $label;
	}

	public function filter_shipping_address( $address ): string {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		parse_str( wp_unslash( $_POST['post_data'] ?? '' ), $post_data ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$location = $post_data['cfw_pickup_location'] ?? '';

		if ( empty( $location ) ) {
			return $address;
		}

		$location = get_post( $location );

		if ( self::pickup_is_selected() && $location ) {
			return sprintf( '%s  &bullet; <b>%s</b><div class="mt-2">%s</div>', __( 'Pick up in store', 'checkout-wc' ), $location->post_title, get_post_meta( $location->ID, 'cfw_pl_address', true ) );
		}

		return $address;
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	public function filter_show_shipping_tab( $show ): bool {
		if ( ! self::pickup_is_selected() ) {
			return $show;
		}

		return SettingsManager::instance()->get_setting( 'enable_pickup_method_step' ) === 'yes';
	}

	public function filter_shipping_totals_label( $label ): string {
		if ( self::pickup_is_selected() ) {
			return __( 'Pickup', 'checkout-wc' );
		}

		return $label;
	}

	public function maybe_remove_side_cart_total_line_item( $data ) {
		if ( is_checkout() ) {
			return $data;
		}

		$shipping_total = cfw_sum_packages_shipping_total( WC()->shipping()->get_packages(), WC()->session, WC()->cart );

		if ( $shipping_total > 0 ) {
			return $data;
		}

		unset( $data['total'] );

		return $data;
	}

	/**
	 * @param int $order_id The order ID.
	 * @throws WC_Data_Exception If the order cannot be updated.
	 */
	public function handle_order_meta( int $order_id ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$order = wc_get_order( $order_id );

		if ( ! empty( $_POST['cfw_delivery_method'] ) ) {
			$order->update_meta_data( '_cfw_delivery_method', wc_clean( wp_unslash( $_POST['cfw_delivery_method'] ) ), true );
		}

		if ( ! empty( $_POST['cfw_pickup_location'] ) ) {
			$order->update_meta_data( '_cfw_pickup_location', wc_clean( wp_unslash( $_POST['cfw_pickup_location'] ) ), true );
		}

		/**
		 * Determine whether to copy pickup details to order notes
		 *
		 * @param bool $copy_pickup_details_to_order_notes Whether to copy pickup details to order notes
		 *
		 * @since 7.7.2
		 */
		if ( apply_filters( 'cfw_copy_pickup_details_to_order_notes', false ) && isset( $_POST['cfw_pickup_location'] ) ) {
			$location = (int) $_POST['cfw_pickup_location'];
			$address  = get_post_meta( $location, 'cfw_pl_address', true );
			$name     = get_the_title( $location );

			$existing_note = $order->get_customer_note();
			$newline       = "\r\n\r\n";
			$note          = '';

			if ( ! empty( $existing_note ) ) {
				$note = $existing_note . $newline;
			}

			$note .= __( 'Pickup Location', 'checkout-wc' ) . ':' . $newline;
			$note .= $name . $newline;
			$note .= $address;

			$order->set_customer_note( $note );
		}

		$order->save();
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	public function pickup_instructions_wrapped( WC_Order $order ) {
		cfw_thank_you_section_auto_wrap(
			[ $this, 'pickup_instructions' ],
			'cfw-order-updates',
			[ $order ]
		);
	}

	public function pickup_instructions( WC_Order $order ) {
		$location = $order->get_meta( '_cfw_pickup_location', true );

		if ( empty( $location ) ) {
			return;
		}

		$raw_address = get_post_meta( $location, 'cfw_pl_address', true );

		/**
		 * Whether to link the local pickup address to Google Maps for directions
		 *
		 * @param bool $link Whether to link the local pickup address to Google Maps
		 *
		 * @since 7.3.2
		 */
		if ( apply_filters( 'cfw_local_pickup_use_google_address_link', true ) ) {
			$address = sprintf( '<a href="https://www.google.com/maps/dir/?api=1&destination=%s">%s</a>', rawurlencode( $raw_address ), wpautop( $raw_address ) );
		} else {
			$address = wpautop( $raw_address );
		}

		/**
		 * Filter the local pickup address shown to customers on the thank you page
		 *
		 * @param string $address The local pickup address shown to customers
		 *
		 * @since 7.3.2
		 */
		$address = apply_filters( 'cfw_local_pickup_thank_you_address', $address, $raw_address, $order );
		?>
		<h3>
			<?php
			/**
			 * Filter the pickup instructions
			 *
			 * @param string $instructions The pickup instructions
			 * @param WC_Order $order The order
			 *
			 * @since 7.3.2
			 */
			echo esc_html( apply_filters( 'cfw_order_updates_heading', __( 'Pickup instructions', 'checkout-wc' ), $order ) );
			?>
		</h3>
		<?php
		/**
		 * Filters pickup instructions text
		 *
		 * @param string $pickup_instructions_text Thank you page order updates text
		 *
		 * @since 7.3.0
		 */
		echo wp_kses_post( wpautop( apply_filters( 'cfw_pickup_instructions_text', get_post_meta( $location, 'cfw_pl_instructions', true ), $order ) ) );
		?>
		<h4>
			<?php esc_html_e( 'Address', 'woocommerce' ); ?>
		</h4>
		<?php
		echo wp_kses_post( $address );
	}

	public function add_pickup_location_column_header( array $columns ): array {

		$new_cols = [];

		foreach ( $columns as $key => $value ) {
			$new_cols[ $key ] = $value;

			if ( 'shipping_address' === $key ) {
				$new_cols['pickup_location'] = __( 'Pickup Location', 'checkout-wc' );
			}
		}

		return $new_cols;
	}

	public function pickup_location_column_content( $column, $post_id ) {
		if ( 'pickup_location' !== $column ) {
			return;
		}

		$order = wc_get_order( $post_id );

		if ( ! $order ) {
			return;
		}

		$location = $order->get_meta( '_cfw_pickup_location', true );

		if ( ! $location ) {
			echo '-';

			return;
		}

		$location = get_post( $location );
		$address  = get_post_meta( $location->ID, 'cfw_pl_address', true );

		echo '<b>' . esc_html( $location->post_title ) . '</b><br>' . wp_kses_post( wpautop( $address ) );
	}

	public function pickup_location_view_order( WC_Order $order ) {
		$location = $order->get_meta( '_cfw_pickup_location', true );

		if ( ! $location ) {
			return;
		}

		$location = get_post( $location );
		$address  = get_post_meta( $location->ID, 'cfw_pl_address', true );

		echo '<h3>' . esc_html( __( 'Pickup Location', 'checkout-wc' ) ) . '</h3>';
		echo '<p><i>' . esc_html( $location->post_title ) . '</i><br>' . wp_kses_post( wpautop( $address ) ) . '</p>';
	}

	public function unrequire_checkout_fields( $fields ): array {
		if ( ! self::pickup_is_selected() ) {
			return $fields;
		}

		/**
		 * Filters whether to use billing address as shipping address when local pickup is selected
		 *
		 * @since 9.1.2
		 * @param bool $use_default_billing_address_as_default_shipping_address Whether to use billing address as shipping address when local pickup is selected
		 */
		if ( ! apply_filters( 'cfw_local_pickup_use_default_billing_address_as_default_shipping_address', false ) ) {
			unset( $fields['shipping']['shipping_first_name'] );
			unset( $fields['shipping']['shipping_last_name'] );
			unset( $fields['shipping']['shipping_company'] );
			unset( $fields['shipping']['shipping_city'] );
			unset( $fields['shipping']['shipping_postcode'] );
			unset( $fields['shipping']['shipping_country'] );
			unset( $fields['shipping']['shipping_state'] );
			unset( $fields['shipping']['shipping_address_1'] );
			unset( $fields['shipping']['shipping_address_2'] );
			unset( $fields['shipping']['shipping_phone'] );

			return $fields;
		}

		$billing_fields = [
			'first_name',
			'last_name',
			'company',
			'country',
			'address_1',
			'address_2',
			'city',
			'state',
			'postcode',
		];

		// Workaround for affirm when using the filter: https://secure.helpscout.net/conversation/2632073489/19986?folderId=2454654
		// Sync default billing field value to shipping fields
		foreach ( $billing_fields as $field ) {
			$billing_key  = 'billing_' . $field;
			$shipping_key = 'shipping_' . $field;

			if ( isset( $fields['billing'][ $billing_key ] ) ) {
				$fields['shipping'][ $shipping_key ]['default'] = $fields['billing'][ $billing_key ]['default'] ?? '';
			}
		}

		// Make shipping fields read-only and optional
		foreach ( $fields['shipping'] as $key => $field ) {
			$fields['shipping'][ $key ]['custom_attributes'] = [ 'readonly' => 'readonly' ];
			$fields['shipping'][ $key ]['required']          = false;
		}

		return $fields;
	}

	public function submit_order_actions() {
		add_filter( 'woocommerce_checkout_fields', [ $this, 'unrequire_checkout_fields' ] );
	}

	/**
	 * Unrequire shipping checkout fields during payment gateway AJAX order creation
	 * (e.g. ppc-create-order fired by Payment Plugins for PayPal WooCommerce).
	 *
	 * Normal checkout order submission is handled by submit_order_actions() on
	 * cfw_before_process_checkout. This method covers the AJAX path where that
	 * action never fires.
	 *
	 * @param array $fields Checkout fields.
	 * @return array
	 */
	public function maybe_unrequire_checkout_fields_for_ajax( array $fields ): array {
		if ( ! wp_doing_ajax() ) {
			return $fields;
		}

		return $this->unrequire_checkout_fields( $fields );
	}

	public function handle_missing_shipping_country( $data ): array {
		if ( ! self::pickup_is_selected() ) {
			return $data;
		}

		if ( isset( $data['billing_country'] ) ) {
			$data['shipping_country'] = $data['billing_country'];
		}

		if ( isset( $data['billing_state'] ) ) {
			$data['shipping_state'] = $data['billing_state'];
		}

		return $data;
	}

	public function handle_invalid_shipping_country_shim( $countries ): array {
		if ( self::pickup_is_selected() ) {
			return WC()->countries->get_countries();
		}

		return $countries;
	}

	public static function get_pickup_times() {
		/**
		 * Filters the pickup times
		 *
		 * @param array $pickup_times The pickup times
		 *
		 * @since 7.3.0
		 */
		return apply_filters(
			'cfw_pickup_times',
			[
				'1h'  => __( 'Usually ready in 1 hour.', 'checkout-wc' ),
				'2h'  => __( 'Usually ready in 2 hours.', 'checkout-wc' ),
				'4h'  => __( 'Usually ready in 4 hours.', 'checkout-wc' ),
				'24h' => __( 'Usually ready in 24 hours.', 'checkout-wc' ),
				'24d' => __( 'Usually ready in 2-4 days.', 'checkout-wc' ),
				'5d'  => __( 'Usually ready in 5+ days.', 'checkout-wc' ),
			]
		);
	}

	/**
	 * Disable shipping option if necessary
	 *
	 * @param bool $disable Whether to disable the shipping option.
	 *
	 * @return bool
	 */
	public function maybe_disable_shipping_option( $disable ): bool {
		if ( $disable ) {
			return $disable;
		}

		$has_calculated_shipping = WC()->customer->has_calculated_shipping();
		$address_required        = get_option( 'woocommerce_shipping_cost_requires_address' ) === 'yes';
		$missing_address         = $address_required && ! $has_calculated_shipping;

		if ( $missing_address ) {
			return $disable; // don't treat missing address like missing methods
		}

		// Get all shipping methods
		$shipping_methods = [];

		foreach ( WC()->shipping()->get_packages() as $package ) {
			$methods = cfw_apply_filters( 'woocommerce_package_rates', $package['rates'], $package );

			foreach ( $methods as $method ) {
				$shipping_methods[ $method->get_id() ] = $method;
			}
		}

		// If only shipping methods are pickup methods, disable shipping option
		$pickup_methods = $this->get_pickup_methods( $shipping_methods );

		foreach ( $shipping_methods as $method ) {
			if ( isset( $pickup_methods[ $method->get_id() ] ) ) {
				continue;
			} else {
				return false; // do not disable shipping option - there are non-pickup shipping methods
			}
		}

		return $disable;
	}

	public function maybe_disable_pickup_option( $disable ): bool {
		if ( $disable ) {
			return $disable;
		}

		$has_calculated_shipping = WC()->customer->has_calculated_shipping();
		$address_required        = get_option( 'woocommerce_shipping_cost_requires_address' ) === 'yes';
		$missing_address         = $address_required && ! $has_calculated_shipping;

		if ( $missing_address ) {
			return $disable; // don't treat missing address like missing methods
		}

		// Get all shipping methods
		$shipping_methods = [];

		foreach ( WC()->shipping()->get_packages() as $package ) {
			$methods = cfw_apply_filters( 'woocommerce_package_rates', $package['rates'], $package );

			foreach ( $methods as $method ) {
				$shipping_methods[ $method->get_id() ] = $method;
			}
		}

		$pickup_methods = $this->get_pickup_methods( $shipping_methods );

		// If $shipping_methods doesn't contain any $pickup_methods disable pickup option
		$common_methods = array_intersect_key( $shipping_methods, $pickup_methods );

		if ( empty( $common_methods ) ) {
			$disable = true;
		}

		return $disable;
	}

	public function add_local_pickup_methods( $methods ) {
		$raw_pickup_methods = (array) $this->settings_getter->get_setting( 'pickup_methods' );

		if ( ! empty( $raw_pickup_methods ) ) {
			return $methods;
		}

		$methods[] = CFW_Shipping_Local_Pickup::class;

		return $methods;
	}

	public function filter_default_shipping_method( $default_shipping_method, $rates ) {
		if ( ! self::pickup_is_selected() ) {
			return $default_shipping_method;
		}

		$pickup_methods = $this->get_pickup_methods( $rates );

		if ( empty( $pickup_methods ) ) {
			return $default_shipping_method;
		}

		return key( $pickup_methods );
	}

	public function pickup_location_shortcode() {
		if ( ! $this->current_order ) {
			return '';
		}

		ob_start();
		$this->pickup_instructions( $this->current_order );
		return ob_get_clean();
	}

	public function filter_show_shipping_method( $show ): bool {
		if ( self::pickup_is_selected() ) {
			return false;
		}

		return $show;
	}

	/**
	 * Filter side cart shipping total to show "calculated at checkout" when LocalPickup is enabled
	 *
	 * @param string $text The shipping total HTML.
	 * @return string The filtered shipping total HTML.
	 */
	public function filter_side_cart_shipping_total( $text ) {
		// Only modify for side cart (not checkout)
		if ( ! is_checkout() && WC()->cart->needs_shipping() ) {
			// Return the same format as used in cfw_get_shipping_total()
			return '<span class="cfw-small">' . esc_html__( 'Shipping costs are calculated during checkout.', 'woocommerce' ) . '</span>';
		}
		return $text;
	}

	/**
	 * Add pickup location information to order emails
	 *
	 * @param WC_Order $order The order object.
	 * @param bool     $sent_to_admin Whether the email is sent to admin.
	 * @param bool     $plain_text Whether the email is plain text.
	 * @param WC_Email $email The email object.
	 */
	public function add_pickup_location_to_email( $order, $sent_to_admin, $plain_text, $email ) {
		$delivery_method = $order->get_meta( '_cfw_delivery_method', true );
		$location_id     = $order->get_meta( '_cfw_pickup_location', true );

		// Only show pickup info if this order used pickup delivery method
		if ( 'pickup' !== $delivery_method || empty( $location_id ) ) {
			return;
		}

		$location = get_post( $location_id );
		if ( ! $location ) {
			return;
		}

		/**
		 * Filter whether to show pickup location info in order emails
		 *
		 * @param bool $show_pickup_info Whether to show pickup location info
		 * @param WC_Order $order The order object
		 * @param WC_Email $email The email object
		 *
		 * @since 10.2.8
		 */
		if ( ! apply_filters( 'cfw_show_pickup_location_in_email', true, $order, $email ) ) {
			return;
		}

		// Don't auto-render if the email template already contains the shortcode
		if ( $this->email_template_contains_pickup_shortcode( $email ) ) {
			return;
		}

		$address      = get_post_meta( $location_id, 'cfw_pl_address', true );
		$instructions = get_post_meta( $location_id, 'cfw_pl_instructions', true );

		if ( $plain_text ) {
			$this->render_pickup_info_plain_text( $location, $address, $instructions );
		} else {
			$this->render_pickup_info_html( $location, $address, $instructions, $order );
		}
	}

	/**
	 * Render pickup location info for plain text emails
	 *
	 * @param WP_Post $location The pickup location post.
	 * @param string  $address The pickup address.
	 * @param string  $instructions The pickup instructions.
	 */
	private function render_pickup_info_plain_text( $location, $address, $instructions ) {
		echo "\n" . esc_html__( 'Pickup Location', 'checkout-wc' ) . "\n";
		echo esc_html( str_repeat( '-', 20 ) . "\n" );
		echo esc_html( $location->post_title ) . "\n";

		if ( $address ) {
			echo esc_html( wp_strip_all_tags( $address ) . "\n" );
		}

		if ( $instructions ) {
			echo "\n" . esc_html__( 'Pickup Instructions:', 'checkout-wc' ) . "\n";
			echo esc_html( wp_strip_all_tags( $instructions ) . "\n" );
		}

		echo "\n";
	}

	/**
	 * Render pickup location info for HTML emails
	 *
	 * @param WP_Post  $location The pickup location post.
	 * @param string   $address The pickup address.
	 * @param string   $instructions The pickup instructions.
	 * @param WC_Order $order The order object.
	 */
	private function render_pickup_info_html( $location, $address, $instructions, $order ) {
		echo '<h2 style="color: #96588a; display: block; font-family: &quot;Helvetica Neue&quot;, Helvetica, Roboto, Arial, sans-serif; font-size: 18px; font-weight: bold; line-height: 130%; margin: 16px 0 8px; text-align: left;">' . esc_html__( 'Pickup Location', 'checkout-wc' ) . '</h2>';

		echo '<div style="margin-bottom: 40px;">';
		echo '<p style="margin: 0 0 16px;"><strong>' . esc_html( $location->post_title ) . '</strong></p>';

		if ( $address ) {
			$formatted_address = $this->format_pickup_address( $address, $order );
			echo '<div style="margin: 0 0 16px;">' . wp_kses_post( $formatted_address ) . '</div>';
		}

		if ( $instructions ) {
			echo '<p style="margin: 0 0 8px;"><strong>' . esc_html__( 'Pickup Instructions:', 'checkout-wc' ) . '</strong></p>';

			/** Documented in pickup_instructions */
			$instructions = apply_filters( 'cfw_pickup_instructions_text', $instructions, $order ); // phpcs:ignore

			echo '<div style="margin: 0 0 16px;">' . wp_kses_post( wpautop( $instructions ) ) . '</div>';
		}

		echo '</div>';
	}

	/**
	 * Format pickup address for HTML emails
	 *
	 * @param string   $address The pickup address.
	 * @param WC_Order $order The order object.
	 * @return string The formatted address.
	 */
	private function format_pickup_address( $address, $order ) {
		/** Documented in pickup_instructions */
		$use_google_link = apply_filters( 'cfw_local_pickup_use_google_address_link', true ); // phpcs:ignore

		if ( $use_google_link ) {
			$formatted_address = sprintf( '<a href="https://www.google.com/maps/dir/?api=1&destination=%s" style="color: #96588a;">%s</a>', rawurlencode( wp_strip_all_tags( $address ) ), wpautop( $address ) );
		} else {
			$formatted_address = wpautop( $address );
		}

		/**
		 * Filter the local pickup address shown in order emails
		 *
		 * @param string $address The formatted pickup address
		 * @param string $raw_address The raw pickup address
		 * @param WC_Order $order The order object
		 *
		 * @since 10.2.8
		 */
		return apply_filters( 'cfw_order_email_pickup_address', $formatted_address, $address, $order );
	}

	/**
	 * Check if the email template already contains the pickup shortcode
	 *
	 * @param WC_Email $email The email object.
	 *
	 * @return bool True if the template contains the shortcode
	 */
	private function email_template_contains_pickup_shortcode( WC_Email $email ): bool {
		// Temporarily remove our hook to prevent recursion
		remove_action( 'woocommerce_email_after_order_table', [ $this, 'add_pickup_location_to_email' ], 9 );

		$template_html  = $email->get_content_html();
		$template_plain = $email->get_content_plain();

		// Re-add the hook
		add_action( 'woocommerce_email_after_order_table', [ $this, 'add_pickup_location_to_email' ], 9, 4 );

		// Check for shortcode in both HTML and plain text templates
		return strpos( $template_html, '[cfw_order_pickup_location_info]' ) !== false ||
				strpos( $template_plain, '[cfw_order_pickup_location_info]' ) !== false;
	}
}
