<?php
/**
 * Premium Init
 */

use Objectiv\Plugins\Checkout\Action\AddToCartAction;
use Objectiv\Plugins\Checkout\Action\DeclineOfferAction;
use Objectiv\Plugins\Checkout\Action\ProcessOfferPaymentAction;
use Objectiv\Plugins\Checkout\Action\UpdateCartItemVariation;
use Objectiv\Plugins\Checkout\Action\UpdateSideCart;
use Objectiv\Plugins\Checkout\Admin\AdminPluginsPageManager;
use Objectiv\Plugins\Checkout\Admin\Notices\AcrDisabledNotice;
use Objectiv\Plugins\Checkout\Admin\Notices\InactiveLicenseNotice;
use Objectiv\Plugins\Checkout\Admin\Notices\InvalidLicenseKeyNotice;
use Objectiv\Plugins\Checkout\Admin\Notices\TemplateDisabledNotice;
use Objectiv\Plugins\Checkout\Admin\Notices\TurnstileFeatureNotice;
use Objectiv\Plugins\Checkout\Admin\Notices\WelcomeNotice;
use Objectiv\Plugins\Checkout\Admin\Pages\AdminPagesRegistry;
use Objectiv\Plugins\Checkout\Admin\Pages\Premium\AbandonedCartRecovery as AbandonedCartRecoveryAdminPage;
use Objectiv\Plugins\Checkout\Admin\Pages\Premium\ABTestingAdmin;
use Objectiv\Plugins\Checkout\Admin\Pages\Premium\LocalPickupAdmin as LocalPickupAdminPage;
use Objectiv\Plugins\Checkout\Admin\Pages\Premium\OrderBumps as OrderBumpsAdminPage;
use Objectiv\Plugins\Checkout\Admin\Pages\Premium\PickupLocations as PickupLocationsAdminPage;
use Objectiv\Plugins\Checkout\Admin\Pages\Premium\SideCart as SideCartAdminPage;
use Objectiv\Plugins\Checkout\Admin\Pages\Premium\TrustBadges as TrustBadgesAdminPage;
use Objectiv\Plugins\Checkout\API\AbandonedCartRecoveryReportAPI;
use Objectiv\Plugins\Checkout\API\AbandonedCartsAPI;
use Objectiv\Plugins\Checkout\API\AfterCheckoutBumpProductFormAPI;
use Objectiv\Plugins\Checkout\API\GetVariationFormAPI;
use Objectiv\Plugins\Checkout\API\LocalPickupLocationsAPI;
use Objectiv\Plugins\Checkout\API\ModalOrderBumpProductFormAPI;
use Objectiv\Plugins\Checkout\API\OrderBumpOfferFormPreviewAPI;
use Objectiv\Plugins\Checkout\API\OrderBumpPreviewAPI;
use Objectiv\Plugins\Checkout\API\PostPurchaseBumpProductFormAPI;
use Objectiv\Plugins\Checkout\API\ProductsAndVariationsSearchAPI;
use Objectiv\Plugins\Checkout\Features\AbandonedCartRecovery;
use Objectiv\Plugins\Checkout\Features\ABTesting;
use Objectiv\Plugins\Checkout\Features\CartEditingAtCheckout;
use Objectiv\Plugins\Checkout\Features\FetchifyAddressAutocomplete;
use Objectiv\Plugins\Checkout\Features\GoogleAddressAutocomplete;
use Objectiv\Plugins\Checkout\Features\HideOptionalAddressFields;
use Objectiv\Plugins\Checkout\Features\InternationalPhoneField;
use Objectiv\Plugins\Checkout\Features\LocalPickup;
use Objectiv\Plugins\Checkout\Features\OneClick\GatewayRegistry;
use Objectiv\Plugins\Checkout\Features\OneClick\OfferOrderManager;
use Objectiv\Plugins\Checkout\Features\OneClick\OrderStatusManager;
use Objectiv\Plugins\Checkout\Features\OnePageCheckout;
use Objectiv\Plugins\Checkout\Features\OrderBumps;
use Objectiv\Plugins\Checkout\Features\OrderReviewStep;
use Objectiv\Plugins\Checkout\Features\PhpSnippets;
use Objectiv\Plugins\Checkout\Features\SideCart;
use Objectiv\Plugins\Checkout\Features\SmartyStreets;
use Objectiv\Plugins\Checkout\Features\TrustBadges;
use Objectiv\Plugins\Checkout\Features\Turnstile;
use Objectiv\Plugins\Checkout\Managers\AssetManager;
use Objectiv\Plugins\Checkout\Managers\PlanManager;
use Objectiv\Plugins\Checkout\Managers\SettingsManager;
use Objectiv\Plugins\Checkout\Managers\UpdatesManager;
use Objectiv\Plugins\Checkout\Model\Bumps\BumpAbstract;
use Objectiv\Plugins\Checkout\Stats\StatCollection;
use Objectiv\Plugins\Checkout\Stats\TelemetryCollection;
use Objectiv\Plugins\Checkout\TrustBadgeImageSizeAdder;

