<?php

namespace Objectiv\Plugins\Checkout\Features;

use Exception;
use Objectiv\Plugins\Checkout\Factories\BumpFactory;
use Objectiv\Plugins\Checkout\Interfaces\SettingsGetterInterface;
use Objectiv\Plugins\Checkout\Managers\AssetManager;
use Objectiv\Plugins\Checkout\Managers\SettingsManager;
use Objectiv\Plugins\Checkout\Managers\StyleManager;
use WC_Frontend_Scripts;
use WC_Shipping_Free_Shipping;
use WC_Shipping_Zones;

class SideCart extends FeaturesAbstract {
	protected $item_just_added_to_cart = false;
	protected $order_bumps_controller  = false;

	public function __construct( bool $enabled, bool $available, string $required_plans_list, SettingsGetterInterface $settings_getter, OrderBumps $order_bumps_controller ) {
		$this->order_bumps_controller = $order_bumps_controller;

		parent::__construct( $enabled, $available, $required_plans_list, $settings_getter );
	}

	protected function run_if_cfw_is_enabled() {
		/**
		 * Disable side cart if filter is set
		 *
		 * @param bool $disable_side_cart Whether to disable the side cart
		 *
		 * @since 7.2.0
		 */
		if ( apply_filters( 'cfw_disable_side_cart', false ) ) {
			return;
		}

		// Prevent redirecting after add to cart when side cart is on
		add_filter(
			'pre_option_woocommerce_cart_redirect_after_add',
			function () {
				return 'no';
			}
		);

		add_filter( 'woocommerce_add_to_cart_redirect', [ $this, 'maybe_prevent_add_to_cart_redirect' ], 1000 );
		add_action( 'woocommerce_add_to_cart', [ $this, 'detect_item_just_added_to_cart' ] );
		add_action( 'wp', [ $this, 'run_sidecart' ] );
		add_shortcode( 'checkoutwc_cart', [ $this, 'render_shortcode' ] );
		add_filter( 'cfw_custom_css_properties', [ $this, 'add_custom_css_property' ] );
		add_filter( 'cfw_get_cart_static_actions_data', [ $this, 'add_side_cart_actions_data' ] );
		add_filter( 'cfw_checkout_data', [ $this, 'add_side_cart_data' ] );
		add_filter( 'woocommerce_add_to_cart_fragments', [ $this, 'add_data_to_add_to_cart_fragments' ] );
		add_filter( 'wc_add_to_cart_message_html', '__return_empty_string' ); // prevent add to cart notices when side cart is in use
		add_filter( 'cfw_disable_cart_editing', [ $this, 'maybe_disable_side_cart_editing' ], 10, 3 );
		add_filter( 'cfw_disable_cart_variation_editing', [ $this, 'maybe_disable_side_cart_variation_editing' ], 10, 2 );
		add_action( 'woocommerce_set_cart_cookies', [ $this, 'detect_cart_changes' ], 10 );
		add_filter( 'cfw_compatibility_all_products_for_subscriptions_run_on_side_cart', '__return_true' );
		add_filter( 'cfw_compatibility_free_gifts_for_woocommerce_prevent_redirect', '__return_true' );
		add_filter( 'cfw_compatibility_nexcessmu_prevent_disable_fragments', '__return_true' );

		// WooCommerce Setting Page Augmentation
		add_action( 'woocommerce_sections_products', [ $this, 'output_woocommerce_product_settings_notice' ] );
		add_filter( 'woocommerce_get_settings_products', [ $this, 'mark_possibly_overridden_product_settings' ], 10, 1 );
	}

