<?php

namespace Objectiv\Plugins\Checkout\Admin\Notices;

class DiviWarning extends NoticeAbstract {
	protected function should_add(): bool {
		return function_exists( 'et_setup_theme' );
	}
}
