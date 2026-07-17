<?php

namespace Objectiv\Plugins\Checkout\Admin\Notices;

use Objectiv\Plugins\Checkout\Features\FeaturesAbstract;
use Objectiv\Plugins\Checkout\Managers\SettingsManager;

class AcrDisabledNotice extends NoticeAbstract {
	protected $feature;

	public function set_feature( FeaturesAbstract $feature ) {
		$this->feature = $feature;
	}

	protected function should_add(): bool {
		if ( SettingsManager::instance()->get_setting( 'enable_acr' ) !== 'yes' ) {
			return false;
		}

		if ( SettingsManager::instance()->get_setting( 'acr_simulate_only' ) === 'yes' ) {
			return false;
		}

		if ( count( $this->feature->get_emails() ) > 0 ) {
			return false;
		}

		return true;
	}
}
