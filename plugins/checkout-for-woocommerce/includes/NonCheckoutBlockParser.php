<?php

namespace Objectiv\Plugins\Checkout;

use WP_Block_Parser;

class NonCheckoutBlockParser extends WP_Block_Parser {
	/**
	 * List of parsed blocks
	 *
	 * @var \WP_Block_Parser_Block[]
	 */
	public $output;

	/**
	 * Parse the blocks from the document
	 *
	 * @param string $document Document to parse.
	 * @return array[]
	 */
	public function parse( $document ): array {
		parent::parse( $document );

		// Filter out checkout blocks recursively
		$this->output = $this->filter_checkout_blocks( $this->output );

		return $this->output;
	}

	/**
	 * Recursively filter out checkout blocks from the blocks array
	 *
	 * @param array $blocks Array of blocks to filter.
	 * @return array Filtered blocks array.
	 */
	private function filter_checkout_blocks( array $blocks ): array {
		return array_filter(
			array_map(
				function ( $block ) {
					// Skip checkout blocks entirely
					if ( 'woocommerce/checkout' === $block['blockName'] ) {
							return null;
					}

					// Process inner blocks recursively if they exist
					if ( ! empty( $block['innerBlocks'] ) ) {
						$block['innerBlocks'] = $this->filter_checkout_blocks( $block['innerBlocks'] );
					}

					return $block;
				},
				$blocks
			)
		);
	}
}
