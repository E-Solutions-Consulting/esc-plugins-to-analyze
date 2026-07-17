<?php

namespace Objectiv\Plugins\Checkout\Features;

use Objectiv\Plugins\Checkout\API\OrderBumpsSearchAPI;
use Objectiv\Plugins\Checkout\Factories\ABTestFactory;
use Objectiv\Plugins\Checkout\Features\ABTesting\ABTestTypeRegistry;
use Objectiv\Plugins\Checkout\Interfaces\ABTestInterface;
use Objectiv\Plugins\Checkout\Model\ABTests\NullABTest;
use Objectiv\Plugins\Checkout\Model\Bumps\BumpAbstract;
use stdClass;

/**
 * A/B Testing Feature
 *
 * @link checkoutwc.com
 * @since 11.0.0
 * @package Objectiv\Plugins\Checkout\Features
 */
class ABTesting extends FeaturesAbstract {

	/**
	 * Initialize the feature
	 */
	public function init() {
		// Defer registry initialization to init hook when translations are available
		add_action( 'init', array( ABTestTypeRegistry::class, 'init' ), 5 );

		// Always register post type and test types, even if feature is disabled
		// This allows the admin interface to work
		add_action( 'cfw_do_plugin_activation', array( $this, 'run_on_plugin_activation' ) );
		add_action( 'cfw_do_plugin_deactivation', array( $this, 'run_on_plugin_deactivation' ) );
		add_action( 'init', array( $this, 'register_post_type' ) );

		// Initialize API endpoint for order bumps search (needed for admin)
		new OrderBumpsSearchAPI();

		// Schedule event to check for expired tests (runs twice daily)
		add_action( 'init', array( $this, 'schedule_expired_tests_check' ) );
		add_action( 'cfw_ab_test_check_expired', array( $this, 'check_and_update_expired_tests' ) );

		// Pause A/B tests when related settings are disabled
		add_action( 'cfw_updated_setting', array( $this, 'maybe_pause_ab_tests_on_setting_change' ), 10, 3 );

		// Add Gutenberg notice when editing/creating A/B test variants
		add_action( 'enqueue_block_editor_assets', array( $this, 'maybe_add_variant_editor_notice' ) );

		// Only run feature functionality if active
		parent::init();
	}

	/**
	 * Run on plugin activation
	 */
	public function run_on_plugin_activation() {
		self::map_capabilities();
		$this->schedule_expired_tests_check();
	}

	/**
	 * Run on plugin deactivation
	 */
	public function run_on_plugin_deactivation() {
		wp_clear_scheduled_hook( 'cfw_ab_test_check_expired' );
	}

	/**
	 * Schedule the expired tests check event (runs twice daily)
	 *
	 * @return void
	 */
	public function schedule_expired_tests_check() {
		if ( ! wp_next_scheduled( 'cfw_ab_test_check_expired' ) ) {
			wp_schedule_event( time(), 'twicedaily', 'cfw_ab_test_check_expired' );
		}
	}