	public function run_sidecart() {
		/**
		 * Filter to disable side cart on specific pages/contexts
		 *
		 * @since 11.0.1
		 * @param bool $disable Whether to disable side cart. Default false.
		 */
		if ( apply_filters( 'cfw_disable_side_cart', false ) ) {
			return;
		}

		add_filter( 'cfw_breadcrumbs', [ $this, 'remove_cart_breadcrumb' ] );
		add_filter( 'cfw_show_return_to_cart_link', '__return_false' );

		if ( SettingsManager::instance()->get_setting( 'enable_order_bumps' ) === 'yes' && SettingsManager::instance()->get_setting( 'enable_order_bumps_on_side_cart' ) === 'yes' ) {
			add_action(
				'cfw_after_side_cart_items_table',
				[
					$this->order_bumps_controller,
					'output_cart_summary_bumps',
				]
			);
			add_action(
				'cfw_after_side_cart_items_table',
				/**
				 * Output side cart bumps
				 */
				function () {
					echo '<div id="cfw_bumps_below_side_cart_items"></div>';
				}
			);

			// Track bumps on checkout for conversion rate purposes
			add_action(
				'cfw_checkout_payment_method_tab',
				function () {
					$bumps     = BumpFactory::get_all( 'publish' );
					$count     = 0;
					$max_bumps = (int) SettingsManager::instance()->get_setting( 'max_bumps' );

					if ( $max_bumps < 0 ) {
						$max_bumps = 999;
					}

					ob_start();
					foreach ( $bumps as $bump ) {
						if ( $count >= $max_bumps ) {
							break;
						}

						if ( $bump->is_displayable() && $bump->is_published() && $bump->get_display_location() === 'below_side_cart_items' ) {
							?>
							<input type="hidden" name="cfw_displayed_order_bump[]" value="<?php echo esc_attr( $bump->get_id() ); ?>"/>
							<?php
							$count++;
						}
					}
				},
				38
			);
		}

		add_filter( 'cfw_side_cart_event_object', [ $this, 'add_localized_settings' ] );

		// Turn off empty cart notice
		remove_action( 'woocommerce_cart_is_empty', 'wc_empty_cart_message', 10 );
		add_action( 'woocommerce_cart_is_empty', 'cfw_output_empty_cart_message', 1 );

		// Move notices output
		remove_action( 'woocommerce_cart_is_empty', 'woocommerce_output_all_notices', 5 );

		add_filter(
			'pre_option_woocommerce_enable_ajax_add_to_cart',
			function ( $result ) {
				if ( SettingsManager::instance()->get_setting( 'enable_side_cart' ) === 'yes' && SettingsManager::instance()->get_setting( 'enable_ajax_add_to_cart' ) === 'yes' ) {
					$result = 'yes';
				}

				return $result;
			},
			10,
			1
		);

		add_filter(
			'woocommerce_cart_redirect_after_error',
			function ( $url ) {
				if ( SettingsManager::instance()->get_setting( 'enable_side_cart' ) !== 'yes' ) {
					return $url;
				}

				// Add cache busting parameter to url
				return add_query_arg( 'nocache', time(), $url );
			}
		);

		// Output custom styles
		add_action(
			'wp_head',
			function () {
				StyleManager::add_styles( 'cfw-side-cart-styles' );

				if ( ! is_checkout() ) {
					StyleManager::queue_custom_font_includes();
				}
			},
			5
		);

		if ( ! is_cfw_page() ) {
			/**
			 * Compatibility Nightmare Avoidance
			 */
			add_action(
				'wp_enqueue_scripts',
				[
					$this,
					'make_sure_cart_fragments_script_is_enqueued',
				],
				100 * 1000
			);
			add_action( 'wp_print_scripts', [ WC_Frontend_Scripts::class, 'localize_printed_scripts' ], 5 );
			add_action( 'wp_print_footer_scripts', [ WC_Frontend_Scripts::class, 'localize_printed_scripts' ], 5 );
			add_action( 'wp_footer', [ $this, 'output_side_cart_and_overlay_markup' ], 1 );
		}
	}

	public function unhook_default_mini_cart_buttons() {
		// Remove default cart widget buttons
		remove_action( 'woocommerce_widget_shopping_cart_buttons', 'woocommerce_widget_shopping_cart_button_view_cart', 10 );
		remove_action( 'woocommerce_widget_shopping_cart_buttons', 'woocommerce_widget_shopping_cart_proceed_to_checkout', 20 );
	}

	public function rehook_default_mini_cart_buttons() {
		// Re-add default cart widget buttons
		add_action( 'woocommerce_widget_shopping_cart_buttons', 'woocommerce_widget_shopping_cart_button_view_cart', 10 );
		add_action( 'woocommerce_widget_shopping_cart_buttons', 'woocommerce_widget_shopping_cart_proceed_to_checkout', 20 );
	}

	public function detect_item_just_added_to_cart() {
		$this->item_just_added_to_cart = true;
	}

	public function make_sure_cart_fragments_script_is_enqueued() {
		WC_Frontend_Scripts::load_scripts();
		wp_enqueue_script( 'wc-cart-fragments' );
	}

