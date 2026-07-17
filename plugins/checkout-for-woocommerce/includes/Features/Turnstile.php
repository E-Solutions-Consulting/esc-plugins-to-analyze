<?php

namespace Objectiv\Plugins\Checkout\Features;

use Objectiv\Plugins\Checkout\Interfaces\SettingsGetterInterface;
use Objectiv\Plugins\Checkout\Managers\AssetManager;

class Turnstile extends FeaturesAbstract {

	private $verify_url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';


	protected function run_if_cfw_is_enabled() {
		// Check for plugin conflicts
		if ( self::has_conflict() ) {
			return;
		}

		// Add template hooks for widget display
		$this->setup_template_hooks();

		// Add validation hooks
		$this->setup_validation_hooks();

		// Add asset loading
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue_login_assets' ) );

		// My Account hooks
		add_action( 'woocommerce_login_form', array( $this, 'output_login_widget' ) );
		add_action( 'woocommerce_register_form', array( $this, 'output_register_widget' ) );
		add_action( 'cfw_before_order_pay_submit', array( $this, 'output_order_pay_widget' ) );

		// WordPress login page hooks
		add_action( 'login_form', array( $this, 'output_wp_login_widget' ) );
	}

	private function setup_template_hooks() {
		if ( $this->settings_getter->get_setting( 'turnstile_checkout_enabled' ) !== 'yes' ) {
			return;
		}

		$position = $this->settings_getter->get_setting( 'turnstile_position' );

		if ( empty( $position ) ) {
			$position = 'before_place_order';
		}

		switch ( $position ) {
			case 'before_payment_methods':
				add_action( 'cfw_checkout_before_payment_methods', array( $this, 'output_checkout_widget' ), 10 );
				break;
			case 'after_payment_methods':
				add_action( 'cfw_checkout_payment_method_tab', array( $this, 'output_checkout_widget' ), 12 );
				break;
			case 'before_place_order':
				add_action( 'cfw_checkout_before_payment_method_tab_nav', array( $this, 'output_checkout_widget' ), 10 );
				break;
		}
	}

	private function setup_validation_hooks() {
		// Checkout validation
		if ( $this->settings_getter->get_setting( 'turnstile_checkout_enabled' ) === 'yes' ) {
			add_action( 'woocommerce_checkout_process', array( $this, 'validate_checkout' ) );
		}

		// Order pay validation
		if ( $this->settings_getter->get_setting( 'turnstile_order_pay_enabled' ) === 'yes' ) {
			add_action( 'woocommerce_before_pay_action', array( $this, 'validate_order_pay' ) );
		}

		// Login validation
		if ( $this->settings_getter->get_setting( 'turnstile_login_enabled' ) === 'yes' ) {
			add_filter( 'authenticate', array( $this, 'validate_login' ), 30, 3 );
		}

		// Registration validation
		if ( $this->settings_getter->get_setting( 'turnstile_register_enabled' ) === 'yes' ) {
			add_action( 'woocommerce_register_post', array( $this, 'validate_registration' ), 10, 3 );
		}
	}

	public function output_checkout_widget() {
		if ( ! $this->should_show_widget() ) {
			return;
		}

		$this->render_widget();
	}

	public function output_login_widget() {
		if ( $this->settings_getter->get_setting( 'turnstile_login_enabled' ) !== 'yes' ||
			! $this->should_show_widget() ) {
			return;
		}

		$this->render_widget();
	}

	public function output_register_widget() {
		if ( $this->settings_getter->get_setting( 'turnstile_register_enabled' ) !== 'yes' ||
			! $this->should_show_widget() ) {
			return;
		}

		$this->render_widget();
	}

	public function output_order_pay_widget() {
		if ( $this->settings_getter->get_setting( 'turnstile_order_pay_enabled' ) !== 'yes' ||
			! $this->should_show_widget() ) {
			return;
		}

		$this->render_widget();
	}

	public function output_wp_login_widget() {
		if ( $this->settings_getter->get_setting( 'turnstile_login_enabled' ) !== 'yes' ||
			! $this->should_show_widget() ) {
			return;
		}

		?>
		<div class="cf-turnstile-scalable-container cfw-module">
			<div class="cf-turnstile"
				data-sitekey="<?php echo esc_attr( $this->settings_getter->get_setting( 'turnstile_site_key' ) ); ?>"
				data-theme="<?php echo esc_attr( $this->settings_getter->get_setting( 'turnstile_theme' ) ?: 'light' ); ?>"
				data-size="normal"
				style="transform-origin: 0 0;">
			</div>
			<div class="cfw-turnstile-error" style="display: none;">
				<?php esc_html_e( 'Please complete the verification challenge.', 'checkout-wc' ); ?>
			</div>
		</div>
		<script>
			function scaleTurnstileToFit() {
				const container = document.querySelector('.cf-turnstile-scalable-container');
				const turnstile = document.querySelector('.cf-turnstile-scalable-container .cf-turnstile');

				if (!container || !turnstile) return;

				const containerWidth = container.offsetWidth;

				if (containerWidth < 300) {
					const scale = containerWidth / 300;
					turnstile.style.transform = 'scale(' + scale + ')';
				} else {
					turnstile.style.transform = 'none';
				}
			}

			window.addEventListener('load', scaleTurnstileToFit);
			window.addEventListener('resize', scaleTurnstileToFit);
		</script>
		<?php
	}

