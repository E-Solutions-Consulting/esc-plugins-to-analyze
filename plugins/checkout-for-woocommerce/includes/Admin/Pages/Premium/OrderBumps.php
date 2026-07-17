<?php

namespace Objectiv\Plugins\Checkout\Admin\Pages\Premium;

use Objectiv\Plugins\Checkout\Admin\Pages\PageAbstract;
use Objectiv\Plugins\Checkout\Admin\TabNavigation;
use Objectiv\Plugins\Checkout\Admin\Pages\Traits\TabbedAdminPageTrait;
use Objectiv\Plugins\Checkout\Factories\BumpFactory;
use Objectiv\Plugins\Checkout\Features\ABTesting;
use Objectiv\Plugins\Checkout\Managers\PlanManager;
use Objectiv\Plugins\Checkout\Managers\SettingsManager;
use Objectiv\Plugins\Checkout\Managers\StyleManager;
use Objectiv\Plugins\Checkout\Model\Bumps\BumpAbstract;

/**
 * @link checkoutwc.com
 * @since 5.0.0
 * @package Objectiv\Plugins\Checkout\Admin\Pages
 */
class OrderBumps extends PageAbstract {
	use TabbedAdminPageTrait;

	protected $post_type_slug;
	protected $formatted_required_plans_list;
	protected $is_available;

	/**
	 * Track order bump IDs being deleted to prevent circular deletion loops
	 *
	 * @var array
	 */
	private static $deleting_order_bumps = [];

	/**
	 * Check if an order bump is currently being deleted
	 *
	 * @param int $order_bump_id The order bump ID to check.
	 * @return bool
	 */
	public static function is_deleting_order_bump( $order_bump_id ) {
		return in_array( (int) $order_bump_id, self::$deleting_order_bumps, true );
	}

	public function __construct( string $post_type_slug, string $formatted_required_plans_list, bool $is_available ) {
		parent::__construct( __( 'Order Bumps', 'checkout-wc' ), 'cfw_manage_order_bumps', 'order_bumps' );

		$this->post_type_slug                = $post_type_slug;
		$this->formatted_required_plans_list = $formatted_required_plans_list;
		$this->is_available                  = $is_available;
	}

