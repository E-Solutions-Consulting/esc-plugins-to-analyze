<?php
/**
 * Variation settings for the shipping-state product replacement.
 *
 * On a variation of a variable subscription: "Replaces variation" (a sibling) and
 * "Exclusive for shipping states" (e.g. CA). Other product types show nothing: the
 * storefront hiding would make a plain variation unpurchasable. Such a variation
 * is hidden on the storefront and sold in place of the variation it replaces when
 * the order ships to one of its states.
 * The replaced variation shows a read-only note.
 *
 * Replacement variations carry the same name as the variation they replace, so
 * the admin marks them (e.g. "CA only") on order and subscription items.
 * Customers never see the mark.
 * See common/state-product-replacement-helpers.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AH_State_Replacement_Admin' ) ) {

	class AH_State_Replacement_Admin {

		public function __construct() {
			add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'render_fields' ), 60, 3 );
			add_action( 'woocommerce_save_product_variation', array( $this, 'save_fields' ), 10, 2 );
			add_action( 'admin_head', array( $this, 'print_card_style' ) );
			add_action( 'admin_footer', array( $this, 'print_card_script' ) );

			// Admin marks for replacement variations.
			add_action( 'woocommerce_after_order_itemmeta', array( $this, 'order_item_badge' ), 10, 3 );
			add_action( 'admin_head', array( $this, 'print_badge_style' ) );
		}

		/**
		 * Marks a replacement variation on order and subscription items (admin only).
		 *
		 * @param int                $item_id Item ID.
		 * @param WC_Order_Item      $item    Order item.
		 * @param WC_Product|null    $product Item product.
		 */
		public function order_item_badge( $item_id, $item, $product ) {
			// WooCommerce fires this hook only in the admin order views; never mark e-mails or customer pages.
			if ( ! is_admin() || ! current_user_can( 'edit_shop_orders' ) || doing_action( 'woocommerce_email_order_details' ) ) {
				return;
			}
			if ( ! $item instanceof WC_Order_Item_Product || ! $item->get_variation_id() ) {
				return;
			}

			$badge = $this->badge_html( (int) $item->get_variation_id() );
			if ( '' !== $badge ) {
				echo '<div class="bh-state-badge-wrap">' . $badge . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in badge_html().
			}
		}

		public function print_badge_style() {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			$ids    = array( 'shop_order', 'shop_subscription', 'woocommerce_page_wc-orders', 'woocommerce_page_wc-orders--shop_subscription' );
			if ( ! $screen || ! in_array( $screen->id, $ids, true ) ) {
				return;
			}
			echo '<style>.bh-state-badge-wrap{margin:4px 0 2px;display:flex;align-items:center;gap:6px;flex-wrap:wrap}.bh-state-badge{display:inline-block;padding:1px 8px;border-radius:999px;background:#fcf0e3;border:1px solid #f0b849;color:#8a4b00;font-size:11px;font-weight:600;line-height:18px;letter-spacing:.02em;text-transform:uppercase}.bh-state-badge-note{color:#646970;font-size:12px}</style>';
		}

		/**
		 * "CA ONLY" badge plus "Replaces #ID" for a replacement variation, empty for any other.
		 */
		private function badge_html( int $variation_id ): string {
			$config = bh_get_state_replacement_config( $variation_id );
			if ( $config['replaces'] <= 0 ) {
				return '';
			}

			return '<span class="bh-state-badge">' . esc_html(
				sprintf(
					/* translators: %s: state codes */
					__( '%s only', 'bh-features' ),
					implode( ', ', $config['states'] )
				)
			) . '</span><span class="bh-state-badge-note">' . esc_html(
				sprintf(
					/* translators: %d: variation ID */
					__( 'Replaces #%d', 'bh-features' ),
					$config['replaces']
				)
			) . '</span>';
		}

		/**
		 * @param int     $loop           Variation loop index.
		 * @param array   $variation_data Variation data.
		 * @param WP_Post $variation      Variation post.
		 */
		public function render_fields( $loop, $variation_data, $variation ) {
			if ( ! $this->is_variable_subscription_variation( (int) $variation->ID ) ) {
				return;
			}
			$config       = bh_get_state_replacement_config( (int) $variation->ID );
			$siblings     = $this->sibling_ids( (int) $variation->ID );
			$replacements = bh_get_state_replacements_of( (int) $variation->ID );

			if ( $config['replaces'] > 0 ) {
				$status = 'replacement';
				/* translators: %s: state codes */
				$pill = sprintf( __( '%s only', 'bh-features' ), implode( ', ', $config['states'] ) );
			} elseif ( $replacements ) {
				$status = 'replaced';
				/* translators: %s: state codes */
				$pill = sprintf( __( 'Replaced in %s', 'bh-features' ), implode( ', ', array_unique( array_merge( ...array_values( $replacements ) ) ) ) );
			} else {
				$status = 'regular';
				$pill   = __( 'Regular', 'bh-features' );
			}
			?>
			<div class="form-row form-row-full bh-state-replacement" data-loop="<?php echo esc_attr( $loop ); ?>">
				<div class="bh-sr bh-sr--<?php echo esc_attr( $status ); ?>">
					<div class="bh-sr__head">
						<p class="bh-sr__title"><span class="dashicons dashicons-location" aria-hidden="true"></span><?php esc_html_e( 'Shipping state replacement', 'bh-features' ); ?></p>
						<span class="bh-sr__pill"><?php echo esc_html( $pill ); ?></span>
					</div>
					<div class="bh-sr__body">
						<?php foreach ( $replacements as $replacement_id => $states ) : ?>
							<p class="bh-sr__note"><span class="dashicons dashicons-randomize" aria-hidden="true"></span>
								<?php
								echo esc_html(
									sprintf(
										/* translators: 1: state codes, 2: variation ID */
										__( 'Replaced in %1$s by #%2$d.', 'bh-features' ),
										implode( ', ', $states ),
										$replacement_id
									)
								);
								?>
							</p>
						<?php endforeach; ?>
						<div class="bh-sr__grid">
							<p class="bh-sr__field">
								<label for="bh_replaces_variation_<?php echo esc_attr( $loop ); ?>"><?php esc_html_e( 'Replaces variation', 'bh-features' ); ?></label>
								<select id="bh_replaces_variation_<?php echo esc_attr( $loop ); ?>" name="<?php echo esc_attr( 'bh_replaces_variation[' . $loop . ']' ); ?>">
									<option value=""><?php esc_html_e( '— None (regular variation) —', 'bh-features' ); ?></option>
									<?php
									foreach ( $siblings as $sibling_id ) :
										$sibling = wc_get_product( $sibling_id );
										$label   = '#' . $sibling_id . ( $sibling ? ' ' . wp_strip_all_tags( wc_get_formatted_variation( $sibling, true, false ) ) : '' );
										?>
										<option value="<?php echo esc_attr( $sibling_id ); ?>"<?php selected( $config['replaces'], $sibling_id ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</p>
							<p class="bh-sr__field">
								<label for="bh_exclusive_shipping_states_<?php echo esc_attr( $loop ); ?>"><?php esc_html_e( 'Exclusive for shipping states', 'bh-features' ); ?></label>
								<input type="text" id="bh_exclusive_shipping_states_<?php echo esc_attr( $loop ); ?>" name="<?php echo esc_attr( 'bh_exclusive_shipping_states[' . $loop . ']' ); ?>" value="<?php echo esc_attr( implode( ', ', $config['states'] ) ); ?>" placeholder="CA" />
							</p>
						</div>
					</div>
					<div class="bh-sr__foot">
						<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
						<span><?php esc_html_e( 'Set both to make this a hidden variation: it is not shown on the storefront and is sold instead of the selected variation when the order ships to one of these states (comma-separated state codes).', 'bh-features' ); ?></span>
					</div>
				</div>
			</div>
			<?php
		}

		/**
		 * Styles of the replacement card, product edit screen only.
		 */
		public function print_card_style() {
			if ( ! $this->is_product_screen() ) {
				return;
			}
			?>
			<style>
			.bh-sr{--bh-sr-dot:#a7aaad;border:1px solid #efefef;border-radius:4px;background:#fff;margin:12px 0 4px}
			.bh-sr--replacement{--bh-sr-dot:#f0c33c}.bh-sr--replaced{--bh-sr-dot:#72aee6}
			.bh-sr__head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:8px 12px;background:#efefef;border-radius:4px 4px 0 0}
			.bh-sr__title{display:flex;align-items:center;gap:6px;margin:0;font-size:12px;font-weight:600;color:#3c434a;text-transform:uppercase;letter-spacing:.04em}
			.bh-sr__title .dashicons{display:none}
			.bh-sr__pill{display:inline-flex;align-items:center;gap:6px;font-size:12px;color:#50575e}
			.bh-sr__pill::before{content:"";width:7px;height:7px;border-radius:50%;background:var(--bh-sr-dot)}
			.bh-sr__body{padding:10px 12px}
			.bh-sr__note{display:flex;align-items:center;gap:6px;margin:0 0 10px;color:#50575e;font-size:13px}
			.bh-sr__note .dashicons{font-size:14px;width:14px;height:14px;color:#a7aaad}
			.bh-sr__grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px 16px}
			.woocommerce_variation .bh-sr p.bh-sr__field{margin:0;padding:0;float:none;width:auto}
			.woocommerce_variation .bh-sr p.bh-sr__field label{display:block;margin:0 0 4px;color:#787c82;font-size:12px;float:none}
			#variable_product_options .bh-sr .bh-sr__field select,#variable_product_options .bh-sr .bh-sr__field input{width:100%;max-width:none;height:40px;min-height:40px;float:none;margin:0}
			.bh-sr__foot{display:flex;align-items:flex-start;gap:6px;padding:6px 12px;border-top:1px solid #efefef;color:#787c82;font-size:12px;line-height:18px}
			.bh-sr__foot .dashicons{font-size:14px;width:14px;height:14px;flex:none;margin-top:2px;color:#a7aaad}
			@media (max-width:782px){.bh-sr__grid{grid-template-columns:1fr}}
			</style>
			<?php
		}

		/**
		 * Moves the card under the Telegra mapping box, or under the description when that box is not there.
		 */
		public function print_card_script() {
			if ( ! $this->is_product_screen() ) {
				return;
			}
			?>
			<script>
			( function ( $ ) {
				function place() {
					$( '.bh-state-replacement[data-loop]' ).each( function () {
						var variation = $( this ).closest( '.woocommerce_variation' );
						var anchor    = variation.find( '.bh-telegra-variation-box' ).first();
						if ( ! anchor.length ) {
							anchor = variation.find( '.variable_description' + $( this ).data( 'loop' ) + '_field' ).parent();
						}
						if ( anchor.length ) {
							$( this ).insertAfter( anchor );
						}
					} );
				}
				// After the other handlers, so the Telegra box is already in place.
				$( document.body ).on( 'woocommerce_variations_loaded woocommerce_variations_added', function () {
					setTimeout( place, 0 );
				} );
			} )( jQuery );
			</script>
			<?php
		}

		private function is_product_screen(): bool {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

			return $screen && 'product' === $screen->id;
		}

		/**
		 * @param int $variation_id Variation ID.
		 * @param int $loop         Variation loop index.
		 */
		public function save_fields( $variation_id, $loop ) {
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verified the nonce before saving variations.
			if ( ! isset( $_POST['bh_replaces_variation'][ $loop ] ) && ! isset( $_POST['bh_exclusive_shipping_states'][ $loop ] ) ) {
				return;
			}
			$replaces = isset( $_POST['bh_replaces_variation'][ $loop ] ) ? absint( $_POST['bh_replaces_variation'][ $loop ] ) : 0;
			$raw      = isset( $_POST['bh_exclusive_shipping_states'][ $loop ] ) ? sanitize_text_field( wp_unslash( $_POST['bh_exclusive_shipping_states'][ $loop ] ) ) : '';
			// phpcs:enable

			$states = array_values( array_unique( array_filter( array_map( 'trim', explode( ',', strtoupper( $raw ) ) ) ) ) );

			if ( 0 === $replaces && empty( $states ) ) {
				$this->clear( $variation_id );
				return;
			}

			$error = $this->validate( (int) $variation_id, $replaces, $states );
			if ( '' !== $error ) {
				WC_Admin_Meta_Boxes::add_error( sprintf( '#%d: %s', $variation_id, $error ) );
				return;
			}

			update_post_meta( $variation_id, BH_REPLACES_VARIATION_META, $replaces );
			update_post_meta( $variation_id, BH_EXCLUSIVE_STATES_META, $states );
		}

		/**
		 * @return string Error message, empty when valid.
		 */
		private function validate( int $variation_id, int $replaces, array $states ): string {
			if ( 0 === $replaces || empty( $states ) ) {
				return __( 'Shipping state replacement needs both "Replaces variation" and at least one state. Not saved.', 'bh-features' );
			}
			if ( ! $this->is_variable_subscription_variation( $variation_id ) ) {
				return __( 'Shipping state replacement is only for variations of variable subscriptions. Not saved.', 'bh-features' );
			}
			if ( ! in_array( $replaces, $this->sibling_ids( $variation_id ), true ) ) {
				return __( '"Replaces variation" must be another variation of this product. Not saved.', 'bh-features' );
			}
			if ( bh_is_state_replacement_variation( $replaces ) ) {
				return __( 'The replaced variation is itself a replacement. Not saved.', 'bh-features' );
			}
			if ( bh_get_state_replacements_of( $variation_id ) ) {
				return __( 'This variation is already replaced by another one, so it cannot be a replacement. Not saved.', 'bh-features' );
			}

			$valid   = class_exists( 'AH_States' ) ? AH_States::get_codes() : array_keys( (array) WC()->countries->get_states( 'US' ) );
			$unknown = array_diff( $states, $valid );
			if ( $unknown ) {
				/* translators: %s: state codes */
				return sprintf( __( 'Unknown state codes: %s. Not saved.', 'bh-features' ), implode( ', ', $unknown ) );
			}

			foreach ( bh_get_state_replacements_of( $replaces ) as $other_id => $other_states ) {
				$overlap = array_intersect( $states, $other_states );
				if ( $other_id !== $variation_id && $overlap ) {
					/* translators: 1: state codes, 2: variation ID */
					return sprintf( __( '%1$s already has a replacement: #%2$d. Not saved.', 'bh-features' ), implode( ', ', $overlap ), $other_id );
				}
			}

			return '';
		}

		/**
		 * Only variations of variable subscriptions can be replacements.
		 */
		private function is_variable_subscription_variation( int $variation_id ): bool {
			$parent_id = (int) wp_get_post_parent_id( $variation_id );

			return $parent_id > 0 && 'variable-subscription' === WC_Product_Factory::get_product_type( $parent_id );
		}

		private function clear( $variation_id ) {
			delete_post_meta( $variation_id, BH_REPLACES_VARIATION_META );
			delete_post_meta( $variation_id, BH_EXCLUSIVE_STATES_META );
		}

		/**
		 * Other variations of the same product.
		 *
		 * @return int[]
		 */
		private function sibling_ids( int $variation_id ): array {
			$parent_id = (int) wp_get_post_parent_id( $variation_id );
			if ( $parent_id <= 0 ) {
				return array();
			}

			$ids = get_posts(
				array(
					'post_type'      => 'product_variation',
					'post_parent'    => $parent_id,
					'post_status'    => array( 'publish', 'private' ),
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'orderby'        => array(
						'menu_order' => 'ASC',
						'ID'         => 'ASC',
					),
				)
			);

			return array_values( array_diff( array_map( 'intval', $ids ), array( $variation_id ) ) );
		}
	}

	new AH_State_Replacement_Admin();
}
