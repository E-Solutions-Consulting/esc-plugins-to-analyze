<?php

namespace Objectiv\Plugins\Checkout\Admin\Notices;

class AvadaWarning extends NoticeAbstract {
	protected function should_add(): bool {
		return defined( 'AVADA_VERSION' );
	}
}
