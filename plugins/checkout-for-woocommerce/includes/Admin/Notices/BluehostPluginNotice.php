<?php

namespace Objectiv\Plugins\Checkout\Admin\Notices;

class BluehostPluginNotice extends NoticeAbstract {
	protected function should_add(): bool {
		return defined( 'BLUEHOST_PLUGIN_VERSION' );
	}
}