	public function output_side_cart_and_overlay_markup() {
		if ( SettingsManager::instance()->get_setting( 'enable_side_cart_payment_buttons' ) === 'yes' ) {
			cfw_do_action( 'woocommerce_before_mini_cart' );
		}
		?>
		<div id="cfw-side-cart-overlay"></div>
		<div class="checkoutwc cfw-grid" id="cfw-side-cart" role="dialog" aria-modal="true" aria-label="<?php _e( 'Cart', 'woocommerce' ); ?>">
			<?php echo '<div id="cfw-side-cart-container"></div>'; ?>
		</div>

		<?php if ( SettingsManager::instance()->get_setting( 'enable_floating_cart_button' ) === 'yes' ) : ?>
			<div id="cfw-side-cart-floating-button"></div>
		<?php endif; ?>
		<?php
	}

	/**
	 * @param array|null $data The data to get the qualification for free shipping status.
	 *
	 * @return bool
	 */
	public function does_cart_qualifies_for_free_shipping( ?array $data = null ): bool {
		$data = $data ?? $this->get_free_shipping_data();

		if ( empty( $data ) ) {
			return false;
		}

		return (bool) $data['has_free_shipping'];
	}

	/**
	 * @param array|null $data The data to use to get the amount remaining.
	 *
	 * @return float|null
	 */
	public function get_remaining_amount_to_qualify_for_free_shipping( ?array $data = null ): ?float {
		$data = $data ?? $this->get_free_shipping_data();

		if ( empty( $data ) ) {
			return null;
		}

		return floatval( $data['amount_remaining'] );
	}

	/**
	 * @param array|null $data The data to use to get the fill percentage.
	 *
	 * @return int
	 */
	public function get_fill_percentage( ?array $data = null ): int {
		$data = $data ?? $this->get_free_shipping_data();

		if ( empty( $data ) ) {
			return 0;
		}

		return intval( $data['fill_percentage'] );
	}

