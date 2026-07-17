<?php

namespace Objectiv\Plugins\Checkout;

class EditorPreviewSettingsOverride {
	public function init() {
		if ( is_admin() ) {
			return;
		}

		$is_preview_page = isset( $_GET['cfw-editor-preview'] ) && $_GET['cfw-editor-preview'] === '1'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$is_update_order_review_with_preview = $this->is_update_order_review_with_editor_preview();

		if ( ! $is_preview_page && ! $is_update_order_review_with_preview ) {
			return;
		}

		if ( $is_preview_page ) {
			if ( ! isset( $_GET['_cfw_preview_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_cfw_preview_nonce'] ) ), 'cfw-editor-preview' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return;
			}
		}

		if ( ! current_user_can( 'cfw_manage_pages' ) ) {
			return;
		}

		if ( $is_preview_page ) {
			add_filter( 'show_admin_bar', array( $this, 'filter_show_admin_bar_in_editor_preview' ), 1 );
		}

		$user_id  = get_current_user_id();
		$settings = get_transient( '_cfw_editor_preview_' . $user_id );

		if ( ! is_array( $settings ) || empty( $settings ) ) {
			return;
		}

		$this->apply_preview_overrides( $settings );
	}

	/**
	 * Apply preview setting overrides (filters only). Call early from main plugin file before premium-init so features that read settings at construction see preview values.
	 *
	 * @param array<string, mixed> $settings Preview settings from transient.
	 */
	public function apply_preview_overrides_early( array $settings ): void {
		$this->apply_preview_overrides( $settings );
	}

	/**
	 * Hide the WordPress admin bar in the editor preview iframe (filterable).
	 *
	 * @return bool Whether to show the admin bar. Default false in editor preview.
	 */
	public function filter_show_admin_bar_in_editor_preview(): bool {
		return cfw_apply_filters( 'cfw_show_admin_bar_in_editor_preview', false );
	}

	/**
	 * Whether the current request is update_order_review AJAX with editor preview flag.
	 */
	private function is_update_order_review_with_editor_preview(): bool {
		if ( ! isset( $_REQUEST['wc-ajax'] ) || sanitize_text_field( wp_unslash( $_REQUEST['wc-ajax'] ) ) !== 'update_order_review' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}
		if ( ! isset( $_POST['cfw_editor_preview'] ) || $_POST['cfw_editor_preview'] !== '1' ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return false;
		}
		if ( ! isset( $_POST['_cfw_preview_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_cfw_preview_nonce'] ) ), 'cfw-editor-preview' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return false;
		}
		return true;
	}

	/**
	 * Apply transient-based setting overrides and empty-cart bypass for preview.
	 *
	 * @param array<string, mixed> $settings
	 */
	private function apply_preview_overrides( array $settings ): void {
		// Bypass empty cart redirect for preview.
		add_filter( 'woocommerce_checkout_redirect_empty_cart', '__return_false' );

		foreach ( $settings as $key => $value ) {
			add_filter(
				'cfw_get_setting_' . $key,
				function () use ( $value ) {
					return $value;
				},
				999
			);

			// WooCommerce and other options read via get_option() — override for preview.
			if ( strpos( $key, 'wp_option/' ) === 0 ) {
				$option_name = str_replace( 'wp_option/', '', $key );
				add_filter(
					'pre_option_' . $option_name,
					function () use ( $value ) {
						return $value;
					},
					999
				);
			}
		}
	}
}
