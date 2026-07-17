<?php
if ( ! defined( 'WPINC' ) ) {
	die;
}

use CheckoutWC\StellarWP\Installer\Config;
use CheckoutWC\StellarWP\Installer\Installer;
use Objectiv\Plugins\Checkout\Action\AccountExistsAction;
use Objectiv\Plugins\Checkout\Action\ClientSideLogger;
use Objectiv\Plugins\Checkout\Action\CompleteOrderAction;
use Objectiv\Plugins\Checkout\Action\LogInAction;
use Objectiv\Plugins\Checkout\Action\LostPasswordAction;
use Objectiv\Plugins\Checkout\Action\RemoveCouponAction;
use Objectiv\Plugins\Checkout\Action\UpdateCheckoutAction;
use Objectiv\Plugins\Checkout\Action\UpdatePaymentMethodAction;
use Objectiv\Plugins\Checkout\Action\ValidateEmailDomainAction;
use Objectiv\Plugins\Checkout\Action\ValidatePostcodeAction;
use Objectiv\Plugins\Checkout\AddressFieldsAugmenter;
use Objectiv\Plugins\Checkout\Admin\DeactivationSurvey;
use Objectiv\Plugins\Checkout\Admin\Notices\AstraProWarning;
use Objectiv\Plugins\Checkout\Admin\Notices\AvadaWarning;
use Objectiv\Plugins\Checkout\Admin\Notices\BFNotice;
use Objectiv\Plugins\Checkout\Admin\Notices\BluehostPluginNotice;
use Objectiv\Plugins\Checkout\Admin\Notices\DiviWarning;
use Objectiv\Plugins\Checkout\Admin\Notices\GatewayProblemsNotice;
use Objectiv\Plugins\Checkout\Admin\Notices\LiteEmailOptIn;
use Objectiv\Plugins\Checkout\Admin\Notices\PortoWarning;
use Objectiv\Plugins\Checkout\Admin\Notices\WoostifyWarning;
use Objectiv\Plugins\Checkout\Admin\Pages\AbandonedCartRecoveryAdminFree;
use Objectiv\Plugins\Checkout\Admin\Pages\AdminPagesRegistry;
use Objectiv\Plugins\Checkout\Admin\Pages\Advanced;
use Objectiv\Plugins\Checkout\Admin\Pages\Appearance;
use Objectiv\Plugins\Checkout\Admin\Pages\CheckoutEditor;
use Objectiv\Plugins\Checkout\Admin\Pages\ExpressCheckout;
use Objectiv\Plugins\Checkout\Admin\Pages\General;
use Objectiv\Plugins\Checkout\Admin\Pages\Integrations;
use Objectiv\Plugins\Checkout\Admin\Pages\LocalPickupAdminFree;
use Objectiv\Plugins\Checkout\Admin\Pages\OrderBumpsAdminFree;
use Objectiv\Plugins\Checkout\Admin\Pages\PageController;
use Objectiv\Plugins\Checkout\Admin\Pages\SideCartAdminFree;
use Objectiv\Plugins\Checkout\Admin\Pages\Support;
use Objectiv\Plugins\Checkout\Admin\Pages\TrustBadgesAdminFree;
use Objectiv\Plugins\Checkout\Admin\Pages\WooCommercePages;
use Objectiv\Plugins\Checkout\Admin\ShippingPhoneController;
use Objectiv\Plugins\Checkout\Admin\WelcomeScreenActivationRedirector;
use Objectiv\Plugins\Checkout\Admin\WooCommerceAdminScreenAugmenter;
use Objectiv\Plugins\Checkout\API\PreviewSettingsAPI;
use Objectiv\Plugins\Checkout\API\SettingsAPI;
use Objectiv\Plugins\Checkout\API\UserRolesAPI;
use Objectiv\Plugins\Checkout\CartImageSizeAdder;
use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;
use Objectiv\Plugins\Checkout\Compatibility\Gateways\AfterPayKrokedil;
use Objectiv\Plugins\Checkout\Compatibility\Gateways\AmazonPay;
use Objectiv\Plugins\Checkout\Compatibility\Gateways\AmazonPayLegacy;
use Objectiv\Plugins\Checkout\Compatibility\Gateways\AmazonPayV1;
use Objectiv\Plugins\Checkout\Compatibility\Gateways\AuthorizeNet;
use Objectiv\Plugins\Checkout\Compatibility\Gateways\Braintree;
use Objectiv\Plugins\Checkout\Compatibility\Gateways\BraintreeForWooCommerce;
use Objectiv\Plugins\Checkout\Compatibility\Gateways\InpsydePayPalPlus;
use Objectiv\Plugins\Checkout\Compatibility\Gateways\KlarnaCheckout;
use Objectiv\Plugins\Checkout\Compatibility\Gateways\KlarnaPayment;
use Objectiv\Plugins\Checkout\Compatibility\Gateways\KlarnaPayment3;
use Objectiv\Plugins\Checkout\Compatibility\Gateways\Mercado;
use Objectiv\Plugins\Checkout\Compatibility\Gateways\Mollie;
use Objectiv\Plugins\Checkout\Compatibility\Gateways\NMI;
use Objectiv\Plugins\Checkout\Compatibility\Gateways\Oppcw;
use Objectiv\Plugins\Checkout\Compatibility\Gateways\PaymentPluginsPayPal;
use Objectiv\Plugins\Checkout\Compatibility\Gateways\PayPalCheckout;
use Objectiv\Plugins\Checkout\Compatibility\Gateways\PayPalForWooCommerce;
use Objectiv\Plugins\Checkout\Compatibility\Gateways\PayPalPlusCw;
use Objectiv\Plugins\Checkout\Compatibility\Gateways\PostFinance;
use Objectiv\Plugins\Checkout\Compatibility\Gateways\ResursBank;
use Objectiv\Plugins\Checkout\Compatibility\Gateways\Square;
use Objectiv\Plugins\Checkout\Compatibility\Gateways\Stripe;
use Objectiv\Plugins\Checkout\Compatibility\Gateways\StripeWooCommerce;
use Objectiv\Plugins\Checkout\Compatibility\Gateways\Timewise;
use Objectiv\Plugins\Checkout\Compatibility\Gateways\Vipps;
use Objectiv\Plugins\Checkout\Compatibility\Gateways\WebToffeeStripe;
use Objectiv\Plugins\Checkout\Compatibility\Gateways\WooCommercePayments;
use Objectiv\Plugins\Checkout\Compatibility\Gateways\WooCommercePayPalPayments;
use Objectiv\Plugins\Checkout\Compatibility\Gateways\WooCommercePensoPay;
use Objectiv\Plugins\Checkout\Compatibility\Gateways\WooSquarePro;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\ActiveCampaign;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\AdvancedCouponsForWooCommerce;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\AeliaCurrencySwitcher;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\AllProductsForSubscriptions;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\ApplyOnline;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\AstraAddon;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\BeaverThemer;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\BigBlue;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\BlocksyCompanion;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\CartFlows;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\CashierForWooCommerce;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\CheckoutAddressAutoComplete;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\CheckoutManager;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\Chronopost;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\CO2OK;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\CoderockzWooDelivery;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\ConvertKitforWooCommerce;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\CraftyClicks;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\CSSHero;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\CURCYWooCommerceMultiCurrency;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\DiviUltimateFooter;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\DiviUltimateHeader;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\DonationForWooCommerce;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\Elementor;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\ElementorPro;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\EnhancedEcommerceGoogleAnalytics;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\EUVATAssistant;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\EUVATNumber;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\ExtraCheckoutFieldsBrazil;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\FacebookForWooCommerce;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\Fattureincloud;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\FreeGiftsforWooCommerce;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\GermanMarket;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\GoogleAnalyticsProV1;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\IconicWooCommerceDeliverySlots;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\IgniteWooGiftCertificatesPro;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\IndeedAffiliatePro;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\JupiterXCore;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\Kangu;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\Klaviyo;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\MailerLite;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\MartfuryAddons;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\MixPanel;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\MondialRelay;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\MyCredPartialPayments;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\MyParcel;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\MyShipper;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\NexcessMU;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\NextGenGallery;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\NIFPortugal;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\NLPostcodeChecker;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\OneClickUpsells;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\OnePageCheckout as WooCommerceOnePageCheckout;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\OrderDeliveryDate;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\OrderDeliveryDateLite;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\OwnID;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\OxygenBuilder;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\PhoneOrdersForWooCommercePro;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\PimwickGiftCardsPro;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\PixelCaffeine;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\Polylang;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\PortugalDPDPickup;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\PortugalVaspKios;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\PostNL;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\PostNL4;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\PWGiftCardsPro;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\QuantityDiscountsPricingForWoocommerce;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\Reviewbird;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\RouteApp;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\SalientWPBakery;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\SavedAddressesForWooCommerce;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\SendCloud;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\ShipMondo;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\SkyVergeCheckoutAddons;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\StrollikCore;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\SUMOPaymentPlans;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\SUMOSubscriptions;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\ThemeHighCheckoutFieldEditor;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\ThemeHighCheckoutFieldEditorPro;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\Tickera;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\TranslatePress;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\UltimateRewardsPoints;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\UpsellOrderBumpOffer;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\UpSolutionCore;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\WCFieldFactory;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\WCPont;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\Webshipper;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\Weglot;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\WooCommerceAddressValidation;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\WooCommerceAdvancedMessages;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\WooCommerceCarrierAgents;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\WooCommerceCheckoutFieldEditor;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\WooCommerceCore;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\WooCommerceEUUKVATCompliancePremium;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\WooCommerceExtendedCouponFeaturesPro;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\WooCommerceGermanized;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\WooCommerceGermanMarket;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\WooCommerceGiftCards;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\WooCommerceMemberships;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\WooCommerceOrderDelivery;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\WooCommercePakettikauppa;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\WooCommercePhoneOrders;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\WooCommercePointsandRewards;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\WooCommercePriceBasedOnCountry;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\WooCommerceProductBundles;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\WooCommerceProductRecommendations;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\WooCommerceServices;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\WooCommerceShipmentTracking;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\WooCommerceSmartCoupons;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\WooCommerceSubscriptionGifting;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\WooCommerceSubscriptions;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\WooCommerceTipping;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\WooFinvoicer;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\WooFunnelsOrderBumps;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\WoolentorAddons;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\WOOMCMultiCurrency;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\WPCProductBundles;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\WPLoyalty;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\WPML;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\WPRocket;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\YITHCompositeProducts;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\YITHDeliveryDate;
use Objectiv\Plugins\Checkout\Compatibility\Plugins\YITHPointsAndRewards;
use Objectiv\Plugins\Checkout\Compatibility\Themes\Acuva;
use Objectiv\Plugins\Checkout\Compatibility\Themes\Astra;
use Objectiv\Plugins\Checkout\Compatibility\Themes\Atelier;
use Objectiv\Plugins\Checkout\Compatibility\Themes\Atik;
use Objectiv\Plugins\Checkout\Compatibility\Themes\Avada;
use Objectiv\Plugins\Checkout\Compatibility\Themes\Barberry;
use Objectiv\Plugins\Checkout\Compatibility\Themes\BeaverBuilder;
use Objectiv\Plugins\Checkout\Compatibility\Themes\BeTheme;
use Objectiv\Plugins\Checkout\Compatibility\Themes\Blaszok;
use Objectiv\Plugins\Checkout\Compatibility\Themes\Divi;
use Objectiv\Plugins\Checkout\Compatibility\Themes\Electro;
use Objectiv\Plugins\Checkout\Compatibility\Themes\Flatsome;
use Objectiv\Plugins\Checkout\Compatibility\Themes\Flevr;
use Objectiv\Plugins\Checkout\Compatibility\Themes\FuelThemes;
use Objectiv\Plugins\Checkout\Compatibility\Themes\GeneratePress;
use Objectiv\Plugins\Checkout\Compatibility\Themes\Genesis;
use Objectiv\Plugins\Checkout\Compatibility\Themes\Greenmart;
use Objectiv\Plugins\Checkout\Compatibility\Themes\Jupiter;
use Objectiv\Plugins\Checkout\Compatibility\Themes\JupiterX;
use Objectiv\Plugins\Checkout\Compatibility\Themes\Konte;
use Objectiv\Plugins\Checkout\Compatibility\Themes\Listable;
use Objectiv\Plugins\Checkout\Compatibility\Themes\Medizin;
use Objectiv\Plugins\Checkout\Compatibility\Themes\Metro;
use Objectiv\Plugins\Checkout\Compatibility\Themes\Minimog;
use Objectiv\Plugins\Checkout\Compatibility\Themes\Neve;
use Objectiv\Plugins\Checkout\Compatibility\Themes\OceanWP;
use Objectiv\Plugins\Checkout\Compatibility\Themes\Optimizer;
use Objectiv\Plugins\Checkout\Compatibility\Themes\Porto;
use Objectiv\Plugins\Checkout\Compatibility\Themes\Pro;
use Objectiv\Plugins\Checkout\Compatibility\Themes\Savoy;
use Objectiv\Plugins\Checkout\Compatibility\Themes\Shoptimizer;
use Objectiv\Plugins\Checkout\Compatibility\Themes\SpaSalonPro;
use Objectiv\Plugins\Checkout\Compatibility\Themes\Stockie;
use Objectiv\Plugins\Checkout\Compatibility\Themes\The7;
use Objectiv\Plugins\Checkout\Compatibility\Themes\TheBox;
use Objectiv\Plugins\Checkout\Compatibility\Themes\Thrive;
use Objectiv\Plugins\Checkout\Compatibility\Themes\TMOrganik;
use Objectiv\Plugins\Checkout\Compatibility\Themes\Tokoo;
use Objectiv\Plugins\Checkout\Compatibility\Themes\Uncode;
use Objectiv\Plugins\Checkout\Compatibility\Themes\Verso;
use Objectiv\Plugins\Checkout\Compatibility\Themes\Woodmart;
use Objectiv\Plugins\Checkout\Compatibility\Themes\Zidane;
use Objectiv\Plugins\Checkout\DatabaseUpdatesManager;
use Objectiv\Plugins\Checkout\EditorPreviewSettingsOverride;
use Objectiv\Plugins\Checkout\FormFieldAugmenter;
use Objectiv\Plugins\Checkout\Install;
use Objectiv\Plugins\Checkout\Managers\NoticesManager;
use Objectiv\Plugins\Checkout\Managers\PlanManager;
use Objectiv\Plugins\Checkout\Managers\SettingsManager;
use Objectiv\Plugins\Checkout\Model\DetectedPaymentGateway;
use Objectiv\Plugins\Checkout\Model\Template;
use Objectiv\Plugins\Checkout\PhpErrorOutputSuppressor;

