<?php

namespace Objectiv\Plugins\Checkout\Features\ABTesting\UIProviders;

use Objectiv\Plugins\Checkout\Interfaces\ABTestUIProviderInterface;
use Objectiv\Plugins\Checkout\Model\ABTests\OrderBumpsABTest;
use Objectiv\Plugins\Checkout\Model\ABTests\NullABTest;
use Objectiv\Plugins\Checkout\Model\Bumps\BumpAbstract;
use Objectiv\Plugins\Checkout\Factories\BumpFactory;
use Objectiv\Plugins\Checkout\Factories\ABTestFactory;
use Objectiv\Plugins\Checkout\Features\ABTesting;
use Objectiv\Plugins\Checkout\Managers\SettingsManager;
use WP_Post;

/**
 * UI Provider for Order Bump A/B Tests
 *
 * Handles all UI-related functionality for order bump A/B tests including
 * metaboxes, AJAX handlers, and admin scripts/styles.
 *
 * @link checkoutwc.com
 * @since 8.0.0
 * @package Objectiv\Plugins\Checkout\Features\ABTesting\UIProviders
 */
class OrderBumpABTestUIProvider implements ABTestUIProviderInterface {
	/**
	 * The test instance
	 *
	 * @var OrderBumpsABTest
	 */
	private OrderBumpsABTest $test;

	/**
	 * Constructor
	 *
	 * @param OrderBumpsABTest $test The test instance
	 */
	public function __construct( OrderBumpsABTest $test ) {
		$this->test = $test;
	}

	/**
	 * Get metabox configurations
	 *
	 * @return array
	 */
	public function get_metaboxes(): array {
		// Check if test is completed with winner - if so, don't show the variants meta box
		$test_status             = get_post_meta( $this->test->get_id(), 'cfw_ab_test_status', true );
		$winner_variant_id       = get_post_meta( $this->test->get_id(), 'cfw_ab_test_winner_variant_id', true );
		$is_complete_with_winner = ( 'complete' === $test_status && ! empty( $winner_variant_id ) );

		if ( $is_complete_with_winner ) {
			return array();
		}

		return array(
			'cfw_ab_test_order_bump_variants_meta_box' => array(
				'title'    => __( 'Variants', 'checkout-wc' ),
				'context'  => 'normal',
				'priority' => 'default',
			),
		);
	}

	/**
	 * Render a metabox
	 *
	 * @param string  $metabox_id The metabox ID
	 * @param WP_Post $post The post object
	 * @return void
	 */
	public function render_metabox( string $metabox_id, WP_Post $post ): void {
		switch ( $metabox_id ) {
			case 'cfw_ab_test_order_bump_variants_meta_box':
				$this->render_order_bump_variants_metabox( $post );
				break;
		}
	}

	/**
	 * Save a metabox
	 *
	 * @param string  $metabox_id The metabox ID
	 * @param int     $post_id The post ID
	 * @param WP_Post $post The post object
	 * @return void
	 */
	public function save_metabox( string $metabox_id, int $post_id, WP_Post $post ): void {
		switch ( $metabox_id ) {
			case 'cfw_ab_test_order_bump_variants_meta_box':
				$this->save_order_bump_variants( $post_id );
				break;
		}
	}

	/**
	 * Get AJAX handlers
	 *
	 * @return array
	 */
	public function get_ajax_handlers(): array {
		return array(
			'save_order_bump'   => 'handle_save_order_bump',
			'get_variants_grid' => 'handle_get_variants_grid',
			'delete_variant'    => 'handle_delete_variant',
			'create_variant'    => 'handle_create_variant',
			'select_winner'     => 'handle_select_winner',
		);
	}

	/**
	 * Handle AJAX requests
	 *
	 * @param string $action The action to handle
	 * @param array  $data The request data
	 * @return array
	 */
	public function handle_ajax( string $action, array $data ): array {
		$handlers = $this->get_ajax_handlers();

		if ( isset( $handlers[ $action ] ) && method_exists( $this, $handlers[ $action ] ) ) {
			return $this->{$handlers[ $action ]}( $data );
		}

		return array(
			'success' => false,
			'message' => __( 'Invalid action', 'checkout-wc' ),
		);
	}

	/**
	 * Enqueue admin scripts
	 *
	 * @return void
	 */
	public function enqueue_admin_scripts(): void {
		// Scripts will be enqueued by ABTestingAdmin
	}

	/**
	 * Enqueue admin styles
	 *
	 * @return void
	 */
	public function enqueue_admin_styles(): void {
		// Add inline styles for variant cards
		$this->output_variant_card_styles();
	}