	private function render_widget( $override_size = null ) {
		$site_key = $this->settings_getter->get_setting( 'turnstile_site_key' );
		if ( empty( $site_key ) ) {
			return;
		}

		$theme = $this->settings_getter->get_setting( 'turnstile_theme' );
		if ( empty( $theme ) ) {
			$theme = 'light';
		}

		if ( $override_size ) {
			$size = $override_size;
		} else {
			$size = $this->settings_getter->get_setting( 'turnstile_size' );
			if ( empty( $size ) ) {
				$size = 'normal';
			}
		}

		?>
		<div class="cfw-turnstile-container cfw-module">
			<div class="cf-turnstile"
				data-sitekey="<?php echo esc_attr( $site_key ); ?>"
				data-theme="<?php echo esc_attr( $theme ); ?>"
				data-size="<?php echo esc_attr( $size ); ?>">
			</div>
			<div class="cfw-turnstile-error" style="display: none;">
				<?php esc_html_e( 'Please complete the verification challenge.', 'checkout-wc' ); ?>
			</div>
		</div>
		<?php
	}

	public function enqueue_assets() {
		if ( ! is_cfw_page() && ! is_account_page() ) {
			return;
		}

		// Enqueue Turnstile API
		wp_enqueue_script(
			'cloudflare-turnstile',
			'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit',
			array(),
			null,
			true
		);

		// Register and enqueue CheckoutWC Turnstile script
		cfw_register_scripts( array( 'turnstile' ) );
		wp_enqueue_script( 'cfw-turnstile' );

		// Enqueue CheckoutWC Turnstile styles
		AssetManager::enqueue_style( 'turnstile-styles' );
	}

	public function enqueue_login_assets() {
		if ( $this->settings_getter->get_setting( 'turnstile_login_enabled' ) !== 'yes' ||
			! $this->should_show_widget() ) {
			return;
		}

		// Enqueue Turnstile API
		wp_enqueue_script(
			'cloudflare-turnstile',
			'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit',
			array(),
			null,
			true
		);

		// Register and enqueue CheckoutWC Turnstile script
		cfw_register_scripts( array( 'turnstile' ) );
		wp_enqueue_script( 'cfw-turnstile' );

		// Enqueue CheckoutWC Turnstile styles
		AssetManager::enqueue_style( 'turnstile' );
	}

	private function should_show_widget(): bool {
		if ( $this->settings_getter->get_setting( 'turnstile_enabled' ) !== 'yes' ) {
			return false;
		}

		if ( empty( $this->settings_getter->get_setting( 'turnstile_site_key' ) ) ||
			empty( $this->settings_getter->get_setting( 'turnstile_secret_key' ) ) ) {
			return false;
		}

		// Skip for logged-in users if guest-only is enabled
		if ( $this->settings_getter->get_setting( 'turnstile_guest_only' ) === 'yes' && is_user_logged_in() ) {
			return false;
		}

		return true;
	}

	public function should_validate(): bool {
		return $this->should_show_widget();
	}

