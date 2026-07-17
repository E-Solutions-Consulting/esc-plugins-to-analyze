<?php

namespace Objectiv\Plugins\Checkout\Admin\Notices;

use Objectiv\Plugins\Checkout\Managers\SettingsManager;
use Objectiv\Plugins\Checkout\Managers\UpdatesManager;

class TemplateDisabledNotice extends NoticeAbstract {
	protected function should_add(): bool {
		$enabled     = SettingsManager::instance()->get_setting( 'enable' ) === 'yes';
		$key_status  = UpdatesManager::instance()->get_field_value( 'key_status' );
		$license_key = UpdatesManager::instance()->get_field_value( 'license_key' );

		if ( $enabled || empty( $license_key ) || 'valid' !== $key_status ) {
			return false;
		}

		return true;
	}
}