// Define premium plan ids, must be done early to avoid issues with plan checks

define(
	'CFW_PREMIUM_PLAN_IDS',
	[
		'basic'  => [
			1, // Basic (Legacy)
			5, // Basic Monthly
			13, // Basic
		],
		'plus'   => [
			2, // Plus (Legacy)
			6, // Plus Monthly (Legacy)
			9, // Plus (5 Sites) - 2023
		],
		'pro'    => [
			7, // Pro (Legacy)
			8, // Pro Monthly (Legacy)
			12, // Pro (10 Sites) - 2023
		],
		'agency' => [
			3, // Agency (Legacy)
			4, // Agency Monthly (Legacy)
			10, // Agency (50 Sites) - 2023
		],
	]
);

/**
 * Load AB tests here
 *
 * @since 8.2.8
 */
do_action( 'cfw_init_ab_tests' );

// Setup our Singletons here
$settings_manager = SettingsManager::instance();
$settings_manager->init();

$stats_collection = StatCollection::instance();
$stats_collection->init();

add_action(
	'action_scheduler_init',
	function() {
		// Initialize Telemetry Collection
		TelemetryCollection::instance()->init();
	}
);

// Hook to send initial telemetry data after 10.1.5 update adds the setting
add_action( 'cfw_updated_to_1036', [ TelemetryCollection::instance(), 'run_telemetry_update' ] );

UpdatesManager::instance()->init( $settings_manager->get_setting( 'enable_beta_version_updates' ) === 'yes' );

if ( PlanManager::can_access_feature( 'enable_acr', 'plus' ) && $settings_manager->get_setting( 'enable_acr' ) === 'yes' ) {
	new AbandonedCartRecoveryReportAPI();
	new AbandonedCartsAPI();
}

new ModalOrderBumpProductFormAPI();
new AfterCheckoutBumpProductFormAPI();
new PostPurchaseBumpProductFormAPI();
new GetVariationFormAPI();
new LocalPickupLocationsAPI();
new OrderBumpOfferFormPreviewAPI();
new OrderBumpPreviewAPI();

/**
 * Plan Availability
 */
$acr = new AbandonedCartRecovery(
	PlanManager::can_access_feature( 'enable_acr', 'plus' ),
	PlanManager::has_premium_plan_or_higher( 'plus' ),
	PlanManager::get_english_list_of_required_plans_html( 'plus' ),
	$settings_manager
);

$acr->init();

