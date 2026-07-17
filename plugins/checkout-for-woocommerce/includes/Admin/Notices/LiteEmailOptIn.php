<?php

namespace Objectiv\Plugins\Checkout\Admin\Notices;

use Objectiv\Plugins\Checkout\Managers\PlanManager;
use Objectiv\Plugins\Checkout\Managers\SettingsManager;

class LiteEmailOptIn extends NoticeAbstract {
	private const OPTION_NAME = 'lite_email_opt_in_status';

	public function __construct() {
		parent::__construct();
		add_action( 'admin_footer', array( $this, 'add_inline_script' ) );
		add_action( 'wp_ajax_cfw_lite_email_opt_in', array( $this, 'handle_ajax_opt_in' ) );
		add_action( 'wp_ajax_cfw_lite_email_opt_in_dismiss', array( $this, 'handle_ajax_dismiss' ) );
	}

	public function add(): void {
		$current_user = wp_get_current_user();
		$user_email = $current_user->user_email ? $current_user->user_email : __( 'your email', 'checkout-wc' );

		$notice_type = $this->get_notice_type();
		$content = $this->get_notice_content( $notice_type, $user_email );

		parent::maybe_add(
			'cfw_lite_email_opt_in',
			$content['title'],
			$content['message'],
			array(
				'type'        => 'info',
				'scope'       => 'global',
				'dismissible' => true,
			)
		);
	}

	protected function should_add(): bool {
		global $pagenow;

		// Don't add if user has a premium plan
		if ( PlanManager::has_premium_plan_or_higher() ) {
			return false;
		}

		// Don't add if user doesn't have required capability
		if ( ! current_user_can( $this->get_required_capability() ) ) {
			return false;
		}

		// Don't add if user already subscribed
		if ( $this->is_subscribed() ) {
			return false;
		}

		// Don't add if notice was dismissed (unless we have enough orders to reach the orders milestone threshold)
		if ( $this->is_dismissed() && ( ! $this->has_reached_orders_milestone_threshold() || $this->is_dismissed( 'orders_milestone' ) ) ) {
			return false;
		}

		// Don't add if user is on the start here page
		if ( 'admin.php' === $pagenow && isset( $_GET['page'] ) && 'cfw-settings' === $_GET['page'] ) {
			return false;
		}

		// Add the notice
		return true;
	}

	protected function get_required_capability(): string {
		return 'manage_options';
	}

	private function get_notice_type(): string {
		// If notice was dismissed and we have reached the orders milestone threshold, show orders milestone version
		if ( $this->is_dismissed() && $this->has_reached_orders_milestone_threshold() ) {
			return 'orders_milestone';
		}

		// Default to default notice
		return 'default';
	}

	private function get_notice_button_html( string $button_label ): string {
		return '<a href="#" id="cfw-lite-email-opt-in-btn" class="button button-primary" style="font-size: 1.1em;" data-nonce="' . wp_create_nonce( 'cfw_lite_email_opt_in' ) . '" data-original-label="' . esc_attr( $button_label ) . '"><strong>' . $button_label . '</strong></a>';
	}

	private function get_notice_privacy_policy_link(): string {
		return '<a href="https://www.checkoutwc.com/privacy-policy/" target="_blank" style="color: #a0a0a0;">' . esc_html__( 'privacy policy', 'checkout-wc' ) . '</a>';
	}

	private function get_notice_footer( string $user_email ): string {
		// translators: %1$s: user email, %2$s: privacy policy link
		return '<p style="color: #a0a0a0; padding-top: 0;"><small><i>' . sprintf( esc_html__( 'By joining, %1$s will be added to our mailing list. Read our %2$s.', 'checkout-wc' ), $user_email, $this->get_notice_privacy_policy_link() ) . '</i></small></p>';
	}

	private function get_notice_content( string $notice_type, string $user_email ): array {
		if ( $notice_type === 'orders_milestone' ) {
			$content = $this->get_notice_content_orders_milestone();
			$button_label = esc_html__( 'Join Newsletter & Save 25%', 'checkout-wc' );
		} else {
			$content = $this->get_notice_content_default();
			$button_label = esc_html__( 'Join Newsletter', 'checkout-wc' );
		}

		// Add common elements (button and footer) to the message
		$content['message'] .= '<p>' . $this->get_notice_button_html( $button_label ) . '</p>';
		$content['message'] .= $this->get_notice_footer( $user_email );

		return $content;
	}

	private function get_notice_content_default(): array {
		$message = $this->get_notice_content_paragraph_wrap( esc_html__( 'Sign up to our newsletter and get proven conversion tips and checkout optimization strategies from the CheckoutWC team.', 'checkout-wc' ) );

		return array(
			'title'   => esc_html__( 'Boost Your Conversions with Proven Strategies.', 'checkout-wc' ),
			'message' => $message,
		);
	}

