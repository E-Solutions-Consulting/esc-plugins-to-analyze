<?php
namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;
use Objectiv\Plugins\Checkout\Managers\SettingsManager;

class WPRocket extends CompatibilityAbstract {
	public function is_available(): bool {
		return defined( 'WP_ROCKET_VERSION' );
	}

	public function pre_init() {
		if ( ! $this->is_available() ) {
			return;
		}

		add_filter( 'cfw_admin_integrations_checkbox_fields', array( $this, 'admin_integration_settings' ) );
		add_action( SettingsManager::instance()->prefix . '_settings_saved', array( $this, 'maybe_delete_cache_empty_cart' ), 10, 1 );
		add_action( 'cfw_before_plugin_data_upgrades', array( $this, 'delete_cache_empty_cart' ) );
		add_filter( 'rocket_rucss_safelist', array( $this, 'exclude_css' ) );
		add_filter( 'rocket_delay_js_exclusions', array( $this, 'exclude_js' ) );
	}

	public function maybe_delete_cache_empty_cart( array $new_settings ) {
		if ( ! defined( 'WP_ROCKET_VERSION' ) ) {
			return;
		}

		if ( ! isset( $new_settings['enable_side_cart'] ) ) {
			return;
		}

		$this->delete_cache_empty_cart();
	}

	public function exclude_css( array $excluded_css ): array {
		$excluded_css[] = str_ireplace( home_url(), '', trailingslashit( CFW_PATH_ASSETS ) . 'css/(.*).css' );

		return $excluded_css;
	}

	public function exclude_js( array $excluded_js ): array {
		if ( SettingsManager::instance()->get_setting( 'enable_wp_rocket_delay_js_compatibility_mode' ) !== 'yes' ) {
			return $excluded_js;
		}

		/**
		 * /wp-includes/js/dist/private-apis.min.js
		 * /wp-includes/js/dist/priority-queue.min.js
		 * /wp-includes/js/dist/redux-routine.min.js
		 * /wp-includes/js/dist/vendor/react.min.js
		 * /wp-includes/js/dist/i18n.min.js
		 * /wp-includes/js/dist/hooks.min.js
		 * /wp-includes/js/dist/element.min.js
		 * /wp-includes/js/dist/data.min.js
		 * /wp-includes/js/dist/compose.min.js
		 * /wp-includes/js/dist/blocks.min.js
		 * /wp-includes/js/dist/vendor/react-dom.min.js
		 */
		$wp_excludes = array(
			'/wp-includes/js/dist/vendor/react.(.*).js',
			'/wp-includes/js/dist/vendor/react-dom.(.*).js',
			'/wp-includes/js/dist/private-apis.(.*).js',
			'/wp-includes/js/dist/priority-queue.(.*).js',
			'/wp-includes/js/dist/redux-routine.(.*).js',
			'/wp-includes/js/dist/i18n.(.*).js',
			'/wp-includes/js/dist/hooks.(.*).js',
			'/wp-includes/js/dist/element.(.*).js',
			'/wp-includes/js/dist/data.(.*).js',
			'/wp-includes/js/dist/compose.(.*).js',
			'/wp-includes/js/dist/blocks.(.*).js',
			'/wp-includes/js/dist/is-shallow-equal.(.*).js',
			'\/jquery(-migrate)?-?([0-9.]+)?(.min|.slim|.slim.min)?.js(\?(.*))?( |\'|"|>)',
		);

		foreach ( $wp_excludes as $wp_exclude ) {
			$excluded_js[] = $wp_exclude;
		}

		$excluded_js[] = str_ireplace( home_url(), '', trailingslashit( CFW_PATH_ASSETS ) . 'js/(.*).js' );

		return $excluded_js;
	}

	/** Copied from wp-rocket/inc/ThirdParty/Plugins/Ecommerce/WooCommerceSubscriber.php */
	public function delete_cache_empty_cart() {
		if ( ! defined( 'WP_ROCKET_VERSION' ) ) {
			return;
		}

		$langs = get_rocket_i18n_code();

		if ( $langs ) {
			foreach ( $langs as $lang ) {
				delete_transient( 'rocket_get_refreshed_fragments_cache_' . $lang );
			}
		}

		delete_transient( 'rocket_get_refreshed_fragments_cache' );
	}

	/**
	 * Add the admin settings
	 *
	 * @param array $integrations The integration settings.
	 *
	 * @return array
	 */
	public function admin_integration_settings( array $integrations ): array {
		if ( ! defined( 'WP_ROCKET_VERSION' ) ) {
			return $integrations;
		}

		$integrations[] = array(
			'name'          => 'enable_wp_rocket_delay_js_compatibility_mode',
			'label'         => __( 'Enable WP Rocket Delay JS Execution compatibility mode', 'checkout-wc' ),
			'description'   => __( 'By default, we exclude our scripts and script dependencies to prevent compatibility problems. Uncheck to allow CheckoutWC scripts to be delayed for maximum performance.', 'checkout-wc' ),
			'initial_value' => SettingsManager::instance()->get_setting( 'enable_wp_rocket_delay_js_compatibility_mode' ) === 'yes',
		);

		return $integrations;
	}
}
