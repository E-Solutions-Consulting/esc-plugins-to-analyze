<?php

namespace Objectiv\Plugins\Checkout\Admin\Pages\Premium;

use Objectiv\Plugins\Checkout\Admin\Pages\PageAbstract;
use Objectiv\Plugins\Checkout\Admin\TabNavigation;
use Objectiv\Plugins\Checkout\Admin\Pages\Traits\TabbedAdminPageTrait;
use Objectiv\Plugins\Checkout\Factories\ABTestFactory;
use Objectiv\Plugins\Checkout\Features\ABTesting;
use Objectiv\Plugins\Checkout\Features\ABTesting\ABTestTypeRegistry;
use Objectiv\Plugins\Checkout\Interfaces\ABTestUIProvidable;
use Objectiv\Plugins\Checkout\Managers\SettingsManager;
use Objectiv\Plugins\Checkout\Model\ABTests\NullABTest;
use WP_Post;

/**
 * A/B Testing Admin Page
 *
 * @link checkoutwc.com
 * @since 11.0.0
 * @package Objectiv\Plugins\Checkout\Admin\Pages
 */
class ABTestingAdmin extends PageAbstract {
	use TabbedAdminPageTrait;

	public function __construct() {
		parent::__construct( __( 'A/B Testing', 'checkout-wc' ), 'cfw_manage_ab_tests', 'ab_testing' );
	}

