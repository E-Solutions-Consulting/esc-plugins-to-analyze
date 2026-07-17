<?php

namespace Objectiv\Plugins\Checkout\Admin\Notices;

use Objectiv\Plugins\Checkout\Managers\UpdatesManager;

class WelcomeNotice extends NoticeAbstract {
	public function maybe_add( string $id = '', string $title = '', string $message = '', array $options = array() ) {
		$updates_manager = UpdatesManager::instance();

		$id    = 'cfw_welcome';
		$title = __( 'Welcome to CheckoutWC', 'checkout-wc' );

		// If valid and not auto activated, don't show.
		if ( $updates_manager->get_field_value( 'key_status' ) === 'valid' && ! get_transient( 'cfw_auto_activated' ) ) {
			return;
		}

		if ( $updates_manager->get_field_value( 'key_status' ) === 'valid' && get_transient( 'cfw_auto_activated' ) ) {
			$message = __( 'Thank you for installing CheckoutWC! We automatically activated your license. You are all set!', 'checkout-wc' );

			delete_transient( 'cfw_auto_activated' );
		}

		if ( $updates_manager->get_field_value( 'key_status' ) !== 'valid' ) {
			$message = __( 'Thank you for installing CheckoutWC! To get started, enter your <strong>License Key</strong> below, save, and click <strong>Activate Site</strong>.', 'checkout-wc' );
		}

		parent::maybe_add( $id, $title, $message, array( 'type' => 'success' ) );
	}

	protected function should_add(): bool {
		if ( empty( $_GET['cfw_welcome'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}

		return true;
	}
}
