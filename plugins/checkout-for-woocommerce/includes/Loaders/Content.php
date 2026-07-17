<?php
namespace Objectiv\Plugins\Checkout\Loaders;

/**
 * Class Content
 *
 * Loads pages into normal WP content
 *
 * @link checkoutwc.com
 * @since 3.6.0
 * @package Objectiv\Plugins\Checkout\Core
 */
class Content extends LoaderAbstract {
	/**
	 *
	 * @since 3.6.0
	 */
	public static function checkout() {
		/**
		 * Filters whether to load checkout template
		 *
		 * @since 2.0.0
		 *
		 * @param bool $load True load, false don't load
		 */
		if ( ! apply_filters( 'cfw_load_checkout_template', cfw_is_checkout() ) ) {
			return;
		}

		$global_template_parameters = self::init_checkout();

		add_action( 'wp_head', array( 'Objectiv\Plugins\Checkout\Loaders\Content', 'output_custom_header_scripts' ), 20, 4 );
		add_action( 'wp_head', array( 'Objectiv\Plugins\Checkout\Loaders\Content', 'custom_styles' ), 5, 5 );
		add_action( 'wp_footer', array( 'Objectiv\Plugins\Checkout\Loaders\Content', 'output_custom_footer_scripts' ) );

		add_shortcode(
			'woocommerce_checkout',
			function () use ( $global_template_parameters ) {
				// Setup checkout
				ob_start();

				echo '<div class="cfw-grid">';

				// Output the contents of the <body></body> section
				self::display( $global_template_parameters, 'content.php' );

				echo '</div>';

				return ob_get_clean();
			}
		);
	}

	public static function order_pay() {
		/**
		 * Filters whether to load order pay template
		 *
		 * @since 2.0.0
		 *
		 * @param bool $load True load, false don't load
		 */
		if ( ! apply_filters( 'cfw_load_order_pay_template', is_checkout_pay_page() ) ) {
			return;
		}

		$global_template_parameters = self::init_order_pay();

		if ( ! isset( $global_template_parameters['order'] ) ) {
			return;
		}

		add_action( 'wp_head', array( 'Objectiv\Plugins\Checkout\Loaders\Content', 'output_custom_header_scripts' ), 20, 4 );
		add_action( 'wp_head', array( 'Objectiv\Plugins\Checkout\Loaders\Content', 'custom_styles' ), 5, 5 );
		add_action( 'wp_footer', array( 'Objectiv\Plugins\Checkout\Loaders\Content', 'output_custom_footer_scripts' ) );

		add_shortcode(
			'woocommerce_checkout',
			function () use ( $global_template_parameters ) {
				// Setup checkout
				ob_start();

				echo '<div class="cfw-grid">';

				// Output the contents of the <body></body> section
				self::display( $global_template_parameters, 'order-pay.php' );

				echo '</div>';

				return ob_get_clean();
			}
		);
	}

	/**
	 * @throws \WC_Data_Exception Exception from thank you page init.
	 */
	public static function order_received() {
		/**
		 * Filters whether to load order received template
		 *
		 * @since 2.0.0
		 *
		 * @param bool $load True load, false don't load
		 */
		if ( ! apply_filters( 'cfw_load_order_received_template', is_order_received_page() ) ) {
			return;
		}

		$global_template_parameters = self::init_thank_you();

		add_action( 'wp_head', array( 'Objectiv\Plugins\Checkout\Loaders\Content', 'output_custom_header_scripts' ), 20, 4 );
		add_action( 'wp_head', array( 'Objectiv\Plugins\Checkout\Loaders\Content', 'custom_styles' ), 5, 5 );
		add_action( 'wp_footer', array( 'Objectiv\Plugins\Checkout\Loaders\Content', 'output_custom_footer_scripts' ) );

		add_shortcode(
			'woocommerce_checkout',
			function () use ( $global_template_parameters ) {
				// Setup checkout
				ob_start();

				echo '<div class="cfw-grid">';

				// Output the contents of the <body></body> section
				self::display( $global_template_parameters, 'thank-you.php' );

				echo '</div>';

				return ob_get_clean();
			}
		);
	}

	/**
	 * @deprecated 5.0.0
	 */
	public static function wp_head() {
		add_action( 'wp_head', array( 'Objectiv\Plugins\Checkout\Loaders\Content', 'output_custom_header_scripts' ), 20, 4 );
		add_action( 'wp_head', array( 'Objectiv\Plugins\Checkout\Loaders\Content', 'custom_styles' ), 5, 5 );
	}

	/**
	 * @deprecated 5.0.0
	 */
	public static function wp_footer() {
		add_action( 'wp_footer', array( 'Objectiv\Plugins\Checkout\Loaders\Content', 'output_custom_footer_scripts' ) );
	}
}
