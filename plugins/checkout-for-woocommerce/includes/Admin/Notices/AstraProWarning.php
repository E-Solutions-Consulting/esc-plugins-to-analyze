<?php

namespace Objectiv\Plugins\Checkout\Admin\Notices;

class AstraProWarning extends NoticeAbstract {
	protected function should_add(): bool {
		return defined( 'ASTRA_EXT_VER' );
	}
}