// Admin Pages - defer instantiation until init to avoid translation loading issues
add_action(
	'init',
	function() use ( $acr ) {
		add_filter(
			'cfw_admin_pages',
			function( $admin_pages ) use ( $acr ) {
				$admin_pages['side_cart']               = ( new SideCartAdminPage() )->set_priority( 80 );
				$admin_pages['trust_badges']            = ( new TrustBadgesAdminPage() )->set_priority( 90 );
				$admin_pages['order_bumps']             = ( new OrderBumpsAdminPage( BumpAbstract::get_post_type(), PlanManager::get_english_list_of_required_plans_html( 'plus' ), PlanManager::has_premium_plan_or_higher( 'plus' ) ) )->set_priority( 95 );
				$admin_pages['ab_testing']              = ( new ABTestingAdmin() )->set_priority( 96 );
				$admin_pages['local_pickup']            = ( new LocalPickupAdminPage() )->set_priority( 102 );
				$admin_pages['pickup_locations']        = ( new PickupLocationsAdminPage( LocalPickup::get_post_type(), true ) )->set_priority( 103 );
				$admin_pages['abandoned_cart_recovery'] = ( new AbandonedCartRecoveryAdminPage( $acr ) )->set_priority( 104 );

				return $admin_pages;
			}
		);
	},
	5 // Run before the main init hook that processes admin pages
);

/**
 * Premium Features Instantiation
 */
// This should always be first so that it runs before other features
$php_snippets = new PhpSnippets(
	! is_admin(),
	true,
	'',
	$settings_manager,
	$settings_manager->get_field_name( 'php_snippets' )
);
$php_snippets->init();

$order_bumps_feature = new OrderBumps(
	PlanManager::can_access_feature( 'enable_order_bumps', 'plus' ),
	PlanManager::has_premium_plan_or_higher( 'plus' ),
	PlanManager::get_english_list_of_required_plans_html( 'plus' ),
	$settings_manager
);
$order_bumps_feature->init();

add_action( 'cfw_angelleye_paypal_ec_is_express_checkout', [ $order_bumps_feature, 'unhook_order_bumps_output' ] );

$ab_testing_feature = new ABTesting(
	PlanManager::can_access_feature( 'enable_ab_testing', 'pro' ),
	PlanManager::has_premium_plan_or_higher( 'pro' ),
	PlanManager::get_english_list_of_required_plans_html( 'pro' ),
	$settings_manager
);
$ab_testing_feature->init();

// Initialize One-Click Gateway Registry and load all registered gateways
add_action(
	'wp_loaded',
	function() {
		GatewayRegistry::instance()->load_all_gateways();
	},
	20
);

OrderStatusManager::instance()->init();
OfferOrderManager::instance();

$order_review_step = new OrderReviewStep(
	PlanManager::can_access_feature( 'enable_order_review_step' ),
	true,
	'',
	$settings_manager
);
$order_review_step->init();
add_action( 'cfw_angelleye_paypal_ec_is_express_checkout', [ $order_review_step, 'unhook' ] );

$one_page_checkout = new OnePageCheckout(
	PlanManager::can_access_feature( 'enable_one_page_checkout' ),
	true,
	'',
	$settings_manager
);
$one_page_checkout->init();

$address_autocomplete = new GoogleAddressAutocomplete(
	PlanManager::can_access_feature( 'enable_address_autocomplete' ),
	true,
	'',
	$settings_manager
);
$address_autocomplete->init();

$fetchify_address_autocomplete = new FetchifyAddressAutocomplete(
	PlanManager::can_access_feature( 'enable_fetchify_address_autocomplete' ),
	true,
	'',
	$settings_manager
);
$fetchify_address_autocomplete->init();

$trust_badges = new TrustBadges(
	PlanManager::can_access_feature( 'enable_trust_badges' ),
	true,
	'',
	$settings_manager,
	$settings_manager->get_field_name( 'trust_badges' )
);
$trust_badges->init();

$smartystreets_address_validation = new SmartyStreets(
	PlanManager::can_access_feature( 'enable_smartystreets_integration' ),
	true,
	PlanManager::get_english_list_of_required_plans_html(),
	$settings_manager
);
$smartystreets_address_validation->init();

