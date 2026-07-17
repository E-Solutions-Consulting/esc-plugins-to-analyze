<?php
namespace Objectiv\Plugins\Checkout\Model;

class Rule {
	public $fieldKey;
	public $subFields;

	public function __construct( $fieldKey, $subFields ) {
		$this->fieldKey  = $fieldKey;
		$this->subFields = $subFields;
	}
}
