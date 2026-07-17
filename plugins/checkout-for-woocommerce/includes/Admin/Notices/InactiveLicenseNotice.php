<?php

namespace Objectiv\Plugins\Checkout\Admin\Notices;

use Objectiv\Plugins\Checkout\Managers\UpdatesManager;

class InactiveLicenseNotice extends NoticeAbstract {
	public function add( string $url ) {
		$message = sprintf(
			/* translators: %1$s: URL to activation page, %2$s: "Start Here" link text */
			__( 'Your license key is not active for this site. Please visit <a href="%1$s">%2$s</a> to activate your license and restore functionality.', 'checkout-wc' ),
			$url,
			__( 'Start Here', 'checkout-wc' )
		);

		parent::maybe_add(
			'cfw_inactive_license',
			'CheckoutWC License Deactivated',
			$message,
			array(
				'type'        => 'error',
				'dismissible' => false,
			)
		);
	}

	protected function should_add(): bool {
		$key_status  = UpdatesManager::instance()->get_field_value( 'key_status' );
		$license_key = UpdatesManager::instance()->get_field_value( 'license_key' );

		if ( empty( $license_key ) || 'site_inactive' !== $key_status ) {
			return false;
		}

		return true;
	}
}