$cart_editing = new CartEditingAtCheckout(
	PlanManager::can_access_feature( 'enable_cart_editing' ),
	true,
	'',
	$settings_manager
);
$cart_editing->init();

$international_phone_field = new InternationalPhoneField(
	PlanManager::can_access_feature( 'enable_international_phone_field' ) && cfw_is_phone_fields_enabled(),
	true,
	'',
	$settings_manager
);
$international_phone_field->init();

$side_cart_enabled = PlanManager::can_access_feature( 'enable_side_cart', 'plus' );

$side_cart = new SideCart(
	$side_cart_enabled,
	PlanManager::has_premium_plan_or_higher( 'plus' ),
	PlanManager::get_english_list_of_required_plans_html( 'plus' ),
	$settings_manager,
	$order_bumps_feature
);

$side_cart->init();

$pickup = new LocalPickup(
	PlanManager::can_access_feature( 'enable_pickup', 'plus' ),
	PlanManager::has_premium_plan_or_higher( 'plus' ),
	PlanManager::get_english_list_of_required_plans_html( 'plus' ),
	$settings_manager
);
$pickup->init();

$turnstile = new Turnstile(
	PlanManager::can_access_feature( 'turnstile_enabled', 'pro' ),
	PlanManager::has_premium_plan_or_higher( 'pro' ),
	PlanManager::get_english_list_of_required_plans_html( 'pro' ),
	$settings_manager
);
$turnstile->init();

$hide_optional_address_fields = new HideOptionalAddressFields(
	'yes' === SettingsManager::instance()->get_setting( 'hide_optional_address_fields_behind_link' ),
	true,
	'',
	$settings_manager
);

$hide_optional_address_fields->init();

// Register deactivation hook for Telemetry Collection cleanup
register_deactivation_hook( CFW_MAIN_FILE, [ TelemetryCollection::instance(), 'deactivate' ] );

if ( $settings_manager->get_setting( 'enable_trust_badges' ) === 'yes' ) {
	add_action( 'init', [ new TrustBadgeImageSizeAdder(), 'add_trust_badge_image_size' ] );
}

// Initialize TurnstileFeatureNotice immediately to catch the upgrade action
$turnstile_feature_notice = new TurnstileFeatureNotice();