	/**
	 * Render the order bump variants metabox
	 *
	 * @param WP_Post $post The post object
	 * @return void
	 */
	private function render_order_bump_variants_metabox( WP_Post $post ): void {
		$order_bump_id = get_post_meta( $post->ID, 'cfw_ab_test_order_bump', true );
		$order_bump_id = ! empty( $order_bump_id ) ? absint( $order_bump_id ) : '';

		// Get selected order bump for display (if one is selected)
		$selected_bump = $order_bump_id ? get_post( $order_bump_id ) : null;

		// Check if the previously selected order bump no longer exists, is trashed, or is not published
		$has_invalid_selected_bump = false;
		if ( $order_bump_id && ( ! $selected_bump || ! in_array( $selected_bump->post_status, array( 'publish' ), true ) ) ) {
			$has_invalid_selected_bump = true;
		}

		// Get selected variants
		$variants = get_post_meta( $post->ID, 'cfw_ab_test_order_bump_variants', true );
		if ( ! is_array( $variants ) ) {
			$variants = array();
		}

		// Check if test is complete with a winner selected
		$test_status = get_post_meta( $post->ID, 'cfw_ab_test_status', true );
		if ( empty( $test_status ) ) {
			$test_status = 'active'; // Default to active
		}
		$winner_variant_id       = get_post_meta( $post->ID, 'cfw_ab_test_winner_variant_id', true );
		$is_complete_with_winner = ( 'complete' === $test_status && ! empty( $winner_variant_id ) );

		// Make order bump readonly after initial save (not in auto-draft status) and once an order bump is set
		$is_order_bump_readonly = 'auto-draft' !== $post->post_status && ! empty( $order_bump_id );

		?>
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">
						<label for="cfw_ab_test_order_bump"><?php esc_html_e( 'Order Bump', 'checkout-wc' ); ?></label>
					</th>
					<td>
						<?php if ( $has_invalid_selected_bump ) : ?>
							<div class="notice notice-error inline" style="margin: 0 0 10px 0;">
								<p>
									<?php esc_html_e( 'The order bump originally configured for this A/B test is no longer available.', 'checkout-wc' ); ?>
									<?php esc_html_e( 'It is recommended you', 'checkout-wc' ); ?>
									<a href="#" id="cfw_delete_ab_test_link" class="cfw-delete-ab-test-link" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
										<?php esc_html_e( 'delete this A/B test', 'checkout-wc' ); ?>
									</a>.
								</p>
							</div>
							<script>
							jQuery(document).ready(function($) {
								$('#cfw_delete_ab_test_link').on('click', function(e) {
									e.preventDefault();
									var postId = $(this).data('post-id');
									if (confirm('<?php echo esc_js( __( 'Are you sure you want to delete this A/B test? This action cannot be undone.', 'checkout-wc' ) ); ?>')) {
										// Redirect to WordPress delete URL
										var deleteUrl = '<?php echo esc_js( get_delete_post_link( $post->ID, '', true ) ); ?>';
										window.location.href = deleteUrl;
									}
								});
							});
							</script>
						<?php else : ?>
							<?php if ( ( $is_complete_with_winner || $is_order_bump_readonly ) && ! empty( $order_bump_id ) ) : ?>
								<input type="hidden" name="cfw_ab_test_order_bump" value="<?php echo esc_attr( $order_bump_id ); ?>" />
							<?php endif; ?>
							<select name="cfw_ab_test_order_bump" id="cfw_ab_test_order_bump" class="wc-enhanced-select" data-selected="<?php echo esc_attr( $order_bump_id ); ?>" <?php echo ( $is_complete_with_winner || $is_order_bump_readonly ) ? 'disabled' : ''; ?>>
								<?php if ( $selected_bump ) : ?>
									<option value="<?php echo esc_attr( $selected_bump->ID ); ?>" selected>
										<?php
										$bump_title = trim( $selected_bump->post_title );
										if ( empty( $bump_title ) ) {
											$bump_title = sprintf( __( 'No Title #%d', 'checkout-wc' ), $selected_bump->ID );
										}
										echo esc_html( $bump_title );
										?>
									</option>
								<?php endif; ?>
							</select>
							<p class="description">
								<?php esc_html_e( 'Select the order bump to test variants against.', 'checkout-wc' ); ?>
							</p>
							<div id="cfw_ab_test_order_bump_notices"></div>
						<?php endif; ?>
					</td>
				</tr>
				<tr id="cfw_ab_test_order_bump_variants_row" style="display: <?php echo ( $order_bump_id && ! $has_invalid_selected_bump ) ? 'table-row' : 'none'; ?>;">
					<th scope="row">
						<label><?php esc_html_e( 'Variants', 'checkout-wc' ); ?></label>
					</th>
					<td>
						<div id="cfw_ab_test_variants_grid" class="cfw-variants-grid" data-test-id="<?php echo esc_attr( $post->ID ); ?>" data-order-bump-id="<?php echo esc_attr( $order_bump_id ); ?>">
							<?php if ( $order_bump_id && ! $has_invalid_selected_bump ) : ?>
								<?php echo $this->render_variants_grid( $order_bump_id, $post->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php elseif ( ! $order_bump_id ) : ?>
								<p class="description"><?php esc_html_e( 'Select an order bump to view and manage variants.', 'checkout-wc' ); ?></p>
							<?php endif; ?>
						</div>
						<?php if ( ! $has_invalid_selected_bump && 'complete' !== $test_status ) : ?>
							<p class="description">
								<?php esc_html_e( 'Manage variants for this A/B test. Click "Create Variant" to add a new variant.', 'checkout-wc' ); ?>
							</p>
						<?php endif; ?>
					</td>
				</tr>
				<?php
				// Get traffic split settings
				$traffic_split_type        = get_post_meta( $post->ID, 'cfw_ab_test_traffic_split_type', true );
				$traffic_split_type        = ! empty( $traffic_split_type ) ? $traffic_split_type : 'equal';
				$traffic_split_percentages = get_post_meta( $post->ID, 'cfw_ab_test_traffic_split_percentages', true );
				if ( ! is_array( $traffic_split_percentages ) ) {
					$traffic_split_percentages = array();
				}
				?>
				<tr id="cfw_ab_test_traffic_split_row" style="display: <?php echo ( $order_bump_id && ! empty( $variants ) && ! $has_invalid_selected_bump ) ? 'table-row' : 'none'; ?>;">
					<th scope="row">
						<label for="cfw_ab_test_traffic_split_type"><?php esc_html_e( 'Traffic Split', 'checkout-wc' ); ?></label>
					</th>
					<td>
						<select name="cfw_ab_test_traffic_split_type" id="cfw_ab_test_traffic_split_type" style="width: 200px;">
							<option value="equal" <?php selected( $traffic_split_type, 'equal' ); ?>><?php esc_html_e( 'Equal', 'checkout-wc' ); ?></option>
							<option value="custom" <?php selected( $traffic_split_type, 'custom' ); ?>><?php esc_html_e( 'Custom', 'checkout-wc' ); ?></option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Choose how to split traffic between the original order bump and variants. Equal splits traffic evenly, Custom allows you to set specific percentages.', 'checkout-wc' ); ?>
						</p>
					</td>
				</tr>
				<tr id="cfw_ab_test_traffic_split_custom_row" style="display: none;">
					<th scope="row">
						<label><?php esc_html_e( 'Traffic Percentages', 'checkout-wc' ); ?></label>
					</th>
					<td>
						<div id="cfw_ab_test_traffic_split_percentages" data-saved-percentages="<?php echo esc_attr( wp_json_encode( $traffic_split_percentages ) ); ?>"></div>
						<p class="description">
							<?php esc_html_e( 'Enter percentages for the original order bump and each variant. Total must equal 100%.', 'checkout-wc' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Save order bump variants
	 *
	 * @param int $post_id The post ID
	 * @return void
	 */
	private function save_order_bump_variants( int $post_id ): void {
		// Check if test is complete with winner - if so, preserve existing data
		$final_status            = isset( $_POST['cfw_ab_test_status'] ) && in_array( $_POST['cfw_ab_test_status'], array( 'active', 'paused', 'complete' ), true )
			? sanitize_text_field( $_POST['cfw_ab_test_status'] )
			: get_post_meta( $post_id, 'cfw_ab_test_status', true );
		$winner_variant_id       = get_post_meta( $post_id, 'cfw_ab_test_winner_variant_id', true );
		$is_complete_with_winner = ( 'complete' === $final_status && ! empty( $winner_variant_id ) );

		// If test is complete with winner, preserve existing order bump and variants data
		if ( $is_complete_with_winner ) {
			// Preserve existing order bump if not in POST (field might be disabled)
			$existing_order_bump = get_post_meta( $post_id, 'cfw_ab_test_order_bump', true );
			if ( ! empty( $existing_order_bump ) && ( ! isset( $_POST['cfw_ab_test_order_bump'] ) || empty( $_POST['cfw_ab_test_order_bump'] ) ) ) {
				// Keep existing order bump
				update_post_meta( $post_id, 'cfw_ab_test_order_bump', $existing_order_bump );
			} elseif ( isset( $_POST['cfw_ab_test_order_bump'] ) && ! empty( $_POST['cfw_ab_test_order_bump'] ) ) {
				$order_bump_id = absint( $_POST['cfw_ab_test_order_bump'] );
				update_post_meta( $post_id, 'cfw_ab_test_order_bump', $order_bump_id );
			}

			// Preserve existing variants data (variants are deleted when winner is set, so this will be empty array)
			$existing_variants = get_post_meta( $post_id, 'cfw_ab_test_order_bump_variants', true );
			if ( ! isset( $_POST['cfw_ab_test_order_bump'] ) || empty( $_POST['cfw_ab_test_order_bump'] ) ) {
				// Keep existing variants meta (empty array is expected when winner is set)
				if ( is_array( $existing_variants ) ) {
					update_post_meta( $post_id, 'cfw_ab_test_order_bump_variants', $existing_variants );
				}
			}

			// Preserve variants data meta - never delete this when winner is set
			$existing_variants_data = get_post_meta( $post_id, 'cfw_ab_test_variants_data', true );
			if ( ! empty( $existing_variants_data ) && is_array( $existing_variants_data ) ) {
				// Keep the saved variants data
				update_post_meta( $post_id, 'cfw_ab_test_variants_data', $existing_variants_data );
			}
		} else {
			// Normal save process when test is not complete with winner
			if ( isset( $_POST['cfw_ab_test_order_bump'] ) && ! empty( $_POST['cfw_ab_test_order_bump'] ) ) {
				$order_bump_id = absint( $_POST['cfw_ab_test_order_bump'] );
				update_post_meta( $post_id, 'cfw_ab_test_order_bump', $order_bump_id );
			} else {
				// Clear order bump if not set
				delete_post_meta( $post_id, 'cfw_ab_test_order_bump' );
			}

			// Save variants - automatically get all variants for the selected order bump
			if ( isset( $_POST['cfw_ab_test_order_bump'] ) && ! empty( $_POST['cfw_ab_test_order_bump'] ) ) {
				$order_bump_id = absint( $_POST['cfw_ab_test_order_bump'] );
				// Get all variants for this order bump
				$all_variants = get_posts(
					array(
						'post_type'      => BumpAbstract::get_post_type(),
						'post_parent'    => $order_bump_id,
						'post_status'    => array( 'publish', 'draft' ),
						'posts_per_page' => -1,
						'fields'         => 'ids',
					)
				);
				update_post_meta( $post_id, 'cfw_ab_test_order_bump_variants', $all_variants );
			} else {
				// Clear variants if order bump not set
				delete_post_meta( $post_id, 'cfw_ab_test_order_bump_variants' );
			}
		}

		// Save traffic split type
		if ( isset( $_POST['cfw_ab_test_traffic_split_type'] ) ) {
			$traffic_split_type = sanitize_text_field( $_POST['cfw_ab_test_traffic_split_type'] );
			if ( in_array( $traffic_split_type, array( 'equal', 'custom' ), true ) ) {
				update_post_meta( $post_id, 'cfw_ab_test_traffic_split_type', $traffic_split_type );
			}
		} else {
			delete_post_meta( $post_id, 'cfw_ab_test_traffic_split_type' );
		}

		// Save traffic split percentages (only if custom split is selected)
		if ( isset( $_POST['cfw_ab_test_traffic_split_type'] ) && 'custom' === $_POST['cfw_ab_test_traffic_split_type'] ) {
			if ( isset( $_POST['cfw_ab_test_traffic_split_percentages'] ) && is_array( $_POST['cfw_ab_test_traffic_split_percentages'] ) ) {
				$percentages = array();
				$total       = 0;
				foreach ( $_POST['cfw_ab_test_traffic_split_percentages'] as $variant_id => $percentage ) {
					// Preserve 0 values - convert empty strings to 0, but keep explicit 0s
					$percentage_value                     = '' === $percentage ? 0 : absint( $percentage );
					$percentages[ absint( $variant_id ) ] = $percentage_value;
					$total                               += $percentage_value;
				}

				// Validate that total equals 100%
				if ( 100 === $total ) {
					update_post_meta( $post_id, 'cfw_ab_test_traffic_split_percentages', $percentages );
				}
			} else {
				delete_post_meta( $post_id, 'cfw_ab_test_traffic_split_percentages' );
			}
		} else {
			// Clear percentages if not using custom split
			delete_post_meta( $post_id, 'cfw_ab_test_traffic_split_percentages' );
		}

		// Check conditions that should pause the test (unless it's already complete)
		$post_status = isset( $_POST['cfw_ab_test_status'] ) && in_array( $_POST['cfw_ab_test_status'], array( 'active', 'paused', 'complete' ), true )
			? sanitize_text_field( $_POST['cfw_ab_test_status'] )
			: get_post_meta( $post_id, 'cfw_ab_test_status', true );

		// Don't pause if status is complete
		if ( 'complete' !== $post_status && 'order_bumps' === $this->test->get_type() ) {
			$should_pause        = false;
			$saved_order_bump_id = get_post_meta( $post_id, 'cfw_ab_test_order_bump', true );

			// Check if A/B testing is disabled globally
			$ab_testing_enabled = SettingsManager::instance()->get_setting( 'enable_ab_testing' ) === 'yes';
			if ( ! $ab_testing_enabled ) {
				$should_pause = true;
			}

			// Check if order bumps are disabled globally
			$order_bumps_enabled = SettingsManager::instance()->get_setting( 'enable_order_bumps' ) === 'yes';
			if ( ! $order_bumps_enabled ) {
				$should_pause = true;
			}

			// Check if test is not published or is password-protected (not publicly visible)
			$test_post = get_post( $post_id );
			if ( $test_post && ( 'publish' !== $test_post->post_status || ! empty( $test_post->post_password ) ) ) {
				$should_pause = true;
			}

			// Check if order bump is missing, deleted, or not published
			if ( ! empty( $saved_order_bump_id ) ) {
				$order_bump = get_post( $saved_order_bump_id );
				if ( ! $order_bump || 'publish' !== $order_bump->post_status ) {
					$should_pause = true;
				}
			} else {
				$should_pause = true;
			}

			// Check if no published variants exist
			$saved_variants         = get_post_meta( $post_id, 'cfw_ab_test_order_bump_variants', true );
			$has_published_variants = false;
			if ( ! empty( $saved_variants ) && is_array( $saved_variants ) && count( $saved_variants ) > 0 ) {
				// Check if any variants are actually published
				foreach ( $saved_variants as $variant_id ) {
					$variant = get_post( $variant_id );
					if ( $variant && 'publish' === $variant->post_status ) {
						$has_published_variants = true;
						break;
					}
				}
			}
			if ( ! $has_published_variants ) {
				$should_pause = true;
			}

			// Pause the test if any condition is met
			if ( $should_pause ) {
				update_post_meta( $post_id, 'cfw_ab_test_status', 'paused' );
			}
		}
	}

	/**
	 * Render variants grid
	 *
	 * @param int $order_bump_id The order bump ID.
	 * @param int $test_id The A/B test ID.
	 * @return string
	 */
	public function render_variants_grid( $order_bump_id, $test_id ) {
		ob_start();

		// Get test status
		$test_status = get_post_meta( $test_id, 'cfw_ab_test_status', true );
		if ( empty( $test_status ) ) {
			$test_status = 'active';
		}

		// Check if there is a winner
		$winner_variant_id = get_post_meta( $test_id, 'cfw_ab_test_winner_variant_id', true );
		$has_winner        = ! empty( $winner_variant_id );

		// Get all variants for this order bump (including trashed)
		$all_variants = get_posts(
			array(
				'post_type'      => BumpAbstract::get_post_type(),
				'post_parent'    => $order_bump_id,
				'post_status'    => array( 'publish', 'draft', 'trash' ),
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'ASC',
			)
		);

		// If test is complete with winner, variants are deleted - return empty (boxes shown under status field)
		if ( 'complete' === $test_status && $has_winner ) {
			return ob_get_clean();
		}

		// If test is complete without winner, show variants with "Select Winner" buttons
		$is_complete_no_winner = ( 'complete' === $test_status && ! $has_winner );

		// Display original bump card (only if not complete with winner)
		if ( ! $has_winner ) {
			$original_bump     = get_post( $order_bump_id );
			$original_bump_obj = $original_bump ? BumpFactory::get( $order_bump_id ) : null;

			if ( $original_bump && $original_bump_obj ) :
				$original_conversion_rate     = $original_bump_obj->get_conversion_rate();
				$original_revenue             = $original_bump_obj->get_captured_revenue();
				$original_location            = $original_bump_obj->get_display_location();
				$original_location_label      = $this->format_location_label( $original_location );
				$original_offer_product       = $original_bump_obj->get_offer_product();
				$original_offer_product_title = $original_offer_product ? $original_offer_product->get_title() : '--';
				?>
				<div class="cfw-variant-card cfw-variant-card-original">
					<div class="cfw-variant-card-header">
						<h3>
						<?php
							$original_bump_title = trim( $original_bump->post_title );
						if ( empty( $original_bump_title ) ) {
							$original_bump_title = sprintf( __( 'No Title #%d', 'checkout-wc' ), $original_bump->ID );
						}
							echo esc_html( $original_bump_title );
						if ( 'draft' === $original_bump->post_status ) {
							echo ' - ' . esc_html__( 'Draft', 'checkout-wc' );
						} elseif ( 'trash' === $original_bump->post_status ) {
							echo ' - ' . esc_html__( 'Trashed', 'checkout-wc' );
						}
						?>
						</h3>
						<span class="cfw-variant-badge cfw-variant-badge-original"><?php esc_html_e( 'Original', 'checkout-wc' ); ?></span>
					</div>
					<div class="cfw-variant-card-info">
						<div class="cfw-variant-info">
							<span class="cfw-variant-info-label"><?php esc_html_e( 'Conversion Rate', 'checkout-wc' ); ?>:</span>
							<span class="cfw-variant-info-value-big"><?php echo esc_html( $original_conversion_rate ); ?></span>
						</div>
						<div class="cfw-variant-info">
							<span class="cfw-variant-info-label"><?php esc_html_e( 'Revenue', 'checkout-wc' ); ?>:</span>
							<span class="cfw-variant-info-value-big">
								<?php echo wp_kses_post( 0.0 === $original_revenue ? '--' : wc_price( $original_revenue ) ); ?>
							</span>
						</div>
						<div class="cfw-variant-info">
							<span class="cfw-variant-info-label"><?php esc_html_e( 'Location', 'checkout-wc' ); ?>:</span>
							<span class="cfw-variant-info-value"><?php echo esc_html( $original_location_label ); ?></span>
						</div>
						<div class="cfw-variant-info">
							<span class="cfw-variant-info-label"><?php esc_html_e( 'Offer Product', 'checkout-wc' ); ?>:</span>
							<span class="cfw-variant-info-value"><?php echo esc_html( $original_offer_product_title ); ?></span>
						</div>
					</div>
				</div>
			<?php
			endif;
		}

		// Display variant cards
		foreach ( $all_variants as $variant_post ) :
			$variant_bump                = BumpFactory::get( $variant_post->ID );
			$variant_conversion_rate     = $variant_bump->get_conversion_rate();
			$variant_revenue             = $variant_bump->get_captured_revenue();
			$variant_location            = $variant_bump->get_display_location();
			$variant_location_label      = $this->format_location_label( $variant_location );
			$variant_offer_product       = $variant_bump->get_offer_product();
			$variant_offer_product_title = $variant_offer_product ? $variant_offer_product->get_title() : '--';
			$edit_url                    = add_query_arg(
				array(
					'cfw_ab_test' => $test_id,
				),
				get_edit_post_link( $variant_post->ID, 'raw' )
			);
			?>
			<div class="cfw-variant-card" data-variant-id="<?php echo esc_attr( $variant_post->ID ); ?>">
				<div class="cfw-variant-card-header">
					<h3>
					<?php
						$variant_title = trim( $variant_post->post_title );
					if ( empty( $variant_title ) ) {
						$variant_title = sprintf( __( 'No Title #%d', 'checkout-wc' ), $variant_post->ID );
					}
						echo esc_html( $variant_title );
					if ( 'draft' === $variant_post->post_status ) {
						echo ' - ' . esc_html__( 'Draft', 'checkout-wc' );
					} elseif ( 'trash' === $variant_post->post_status ) {
						echo ' - ' . esc_html__( 'Trashed', 'checkout-wc' );
					}
					?>
					</h3>
					<?php if ( ! $is_complete_no_winner ) : ?>
						<button type="button" class="cfw-variant-delete-button" data-variant-id="<?php echo esc_attr( $variant_post->ID ); ?>" title="<?php esc_attr_e( 'Delete variant', 'checkout-wc' ); ?>">
							<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
								<path d="M12 4L4 12M4 4L12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
							</svg>
						</button>
					<?php endif; ?>
				</div>
				<div class="cfw-variant-card-info">
					<div class="cfw-variant-info">
						<span class="cfw-variant-info-label"><?php esc_html_e( 'Conversion Rate', 'checkout-wc' ); ?>:</span>
						<span class="cfw-variant-info-value-big"><?php echo esc_html( $variant_conversion_rate ); ?></span>
					</div>
					<div class="cfw-variant-info">
						<span class="cfw-variant-info-label"><?php esc_html_e( 'Revenue', 'checkout-wc' ); ?>:</span>
						<span class="cfw-variant-info-value-big">
							<?php
							// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
							echo wp_kses_post( 0.0 === $variant_revenue ? '--' : wc_price( $variant_revenue ) );
							?>
						</span>
					</div>
					<div class="cfw-variant-info">
						<span class="cfw-variant-info-label"><?php esc_html_e( 'Location', 'checkout-wc' ); ?>:</span>
						<span class="cfw-variant-info-value"><?php echo esc_html( $variant_location_label ); ?></span>
					</div>
					<div class="cfw-variant-info">
						<span class="cfw-variant-info-label"><?php esc_html_e( 'Offer Product', 'checkout-wc' ); ?>:</span>
						<span class="cfw-variant-info-value"><?php echo esc_html( $variant_offer_product_title ); ?></span>
					</div>
				</div>
				<div class="cfw-variant-card-actions">
					<?php if ( $is_complete_no_winner ) : ?>
						<button type="button" class="button button-small cfw-select-winner-button" data-variant-id="<?php echo esc_attr( $variant_post->ID ); ?>" data-is-original="0"<?php echo 'publish' !== $variant_post->post_status ? ' disabled' : ''; ?>>
							<?php esc_html_e( 'Select Winner', 'checkout-wc' ); ?>
						</button>
					<?php else : ?>
						<button type="button" class="button button-small cfw-edit-variant-button" data-edit-url="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'checkout-wc' ); ?></button>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>

		<?php
		// Display "Create Variant" card (only if not complete)
		if ( 'complete' !== $test_status ) :
			?>
			<div class="cfw-variant-card cfw-variant-card-create">
				<button type="button" class="cfw-variant-card-create-button" data-test-id="<?php echo esc_attr( $test_id ); ?>">
					<div class="cfw-variant-card-create-icon">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M12 5V19M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
						</svg>
					</div>
					<span class="cfw-variant-card-create-text"><?php esc_html_e( 'Create Variant', 'checkout-wc' ); ?></span>
				</button>
			</div>
		<?php endif; ?>

		<?php

		return ob_get_clean();
	}

	/**
	 * Output variant card styles
	 *
	 * @return void
	 */
	private function output_variant_card_styles() {
		?>
		<style>
		/* Grid/Layout */
		.cfw-variants-grid {
			display: grid;
			grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
			gap: 20px;
			margin-bottom: 10px;
		}

		/* Variant Card Base */
		.cfw-variant-card {
			border: 1px solid #ddd;
			border-radius: 4px;
			padding: 15px;
			background: #fff;
			box-shadow: 0 1px 3px rgba(0,0,0,0.1);
		}
		.cfw-variant-card-original {
			border-color: #2271b1;
			border-width: 2px;
		}
		.cfw-variant-card-original.cfw-variant-card-original-no-border {
			border-color: #ddd !important;
			border-width: 1px !important;
		}
		.cfw-variant-card-winner {
			border-color: #00a32a;
			border-width: 2px;
		}

		/* Variant Card Header */
		.cfw-variant-card-header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 15px;
		}
		.cfw-variant-card-header h3 {
			margin: 0;
			font-size: 14px;
			font-weight: 600;
		}

		/* Variant Card Info */
		.cfw-variant-card-info {
			display: flex;
			flex-direction: column;
			gap: 10px;
			margin-bottom: 15px;
		}

		/* Variant Card Actions */
		.cfw-variant-card-actions {
			margin-top: 10px;
		}

		/* Variant Card Create */
		.cfw-variant-card-create {
			border: 2px dashed #ddd;
			display: flex;
			align-items: center;
			justify-content: center;
			min-height: 150px;
			background: #f9f9f9;
		}
		.cfw-variant-card-create:hover {
			border-color: #2271b1;
			background: #f0f6fc;
		}
		.cfw-variant-card-create-button {
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			width: 100%;
			height: 100%;
			background: none;
			border: none;
			cursor: pointer;
			padding: 0;
			color: #666;
		}
		.cfw-variant-card-create-button:hover {
			color: #2271b1;
		}
		.cfw-variant-card-create-button:disabled {
			opacity: 0.6;
			cursor: not-allowed;
		}
		.cfw-variant-card-create-icon {
			margin-bottom: 10px;
			color: #999;
		}
		.cfw-variant-card-create-button:hover .cfw-variant-card-create-icon {
			color: #2271b1;
		}
		.cfw-variant-card-create-button:disabled .cfw-variant-card-create-icon {
			color: #999;
		}
		.cfw-variant-card-create-text {
			font-size: 14px;
			font-weight: 500;
		}

		/* Variant Badge */
		.cfw-variant-badge {
			display: inline-block;
			padding: 2px 8px;
			border-radius: 3px;
			font-size: 11px;
			font-weight: 600;
			text-transform: uppercase;
		}
		.cfw-variant-badge-original {
			background: #2271b1;
			color: #fff;
		}
		.cfw-variant-badge-winner {
			background: #00a32a;
			color: #fff;
		}

		/* Variant Info */
		.cfw-variant-info {
			display: flex;
			justify-content: space-between;
			align-items: center;
		}
		.cfw-variant-info-label {
			font-size: 12px;
			color: #666;
		}
		.cfw-variant-info-value {
			font-size: 12px;
			color: #666;
			text-align: right;
		}
		.cfw-variant-info-value-big {
			font-size: 14px;
			font-weight: 600;
			color: #2271b1;
		}
		.cfw-variant-info-value-winner {
			color: #00a32a;
		}

		/* Variant Delete Button */
		.cfw-variant-delete-button {
			background: none;
			border: none;
			cursor: pointer;
			padding: 4px;
			color: #a7aaad;
			display: flex;
			align-items: center;
			justify-content: center;
			border-radius: 3px;
			transition: all 0.2s;
		}
		.cfw-variant-delete-button:hover {
			color: #d63638;
			background: #f0f0f1;
		}
		</style>
		<?php
	}

	/**
	 * Format the location label
	 *
	 * @param string $location The location.
	 * @return string
	 */
	private function format_location_label( $location ): string {
		if ( empty( $location ) ) {
			return '--';
		}

		// Special case for 'complete_order'
		if ( 'complete_order' === $location ) {
			return __( 'After Checkout Submit', 'checkout-wc' );
		}

		// Convert underscore-separated values to readable labels
		$label = str_replace( '_', ' ', $location );
		return ucwords( $label );
	}

	/**
	 * Handle save order bump AJAX request
	 *
	 * @param array $data Request data
	 * @return array
	 */
	private function handle_save_order_bump( array $data ): array {
		// Implementation would handle saving order bump for the test
		return array(
			'success' => true,
			'message' => __( 'Order bump saved', 'checkout-wc' ),
		);
	}

	/**
	 * Handle get variants grid AJAX request
	 *
	 * @param array $data Request data
	 * @return array
	 */
	private function handle_get_variants_grid( array $data ): array {
		$order_bump_id = isset( $data['order_bump_id'] ) ? absint( $data['order_bump_id'] ) : 0;
		$test_id       = isset( $data['test_id'] ) ? absint( $data['test_id'] ) : 0;

		if ( ! $order_bump_id ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid order bump ID.', 'checkout-wc' ),
			);
		}

		$grid_html = $this->render_variants_grid( $order_bump_id, $test_id );

		return array(
			'success' => true,
			'html'    => $grid_html,
		);
	}

	/**
	 * Handle delete variant AJAX request
	 *
	 * @param array $data Request data
	 * @return array
	 */
	private function handle_delete_variant( array $data ): array {
		$variant_id    = isset( $data['variant_id'] ) ? absint( $data['variant_id'] ) : 0;
		$order_bump_id = isset( $data['order_bump_id'] ) ? absint( $data['order_bump_id'] ) : 0;
		$test_id       = isset( $data['test_id'] ) ? absint( $data['test_id'] ) : 0;

		if ( ! $variant_id ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid variant ID.', 'checkout-wc' ),
			);
		}

		// Verify this is actually a variant (has a parent)
		$variant_post = get_post( $variant_id );
		if ( ! $variant_post || $variant_post->post_parent <= 0 ) {
			return array(
				'success' => false,
				'message' => __( 'This is not a valid variant.', 'checkout-wc' ),
			);
		}

		// Use variant's parent as order_bump_id if not provided
		if ( ! $order_bump_id ) {
			$order_bump_id = (int) $variant_post->post_parent;
		}

		// Verify the variant belongs to the specified order bump
		if ( (int) $variant_post->post_parent !== $order_bump_id ) {
			return array(
				'success' => false,
				'message' => __( 'Variant does not belong to the specified order bump.', 'checkout-wc' ),
			);
		}

		// Get test_id from variant meta if not provided
		if ( ! $test_id ) {
			$test_id = get_post_meta( $variant_id, '_cfw_ab_test_id', true );
		}

		// Permanently delete the variant
		$result = wp_delete_post( $variant_id, true );

		if ( ! $result ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to delete variant.', 'checkout-wc' ),
			);
		}

