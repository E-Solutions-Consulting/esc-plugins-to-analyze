<?php

namespace Objectiv\Plugins\Checkout\Features;

use Objectiv\Plugins\Checkout\Admin\Pages\PageAbstract;
use Objectiv\Plugins\Checkout\Interfaces\SettingsGetterInterface;
use Objectiv\Plugins\Checkout\Managers\SettingsManager;

class PhpSnippets extends FeaturesAbstract {
	protected $php_snippets_field_name;

	public function __construct( bool $enabled, bool $available, string $required_plans_list, SettingsGetterInterface $settings_getter, string $php_snippets_field_name ) {
		$this->php_snippets_field_name = $php_snippets_field_name;

		parent::__construct( $enabled, $available, $required_plans_list, $settings_getter );
	}

	protected function run_if_cfw_is_enabled() {
		$php_snippets = $this->sanitize_snippet( $this->settings_getter->get_setting( 'php_snippets' ) );

		if ( empty( $php_snippets ) ) {
			return;
		}

		if ( class_exists( '\\ParseError' ) ) {
			try {
				eval( $php_snippets ); // phpcs:ignore
			} catch( \ParseError $e ) { // phpcs:ignore
				wc_get_logger()->error( 'CheckoutWC: Failed to load PHP snippets. Parse Error: ' . $e->getMessage(), array( 'source' => 'checkout-wc' ) );
			}
		} else {
			eval( $php_snippets ); // phpcs:ignore
		}
	}

	/**
	 * @param string $code The code to sanitize.
	 * @return string
	 */
	private function sanitize_snippet( string $code ): string {
		/* Remove <?php and <? from beginning of snippet */
		$code = preg_replace( '|^[\s]*<\?(php)?|', '', $code );

		/* Remove ?> from end of snippet */
		$code = preg_replace( '|\?>[\s]*$|', '', $code );

		return strval( $code );
	}
}