add_action(
	'admin_init',
	function() use ( $acr, $turnstile_feature_notice ) {
		if ( ! is_admin() ) {
			return;
		}

		// Plugins page modifications
		( new AdminPluginsPageManager( AdminPagesRegistry::get( 'general' )->get_url() ) )->init();

		( new TemplateDisabledNotice() )->maybe_add(
			'cfw_templates_disabled',
			__( 'CheckoutWC Templates Deactivated', 'checkout-wc' ),
			sprintf(
				/* translators: %1$s: "Settings" text, %2$s: "Start Here" text, %3$s: "Activate CheckoutWC Templates" text */
				esc_html__( 'Your license is valid and activated for this site but CheckoutWC is disabled for normal customers. To fix this, go to %1$s > %2$s and toggle "%3$s".', 'checkout-wc' ),
				esc_html__( 'Settings', 'checkout-wc' ),
				esc_html__( 'Start Here', 'checkout-wc' ),
				esc_html__( 'Activate CheckoutWC Templates', 'checkout-wc' )
			),
			[ 'type' => 'warning' ]
		);

		( new InvalidLicenseKeyNotice() )->maybe_add(
			'cfw_invalid_license',
			__( 'Invalid CheckoutWC License', 'checkout-wc' ),
			sprintf(
				/* translators: %1$s: Pricing URL, %2$s: "purchase a license" link text */
				__( 'Your license key is missing or invalid. Please verify that your license key is valid or <a target="_blank" href="%1$s">%2$s</a> to restore full functionality.', 'checkout-wc' ),
				'https://www.checkoutwc.com/pricing',
				__( 'purchase a license', 'checkout-wc' )
			),
			[
				'type'        => 'error',
				'dismissible' => false,
			]
		);

		( new InactiveLicenseNotice() )->add(
			AdminPagesRegistry::get( 'general' )->get_url()
		);

		( new WelcomeNotice() )->maybe_add();

		$turnstile_feature_notice->maybe_add(
			'cfw_turnstile_feature',
			__( 'New Feature: Cloudflare Turnstile Bot Protection', 'checkout-wc' ),
			sprintf(
				/* translators: 1: Cloudflare Turnstile name wrapped in strong tags, 2: Setup link */
				__( 'CheckoutWC now supports %1$s - a privacy-first, CAPTCHA-free way to protect your checkout from bots and spam orders. %2$s', 'checkout-wc' ),
				sprintf(
					'<strong>%s</strong>',
					/* translators: The name of the Cloudflare Turnstile service */
					__( 'Cloudflare Turnstile', 'checkout-wc' )
				),
				PlanManager::has_premium_plan_or_higher( 'pro' ) ? sprintf(
					/* translators: 1: Link text to setup feature, 2: Link anchor text for setting up feature */
					'<a href="%1$s" target="_blank">%2$s</a>',
					admin_url( 'admin.php?page=cfw-settings-integrations' ),
					__( 'Setup Cloudflare Turnstile now', 'checkout-wc' )
				) : sprintf(
					/* translators: 1: Link text to upgrade plan, 2: Upgrade plan anchor text */
					'<a href="%1$s" target="_blank">%2$s</a>',
					'https://www.checkoutwc.com/documentation/upgrading-your-license/',
					__( 'Upgrade your plan now to access', 'checkout-wc' )
				)
			),
			[
				'type'        => 'info',
				'dismissible' => true,
			]
		);

		$acr_disabled_notice = new AcrDisabledNotice();
		$acr_disabled_notice->set_feature( $acr );
		$acr_disabled_notice->maybe_add(
			'cfw_acr_tracking_disabled',
			__( 'Abandoned Cart Recovery Tracking Disabled', 'checkout-wc' ),
			sprintf(
				/* translators: %1$s: Gist URL, %2$s: "Learn More" link text */
				__( 'You have enabled Abandoned Cart Recovery tracking, but you do not have any emails published. To track carts, you need to publish emails or use this dev filter: <a target="_blank" href="%1$s">%2$s</a>.', 'checkout-wc' ),
				'https://gist.github.com/clifgriffin/490f366d1e75779becc0447384a3ce13',
				__( 'Learn More', 'checkout-wc' )
			),
			[
				'type'        => 'warning',
				'dismissible' => true,
			]
		);

		// Initialize offer refund manager for admin metabox
		Objectiv\Plugins\Checkout\Features\OneClick\OfferRefundManager::instance();
	},
	10
);

add_action(
	'init',
	function() {
		cfw_register_scripts( [ 'blocks' ] );
		AssetManager::enqueue_style( 'blocks-styles' );

		register_block_type( CFW_PATH . '/blocks/order-bump-steps/block.json' );
		register_block_type( CFW_PATH . '/blocks/order-bump-offer-form/block.json' );
	}
);

// Load APIs that depend on WooCommerce
add_action(
	'rest_api_init',
	function() {
		$products_and_variations_search_api = new ProductsAndVariationsSearchAPI();
		$products_and_variations_search_api->register_routes();
	}
);

add_action(
	'cfw_do_plugin_activation',
	function() {
		$license_file = CFW_PATH . '/purchased_license.php';

		if ( file_exists( $license_file ) ) {
			require $license_file;

			$updates_manager     = UpdatesManager::instance();
			$current_license_key = $updates_manager->get_license_key();

			if ( empty( $license_key ) || ! empty( $current_license_key ) ) {
				return;
			}

			$updates_manager->set_field_value( 'license_key', $license_key );

			$updates_manager->auto_activate_license();
			set_transient( 'cfw_auto_activated', true );
		}
	}
);