	public function validate_checkout() {
		if ( ! $this->should_validate() ) {
			cfw_debug_log( 'Turnstile: validate_checkout() skipped - should_validate() returned false' );
			return;
		}

		$token = sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ?? '' ) );
		if ( empty( $token ) ) {
			cfw_debug_log( 'Turnstile: validate_checkout() - no token received' );
		}

		$result = $this->verify_token( $token );

		if ( ! $result['success'] ) {
			cfw_debug_log( 'Turnstile: validate_checkout() failed - ' . $result['message'] );
			wc_add_notice( $result['message'], 'error' );

			return;
		}

		cfw_debug_log( 'Turnstile: validate_checkout() succeeded' );
	}

	public function validate_order_pay( $order_id ) {
		if ( ! $this->should_validate() ) {
			cfw_debug_log( 'Turnstile: validate_order_pay() skipped - should_validate() returned false' );
			return;
		}

		$token = sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ?? '' ) );
		if ( empty( $token ) ) {
			cfw_debug_log( 'Turnstile: validate_order_pay() - no token received' );
		}

		$result = $this->verify_token( $token );

		if ( ! $result['success'] ) {
			cfw_debug_log( 'Turnstile: validate_order_pay() failed - ' . $result['message'] );
			wc_add_notice( $result['message'], 'error' );
			wp_redirect( wc_get_checkout_url() );
			exit;
		}

		cfw_debug_log( 'Turnstile: validate_order_pay() succeeded' );
	}

	public function validate_login( $user, $username, $password ) {
		if ( ! $this->should_validate() || is_wp_error( $user ) ) {
			return $user;
		}

		$token = sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ?? '' ) );

		if ( empty( $token ) ) {
			cfw_debug_log( 'Turnstile: validate_login() - no token received for user: ' . $username );
		}

		$result = $this->verify_token( $token );

		if ( ! $result['success'] ) {
			cfw_debug_log( 'Turnstile: validate_login() failed for user: ' . $username . ' - ' . $result['message'] );
			return new \WP_Error( 'turnstile_failed', $result['message'] );
		}

		cfw_debug_log( 'Turnstile: validate_login() succeeded for user: ' . $username );

		return $user;
	}

	public function validate_registration( $username, $email, $validation_errors ) {
		// Skip registration validation during checkout - checkout validation handles it
		if ( is_checkout() ) {
			cfw_debug_log( 'Turnstile: validate_registration() skipped during checkout for user: ' . $username );
			return;
		}

		if ( ! $this->should_validate() ) {
			cfw_debug_log( 'Turnstile: validate_registration() skipped - should_validate() returned false' );
			return;
		}

		$token = sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ?? '' ) );
		if ( empty( $token ) ) {
			cfw_debug_log( 'Turnstile: validate_registration() - no token received for user: ' . $username );
		}

		$result = $this->verify_token( $token );

		if ( ! $result['success'] ) {
			cfw_debug_log( 'Turnstile: validate_registration() failed for user: ' . $username . ' - ' . $result['message'] );
			$validation_errors->add( 'turnstile_failed', $result['message'] );
			return;
		}

		cfw_debug_log( 'Turnstile: validate_registration() succeeded for user: ' . $username );
	}

	private function verify_token( string $token ): array {
		if ( empty( $token ) ) {
			return array(
				'success' => false,
				'message' => __( 'Please complete the verification challenge.', 'checkout-wc' ),
			);
		}

		$response = wp_remote_post(
			$this->verify_url,
			array(
				'body'    => array(
					'secret'   => $this->settings_getter->get_setting( 'turnstile_secret_key' ),
					'response' => $token,
					'remoteip' => $this->get_client_ip(),
				),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			cfw_debug_log( 'Turnstile API request failed: ' . $response->get_error_message() );
			return array(
				'success' => false,
				'message' => __( 'Verification service unavailable. Please try again.', 'checkout-wc' ),
			);
		}

		$body   = wp_remote_retrieve_body( $response );
		$result = json_decode( $body, true );

		// Check for JSON decode errors
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			cfw_debug_log( 'Turnstile API returned invalid JSON: ' . $body );
			return array(
				'success' => false,
				'message' => __( 'Verification failed. Please try again.', 'checkout-wc' ),
			);
		}

		// Log the API response for debugging (but only the essential parts)
		$success     = $result['success'] ?? false;
		$error_codes = $result['error-codes'] ?? array();
		cfw_debug_log( 'Turnstile API response - success: ' . ( $success ? 'true' : 'false' ) . ', error_codes: ' . implode( ', ', $error_codes ) );

		// Explicitly check for boolean true
		if ( isset( $result['success'] ) && $result['success'] === true ) {
			return array(
				'success' => true,
				'message' => 'Verification successful',
			);
		}

		return array(
			'success' => false,
			'message' => __( 'Verification failed. Please try again.', 'checkout-wc' ),
			'errors'  => $result['error-codes'] ?? array(),
		);
	}

	private function get_client_ip(): string {
		$headers = array( 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR' );

		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip = sanitize_text_field( $_SERVER[ $header ] );

				// Handle comma-separated IPs (e.g., from X-Forwarded-For)
				if ( strpos( $ip, ',' ) !== false ) {
					$ip_list = explode( ',', $ip );
					$ip      = trim( $ip_list[0] ); // Use the first IP
				}

				// Validate IP format (allow private ranges for development/proxy environments)
				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE ) ) {
					return $ip;
				}
			}
		}

		return sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );
	}

	/**
	 * Get the conflict notice message
	 *
	 * @return string
	 */
	public static function get_conflict_notice(): string {
		if ( function_exists( 'cfturnstile_settings_redirect' ) ) {
			return __( 'We have detected that Simple Cloudflare Turnstile is active. To enable CheckoutWC Turnstile functionality, please deactivate Simple Cloudflare Turnstile.', 'checkout-wc' );
		}

		return '';
	}

	/**
	 * Check if there's a plugin conflict
	 *
	 * @return bool
	 */
	public static function has_conflict(): bool {
		return function_exists( 'cfturnstile_settings_redirect' );
	}
}