	/**
	 * Check for A/B tests that have exceeded their end date and set status to complete
	 *
	 * @return void
	 */
	public function check_and_update_expired_tests() {
		$now = current_time( 'mysql' );

		// Get all published A/B tests
		$tests = get_posts(
			array(
				'post_type'      => self::get_post_type(),
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $tests as $test_id ) {
			$status = get_post_meta( $test_id, 'cfw_ab_test_status', true );

			// Only check tests that are not already complete
			if ( 'complete' === $status ) {
				continue;
			}

			$date_to = get_post_meta( $test_id, 'cfw_ab_test_date_to', true );

			// If end date is set and we're after it, set status to complete
			if ( ! empty( $date_to ) && strtotime( $now ) > strtotime( $date_to ) ) {
				update_post_meta( $test_id, 'cfw_ab_test_status', 'complete' );
			}
		}
	}

	/**
	 * Run if CFW is enabled
	 */
	protected function run_if_cfw_is_enabled() {
		// Register and apply active A/B tests after test types are registered
		add_action( 'init', array( $this, 'register_active_ab_tests' ), 20 );
	}

	/**
	 * Register active A/B tests
	 *
	 * @return void
	 */
	public function register_active_ab_tests(): void {
		$active_tests = ABTestFactory::get_all();

		/** @var ABTestInterface $test */
		foreach ( $active_tests as $test ) {
			$test->apply();
		}
	}

	/**
	 * Register the A/B Test post type
	 */
	public function register_post_type() {
		$labels = array(
			'name'               => __( 'A/B Tests', 'checkout-wc' ),
			'singular_name'      => __( 'A/B Test', 'checkout-wc' ),
			'menu_name'          => __( 'A/B Tests', 'checkout-wc' ),
			'add_new'            => __( 'Add A/B Test', 'checkout-wc' ),
			'add_new_item'       => __( 'Add New A/B Test', 'checkout-wc' ),
			'edit'               => __( 'Edit', 'checkout-wc' ),
			'edit_item'          => __( 'Edit A/B Test', 'checkout-wc' ),
			'new_item'           => __( 'New A/B Test', 'checkout-wc' ),
			'view'               => __( 'View A/B Tests', 'checkout-wc' ),
			'view_item'          => __( 'View A/B Test', 'checkout-wc' ),
			'search_items'       => __( 'Search A/B Tests', 'checkout-wc' ),
			'not_found'          => __( 'No A/B Tests found', 'checkout-wc' ),
			'not_found_in_trash' => __( 'No A/B Tests found in trash', 'checkout-wc' ),
		);

		$post_type_args = array(
			'labels'              => $labels,
			'description'         => __( 'This is where you can manage A/B tests for order bumps and other checkout elements.', 'checkout-wc' ),
			'public'              => false,
			'show_ui'             => true,
			'capability_type'     => self::get_post_type(),
			'map_meta_cap'        => true,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_in_menu'        => false,
			'hierarchical'        => false,
			'rewrite'             => false,
			'query_var'           => false,
			'supports'            => array(
				'title',
			),
			'show_in_nav_menus'   => false,
		);

		register_post_type( self::get_post_type(), $post_type_args );
	}

	/**
	 * Map capabilities for the post type
	 */
	public static function map_capabilities() {
		global $wp_roles;

		if ( ! isset( $wp_roles ) ) {
			return;
		}

		$args                  = new stdClass();
		$args->map_meta_cap    = true;
		$args->capability_type = self::get_post_type();
		$args->capabilities    = array();

		foreach ( (array) get_post_type_capabilities( $args ) as $mapped ) {
			$wp_roles->add_cap( 'shop_manager', $mapped );
			$wp_roles->add_cap( 'administrator', $mapped );
		}

		$wp_roles->add_cap( 'shop_manager', 'cfw_manage_ab_tests' );
		$wp_roles->add_cap( 'administrator', 'cfw_manage_ab_tests' );
	}

	/**
	 * Get the post type slug
	 *
	 * @return string
	 */
	public static function get_post_type(): string {
		return 'cfw_ab_test';
	}

	/**
	 * Pause A/B tests when related settings are disabled
	 *
	 * @param string $setting The setting key (may include _cfw_ prefix).
	 * @param mixed  $new_value The new setting value.
	 * @param mixed  $old_value The old setting value.
	 * @return void
	 */
	public function maybe_pause_ab_tests_on_setting_change( $setting, $new_value, $old_value ): void {
		// Normalize setting key by removing prefix for comparison
		$normalized_setting = str_replace( '_cfw_', '', $setting );

		// Check if this is the enable_order_bumps setting
		if ( 'enable_order_bumps' === $normalized_setting ) {
			// Only pause if setting was just disabled (changed from 'yes' to something else)
			if ( 'yes' === $old_value && 'yes' !== $new_value ) {
				$this->pause_ab_tests( 'order_bumps' );
			}
			return;
		}

		// Check if this is the enable_ab_testing setting
		if ( 'enable_ab_testing' === $normalized_setting ) {
			// Only pause if setting was just disabled (changed from 'yes' to something else)
			if ( 'yes' === $old_value && 'yes' !== $new_value ) {
				$this->pause_ab_tests();
			}
			return;
		}
	}

	/**
	 * Pause A/B tests based on type filter
	 *
	 * @param string|null $test_type Optional. Test type to filter by (e.g., 'order_bumps'). If null, pauses all tests.
	 * @return void
	 */
	private function pause_ab_tests( ?string $test_type = null ): void {
		$meta_query = array(); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query

		// If filtering by test type, add that condition
		if ( $test_type ) {
			$meta_query[] = array(
				'key'   => 'cfw_ab_test_type',
				'value' => $test_type,
			);
		}

		// Exclude tests that are already paused or complete
		$meta_query[] = array(
			'key'     => 'cfw_ab_test_status',
			'value'   => array( 'paused', 'complete' ),
			'compare' => 'NOT IN',
		);

		$tests = ABTestFactory::get_all(
			'any',
			array(
				'meta_query' => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			)
		);

		foreach ( $tests as $test ) {
			$test_id = $test->get_id();
			if ( $test_id ) {
				update_post_meta( $test_id, 'cfw_ab_test_status', 'paused' );
			}
		}
	}

	/**
	 * Add Gutenberg notice when editing/creating an A/B test variant
	 *
	 * @return void
	 */
	public function maybe_add_variant_editor_notice() {
		global $post, $typenow;

		// Check if we're editing/creating an order bump post
		$post_type = null;
		if ( isset( $post ) && isset( $post->post_type ) ) {
			$post_type = $post->post_type;
		} elseif ( isset( $typenow ) ) {
			$post_type = $typenow;
		} elseif ( isset( $_GET['post_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$post_type = sanitize_text_field( wp_unslash( $_GET['post_type'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		if ( ! $post_type || BumpAbstract::get_post_type() !== $post_type ) {
			return;
		}

		// Check if cfw_ab_test query var is present
		$ab_test_id = isset( $_GET['cfw_ab_test'] ) ? absint( $_GET['cfw_ab_test'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $ab_test_id ) {
			return;
		}

		// Verify A/B test exists
		$ab_test = ABTestFactory::get( $ab_test_id );
		if ( get_class( $ab_test ) === get_class( new NullABTest() ) ) {
			return;
		}

		// Get order bump ID from A/B test
		$order_bump_id = get_post_meta( $ab_test_id, 'cfw_ab_test_order_bump', true );
		if ( ! $order_bump_id ) {
			return;
		}

		// Ensure wp-edit-post is enqueued (includes wp-element)
		wp_enqueue_script( 'wp-edit-post' );

		// Add inline script to create the notice and update back button
		$script = '(function() {
			function addNoticeAndUpdateButton() {
				if (!window.wp || !window.wp.data) {
					return;
				}

				var messageText = ' . wp_json_encode( __( 'You are editing an A/B test variant. After saving, close this window to return to your variants list.', 'checkout-wc' ), JSON_UNESCAPED_SLASHES ) . ";

				wp.data.dispatch('core/notices').createNotice(
					'info',
					messageText,
					{
						isDismissible: false
					}
				);

				// Update back button href - try multiple times in case it's not ready yet
				function updateBackButton() {
					var backButton = document.querySelector('.editor-header__back-button a');
					if (backButton) {
						backButton.href = '#';
					} else {
						// Retry after a short delay if button not found
						setTimeout(updateBackButton, 100);
					}
				}
				updateBackButton();
			}

			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', addNoticeAndUpdateButton);
			} else {
				wp.domReady(addNoticeAndUpdateButton);
			}
		})();";

		wp_add_inline_script( 'wp-edit-post', $script );
	}

	public static function get_all_test_types(): array {
		// Now delegates to the registry
		return ABTestTypeRegistry::get_all_types();
	}
}