	/**
	 * @return array
	 */
	public function get_free_shipping_data(): array {
		// Don't do calculations if the feature is disabled
		if ( SettingsManager::instance()->get_setting( 'enable_free_shipping_progress_bar' ) !== 'yes' ) {
			return [];
		}

		$data = [];

		$raw_threshold = SettingsManager::instance()->get_setting( 'side_cart_free_shipping_threshold' );
		$threshold     = (float) $raw_threshold;

		// Set up a dummy product for the filter
		$dummy_product = new \WC_Product();
		$dummy_product->set_price( $threshold );
		$dummy_product->set_regular_price( $threshold );

		/**
		 * Filters the free shipping threshold amount
		 *
		 * @param float $threshold The free shipping threshold amount
		 *
		 * @since 8.1.12
		 */
		$threshold                = apply_filters( 'cfw_side_cart_free_shipping_threshold', cfw_apply_filters( 'woocommerce_product_get_price', $threshold, $dummy_product ) );
		$has_free_shipping        = false;
		$amount_remaining         = null;
		$fill_percentage          = null;
		$subtotal                 = WC()->cart->get_displayed_subtotal();
		$has_free_shipping_coupon = false;

		/**
		 * Filters whether to exclude discounts from the subtotal when calculating the free shipping bar
		 *
		 * @param bool $exclude_discounts Whether to exclude discounts from the subtotal when calculating the free shipping bar
		 *
		 * @since 7.10.2
		 */
		$exclude_discounts = apply_filters( 'cfw_side_cart_shipping_bar_data_exclude_discounts', false );

		if ( $exclude_discounts ) {
			if ( WC()->cart->display_prices_including_tax() ) {
				$discount = WC()->cart->get_cart_discount_total() + WC()->cart->get_cart_discount_tax_total();
			} else {
				$discount = WC()->cart->get_cart_discount_total();
			}

			$subtotal = $subtotal - $discount;
		}

		foreach ( WC()->cart->get_applied_coupons() as $coupon_code ) {
			$coupon = new \WC_Coupon( $coupon_code );

			if ( $coupon->get_free_shipping() ) {
				$has_free_shipping_coupon = true;
				break;
			}
		}

		if ( $has_free_shipping_coupon ) {
			$data = [
				'has_free_shipping' => true,
				'amount_remaining'  => 0,
				'fill_percentage'   => 100,
			];

			/**
			 * Filters the free shipping data when a free shipping coupon is applied
			 *
			 * @param array $data
			 *
			 * @since 7.0.5
			 */
			return apply_filters( 'cfw_shipping_bar_data', $data );
		}

		// Check the original, raw threshold value because it will be 0.00 if not set
		if ( ! empty( $raw_threshold ) && is_numeric( $raw_threshold ) ) {
			$data = [
				'has_free_shipping' => ( $subtotal >= $threshold ),
				'amount_remaining'  => $subtotal >= $threshold ? 0 : $threshold - $subtotal,
				'fill_percentage'   => $threshold > 0 ? min( ceil( ( $subtotal / $threshold ) * 100 ), 100 ) : 100,
			];

			/**
			 * Filters the free shipping data when a threshold is set
			 *
			 * @param array $data
			 *
			 * @since 7.0.5
			 */
			return apply_filters( 'cfw_shipping_bar_data', $data );
		}

		WC()->cart->calculate_shipping();

		$packages = WC()->shipping()->get_packages();

		if ( empty( $packages ) ) {
			/**
			 * Filters the free shipping data when no packages are available
			 *
			 * @param array $data
			 *
			 * @since 7.0.5
			 */
			return apply_filters( 'cfw_shipping_bar_data', $data );
		}

		// Only look at first package for this feature
		$available_methods = $packages[0]['rates'] ?? [];

		// Guard against invalid argument for foreach
		if ( ! is_array( $available_methods ) ) {
			$available_methods = [];
		}

		foreach ( $available_methods as $available_method ) {
			if ( $available_method instanceof WC_Shipping_Free_Shipping ) {
				$has_free_shipping = true;
				break;
			}

			if ( 'cfw_local_pickup' !== $available_method->id && 'local_pickup' !== $available_method->method_id && (float) $available_method->cost <= 0 ) {
				$has_free_shipping = true;

				break;
			}
		}

		if ( ! $has_free_shipping ) {
			$shipping_zone    = WC_Shipping_Zones::get_zone_matching_package( $packages[0] );
			$shipping_methods = $shipping_zone->get_shipping_methods( true );

			foreach ( $shipping_methods as $shipping_method ) {
				if ( $shipping_method instanceof WC_Shipping_Free_Shipping && ( 'min_amount' === $shipping_method->requires || 'either' === $shipping_method->requires ) ) {

					if ( 'no' !== $shipping_method->ignore_discounts && $exclude_discounts && ! empty( WC()->cart->get_coupon_discount_totals() ) ) {
						// Maybe add back the discounts if a coupon code rule is overriding
						foreach ( WC()->cart->get_coupon_discount_totals() as $coupon_value ) {
							$subtotal += $coupon_value;
						}
					}

					$min_amount = $shipping_method->min_amount;

					// Set up a dummy product for the filter
					$dummy_product = new \WC_Product();
					$dummy_product->set_price( $min_amount );
					$dummy_product->set_regular_price( $min_amount );

					if ( $subtotal >= cfw_apply_filters( 'cfw_side_cart_free_shipping_threshold', cfw_apply_filters( 'woocommerce_product_get_price', $min_amount, $dummy_product ) ) ) {
						$has_free_shipping = true;
					} else {
						$amount_remaining = $shipping_method->min_amount - $subtotal;
						$fill_percentage  = ceil( ( $subtotal / $shipping_method->min_amount ) * 100 );
					}
					break;
				}
			}
		}

		if ( ! $has_free_shipping && is_null( $amount_remaining ) ) {
			/**
			 * Filters the free shipping data when no free shipping methods are available
			 *
			 * @param array $data
			 *
			 * @since 7.0.5
			 */
			return apply_filters( 'cfw_shipping_bar_data', $data );
		}

		$data = [
			'has_free_shipping' => $has_free_shipping,
			'amount_remaining'  => $amount_remaining,
			'fill_percentage'   => $has_free_shipping ? 100 : $fill_percentage,
		];

		/**
		 * Filters the free shipping data
		 *
		 * @param array $data
		 *
		 * @since 7.0.5
		 */
		return apply_filters( 'cfw_shipping_bar_data', $data );
	}

