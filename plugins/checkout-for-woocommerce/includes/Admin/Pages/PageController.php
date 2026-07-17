<?php

namespace Objectiv\Plugins\Checkout\Admin\Pages;

use Objectiv\Plugins\Checkout\Managers\NoticesManager;
use function WordpressEnqueueChunksPlugin\get as cfwChunkedScriptsConfigGet;

class PageController {
	protected $pages = array();

	public function __construct( array $pages ) {
		$this->pages = $pages;
	}

	public function init() {
		add_action( 'admin_head', array( $this, 'custom_css' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 1000 );

		$this->maybe_add_body_class();

		foreach ( $this->pages as $page ) {
			$page->init();
		}
	}

	public function custom_css() {
		$custom_css  = 'ul.wp-submenu span.cfw-premium-badge::after, #wpadminbar span.cfw-premium-badge::after { content:"' . esc_html__( 'Premium', 'checkout-wc' ) . '"}';
		$custom_css .= 'li a[href*="lite-upgrade"] { background-color: #00a32a !important; color: #fff !important; font-weight: 600 !important;}';

		echo "<style>$custom_css</style>"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public function is_cfw_admin_page(): bool {
		foreach ( $this->pages as $page ) {
			if ( $page->is_current_page() ) {
				return true;
			}
		}

		return false;
	}

	public function enqueue_scripts() {
		$front    = CFW_PATH_ASSETS;
		$manifest = cfwChunkedScriptsConfigGet( 'manifest' );

		// PHP 8.1+ Fix
		foreach ( $manifest['chunks'] as $chunk_name => $chunk ) {
			add_filter(
				"wpecp/register/{$chunk_name}",
				function ( $args ) use ( $chunk_name ) {
					if ( ! in_array( $chunk_name, array( 'admin', 'admin-acr-reports', 'admin-settings' ), true ) ) {
						return $args;
					}

					array_push( $args['deps'], 'wp-color-picker', 'wc-enhanced-select', 'jquery-blockui', 'wp-api' );

					return $args;
				}
			);
		}

		// Admin global styles
		if ( isset( $manifest['chunks']['admin-global-styles']['file'] ) ) {
			wp_enqueue_style( 'objectiv-cfw-admin-global-styles', "{$front}/{$manifest['chunks']['admin-global-styles']['file']}", array(), $manifest['chunks']['admin-global-styles']['hash'] );
		}

		// Only load on our pages past here.
		if ( ! $this->is_cfw_admin_page() ) {
			return;
		}

		if ( isset( $manifest['chunks']['admin-styles']['file'] ) ) {
			wp_enqueue_style( 'objectiv-cfw-admin-styles', "{$front}/{$manifest['chunks']['admin-styles']['file']}", array( 'wc-components', 'wp-components' ), $manifest['chunks']['admin-styles']['hash'] );
		}

		wp_enqueue_style( 'woocommerce_admin_styles' );
		cfw_register_scripts( array( 'admin' ) );

		wp_enqueue_code_editor( array( 'type' => 'text/html' ) );
		wp_enqueue_script( 'cfw-admin' );

		$settings_array = array(
			'i18n_nav_warning' => __( 'The changes you made will be lost if you navigate away from this page.', 'woocommerce' ),
			'ajax_url'         => admin_url( 'admin-ajax.php' ),
			'nonce'            => wp_create_nonce( 'objectiv-cfw-admin-save' ),
			'deferred_notices' => $this->get_deferred_notices(),
		);
		wp_localize_script( 'cfw-admin', 'objectiv_cfw_admin', $settings_array );
	}

	protected function maybe_add_body_class() {
		if ( ! $this->is_cfw_admin_page() ) {
			return;
		}

		add_filter(
			'admin_body_class',
			function ( $classes ) {
				return $classes . ' cfw-admin-page';
			},
			10000
		);
	}

	protected function get_deferred_notices(): array {
		$raw_notices = NoticesManager::instance()->get_deferred_notices();
		$notices     = array();

		foreach ( $raw_notices as $notice ) {
			if ( ! $notice->show() ) {
				continue;
			}

			ob_start();
			$notice->the_notice();

			$notices[] = ob_get_clean();
		}

		return $notices;
	}
}
