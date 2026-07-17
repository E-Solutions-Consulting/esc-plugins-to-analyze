<?php

namespace Objectiv\Plugins\Checkout\Admin\Notices;

class TurnstileFeatureNotice extends NoticeAbstract {
	public function __construct() {
		parent::__construct();

		// Listen for the 10.2.0 upgrade action to set the transient
		add_action( 'cfw_updated_to_1020', array( $this, 'set_notice_transient' ) );
	}

	public function set_notice_transient() {
		// Set a transient that expires in 30 days to trigger the notice
		set_transient( 'cfw_turnstile_feature_notice', true, 30 * DAY_IN_SECONDS );
	}


	protected function should_add(): bool {
		// Only show if the transient exists (user upgraded to 10.2.0)
		if ( ! get_transient( 'cfw_turnstile_feature_notice' ) ) {
			return false;
		}

		// Don't show on non-admin pages
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		// Only show on CheckoutWC admin pages
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only used for display logic, not processing form data
		$current_page = sanitize_text_field( wp_unslash( $_GET['page'] ?? '' ) );
		if ( strpos( $current_page, 'cfw-settings' ) === false ) {
			return false;
		}

		delete_transient( 'cfw_turnstile_feature_notice' );

		return true;
	}
}