	public function add_custom_css_property( $properties ) {
		$properties['--cfw-side-cart-free-shipping-progress-indicator']  = SettingsManager::instance()->get_setting( 'side_cart_free_shipping_progress_indicator_color' );
		$properties['--cfw-side-cart-free-shipping-progress-background'] = SettingsManager::instance()->get_setting( 'side_cart_free_shipping_progress_bg_color' );
		$properties['--cfw-side-cart-button-bottom-position']            = SettingsManager::instance()->get_setting( 'floating_cart_button_bottom_position' ) . 'px';
		$properties['--cfw-side-cart-button-right-position']             = SettingsManager::instance()->get_setting( 'floating_cart_button_right_position' ) . 'px';
		$properties['--cfw-side-cart-icon-color']                        = SettingsManager::instance()->get_setting( 'side_cart_icon_color' );
		$properties['--cfw-side-cart-icon-width']                        = SettingsManager::instance()->get_setting( 'side_cart_icon_width' ) . 'px';

		return $properties;
	}

	public function maybe_prevent_add_to_cart_redirect( $redirect_url ) {
		// Special handling for this aptly named plugin: Direct checkout, Add to cart redirect, Quick purchase button, Buy now button, Quick View button for WooCommerce
		if ( isset( $_POST['pi_quick_checkout'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return $redirect_url;
		}

		// Otherwise, stop it
		return false;
	}

	public function add_localized_settings( array $event_data ): array {
		$event_data['settings']['enable_ajax_add_to_cart'] = SettingsManager::instance()->get_setting( 'enable_ajax_add_to_cart' ) === 'yes';
		$event_data['runtime_params']['openCart']          = $this->item_just_added_to_cart;

		return $event_data;
	}

	public function remove_cart_breadcrumb( $breadcrumbs ) {
		unset( $breadcrumbs['cart'] );

		return $breadcrumbs;
	}

	public function render_shortcode( $attributes ): string {
		$attributes = shortcode_atts(
			[
				'color'      => SettingsManager::instance()->get_setting( 'side_cart_icon_color' ),
				'width'      => SettingsManager::instance()->get_setting( 'side_cart_icon_width' ) . 'px',
				'text_color' => '#222',
			],
			$attributes,
			'checkoutwc_cart'
		);

		/**
		 * Filters additional classes for the cart icon shortcode
		 *
		 * @param array $additional_classes Additional classes for the cart icon shortcode
		 *
		 * @since 8.2.18
		 */
		$additional_classes = apply_filters( 'checkoutwc_cart_shortcode_additional_classes', [] );

		$additional_classes[] = 'cfw_cart_icon_shortcode';
		$additional_classes[] = 'cfw-side-cart-open-trigger';

		$output  = "<style>.cfw_cart_icon_shortcode { --cfw-side-cart-icon-color: {$attributes['color']}; --cfw-side-cart-icon-width: {$attributes['width']}; --cfw-side-cart-icon-text-color: {$attributes['text_color']}; }</style>";
		$output .= '<div class="cfw-checkoutwc_cart-shortcode" data-additional-classes="' . join( ' ', $additional_classes ) . '"></div>';

		return $output;
	}

	public static function get_cart_icon_file_contents(): string {
		$custom_attachment_id = SettingsManager::instance()->get_setting( 'side_cart_custom_icon_attachment_id' );

		// Get local file path to attachment
		if ( ! empty( $custom_attachment_id ) ) {
			$path = get_attached_file( $custom_attachment_id );
		} else {
			$filename = SettingsManager::instance()->get_setting( 'side_cart_icon' );
			$path     = CFW_PATH . '/assets/images/cart-icons/' . $filename;
		}

		if ( ! file_exists( $path ) ) {
			return '';
		}

		/**
		 * The path to the side cart icon file
		 *
		 * @since 8.2.7
		 * @param string $path The path to the side cart icon file
		 */
		$path = apply_filters( 'cfw_side_cart_icon_file_path', $path );

		$contents = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		/**
		 * If the custom SVG does not have any meaningful strokes, assume it's solid and apply our special class
		 */
		if ( ! empty( $custom_attachment_id ) && ! self::has_meaningful_stroke( $contents ) ) {
			$contents = str_replace( '<svg ', '<svg class="cfw-side-cart-icon-solid" ', $contents );
		}

		/**
		 * The contents of the side cart icon file
		 *
		 * @since 8.2.7
		 * @param string $path The contents of the side cart icon file
		 */
		return apply_filters( 'cfw_side_cart_icon', $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}

	public static function has_meaningful_stroke( $svg_contents ): bool {
		// Remove stroke-width:0 and stroke:none cases (these are solid icons)
		$normalized = preg_replace( '/stroke-width\s*:\s*0(px|%|em|rem)?/i', '', $svg_contents );
		$normalized = preg_replace( '/stroke\s*:\s*none/i', '', $normalized );

		// Now check if any stroke-related properties remain
		return stripos( $normalized, 'stroke' ) !== false;
	}

	public function add_side_cart_actions_data( $actions ) {
		/**
		 * Fires after side cart items table
		 *
		 * @since 7.0.0
		 */
		$actions['cfw_after_side_cart_items_table'] = cfw_get_action_output( 'cfw_after_side_cart_items_table' );

		/**
		 * Fires after side cart totals table
		 *
		 * @since 7.0.0
		 */
		$actions['cfw_before_side_cart_totals'] = cfw_get_action_output( 'cfw_before_side_cart_totals' );

		/**
		 * Fires after side cart totals table
		 *
		 * @since 7.0.0
		 */
		$actions['cfw_after_side_cart_totals'] = cfw_get_action_output( 'cfw_after_side_cart_totals' );

		$actions['cfw_side_cart_footer_start'] = cfw_get_action_output( 'cfw_side_cart_footer_start' );

		/**
		 * Filter to enable or disable the WooCommerce cart actions
		 *
		 * @since 8.0.0
		 * @param bool $cfw_run_woocommerce_cart_actions
		 */
		$cfw_run_woocommerce_cart_actions = apply_filters( 'cfw_run_woocommerce_cart_actions', false );

		$actions['woocommerce_cart_actions'] = $cfw_run_woocommerce_cart_actions ? cfw_get_action_output( 'woocommerce_cart_actions' ) : '';

		/**
		 * Fires after checkout proceed to cart (and maybe continue shopping) buttons
		 *
		 * @since 7.0.6
		 */
		$actions['cfw_after_side_cart_proceed_to_checkout_button'] = cfw_get_action_output( 'cfw_after_side_cart_proceed_to_checkout_button' );

		$this->unhook_default_mini_cart_buttons();
		$actions['woocommerce_widget_shopping_cart_buttons'] = cfw_get_action_output( 'woocommerce_widget_shopping_cart_buttons' );
		$this->rehook_default_mini_cart_buttons();

		$actions['cfw_after_side_cart_header'] = cfw_get_action_output( 'cfw_after_side_cart_header' );

		return $actions;
	}

	public function add_side_cart_data( $data ): array {
		$free_shipping_data       = $this->get_free_shipping_data();
		$fill_percent             = $this->get_fill_percentage( $free_shipping_data );
		$has_free_shipping        = $this->does_cart_qualifies_for_free_shipping( $free_shipping_data );
		$amount_remaining         = $this->get_remaining_amount_to_qualify_for_free_shipping( $free_shipping_data );
		$random_fallback          = SettingsManager::instance()->get_setting( 'enable_side_cart_suggested_products_random_fallback' ) === 'yes';
		$products                 = SettingsManager::instance()->get_setting( 'enable_side_cart_suggested_products' ) === 'yes' ? cfw_get_suggested_products( 3, $random_fallback ) : [];
		$free_shipping_message    = SettingsManager::instance()->get_setting( 'side_cart_free_shipping_message' );
		$amount_remaining_message = SettingsManager::instance()->get_setting( 'side_cart_amount_remaining_message' );

		if ( empty( $free_shipping_message ) ) {
			$free_shipping_message = __( 'Congrats! You get free standard shipping.', 'checkout-wc' );
		}

		/**
		 * Filter the message displayed when the cart qualifies for free shipping.
		 *
		 * @param string $free_shipping_message
		 *
		 * @since 7.3.0
		 */
		$free_shipping_message = apply_filters( 'cfw_side_cart_free_shipping_progress_bar_free_shipping_message', $free_shipping_message );

		if ( empty( $amount_remaining_message ) ) {
			// translators: %s is the amount remaining for free shipping
			$amount_remaining_message = __( 'You\'re %s away from free shipping!', 'checkout-wc' );
		}

		/**
		 * Filter the message format for the amount remaining for free shipping
		 *
		 * @param string $amount_remaining_message
		 *
		 * @since 7.3.0
		 */
		$amount_remaining_message = apply_filters( 'cfw_side_cart_free_shipping_progress_bar_amount_remaining_message_format', $amount_remaining_message );
		$amount_remaining         = null !== $amount_remaining ? '<strong>' . wc_price( $amount_remaining, [ 'decimals' => 0.0 === fmod( $amount_remaining ?? 0, 1 ) ? 0 : 2 ] ) . '</strong>' : null;
		$amount_remaining_message = sprintf( $amount_remaining_message, $amount_remaining );

		$suggested_products = [];

		foreach ( $products as $product ) {
			$suggested_products[] = [
				'productId'     => $product->get_id(),
				'title'         => $product->get_title(),
				/**
				 * Fires after the suggested product title
				 *
				 * @since 9.0.33
				 * @param int $product_id The ID of the suggested product
				 */
				'afterTitle'    => cfw_get_action_output( 'cfw_after_suggested_product_title', $product->get_id() ),
				'priceHtml'     => $product->get_price_html(),
				'imageTag'      => $product->get_image( 'cfw_cart_thumb' ),
				'isVariable'    => $product->is_type( 'variable' ) && 0 === $product->get_parent_id(),
				'addToCartText' => $product->single_add_to_cart_text(),
				'permalink'     => get_permalink( $product->get_id() ),
			];
		}

		$data['side_cart'] = [
			'free_shipping_progress_bar' => [
				'has_free_shipping'        => $has_free_shipping,
				'amount_remaining'         => $amount_remaining,
				'fill_percentage'          => $fill_percent,
				'free_shipping_message'    => $free_shipping_message,
				'amount_remaining_message' => $amount_remaining_message,
			],
			'suggested_products'         => $suggested_products,
		];

		return $data;
	}

	/**
	 * Add data to add to cart fragment
	 *
	 * @param array $fragments The add to cart fragments.
	 * @throws Exception The exception.
	 */
	public function add_data_to_add_to_cart_fragments( $fragments ) {
		if ( SettingsManager::instance()->get_setting( 'enable_side_cart_totals' ) === 'yes' ) {
			WC()->cart->calculate_shipping();
		}

		$fragments['cfw_data'] = AssetManager::get_data();

		return $fragments;
	}

	public function maybe_disable_side_cart_editing( $disable, $cart_item, $cart_item_key ) {
		if ( is_checkout() ) {
			return $disable;
		}

		/**
		 * Filters whether to disable cart item quantity control on the Side Cart
		 *
		 * @param bool $disable Whether to disable cart item quantity control on the Side Cart
		 * @param array $cart_item The cart item
		 * @param string $cart_item_key The cart item key
		 *
		 * @since 9.0.0
		 */
		return apply_filters( 'cfw_disable_side_cart_item_quantity_control', $disable, $cart_item, $cart_item_key );
	}

	public function maybe_disable_side_cart_variation_editing( $disable, $item ): bool {
		if ( is_checkout() ) {
			return (bool) $disable;
		}

		return SettingsManager::instance()->get_setting( 'allow_side_cart_item_variation_changes' ) !== 'yes' || empty( $item['variation_id'] );
	}

	public function detect_cart_changes( $set ) {
		if ( true !== $set ) {
			return; // cookies aren't being set by Woo so bail
		}

		if ( ! WC()->cart ) {
			return;
		}

		if ( headers_sent() ) {
			return;
		}

		if ( empty( $_COOKIE['cfw_cart_hash'] ) || WC()->cart->get_cart_hash() !== $_COOKIE['cfw_cart_hash'] ) {
			setcookie( 'cfw_cart_hash', WC()->cart->get_cart_hash(), time() + ( DAY_IN_SECONDS * 30 ), '/', COOKIE_DOMAIN, is_ssl() );
		}
	}

	public function output_woocommerce_product_settings_notice() {
		if ( SettingsManager::instance()->get_setting( 'enable_side_cart' ) !== 'yes' ) {
			return;
		}
		?>
		<div id="message" class="updated woocommerce-message inline">
			<p>
				<strong><?php _e( 'CheckoutWC:' ); ?></strong>
				<?php _e( 'Settings marked with asterisks (**) may be overridden based on your Side Cart settings. (CheckoutWC > Side Cart)' ); ?>
			</p>
		</div>
		<?php
	}

	public function mark_possibly_overridden_product_settings( array $settings ): array {
		foreach ( $settings as $key => $setting ) {
			if ( 'woocommerce_enable_ajax_add_to_cart' === $setting['id'] || 'woocommerce_cart_redirect_after_add' === $setting['id'] ) {
				$settings[ $key ]['desc'] = "{$setting['desc']} **";
			}
		}

		return $settings;
	}
}
