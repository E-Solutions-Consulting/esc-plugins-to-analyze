<?php

namespace Objectiv\Plugins\Checkout\Admin\Notices;

class PortoWarning extends NoticeAbstract {
	protected function should_add(): bool {
		return function_exists( 'porto_setup' );
	}
}
