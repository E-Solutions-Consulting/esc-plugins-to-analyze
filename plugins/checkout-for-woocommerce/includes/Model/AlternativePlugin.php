<?php

namespace Objectiv\Plugins\Checkout\Model;

class AlternativePlugin {
	public $title;
	public $slug;
	public $can_be_installed;

	public function __construct( string $slug, string $title, $can_be_installed = true ) {
		$this->slug             = $slug;
		$this->title            = $title;
		$this->can_be_installed = $can_be_installed;
	}
}