// Setup our Singletons here
$settings_manager = SettingsManager::instance();
$settings_manager->init();

AddressFieldsAugmenter::instance();

// TODO: This should eventually be removed...right? Probably want to grandfather people who have had this active and only disable for new installs
( new PhpErrorOutputSuppressor() )->init();

/**
 * REST API Endpoints
 */
add_filter(
	'woocommerce_is_rest_api_request',
	function( $is_rest_api_request ) {
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return $is_rest_api_request;
		}

		// Bail early if this is not our request.
		if ( false === strpos( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), 'checkoutwc' ) ) {
			return $is_rest_api_request;
		}

		return false;
	}
);

// Rest APIs
( new SettingsAPI() );
( new UserRolesAPI() );
( new PreviewSettingsAPI() );

/**
 * Admin Settings Pages
 */
add_action(
	'init',
	function() {
		// Handles Parent Menu and General Menu - instantiate here to avoid translation loading issues
		$appearance_admin_page = new Appearance();
		$general_admin_page    = new General( $appearance_admin_page );

		// These priorities start at 70 because General sets up the main menu on $priority - 5
		// 65 is our target priority for our admin parent menu
		$admin_pages = [
			'general'                 => $general_admin_page->set_priority( 70 ),
			'appearance'              => $appearance_admin_page->set_priority( 72 ),
			'woocommerce_pages'       => ( new WooCommercePages() )->set_priority( 75 ),
			'checkout_editor'         => ( new CheckoutEditor() )->set_priority( 76 ),
			'express_checkout'        => ( new ExpressCheckout() )->set_priority( 77 ),
			'side_cart'               => ( new SideCartAdminFree() )->set_priority( 80 ),
			'trust_badges'            => ( new TrustBadgesAdminFree() )->set_priority( 90 ),
			'order_bumps'             => ( new OrderBumpsAdminFree() )->set_priority( 95 ),
			'local_pickup'            => ( new LocalPickupAdminFree() )->set_priority( 102 ),
			'abandoned_cart_recovery' => ( new AbandonedCartRecoveryAdminFree() )->set_priority( 104 ),
			'integrations'            => ( new Integrations() )->set_priority( 105 ),
			'advanced'                => ( new Advanced() )->set_priority( 110 ),
			'support'                 => ( new Support() )->set_priority( 120 ),
		];

		/**
		 * Filters the admin pages.
		 *
		 * @param array $admin_pages the admin pages
		 *
		 * @since 10.1.0
		 */
		$admin_pages = apply_filters( 'cfw_admin_pages', $admin_pages );

		AdminPagesRegistry::bulk_add( $admin_pages );

		$page_controller = new PageController( $admin_pages );
		$page_controller->init();
	}
);

