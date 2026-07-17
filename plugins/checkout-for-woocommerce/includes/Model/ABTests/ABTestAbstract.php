<?php

namespace Objectiv\Plugins\Checkout\Model\ABTests;

use Objectiv\Plugins\Checkout\Interfaces\ABTestInterface;
use WP_Post;

class ABTestAbstract implements ABTestInterface {
	protected $id;
	protected $type = '';
	protected $status = '';
	protected $label = '';
	protected $description = '';

	protected $supports = array();
	protected $date_from = '';
	protected $date_to = '';

	public function __construct( ?int $id = null ) {
		$this->id = $id;
	}

	public function load( ?WP_Post $post = null ): void {
		if ( ! $post ) {
			return;
		}

		$this->id        = $post->ID;
		$this->type      = get_post_meta( $this->id, 'cfw_ab_test_type', true );
		$this->status    = get_post_meta( $this->id, 'cfw_ab_test_status', true );
		$this->date_from = get_post_meta( $this->id, 'cfw_ab_test_date_from', true );
		$this->date_to   = get_post_meta( $this->id, 'cfw_ab_test_date_to', true );
	}

	public function get_id(): int {
		return $this->id ?? 0;
	}

	public function get_type(): string {
		return $this->type;
	}

	public function get_label(): string {
		return $this->label;
	}

	public function get_description(): string {
		return $this->description;
	}

	public function supports( string $feature ): bool {
		return in_array( $feature, $this->supports, true );
	}

	public function get_status(): string {
		return $this->status;
	}

	public function get_fields_config(): array {
		return array();
	}

	public function apply(): void {
		// defined in child classes
	}

	public function get_date_from(): string {
		return $this->date_from;
	}

	public function get_date_to(): string {
		return $this->date_to;
	}
}