		// Update the test's variants meta to remove the deleted variant
		if ( $order_bump_id && $test_id ) {
			$this->update_variants_meta( $test_id, $order_bump_id );
			$this->redistribute_traffic_percentages_after_deletion( $test_id, $order_bump_id, $variant_id );
		}

		// Return success with updated percentages for UI refresh
		$response = array(
			'success' => true,
			'message' => __( 'Variant deleted successfully.', 'checkout-wc' ),
		);

		// Include updated percentages if using custom split
		if ( $order_bump_id && $test_id ) {
			$traffic_split_type = get_post_meta( $test_id, 'cfw_ab_test_traffic_split_type', true );
			if ( 'custom' === $traffic_split_type ) {
				$updated_percentages = get_post_meta( $test_id, 'cfw_ab_test_traffic_split_percentages', true );
				if ( is_array( $updated_percentages ) ) {
					$response['percentages'] = $updated_percentages;
				}
			}
		}

		return $response;
	}

	/**
	 * Redistribute traffic split percentages after a variant is deleted
	 *
	 * @param int $test_id The test ID
	 * @param int $order_bump_id The order bump ID
	 * @param int $deleted_variant_id The deleted variant ID
	 * @return void
	 */
	private function redistribute_traffic_percentages_after_deletion( int $test_id, int $order_bump_id, int $deleted_variant_id ): void {
		$traffic_split_type = get_post_meta( $test_id, 'cfw_ab_test_traffic_split_type', true );
		if ( 'custom' !== $traffic_split_type ) {
			return;
		}

		$percentages = get_post_meta( $test_id, 'cfw_ab_test_traffic_split_percentages', true );
		if ( empty( $percentages ) || ! is_array( $percentages ) ) {
			return;
		}

		// Remove deleted variant from percentages
		unset( $percentages[ $deleted_variant_id ] );

		// Get remaining items (original + remaining variants)
		$remaining_variants = get_posts(
			array(
				'post_type'      => BumpAbstract::get_post_type(),
				'post_parent'    => $order_bump_id,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		$remaining_items    = array_merge( array( $order_bump_id ), $remaining_variants );

		if ( empty( $remaining_items ) ) {
			delete_post_meta( $test_id, 'cfw_ab_test_traffic_split_percentages' );
			return;
		}

		// Calculate remaining total
		$remaining_total = 0;
		foreach ( $remaining_items as $item_id ) {
			$item_id = (int) $item_id;
			if ( isset( $percentages[ $item_id ] ) ) {
				$remaining_total += (int) $percentages[ $item_id ];
			}
		}

		// Redistribute percentages
		$redistributed = $this->calculate_redistributed_percentages( $remaining_items, $percentages, $remaining_total );
		update_post_meta( $test_id, 'cfw_ab_test_traffic_split_percentages', $redistributed );
	}

	/**
	 * Calculate redistributed percentages for remaining items
	 *
	 * When a variant is deleted, this method redistributes its percentage share
	 * proportionally across the remaining items (original + remaining variants).
	 *
	 * Example:
	 * - Original: 20%, Variant 1: 20%, Variant 2: 60% (deleted)
	 * - Remaining total: 40% (20% + 20%)
	 * - Redistributed: Original: 50% (20/40 * 100), Variant 1: 50% (20/40 * 100)
	 *
	 * @param array $remaining_items Array of item IDs (original order bump + remaining variants)
	 * @param array $percentages Current percentages array (before deletion)
	 * @param int   $remaining_total Sum of percentages for remaining items (excluding deleted variant)
	 * @return array Redistributed percentages that total exactly 100%
	 */
	private function calculate_redistributed_percentages( array $remaining_items, array $percentages, int $remaining_total ): array {
		$redistributed = array();

		if ( $remaining_total > 0 ) {
			// Case 1: Redistribute proportionally based on existing percentages
			// This preserves the relative ratios of the remaining items
			// Formula: new_percentage = (current_percentage / remaining_total) * 100
			// This scales the remaining percentages up to 100%
			foreach ( $remaining_items as $item_id ) {
				$item_id = (int) $item_id;
				$current = isset( $percentages[ $item_id ] ) ? (int) $percentages[ $item_id ] : 0;
				// Calculate proportional percentage: scale current percentage to 100%
				// Example: if current is 20% and remaining_total is 40%, result is 50%
				$redistributed[ $item_id ] = round( ( $current / $remaining_total ) * 100 );
			}
		} else {
			// Case 2: No existing percentages or all were 0
			// Distribute equally across all remaining items
			// Example: 3 items = 33% each (with 1% remainder added to first)
			$item_count = count( $remaining_items );
			$equal      = round( 100 / $item_count );
			// Calculate remainder due to rounding (e.g., 3 items: 33+33+33 = 99, remainder = 1)
			$remainder = 100 - ( $equal * $item_count );

			foreach ( $remaining_items as $index => $item_id ) {
				$item_id = (int) $item_id;
				// Add remainder to first item to ensure total equals exactly 100
				$redistributed[ $item_id ] = $equal + ( 0 === $index ? $remainder : 0 );
			}
		}

		// Fix rounding errors to ensure total equals exactly 100
		// Due to rounding, the sum might be 99 or 101 instead of 100
		$total = array_sum( $redistributed );
		if ( $total !== 100 && ! empty( $redistributed ) ) {
			$difference = 100 - $total;
			// Add/subtract the difference to/from the first item
			// This ensures the total is always exactly 100%
			$first_key                    = array_key_first( $redistributed );
			$redistributed[ $first_key ] += $difference;
		}

		return $redistributed;
	}

	/**
	 * Handle create variant AJAX request
	 *
	 * @param array $data Request data
	 * @return array
	 */
	private function handle_create_variant( array $data ): array {
		$test_id       = isset( $data['test_id'] ) ? absint( $data['test_id'] ) : 0;
		$variant_title = isset( $data['variant_title'] ) ? sanitize_text_field( $data['variant_title'] ) : '';

		if ( ! $test_id ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid test ID.', 'checkout-wc' ),
			);
		}

		if ( empty( $variant_title ) ) {
			return array(
				'success' => false,
				'message' => __( 'Variant title is required.', 'checkout-wc' ),
			);
		}

		// Verify test exists
		$test = get_post( $test_id );
		if ( ! $test || ABTesting::get_post_type() !== $test->post_type ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid test.', 'checkout-wc' ),
			);
		}

		// Get order bump ID
		if ( isset( $data['order_bump_id'] ) && ! empty( $data['order_bump_id'] ) ) {
			$order_bump_id = absint( $data['order_bump_id'] );
			if ( $order_bump_id ) {
				update_post_meta( $test_id, 'cfw_ab_test_order_bump', $order_bump_id );
			}
		} else {
			$order_bump_id = get_post_meta( $test_id, 'cfw_ab_test_order_bump', true );
		}

		if ( empty( $order_bump_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'Please select an order bump for this test before creating variants.', 'checkout-wc' ),
			);
		}

		// Verify order bump exists
		$order_bump = get_post( $order_bump_id );
		if ( ! $order_bump ) {
			return array(
				'success' => false,
				'message' => __( 'Order bump not found.', 'checkout-wc' ),
			);
		}

		// Create the variant post
		$variant_id = wp_insert_post(
			array(
				'post_title'  => $variant_title,
				'post_type'   => BumpAbstract::get_post_type(),
				'post_status' => 'draft',
				'post_parent' => $order_bump_id,
			)
		);

		if ( is_wp_error( $variant_id ) || ! $variant_id ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to create variant.', 'checkout-wc' ),
			);
		}

		// Clone meta from parent order bump
		$meta_data = get_post_meta( $order_bump_id );
		foreach ( $meta_data as $key => $values ) {
			// Skip internal WordPress meta and conversion stats
			if ( strpos( $key, '_edit_' ) === 0 || strpos( $key, '_wp_' ) === 0 ) {
				continue;
			}
			if ( in_array( $key, array( 'times_bump_purchased', 'times_bump_displayed_on_purchases', 'conversion_rate', 'captured_revenue' ), true ) ) {
				continue;
			}

			// Delete existing meta
			delete_post_meta( $variant_id, $key );

			// Copy meta from parent
			foreach ( $values as $value ) {
				add_post_meta( $variant_id, $key, maybe_unserialize( $value ) );
			}
		}

		// Mark as cloned
		update_post_meta( $variant_id, '_cfw_variant_cloned', '1' );

		// Store A/B test ID in post meta
		update_post_meta( $variant_id, '_cfw_ab_test_id', $test_id );

		// Update the test's variants meta
		$this->update_variants_meta( $test_id, $order_bump_id );

		// Build edit URL
		$edit_url = add_query_arg(
			array(
				'cfw_ab_test' => $test_id,
			),
			get_edit_post_link( $variant_id, 'raw' )
		);

		return array(
			'success'  => true,
			'message'  => __( 'Variant created successfully.', 'checkout-wc' ),
			'edit_url' => $edit_url,
		);
	}

	/**
	 * Handle select winner AJAX request
	 *
	 * @param array $data Request data
	 * @return array
	 */
	private function handle_select_winner( array $data ): array {
		// Validate request
		$validation_error = $this->validate_winner_selection( $data );
		if ( $validation_error ) {
			return $validation_error;
		}

		$variant_id    = absint( $data['variant_id'] );
		$test_id       = absint( $data['test_id'] );
		$order_bump_id = absint( get_post_meta( $test_id, 'cfw_ab_test_order_bump', true ) );
		$apply_stats   = isset( $data['apply_stats'] ) && '1' === $data['apply_stats'];

		// Process winner selection
		$this->save_variants_data_for_winner( $test_id, $variant_id, $order_bump_id );
		$this->copy_winner_to_original( $variant_id, $order_bump_id, $apply_stats );
		$this->delete_all_test_variants( $order_bump_id );

		return array(
			'success' => true,
			'message' => __( 'Winner set successfully.', 'checkout-wc' ),
		);
	}

	/**
	 * Validate winner selection request
	 *
	 * @param array $data Request data.
	 * @return array|null Error array or null if valid.
	 */
	private function validate_winner_selection( array $data ): ?array {
		$variant_id = isset( $data['variant_id'] ) ? absint( $data['variant_id'] ) : 0;
		$test_id    = isset( $data['test_id'] ) ? absint( $data['test_id'] ) : 0;

		// Check IDs provided
		if ( ! $variant_id || ! $test_id ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid variant or test ID.', 'checkout-wc' ),
			);
		}

		// Verify test exists
		$test = get_post( $test_id );
		if ( ! $test || ABTesting::get_post_type() !== $test->post_type ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid test.', 'checkout-wc' ),
			);
		}

		// Check test is complete
		$test_status = get_post_meta( $test_id, 'cfw_ab_test_status', true );
		if ( 'complete' !== $test_status ) {
			return array(
				'success' => false,
				'message' => __( 'Test must be marked as Complete before selecting a winner.', 'checkout-wc' ),
			);
		}

		// Check order bump exists
		$order_bump_id = get_post_meta( $test_id, 'cfw_ab_test_order_bump', true );
		if ( empty( $order_bump_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'No order bump associated with this test.', 'checkout-wc' ),
			);
		}

		// Verify variant validity
		return $this->validate_variant( $variant_id, $order_bump_id );
	}

	/**
	 * Validate that variant is valid and belongs to test
	 *
	 * @param int $variant_id Variant ID.
	 * @param int $order_bump_id Order bump ID.
	 * @return array|null Error array or null if valid.
	 */
	private function validate_variant( int $variant_id, int $order_bump_id ): ?array {
		$variant_post = get_post( $variant_id );
		if ( ! $variant_post ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid variant.', 'checkout-wc' ),
			);
		}

		// Cannot select original as winner
		if ( $variant_id === $order_bump_id ) {
			return array(
				'success' => false,
				'message' => __( 'The original order bump cannot be set as the winner. Please select a variant.', 'checkout-wc' ),
			);
		}

		// Check variant belongs to order bump
		if ( (int) $variant_post->post_parent !== $order_bump_id ) {
			return array(
				'success' => false,
				'message' => __( 'Variant does not belong to this test.', 'checkout-wc' ),
			);
		}

		return null;
	}

	/**
	 * Save variants data before deletion
	 *
	 * @param int $test_id Test ID.
	 * @param int $variant_id Winner variant ID.
	 * @param int $order_bump_id Order bump ID.
	 * @return void
	 */
	private function save_variants_data_for_winner( int $test_id, int $variant_id, int $order_bump_id ): void {
		$saved_variants_data = array();

		// Save original bump data
		$original_data = $this->get_bump_data( $order_bump_id, true, false );
		if ( $original_data ) {
			$saved_variants_data[ $order_bump_id ] = $original_data;
		}

		// Save all variant data
		$all_variants = $this->get_all_test_variants( $order_bump_id );
		foreach ( $all_variants as $variant_post ) {
			$is_winner    = ( $variant_post->ID === $variant_id );
			$variant_data = $this->get_bump_data( $variant_post->ID, false, $is_winner );
			if ( $variant_data ) {
				$saved_variants_data[ $variant_post->ID ] = $variant_data;
			}
		}

		// Save to test meta
		update_post_meta( $test_id, 'cfw_ab_test_variants_data', $saved_variants_data );
		update_post_meta( $test_id, 'cfw_ab_test_winner_variant_id', $variant_id );
	}

	/**
	 * Get bump data for storage
	 *
	 * @param int  $bump_id Bump ID.
	 * @param bool $is_original Whether this is the original.
	 * @param bool $is_winner Whether this is the winner.
	 * @return array|null
	 */
	private function get_bump_data( int $bump_id, bool $is_original, bool $is_winner ): ?array {
		$bump     = get_post( $bump_id );
		$bump_obj = $bump ? BumpFactory::get( $bump_id ) : null;

		if ( ! $bump || ! $bump_obj ) {
			return null;
		}

		$offer_product = $bump_obj->get_offer_product();

		return array(
			'id'              => $bump_id,
			'title'           => $bump->post_title,
			'is_original'     => $is_original,
			'is_winner'       => $is_winner,
			'conversion_rate' => $bump_obj->get_conversion_rate(),
			'revenue'         => $bump_obj->get_captured_revenue(),
			'location'        => $bump_obj->get_display_location(),
			'offer_product'   => $offer_product ? $offer_product->get_title() : '--',
		);
	}

	/**
	 * Copy winner data to original bump
	 *
	 * @param int  $variant_id Winner variant ID.
	 * @param int  $order_bump_id Original order bump ID.
	 * @param bool $apply_stats Whether to apply stats.
	 * @return void
	 */
	private function copy_winner_to_original( int $variant_id, int $order_bump_id, bool $apply_stats ): void {
		$variant_bump = BumpFactory::get( $variant_id );
		if ( ! $variant_bump ) {
			return;
		}

		$variant_meta = get_post_meta( $variant_id );
		foreach ( $variant_meta as $key => $values ) {
			// Skip internal WordPress meta
			if ( $this->should_skip_meta_key( $key, $apply_stats ) ) {
				continue;
			}

			// Replace original meta with variant meta
			delete_post_meta( $order_bump_id, $key );
			foreach ( $values as $value ) {
				add_post_meta( $order_bump_id, $key, maybe_unserialize( $value ) );
			}
		}
	}

	/**
	 * Check if meta key should be skipped
	 *
	 * @param string $key Meta key.
	 * @param bool   $apply_stats Whether to apply stats.
	 * @return bool
	 */
	private function should_skip_meta_key( string $key, bool $apply_stats ): bool {
		// Skip WordPress internal meta
		if ( strpos( $key, '_edit_' ) === 0 || strpos( $key, '_wp_' ) === 0 ) {
			return true;
		}

		// Skip stats unless requested
		$stat_keys = array( 'times_bump_purchased', 'times_bump_displayed_on_purchases', 'conversion_rate', 'captured_revenue' );
		if ( ! $apply_stats && in_array( $key, $stat_keys, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get all test variants
	 *
	 * @param int $order_bump_id Order bump ID.
	 * @return array
	 */
	private function get_all_test_variants( int $order_bump_id ): array {
		return get_posts(
			array(
				'post_type'      => BumpAbstract::get_post_type(),
				'post_parent'    => $order_bump_id,
				'post_status'    => 'any',
				'posts_per_page' => -1,
			)
		);
	}

	/**
	 * Delete all test variants
	 *
	 * @param int $order_bump_id Order bump ID.
	 * @return void
	 */
	private function delete_all_test_variants( int $order_bump_id ): void {
		$all_variants = $this->get_all_test_variants( $order_bump_id );
		foreach ( $all_variants as $variant_post ) {
			wp_delete_post( $variant_post->ID, true );
		}
	}

	/**
	 * Render status notices for the test
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function render_status_notices( WP_Post $post ): void {
		$test = ABTestFactory::get( $post->ID );
		if ( ! $test instanceof OrderBumpsABTest ) {
			return;
		}

		$test_status   = $test->get_status();
		$order_bump_id = get_post_meta( $post->ID, 'cfw_ab_test_order_bump', true );

		if ( empty( $order_bump_id ) ) {
			return;
		}

		// Check if there is a winner
		$winner_variant_id = get_post_meta( $post->ID, 'cfw_ab_test_winner_variant_id', true );
		$has_winner        = ! empty( $winner_variant_id );

		// Determine notice text based on status
		$notice_text  = '';
		$notice_class = 'notice-info';
		$show_notice  = true;

		// Show notice based on test status
		if ( 'active' === $test_status ) {
			$notice_text  = '<strong>' . __( 'This test is active. It ends once you set the status to completed or when the end date is reached. You can then select a winner.', 'checkout-wc' ) . '</strong>';
			$notice_class = 'notice-info';
		} elseif ( 'paused' === $test_status ) {
			$notice_text  = '<strong>' . __( 'This test is paused due to one or more of the following conditions:', 'checkout-wc' ) . '</strong>' . '<ul style="margin: 20px 0;">' .
				'<li>' . __( 'It was manually paused or is not published', 'checkout-wc' ) . '</li>' .
				'<li>' . __( 'Original order bump is not published or was deleted', 'checkout-wc' ) . '</li>' .
				'<li>' . __( 'Order bump variants are not created or published', 'checkout-wc' ) . '</li>' .
				'<li>' . __( 'A/B testing and/or order bumps features have been disabled', 'checkout-wc' ) . '</li>' .
				'</ul>' . __( 'If these are no longer true and you want to resume the test, set the status to active.', 'checkout-wc' );
			$notice_class = 'notice-warning';
		} elseif ( 'complete' === $test_status ) {
			if ( ! $has_winner ) {
				$notice_text  = '<strong>' . __( 'This test is complete. Select a variant below as the winner.', 'checkout-wc' ) . '</strong>';
				$notice_class = 'notice-success';
			} else {
				// Completed with winner - show success notice
				$notice_text  = '<strong>' . __( 'This test is complete, and a winner has already been selected.', 'checkout-wc' ) . '</strong>';
				$notice_class = 'notice-success';
				$show_notice  = true;
			}
		}

		if ( $show_notice && ! empty( $notice_text ) ) {
			?>
			<div class="notice <?php echo esc_attr( $notice_class ); ?> inline" style="margin: 20px 0 0 0;">
				<p><?php echo wp_kses_post( $notice_text ); ?></p>
			</div>
			<?php
		} elseif ( ! $show_notice && ! empty( $notice_text ) ) {
			?>
			<p style="margin: 20px 0 0 0;"><?php echo wp_kses_post( $notice_text ); ?></p>
			<?php
		}

		// Display variant boxes from saved data when test is complete with winner
		if ( 'complete' === $test_status && $has_winner ) {
			$saved_variants_data = get_post_meta( $post->ID, 'cfw_ab_test_variants_data', true );
			if ( ! empty( $saved_variants_data ) && is_array( $saved_variants_data ) ) {
				?>
				<div class="cfw-variants-grid" style="margin: 20px 0 0 0;">
					<?php
					foreach ( $saved_variants_data as $variant_data ) {
						$is_winner   = ! empty( $variant_data['is_winner'] );
						$is_original = ! empty( $variant_data['is_original'] );
						// When winner exists, original should not have blue border
						if ( $is_winner ) {
							$card_class = 'cfw-variant-card-winner';
						} elseif ( $is_original ) {
							$card_class = 'cfw-variant-card-original cfw-variant-card-original-no-border';
						} else {
							$card_class = 'cfw-variant-card';
						}
						$badge_text  = $is_winner ? __( 'Winner', 'checkout-wc' ) : ( $is_original ? __( 'Original', 'checkout-wc' ) : '' );
						$badge_class = $is_winner ? 'cfw-variant-badge-winner' : ( $is_original ? 'cfw-variant-badge-original' : '' );
						?>
						<div class="cfw-variant-card <?php echo esc_attr( $card_class ); ?>">
							<div class="cfw-variant-card-header">
								<h3><?php echo esc_html( $variant_data['title'] ); ?></h3>
								<?php if ( ! empty( $badge_text ) ) : ?>
									<span class="cfw-variant-badge <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $badge_text ); ?></span>
								<?php endif; ?>
							</div>
							<div class="cfw-variant-card-info">
								<div class="cfw-variant-info">
									<span class="cfw-variant-info-label"><?php esc_html_e( 'Conversion Rate', 'checkout-wc' ); ?>:</span>
									<span class="cfw-variant-info-value-big <?php echo $is_winner ? 'cfw-variant-info-value-winner' : ''; ?>"><?php echo esc_html( $variant_data['conversion_rate'] ); ?></span>
								</div>
								<div class="cfw-variant-info">
									<span class="cfw-variant-info-label"><?php esc_html_e( 'Revenue', 'checkout-wc' ); ?>:</span>
									<span class="cfw-variant-info-value-big <?php echo $is_winner ? 'cfw-variant-info-value-winner' : ''; ?>">
										<?php
										// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
										$revenue = isset( $variant_data['revenue'] ) ? floatval( $variant_data['revenue'] ) : 0.0;
										echo wp_kses_post( 0.0 === $revenue ? '--' : wc_price( $revenue ) );
										?>
									</span>
								</div>
								<div class="cfw-variant-info">
									<span class="cfw-variant-info-label"><?php esc_html_e( 'Location', 'checkout-wc' ); ?>:</span>
									<span class="cfw-variant-info-value">
										<?php
										$location       = isset( $variant_data['location'] ) ? $variant_data['location'] : '';
										$location_label = ! empty( $location ) ? $this->format_location_label( $location ) : '--';
										echo esc_html( $location_label );
										?>
									</span>
								</div>
								<div class="cfw-variant-info">
									<span class="cfw-variant-info-label"><?php esc_html_e( 'Offer Product', 'checkout-wc' ); ?>:</span>
									<span class="cfw-variant-info-value">
										<?php
										$offer_product_title = isset( $variant_data['offer_product'] ) ? $variant_data['offer_product'] : '--';
										echo esc_html( $offer_product_title );
										?>
									</span>
								</div>
							</div>
						</div>
						<?php
					}
					?>
				</div>
				<?php
				// Display delete test notice so user can create a new test using this order bump if they want to
				$order_bump_title = '';
				if ( ! empty( $order_bump_id ) ) {
					$order_bump_post = get_post( $order_bump_id );
					if ( $order_bump_post ) {
						$order_bump_title = $order_bump_post->post_title;
					}
				}
				$delete_link = get_delete_post_link( $post->ID, '', true );
				?>
				<div class="notice notice-info inline" style="margin: 20px 0 0 0;">
					<p>
						<?php
						$order_bump_display_name = $order_bump_title ?: __( 'Order Bump', 'checkout-wc' );
						$delete_link_html        = '<a href="' . esc_url( $delete_link ) . '" class="submitdelete cfw-ab-test-delete-link">' . esc_html__( 'delete this test', 'checkout-wc' ) . '</a>';
						/* translators: %1$s: Order bump title, %2$s: Delete link HTML */
						echo wp_kses_post(
							sprintf(
								// translators: %1$s the order bump ID, %2$s delete test link
								__( 'To create a new A/B test for the "%1$s" order bump, %2$s.', 'checkout-wc' ),
								esc_html( $order_bump_display_name ),
								$delete_link_html
							)
						);
						?>
					</p>
				</div>
				<script type="text/javascript">
				jQuery(document).ready(function($) {
					$('.cfw-ab-test-delete-link').on('click', function(e) {
						if (!confirm('<?php echo esc_js( __( 'Are you sure you want to delete this A/B test? This action cannot be undone.', 'checkout-wc' ) ); ?>')) {
							e.preventDefault();
							return false;
						}
					});
				});
				</script>
				<?php
			}
		}
	}

	/**
	 * Handle test status changes
	 *
	 * @param int    $post_id Test post ID.
	 * @param string $old_status Previous status.
	 * @param string $new_status New status.
	 * @return void
	 */
	public function handle_status_change( int $post_id, string $old_status, string $new_status ): void {
		// If changing from complete to something else, clear winner flags
		if ( 'complete' === $old_status && 'complete' !== $new_status ) {
			$order_bump_id = get_post_meta( $post_id, 'cfw_ab_test_order_bump', true );

			if ( $order_bump_id ) {
				$variants = get_posts(
					array(
						'post_type'      => 'cfw_order_bumps',
						'post_parent'    => $order_bump_id,
						'post_status'    => 'any',
						'posts_per_page' => -1,
						'fields'         => 'ids',
					)
				);

				foreach ( $variants as $variant_id ) {
					delete_post_meta( $variant_id, 'cfw_ab_test_winner' );
				}
			}
		}
	}

	/**
	 * Delete all test variants
	 *
	 * @param int $post_id Test post ID.
	 * @return void
	 */
	public function delete_test_variants( int $post_id ): void {
		// Check if test is complete with winner
		$test_status             = get_post_meta( $post_id, 'cfw_ab_test_status', true );
		$winner_variant_id       = get_post_meta( $post_id, 'cfw_ab_test_winner_variant_id', true );
		$is_complete_with_winner = ( 'complete' === $test_status && ! empty( $winner_variant_id ) );

		if ( $is_complete_with_winner ) {
			// Variants already deleted when winner selected
			return;
		}

		// Find all variants associated with this test
		$variants = get_posts(
			array(
				'post_type'      => BumpAbstract::get_post_type(),
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'trash' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => '_cfw_ab_test_id',
						'value' => $post_id,
					),
				),
			)
		);

		// Delete all variants
		foreach ( $variants as $variant_id ) {
			wp_delete_post( $variant_id, true );
		}
	}

	/**
	 * Update test variants meta
	 *
	 * @param int $test_id The A/B test ID.
	 * @param int $order_bump_id The order bump ID.
	 * @return void
	 */
	private function update_variants_meta( int $test_id, int $order_bump_id ): void {
		if ( ! $test_id || ! $order_bump_id ) {
			return;
		}

		// Verify test exists and supports order bump variants
		$test = ABTestFactory::get( $test_id );

		if ( get_class( $test ) === get_class( new NullABTest() ) ) {
			return;
		}

		$test_type = $test->get_type();

		if ( ! $test_type ) {
			return;
		}

		if ( ! $test->supports( 'order_bump_variants' ) ) {
			return;
		}

		// Get all variants for this order bump
		$all_variants = get_posts(
			array(
				'post_type'      => BumpAbstract::get_post_type(),
				'post_parent'    => $order_bump_id,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		update_post_meta( $test_id, 'cfw_ab_test_order_bump_variants', $all_variants );
	}

	/**
	 * Outputs the JavaScript for managing order bump variants
	 *
	 * @return void
	 */
	public function enqueue_variant_management_script(): void {
		global $post;

		// Guard clauses
		if ( ! isset( $post ) || ABTesting::get_post_type() !== $post->post_type || empty( $post->ID ) ) {
			return;
		}

		$test = ABTestFactory::get( $post->ID );
		if ( ! $test instanceof OrderBumpsABTest ) {
			return;
		}

		$rest_url    = rest_url( 'checkoutwc/v1/order-bumps' );
		$nonce       = wp_create_nonce( 'wp_rest' );
		$selected_id = get_post_meta( $post->ID, 'cfw_ab_test_order_bump', true );
		$selected_id = ! empty( $selected_id ) ? absint( $selected_id ) : '';

		$traffic_split_percentages = get_post_meta( $post->ID, 'cfw_ab_test_traffic_split_percentages', true );
		if ( ! is_array( $traffic_split_percentages ) ) {
			$traffic_split_percentages = array();
		}

		// Get order bump IDs that already have A/B tests (excluding current test if editing)
		// Include all statuses including trash to prevent selecting bumps used in trashed tests
		$current_test_id = ! empty( $post->ID ) && 'auto-draft' !== $post->post_status ? $post->ID : 0;
		$existing_tests  = get_posts(
			array(
				'post_type'      => ABTesting::get_post_type(),
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
				'posts_per_page' => -1,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => 'cfw_ab_test_order_bump',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		$order_bumps_with_tests = array();
		$order_bump_test_titles = array(); // Map order bump ID to test title
		foreach ( $existing_tests as $test_post ) {
			// Skip current test if editing
			if ( $current_test_id && (int) $test_post->ID === (int) $current_test_id ) {
				continue;
			}

			$order_bump_id = get_post_meta( $test_post->ID, 'cfw_ab_test_order_bump', true );
			if ( $order_bump_id ) {
				$order_bumps_with_tests[]                 = absint( $order_bump_id );
				$order_bump_test_titles[ $order_bump_id ] = $test_post->post_title . ( 'publish' !== $test_post->post_status ? ' - ' . get_post_status_object( $test_post->post_status )->label : '' );
			}
		}

		$order_bumps_with_tests = array_values( array_unique( $order_bumps_with_tests ) );
		$existing_test_label    = __( 'Existing Test:', 'checkout-wc' );
		?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			if (typeof $.fn.selectWoo === 'undefined') {
				return;
			}

			var orderBumpsWithTests = <?php echo wp_json_encode( $order_bumps_with_tests ); ?>;
			if (!Array.isArray(orderBumpsWithTests)) {
				orderBumpsWithTests = Object.values(orderBumpsWithTests || {});
			}

			var orderBumpTestTitles = <?php echo wp_json_encode( $order_bump_test_titles ); ?>;
			var existingTestLabel = <?php echo wp_json_encode( $existing_test_label ); ?>;
			var orderBumpSelect = $('#cfw_ab_test_order_bump');
			var variantsRow = $('#cfw_ab_test_order_bump_variants_row');
			var variantsGrid = $('#cfw_ab_test_variants_grid');
			var trafficSplitRow = $('#cfw_ab_test_traffic_split_row');
			var trafficSplitType = $('#cfw_ab_test_traffic_split_type');
			var trafficSplitCustomRow = $('#cfw_ab_test_traffic_split_custom_row');
			var trafficSplitPercentages = $('#cfw_ab_test_traffic_split_percentages');
			var orderBumpNoticesContainer = $('#cfw_ab_test_order_bump_notices');
			var restBaseUrl = <?php echo json_encode( rest_url( 'checkoutwc/v1' ) ); ?>;
			var createBumpUrl = <?php echo json_encode( add_query_arg( array( 'post_type' => 'cfw_order_bumps' ), admin_url( 'edit.php' ) ) ); ?>;
			var ajaxUrl = <?php echo json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			var ajaxNonce = <?php echo json_encode( wp_create_nonce( 'objectiv-cfw-admin-save' ) ); ?>;
			var testId = variantsGrid.data('test-id') || <?php echo ! empty( $post->ID ) && 'auto-draft' !== $post->post_status ? $post->ID : 0; ?>;

			// Initialize order bump select
			if (orderBumpSelect.length) {
				var selectedValue = orderBumpSelect.val();
				var selectedText = selectedValue ? orderBumpSelect.find('option:selected').text() : '';

				orderBumpSelect.empty();

				orderBumpSelect.selectWoo({
					placeholder: <?php echo json_encode( __( 'Search for an order bump...', 'checkout-wc' ) ); ?>,
					allowClear: true,
					width: '50%',
					ajax: {
						url: <?php echo json_encode( $rest_url ); ?>,
						dataType: 'json',
						type: 'GET',
						delay: 250,
						beforeSend: function(xhr) {
							xhr.setRequestHeader('X-WP-Nonce', <?php echo json_encode( $nonce ); ?>);
						},
						data: function(params) {
							return {
								term: params.term || ''
							};
						},
						processResults: function(data) {
							var results = Array.isArray(data) ? data : (data.data || data);
							if (!results) {
								results = [];
							}
							// Disable options that already have A/B tests and add suffix
							results = results.map(function(item) {
								var itemId = parseInt(item.id, 10);
								if (orderBumpsWithTests.indexOf(itemId) !== -1) {
									item.disabled = true;
									var testTitle = orderBumpTestTitles[itemId] || '';
									item.text = item.text + ' (' + existingTestLabel + ' ' + testTitle + ')';
								}
								return item;
							});
							return {
								results: results
							};
						},
						cache: true
					},
					minimumInputLength: 0
				});

				function checkOrderBumpsExist() {
					$.ajax({
						url: restBaseUrl + '/order-bumps',
						dataType: 'json',
						type: 'GET',
						headers: {
							'X-WP-Nonce': <?php echo json_encode( $nonce ); ?>
						},
						data: {
							term: ''
						},
						success: function(data) {
							var results = Array.isArray(data) ? data : (data.data || data);
							var hasOrderBumps = results && results.length > 0;

							if (!hasOrderBumps) {
								showNoOrderBumpsNotice();
							} else {
								// If there are order bumps, check if selected bump has variants
								var selectedId = orderBumpSelect.val();
								if (selectedId) {
									checkOrderBumpHasVariants(selectedId);
								} else {
									hideNotices();
								}
							}
						},
						error: function() {
							hideNotices();
						}
					});
				}

				function checkOrderBumpHasVariants(parentId) {
					if (!parentId) {
						hideNotices();
						return;
					}

					$.ajax({
						url: restBaseUrl + '/order-bumps/' + parentId + '/variants',
						dataType: 'json',
						type: 'GET',
						headers: {
							'X-WP-Nonce': <?php echo json_encode( $nonce ); ?>
						},
						success: function(data) {
							var results = Array.isArray(data) ? data : (data.data || data);
							var hasVariants = results && results.length > 0;

							if (!hasVariants) {
								hideNotices();
							} else {
								hideNotices();
							}
						},
						error: function() {
							hideNotices();
						}
					});
				}

				function showNoOrderBumpsNotice() {
					var noticeHtml = '<div class="notice notice-warning inline" style="margin: 10px 0 0 0;">' +
						'<p>' +
						<?php echo json_encode( __( 'You have not yet created any order bumps.', 'checkout-wc' ) ); ?> + ' ' +
						'<a href="' + createBumpUrl + '">' +
						<?php echo json_encode( __( 'Create an order bump', 'checkout-wc' ) ); ?> +
						' &rarr;</a>' +
						'</p>' +
						'</div>';
					orderBumpNoticesContainer.html(noticeHtml);
				}

				function hideNotices() {
					orderBumpNoticesContainer.empty();
				}

				// If there was a selected value, restore it after initialization
				if (selectedValue && selectedText) {
					setTimeout(function() {
						var selectedId = parseInt(selectedValue, 10);
						var optionText = selectedText;
						// Add suffix if this order bump has a test (but allow it to be selected if it's the current test)
						if (orderBumpsWithTests.indexOf(selectedId) !== -1) {
							// Only add suffix if not already present
							if (optionText.indexOf('(' + existingTestLabel) === -1) {
								var testTitle = orderBumpTestTitles[selectedId] || '';
								optionText = optionText + ' (' + existingTestLabel + ' ' + testTitle + ')';
							}
						}
						var newOption = new Option(optionText, selectedValue, true, true);
						// Note: We don't disable it if it's already selected (current test)
						orderBumpSelect.append(newOption);
						orderBumpSelect.trigger('change');

						// Check for notices after restoring selection
						checkOrderBumpsExist();
					}, 100);
				} else {
					// Check for notices on page load if no selection
					checkOrderBumpsExist();
				}

				function loadVariantsGrid(orderBumpId, preservePercentages, suppressErrors) {
					if (!orderBumpId) {
						variantsGrid.html('<p class="description"><?php echo esc_js( __( 'Select an order bump to view and manage variants.', 'checkout-wc' ) ); ?></p>');
						variantsGrid.attr('data-order-bump-id', '');
						return;
					}

					// Capture current percentage values if preserving
					var preservedPercentages = null;
					if (preservePercentages && trafficSplitType.val() === 'custom') {
						preservedPercentages = {};
						$('.cfw-traffic-split-percentage').each(function() {
							var $input = $(this);
							var itemId = $input.attr('name').match(/\[(\d+)\]/);
							if (itemId && itemId[1]) {
								var value = $input.val();
								if (value !== '' && value !== null && value !== undefined) {
									preservedPercentages[itemId[1]] = parseInt(value) || 0;
								}
							}
						});
					}

					variantsGrid.html('<p class="description"><?php echo esc_js( __( 'Loading variants...', 'checkout-wc' ) ); ?></p>');

					$.ajax({
						url: ajaxUrl,
						type: 'POST',
						data: {
							action: 'cfw_order_bumps_ab_test_get_variants_grid',
							order_bump_id: orderBumpId,
							test_id: testId,
							nonce: ajaxNonce
						},
						success: function(response) {
							if (response.success && response.data && response.data.html) {
								variantsGrid.html(response.data.html);
								variantsGrid.attr('data-order-bump-id', orderBumpId);

								// Update traffic split fields after grid loads
								setTimeout(function() {
									updateTrafficSplitFields();
									if (trafficSplitType.val() === 'custom') {
										updateTrafficSplitPercentageFields();

										// Restore preserved percentage values if they exist
										if (preservedPercentages) {
											Object.keys(preservedPercentages).forEach(function(itemId) {
												var $input = $('#cfw_ab_test_traffic_split_' + itemId);
												if ($input.length) {
													$input.val(preservedPercentages[itemId]);
												}
											});
											validateTrafficSplitPercentages();
										}
									}
								}, 100);
							} else {
								if (!suppressErrors) {
									variantsGrid.html('<p class="description"><?php echo esc_js( __( 'Error loading variants.', 'checkout-wc' ) ); ?></p>');
								}
							}
						},
						error: function() {
							if (!suppressErrors) {
								variantsGrid.html('<p class="description"><?php echo esc_js( __( 'Error loading variants.', 'checkout-wc' ) ); ?></p>');
							}
						}
					});
				}

				// Show/hide variants row and load variants grid when order bump changes
				orderBumpSelect.on('change', function() {
					var parentId = $(this).val();

					if (parentId) {
						variantsRow.show();

						// Hide traffic split fields when order bump changes (will show again when variants are loaded)
						trafficSplitRow.hide();
						trafficSplitCustomRow.hide();

						// Load variants grid via AJAX
						loadVariantsGrid(parentId);

						// Check if selected order bump has variants
						checkOrderBumpHasVariants(parentId);
					} else {
						// Hide variants row when no order bump selected or invalid bump selected
						variantsRow.hide();
						trafficSplitRow.hide();
						trafficSplitCustomRow.hide();
						variantsGrid.html('<p class="description"><?php echo esc_js( __( 'Select an order bump to view and manage variants.', 'checkout-wc' ) ); ?></p>');
						variantsGrid.attr('data-order-bump-id', '');

						// Check if there are any order bumps when selection is cleared
						checkOrderBumpsExist();
					}
				});

				// Handle delete variant button click (using event delegation for dynamically loaded content)
				$(document).on('click', '.cfw-variant-delete-button', function(e) {
					e.preventDefault();
					var $button = $(this);
					var variantId = $button.data('variant-id');
					var orderBumpId = variantsGrid.attr('data-order-bump-id');

					if (!variantId) {
						return;
					}

					var confirmMessage = <?php echo json_encode( __( 'Are you sure you want to delete this variant? This action cannot be undone. If using a custom traffic split, the traffic percentages will be redistributed proportionally across the remaining variants.', 'checkout-wc' ) ); ?>;
					if (!confirm(confirmMessage)) {
						return;
					}

					$button.prop('disabled', true);

					$.ajax({
						url: ajaxUrl,
						type: 'POST',
						data: {
							action: "cfw_order_bumps_ab_test_delete_variant",
							variant_id: variantId,
							order_bump_id: orderBumpId,
							test_id: testId,
							nonce: ajaxNonce
						},
						success: function(response) {
							if (response.success) {
								// Update traffic split percentages if provided
								// Use both .attr() and .data() to ensure jQuery's cache is updated
								if (response.data && response.data.percentages) {
									var percentagesJson = JSON.stringify(response.data.percentages);
									trafficSplitPercentages.attr('data-saved-percentages', percentagesJson);
									trafficSplitPercentages.data('saved-percentages', response.data.percentages);
								}

								// Reload the variants grid
								if (orderBumpId) {
									// Store updated percentages for use after grid loads
									var updatedPercentages = response.data && response.data.percentages ? response.data.percentages : null;

									loadVariantsGrid(orderBumpId);

									// Update traffic split percentage fields after grid loads
									// Use a longer timeout (300ms) to ensure grid has fully loaded and loadVariantsGrid's update (100ms) has completed
									if (trafficSplitType.val() === 'custom' && updatedPercentages) {
										setTimeout(function() {
											// Ensure data is still set (in case loadVariantsGrid cleared it)
											if (updatedPercentages) {
												trafficSplitPercentages.attr('data-saved-percentages', JSON.stringify(updatedPercentages));
												trafficSplitPercentages.data('saved-percentages', updatedPercentages);
											}
											updateTrafficSplitPercentageFields();
											validateTrafficSplitPercentages();
										}, 300);
									}
								}
							} else {
								alert(response.data && response.data.message ? response.data.message : <?php echo json_encode( __( 'Failed to delete variant.', 'checkout-wc' ) ); ?>);
								$button.prop('disabled', false);
							}
						},
						error: function() {
							alert(<?php echo json_encode( __( 'An error occurred while deleting the variant.', 'checkout-wc' ) ); ?>);
							$button.prop('disabled', false);
						}
					});
				});

				// Function to open variant editor in a thickbox modal
				function openVariantEditorPopup(url, orderBumpId) {
					// Prepare URL for thickbox iframe
					var separator = url.indexOf('?') !== -1 ? '&' : '?';
					var thickboxUrl = url + separator + 'TB_iframe=true&width=1200&height=800';

					// Store orderBumpId for use when modal closes
					if (orderBumpId) {
						// Remove any existing handler to avoid duplicates
						$(document.body).off('thickbox:removed.cfw_variant_editor');
						// Add handler for when thickbox closes
						$(document.body).one('thickbox:removed.cfw_variant_editor', function() {
							// Reload variants grid when modal is closed, preserving percentages
							loadVariantsGrid(orderBumpId, true);
						});
					}

					// Open thickbox modal with iframe
					tb_show(
						<?php echo json_encode( __( 'Edit Variant', 'checkout-wc' ) ); ?>,
						thickboxUrl
					);
				}

				// Handle edit variant button click (using event delegation for dynamically loaded content)
				$(document).on('click', '.cfw-edit-variant-button', function(e) {
					e.preventDefault();
					var $button = $(this);
					var editUrl = $button.data('edit-url');
					var orderBumpId = orderBumpSelect.val();

					if (!editUrl) {
						return;
					}

					// Open edit page in thickbox modal
					openVariantEditorPopup(editUrl, orderBumpId);
				});

				// Handle create variant button click (using event delegation for dynamically loaded content)
				$(document).on('click', '.cfw-variant-card-create-button', function(e) {
					e.preventDefault();
					var $button = $(this);
					var testId = $button.data('test-id');

					if (!testId) {
						return;
					}

					// Get order bump ID from the select field
					var orderBumpId = orderBumpSelect.val();

					// Prompt for variant title
					var variantTitle = prompt(<?php echo json_encode( __( 'Enter a title for this variant:', 'checkout-wc' ) ); ?>);

					if (!variantTitle || variantTitle.trim() === '') {
						return;
					}

					// Disable button during creation
					$button.prop('disabled', true);
					$button.find('.cfw-variant-card-create-text').text(<?php echo json_encode( __( 'Creating...', 'checkout-wc' ) ); ?>);

					$.ajax({
						url: ajaxUrl,
						type: 'POST',
						data: {
							action: "cfw_order_bumps_ab_test_create_variant",
							test_id: testId,
							order_bump_id: orderBumpId,
							variant_title: variantTitle.trim(),
							nonce: ajaxNonce
						},
						success: function(response) {
								if (response.success && response.data && response.data.edit_url) {
									// Store orderBumpId for use when modal closes
									if (orderBumpId) {
										// Remove any existing handler to avoid duplicates
										$(document.body).off('thickbox:removed.cfw_variant_editor');
										// Add handler for when thickbox closes
										$(document.body).one('thickbox:removed.cfw_variant_editor', function() {
											// Reload variants grid when modal is closed, preserving percentages
											loadVariantsGrid(orderBumpId, true);
										});
									}

								// Prepare URL for thickbox iframe
								var editUrl = response.data.edit_url;
								var separator = editUrl.indexOf('?') !== -1 ? '&' : '?';
								var thickboxUrl = editUrl + separator + 'TB_iframe=true&width=1200&height=800';

								// Open thickbox modal with iframe
								tb_show(
									<?php echo json_encode( __( 'Edit Variant', 'checkout-wc' ) ); ?>,
									thickboxUrl
								);

								// Re-enable button
								$button.prop('disabled', false);
								$button.find('.cfw-variant-card-create-text').text(<?php echo json_encode( __( 'Create Variant', 'checkout-wc' ) ); ?>);
							} else {
								alert(response.data && response.data.message ? response.data.message : <?php echo json_encode( __( 'Failed to create variant.', 'checkout-wc' ) ); ?>);
								$button.prop('disabled', false);
								$button.find('.cfw-variant-card-create-text').text(<?php echo json_encode( __( 'Create Variant', 'checkout-wc' ) ); ?>);
							}
						},
						error: function() {
							alert(<?php echo json_encode( __( 'An error occurred while creating the variant.', 'checkout-wc' ) ); ?>);
							$button.prop('disabled', false);
							$button.find('.cfw-variant-card-create-text').text(<?php echo json_encode( __( 'Create Variant', 'checkout-wc' ) ); ?>);
						}
					});
				});

				// Handle select winner button click (using event delegation for dynamically loaded content)
				$(document).on('click', '.cfw-select-winner-button', function(e) {
					e.preventDefault();
					var $button = $(this);
					var variantId = $button.data('variant-id');
					var isOriginal = $button.data('is-original') === 1;

					if (!variantId) {
						return;
					}

					// Create thickbox modal content
					var modalContent = '<div style="padding: 0 20px;">' +
						'<p>' + <?php echo json_encode( __( 'Selecting this variant as the winner will update the original order bump to match it and delete variants.', 'checkout-wc' ) ); ?> + '</p>' +
						'<p>' +
						'<label>' +
						'<input type="checkbox" id="cfw-apply-stats-checkbox" value="1" checked> ' +
						<?php echo json_encode( __( 'Apply this variant\'s conversion stats to the original order bump.', 'checkout-wc' ) ); ?> +
						'</label>' +
						'</p>' +
						'<p style="margin-top: 20px;">' +
						'<button type="button" class="button button-primary" id="cfw-confirm-select-winner">' +
						<?php echo json_encode( __( 'Select Winner', 'checkout-wc' ) ); ?> +
						'</button> ' +
						'<button type="button" class="button" onclick="tb_remove();">' +
						<?php echo json_encode( __( 'Cancel', 'checkout-wc' ) ); ?> +
						'</button>' +
						'</p>' +
						'</div>';

					// Create hidden div for thickbox
					var modalId = 'cfw-select-winner-modal';
					if ($('#' + modalId).length === 0) {
						$('body').append('<div id="' + modalId + '" style="display:none;">' + modalContent + '</div>');
					} else {
						$('#' + modalId).html(modalContent);
					}

					// Open thickbox
					tb_show(
						<?php echo json_encode( __( 'Select Winner', 'checkout-wc' ) ); ?>,
						'#TB_inline?inlineId=' + modalId + '&width=500&height=180'
					);

					// Handle confirm button click
					$('#cfw-confirm-select-winner').off('click').on('click', function() {
						var applyStats = $('#cfw-apply-stats-checkbox').is(':checked') ? '1' : '0';

						// Disable button during request
						$(this).prop('disabled', true);

						$.ajax({
							url: ajaxUrl,
							type: 'POST',
							data: {
								action: "cfw_order_bumps_ab_test_select_winner",
								variant_id: variantId,
								test_id: testId,
								apply_stats: applyStats,
								nonce: ajaxNonce
							},
							success: function(response) {
								if (response.success) {
									// Close thickbox
									tb_remove();

									// Reload the variants grid (suppress errors since we're about to reload the page)
									var orderBumpId = variantsGrid.attr('data-order-bump-id');
									if (orderBumpId) {
										loadVariantsGrid(orderBumpId, false, true);
									}

									// Reload page to update meta boxes
									location.reload();
								} else {
									alert(response.data && response.data.message ? response.data.message : <?php echo json_encode( __( 'Failed to select winner.', 'checkout-wc' ) ); ?>);
									$('#cfw-confirm-select-winner').prop('disabled', false);
								}
							},
							error: function() {
								alert(<?php echo json_encode( __( 'An error occurred while setting the winner.', 'checkout-wc' ) ); ?>);
								$('#cfw-confirm-select-winner').prop('disabled', false);
							}
						});
					});
				});

				// Handle traffic split type change
				trafficSplitType.on('change', function() {
					if ($(this).val() === 'custom') {
						trafficSplitCustomRow.show();
						updateTrafficSplitPercentageFields();
					} else {
						trafficSplitCustomRow.hide();
					}
				});

				// Validate percentage totals
				$(document).on('input', '.cfw-traffic-split-percentage', function() {
					validateTrafficSplitPercentages();
				});

				// Prevent form submission if percentages don't add up to 100%
				$('#post').on('submit', function(e) {
					if (trafficSplitType.val() === 'custom') {
						var total = 0;
						$('.cfw-traffic-split-percentage').each(function() {
							var value = parseInt($(this).val()) || 0;
							total += value;
						});

						if (total !== 100) {
							e.preventDefault();
							alert(<?php echo json_encode( __( 'Traffic split percentages must total exactly 100%. Please adjust your percentages before saving.', 'checkout-wc' ) ); ?>);
							$('.cfw-traffic-split-percentage').addClass('cfw-traffic-split-error');
							return false;
						}
					}
				});

				// Function to update traffic split fields visibility
				function updateTrafficSplitFields() {
					var parentId = orderBumpSelect.val();
					// Check if there are any variant cards (excluding original and create card)
					var hasVariants = variantsGrid.find('.cfw-variant-card:not(.cfw-variant-card-original):not(.cfw-variant-card-create)').length > 0;

					if (hasVariants && parentId) {
						trafficSplitRow.show();
						if (trafficSplitType.val() === 'custom') {
							updateTrafficSplitPercentageFields();
							trafficSplitCustomRow.show();
						}
					} else {
						trafficSplitRow.hide();
						trafficSplitCustomRow.hide();
					}
				}

				// Function to update percentage fields based on variants in grid
				function updateTrafficSplitPercentageFields() {
					var parentId = orderBumpSelect.val();

					if (!parentId) {
						trafficSplitPercentages.empty();
						return;
					}

					// Get saved percentages from data attribute
					var savedPercentages = {};
					try {
						var savedPercentagesData = trafficSplitPercentages.data('saved-percentages');
						if (savedPercentagesData) {
							savedPercentages = typeof savedPercentagesData === 'string'
								? JSON.parse(savedPercentagesData)
								: savedPercentagesData;
						}
					} catch(e) {
						console.error('Error parsing saved percentages:', e);
					}

					// Get parent order bump data
					var parentData = null;
					var parentOption = orderBumpSelect.find('option:selected');
					if (parentOption.length > 0) {
						parentData = {
							id: parentOption.val(),
							text: parentOption.text()
						};
					}

					// Get variant data from grid cards (excluding original and create cards)
					var variantData = [];
					variantsGrid.find('.cfw-variant-card:not(.cfw-variant-card-original):not(.cfw-variant-card-create)').each(function() {
						var $card = $(this);
						var variantId = $card.data('variant-id');
						var variantTitle = $card.find('h3').text();
						if (variantId && variantTitle) {
							variantData.push({
								id: variantId,
								text: variantTitle
							});
						}
					});

					// Clear existing fields
					trafficSplitPercentages.empty();

					// Create percentage input for parent order bump (first)
					if (parentData) {
						// Handle 0 values properly - check if key exists, not just truthy value
						var existingValue = '';
						if (parentData.id in savedPercentages) {
							existingValue = savedPercentages[parentData.id] !== null && savedPercentages[parentData.id] !== undefined
								? savedPercentages[parentData.id]
								: '';
						}

						var p = $('<p></p>');
						var label = $('<label></label>')
							.attr('for', 'cfw_ab_test_traffic_split_' + parentData.id)
							.text(parentData.text + ' <?php echo esc_js( __( '(Original)', 'checkout-wc' ) ); ?>:');
						var br = $('<br>');
						var input = $('<input>')
							.attr('type', 'number')
							.attr('name', 'cfw_ab_test_traffic_split_percentages[' + parentData.id + ']')
							.attr('id', 'cfw_ab_test_traffic_split_' + parentData.id)
							.attr('value', existingValue)
							.attr('min', '0')
							.attr('max', '100')
							.addClass('cfw-traffic-split-percentage');
						var percent = $('<span></span>').text('%');

						p.append(label).append(br).append(input).append(percent);
						trafficSplitPercentages.append(p);
					}

					// Create percentage input for each variant
					variantData.forEach(function(variant) {
						// Handle 0 values properly - check if key exists, not just truthy value
						var existingValue = '';
						if (variant.id in savedPercentages) {
							existingValue = savedPercentages[variant.id] !== null && savedPercentages[variant.id] !== undefined
								? savedPercentages[variant.id]
								: '';
						}

						var p = $('<p></p>');
						var label = $('<label></label>')
							.attr('for', 'cfw_ab_test_traffic_split_' + variant.id)
							.text(variant.text + ':');
						var br = $('<br>');
						var input = $('<input>')
							.attr('type', 'number')
							.attr('name', 'cfw_ab_test_traffic_split_percentages[' + variant.id + ']')
							.attr('id', 'cfw_ab_test_traffic_split_' + variant.id)
							.attr('value', existingValue)
							.attr('min', '0')
							.attr('max', '100')
							.addClass('cfw-traffic-split-percentage');
						var percent = $('<span></span>').text('%');

						p.append(label).append(br).append(input).append(percent);
						trafficSplitPercentages.append(p);
					});

					validateTrafficSplitPercentages();
				}

				// Function to validate percentage totals
				function validateTrafficSplitPercentages() {
					var total = 0;
					$('.cfw-traffic-split-percentage').each(function() {
						var value = parseInt($(this).val()) || 0;
						total += value;
					});

					if (total === 100) {
						$('.cfw-traffic-split-percentage').removeClass('cfw-traffic-split-error');
					} else {
						$('.cfw-traffic-split-percentage').addClass('cfw-traffic-split-error');
					}
				}
			}

			// Load variants grid on page load if order bump is already selected
			if (orderBumpSelect.length && orderBumpSelect.val()) {
				var initialParentId = orderBumpSelect.val();
				if (initialParentId) {
					variantsRow.show();
					loadVariantsGrid(initialParentId);
				}
			}
		});
		</script>
		<?php
	}
}
