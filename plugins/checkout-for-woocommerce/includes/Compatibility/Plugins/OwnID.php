<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

class OwnID extends CompatibilityAbstract {
	public function is_available(): bool {
		return function_exists( 'ownid_passwordless_active' );
	}

	public function run() {
		remove_action( 'wp_footer', 'ownid_passwordless_add_script_to_account_page', 9999999 );
	}

	public function typescript_class_and_params( array $compatibility ): array {
		$login_variant = get_option( 'ownid-account-auth-button' );

		if ( '' === $login_variant ) {
			$login_variant = 'button-fingerprint';
		}

		if ( 'ownid-account-auth-button' === $login_variant ) {
			$login_variant = 'ownid-auth-button';
		}

		$compatibility[] = array(
			'class'  => 'OwnID',
			'params' => array(
				'loginVariant'        => $login_variant,
				'language'            => esc_attr( substr( get_locale(), 0, 2 ) ),
				'widgetPosition'      => esc_attr( get_option( 'ownid_widget_position' ) ),
				'infoTooltipPosition' => esc_attr( get_option( 'ownid_infoTooltip_Position' ) ),
			),
		);

		return $compatibility;
	}
}