	public function init() {
		parent::init();

		$this->set_tabbed_navigation( new TabNavigation( 'settings' ) );

		$this->get_tabbed_navigation()->add_tab( __( 'Settings', 'checkout-wc' ), add_query_arg( [ 'subpage' => 'settings' ], $this->get_url() ) );
		$this->get_tabbed_navigation()->add_tab(
			__( 'Manage Bumps', 'checkout-wc' ),
			add_query_arg(
				[
					'post_type' => $this->post_type_slug,
				],
				admin_url( 'edit.php' )
			),
			'manage-bumps'
		);

		// Filter post status counts to exclude variants from All/Published (but include in Trash)
		add_filter( "views_edit-{$this->post_type_slug}", [ $this, 'filter_post_status_counts' ] );

		// Exclude variants from main list
		add_action( 'pre_get_posts', [ $this, 'exclude_variants_from_main_list' ] );

		add_action( 'all_admin_notices', [ $this, 'output_post_type_editor_header' ] );

		add_filter( 'wp_insert_post_data', [ $this, 'maybe_prevent_post_publication' ], '99', 2 );
		add_action( 'admin_notices', [ $this, 'maybe_show_post_pending_notice' ] );
		add_filter( 'replace_editor', [ $this, 'replace_editor' ], 10, 2 );

		if ( isset( $_GET['post_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			add_action( 'all_admin_notices', [ $this, 'maybe_show_license_upgrade_splash' ] );
		}

		// Reset bump statistics
		add_filter( 'post_row_actions', [ $this, 'add_reset_link' ], 10, 2 );
		add_action( 'admin_init', [ $this, 'maybe_reset_bump_stats' ] );

		// Trash variants when order bump is trashed
		add_action( 'wp_trash_post', [ $this, 'trash_order_bump_variants' ] );

		// Restore variants when order bump is restored from trash
		add_action( 'untrash_post', [ $this, 'restore_order_bump_variants' ] );

		// Delete variants when order bump is permanently deleted
		add_action( 'before_delete_post', [ $this, 'delete_order_bump_variants' ] );

		// Pause A/B tests when order bump status changes to draft
		add_action( 'transition_post_status', [ $this, 'pause_ab_tests_on_order_bump_draft' ], 10, 3 );

		/**
		 * Highlights Order Bumps submenu item when
		 * on the New Order Bumps admin page
		 */
		add_filter( 'submenu_file', [ $this, 'maybe_highlight_order_bumps_submenu_item' ] );

		/**
		 * Highlight parent menu
		 */
		add_filter( 'parent_file', [ $this, 'menu_highlight' ] );

		/**
		 * Highlight Manage Bumps tab
		 */
		add_filter( 'cfw_selected_tab', [ $this, 'maybe_set_manage_bumps_tab' ] );

		$post_type = $this->post_type_slug;

		add_filter(
			"manage_{$post_type}_posts_columns",
			function ( $columns ) {
				$date = array_pop( $columns );

				$columns['order_bump_id']   = __( 'ID', 'checkout-wc' );
				$columns['conversion_rate'] = __( 'Conversion Rate', 'checkout-wc' ) . wc_help_tip( __( 'Conversion Rate tracks how often a bump is added to an actual completed purchase. If 20 orders are placed and a bump was displayed on 10 of those orders and the bump was purchased 5 times, the conversion rate is 50%.', 'checkout-wc' ) );
				$columns['revenue']         = __( 'Revenue', 'checkout-wc' ) . wc_help_tip( __( 'The additional revenue that an Order Bump has captured. When configured as an upsell, it calculates the relative value between the offer product and the product being replaced. Revenues incurred before version 6.1.4 are estimated.', 'checkout-wc' ) );
				$columns['location']        = __( 'Location', 'checkout-wc' );
				$columns['offer_product']   = __( 'Offer Product', 'checkout-wc' );

				// Add A/B Test column only if A/B testing is enabled
				if ( SettingsManager::instance()->get_setting( 'enable_ab_testing' ) === 'yes' ) {
					$columns['ab_test'] = __( 'A/B Test', 'checkout-wc' );
				}

				$columns['date'] = $date;

				return $columns;
			}
		);

		add_action(
			"manage_{$post_type}_posts_custom_column",
			function ( $column, $post_id ) {
				$bump = BumpFactory::get( $post_id );

				if ( 'conversion_rate' === $column ) {
					echo esc_html( $bump->get_conversion_rate() );
				}

				if ( 'revenue' === $column ) {
					$captured_revenue = $bump->get_captured_revenue();

					echo wp_kses_post( 0.0 === $captured_revenue ? '--' : wc_price( $captured_revenue ) );
				}

				if ( 'location' === $column ) {
					$display_location = $bump->get_display_location();

					if ( 'complete_order' === $display_location ) {
						$display_location = __( 'Place Order Click (Checkout)', 'checkout-wc' );
					} elseif ( 'post_purchase_one_click' === $display_location ) {
						$display_location = __( 'Post Purchase One-Click (Thank You)', 'checkout-wc' );
					}

					echo esc_html( self::convert_value_to_label( $display_location ) );
				}

				if ( 'offer_product' === $column ) {
					echo $bump->get_offer_product() ? wp_kses_post( $bump->get_offer_product()->get_title() ) : '';
				}

				if ( 'order_bump_id' === $column ) {
					echo absint( $post_id );
				}

				if ( 'ab_test' === $column ) {
					// Only show if A/B testing is enabled
					if ( SettingsManager::instance()->get_setting( 'enable_ab_testing' ) !== 'yes' ) {
						return;
					}

					// Check if this is a parent order bump (not a variant)
					$post = get_post( $post_id );
					if ( ! $post || $post->post_parent > 0 ) {
						return;
					}

					// Find A/B test for this order bump
					$ab_tests = get_posts(
						[
							'post_type'      => ABTesting::get_post_type(),
							'post_status'    => 'any',
							'posts_per_page' => 1,
							'fields'         => 'ids',
							'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
								[
									'key'   => 'cfw_ab_test_order_bump',
									'value' => $post_id,
								],
							],
						]
					);

					if ( ! empty( $ab_tests ) ) {
						$test_id  = $ab_tests[0];
						$edit_url = get_edit_post_link( $test_id );
						if ( $edit_url ) {
							echo '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'View Test', 'checkout-wc' ) . '</a>';
						}
					} else {
						$create_url = add_query_arg(
							[
								'post_type'         => ABTesting::get_post_type(),
								'cfw_order_bump_id' => $post_id,
							],
							admin_url( 'post-new.php' )
						);
						echo '<a href="' . esc_url( $create_url ) . '">' . esc_html__( 'Add Test', 'checkout-wc' ) . '</a>';
					}
				}
			},
			10,
			2
		);

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_order_bump_editor_script' ], 1001 );

		add_action(
			'init',
			function () {
				if ( get_post_type() !== $this->post_type_slug ) {
					return;
				}
				register_block_type(
				'cfw/order-bump-preview',
				[
					'supports' => [
						'multiple' => false,
					],
				]
				);
			}
		);

		add_filter(
			"rest_prepare_{$post_type}",
			[
				$this,
				'maybe_add_order_bump_preview_block_to_editor',
			],
			10,
			1
		);
	}

	public static function convert_value_to_label( $value ): string {
		$value = str_replace( '_', ' ', $value );

		return ucwords( $value );
	}

	/**
	 * Filter post status counts to exclude variants from All/Published (but include in Trash)
	 *
	 * @param array $views The views array.
	 * @return array
	 */
	public function filter_post_status_counts( $views ) {
		global $wpdb;

		$post_type = $this->post_type_slug;

		// Get counts for all posts
		$counts = (array) wp_count_posts( $post_type );

		// Count variants per status to subtract (excluding trash)
		$variants_by_status = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_status, COUNT(*) as count FROM {$wpdb->posts} WHERE post_type = %s AND post_parent > 0 AND post_status != 'trash' GROUP BY post_status",
				$post_type
			),
			ARRAY_A
		);

