<?php

namespace Objectiv\Plugins\Checkout\Admin\Pages\Traits;

use Objectiv\Plugins\Checkout\Admin\TabNavigation;

trait TabbedAdminPageTrait {
	private $tabbed_navigation;

	public function get_tabbed_navigation(): TabNavigation {
		return $this->tabbed_navigation;
	}

	public function set_tabbed_navigation( TabNavigation $tabbed_navigation ) {
		$this->tabbed_navigation = $tabbed_navigation;
	}
}
