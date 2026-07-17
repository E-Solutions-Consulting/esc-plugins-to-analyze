<?php

namespace Objectiv\Plugins\Checkout\Model;

class DetectedPaymentGateway {
	public $title;
	public $id;
	public $supported;
	public $recommendation;
	public $substitute;
	public $show_notice;

	public function __construct( string $title, string $supported, ?string $recommendation = null, ?AlternativePlugin $substitute = null ) {
		$this->title          = $title;
		$this->id             = sanitize_key( $title );
		$this->supported      = $supported;
		$this->recommendation = null === $recommendation && GatewaySupport::FULLY_SUPPORTED !== $supported ? 'Gateway does not provide Express Checkout.' : $recommendation;
		$this->substitute     = $substitute;
		$this->show_notice    = null !== $substitute && GatewaySupport::FULLY_SUPPORTED !== $this->supported;
	}
}
