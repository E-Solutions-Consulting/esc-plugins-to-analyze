<?php
namespace Objectiv\Plugins\Checkout\Managers;

use Objectiv\Plugins\Checkout\SingletonAbstract;
use CheckoutWC\Pressmodo\AdminNotices\Notices;

class NoticesManager extends SingletonAbstract {
	private $notices;

	public function init() {
		$this->set_notices_object( new Notices() );

		add_action( 'admin_notices', array( $this->notices, 'boot' ), -100 );
	}

	public function add( string $id, string $title, string $message, array $options = array() ) {
		$this->get_notices_object()->add( $id, $title, $message, $options );
	}

	public function get_notices_object(): Notices {
		return $this->notices;
	}

	public function set_notices_object( Notices $notices ) {
		$this->notices = $notices;
	}

	public function get_deferred_notices(): array {
		$all      = $this->notices->get_all();
		$deferred = array();

		foreach ( $all as $notice ) {
			if ( 'deferred' === $notice->get_option( 'mode' ) ) {
				$deferred[] = $notice;
			}
		}

		return $deferred;
	}
}