	private function get_notice_content_orders_milestone(): array {
		// translators: %s: Discount text
		$message = $this->get_notice_content_paragraph_wrap( esc_html__( "We're so glad that you've chosen CheckoutWC to handle your checkouts. Unlock more sales with premium features like order bumps, abandoned cart recovery, and much more.", 'checkout-wc' ) );

		return array(
			'title'   => esc_html__( 'Unlock more sales with CheckoutWC Premium', 'checkout-wc' ),
			'message' => $message,
		);
	}

	private function get_notice_content_paragraph_wrap( string $content ): string {
		return '<p style="font-size: 1.2em; margin-top: 0;">' . $content . '</p>';
	}

	private function get_notice_status(): array {
		$status = SettingsManager::instance()->get_setting( self::OPTION_NAME );
		if ( ! $status ) {
			$status = array( 'dismissed' => array(), 'subscribed' => false );
		}
		return wp_parse_args( $status, array( 'dismissed' => array(), 'subscribed' => false ) );
	}

	protected function get_subscription_error_message(): string {
		return __( 'Sorry, there was an error joining our newsletter. Please refresh the page and try again.', 'checkout-wc' );
	}

	private function get_orders_milestone_threshold(): int {
		return 5;
	}

	private function is_dismissed( string $dismissal_type = 'default' ): bool {
		$status = $this->get_notice_status();

		return isset( $status['dismissed'][ $dismissal_type ] ) && $status['dismissed'][ $dismissal_type ];
	}

	private function is_subscribed(): bool {
		$status = $this->get_notice_status();

		return $status['subscribed'];
	}

	private function mark_as_dismissed( string $dismissal_type = 'default' ): void {
		$status = $this->get_notice_status();

		$status['dismissed'][ $dismissal_type ] = true;
		SettingsManager::instance()->update_setting( self::OPTION_NAME, $status );
	}

	private function mark_as_subscribed(): void {
		$status = $this->get_notice_status();
		$status['subscribed'] = true;
		SettingsManager::instance()->update_setting( self::OPTION_NAME, $status );
	}

	private function has_reached_orders_milestone_threshold(): bool {
		// Check if WooCommerce is available
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return false;
		}

		// Get installation date
		$install_date = SettingsManager::instance()->get_setting( 'installed' );
		if ( ! $install_date ) {
			return false;
		}

		$install_timestamp = strtotime( $install_date );
		$threshold = $this->get_orders_milestone_threshold();

		// Check for cached result first
		$transient_key = '_cfw_lite_email_opt_in_reached_orders_milestone_threshold';
		$cached_result = get_transient( $transient_key );

		if ( $cached_result !== false ) {
			return $cached_result;
		}

		// Get orders up to the threshold + 1 (to check if we have at least threshold)
		$order_query_args = array(
			'limit'        => $threshold + 1,
			'return'       => 'ids',
			'date_created' => '>' . $install_timestamp,
		);

		if ( cfw_is_hpos_enabled() ) {
			$order_query_args['meta_query'] = array(
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

		$result = count( $orders ) >= $threshold;

		// Cache the result for 1 hour (3600 seconds)
		set_transient( $transient_key, $result, 3600 );

		return $result;
	}

	public function add_inline_script(): void {
		if ( ! $this->should_add() ) {
			return;
		}
		?>
		<script>
		jQuery(document).ready(function($) {
			// Handle email opt-in button click
			$(document).on("click", "#cfw-lite-email-opt-in-btn", function(e) {
				e.preventDefault();

				var $btn = $(this);
				var nonce = $btn.data("nonce");
				var originalLabel = $btn.data("original-label");

				$btn.attr("disabled", true).text("<?php echo esc_js( __( 'Please wait...', 'checkout-wc' ) ); ?>");

				$.ajax({
					url: ajaxurl,
					type: "POST",
					data: {
						action: "cfw_lite_email_opt_in",
						nonce: nonce
					},
					success: function(response) {
						if (response.success) {
							alert(response.data.message);
							// Hide the entire notice after successful subscription
							$("#pressmodo-notice-cfw_lite_email_opt_in").fadeOut(function() {
								$(this).remove(); // Ensures the notice is completely removed from the DOM, otherwise immediately reappears on cfw-settings pages
							});
						} else {
							alert(response.data.message);
							$btn.prop("disabled", false).text(originalLabel);
						}
					},
					error: function(xhr) {
						var errorMessage = "<?php echo esc_js( $this->get_subscription_error_message() ); ?>";
						try {
							var response = JSON.parse(xhr.responseText);
							if (response.data && response.data.message) {
								errorMessage = response.data.message;
							}
						} catch (e) {
							// Use default error message
						}
						alert(errorMessage);
						$btn.prop("disabled", false).text(originalLabel);
					}
				});
			});

			// Handle notice dismissal
			$(document).on("click", "#pressmodo-notice-cfw_lite_email_opt_in .notice-dismiss", function(e) {
				e.preventDefault();

				var $notice = $(this).closest('.notice');
				var dismissNonce = "<?php echo wp_create_nonce( 'cfw_lite_email_opt_in_dismiss' ); ?>";

				$.ajax({
					url: ajaxurl,
					type: "POST",
					data: {
						action: "cfw_lite_email_opt_in_dismiss",
						nonce: dismissNonce
					},
					success: function(response) {
						if (response.success) {
							$notice.fadeOut(function() {
								$(this).remove();
							});
						}
					},
					error: function() {
						// Even if AJAX fails, hide the notice for better UX
						$notice.fadeOut(function() {
							$(this).remove();
						});
					}
				});
			});
		});
		</script>
		<?php
	}

	public function handle_ajax_opt_in(): void {
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'cfw_lite_email_opt_in' ) ) {
			wp_send_json_error( array( 'message' => $this->get_subscription_error_message() ) );
		}