		// Subtract variants from each status count (except trash)
		foreach ( $variants_by_status as $variant_data ) {
			$status        = $variant_data['post_status'];
			$variant_count = (int) $variant_data['count'];

			if ( isset( $counts[ $status ] ) ) {
				$counts[ $status ] = max( 0, $counts[ $status ] - $variant_count );
			}
		}

		// Rebuild views with updated counts
		$statuses        = get_post_stati( [ 'show_in_admin_status_list' => true ], 'objects' );
		$statuses['all'] = (object) [ 'label_count' => _n_noop( 'All <span class="count">(%s)</span>', 'All <span class="count">(%s)</span>', 'checkout-wc' ) ];

		// Calculate "All" count (excluding trash and variants)
		$all_count = 0;
		foreach ( $counts as $status => $count ) {
			if ( 'auto-draft' !== $status && 'inherit' !== $status && 'trash' !== $status ) {
				$all_count += $count;
			}
		}
		$counts['all'] = $all_count;

		foreach ( $statuses as $status => $status_obj ) {
			if ( ! isset( $counts[ $status ] ) ) {
				continue;
			}

			$count = $counts[ $status ];

			if ( 0 === $count && 'all' !== $status ) {
				// Remove empty status views except 'all'
				unset( $views[ $status ] );
				continue;
			}

			$label = $status_obj->label_count;
			$url   = 'all' === $status ? admin_url( "edit.php?post_type={$post_type}" ) : admin_url( "edit.php?post_type={$post_type}&post_status={$status}" );

			$views[ $status ] = sprintf(
				'<a href="%s"%s>%s</a>',
				esc_url( $url ),
				( get_query_var( 'post_status' ) === $status || ( '' === get_query_var( 'post_status' ) && 'all' === $status ) ) ? ' class="current"' : '',
				sprintf( translate_nooped_plural( $label, $count ), number_format_i18n( $count ) )
			);
		}

