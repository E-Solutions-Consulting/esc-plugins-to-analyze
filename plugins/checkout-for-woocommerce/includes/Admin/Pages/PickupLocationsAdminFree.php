<?php

namespace Objectiv\Plugins\Checkout\Admin\Pages;

use Objectiv\Plugins\Checkout\Features\LocalPickup;
use WP_Post;

/**
 * @link checkoutwc.com
 * @since 7.3.0
 * @package Objectiv\Plugins\Checkout\Admin\Pages
 */
class PickupLocationsAdminFree extends PageAbstract {
	protected $post_type_slug;
	protected $nonce_field  = '_cfw_pl_nonce';
	protected $nonce_action = 'cfw_save_pl_mb';
	protected $formatted_required_plans_list;
	protected $is_available;

	public function __construct( string $post_type_slug, bool $is_available ) {
		parent::__construct( __( 'Pickup Locations', 'checkout-wc' ), 'cfw_manage_local_pickup', null );

		$this->post_type_slug = $post_type_slug;
		$this->slug           = 'edit.php?post_type=' . $this->post_type_slug;
		$this->is_available   = $is_available;
	}

	public function init() {
		parent::init();

		add_action( 'add_meta_boxes', [ $this, 'register_meta_boxes' ] );
		add_action( 'save_post', [ $this, 'save_metaboxes' ] );
		add_action( 'all_admin_notices', [ $this, 'output_with_wrap' ] );
		add_action( 'all_admin_notices', [ $this, 'maybe_show_license_upgrade_splash' ] );

		/**
		 * Highlights Pickup Locations submenu item when
		 * on the New Pickup Locations admin page
		 */
		add_filter( 'submenu_file', [ $this, 'maybe_highlight_pickup_locations_submenu_item' ] );
	}

	public function get_url(): string {
		$url = admin_url( $this->slug );

		return esc_url( $url );
	}

	public function setup_menu() {
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

	public function register_meta_boxes() {
		add_meta_box( 'cfw_order_bump_products_mb', __( 'Pickup Location Details', 'checkout-wc' ), [ $this, 'render_meta_box' ], $this->post_type_slug );
	}

	/**
	 * Render the meta box
	 *
	 * @param WP_Post $post The post object.
	 */
	public function render_meta_box( WP_Post $post ) {
		$cfw_pl_address        = get_post_meta( $post->ID, 'cfw_pl_address', true );
		$cfw_pl_instructions   = get_post_meta( $post->ID, 'cfw_pl_instructions', true );
		$cfw_pl_estimated_time = get_post_meta( $post->ID, 'cfw_pl_estimated_time', true );

		wp_nonce_field( $this->nonce_action, $this->nonce_field );
		?>
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row" valign="top">
						<label for="cfw_pl_address">
							<?php _e( 'Address', 'checkout-wc' ); ?>
						</label>
					</th>
					<td>
						<?php
						wp_editor(
							$cfw_pl_address,
							sanitize_title_with_dashes( 'cfw_pl_address' ),
							[
								'textarea_rows' => 4,
								'quicktags'     => false,
								'media_buttons' => false,
								'textarea_name' => 'cfw_pl_address',
								'tinymce'       => false,
							]
						);
						?>
					</td>
				</tr>
				<tr>
					<th scope="row" valign="top">
						<label for="cfw_pl_estimated_time">
							<?php _e( 'Estimated Pickup Time', 'checkout-wc' ); ?>
						</label>
					</th>
					<td>
						<select style="width: 50%;" id="cfw_pl_estimated_time" name="cfw_pl_estimated_time">
							<?php foreach ( LocalPickup::get_pickup_times() as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $cfw_pl_estimated_time, true ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row" valign="top">
						<label for="cfw_pl_instructions">
							<?php _e( 'Pickup Instructions', 'checkout-wc' ); ?>
						</label>
					</th>
					<td>
						<?php
						wp_editor(
							$cfw_pl_instructions,
							sanitize_title_with_dashes( 'cfw_pl_instructions' ),
							[
								'textarea_rows' => 4,
								'quicktags'     => false,
								'media_buttons' => false,
								'textarea_name' => 'cfw_pl_instructions',
								'tinymce'       => false,
							]
						);
						?>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * @param int $post_id The post ID.
	 */
	public function save_metaboxes( int $post_id ) {
		$nonce_name = sanitize_text_field( wp_unslash( $_POST[ $this->nonce_field ] ?? '' ) );

		if ( ! wp_verify_nonce( $nonce_name, $this->nonce_action ) ) {
			return;
		}

		if ( ! current_user_can( 'cfw_manage_local_pickup', $post_id ) ) {
			return;
		}

		if ( wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		update_post_meta( $post_id, 'cfw_pl_address', sanitize_text_field( wp_unslash( $_POST['cfw_pl_address'] ?? '' ) ) );
		update_post_meta( $post_id, 'cfw_pl_instructions', sanitize_text_field( wp_unslash( $_POST['cfw_pl_instructions'] ?? '' ) ) );
		update_post_meta( $post_id, 'cfw_pl_estimated_time', sanitize_text_field( wp_unslash( $_POST['cfw_pl_estimated_time'] ?? '' ) ) );
	}

	public function is_current_page(): bool {
		global $post;

		if ( isset( $_GET['post_type'] ) && $this->post_type_slug === $_GET['post_type'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}

		if ( $post && $this->post_type_slug === $post->post_type ) {
			return true;
		}

		return false;
	}

	/**
	 * The admin page wrap
	 *
	 * @since 1.0.0
	 */
	public function output_with_wrap() {
		if ( ! $this->is_current_page() ) {
			return;
		}
		?>
		<div class="cfw-tw">
			<div id="cfw_admin_page_header" class="absolute left-0 right-0 top-0 divide-y shadow z-50">
				<?php cfw_do_action( 'cfw_before_admin_page_header', $this ); ?>
				<div class="min-h-[64px] bg-white flex items-center pl-8">
					<span>
						<?php echo file_get_contents( CFW_PATH . '/assets/images/cfw.svg' ); // phpcs:ignore  ?>
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
									<svg class="flex-shrink-0 h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
										<path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
									</svg>
									<span class="ml-2 text-sm font-medium text-gray-500" aria-current="page">
										<?php echo esc_html( $this->title ); // phpcs:ignore ?>
									</span>
								</div>
							</li>
						</ol>
					</nav>
				</div>
				<?php cfw_do_action( 'cfw_after_admin_page_header', $this ); ?>
			</div>
		</div>
		<?php
	}

	public function maybe_show_license_upgrade_splash() {
		if ( $this->is_current_page() && ! $this->is_available ) {
			echo wp_kses_post( $this->get_old_style_upgrade_required_notice( $this->formatted_required_plans_list ) );
		}
	}

	/**
	 * @param mixed $submenu_file The submenu file.
	 * @return mixed
	 */
	public function maybe_highlight_pickup_locations_submenu_item( $submenu_file ) {
		$post_type = $this->post_type_slug;

		if ( stripos( $_SERVER['REQUEST_URI'], "post-new.php?post_type=$post_type" ) !== false ) { // phpcs:ignore
			return $this->get_slug();
		}

		return $submenu_file;
	}

	public function output() {}
}