if ( ! PlanManager::has_premium_plan_or_higher() ) {
	add_action(
		'admin_menu',
		function() {
			add_submenu_page(
			'cfw-settings',
			esc_html__( 'Upgrade to Premium', 'checkout-wc' ),
			esc_html__( 'Upgrade to Premium', 'checkout-wc' ),
			'manage_options',
			esc_url( 'https://www.checkoutwc.com/lite-upgrade/?utm_campaign=liteplugin&utm_medium=admin-menu&utm_source=WordPress&utm_content=Upgrade+to+Pro' )
			);
		},
		125
	);
}

// Editor preview settings override — must run on init so filters are in place before wc-ajax (template_redirect priority 0) runs and exits.
add_action(
	'init',
	function() {
		( new EditorPreviewSettingsOverride() )->init();
	},
	999
);

/**
 * Prevent duplicate add-to-cart actions in the Checkout Editor preview.
 *
 * This lives in global bootstrap (`init.php`) because it must run on frontend preview/`wc-ajax` requests, not only admin page hooks.
 * When the editor preview URL includes an add-to-cart parameter, refreshing the page
 * would normally keep re-adding the same product to the cart on every load.
 * In the special context of the editor preview iframe we instead detect if the
 * product is already present in the cart and, if so, skip the add-to-cart.
 */
