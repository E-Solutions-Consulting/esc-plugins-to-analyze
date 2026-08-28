<?php
/**
 * Async Telegra / TelemdNow visit creation after checkout.
 *
 * Queues Action Scheduler jobs so Place Order / landing REST return quickly.
 * Worker flips order to telemdnow_trigger_action (existing TelemdNow hooks run).
 *
 * @package BH_Features
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Do not early-return before ensuring the AS hook is registered.
if ( class_exists( 'BH_Telegra_Async' ) ) {
	if ( method_exists( 'BH_Telegra_Async', 'init' ) ) {
		BH_Telegra_Async::init();
	}
	return;
}

class BH_Telegra_Async {

	const HOOK           = 'bh_telegra_process_order';
	const GROUP          = 'brello-telegra';
	const STATUS_META    = '_brello_telegra_async_status';
	const LOCK_META      = '_brello_telegra_async_lock';
	const ATTEMPTS_META  = '_brello_telegra_async_attempts';
	const MAX_ATTEMPTS   = 3;
	const LOCK_TTL       = 300; // 5 minutes.
	const REST_NAMESPACE = 'bh/v1';
	const REST_ROUTE     = '/telegra-visit-status';

	/** @var int[] retry backoff seconds per attempt index (1-based). */
	private static $backoff = array(
		1 => 30,
		2 => 120,
		3 => 300,
	);

	public static function init() {
		// NOTE: Hook `bh_telegra_process_order` is registered in bh-features.php
		// (bootstrap) so Action Scheduler async requests always see a callback,
		// even when this module file loads late or fails in AJAX context.
		if ( ! has_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) ) ) {
			add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		}
	}

	/**
	 * @return WC_Logger|null
	 */
	private static function logger() {
		if ( function_exists( 'wc_get_logger' ) ) {
			return wc_get_logger();
		}
		return null;
	}

	/**
	 * @param string $level   emergency|alert|critical|error|warning|notice|info|debug
	 * @param string $message Message.
	 * @param array  $context Context.
	 */
	private static function log( $level, $message, $context = array() ) {
		$logger = self::logger();
		if ( ! $logger ) {
			return;
		}
		$context['source'] = 'brello-telegra-async';
		$logger->log( $level, $message, $context );
	}

	/**
	 * Queue Telegra trigger for an order (idempotent).
	 *
	 * @param int $order_id Order ID.
	 * @return bool True if newly queued or already in flight / done.
	 */
	public static function queue( $order_id ) {
		$order_id = absint( $order_id );
		if ( $order_id < 1 ) {
			return false;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		if ( $order->get_meta( 'telemdnow_visit_link' ) || $order->get_meta( 'telemdnow_entity_id' ) ) {
			$order->update_meta_data( self::STATUS_META, 'done' );
			$order->save();
			return true;
		}

		$status = (string) $order->get_meta( self::STATUS_META );
		if ( 'done' === $status ) {
			return true;
		}

		// Skip only if a worker job is already pending/running for this order.
		$already_scheduled = false;
		if ( function_exists( 'as_has_scheduled_action' ) ) {
			$already_scheduled = as_has_scheduled_action( self::HOOK, array( $order_id ), self::GROUP );
		}
		if ( $already_scheduled ) {
			self::log( 'info', "Skip queue order #{$order_id}; AS job already scheduled" );
			return true;
		}
		if ( 'processing' === $status ) {
			$lock = (int) $order->get_meta( self::LOCK_META );
			if ( $lock > 0 && ( time() - $lock ) < self::LOCK_TTL ) {
				self::log( 'info', "Skip queue order #{$order_id}; processing lock held" );
				return true;
			}
		}

		$order->update_meta_data( self::STATUS_META, 'queued' );
		$order->save();
		$order->add_order_note( __( 'Telegra queued (async).', 'bh-features' ) );

		// Immediate schedule (no artificial delay). Bootstrap registers the callback
		// in bh-features.php so AS async requests can run this hook.
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::HOOK, array( $order_id ), self::GROUP );
		} elseif ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( time(), self::HOOK, array( $order_id ), self::GROUP );
		} else {
			wp_schedule_single_event( time() + 1, self::HOOK, array( $order_id ) );
		}

		if ( function_exists( 'spawn_cron' ) ) {
			spawn_cron( time() );
		}

		self::log( 'info', "Queued Telegra async for order #{$order_id}" );
		return true;
	}

	/**
	 * AS / cron worker.
	 *
	 * @param int|array $order_id Order id or AS args.
	 */
	public static function process( $order_id ) {
		if ( is_array( $order_id ) && isset( $order_id['order_id'] ) ) {
			$order_id = $order_id['order_id'];
		}
		$order_id = absint( $order_id );
		$order    = $order_id ? wc_get_order( $order_id ) : false;
		if ( ! $order ) {
			self::log( 'warning', "Telegra async: order missing #{$order_id}" );
			return;
		}

		if ( $order->has_status( array( 'cancelled', 'refunded', 'failed' ) ) ) {
			$order->update_meta_data( self::STATUS_META, 'failed' );
			$order->save();
			self::log( 'info', "Telegra async skip cancelled/failed order #{$order_id}" );
			return;
		}

		if ( $order->get_meta( 'telemdnow_visit_link' ) || $order->get_meta( 'telemdnow_entity_id' ) ) {
			$order->update_meta_data( self::STATUS_META, 'done' );
			$order->delete_meta_data( self::LOCK_META );
			$order->save();
			self::maybe_mark_landing_telegra_done( $order );
			return;
		}

		$lock = (int) $order->get_meta( self::LOCK_META );
		$now  = time();
		if ( $lock > 0 && ( $now - $lock ) < self::LOCK_TTL ) {
			$status = (string) $order->get_meta( self::STATUS_META );
			if ( 'processing' === $status ) {
				self::log( 'info', "Telegra async lock held for order #{$order_id}" );
				return;
			}
		}

		$attempts = (int) $order->get_meta( self::ATTEMPTS_META );
		$attempts++;
		$order->update_meta_data( self::ATTEMPTS_META, $attempts );
		$order->update_meta_data( self::STATUS_META, 'processing' );
		$order->update_meta_data( self::LOCK_META, $now );
		$order->save();

		self::log( 'info', "Telegra async processing order #{$order_id} attempt={$attempts}" );

		$trigger = get_option( 'telemdnow_trigger_action' );
		if ( empty( $trigger ) ) {
			self::fail_and_maybe_retry( $order, $attempts, 'telemdnow_trigger_action option empty' );
			return;
		}

		try {
			if ( ! $order->has_status( $trigger ) ) {
				$order->update_status(
					$trigger,
					sprintf(
						/* translators: %d: attempt number */
						__( 'Async Telegra trigger (attempt %d).', 'bh-features' ),
						$attempts
					)
				);
			} elseif ( function_exists( 'send_order_to_telegra' ) ) {
				// Already on trigger status but no visit link — call sender directly.
				send_order_to_telegra( $order_id );
			}

			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				return;
			}

			if ( $order->get_meta( 'telemdnow_visit_link' ) || $order->get_meta( 'telemdnow_entity_id' ) ) {
				$order->update_meta_data( self::STATUS_META, 'done' );
				$order->delete_meta_data( self::LOCK_META );
				$order->save();
				$order->add_order_note( __( 'Telegra visit link ready (async).', 'bh-features' ) );
				self::maybe_mark_landing_telegra_done( $order );
				self::log( 'info', "Telegra async done order #{$order_id}" );
				return;
			}

			self::fail_and_maybe_retry(
				$order,
				$attempts,
				'Trigger ran but telemdnow_visit_link / entity_id still empty'
			);
		} catch ( \Throwable $e ) {
			self::fail_and_maybe_retry( $order, $attempts, $e->getMessage() );
		}
	}

	/**
	 * @param WC_Order $order    Order.
	 * @param int      $attempts Attempt count.
	 * @param string   $reason   Reason.
	 */
	private static function fail_and_maybe_retry( $order, $attempts, $reason ) {
		$order_id = $order->get_id();
		$order->delete_meta_data( self::LOCK_META );
		$order->add_order_note(
			sprintf(
				/* translators: 1: attempt 2: reason */
				__( 'Telegra async failed (attempt %1$d): %2$s', 'bh-features' ),
				$attempts,
				$reason
			)
		);
		self::log( 'error', "Telegra async fail order #{$order_id}: {$reason}", array( 'attempt' => $attempts ) );

		if ( $attempts < self::MAX_ATTEMPTS ) {
			$delay = isset( self::$backoff[ $attempts ] ) ? self::$backoff[ $attempts ] : 300;
			$order->update_meta_data( self::STATUS_META, 'queued' );
			$order->save();

			if ( function_exists( 'as_schedule_single_action' ) ) {
				as_schedule_single_action( time() + $delay, self::HOOK, array( $order_id ), self::GROUP );
			} else {
				wp_schedule_single_event( time() + $delay, self::HOOK, array( $order_id ) );
			}
			self::log( 'info', "Telegra async retry scheduled order #{$order_id} in {$delay}s" );
			return;
		}

		$order->update_meta_data( self::STATUS_META, 'failed' );
		$order->save();
	}

	/**
	 * Update landing flow meta when visit link becomes available.
	 *
	 * @param WC_Order $order Order.
	 */
	private static function maybe_mark_landing_telegra_done( $order ) {
		$flow = $order->get_meta( '_brello_landing_flow' );
		if ( ! is_array( $flow ) ) {
			return;
		}
		if ( empty( $flow['steps'] ) || ! is_array( $flow['steps'] ) ) {
			$flow['steps'] = array();
		}
		$flow['steps']['telegra_visit_link'] = array(
			'status' => 'done',
			'at'     => gmdate( 'c' ),
			'detail' => 'Async Telegra complete',
		);
		$flow['updated_at'] = gmdate( 'c' );
		$order->update_meta_data( '_brello_landing_flow', $flow );
		$order->save();
	}

	/**
	 * Build public status payload for an order.
	 *
	 * @param WC_Order $order Order.
	 * @return array
	 */
	public static function get_status_payload( $order ) {
		$visit_link = (string) $order->get_meta( 'telemdnow_visit_link' );
		$entity_id  = (string) $order->get_meta( 'telemdnow_entity_id' );
		$status     = (string) $order->get_meta( self::STATUS_META );

		if ( $visit_link || $entity_id ) {
			$status = 'done';
		} elseif ( '' === $status ) {
			$status = ( function_exists( 'bh_is_async_telegra_landing_enabled' ) && bh_is_async_telegra_landing_enabled( $order ) )
				? 'queued'
				: 'unknown';
		}

		$redirect_url = $visit_link;
		if ( $visit_link && $order->get_meta( 'telemdnow_order_creation' ) && 'false' !== $order->get_meta( 'telemdnow_order_creation' ) ) {
			$thank_you    = wc_get_endpoint_url( 'order-received', $order->get_id(), wc_get_checkout_url() ) . '?key=' . $order->get_order_key();
			$redirect_url = $visit_link . '&redirectUrl=' . $thank_you;
		}

		$message = '';
		switch ( $status ) {
			case 'done':
				$message = __( 'Your intake link is ready.', 'bh-features' );
				break;
			case 'failed':
				$message = __( 'We are still setting up your intake. Please refresh or contact support. Do not pay again.', 'bh-features' );
				break;
			case 'processing':
			case 'queued':
				$message = __( 'Payment successful — preparing your medical questionnaire…', 'bh-features' );
				break;
			default:
				$message = __( 'Checking intake status…', 'bh-features' );
		}

		return array(
			'order_id'             => $order->get_id(),
			'status'               => $status,
			'telemdnow_visit_link' => $visit_link,
			'telemdnow_entity_id'  => $entity_id,
			'redirect_url'         => $redirect_url,
			'message'              => $message,
			'attempts'             => (int) $order->get_meta( self::ATTEMPTS_META ),
			'async_enabled'        => function_exists( 'bh_is_async_telegra_landing_enabled' )
				? bh_is_async_telegra_landing_enabled( $order )
				: false,
		);
	}

	public static function register_rest_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_visit_status' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'order_id' => array(
						'required' => true,
						'type'     => 'integer',
					),
					'key'      => array(
						'required' => true,
						'type'     => 'string',
					),
				),
			)
		);
	}

	/**
	 * GET /wp-json/bh/v1/telegra-visit-status?order_id=&key=
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_visit_status( $request ) {
		$order_id = absint( $request->get_param( 'order_id' ) );
		$key      = (string) $request->get_param( 'key' );
		$order    = $order_id ? wc_get_order( $order_id ) : false;

		if ( ! $order || ! hash_equals( (string) $order->get_order_key(), $key ) ) {
			return new WP_Error(
				'bh_telegra_status_forbidden',
				__( 'Invalid order or key.', 'bh-features' ),
				array( 'status' => 403 )
			);
		}

		return new WP_REST_Response( self::get_status_payload( $order ), 200 );
	}
}

// Register callbacks as soon as this file loads so AS cron/async always sees them.
BH_Telegra_Async::init();