		if ( ! current_user_can( $this->get_required_capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'Sorry, your account has insufficient permissions to join our newsletter.', 'checkout-wc' ) ) );
		}

		// Process email subscription
		$current_user	= wp_get_current_user();
		$email			= $current_user->user_email;
		$first_name		= $current_user->first_name ?: '';
		$last_name		= $current_user->last_name ?: '';

		// Check if contact already exists using Kestrel Connect
		$check_url = 'https://connect.kestrelwp.io/checkoutwc/groundhogg/check-contact?email=' . urlencode( $email );
		$check_response = wp_remote_get( $check_url, array(
			'headers' => array(
				'Accept'		=> 'application/json',
				'Content-Type'	=> 'application/json',
			),
			'timeout' => 30,
		) );

		if ( is_wp_error( $check_response ) ) {
			wp_send_json_error( array( 'message' => $this->get_subscription_error_message() ) );
		}

		$check_body = wp_remote_retrieve_body( $check_response );
		$check_data = json_decode( $check_body, true );

		// Check for successful response (200) and contact exists
		if ( null !== $check_data &&
				wp_remote_retrieve_response_code( $check_response ) === 200 &&
				isset( $check_data['status'] ) &&
				$check_data['status'] === 'success' &&
				isset( $check_data['exists'] ) &&
				$check_data['exists'] === true ) {
			$this->mark_as_subscribed();
			wp_send_json_success( array( 'message' => __( 'You have already joined our newsletter!', 'checkout-wc' ) ) );
		}

		// Add contact using Kestrel Connect
		$add_url = 'https://connect.kestrelwp.io/checkoutwc/groundhogg/add-contact';
		$add_data = array(
			'email'			=> $email,
			'optin_status'	=> 4,
			'tags'			=> [
				95, // LiteEmailOptIn tag ID
			],
		);

		if ( ! empty( $first_name ) ) {
			$add_data['first_name'] = $first_name;
		}
		if ( ! empty( $last_name ) ) {
			$add_data['last_name'] = $last_name;
		}

		$response = wp_remote_post( $add_url, array(
			'headers' => array(
				'Accept'		=> 'application/json',
				'Content-Type'	=> 'application/json',
			),
			'body'		=> wp_json_encode( $add_data ),
			'timeout'	=> 30,
		) );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $this->get_subscription_error_message() ) );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$response_data = json_decode( $response_body, true );

		// Check for successful response (201) and success status
		if ( $response_code === 201 &&
			 isset( $response_data['status'] ) &&
			 $response_data['status'] === 'success' ) {
			$this->mark_as_subscribed();
			wp_send_json_success( array( 'message' => __( 'Thank you for joining our newsletter!', 'checkout-wc' ) ) );
		}

		// Handle fallback error response
		$error_message = $this->get_subscription_error_message();
		if ( isset( $response_data['message'] ) ) {
			$error_message = $response_data['message'];
		}

		wp_send_json_error( array( 'message' => $error_message ) );
	}

	public function handle_ajax_dismiss(): void {
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'cfw_lite_email_opt_in_dismiss' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'checkout-wc' ) ) );
		}

		if ( ! current_user_can( $this->get_required_capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'checkout-wc' ) ) );
		}

		// Determine dismissal type based on the current notice type being shown
		$dismissal_type = $this->get_notice_type();

		$this->mark_as_dismissed( $dismissal_type );

		wp_send_json_success( array( 'message' => __( 'Notice dismissed.', 'checkout-wc' ) ) );
	}
}
