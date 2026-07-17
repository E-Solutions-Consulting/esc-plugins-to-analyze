<?php

namespace Objectiv\Plugins\Checkout\Interfaces;

use WP_Post;

/**
 * Interface for A/B Test UI Providers
 *
 * Defines the contract for classes that provide UI components (metaboxes, AJAX handlers, etc.)
 * for specific A/B test types in the admin interface.
 *
 * @link checkoutwc.com
 * @since 8.0.0
 * @package Objectiv\Plugins\Checkout\Interfaces
 */
interface ABTestUIProviderInterface {
	/**
	 * Get the metabox configurations for this test type
	 *
	 * @return array Array of metabox configurations with keys:
	 *               - title: The metabox title
	 *               - context: The metabox context ('normal', 'side', 'advanced')
	 *               - priority: The metabox priority ('high', 'default', 'low')
	 */
	public function get_metaboxes(): array;

	/**
	 * Render a specific metabox
	 *
	 * @param string  $metabox_id The metabox ID to render.
	 * @param WP_Post $post The post object.
	 * @return void
	 */
	public function render_metabox( string $metabox_id, WP_Post $post ): void;

	/**
	 * Save a specific metabox
	 *
	 * @param string  $metabox_id The metabox ID to save.
	 * @param int     $post_id The post ID.
	 * @param WP_Post $post The post object.
	 * @return void
	 */
	public function save_metabox( string $metabox_id, int $post_id, WP_Post $post ): void;

	/**
	 * Get the AJAX handler configurations for this test type
	 *
	 * @return array Array of AJAX handler configurations, keyed by action name
	 */
	public function get_ajax_handlers(): array;

	/**
	 * Handle an AJAX request
	 *
	 * @param string $action The AJAX action to handle.
	 * @param array  $data The request data.
	 * @return array The response data
	 */
	public function handle_ajax( string $action, array $data ): array;

	/**
	 * Enqueue admin scripts for this test type
	 *
	 * @return void
	 */
	public function enqueue_admin_scripts(): void;

	/**
	 * Enqueue admin styles for this test type
	 *
	 * @return void
	 */
	public function enqueue_admin_styles(): void;

	/**
	 * Render status-specific notices in the test metabox
	 *
	 * @param WP_Post $post The post object.
	 * @return void
	 */
	public function render_status_notices( WP_Post $post ): void;

	/**
	 * Handle status change lifecycle events
	 *
	 * @param int    $post_id    The test post ID.
	 * @param string $old_status The old status.
	 * @param string $new_status The new status.
	 * @return void
	 */
	public function handle_status_change( int $post_id, string $old_status, string $new_status ): void;

	/**
	 * Enqueue variant management scripts
	 *
	 * @return void
	 */
	public function enqueue_variant_management_script(): void;

	/**
	 * Delete test variants when test is deleted
	 *
	 * @param int $post_id The test post ID.
	 * @return void
	 */
	public function delete_test_variants( int $post_id ): void;
}