	public function init() {
		parent::init();

		$this->set_tabbed_navigation( new TabNavigation( 'settings' ) );

		$this->get_tabbed_navigation()->add_tab( __( 'Settings', 'checkout-wc' ), add_query_arg( [ 'subpage' => 'settings' ], $this->get_url() ), 'settings' );
		$this->get_tabbed_navigation()->add_tab(
			__( 'Manage Tests', 'checkout-wc' ),
			add_query_arg(
				[
					'post_type' => ABTesting::get_post_type(),
				],
				admin_url( 'edit.php' )
			),
			'manage-tests'
		);

		add_action( 'all_admin_notices', [ $this, 'output_post_type_editor_header' ] );

		/**
		 * Highlights A/B Testing submenu item when on the New A/B Test admin page
		 */
		add_filter( 'submenu_file', [ $this, 'maybe_highlight_submenu_item' ] );

		/**
		 * Highlight parent menu
		 */
		add_filter( 'parent_file', [ $this, 'menu_highlight' ] );

		/**
		 * Highlight Manage A/B Tests tab
		 */
		add_filter( 'cfw_selected_tab', [ $this, 'maybe_set_manage_tests_tab' ] );

		add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
		add_action( 'save_post', [ $this, 'save_meta_boxes' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_ab_test_admin_scripts' ] );

		add_action( 'load-post-new.php', [ $this, 'auto_create_draft_ab_test' ] );

		// Register UI provider AJAX handlers dynamically
		add_action( 'admin_init', [ $this, 'register_ui_provider_ajax_handlers' ] );

		add_action( 'before_delete_post', [ $this, 'delete_ab_test_variants' ] );

		$post_type = ABTesting::get_post_type();

		add_filter( "manage_{$post_type}_posts_columns", [ $this, 'add_custom_columns' ] );
		add_filter( "manage_edit-{$post_type}_sortable_columns", [ $this, 'add_sortable_columns' ] );
		add_action( "manage_{$post_type}_posts_custom_column", [ $this, 'render_custom_column' ], 10, 2 );
		add_action( 'pre_get_posts', [ $this, 'handle_column_sorting' ] );
		add_filter( 'posts_join', [ $this, 'modify_sorting_join' ], 10, 2 );
		add_filter( 'posts_orderby', [ $this, 'modify_sorting_orderby' ], 10, 2 );
		add_filter( 'post_row_actions', [ $this, 'disable_inline_edit' ], 10, 2 );
		add_filter( "bulk_actions-edit-{$post_type}", [ $this, 'remove_edit_bulk_action' ] );
	}

	/**
	 * Add custom columns to the A/B tests list table
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function add_custom_columns( $columns ) {
		$date                 = array_pop( $columns );
		$columns['test_type'] = __( 'Type', 'checkout-wc' );
		$columns['status']    = __( 'Status', 'checkout-wc' );
		$columns['duration']  = __( 'Duration', 'checkout-wc' );
		$columns['date']      = $date;
		return $columns;
	}

	/**
	 * Add sortable columns to the A/B tests list table
	 *
	 * @param array $columns Existing sortable columns.
	 * @return array
	 */
	public function add_sortable_columns( $columns ) {
		$columns['test_type'] = 'cfw_ab_test_type';
		$columns['status']    = 'cfw_ab_test_status';
		$columns['duration']  = 'cfw_ab_test_date_from';
		return $columns;
	}

	/**
	 * Render custom column content
	 *
	 * @param string $column Column name.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function render_custom_column( $column, $post_id ) {
		$test   = ABTestFactory::get( $post_id );
		$status = $test->get_status();

		if ( empty( $status ) ) {
			$status = 'paused';
		}

		if ( 'test_type' === $column ) {
			echo esc_html( $test->get_label() );
		}

		if ( 'status' === $column ) {
			$status_labels = [
				'active'   => __( 'Active', 'checkout-wc' ),
				'paused'   => __( 'Paused', 'checkout-wc' ),
				'complete' => __( 'Complete', 'checkout-wc' ),
			];

			$label = $status_labels[ $status ] ?? $status;

			$color = '';
			if ( 'active' === $status ) {
				$color = 'color: #72aee6;'; // Blue
			} elseif ( 'paused' === $status ) {
				$color = 'color: #dba617;'; // Orange
			} elseif ( 'complete' === $status ) {
				$color = 'color: #00a32a;'; // Green
			}

			printf(
				'<span style="font-weight: bold; %s">%s</span>',
				esc_attr( $color ),
				esc_html( $label )
			);
		}

		if ( 'duration' === $column ) {
			// If test is complete, show "Finished" in the duration column, as makes more sense than "Ongoing".
			if ( 'complete' === $status ) {
				echo esc_html__( 'Finished', 'checkout-wc' );
				return;
			}

			// Show duration between start and end dates or "Ongoing" if no dates are set.
			$date_from = $test->get_date_from();
			$date_to   = $test->get_date_to();

			if ( empty( $date_from ) && empty( $date_to ) ) {
				echo esc_html__( 'Ongoing', 'checkout-wc' );
				return;
			}

			$formatted_dates = [];

			if ( ! empty( $date_from ) ) {
				$formatted_dates[] = date_i18n( get_option( 'date_format' ), strtotime( $date_from ) );
			}

			if ( ! empty( $date_to ) ) {
				$formatted_dates[] = date_i18n( get_option( 'date_format' ), strtotime( $date_to ) );
			}

			if ( ! empty( $date_from ) && ! empty( $date_to ) ) {
				// Both dates to show, so show the start and end dates.
				echo esc_html( implode( ' → ', $formatted_dates ) );
				return;
			}

			// Only one date to show, so just show the date.
			echo esc_html( $formatted_dates[0] );
		}
	}

	/**
	 * Register AJAX handlers for UI providers
	 *
	 * @return void
	 */
	public function register_ui_provider_ajax_handlers(): void {
		$test_types = ABTestTypeRegistry::get_all_test_types();

		foreach ( $test_types as $type_key => $type_info ) {
			$type_class = $type_info['class'];

			// Create dummy instance to check for UI provider
			$test = new $type_class( 0 );

			if ( $test instanceof ABTestUIProvidable && $test->has_ui_provider() ) {
				$ui_provider   = $test->get_ui_provider();
				$ajax_handlers = $ui_provider->get_ajax_handlers();

				foreach ( $ajax_handlers as $action => $method ) {
					$ajax_action = "cfw_{$type_key}_ab_test_{$action}";

					add_action(
						"wp_ajax_{$ajax_action}",
						function () use ( $type_key, $action ) {
							$this->handle_ui_provider_ajax( $type_key, $action );
						}
					);
				}
			}
		}
	}

	/**
	 * Handle AJAX request for UI provider
	 *
	 * @param string $type_key Test type key
	 * @param string $action   Action name
	 * @return void
	 */
	private function handle_ui_provider_ajax( string $type_key, string $action ): void {
		// Security check
		check_ajax_referer( 'objectiv-cfw-admin-save', 'nonce' );

		if ( ! current_user_can( 'cfw_manage_ab_tests' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'checkout-wc' ) ] );
		}

		// Get test type info
		$test_types = ABTestTypeRegistry::get_all_test_types();
		if ( ! isset( $test_types[ $type_key ] ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid test type.', 'checkout-wc' ) ] );
		}

		$type_class = $test_types[ $type_key ]['class'];
		$post_id    = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		// Get test instance
		$test = new $type_class( $post_id );

		if ( ! $test instanceof ABTestUIProvidable || ! $test->has_ui_provider() ) {
			wp_send_json_error( [ 'message' => __( 'Test type does not support UI operations.', 'checkout-wc' ) ] );
		}

		$ui_provider = $test->get_ui_provider();

		// Handle the action
		$result = $ui_provider->handle_ajax( $action, $_POST );

		if ( isset( $result['success'] ) && $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * Disable inline edit action for A/B tests
	 *
	 * @param array   $actions An array of row action links.
	 * @param WP_Post $post    The post object.
	 * @return array
	 */
	public function disable_inline_edit( $actions, $post ) {
		if ( ABTesting::get_post_type() === $post->post_type ) {
			// Removed as updates directly from the list might result in a mismatch of test status
			// Inline edits only update the changed meta specifically vs via the post page where the status can get changed after full post save if matching specific conditions.
			unset( $actions['inline hide-if-no-js'] );
		}
		return $actions;
	}

	/**
	 * Remove Edit bulk action for A/B tests
	 *
	 * @param array $actions An array of bulk actions.
	 * @return array
	 */
	public function remove_edit_bulk_action( $actions ) {
		// Removed for the same reason as disable_inline_edit.
		unset( $actions['edit'] );
		return $actions;
	}

	/**
	 * Handle sorting for custom columns
	 *
	 * @param \WP_Query $query The WP_Query instance.
	 * @return void
	 */
	public function handle_column_sorting( $query ) {
		// Only apply to admin and main query
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$post_type = ABTesting::get_post_type();

		// Check if we're on the correct screen
		$screen = get_current_screen();
		if ( ! $screen || "edit-{$post_type}" !== $screen->id ) {
			return;
		}

		if ( $post_type !== $query->get( 'post_type' ) ) {
			return;
		}

		$orderby = $query->get( 'orderby' );

		// Sort by test type
		if ( 'cfw_ab_test_type' === $orderby ) {
			$query->set( 'meta_key', 'cfw_ab_test_type' );
			$query->set( 'orderby', 'meta_value' );
		}

		// Sort by status
		if ( 'cfw_ab_test_status' === $orderby ) {
			$query->set( 'meta_key', 'cfw_ab_test_status' );
			$query->set( 'orderby', 'meta_value' );
		}

		// Sort by duration (start date) - use custom query to ensure tests without date data are still included in the list.
		if ( 'cfw_ab_test_date_from' === $orderby ) {
			// Store flag to use in filters
			$query->set( 'cfw_ab_test_orderby_date_from', true );
			// Clear default meta sorting
			$query->set( 'meta_key', '' );
			$query->set( 'orderby', 'none' );
		}
	}

	/**
	 * Modify JOIN clause for date sorting to ensure tests without date data are still included in the list.
	 *
	 * @param string    $join The JOIN clause.
	 * @param \WP_Query $query The WP_Query instance.
	 * @return string
	 */
	public function modify_sorting_join( $join, $query ) {
		// Only apply when sorting by date_from
		if ( ! $query->get( 'cfw_ab_test_orderby_date_from' ) ) {
			return $join;
		}

		$post_type = ABTesting::get_post_type();
		$screen    = get_current_screen();

		// Only apply to our post type admin screen
		if ( ! $screen || "edit-{$post_type}" !== $screen->id ) {
			return $join;
		}

		global $wpdb;

		// Check if we already have this join
		if ( strpos( $join, "LEFT JOIN {$wpdb->postmeta} AS cfw_date_sort_meta" ) !== false ) {
			return $join;
		}

		// Add LEFT JOIN to include posts without meta
		$join .= " LEFT JOIN {$wpdb->postmeta} AS cfw_date_sort_meta ON ({$wpdb->posts}.ID = cfw_date_sort_meta.post_id AND cfw_date_sort_meta.meta_key = 'cfw_ab_test_date_from')";

		return $join;
	}

	/**
	 * Modify ORDER BY clause for date sorting
	 *
	 * @param string    $orderby The ORDER BY clause.
	 * @param \WP_Query $query The WP_Query instance.
	 * @return string
	 */
	public function modify_sorting_orderby( $orderby, $query ) {
		// Only apply when sorting by date_from
		if ( ! $query->get( 'cfw_ab_test_orderby_date_from' ) ) {
			return $orderby;
		}

		$post_type = ABTesting::get_post_type();
		$screen    = get_current_screen();

		// Only apply to our post type admin screen
		if ( ! $screen || "edit-{$post_type}" !== $screen->id ) {
			return $orderby;
		}

		$order = $query->get( 'order' );
		if ( empty( $order ) ) {
			$order = 'ASC';
		}

		// Use COALESCE to treat NULL/empty as far-future date for sorting (posts without dates appear last)
		$orderby = "CAST(COALESCE(cfw_date_sort_meta.meta_value, '9999-12-31 00:00:00') AS DATETIME) {$order}";

		return $orderby;
	}

	/**
	 * Output the A/B testing admin page
	 *
	 * @return void
	 */
	public function output() {
		$current_tab_function = $this->get_tabbed_navigation()->get_current_tab() . '_tab';
		$callable             = [ $this, $current_tab_function ];

		$this->get_tabbed_navigation()->display_tabs();

		call_user_func( $callable );
	}

	/**
	 * Output the settings tab
	 *
	 * @return void
	 */
	public function settings_tab() {
		?>
		<div id="cfw-admin-pages-ab-testing"></div>
		<?php
	}

	/**
	 * Check if the current page is the A/B testing admin page
	 *
	 * @return bool
	 */
	public function is_current_page(): bool {
		global $post;

		if ( parent::is_current_page() ) {
			return true;
		}

		$post_type = ABTesting::get_post_type();

		if ( isset( $_GET['post_type'] ) && $_GET['post_type'] === $post_type ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}

		if ( $post && $post->post_type === $post_type ) {
			return true;
		}

		return false;
	}

	/**
	 * Highlight the A/B testing submenu item
	 *
	 * @param mixed $submenu_file The submenu file.
	 * @return mixed
	 */
	public function maybe_highlight_submenu_item( $submenu_file ) {
		global $post;

		$post_type = ABTesting::get_post_type();

		if ( isset( $_GET['post_type'] ) && $_GET['post_type'] === $post_type ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $this->get_slug();
		} elseif ( $post && $post->post_type === $post_type ) {
			return $this->get_slug();
		}

		return $submenu_file;
	}

	/**
	 * Highlight the A/B testing parent menu
	 *
	 * @param string $parent_file The parent file.
	 * @return string
	 */
	public function menu_highlight( $parent_file ) {
		global $plugin_page, $post_type;

		if ( ABTesting::get_post_type() === $post_type ) {
			$plugin_page = PageAbstract::$parent_slug; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		return $parent_file;
	}

	/**
	 * Set the manage tests tab as active when on A/B test pages
	 *
	 * @param string $selected_tab The currently selected tab.
	 * @return string
	 */
	public function maybe_set_manage_tests_tab( $selected_tab ) {
		global $post;

		$post_type = ABTesting::get_post_type();

		// Check if we're on any A/B test related page
		if ( isset( $_GET['post_type'] ) && $_GET['post_type'] === $post_type ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return 'manage-tests';
		} elseif ( $post && $post->post_type === $post_type ) {
			return 'manage-tests';
		}

		return $selected_tab;
	}

	/**
	 * The admin page wrap for post type editor
	 *
	 * @return void
	 */
	public function output_post_type_editor_header() {
		global $post;

		if ( isset( $_GET['post_type'] ) && ABTesting::get_post_type() !== $_GET['post_type'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		} elseif ( isset( $post ) && ABTesting::get_post_type() !== $post->post_type ) {
			return;
		} elseif ( ! isset( $_GET['post_type'] ) && ! isset( $post ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		?>
		<div class="cfw-admin-notices-container">
			<div class="wp-header-end"></div>
			<div id="cfw-custom-admin-notices"></div>
		</div>
		<div class="cfw-tw">
			<div id="cfw_admin_page_header" class="absolute left-0 right-0 top-0 divide-y shadow z-50">
				<?php
				/**
				 * Fires before the admin page header
				 *
				 * @param ABTestingAdmin $this The ABTestingAdmin instance.
				 */
				do_action( 'cfw_before_admin_page_header', $this );
				?>
				<div class="min-h-[64px] bg-white flex items-center pl-8">
					<span>
						<?php echo file_get_contents( CFW_PATH . '/assets/images/cfw.svg' ); // phpcs:ignore ?>
					</span>
					<nav class="flex" aria-label="Breadcrumb">
						<ol role="list" class="flex items-center space-x-2">
							<li class="m-0">
								<div class="flex items-center">
									<span class="ml-2 text-sm font-medium text-gray-800">
										<?php _e( 'CheckoutWC', 'checkout-wc' ); ?>
									</span>
								</div>
							</li>
							<li class="m-0">
								<div class="flex items-center">
									<!-- Heroicon name: solid/chevron-right -->
									<svg class="flex-shrink-0 h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg"
										viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
										<path fill-rule="evenodd"
												d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
												clip-rule="evenodd"/>
									</svg>
									<span class="ml-2 text-sm font-medium text-gray-500" aria-current="page">
										<?php echo wp_kses_post( $this->title ); ?>
									</span>
								</div>
							</li>
						</ol>
					</nav>
				</div>
				<?php
				/**
				 * Fires after the admin page header
				 *
				 * @param ABTestingAdmin $this The ABTestingAdmin instance.
				 */
				do_action( 'cfw_after_admin_page_header', $this );
				?>
			</div>

			<div class="mt-10 mr-4">
				<?php $this->get_tabbed_navigation()->display_tabs(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Set script data for the A/B testing admin page
	 *
	 * @return void
	 */
	public function maybe_set_script_data() {
		if ( ! $this->is_current_page() || 'settings' !== $this->get_tabbed_navigation()->get_current_tab() ) {
			return;
		}

		$this->set_script_data(
			[
				'settings' => [
					'enable_ab_testing' => SettingsManager::instance()->get_setting( 'enable_ab_testing' ) === 'yes',
				],
				'plan'     => $this->get_plan_data(),
			]
		);
	}

	/**
	 * Add meta boxes
	 *
	 * @return void
	 */
	public function add_meta_boxes() {
		global $post;

		// Add the test meta box
		add_meta_box(
			'cfw_ab_test_test_meta_box',
			__( 'Test', 'checkout-wc' ),
			[ $this, 'render_test_meta_box' ],
			ABTesting::get_post_type(),
			'normal',
			'high'
		);

		// Only continue if we have a post
		if ( ! $post ) {
			return;
		}

		$test = ABTestFactory::get( $post->ID );

		if ( get_class( $test ) === get_class( new NullABTest() ) ) {
			return;
		}

		// Check if test provides UI components
		if ( $test instanceof ABTestUIProvidable && $test->has_ui_provider() ) {
			$ui_provider = $test->get_ui_provider();

			if ( $ui_provider ) {
				$metaboxes = $ui_provider->get_metaboxes();

				foreach ( $metaboxes as $metabox_id => $metabox_config ) {
					add_meta_box(
						$metabox_id,
						$metabox_config['title'],
						function ( $post ) use ( $ui_provider, $metabox_id ) {
							$ui_provider->render_metabox( $metabox_id, $post );
						},
						ABTesting::get_post_type(),
						$metabox_config['context'] ?? 'normal',
						$metabox_config['priority'] ?? 'default'
					);
				}
			}
		}
	}

	/**
	 * Render test meta box
	 *
	 * @param WP_Post $post The post object.
	 * @return void
	 */
	public function render_test_meta_box( $post ) {
		wp_nonce_field( 'cfw_ab_test_meta_box', 'cfw_ab_test_meta_box_nonce' );

		$test       = ABTestFactory::get( $post->ID );
		$test_type  = $test->get_type();
		$test_types = ABTestTypeRegistry::get_types_with_labels();

		// If no type is selected and this is a new post, auto-select the first available type
		if ( empty( $test_type ) && ! empty( $test_types ) && 'auto-draft' === $post->post_status ) {
			$test_type = array_key_first( $test_types );
		}

		// Get test status
		$test_status = $test->get_status();

		if ( empty( $test_status ) ) {
			$test_status = 'active';
		}

		// Check if test is complete with a winner selected
		$winner_variant_id       = get_post_meta( $post->ID, 'cfw_ab_test_winner_variant_id', true );
		$is_complete_with_winner = ( 'complete' === $test_status && ! empty( $winner_variant_id ) );

		// Get date fields
		$date_from = $test->get_date_from();
		$date_to   = $test->get_date_to();

		// Format dates for display (YYYY-MM-DD)
		$date_from_display = ! empty( $date_from ) ? date( 'Y-m-d', strtotime( $date_from ) ) : '';
		$date_to_display   = ! empty( $date_to ) ? date( 'Y-m-d', strtotime( $date_to ) ) : '';

		?>
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">
						<label for="cfw_ab_test"><?php esc_html_e( 'Type', 'checkout-wc' ); ?></label>
					</th>
					<td>
						<?php
						// Make type readonly after initial save (not in auto-draft status)
						$is_type_readonly = 'auto-draft' !== $post->post_status && ! empty( $test_type );
						?>
						<?php if ( $is_type_readonly ) : ?>
							<input type="hidden" name="cfw_ab_test_type" value="<?php echo esc_attr( $test_type ); ?>" />
						<?php endif; ?>
						<select name="cfw_ab_test_type" id="cfw_ab_test_type" style="width: 50%;" <?php echo $is_type_readonly ? 'disabled' : 'required'; ?>>
							<?php foreach ( $test_types as $type_key => $type_label ) : ?>
								<option value="<?php echo esc_attr( $type_key ); ?>" <?php selected( $test_type, $type_key ); ?>>
									<?php echo esc_html( $type_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description" id="cfw_ab_test_description">
							<?php if ( $test_type ) : ?>
								<?php echo esc_html( $test->get_description() ); ?>
							<?php endif; ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="cfw_ab_test_status"><?php esc_html_e( 'Status', 'checkout-wc' ); ?></label>
					</th>
					<td>
						<?php if ( $is_complete_with_winner ) : ?>
							<input type="hidden" name="cfw_ab_test_status" value="<?php echo esc_attr( $test_status ); ?>" />
						<?php endif; ?>
						<select name="cfw_ab_test_status" id="cfw_ab_test_status" style="width: 50%;" <?php echo $is_complete_with_winner ? 'disabled' : 'required'; ?>>
							<option value="active" <?php selected( $test_status, 'active' ); ?>><?php esc_html_e( 'Active', 'checkout-wc' ); ?></option>
							<option value="paused" <?php selected( $test_status, 'paused' ); ?>><?php esc_html_e( 'Paused', 'checkout-wc' ); ?></option>
							<option value="complete" <?php selected( $test_status, 'complete' ); ?>><?php esc_html_e( 'Complete', 'checkout-wc' ); ?></option>
						</select>
						<p class="description">
							<?php esc_html_e( 'The current status of this A/B test.', 'checkout-wc' ); ?>
						</p>
						<?php
						// Delegate status notices to UI provider
						if ( $test instanceof ABTestUIProvidable && $test->has_ui_provider() ) {
							$ui_provider = $test->get_ui_provider();
							$ui_provider->render_status_notices( $post );
						}
						?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="cfw_ab_test_date_from"><?php esc_html_e( 'Start Date', 'checkout-wc' ); ?></label>
					</th>
					<td>
						<input
							type="date"
							id="cfw_ab_test_date_from"
							name="cfw_ab_test_date_from"
							value="<?php echo esc_attr( $date_from_display ); ?>"
							class="regular-text"
							<?php echo $is_complete_with_winner ? 'readonly' : ''; ?>
						/>
						<p class="description">
							<?php esc_html_e( 'The date when the A/B test should start. Leave empty to start immediately.', 'checkout-wc' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="cfw_ab_test_date_to"><?php esc_html_e( 'End Date', 'checkout-wc' ); ?></label>
					</th>
					<td>
						<input
							type="date"
							id="cfw_ab_test_date_to"
							name="cfw_ab_test_date_to"
							value="<?php echo esc_attr( $date_to_display ); ?>"
							class="regular-text"
							<?php echo $is_complete_with_winner ? 'readonly' : ''; ?>
						/>
						<p class="description">
							<?php esc_html_e( 'The date when the A/B test should end. After this date, the original variant will be displayed. Leave empty for no end date.', 'checkout-wc' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			// Update the test type description when the test type is changed
			var $typeSelect = $('#cfw_ab_test_type');
			var $description = $('#cfw_ab_test_description');
			function updateDescription() {
				var selectedOption = $typeSelect.find('option:selected');
				var description = selectedOption.data('description') || '';
				$description.text(description);
			}
			$typeSelect.on('change', updateDescription);
		});
		</script>
		<?php
	}

	/**
	 * Save meta boxes
	 *
	 * @param int     $post_id The post ID.
	 * @param mixed   $post The post object (when available).
	 * @return void
	 */
	public function save_meta_boxes( $post_id, $post ) {
		$post_id = (int) $post_id;

		if ( ! $post instanceof WP_Post ) {
			$post = get_post( $post_id );
		}

		if ( ! $post instanceof WP_Post ) {
			return;
		}

		if ( ! $this->can_save_metaboxes( $post_id, $post ) ) {
			return;
		}

		$test       = ABTestFactory::get( $post_id );
		$old_status = $this->get_current_status( $test );

		$this->save_test_type( $post_id );
		$this->save_test_status( $post_id );
		$this->maybe_pause_for_visibility( $post_id, $post );
		$this->save_duration_dates( $post_id );
		$this->maybe_complete_expired_test( $post_id );
		$this->delegate_to_ui_provider( $post_id, $post, $test );
		$this->handle_status_changes( $post_id, $test, $old_status );
	}

	/**
	 * Check if metaboxes can be saved
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post object.
	 * @return bool
	 */
	private function can_save_metaboxes( int $post_id, WP_Post $post ): bool {
		// Check if this is an autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}

		// Check post type
		if ( ABTesting::get_post_type() !== $post->post_type ) {
			return false;
		}

		// Check nonce
		if ( ! isset( $_POST['cfw_ab_test_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['cfw_ab_test_meta_box_nonce'], 'cfw_ab_test_meta_box' ) ) {
			return false;
		}

		// Check permissions
		return current_user_can( 'cfw_manage_ab_tests', $post_id );
	}

	/**
	 * Get current test status
	 *
	 * @param ABTestInterface $test Test object.
	 * @return string
	 */
	private function get_current_status( $test ): string {
		$status = $test->get_status();
		return empty( $status ) ? 'active' : $status;
	}

	/**
	 * Save test type
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function save_test_type( int $post_id ): void {
		if ( ! empty( $_POST['cfw_ab_test_type'] ) ) {
			update_post_meta( $post_id, 'cfw_ab_test_type', sanitize_text_field( $_POST['cfw_ab_test_type'] ) );
		}
	}

	/**
	 * Save test status
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function save_test_status( int $post_id ): void {
		$valid_statuses = [ 'active', 'paused', 'complete' ];
		$new_status     = isset( $_POST['cfw_ab_test_status'] ) ? sanitize_text_field( $_POST['cfw_ab_test_status'] ) : 'active';

		if ( ! in_array( $new_status, $valid_statuses, true ) ) {
			$new_status = 'active';
		}

		update_post_meta( $post_id, 'cfw_ab_test_status', $new_status );
	}

	/**
	 * Pause test if post is not publicly visible
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	private function maybe_pause_for_visibility( int $post_id, WP_Post $post ): void {
		// If post status is not publish, or post has a password (not public), pause the test
		if ( 'publish' !== $post->post_status || ! empty( $post->post_password ) ) {
			update_post_meta( $post_id, 'cfw_ab_test_status', 'paused' );
		}
	}

	/**
	 * Save duration dates
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function save_duration_dates( int $post_id ): void {
		// Save from date
		if ( isset( $_POST['cfw_ab_test_date_from'] ) && ! empty( $_POST['cfw_ab_test_date_from'] ) ) {
			$date_from = sanitize_text_field( $_POST['cfw_ab_test_date_from'] );
			// Store as YYYY-MM-DD 00:00:00 for consistent comparison
			update_post_meta( $post_id, 'cfw_ab_test_date_from', $date_from . ' 00:00:00' );
		} else {
			delete_post_meta( $post_id, 'cfw_ab_test_date_from' );
		}

		// Save to date
		if ( isset( $_POST['cfw_ab_test_date_to'] ) && ! empty( $_POST['cfw_ab_test_date_to'] ) ) {
			$date_to = sanitize_text_field( $_POST['cfw_ab_test_date_to'] );
			// Store as YYYY-MM-DD 23:59:59 for end of day comparison
			update_post_meta( $post_id, 'cfw_ab_test_date_to', $date_to . ' 23:59:59' );
		} else {
			delete_post_meta( $post_id, 'cfw_ab_test_date_to' );
		}
	}

	/**
	 * Complete test if end date has passed
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function maybe_complete_expired_test( int $post_id ): void {
		$date_to = get_post_meta( $post_id, 'cfw_ab_test_date_to', true );

		if ( empty( $date_to ) ) {
			return;
		}

		$now = current_time( 'mysql' );
		if ( strtotime( $now ) > strtotime( $date_to ) ) {
			// End date has passed, set status to complete
			update_post_meta( $post_id, 'cfw_ab_test_status', 'complete' );
		}
	}

	/**
	 * Delegate saving to UI provider
	 *
	 * @param int             $post_id Post ID.
	 * @param WP_Post         $post Post object.
	 * @param ABTestInterface $test Test object.
	 * @return void
	 */
	private function delegate_to_ui_provider( int $post_id, WP_Post $post, $test ): void {
		if ( ! $test instanceof ABTestUIProvidable || ! $test->has_ui_provider() ) {
			return;
		}

		$ui_provider = $test->get_ui_provider();
		if ( ! $ui_provider ) {
			return;
		}

		$metaboxes = $ui_provider->get_metaboxes();
		foreach ( $metaboxes as $metabox_id => $metabox_config ) {
			$ui_provider->save_metabox( $metabox_id, $post_id, $post );
		}
	}

	/**
	 * Handle status changes
	 *
	 * @param int             $post_id Post ID.
	 * @param ABTestInterface $test Test object.
	 * @param string          $old_status Old status.
	 * @return void
	 */
	private function handle_status_changes( int $post_id, $test, string $old_status ): void {
		// Reload test to get final status after all saves
		$test->load();
		$final_status = $test->get_status();

		// Handle status change via UI provider
		if ( 'complete' === $old_status && 'complete' !== $final_status ) {
			if ( $test instanceof ABTestUIProvidable && $test->has_ui_provider() ) {
				$ui_provider = $test->get_ui_provider();
				$ui_provider->handle_status_change( $post_id, $old_status, $final_status );
			}
		}
	}

	/**
	 * Auto-create draft post when loading new A/B test page
	 *
	 * This is used to ensure the post exists fully for variants to be added to.
	 *
	 * @return void
	 */
	public function auto_create_draft_ab_test() {
		if ( ! isset( $_GET['post_type'] ) || $_GET['post_type'] !== ABTesting::get_post_type() ) {
			return;
		}

		// Check user capabilities
		if ( ! current_user_can( 'cfw_manage_ab_tests' ) ) {
			return;
		}

		// Create draft post immediately
		$post_id = wp_insert_post(
			[
				'post_type'   => ABTesting::get_post_type(),
				'post_status' => 'draft',
				'post_title'  => '',
			]
		);

		if ( $post_id && ! is_wp_error( $post_id ) ) {
			// Set default test type so UI appears immediately
			$test_types = ABTestTypeRegistry::get_all_types();
			if ( ! empty( $test_types ) ) {
				$default_type = reset( $test_types ); // Get first available type
				update_post_meta( $post_id, 'cfw_ab_test_type', $default_type );
			}

			// Redirect to edit screen WITH the ID
			wp_safe_redirect( admin_url( "post.php?post={$post_id}&action=edit" ) );
			exit;
		}
	}

	/**
	 * Enqueue admin scripts for A/B test editor
	 *
	 * @return void
	 */
	public function enqueue_ab_test_admin_scripts() {
		global $post;

		if ( ! isset( $post ) || ABTesting::get_post_type() !== $post->post_type ) {
			return;
		}

		// Enqueue WooCommerce admin scripts which include SelectWoo
		wp_enqueue_script( 'wc-enhanced-select' );
		wp_enqueue_style( 'woocommerce_admin_styles' );

		// Enqueue thickbox for modal
		add_thickbox();

		// Delegate to UI provider for test-specific scripts and styles
		$test = ABTestFactory::get( $post->ID );
		if ( $test instanceof ABTestUIProvidable && $test->has_ui_provider() ) {
			$ui_provider = $test->get_ui_provider();

			// Enqueue admin styles
			$ui_provider->enqueue_admin_styles();

			// Enqueue variant management script
			add_action( 'admin_footer', [ $ui_provider, 'enqueue_variant_management_script' ], 999 );
		}
	}

	/**
	 * Show traffic split validation error notice
	 *
	 * @return void
	 */
	public function show_traffic_split_validation_error() {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Traffic split percentages must total 100%. Please adjust your percentages.', 'checkout-wc' ) . '</p></div>';
	}

	/**
	 * Delete all variants of an A/B test
	 *
	 * @param int $post_id The post ID being deleted.
	 * @return void
	 */
	public function delete_ab_test_variants( $post_id ) {
		$post = get_post( $post_id );

		// Only proceed if this is an A/B test post type
		if ( ! $post || ABTesting::get_post_type() !== $post->post_type ) {
			return;
		}

		// Delegate variant deletion to UI provider if available
		$test = ABTestFactory::get( $post_id );
		if ( $test instanceof ABTestUIProvidable && $test->has_ui_provider() ) {
			$ui_provider = $test->get_ui_provider();
			$ui_provider->delete_test_variants( $post_id );
		}
	}
}