add_action(
	'init',
	function() {
		if ( ! function_exists( 'cfw_is_editor_preview' ) || ! cfw_is_editor_preview() ) {
			return;
		}

		add_filter(
			'woocommerce_add_to_cart_validation',
			function( $passed, $product_id, $quantity ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
				if ( null === WC()->cart ) {
					return $passed;
				}

				foreach ( WC()->cart->get_cart() as $cart_item ) {
					$cart_product_id   = isset( $cart_item['product_id'] ) ? (int) $cart_item['product_id'] : 0;
					$cart_variation_id = isset( $cart_item['variation_id'] ) ? (int) $cart_item['variation_id'] : 0;

					if ( $cart_product_id === (int) $product_id || $cart_variation_id === (int) $product_id ) {
						return false;
					}
				}

				return $passed;
			},
			10,
			3
		);
	},
	1
);

/**
 * Add body class in Checkout Editor preview iframe so notices can be hidden with CSS.
 * Kept in global bootstrap so it applies to frontend preview rendering, not just admin page hooks.
 */
add_filter(
	'cfw_body_classes',
	function( $classes ) {
		if ( cfw_is_editor_preview() ) {
			$classes[] = 'cfw-editor-preview';
		}

		return $classes;
	}
);

// Note: The active template has to be setup early because admin pages use it to store active theme specific settings
// The fact that a "get" function is causing outside changes in the ether is an indication this should be refactored.
$active_template = cfw_get_active_template();
add_action( 'cfw_do_plugin_activation', [ $active_template, 'run_on_plugin_activation' ] );

/**
 * Setup Compatibility Modules
 */
