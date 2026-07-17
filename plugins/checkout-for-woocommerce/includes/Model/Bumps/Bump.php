<?php

namespace Objectiv\Plugins\Checkout\Model\Bumps;

class Bump extends BumpAbstract {
	public function __construct( int $id = null ) {
		parent::__construct();

		if ( empty( $id ) ) {
			return;
		}

		$post = get_post( $id );
		$this->load( $post );
	}
}
