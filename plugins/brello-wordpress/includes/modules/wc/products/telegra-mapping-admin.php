<?php
/**
 * Read-only Telegra mapping on the product edit screen.
 *
 * The mapping is edited only in Telegra > Telegra Product Variation. This shows it
 * where the product is edited: a box per variation of a variable subscription with
 * the Telegra product, dispensing quantity and project ID, plus a notice when one of
 * those variations has no mapping. Other product types show nothing. Telegra names load after the page through AJAX, so the
 * product screen never waits on the Telegra API; IDs show when names are unavailable.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AH_Telegra_Mapping_Admin' ) ) {

	class AH_Telegra_Mapping_Admin {

		const AJAX_ACTION = 'bh_telegra_product_names';

		public function __construct() {
			add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'render_variation_box' ), 40, 3 );
			add_action( 'admin_notices', array( $this, 'unmapped_notice' ) );
			add_action( 'admin_head', array( $this, 'print_styles' ) );
			add_action( 'admin_footer', array( $this, 'print_script' ) );
			add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_names' ) );
		}

		/**
		 * @param int     $loop           Variation loop index.
		 * @param array   $variation_data Variation data.
		 * @param WP_Post $variation      Variation post.
		 */
		public function render_variation_box( $loop, $variation_data, $variation ) {
			if ( ! $this->is_variable_subscription( (int) $variation->post_parent ) ) {
				return;
			}
			// WooCommerce has no hook under the description; the script moves the box there.
			echo '<div class="form-row form-row-full bh-telegra-variation-box" data-loop="' . esc_attr( $loop ) . '">';
			$this->render_box( (int) $variation->ID );
			echo '</div>';
		}

		/**
		 * Only variations of variable subscriptions show the mapping.
		 */
		private function is_variable_subscription( int $product_id ): bool {
			return $product_id > 0 && 'variable-subscription' === WC_Product_Factory::get_product_type( $product_id );
		}

		/**
		 * Notice on the product edit screen when a variation has no Telegra mapping.
		 */
		public function unmapped_notice() {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( ! $screen || 'product' !== $screen->id || 'post' !== $screen->base ) {
				return;
			}

			global $post;
			$product = $post ? wc_get_product( $post->ID ) : null;
			if ( ! $product || ! $product->is_type( 'variable-subscription' ) ) {
				return;
			}

			$ids = $product->get_children();
			_prime_post_caches( $ids, false, true ); // Posts and meta of all variations in two queries.

			$unmapped = array_filter(
				$ids,
				function ( $id ) {
					return 'not_mapped' === bh_get_telegra_mapping( (int) $id )['status'];
				}
			);
			if ( ! $unmapped ) {
				return;
			}

			echo '<div class="notice notice-warning"><p>' . esc_html(
				sprintf(
					/* translators: %s: variation IDs */
					__( 'Variations not mapped to a Telegra product: #%s. Orders for them reach Telegra without a product.', 'bh-features' ),
					implode( ', #', $unmapped )
				)
			) . ' <a href="' . esc_url( $this->settings_url() ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Map in Telegra settings', 'bh-features' ) . '</a></p></div>';
		}

		/**
		 * Fills the Telegra names into the boxes, also after WooCommerce loads the variations.
		 */
		public function print_script() {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( ! $screen || 'product' !== $screen->id ) {
				return;
			}
			?>
			<script>
			( function ( $ ) {
				var names = null, loading = null;
				var request = { action: <?php echo wp_json_encode( self::AJAX_ACTION ); ?>, nonce: <?php echo wp_json_encode( wp_create_nonce( self::AJAX_ACTION ) ); ?> };

				function fill() {
					$( '.bh-telegra-name[data-pvt]' ).each( function () {
						var id = $( this ).data( 'pvt' );
						if ( names && names[ id ] ) {
							$( this ).text( names[ id ] );
						} else if ( names ) {
							$( this ).html( '<span class="bh-tg__missing"><?php echo esc_js( __( 'Not found in Telegra', 'bh-features' ) ); ?></span>' );
						}
					} );
				}

				function load() {
					if ( loading ) {
						return loading.done( fill );
					}
					$( '.bh-telegra-status' ).text( '<?php echo esc_js( __( 'Loading Telegra names…', 'bh-features' ) ); ?>' );
					loading = $.post( ajaxurl, request ).done( function ( res ) {
						if ( res && res.success ) {
							names = res.data;
							$( '.bh-telegra-status' ).text( '' );
						} else {
							$( '.bh-telegra-status' ).text( ( res && res.data ) || '<?php echo esc_js( __( 'Telegra did not answer. Showing IDs only.', 'bh-features' ) ); ?>' );
						}
						fill();
					} ).fail( function () {
						$( '.bh-telegra-status' ).text( '<?php echo esc_js( __( 'Telegra did not answer. Showing IDs only.', 'bh-features' ) ); ?>' );
					} );
				}

				// Right under the variation description.
				function place() {
					$( '.bh-telegra-variation-box[data-loop]' ).each( function () {
						var description = $( this ).closest( '.woocommerce_variation' ).find( '.variable_description' + $( this ).data( 'loop' ) + '_field' ).parent();
						if ( description.length ) {
							$( this ).insertAfter( description );
						}
					} );
				}

				// Ask Telegra only when a mapped variation needs its name.
				function loadIfNeeded() {
					if ( $( '.bh-telegra-name[data-pvt]' ).length ) {
						load();
					}
				}

				$( loadIfNeeded );
				$( document.body ).on( 'woocommerce_variations_loaded woocommerce_variations_added', function () {
					place();
					loadIfNeeded();
				} );
			} )( jQuery );
			</script>
			<?php
		}

		/**
		 * AJAX: Telegra names keyed by ID.
		 */
		public function ajax_names() {
			check_ajax_referer( self::AJAX_ACTION, 'nonce' );
			if ( ! current_user_can( 'edit_products' ) ) {
				wp_send_json_error( __( 'Not allowed.', 'bh-features' ), 403 );
			}

			$names = bh_get_telegra_product_names();
			if ( is_wp_error( $names ) ) {
				wp_send_json_error( $names->get_error_message() );
			}

			wp_send_json_success( $names );
		}

		/**
		 * Styles of the mapping card, product edit screen only.
		 */
		public function print_styles() {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( ! $screen || 'product' !== $screen->id ) {
				return;
			}
			?>
			<style>
			.bh-tg{--bh-tg-dot:#a7aaad;border:1px solid #efefef;border-radius:4px;background:#fff;margin:12px 0 4px}
			.bh-tg--mapped{--bh-tg-dot:#68de7c}.bh-tg--not_mapped{--bh-tg-dot:#f86368}
			.bh-tg__head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:8px 12px;background:#efefef;border-radius:4px 4px 0 0}
			.bh-tg__title{display:flex;align-items:center;gap:6px;margin:0;font-size:12px;font-weight:600;color:#3c434a;text-transform:uppercase;letter-spacing:.04em}
			.bh-tg__edit{display:inline-flex;color:#787c82;text-decoration:none}
			.bh-tg__edit:hover,.bh-tg__edit:focus{color:#1d2327}
			.bh-tg__edit .dashicons{font-size:14px;width:14px;height:14px}
			.bh-tg__pill{display:inline-flex;align-items:center;gap:6px;font-size:12px;color:#50575e}
			.bh-tg__pill::before{content:"";width:7px;height:7px;border-radius:50%;background:var(--bh-tg-dot)}
			.bh-tg__body{padding:10px 12px}
			.bh-tg__grid{display:grid;grid-template-columns:140px minmax(0,1fr);gap:6px 16px;margin:0}
			.bh-tg__grid dt{margin:0;color:#787c82;font-size:12px;line-height:20px}
			.bh-tg__grid dd{margin:0;color:#1d2327;font-size:13px;line-height:20px;min-width:0}
			.bh-tg__name{display:block;word-break:break-word}
			.bh-tg__code{display:inline-block;margin-top:2px;padding:0 5px;border-radius:3px;background:#efefef;color:#787c82;font-size:11px;word-break:break-all}
			.bh-tg__empty{color:#a7aaad}
			.bh-tg__missing{color:#50575e;font-style:italic}
			.bh-tg__status{display:block;color:#787c82;font-size:12px;font-style:italic}.bh-tg__status:empty{display:none}
			@media (max-width:782px){.bh-tg__grid{grid-template-columns:1fr;gap:2px}.bh-tg__grid dd{margin-bottom:8px}}
			</style>
			<?php
		}

		/**
		 * @param int $product_id Product or variation ID.
		 */
		private function render_box( int $product_id ) {
			$mapping = bh_get_telegra_mapping( $product_id );
			$labels  = array(
				'mapped'        => __( 'Mapped', 'bh-features' ),
				'not_mapped'    => __( 'Not mapped', 'bh-features' ),
				'no_processing' => __( 'No processing', 'bh-features' ),
			);
			$edit    = __( 'Edit in Telegra settings', 'bh-features' );
			?>
			<div class="bh-tg bh-tg--<?php echo esc_attr( $mapping['status'] ); ?>">
				<div class="bh-tg__head">
					<p class="bh-tg__title">
						<?php esc_html_e( 'Telegra mapping', 'bh-features' ); ?>
						<a class="bh-tg__edit" href="<?php echo esc_url( $this->settings_url() ); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo esc_attr( $edit ); ?>" aria-label="<?php echo esc_attr( $edit ); ?>"><span class="dashicons dashicons-edit" aria-hidden="true"></span></a>
					</p>
					<span class="bh-tg__pill"><?php echo esc_html( $labels[ $mapping['status'] ] ); ?></span>
				</div>
				<div class="bh-tg__body">
					<dl class="bh-tg__grid">
						<dt><?php esc_html_e( 'Telegra product', 'bh-features' ); ?></dt>
						<dd>
							<?php if ( 'mapped' === $mapping['status'] ) : ?>
								<span class="bh-tg__name bh-telegra-name" data-pvt="<?php echo esc_attr( $mapping['product_variation'] ); ?>"><?php echo esc_html( $mapping['product_variation'] ); ?></span>
								<code class="bh-tg__code"><?php echo esc_html( $mapping['product_variation'] ); ?></code>
								<span class="bh-tg__status bh-telegra-status"></span>
							<?php else : ?>
								<span class="bh-tg__empty">&mdash;</span>
							<?php endif; ?>
						</dd>
						<dt><?php esc_html_e( 'Dispensing quantity', 'bh-features' ); ?></dt>
						<dd><?php echo '' !== $mapping['quantity'] ? esc_html( $mapping['quantity'] ) : '<span class="bh-tg__empty">&mdash;</span>'; ?></dd>
						<dt><?php esc_html_e( 'Project ID', 'bh-features' ); ?></dt>
						<dd><?php echo '' !== $mapping['project_id'] ? '<code class="bh-tg__code">' . esc_html( $mapping['project_id'] ) . '</code>' : '<span class="bh-tg__empty">&mdash;</span>'; ?></dd>
					</dl>
				</div>
			</div>
			<?php
		}

		private function settings_url(): string {
			return admin_url( 'admin.php?page=telemdnow' );
		}
	}

	new AH_Telegra_Mapping_Admin();
}
