<?php
/**
 * Test states: try a state that is not licensed yet, end to end, without opening it to customers.
 *
 * An admin picks one or more states, an expiry date and gets a secret link
 * (checkout URL + ?bh_test_states=<key>). Whoever opens the link sees those states in
 * the checkout state list until the cookie expires; nobody else does. Orders placed that
 * way in a test state, and their subscriptions, carry _bh_test_state, and the renewal
 * gates let only those subscriptions renew (scheduled renewals, "Process renewal",
 * free renewals, early renewal, failed-renewal recovery) while test mode is on.
 * Real subscriptions in the same state stay blocked.
 *
 * Off, expired, without a key or without states, nothing changes anywhere.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AH_States_Test_Mode' ) ) {

	class AH_States_Test_Mode {

		const OPTION_KEY = 'ah_states_test_mode';
		const QUERY_ARG  = 'bh_test_states';
		const COOKIE     = 'bh_test_states';
		const META       = '_bh_test_state';
		const LOG_SOURCE = 'ah-states-test-mode';

		/** Longest life of the tester cookie (it never outlives the expiry date either). */
		const COOKIE_TTL = DAY_IN_SECONDS;

		public static function init() {
			add_action( 'init', array( __CLASS__, 'handle_link' ), 1 );

			add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'mark_new_order' ), 10, 1 );
			add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( __CLASS__, 'mark_new_order' ), 10, 2 );
			add_action( 'woocommerce_checkout_subscription_created', array( __CLASS__, 'mark_subscription' ), 5, 2 );

			add_action( 'admin_post_ah_save_states_test_mode', array( __CLASS__, 'handle_save' ) );
		}

		/* ------------------------------------------------------------------
		 * Settings
		 * ------------------------------------------------------------------ */

		/**
		 * @return array{enabled: bool, states: string[], key: string, expires: int}
		 */
		public static function get_settings() {
			$saved = get_option( self::OPTION_KEY, array() );
			$saved = is_array( $saved ) ? $saved : array();

			$states = array();
			foreach ( (array) ( $saved['states'] ?? array() ) as $code ) {
				$code = strtoupper( trim( (string) $code ) );
				if ( in_array( $code, AH_States::get_codes(), true ) ) {
					$states[] = $code;
				}
			}

			return array(
				'enabled' => ! empty( $saved['enabled'] ),
				'states'  => array_values( array_unique( $states ) ),
				'key'     => (string) ( $saved['key'] ?? '' ),
				'expires' => (int) ( $saved['expires'] ?? 0 ),
			);
		}

		/**
		 * On, with a key, at least one state and an expiry date in the future.
		 */
		public static function is_active() {
			$s = self::get_settings();

			return $s['enabled'] && strlen( $s['key'] ) >= 20 && ! empty( $s['states'] ) && $s['expires'] > time();
		}

		/**
		 * Test states while test mode is active, else none.
		 *
		 * @return string[]
		 */
		public static function get_states() {
			return self::is_active() ? self::get_settings()['states'] : array();
		}

		public static function is_test_state( $state ) {
			return in_array( strtoupper( trim( (string) $state ) ), self::get_states(), true );
		}

		/* ------------------------------------------------------------------
		 * Tester link and cookie
		 * ------------------------------------------------------------------ */

		/**
		 * Signed cookie value: "<expiry>.<hmac>", bound to the current key and to its own expiry,
		 * so a new key invalidates every cookie and a copied cookie stops working when it expires.
		 *
		 * @param int $expires Unix time the cookie stops being valid.
		 */
		protected static function cookie_value( $expires ) {
			$expires = (int) $expires;

			return $expires . '.' . hash_hmac( 'sha256', 'bh-test-states|' . self::get_settings()['key'] . '|' . $expires, wp_salt( 'auth' ) );
		}

		protected static function link_matches() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- secret link, no form.
			$given = isset( $_GET[ self::QUERY_ARG ] ) ? (string) wp_unslash( $_GET[ self::QUERY_ARG ] ) : '';

			return '' !== $given && hash_equals( self::get_settings()['key'], $given );
		}

		protected static function cookie_matches() {
			$given = isset( $_COOKIE[ self::COOKIE ] ) ? (string) wp_unslash( $_COOKIE[ self::COOKIE ] ) : '';
			$parts = explode( '.', $given, 2 );
			if ( 2 !== count( $parts ) || ! ctype_digit( $parts[0] ) || (int) $parts[0] < time() ) {
				return false;
			}

			return hash_equals( self::cookie_value( (int) $parts[0] ), $given );
		}

		/**
		 * Whether this request comes from someone who opened the test link.
		 */
		public static function is_test_request() {
			if ( ! self::is_active() ) {
				return false;
			}

			return self::link_matches() || self::cookie_matches();
		}

		/**
		 * States this request may use on top of the licensed ones.
		 *
		 * @return string[]
		 */
		public static function get_request_states() {
			return self::is_test_request() ? self::get_states() : array();
		}

		/**
		 * ?bh_test_states=<key> sets the tester cookie; ?bh_test_states=off clears it.
		 * Pages of a test request are never cached. A page opened with the parameter is
		 * redirected to the same URL without it, so the key never reaches the rendered page
		 * (tracking pixels read the page URL).
		 */
		public static function handle_link() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$given = isset( $_GET[ self::QUERY_ARG ] ) ? (string) wp_unslash( $_GET[ self::QUERY_ARG ] ) : '';

			if ( 'off' === $given ) {
				self::send_cookie( '', time() - YEAR_IN_SECONDS );
				unset( $_COOKIE[ self::COOKIE ] );
				self::redirect_without_key();
				return;
			}

			if ( ! self::is_test_request() ) {
				if ( '' !== $given ) {
					self::redirect_without_key();
				}
				return;
			}

			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true );
			}
			if ( ! headers_sent() ) {
				nocache_headers();
			}

			if ( self::link_matches() && ! self::cookie_matches() ) {
				$expires = min( time() + self::COOKIE_TTL, self::get_settings()['expires'] );
				self::send_cookie( self::cookie_value( $expires ), $expires );
				$_COOKIE[ self::COOKIE ] = self::cookie_value( $expires );
				wc_get_logger()->info( 'Test states link opened.', array( 'source' => self::LOG_SOURCE ) );
			}

			if ( '' !== $given ) {
				self::redirect_without_key();
			}
		}

		/**
		 * Redirects a page request (GET, not admin, AJAX or cron) to the same URL without the key.
		 */
		protected static function redirect_without_key() {
			$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : '';
			if ( 'GET' !== $method || is_admin() || wp_doing_ajax() || wp_doing_cron() || headers_sent() ) {
				return;
			}

			$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
			wp_safe_redirect( remove_query_arg( self::QUERY_ARG, $uri ), 302 );
			exit;
		}

		protected static function send_cookie( $value, $expires ) {
			if ( headers_sent() ) {
				return;
			}
			setcookie(
				self::COOKIE,
				$value,
				array(
					'expires'  => $expires,
					'path'     => COOKIEPATH ? COOKIEPATH : '/',
					'domain'   => COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);
		}

		/* ------------------------------------------------------------------
		 * Orders and subscriptions
		 * ------------------------------------------------------------------ */

		protected static function order_state( $order ) {
			$state = $order->get_shipping_state();
			if ( '' === (string) $state ) {
				$state = $order->get_billing_state();
			}

			return strtoupper( trim( (string) $state ) );
		}

		/**
		 * Marks a checkout order placed through the test link in a test state that is not licensed.
		 *
		 * The Store API reuses its draft order when a checkout is retried (another address, a failed
		 * payment), so a mark that no longer applies is removed. Paying an existing order
		 * (/checkout/{id}) keeps the mark it got when it was placed.
		 *
		 * @param WC_Order                   $order   Order being created (classic or Store API checkout).
		 * @param WP_REST_Request|array|null $request Store API request (classic checkout passes its data).
		 */
		public static function mark_new_order( $order, $request = null ) {
			if ( ! $order instanceof WC_Order ) {
				return;
			}
			if ( $request instanceof WP_REST_Request && preg_match( '#/checkout/\d+/?$#', $request->get_route() ) ) {
				return;
			}

			$state = self::order_state( $order );
			if ( ! self::is_test_request() || ! self::is_test_state( $state ) || AH_Licensed_States_Manager::is_state_available( $state ) ) {
				if ( '' !== (string) $order->get_meta( self::META ) ) {
					$order->delete_meta_data( self::META );
				}
				return;
			}

			$order->update_meta_data( self::META, $state );
			wc_get_logger()->info( "Checkout order marked as test state order ({$state}).", array( 'source' => self::LOG_SOURCE ) );
		}

		/**
		 * The subscription created from a marked order gets the same mark.
		 *
		 * @param WC_Subscription $subscription New subscription.
		 * @param WC_Order        $order        Parent order.
		 */
		public static function mark_subscription( $subscription, $order ) {
			if ( ! $subscription instanceof WC_Subscription || ! $order instanceof WC_Order ) {
				return;
			}

			$state = (string) $order->get_meta( self::META );
			if ( '' === $state ) {
				return;
			}

			$subscription->update_meta_data( self::META, $state );
			$subscription->save();

			$note = sprintf( 'Test state order (%s): placed with the test states link. Renewals are allowed while test mode is on.', $state );
			$subscription->add_order_note( $note );
			$order->add_order_note( $note );
		}

		/**
		 * Whether a subscription in a state that is not licensed may renew: it was created through
		 * the test link in that same state, and that state is still a test state.
		 *
		 * @param WC_Subscription $subscription Subscription.
		 */
		public static function subscription_allowed( $subscription ) {
			if ( ! $subscription instanceof WC_Subscription ) {
				return false;
			}

			$mark  = strtoupper( (string) $subscription->get_meta( self::META ) );
			$state = self::order_state( $subscription );

			return '' !== $mark && $mark === $state && self::is_test_state( $state );
		}

		/**
		 * Same rule for an order: a renewal order follows its subscription, any other order its own mark.
		 *
		 * @param WC_Order $order Order.
		 */
		public static function order_allowed( $order ) {
			if ( ! $order instanceof WC_Order ) {
				return false;
			}

			if ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order ) ) {
				foreach ( wcs_get_subscriptions_for_renewal_order( $order ) as $subscription ) {
					if ( self::subscription_allowed( $subscription ) && self::order_state( $order ) === self::order_state( $subscription ) ) {
						return true;
					}
				}
				return false;
			}

			$mark  = strtoupper( (string) $order->get_meta( self::META ) );
			$state = self::order_state( $order );

			return '' !== $mark && $mark === $state && self::is_test_state( $state );
		}

		/* ------------------------------------------------------------------
		 * Admin (card on the Licensed States page)
		 * ------------------------------------------------------------------ */

		public static function render_admin_card() {
			$s      = self::get_settings();
			$active = self::is_active();
			$link   = ( '' !== $s['key'] && function_exists( 'wc_get_checkout_url' ) )
				? add_query_arg( self::QUERY_ARG, rawurlencode( $s['key'] ), wc_get_checkout_url() )
				: '';
			$date   = $s['expires'] ? wp_date( 'Y-m-d', $s['expires'] ) : '';

			if ( $active ) {
				$status = 'Active until ' . wp_date( 'Y-m-d H:i', $s['expires'] ) . ' for ' . implode( ', ', $s['states'] ) . '.';
			} elseif ( ! $s['enabled'] ) {
				$status = 'Off.';
			} elseif ( empty( $s['states'] ) ) {
				$status = 'Off: no test states selected.';
			} elseif ( $s['expires'] <= time() ) {
				$status = 'Off: the expiry date has passed.';
			} else {
				$status = 'Off: no key.';
			}
			?>
			<div class="card" style="max-width:none;margin:1rem 0;">
				<h2 style="margin-top:0;">Test States</h2>
				<p>
					Lets anyone with the link below place orders in states that are not licensed yet, and lets
					<strong>only those</strong> subscriptions renew (scheduled renewals, Process renewal, free renewals).
					Customers without the link never see these states, and existing subscriptions in them stay blocked.
				</p>
				<p><strong>Status:</strong> <?php echo esc_html( $status ); ?></p>

				<?php if ( isset( $_GET['test_mode_updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
					<div class="notice notice-success inline"><p>Test states saved.</p></div>
				<?php endif; ?>

				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="POST">
					<?php wp_nonce_field( 'ah_save_states_test_mode' ); ?>
					<input type="hidden" name="action" value="ah_save_states_test_mode">

					<table class="form-table">
						<tr>
							<th>Enabled</th>
							<td><label><input type="checkbox" name="enabled" value="1" <?php checked( $s['enabled'] ); ?>> Test mode on</label></td>
						</tr>
						<tr>
							<th>Test states</th>
							<td>
								<input type="text" name="states" value="<?php echo esc_attr( implode( ', ', $s['states'] ) ); ?>" class="regular-text" placeholder="CA">
								<p class="description">State codes separated by commas, e.g. <code>CA</code> or <code>CA, WA</code>.</p>
							</td>
						</tr>
						<tr>
							<th>Expires</th>
							<td>
								<input type="date" name="expires" value="<?php echo esc_attr( $date ); ?>" required>
								<p class="description">Required. Test mode turns off at the end of this day (site time zone).</p>
							</td>
						</tr>
						<tr>
							<th>Test link</th>
							<td>
								<?php if ( $link ) : ?>
									<input type="text" readonly class="large-text code" value="<?php echo esc_attr( $link ); ?>" onclick="this.select();">
									<p class="description">Share it only with testers. Open <code>?<?php echo esc_html( self::QUERY_ARG ); ?>=off</code> to leave test mode in a browser.</p>
								<?php else : ?>
									<p class="description">A key is created when you save.</p>
								<?php endif; ?>
								<label><input type="checkbox" name="regenerate_key" value="1"> Create a new key (the old link stops working)</label>
							</td>
						</tr>
					</table>

					<p><button type="submit" class="button button-primary">Save Test States</button></p>
				</form>
			</div>
			<?php
		}

		public static function handle_save() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( 'Unauthorized' );
			}
			check_admin_referer( 'ah_save_states_test_mode' );

			$current = self::get_settings();

			$states = array();
			$raw    = isset( $_POST['states'] ) ? sanitize_text_field( wp_unslash( $_POST['states'] ) ) : '';
			foreach ( preg_split( '/[\s,]+/', strtoupper( $raw ), -1, PREG_SPLIT_NO_EMPTY ) as $code ) {
				if ( in_array( $code, AH_States::get_codes(), true ) ) {
					$states[] = $code;
				}
			}

			$expires = 0;
			$date    = isset( $_POST['expires'] ) ? sanitize_text_field( wp_unslash( $_POST['expires'] ) ) : '';
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
				$end = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $date . ' 23:59:59', wp_timezone() );
				if ( $end ) {
					$expires = $end->getTimestamp();
				}
			}

			$key = $current['key'];
			if ( '' === $key || ! empty( $_POST['regenerate_key'] ) ) {
				$key = wp_generate_password( 32, false, false );
			}

			$settings = array(
				'enabled' => ! empty( $_POST['enabled'] ),
				'states'  => array_values( array_unique( $states ) ),
				'key'     => $key,
				'expires' => $expires,
			);
			update_option( self::OPTION_KEY, $settings, false );

			wc_get_logger()->info(
				sprintf(
					'Test states saved by user %d: %s, states %s, expires %s%s.',
					get_current_user_id(),
					$settings['enabled'] ? 'on' : 'off',
					implode( ',', $settings['states'] ) ?: '-',
					$expires ? wp_date( 'Y-m-d H:i', $expires ) : '-',
					$key !== $current['key'] ? ', new key' : ''
				),
				array( 'source' => self::LOG_SOURCE )
			);

			wp_safe_redirect( admin_url( 'admin.php?page=' . PARENT_MENU_SLUG . '--licensed-states&test_mode_updated=1' ) );
			exit;
		}
	}

	AH_States_Test_Mode::init();
}
