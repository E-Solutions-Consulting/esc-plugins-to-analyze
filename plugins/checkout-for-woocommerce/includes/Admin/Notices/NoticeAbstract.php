<?php

namespace Objectiv\Plugins\Checkout\Admin\Notices;

use Objectiv\Plugins\Checkout\Managers\NoticesManager;

abstract class NoticeAbstract {
	public function __construct() {}

	public function maybe_add( string $id, string $title, string $message, array $options = [] ) {
		if ( ! $this->should_add() ) {
			return;
		}

		$options['image'] = file_get_contents( CFW_PATH . '/assets/images/cfw.svg' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$options['mode']  = $options['mode'] ?? 'regular';
		$options['scope'] = $options['scope'] ?? 'user';

		NoticesManager::instance()->add( $id, $title, $message, $options );
	}

	protected function should_add(): bool {
		return true;
	}
}
