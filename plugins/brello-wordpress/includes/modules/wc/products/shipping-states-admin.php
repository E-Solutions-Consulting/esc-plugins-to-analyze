<?php
/**
 * Admin settings for the per-product shipping state rules.
 *
 * - Licensed States page: "Opt-in states" card (global list, e.g. CA).
 * - Product category (add / edit): "Allowed opt-in states" and "Ship only to".
 * - Product: "Shipping states" tab. Uses the rules of its categories, or its own
 *   rules ("Allowed opt-in states", "Ship only to"), and shows the rules in effect.
 *
 * Codes are typed comma separated. A value with an unknown code is not saved and the
 * screen says why.
 * See common/product-shipping-states-helpers.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Bh_Shipping_States_Admin' ) ) {

	class Bh_Shipping_States_Admin {

		const TERM_ERROR_TRANSIENT = 'bh_shipping_states_term_error_';
		const SAVE_ACTION          = 'bh_save_opt_in_states';

		public function __construct() {
			// Global opt-in states (card on the Licensed States page).
			add_action( 'admin_post_' . self::SAVE_ACTION, array( $this, 'save_opt_in_states' ) );

			// Product categories.
			add_action( 'product_cat_add_form_fields', array( $this, 'render_term_add_fields' ), 20 );
			add_action( 'product_cat_edit_form_fields', array( $this, 'render_term_edit_fields' ), 20 );
			add_action( 'created_product_cat', array( $this, 'save_term_fields' ) );
			add_action( 'edited_product_cat', array( $this, 'save_term_fields' ) );
			add_action( 'admin_notices', array( $this, 'term_error_notice' ) );

			// Products.
			add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_product_tab' ) );
			add_action( 'woocommerce_product_data_panels', array( $this, 'render_product_panel' ) );
			add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_product_fields' ) );
		}

		/* ------------------------------------------------------------------
		 * Global opt-in states
		 * ------------------------------------------------------------------ */

		/**
		 * Card for the Licensed States page.
		 */
		public static function render_opt_in_card() {
			$states = bh_get_opt_in_states();
			$error  = isset( $_GET['bh_opt_in_error'] ) ? sanitize_text_field( wp_unslash( $_GET['bh_opt_in_error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			?>
			<div class="card" style="max-width:none;margin:1rem 0;">
				<h2 style="margin-top:0;"><?php esc_html_e( 'Opt-in states', 'bh-features' ); ?></h2>
				<p>
					<?php esc_html_e( 'Products ship to these states only when their category or the product allows them ("Allowed opt-in states"). Every other product, including new ones, does not. The state must also be available below.', 'bh-features' ); ?>
				</p>
				<?php if ( isset( $_GET['bh_opt_in_saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
					<div class="notice notice-success inline"><p><?php esc_html_e( 'Opt-in states saved.', 'bh-features' ); ?></p></div>
				<?php endif; ?>
				<?php if ( '' !== $error ) : ?>
					<div class="notice notice-error inline"><p>
						<?php
						/* translators: %s: state codes */
						echo esc_html( sprintf( __( 'Unknown state codes: %s. Not saved.', 'bh-features' ), $error ) );
						?>
					</p></div>
				<?php endif; ?>
				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="POST">
					<?php wp_nonce_field( self::SAVE_ACTION ); ?>
					<input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE_ACTION ); ?>">
					<input type="text" name="bh_opt_in_states" class="regular-text" placeholder="CA" value="<?php echo esc_attr( implode( ', ', $states ) ); ?>">
					<button type="submit" class="button button-secondary"><?php esc_html_e( 'Save opt-in states', 'bh-features' ); ?></button>
					<p class="description"><?php esc_html_e( 'State codes, comma separated. Empty = no opt-in states.', 'bh-features' ); ?></p>
				</form>
			</div>
			<?php
		}

		public function save_opt_in_states() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( 'Unauthorized' );
			}
			check_admin_referer( self::SAVE_ACTION );

			$states   = bh_parse_state_codes( wp_unslash( (string) ( $_POST['bh_opt_in_states'] ?? '' ) ) );
			$unknown  = bh_unknown_state_codes( $states );
			$redirect = admin_url( 'admin.php?page=' . PARENT_MENU_SLUG . '--licensed-states' );

			if ( $unknown ) {
				wp_safe_redirect( add_query_arg( 'bh_opt_in_error', rawurlencode( implode( ', ', $unknown ) ), $redirect ) );
				exit;
			}

			update_option( BH_OPT_IN_STATES_OPTION, $states, false );
			wp_safe_redirect( add_query_arg( 'bh_opt_in_saved', '1', $redirect ) );
			exit;
		}

		/* ------------------------------------------------------------------
		 * Product categories
		 * ------------------------------------------------------------------ */

		public function render_term_add_fields() {
			wp_nonce_field( 'bh_shipping_states_term', 'bh_shipping_states_term_nonce' );
			?>
			<div class="form-field">
				<label for="bh_allowed_opt_in_states"><?php esc_html_e( 'Allowed opt-in states', 'bh-features' ); ?></label>
				<input type="text" name="bh_allowed_opt_in_states" id="bh_allowed_opt_in_states" placeholder="CA">
				<p><?php echo esc_html( $this->opt_in_help() ); ?></p>
			</div>
			<div class="form-field">
				<label for="bh_ship_only_states"><?php esc_html_e( 'Ship only to', 'bh-features' ); ?></label>
				<input type="text" name="bh_ship_only_states" id="bh_ship_only_states">
				<p><?php echo esc_html( $this->ship_only_help() ); ?></p>
			</div>
			<?php
		}

		/**
		 * @param WP_Term $term Category.
		 */
		public function render_term_edit_fields( $term ) {
			$rules = bh_get_category_state_rules( (int) $term->term_id );
			wp_nonce_field( 'bh_shipping_states_term', 'bh_shipping_states_term_nonce' );
			?>
			<tr class="form-field">
				<th scope="row"><label for="bh_allowed_opt_in_states"><?php esc_html_e( 'Allowed opt-in states', 'bh-features' ); ?></label></th>
				<td>
					<input type="text" name="bh_allowed_opt_in_states" id="bh_allowed_opt_in_states" placeholder="CA" value="<?php echo esc_attr( implode( ', ', $rules['opt_in'] ) ); ?>">
					<p class="description"><?php echo esc_html( $this->opt_in_help() ); ?></p>
				</td>
			</tr>
			<tr class="form-field">
				<th scope="row"><label for="bh_ship_only_states"><?php esc_html_e( 'Ship only to', 'bh-features' ); ?></label></th>
				<td>
					<input type="text" name="bh_ship_only_states" id="bh_ship_only_states" value="<?php echo esc_attr( implode( ', ', $rules['ship_only'] ) ); ?>">
					<p class="description"><?php echo esc_html( $this->ship_only_help() ); ?></p>
				</td>
			</tr>
			<?php
		}

		/**
		 * @param int $term_id Category ID.
		 */
		public function save_term_fields( $term_id ) {
			if ( ! isset( $_POST['bh_shipping_states_term_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['bh_shipping_states_term_nonce'] ) ), 'bh_shipping_states_term' ) ) {
				return;
			}
			if ( ! current_user_can( 'manage_product_terms' ) ) {
				return;
			}

			$fields = array(
				BH_ALLOWED_OPT_IN_STATES_META => bh_parse_state_codes( wp_unslash( (string) ( $_POST['bh_allowed_opt_in_states'] ?? '' ) ) ),
				BH_SHIP_ONLY_STATES_META      => bh_parse_state_codes( wp_unslash( (string) ( $_POST['bh_ship_only_states'] ?? '' ) ) ),
			);

			$unknown = bh_unknown_state_codes( array_merge( ...array_values( $fields ) ) );
			if ( $unknown ) {
				set_transient( self::TERM_ERROR_TRANSIENT . get_current_user_id(), implode( ', ', $unknown ), 5 * MINUTE_IN_SECONDS );
				return;
			}

			foreach ( $fields as $key => $codes ) {
				if ( $codes ) {
					update_term_meta( $term_id, $key, $codes );
				} else {
					delete_term_meta( $term_id, $key );
				}
			}
		}

		public function term_error_notice() {
			$key     = self::TERM_ERROR_TRANSIENT . get_current_user_id();
			$unknown = get_transient( $key );
			if ( ! $unknown ) {
				return;
			}
			delete_transient( $key );
			/* translators: %s: state codes */
			echo '<div class="notice notice-error"><p>' . esc_html( sprintf( __( 'Category shipping states not saved. Unknown state codes: %s.', 'bh-features' ), $unknown ) ) . '</p></div>';
		}

		/* ------------------------------------------------------------------
		 * Products
		 * ------------------------------------------------------------------ */

		/**
		 * @param array $tabs Product data tabs.
		 * @return array
		 */
		public function add_product_tab( $tabs ) {
			$tabs['bh_shipping_states'] = array(
				'label'    => __( 'Shipping states', 'bh-features' ),
				'target'   => 'bh_shipping_states_data',
				'class'    => array(),
				'priority' => 75,
			);

			return $tabs;
		}

		public function render_product_panel() {
			global $post;

			$product_id = $post ? (int) $post->ID : 0;
			$mode       = 'custom' === get_post_meta( $product_id, BH_STATES_RULES_MODE_META, true ) ? 'custom' : 'inherit';
			$own        = array(
				'opt_in'    => bh_parse_state_codes( get_post_meta( $product_id, BH_ALLOWED_OPT_IN_STATES_META, true ) ),
				'ship_only' => bh_parse_state_codes( get_post_meta( $product_id, BH_SHIP_ONLY_STATES_META, true ) ),
			);
			?>
			<div id="bh_shipping_states_data" class="panel woocommerce_options_panel hidden">
				<div class="options_group">
					<?php
					woocommerce_wp_select(
						array(
							'id'          => 'bh_shipping_states_mode',
							'label'       => __( 'Rules', 'bh-features' ),
							'value'       => $mode,
							'options'     => array(
								'inherit' => __( 'Use the rules of the product categories', 'bh-features' ),
								'custom'  => __( 'Use the rules below for this product', 'bh-features' ),
							),
							'desc_tip'    => true,
							'description' => __( 'With "Use the rules below", the category rules are ignored for this product (empty fields = no opt-in states and no "Ship only to").', 'bh-features' ),
						)
					);
					woocommerce_wp_text_input(
						array(
							'id'          => 'bh_allowed_opt_in_states',
							'label'       => __( 'Allowed opt-in states', 'bh-features' ),
							'value'       => implode( ', ', $own['opt_in'] ),
							'placeholder' => 'CA',
							'desc_tip'    => true,
							'description' => $this->opt_in_help(),
						)
					);
					woocommerce_wp_text_input(
						array(
							'id'          => 'bh_ship_only_states',
							'label'       => __( 'Ship only to', 'bh-features' ),
							'value'       => implode( ', ', $own['ship_only'] ),
							'desc_tip'    => true,
							'description' => $this->ship_only_help(),
						)
					);
					?>
				</div>
				<div class="options_group">
					<p class="form-field"><label><?php esc_html_e( 'In effect', 'bh-features' ); ?></label><?php echo wp_kses_post( $this->effective_summary( $product_id ) ); ?></p>
				</div>
			</div>
			<?php
		}

		/**
		 * @param WC_Product $product Product being saved.
		 */
		public function save_product_fields( $product ) {
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verified the product meta box nonce.
			if ( ! $product instanceof WC_Product || ! isset( $_POST['bh_shipping_states_mode'] ) ) {
				return;
			}

			$mode = 'custom' === sanitize_key( wp_unslash( $_POST['bh_shipping_states_mode'] ) ) ? 'custom' : 'inherit';
			$own  = array(
				BH_ALLOWED_OPT_IN_STATES_META => bh_parse_state_codes( wp_unslash( (string) ( $_POST['bh_allowed_opt_in_states'] ?? '' ) ) ),
				BH_SHIP_ONLY_STATES_META      => bh_parse_state_codes( wp_unslash( (string) ( $_POST['bh_ship_only_states'] ?? '' ) ) ),
			);
			// phpcs:enable

			$unknown = bh_unknown_state_codes( array_merge( ...array_values( $own ) ) );
			if ( $unknown ) {
				/* translators: %s: state codes */
				WC_Admin_Meta_Boxes::add_error( sprintf( __( 'Shipping states not saved. Unknown state codes: %s.', 'bh-features' ), implode( ', ', $unknown ) ) );
				return;
			}

			if ( 'custom' === $mode ) {
				$product->update_meta_data( BH_STATES_RULES_MODE_META, 'custom' );
			} else {
				$product->delete_meta_data( BH_STATES_RULES_MODE_META );
			}
			// The own values are kept while the product uses the category rules, so switching back restores them.
			foreach ( $own as $key => $codes ) {
				if ( $codes ) {
					$product->update_meta_data( $key, $codes );
				} else {
					$product->delete_meta_data( $key );
				}
			}
		}

		/**
		 * Rules in effect for a product, as HTML.
		 */
		private function effective_summary( int $product_id ): string {
			$rules  = bh_get_product_state_rules( $product_id );
			$opt_in = bh_get_opt_in_states();
			$from   = 'product' === $rules['source'] ? __( 'this product', 'bh-features' ) : __( 'its categories', 'bh-features' );

			if ( $rules['ship_only'] ) {
				/* translators: 1: state codes, 2: source */
				$text = sprintf( __( 'Ships only to %1$s (from %2$s).', 'bh-features' ), implode( ', ', $rules['ship_only'] ), $from );
			} else {
				$allowed = array_values( array_intersect( $opt_in, $rules['opt_in'] ) );
				$blocked = array_values( array_diff( $opt_in, $rules['opt_in'] ) );
				$parts   = array( __( 'Ships to every available state', 'bh-features' ) );
				if ( $blocked ) {
					/* translators: %s: state codes */
					$parts[] = sprintf( __( 'except %s', 'bh-features' ), implode( ', ', $blocked ) );
				}
				if ( $allowed ) {
					/* translators: %s: state codes */
					$parts[] = sprintf( __( 'including the opt-in states %s', 'bh-features' ), implode( ', ', $allowed ) );
				}
				/* translators: 1: rule, 2: source */
				$text = sprintf( __( '%1$s (from %2$s).', 'bh-features' ), implode( ', ', $parts ), $from );
			}

			$opt_in_text = $opt_in
				/* translators: %s: state codes */
				? sprintf( __( 'Opt-in states: %s.', 'bh-features' ), implode( ', ', $opt_in ) )
				: __( 'No opt-in states are set.', 'bh-features' );

			return '<span>' . esc_html( $text ) . '<br><span class="description">' . esc_html( $opt_in_text ) . ' ' . esc_html__( 'Saved changes show here after updating the product.', 'bh-features' ) . '</span></span>';
		}

		private function opt_in_help(): string {
			return __( 'Opt-in states (set on Licensed States) this product may ship to, comma separated, e.g. CA.', 'bh-features' );
		}

		private function ship_only_help(): string {
			return __( 'Optional. When set, the product ships only to these states, comma separated. Empty = every available state.', 'bh-features' );
		}
	}

	new Bh_Shipping_States_Admin();
}