$compatibility_modules = [
	// Plugins
	WooCommerceCore::instance(),
	MixPanel::instance(),
	SkyVergeCheckoutAddons::instance(),
	Tickera::instance(),
	PixelCaffeine::instance(),
	OneClickUpsells::instance(),
	GoogleAnalyticsProV1::instance(),
	WooCommerceOnePageCheckout::instance(),
	WooCommerceSubscriptions::instance(),
	WooCommerceSubscriptionGifting::instance(),
	WooCommerceGermanized::instance(),
	CraftyClicks::instance(),
	CheckoutManager::instance(),
	CheckoutAddressAutoComplete::instance(),
	NLPostcodeChecker::instance(),
	PostNL::instance(),
	PostNL4::instance(),
	ActiveCampaign::instance(),
	UltimateRewardsPoints::instance(),
	WooCommerceSmartCoupons::instance(),
	EUVATNumber::instance(),
	FacebookForWooCommerce::instance(),
	Webshipper::instance(),
	OrderDeliveryDate::instance(),
	OrderDeliveryDateLite::instance(),
	WooFunnelsOrderBumps::instance(),
	MartfuryAddons::instance(),
	WCFieldFactory::instance(),
	MondialRelay::instance(),
	SUMOPaymentPlans::instance(),
	WooCommerceAddressValidation::instance(),
	ElementorPro::instance(),
	Elementor::instance(),
	SendCloud::instance(),
	CO2OK::instance(),
	DiviUltimateHeader::instance(),
	DiviUltimateFooter::instance(),
	ExtraCheckoutFieldsBrazil::instance(),
	MyCredPartialPayments::instance(),
	GermanMarket::instance(),
	StrollikCore::instance(),
	WooCommerceCheckoutFieldEditor::instance(),
	IndeedAffiliatePro::instance(),
	ShipMondo::instance(),
	Chronopost::instance(),
	JupiterXCore::instance(),
	OxygenBuilder::instance(),
	Fattureincloud::instance(),
	CSSHero::instance(),
	NIFPortugal::instance(),
	WooCommerceOrderDelivery::instance(),
	PortugalVaspKios::instance(),
	WPCProductBundles::instance(),
	YITHDeliveryDate::instance(),
	CartFlows::instance(),
	PWGiftCardsPro::instance(),
	NextGenGallery::instance(),
	Weglot::instance(),
	WooCommerceGiftCards::instance(), // WooCommerce Gift Cards (official)
	BeaverThemer::instance(),
	WooCommerceCarrierAgents::instance(),
	WooCommerceServices::instance(),
	SalientWPBakery::instance(),
	WCPont::instance(),
	MailerLite::instance(),
	ApplyOnline::instance(),
	WooCommerceExtendedCouponFeaturesPro::instance(),
	WooCommerceGermanMarket::instance(),
	IconicWooCommerceDeliverySlots::instance(),
	MyShipper::instance(),
	EnhancedEcommerceGoogleAnalytics::instance(),
	WooCommercePointsandRewards::instance(),
	SavedAddressesForWooCommerce::instance(),
	TranslatePress::instance(),
	SUMOSubscriptions::instance(),
	UpsellOrderBumpOffer::instance(),
	WooCommerceAdvancedMessages::instance(),
	Klaviyo::instance(),
	ThemeHighCheckoutFieldEditor::instance(),
	ThemeHighCheckoutFieldEditorPro::instance(),
	WooCommercePakettikauppa::instance(),
	WooFinvoicer::instance(),
	WooCommerceShipmentTracking::instance(),
	WooCommerceTipping::instance(),
	WooCommerceProductBundles::instance(),
	MyParcel::instance(),
	CoderockzWooDelivery::instance(),
	CURCYWooCommerceMultiCurrency::instance(),
	WOOMCMultiCurrency::instance(),
	WooCommerceProductRecommendations::instance(),
	CashierForWooCommerce::instance(),
	BigBlue::instance(),
	DonationForWooCommerce::instance(),
	WooCommercePriceBasedOnCountry::instance(),
	PimwickGiftCardsPro::instance(),
	WPML::instance(),
	WoolentorAddons::instance(),
	AdvancedCouponsForWooCommerce::instance(),
	Polylang::instance(),
	QuantityDiscountsPricingForWoocommerce::instance(),
	Reviewbird::instance(),
	RouteApp::instance(),
	PortugalDPDPickup::instance(),
	OwnID::instance(),
	WooCommercePhoneOrders::instance(),
	AeliaCurrencySwitcher::instance(),
	PhoneOrdersForWooCommercePro::instance(),
	WPLoyalty::instance(),
	AllProductsForSubscriptions::instance(),
	Kangu::instance(),
	BlocksyCompanion::instance(),
	IgniteWooGiftCertificatesPro::instance(),
	EUVATAssistant::instance(),
	WPRocket::instance(),
	FreeGiftsforWooCommerce::instance(),
	NexcessMU::instance(),
	YITHCompositeProducts::instance(),
	WooCommerceMemberships::instance(),
	YITHPointsAndRewards::instance(),

	// Gateways
	PayPalCheckout::instance(),
	Stripe::instance(),
	PayPalForWooCommerce::instance(),
	Braintree::instance(),
	BraintreeForWooCommerce::instance(),
	AmazonPay::instance(),
	AmazonPayLegacy::instance(),
	AmazonPayV1::instance(),
	KlarnaCheckout::instance(),
	KlarnaPayment::instance(),
	KlarnaPayment3::instance(),
	AfterPayKrokedil::instance(),
	InpsydePayPalPlus::instance(),
	WooSquarePro::instance(),
	PayPalPlusCw::instance(),
	PostFinance::instance(),
	Square::instance(),
	StripeWooCommerce::instance(),
	WooCommercePensoPay::instance(),
	Vipps::instance(),
	ConvertKitforWooCommerce::instance(),
	WooCommercePayments::instance(),
	WooCommercePayPalPayments::instance(),
	UpSolutionCore::instance(),
	WooCommerceEUUKVATCompliancePremium::instance(),
	NMI::instance(),
	PaymentPluginsPayPal::instance(),
	Mercado::instance(),
	Oppcw::instance(),
	AuthorizeNet::instance(),
	Mollie::instance(),
	WebToffeeStripe::instance(),
	ResursBank::instance(),
	Timewise::instance(),

	// Themes
	Avada::instance(),
	Porto::instance(),
	GeneratePress::instance(),
	TMOrganik::instance(),
	BeaverBuilder::instance(),
	Astra::instance(),
	Savoy::instance(),
	OceanWP::instance(),
	Atelier::instance(),
	Jupiter::instance(),
	The7::instance(),
	Zidane::instance(),
	Atik::instance(),
	Optimizer::instance(),
	Verso::instance(),
	Listable::instance(),
	Flevr::instance(),
	Divi::instance(),
	Electro::instance(),
	JupiterX::instance(),
	Blaszok::instance(),
	Konte::instance(),
	Genesis::instance(),
	TheBox::instance(),
	Barberry::instance(),
	Stockie::instance(),
	Tokoo::instance(),
	FuelThemes::instance(),
	SpaSalonPro::instance(),
	Shoptimizer::instance(),
	Flatsome::instance(),
	Pro::instance(),
	Uncode::instance(),
	Neve::instance(),
	AstraAddon::instance(),
	Thrive::instance(),
	BeTheme::instance(),
	Minimog::instance(),
	Medizin::instance(),
	Woodmart::instance(),
	Greenmart::instance(),
	Acuva::instance(),
	Metro::instance(),
];

add_filter( 'cfw_blocked_style_handles', 'cfw_remove_theme_styles', 10, 1 );
add_filter( 'cfw_blocked_script_handles', 'cfw_remove_theme_scripts', 10, 1 );

/**
 * Misc Admin Stuff That Defies Cogent Categorization For The Moment
 */
( new ShippingPhoneController() )->init();
( new WooCommerceAdminScreenAugmenter() )->init();
CartFlows::instance()->admin_init();

add_action(
	'admin_init',
	function() {
		if ( ! is_admin() ) {
			return;
		}

		if ( wp_doing_ajax() || defined( 'WC_DOING_AJAX' ) ) {
			return;
		}

		if ( ! get_transient( '_cfw_100_upgrade_welcome_redirect' ) ) {
			return;
		}

		delete_transient( '_cfw_100_upgrade_welcome_redirect' );

		// Redirect to 10.0 upgrade screen
		wp_safe_redirect(
			add_query_arg(
				[
					'page'    => 'cfw-settings',
					'upgrade' => '10',
				],
				admin_url( 'admin.php' )
			)
		);
	}
);

add_action(
	'admin_head',
	function() {
		echo '<style>';
		echo 'div[id^="pressmodo-notice-cfw"].notice { display: flex; align-items: center; }';
		echo 'div[id^="pressmodo-notice-cfw"].notice .pressmodo-notice-image { flex: 0 0 90px; margin: 0.5em; }';
		echo '</style>';
	}
);

/**
 * Hide screen options for specific post types
 */