		return $views;
	}

	/**
	 * Exclude variants from the main order bumps list
	 *
	 * @param \WP_Query $query The WP_Query object.
	 * @return void
	 */
	public function exclude_variants_from_main_list( $query ) {
		global $typenow;

		// Only filter on admin edit.php page for this post type
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( $this->post_type_slug !== $typenow ) {
			return;
		}

		$post_status       = isset( $_GET['post_status'] ) ? sanitize_text_field( $_GET['post_status'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$query_post_status = $query->get( 'post_status' );
		$is_trash_view     = 'trash' === $post_status || 'trash' === $query_post_status;

		// Exclude variants (posts with a parent) from the main list (but allow in trash)
		if ( ! $is_trash_view ) {
			$query->set( 'post_parent', 0 );
		}
	}

	/**
	 * The admin page wrap
	 *
	 * @since 1.0.0
	 */
	public function output_post_type_editor_header() {
		global $post;

		if ( isset( $_GET['post_type'] ) && $this->post_type_slug !== $_GET['post_type'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		} elseif ( isset( $post ) && $this->post_type_slug !== $post->post_type ) {
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
				 * @param OrderBumps $this The OrderBumps instance.
				 *
				 * @since 7.0.0
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
				 * @param AbandonedCartRecovery $this The AbandonedCartRecovery instance.
				 *
				 * @since 7.0.0
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

	public function get_url(): string {
		$page_slug = join( '-', array_filter( [ self::$parent_slug, 'order_bumps' ] ) );
		$url       = add_query_arg( 'page', $page_slug, admin_url( 'admin.php' ) );

		return esc_url( $url );
	}

	/**
	 * Keeps the submenu open when on the order bumps editor
	 *
	 * @return void
	 */
	public function setup_menu() {
		parent::setup_menu();

		global $submenu;

		$stash_menu_item = null;

		if ( empty( $submenu[ self::$parent_slug ] ) ) {
			return;
		}

		foreach ( (array) $submenu[ self::$parent_slug ] as $i => $item ) {
			if ( $this->slug === $item[2] ) {
				$stash_menu_item = $submenu[ self::$parent_slug ][ $i ];
				unset( $submenu[ self::$parent_slug ][ $i ] );
			}
		}

		if ( empty( $stash_menu_item ) ) {
			return;
		}

		$submenu[ self::$parent_slug ][ $this->priority ] = $stash_menu_item; // phpcs:ignore
	}

	public function add_reset_link( $actions, \WP_Post $post ) {
		if ( BumpAbstract::get_post_type() !== $post->post_type ) {
			return $actions;
		}

		$actions['reset_stats'] = sprintf(
			'<a href="%s" onclick="return confirm(\'Are you sure?\')">%s</a>',
			add_query_arg(
				[
					'cfw_action' => 'cfw_reset_stats',
					'post'       => $post->ID,
					'nonce'      => wp_create_nonce( 'cfw_reset_stats' ),
				]
			),
			__( 'Reset Order Bump Conversion Stats', 'checkout-wc' )
		);

		return $actions;
	}

	public function maybe_reset_bump_stats() {
		if ( ! isset( $_GET['cfw_action'] ) || 'cfw_reset_stats' !== $_GET['cfw_action'] ) {
			return;
		}

		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ?? '' ) ), 'cfw_reset_stats' ) ) {
			return;
		}

		$post_id = absint( $_GET['post'] ); // phpcs:ignore

		if ( ! $post_id ) {
			return;
		}

		$bump = BumpFactory::get( $post_id );

		if ( ! $bump ) {
			return;
		}

		delete_post_meta( $bump->get_id(), 'times_bump_displayed_on_purchases' );
		delete_post_meta( $bump->get_id(), 'times_bump_purchased' );
		delete_post_meta( $bump->get_id(), 'captured_revenue' );
		delete_post_meta( $bump->get_id(), 'conversion_rate' );

		wp_safe_redirect( admin_url( 'edit.php?post_type=' . BumpAbstract::get_post_type() ) );
		exit;
	}

	/**
	 * Trash all variants of an order bump
	 *
	 * @param int $post_id The post ID being trashed.
	 * @return void
	 */
	public function trash_order_bump_variants( $post_id ) {
		$post = get_post( $post_id );

		// Only proceed if this is an order bump post type
		if ( ! $post || BumpAbstract::get_post_type() !== $post->post_type ) {
			return;
		}

		// Only proceed if this is a parent order bump (not a variant itself)
		if ( $post->post_parent > 0 ) {
			return;
		}

		// Find all variants (posts with this order bump as parent, excluding already trashed)
		$variants = get_posts(
			[
				'post_type'      => BumpAbstract::get_post_type(),
				'post_parent'    => $post_id,
				'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
				'posts_per_page' => -1,
				'fields'         => 'ids',
			]
		);

		// Trash all variants
		foreach ( $variants as $variant_id ) {
			wp_trash_post( $variant_id );
		}

		// Pause associated A/B tests
		$this->pause_associated_ab_tests( $post_id );
	}

	/**
	 * Restore all variants of an order bump from trash
	 *
	 * @param int $post_id The post ID being restored.
	 * @return void
	 */
	public function restore_order_bump_variants( $post_id ) {
		$post = get_post( $post_id );

		// Only proceed if this is an order bump post type
		if ( ! $post || BumpAbstract::get_post_type() !== $post->post_type ) {
			return;
		}

		// Only proceed if this is a parent order bump (not a variant itself)
		if ( $post->post_parent > 0 ) {
			return;
		}

		// Find all trashed variants (posts with this order bump as parent)
		$variants = get_posts(
			[
				'post_type'      => BumpAbstract::get_post_type(),
				'post_parent'    => $post_id,
				'post_status'    => 'trash',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			]
		);

		// Restore all variants
		foreach ( $variants as $variant_id ) {
			wp_untrash_post( $variant_id );
		}
	}

	/**
	 * Pause A/B tests when order bump status changes to draft
	 *
	 * @param string   $new_status The new post status.
	 * @param string   $old_status The old post status.
	 * @param \WP_Post $post       The post object.
	 * @return void
	 */
	public function pause_ab_tests_on_order_bump_draft( $new_status, $old_status, $post ) {
		// Only proceed if transitioning to draft
		if ( 'draft' !== $new_status ) {
			return;
		}

		// Only proceed if this is an order bump post type
		if ( ! $post || BumpAbstract::get_post_type() !== $post->post_type ) {
			return;
		}

		// Only proceed if this is a parent order bump (not a variant itself)
		if ( $post->post_parent > 0 ) {
			return;
		}

		// Pause associated A/B tests
		$this->pause_associated_ab_tests( $post->ID );
	}

	/**
	 * Pause associated A/B tests of an order bump
	 *
	 * @param int $order_bump_id The order bump ID.
	 * @return void
	 */
	private function pause_associated_ab_tests( $order_bump_id ) {
		// Find A/B tests associated with this order bump
		$ab_tests = get_posts(
			[
				'post_type'      => ABTesting::get_post_type(),
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					[
						'key'   => 'cfw_ab_test_order_bump',
						'value' => $order_bump_id,
					],
				],
			]
		);

		// Pause each A/B test (unless it's already complete)
		foreach ( $ab_tests as $ab_test_id ) {
			$test_status = get_post_meta( $ab_test_id, 'cfw_ab_test_status', true );
			if ( 'complete' !== $test_status ) {
				update_post_meta( $ab_test_id, 'cfw_ab_test_status', 'paused' );
			}
		}
	}

	/**
	 * Delete associated A/B tests permanently
	 *
	 * @param int $order_bump_id The order bump ID.
	 * @return void
	 */
	private function delete_associated_ab_tests( $order_bump_id ) {
		$ab_tests = get_posts(
			[
				'post_type'      => ABTesting::get_post_type(),
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					[
						'key'   => 'cfw_ab_test_order_bump',
						'value' => $order_bump_id,
					],
				],
			]
		);

		// Delete each A/B test permanently
		foreach ( $ab_tests as $ab_test_id ) {
			wp_delete_post( $ab_test_id, true );
		}
	}

	/**
	 * Delete all variants of an order bump
	 *
	 * @param int $post_id The post ID being deleted.
	 * @return void
	 */
	public function delete_order_bump_variants( $post_id ) {
		$post = get_post( $post_id );

		// Only proceed if this is an order bump post type
		if ( ! $post || BumpAbstract::get_post_type() !== $post->post_type ) {
			return;
		}

		// Only proceed if this is a parent order bump (not a variant itself)
		if ( $post->post_parent > 0 ) {
			return;
		}

		// Prevent circular deletion - mark this order bump as being deleted
		if ( in_array( $post_id, self::$deleting_order_bumps, true ) ) {
			return;
		}
		self::$deleting_order_bumps[] = $post_id;

		// Find all variants (posts with this order bump as parent, including trashed ones)
		$variants = get_posts(
			[
				'post_type'      => BumpAbstract::get_post_type(),
				'post_parent'    => $post_id,
				'post_status'    => [ 'publish', 'draft', 'pending', 'private', 'trash' ],
				'posts_per_page' => -1,
				'fields'         => 'ids',
			]
		);

		// Delete all variants permanently (including trashed ones)
		foreach ( $variants as $variant_id ) {
			wp_delete_post( $variant_id, true );
		}

		// Delete associated A/B tests permanently
		$this->delete_associated_ab_tests( $post_id );

		// Remove from tracking array after deletion is complete
		$key = array_search( $post_id, self::$deleting_order_bumps, true );
		if ( false !== $key ) {
			unset( self::$deleting_order_bumps[ $key ] );
		}
	}

	public function is_current_page(): bool {
		global $post;

		if ( parent::is_current_page() ) {
			return true;
		}

		if ( isset( $_GET['post_type'] ) && $this->post_type_slug === $_GET['post_type'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}

		if ( $post && $this->post_type_slug === $post->post_type ) {
			return true;
		}

		return false;
	}

	public function maybe_show_license_upgrade_splash() {
		if ( $this->is_current_page() && ! $this->is_available ) {
			echo wp_kses( $this->get_old_style_upgrade_required_notice( $this->formatted_required_plans_list ), cfw_get_allowed_html() );
		}
	}

	/**
	 * @param mixed $submenu_file The submenu file.
	 *
	 * @return mixed
	 */
	public function maybe_highlight_order_bumps_submenu_item( $submenu_file ) {
		global $post;

		$post_type = $this->post_type_slug;

		if ( isset( $_GET['post_type'] ) && $_GET['post_type'] === $post_type ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $this->get_slug();
		} elseif ( $post && $post->post_type === $post_type ) {
			return $this->get_slug();
		}

		return $submenu_file;
	}

	public function menu_highlight( $parent_file ) {
		global $plugin_page, $post_type;

		if ( $this->post_type_slug === $post_type ) {
			$plugin_page = PageAbstract::$parent_slug; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		return $parent_file;
	}

	public function output() {
		if ( ! empty( $notice ) ) {
			echo wp_kses( $notice, cfw_get_allowed_html() );
		}

		if ( isset( $_GET['post_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$current_tab_function = $this->get_tabbed_navigation()->get_current_tab() . '_tab';
		$callable             = [ $this, $current_tab_function ];

		$this->get_tabbed_navigation()->display_tabs();

		call_user_func( $callable );
	}

	public function settings_tab() {
		?>
		<div id="cfw-admin-pages-order-bumps"></div>
		<?php
	}

	public function maybe_set_script_data() {
		if ( ! $this->is_current_page() || 'settings' !== $this->get_tabbed_navigation()->get_current_tab() ) {
			return;
		}

		$this->set_script_data(
			[
				'settings' => [
					'enable_order_bumps'       => SettingsManager::instance()->get_setting( 'enable_order_bumps' ) === 'yes',
					'max_bumps'                => SettingsManager::instance()->get_setting( 'max_bumps' ),
					'max_after_checkout_bumps' => SettingsManager::instance()->get_setting( 'max_after_checkout_bumps' ),
				],
				'plan'     => $this->get_plan_data(),
			]
		);
	}

	public function maybe_prevent_post_publication( $data, $post ) {
		if ( BumpAbstract::get_post_type() !== $post['post_type'] ) {
			return $data;
		}

		// If post create date is before March 14, 2024 then allow publishing
		if ( strtotime( $post['post_date'] ) < strtotime( '2024-03-14' ) ) {
			return $data;
		}

		$override = cfw_apply_filters( 'cfw_restricted_post_types_publish_override', false, $post );

		if ( $override ) {
			return $data;
		}

		$current_post_status = get_post_status( $post['ID'] );

		if ( self::get_bumps_count() >= self::get_allowed_bump_count() && 'publish' !== $current_post_status && 'publish' === $data['post_status'] ) {
			// Change post status back to original status
			$data['post_status'] = $current_post_status;

			// set a transient to show the admin notice
			set_transient( 'cfw_order_bumps_publish_notice', true, 20 );
		}

		return $data;
	}

	public function maybe_show_post_pending_notice() {
		if ( ! get_transient( 'cfw_order_bumps_publish_notice' ) ) {
			return;
		}

		$this->maybe_show_upgrade_notice();

		// delete the transient so we only show it once
		delete_transient( 'cfw_order_bumps_publish_notice' );
	}

	/**
	 * Set the manage bumps tab as active when on order bump post type pages
	 *
	 * @param string $selected_tab The currently selected tab.
	 * @return string
	 */
	public function maybe_set_manage_bumps_tab( $selected_tab ) {
		global $post;

		$post_type = BumpAbstract::get_post_type();

		// Check if we're on any order bump related page
		if ( isset( $_GET['post_type'] ) && $_GET['post_type'] === $post_type ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return 'manage-bumps';
		} elseif ( $post && $post->post_type === $post_type ) {
			return 'manage-bumps';
		}

		return $selected_tab;
	}

	public function replace_editor( $replace, $post ) {
		if ( BumpAbstract::get_post_type() !== $post->post_type ) {
			return $replace;
		}

		if ( ! self::is_post_new_screen() ) {
			return $replace;
		}

		$override = cfw_apply_filters( 'cfw_restricted_post_types_publish_override', false, $post );

		if ( $override ) {
			return $replace;
		}

		if ( self::get_bumps_count() >= self::get_allowed_bump_count() ) {
			require_once ABSPATH . 'wp-admin/admin-header.php';
			$this->maybe_show_upgrade_notice();

			return true;
		}

		return $replace;
	}

	public static function get_allowed_bump_count(): int {
		/**
		 * Limits
		 * - Basic: 0
		 * - Plus: 2
		 * - Pro and Agency: Unlimited
		 */

		$limit = 0;

		if ( PlanManager::has_premium_plan_or_higher( 'plus' ) ) {
			$limit = 2;
		}

		if ( PlanManager::has_premium_plan_or_higher( 'pro' ) ) {
			$limit = 1000;
		}

		return $limit;
	}

	public static function get_bumps_count(): int {
		$args = [
			'post_type'      => BumpAbstract::get_post_type(),
			'posts_per_page' => - 1,
			'post_status'    => [ 'publish' ],
		];

		/**
		 * Filters the arguments for the bumps count query
		 *
		 * @param array $args The arguments for the bumps count query
		 * @since 8.0.0
		 */
		$args = apply_filters( 'cfw_restricted_post_types_count_args', $args );

		$bumps = new \WP_Query( $args );

		return $bumps->post_count;
	}

	public static function is_post_new_screen(): bool {
		global $pagenow, $typenow;

		if ( 'post-new.php' === $pagenow && BumpAbstract::get_post_type() === $typenow ) {
			return true;
		}

		return false;
	}

	public function maybe_show_upgrade_notice() {
		?>
		<div class='cfw-license-upgrade-blocker-og cfw-tw'>
			<div class="inner text-base">
				<h3 class="text-xl font-bold mb-4">
					<?php _e( 'Upgrade Your Plan', 'checkout-wc' ); ?>
				</h3>

				<?php echo esc_html( sprintf( /* translators: %1$d: Allowed bump count, %2$d: Used bump count */ __( 'Your CheckoutWC plan allows you to create %1$d Order Bumps. You have used %2$d.', 'checkout-wc' ), self::get_allowed_bump_count(), self::get_bumps_count() ) ); ?>

				<p class="text-base italic mt-2 mb-2">
					<?php _e( 'You cannot create or publish new Order Bumps if you are over the limit.', 'checkout-wc' ); ?>
				</p>

				<p class="text-base">
					<?php echo wp_kses_post( sprintf( __( 'You can upgrade your license in <a class="text-blue-600 underline" target="_blank" href="%1$s">Account</a>. For help upgrading your license, <a class="text-blue-600 underline" target="_blank" href="%2$s">click here.</a>', 'checkout-wc' ), 'https://www.checkoutwc.com/account/', 'https://kb.checkoutwc.com/article/53-upgrading-your-license' ) ); ?>
				</p>
			</div>
		</div>
		<?php
	}

	public function enqueue_order_bump_editor_script() {
		global $post;

		if ( ! isset( $post ) || $this->post_type_slug !== $post->post_type ) {
			return;
		}

		remove_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ], 1001 );

		cfw_register_scripts( [ 'admin-order-bumps-editor' ] );
		wp_enqueue_script( 'cfw-admin-order-bumps-editor' );

		// Add CSS custom properties for button styling in preview
		StyleManager::add_styles( 'objectiv-cfw-admin-styles' );

		// Add plan data to script
		$script_data                       = $this->get_plan_data();
		$script_data['supportedGateways'] = array_keys( \Objectiv\Plugins\Checkout\Features\OneClick\GatewayRegistry::instance()->get_supported_gateways() );
		wp_localize_script( 'cfw-admin-order-bumps-editor', 'cfwOrderBumpsData', $script_data );
	}

	public function maybe_add_order_bump_preview_block_to_editor( $response ) {
		if ( ! isset( $response->data['content'] ) ) {
			return $response;
		}

		$content = $response->data['content']['raw'];

		if ( strpos( $content, '<!-- wp:cfw/order-bump-preview' ) === false ) {
			// Prepend the block to the content
			$content = '<!-- wp:cfw/order-bump-preview {"lock":{"move":true,"remove":true}} /-->' . $content;
		}

		$response->data['content']['raw'] = $content;

		return $response;
	}
}
