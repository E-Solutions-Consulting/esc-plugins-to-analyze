<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Gateways;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

class Mollie extends CompatibilityAbstract {
	public function is_available(): bool {
		return defined( 'M4W_FILE' );
	}

	public function typescript_class_and_params( array $compatibility ): array {
		$compatibility[] = array(
			'class'  => 'Mollie',
			'params' => array(),
		);

		return $compatibility;
	}
}