add_filter(
	'screen_options_show_screen',
	function( $show ) {
		global $post, $typenow;

		$post_types_to_hide = [
			'cfw_order_bumps',
			'cfw_pickup_location',
			'cfw_acr_emails',
			'cfw_ab_test',
		];

		$current_post_type = $typenow;

		if ( ! $current_post_type && $post ) {
			$current_post_type = $post->post_type;
		}

		if ( isset( $_GET['post_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$current_post_type = sanitize_text_field( wp_unslash( $_GET['post_type'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		if ( in_array( $current_post_type, $post_types_to_hide, true ) ) {
			return false;
		}

		return $show;
	}
);

add_action(
	'init',
	function() {
		if ( isset( $_GET['clear-all-acr-carts'], $_GET['nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'clear-all-acr-carts' ) ) {
			global $wpdb;

			$table_name = $wpdb->prefix . 'cfw_acr_carts';
			$wpdb->query( "DELETE FROM {$table_name} WHERE id > 0" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}
);

add_action( 'cfw_do_plugin_activation', [ new Install(), 'init' ] );
add_action( 'init', [ DatabaseUpdatesManager::instance(), 'init' ] );
add_action( 'init', [ new CartImageSizeAdder(), 'add_cart_image_size' ] );
add_action(
	'init',
	function() {
		load_plugin_textdomain(
			'checkout-wc',
			false,
			dirname( plugin_basename( CFW_MAIN_FILE ) ) . '/i18n/languages'
		);
	},
	0
);

add_action(
	'init',
	function() {
		// Menu location for template footer
		register_nav_menu( 'cfw-footer-menu', __( 'CheckoutWC: Footer', 'checkout-wc' ) );
	},
	1
);

add_action(
	'plugins_loaded',
	function() use ( $compatibility_modules ) {
		if ( ! cfw_is_enabled() ) {
			return;
		}

		/**
		 * Compatibility Pre-init
		 *
		 * Priority 1 (rather than -1000) because init.php is now loaded inside a plugins_loaded@0
		 * callback. WordPress does not re-run past priorities, so -1000 would never fire.
		 * Priority 1 is still within plugins_loaded (all plugin constants are available) and
		 * is early enough for everything pre_init() needs to register.
		 */
		/** @var CompatibilityAbstract $module */
		foreach ( $compatibility_modules as $module ) {
			$module->pre_init();
		}
	},
	1
);

/**
 * WP Admin Notices handler
 *
 * Init here so that it's always available (Ticket #19399)
 */
NoticesManager::instance()->init();

add_action(
	'admin_init',
	function() {
		if ( ! is_admin() ) {
			return;
		}

		( new AvadaWarning() )->maybe_add(
			'cfw_avada_warning',
			__( 'Configure Avada to Work With CheckoutWC', 'checkout-wc' ),
			sprintf(
				/* translators: %1$s: Guide URL, %2$s: Guide link text */
				__( 'CheckoutWC and Avada work great together, but you will need to adjust a couple of theme settings. Please see our guide here: <a target="_blank" href="%1$s">%2$s</a>', 'checkout-wc' ),
				'https://www.checkoutwc.com/documentation/avada-template-layout-problems/',
				__( 'Configure Avada to Work With CheckoutWC', 'checkout-wc' )
			),
			[ 'type' => 'warning' ]
		);

		( new BluehostPluginNotice() )->maybe_add(
			'cfw_bluehost_warning',
			__( 'Incompatible Plugin: Please Deactivate The Bluehost Plugin', 'checkout-wc' ),
			sprintf(
				/* translators: %1$s: Help URL, %2$s: Help link text */
				__( 'CheckoutWC and Bluehost work great together, but you must deactivate The Bluehost Plugin to prevent problems. More info here: <a target="_blank" href="%1$s">%2$s</a>', 'checkout-wc' ),
				'https://www.checkoutwc.com/documentation/how-to-fix-problems-with-the-bluehost-plugin/',
				__( 'How To Fix Problems With The Bluehost Plugin', 'checkout-wc' )
			),
			[
				'type'        => 'error',
				'dismissible' => false,
			]
		);

		( new DiviWarning() )->maybe_add(
			'cfw_divi_warning',
			__( 'Configure Divi to Work With CheckoutWC', 'checkout-wc' ),
			sprintf(
				/* translators: %1$s: Guide URL, %2$s: Guide link text */
				__( 'CheckoutWC and Divi work great together, but you will need to adjust a couple of settings. Please see our guide here: <a target="_blank" href="%1$s">%2$s</a>', 'checkout-wc' ),
				'https://www.checkoutwc.com/documentation/how-to-fix-styling-issues-with-divi-theme/',
				__( 'How To Fix Styling Issues With Divi Theme', 'checkout-wc' )
			),
			[ 'type' => 'warning' ]
		);

		( new PortoWarning() )->maybe_add(
			'cfw_porto_warning',
			__( 'Configure Porto to Work With CheckoutWC', 'checkout-wc' ),
			sprintf(
				/* translators: %1$s: Guide URL, %2$s: Guide link text */
				__( 'CheckoutWC and Porto work great together, but you will need to adjust a couple of settings. Please see our guide here: <a target="_blank" href="%1$s">%2$s</a>', 'checkout-wc' ),
				'https://www.checkoutwc.com/documentation/fix-problems-with-porto-theme/',
				__( 'Fix Problems With Porto Theme', 'checkout-wc' )
			),
			[ 'type' => 'warning' ]
		);

		( new AstraProWarning() )->maybe_add(
			'cfw_astrapro_warning',
			__( 'Configure Astra Pro to Work With CheckoutWC', 'checkout-wc' ),
			sprintf(
				/* translators: %1$s: Info URL, %2$s: Info link text */
				__( 'CheckoutWC and Astra Pro work great together, but there is one setting you should check. More info here: <a target="_blank" href="%1$s">%2$s</a>', 'checkout-wc' ),
				'https://checkoutwc.com/documentation/fix-layout-issues-with-astra-pro/',
				__( 'Fix Layout Issues with Astra Pro', 'checkout-wc' )
			),
			[ 'type' => 'warning' ]
		);

		( new WoostifyWarning() )->maybe_add(
			'cfw_woostify_warning',
			__( 'Configure Woostify to Work With CheckoutWC', 'checkout-wc' ),
			sprintf(
				/* translators: %1$s: Info URL, %2$s: Info link text */
				__( 'CheckoutWC and Woostify work great together, but there is one setting you should check. More info here: <a target="_blank" href="%1$s">%2$s</a>', 'checkout-wc' ),
				'https://www.checkoutwc.com/documentation/fix-ajax-add-to-cart-with-woostify-and-checkoutwc/',
				__( 'Fix AJAX Add To Cart with Woostify and CheckoutWC Side Cart', 'checkout-wc' )
			),
			[ 'type' => 'warning' ]
		);

		add_filter(
			'stellarwp/installer/cfw/button_classes',
			function( $classes ) {
				$classes[] = 'components-button';
				$classes[] = 'is-primary';

				return $classes;
			}
		);

		try {
			Config::set_hook_prefix( 'cfw' );
		} catch ( Exception $e ) {
			cfw_debug_log(
				'Admin init ran multiple times in this request; config already set. Potential causes: theme builders (BeTheme, Divi, etc.), page editors that re-run admin_init, or plugins that re-bootstrap the admin. Exception: ' . $e->getMessage()
			);
		}

		Installer::init();

		$detected_gateways = cfw_apply_filters( 'cfw_detected_gateways', [] );

		/**
		 * Detected payment gateways
		 *
		 * @var DetectedPaymentGateway $gateway
		 */
		foreach ( $detected_gateways as $gateway ) {
			if ( $gateway->show_notice && ! empty( $gateway->recommendation ) ) {
				( new GatewayProblemsNotice() )->build( $gateway );
			}
		}

		( new BFNotice() )->maybe_add();

		// Lite email opt-in notice
		( new LiteEmailOptIn() )->add();
	},
	10
);

register_deactivation_hook(
	CFW_MAIN_FILE,
	function() {
		/**
		 * Fires after plugin deactivation.
		 *
		 * @since 1.0.0
		 */
		do_action( 'cfw_do_plugin_deactivation' );
	}
);

add_filter(
	'cfw_disable_woocommerce_gift_cards_compatibility',
	function() {
		return class_exists( '\\WC_GC_Coupon_Input' );
	}
);

if ( SettingsManager::instance()->get_setting( 'skip_cart_step' ) === 'yes' ) {
	add_filter(
		'woocommerce_add_to_cart_redirect',
		function() {
			return wc_get_checkout_url();
		}
	);

	add_filter(
		'cfw_breadcrumbs',
		function( $breadcrumbs ) {
			unset( $breadcrumbs['cart'] );

			return $breadcrumbs;
		}
	);
}

add_filter(
	'cfw_get_billing_checkout_fields',
	function( $fields ) {
		if ( is_null( WC()->cart ) ) {
			return $fields;
		}

		$original_fields = [
			'billing_first_name',
			'billing_last_name',
			'billing_address_1',
			'billing_address_2',
			'billing_company',
			'billing_country',
			'billing_postcode',
			'billing_state',
			'billing_city',
			'billing_phone',
		];

		$enabled_fields = cfw_get_setting( 'enabled_billing_address_fields', null, [] );

		if ( SettingsManager::instance()->get_setting( 'hide_billing_address_for_free_orders' ) === 'yes' && ! WC()->cart->needs_payment() && WC()->cart->needs_shipping_address() ) {
			$enabled_fields = [];
		}

		if ( SettingsManager::instance()->get_setting( 'hide_billing_address_for_free_orders' ) === 'yes' && ! WC()->cart->needs_payment() && ! WC()->cart->needs_shipping_address() ) {
			$enabled_fields = [ 'billing_first_name', 'billing_last_name' ];
		}

		foreach ( $original_fields as $field_key ) {
			if ( ! in_array( $field_key, $enabled_fields, true ) ) {
				unset( $fields[ $field_key ] );
			}
		}

		return $fields;
	},
	100
);

add_filter(
	'woocommerce_checkout_fields',
	function( $fields ) {
		if ( is_null( WC()->cart ) ) {
			return $fields;
		}

		$original_fields = [
			'billing_first_name',
			'billing_last_name',
			'billing_address_1',
			'billing_address_2',
			'billing_company',
			'billing_country',
			'billing_postcode',
			'billing_state',
			'billing_city',
			'billing_phone',
		];

		$enabled_fields = cfw_get_setting( 'enabled_billing_address_fields', null, [] );

		if ( SettingsManager::instance()->get_setting( 'hide_billing_address_for_free_orders' ) === 'yes' && ! WC()->cart->needs_payment() && WC()->cart->needs_shipping_address() ) {
			$enabled_fields = [];
		}

		if ( SettingsManager::instance()->get_setting( 'hide_billing_address_for_free_orders' ) === 'yes' && ! WC()->cart->needs_payment() && ! WC()->cart->needs_shipping_address() ) {
			$enabled_fields = [ 'billing_first_name', 'billing_last_name' ];
		}

		foreach ( $original_fields as $field_key ) {
			if ( ! in_array( $field_key, $enabled_fields, true ) ) {
				if ( isset( $fields['billing'][ $field_key ] ) ) {
					unset( $fields['billing'][ $field_key ] );
				}
			}
		}

		return $fields;
	}
);

add_action( 'admin_init', [ new WelcomeScreenActivationRedirector(), 'welcome_screen_do_activation_redirect' ] );

/**
 * Initialize deactivation survey for both Pro and Lite versions.
 *
 * @since 11.0.1
 */
add_action(
	'admin_init',
	function() {
		( new DeactivationSurvey() )->init();
	}
);

/**
 * Warning to admins about disabled templates
 */
add_action(
	'cfw_before_print_notices',
	function() {
		$templates_disabled = cfw_templates_disabled();

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$admin_message = '';

		if ( $templates_disabled ) {
			$admin_message = __( 'Admin Preview Mode: CheckoutWC templates are disabled for normal users. To fix this, please activate templates here: WP Admin > CheckoutWC > Start Here', 'checkout-wc' );
		}

		/**
		 * Filter the admin preview message.
		 *
		 * @param string $admin_message the admin preview message
		 * @return string the admin preview message
		 *
		 * @since 10.1.0
		 */
		$admin_message = apply_filters( 'cfw_admin_preview_message', $admin_message );

		if ( empty( $admin_message ) ) {
			return;
		}

		wc_add_notice( $admin_message, 'notice' );
	}
);

/**
 * Permissioned Init
 *
 * Nothing south of this check should run if templates aren't enabled
 *
 * This has to run on init because we need to be able to use current_user_can()
 * See: https://wordpress.stackexchange.com/questions/198185/what-is-valid-timing-of-using-current-user-can-and-related-functions
 */
add_action(
	'init',
	function() use ( $active_template, $compatibility_modules, $settings_manager ) {
		if ( SettingsManager::instance()->get_setting( 'add_capabilities' ) === false ) {
			Install::add_capabilities();
		}

		if ( ! cfw_is_enabled() ) {
			return;
		}

		// Some gateways detect whether the checkout page is using the block
		// This code makes sure they will always think it's the shortcode when our templates are active
		add_filter(
			'the_posts',
			function( $posts ) {
				$checkout_page_id = wc_get_page_id( 'checkout' );

				foreach ( $posts as $post ) {
					if ( $post instanceof WP_Post && $post->ID === $checkout_page_id ) {
						$post->post_content = '[woocommerce_checkout]';
					}
				}

				return $posts;
			},
			1000
		);

		/**
		 * Permissioned Init
		 *
		 * This hook runs on init if CheckoutWC is enabled and the license is valid or free, or the current user is an admin
		 *
		 * @since 8.2.11
		 */
		do_action( 'cfw_permissioned_init' );

		/**
		 * Ad Hoc Compatibility
		 */
		// Optimole
		add_filter(
			'optml_dont_replace_url',
			function( $old ) {
				if ( is_cfw_page() ) {
					return true;
				}

				return $old;
			},
			10
		);

		// If language is LTR set HTML tag dir="ltr" using language_attributes filter
		add_filter(
			'language_attributes',
			function( $output ) {
				if ( function_exists( 'is_rtl' ) && ! is_rtl() ) {
					return $output . ' dir="ltr"';
				}

				return $output;
			}
		);

		// Enqueue Template Assets and Load Template functions.php file
		Template::init_active_template( $active_template );

		// Init Compatibility Modules
		/** @var CompatibilityAbstract $module */
		foreach ( $compatibility_modules as $module ) {
			$module->init();
		}

		/**
		 * Setup Ajax Action Listeners
		 */
		( new AccountExistsAction() )->load();
		( new LogInAction() )->load();
		( new CompleteOrderAction() )->load();
		( new RemoveCouponAction() )->load();
		( new UpdateCheckoutAction() )->load();
		( new UpdatePaymentMethodAction() )->load();
		( new LostPasswordAction() )->load();
		( new ValidatePostcodeAction() )->load();
		( new ClientSideLogger() )->load();
		( new ValidateEmailDomainAction() )->load();

		/**
		 * Override the hide shipping costs until an address is entered to no
		 * It doesn't just hide shipping costs, it fundamentally changes the underlying packages in the WC session
		 * The setting shows a notice that it will be overridden by CheckoutWC.
		 */
		add_filter(
			'pre_option_woocommerce_shipping_cost_requires_address',
			function() {
				return 'no';
			},
			0
		);

		if ( SettingsManager::instance()->get_setting( 'registration_style' ) !== 'woocommerce' ) {
			// Override some WooCommerce Options
			add_filter(
				'pre_option_woocommerce_registration_generate_password',
				function() {
					if (
						( is_admin() && empty( $_GET['wc-ajax'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
						// processing registration form
						|| isset( $_POST['register'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
						|| ( did_action( 'wp' ) && ! cfw_is_checkout() ) // not on checkout when we can know we should be on checkout
					) {
						return false;
					}

					return 'yes';
				},
				0,
				1
			);

			add_filter(
				'pre_option_woocommerce_registration_generate_username',
				function() {
					if (
						( is_admin() && empty( $_GET['wc-ajax'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
						// processing registration form
						|| isset( $_POST['register'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
						|| ( did_action( 'wp' ) && ! cfw_is_checkout() ) // not on checkout when we can know we should be on checkout
					) {
						return false;
					}

					return 'yes';
				},
				0,
				1
			);
		}

		if ( $settings_manager->get_setting( 'enable_order_notes' ) === 'yes' ) {
			add_filter( 'woocommerce_enable_order_notes_field', '__return_true' );
		}

		add_action(
			'cfw_output_fieldset',
			function( $fieldset ) {
				cfw_output_fieldset( $fieldset ?? [] );
			},
			10,
			1
		);

		/**
		 * Load Frontend Handlers
		 */
		add_action(
			'wp',
			function() {
				if ( is_cfw_page() ) {
					AddressFieldsAugmenter::instance()->init();
				}

				if ( cfw_is_phone_fields_enabled() ) {
					add_action(
						'woocommerce_checkout_create_order',
						[
							AddressFieldsAugmenter::instance(),
							'update_shipping_phone_on_order_create',
						],
						10
					);

					// Hook for saving shipping phone to customer meta
					add_action(
						'woocommerce_checkout_update_customer',
						[
							AddressFieldsAugmenter::instance(),
							'save_shipping_phone_to_customer',
						],
						10,
						2
					);
				}
			},
			1
		);

		add_action(
			'wp',
			function() {
				if ( is_cfw_page() ) {
					FormFieldAugmenter::instance()->add_hooks();
				}
			},
			1
		);
		add_action(
			'cfw_checkout_update_order_review',
			function() {
				FormFieldAugmenter::instance()->add_hooks();
			},
			1
		);

		add_action( 'wp', 'cfw_frontend', 1 );
	},
	1
);