add_filter(
	'cfw_admin_preview_message',
	function( $admin_message ) {
		$templates_disabled = cfw_templates_disabled();
		$valid_license      = UpdatesManager::instance()->is_license_valid();

		if ( ! $valid_license && $templates_disabled ) {
			$admin_message = 'Admin Preview Mode: CheckoutWC templates are disabled for normal users. To fix this, please make sure you have a valid license and activate your templates here: WP Admin > CheckoutWC > Start Here';
		}

		if ( ! $valid_license && ! $templates_disabled ) {
			$admin_message = 'Admin Preview Mode: CheckoutWC templates are disabled for normal users. Your license is invalid or not activated for this site. Check your license details here: WP Admin > CheckoutWC > Start Here';
		}

		return $admin_message;
	}
);

add_action(
	'cfw_permissioned_init',
	function() use ( $order_bumps_feature, $smartystreets_address_validation ) {
	// Free shipping auto select
	// Side Cart
		add_action(
		'cfw_cart_updated',
		function( $cart_updated, $context ) {
			if ( 'side_cart' !== $context ) {
				return;
			}

			cfw_maybe_select_free_shipping_method( $cart_updated, 'side_cart' );
		},
		10,
		3
		);

	// Checkout
		add_action(
		'cfw_after_update_checkout_calculated',
		function( $raw_post_data, $was_free_shipping_available_pre_cart_update ) {
			parse_str( $raw_post_data, $post_data );

			cfw_maybe_select_free_shipping_method( isset( $post_data['cart'] ), 'checkout', $was_free_shipping_available_pre_cart_update );
		},
		10,
		2
		);

	// Add to cart
		add_action(
		'woocommerce_add_to_cart',
		function() {
			cfw_maybe_select_free_shipping_method( true, 'add_to_cart' );
		},
		10000
		);

	// AJAX Listeners
		( new UpdateSideCart( $order_bumps_feature ) )->load();
		( new UpdateCartItemVariation() )->load();
		( new AddToCartAction() )->load();
		( new ProcessOfferPaymentAction( $order_bumps_feature ) )->load();
		( new DeclineOfferAction( 'cfw_decline_offer' ) )->load();
		$smartystreets_address_validation->load_ajax_action();

		if ( PlanManager::can_access_feature( 'enable_thank_you_page', 'plus' ) && PlanManager::can_access_feature( 'override_view_order_template' ) ) {
			add_filter(
				'woocommerce_get_view_order_url',
				function( $url, WC_Order $order ) {
					return add_query_arg( 'view', 'true', $order->get_checkout_order_received_url() );
				},
				100,
				2
			);
		}

		/**
		 * User matching
		 */
		if ( PlanManager::can_access_feature( 'user_matching' ) ) {
			// Match new guest orders to accounts
			add_action( 'woocommerce_new_order', 'cfw_maybe_match_new_order_to_user_account', 10, 1 );

			// Match old guest orders to accounts on registration
			add_action( 'cfw_link_orders_for_customer', 'cfw_maybe_link_orders_at_registration', 10, 1 );
			add_action(
				'woocommerce_created_customer',
				function( $user_id ) {
					if ( function_exists( 'as_schedule_single_action' ) ) {
						as_schedule_single_action( time() + 300, 'cfw_link_orders_for_customer', [ $user_id ], 'checkoutwc' );
						wc_get_logger()->info( "CheckoutWC: Scheduled order linking for user {$user_id} in 5 minutes", [ 'source' => 'checkout-wc' ] );

						return;
					}

					wc_get_logger()->warning( 'CheckoutWC: Action Scheduler not available, user matching will not work', [ 'source' => 'checkout-wc' ] );
				}
			);

			// Prevent login requirement on thank you page
			add_filter( 'woocommerce_order_received_verify_known_shoppers', '__return_false' );
		}
	}
);
