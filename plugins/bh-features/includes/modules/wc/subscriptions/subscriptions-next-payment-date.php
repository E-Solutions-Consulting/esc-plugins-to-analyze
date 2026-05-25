<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AH_Subscription_Next_Payment_Date' ) ) {

class AH_Subscription_Next_Payment_Date {

	private $logger;
	private $log_context = [ 'source' => 'ah-subscription-next-payment-date' ];

	public function __construct() {
		$this->logger = wc_get_logger();
		add_action( 'woocommerce_order_status_completed', [ $this, 'update_on_order_completed' ], 10, 1 );
		add_action( 'woocommerce_checkout_subscription_created', [ $this, 'set_on_checkout' ], 10, 3 );
	}

	/**
	 * Update the subscription next payment date when an order is completed.
	 * Handles both the initial parent order and renewal orders.
	 *
	 * @param int $order_id
	 */
	public function update_on_order_completed( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		try {
			$is_renewal = wcs_order_contains_renewal( $order );
			$is_parent  = wcs_order_contains_subscription( $order );

			if ( ! $is_renewal && ! $is_parent ) {
				return;
			}

			$order_type = $is_renewal ? 'renewal' : 'parent';

			$this->logger->info( sprintf(
				'[update_on_order_completed] order_id=%d type=%s',
				$order_id,
				$order_type
			), $this->log_context );

			$base_date = wcs_get_datetime_utc_string( $order->get_date_completed( 'edit' ) );

			if ( empty( $base_date ) ) {
				$this->logger->warning( sprintf(
					'[update_on_order_completed] order_id=%d — date_completed is empty, aborting',
					$order_id
				), $this->log_context );
				return;
			}

			$this->logger->info( sprintf(
				'[update_on_order_completed] order_id=%d base_date=%s',
				$order_id,
				$base_date
			), $this->log_context );

			if ( $is_renewal ) {
				$subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );
			} else {
				$subscriptions = wcs_get_subscriptions_for_order( $order_id, [ 'order_type' => 'parent' ] );
			}

			foreach ( $subscriptions as $subscription ) {
				$subscription_id = $subscription->get_id();

				if ( $subscription->get_status() !== 'active' ) {
					$this->logger->info( sprintf(
						'[update_on_order_completed] subscription_id=%d status=%s — skipped',
						$subscription_id,
						$subscription->get_status()
					), $this->log_context );
					continue;
				}

				$product     = null;
				$has_product = false;

				foreach ( $subscription->get_items() as $item ) {
					$variation_id = $item->get_variation_id();
					$product_id   = $item->get_product_id();
					$product      = wc_get_product( $variation_id ?: $product_id );

					if ( ! $product ) {
						continue;
					}

					$has_product              = true;
					$product_billing_period   = get_post_meta( $product->get_id(), '_subscription_period', true ) ?: 'month';
					$product_billing_interval = (int) get_post_meta( $product->get_id(), '_subscription_period_interval', true ) ?: 1;
					break;
				}

				if ( ! $has_product ) {
					$this->logger->warning( sprintf(
						'[update_on_order_completed] subscription_id=%d — no valid product found, skipped',
						$subscription_id
					), $this->log_context );
					continue;
				}

				$days             = bh_get_renewal_days_for_product( $product );
				$new_next_payment = date( 'Y-m-d H:i:s', strtotime( "+{$days} days", strtotime( $base_date ) ) );

				$subscription_billing_period   = $subscription->get_billing_period();
				$subscription_billing_interval = (int) $subscription->get_billing_interval();

				$this->logger->info( sprintf(
					'[update_on_order_completed] subscription_id=%d product_id=%d days=%d base_date=%s new_next_payment=%s',
					$subscription_id,
					$product->get_id(),
					$days,
					$base_date,
					$new_next_payment
				), $this->log_context );

				$subscription->update_dates( [ 'next_payment' => $new_next_payment ] );

				if ( $subscription_billing_period !== $product_billing_period ) {
					$this->logger->info( sprintf(
						'[update_on_order_completed] subscription_id=%d billing_period %s → %s',
						$subscription_id,
						$subscription_billing_period,
						$product_billing_period
					), $this->log_context );
					$subscription->set_billing_period( $product_billing_period );
				}

				if ( $subscription_billing_interval !== $product_billing_interval ) {
					$this->logger->info( sprintf(
						'[update_on_order_completed] subscription_id=%d billing_interval %d → %d',
						$subscription_id,
						$subscription_billing_interval,
						$product_billing_interval
					), $this->log_context );
					$subscription->set_billing_interval( $product_billing_interval );
				}

				$subscription->save();

				$this->logger->info( sprintf(
					'[update_on_order_completed] subscription_id=%d — saved',
					$subscription_id
				), $this->log_context );
			}
		} catch ( \Throwable $th ) {
			$this->logger->error( sprintf(
				'[update_on_order_completed] order_id=%d error=%s',
				$order_id,
				$th->getMessage()
			), $this->log_context );

			bh_plugins_error_log( [
				'error'    => $th->getMessage(),
				'function' => 'AH_Subscription_Next_Payment_Date::update_on_order_completed',
				'args'     => [ $order_id ],
			] );
		}
	}

	/**
	 * Set an initial next payment date when a subscription is created at checkout.
	 * This is a best-effort initial value — the mandatory calculation happens
	 * when the order is completed via update_on_order_completed().
	 *
	 * @param WC_Subscription $subscription
	 * @param WC_Order        $order
	 * @param WC_Cart         $recurring_cart
	 */
	public function set_on_checkout( WC_Subscription $subscription, WC_Order $order, $recurring_cart ): void {
		try {
			$subscription_id      = $subscription->get_id();
			$current_next_payment = wcs_get_datetime_utc_string( $order->get_date_created( 'edit' ) );
			$plan_days            = BH_DAYS_THREE_MONTH_PLAN;

			foreach ( $subscription->get_items() as $item ) {
				$variation_id = $item->get_variation_id();
				$product_id   = $item->get_product_id();
				$product      = wc_get_product( $variation_id ?: $product_id );

				if ( $product ) {
					$plan_days = bh_get_renewal_days_for_product( $product );
					break;
				}
			}

			$new_next_payment = date( 'Y-m-d H:i:s', strtotime( "+{$plan_days} days", strtotime( $current_next_payment ) ) );

			$dates     = [ 'next_payment' => $new_next_payment ];
			$trial_end = $subscription->get_date( 'trial_end' );

			if ( $trial_end ) {
				$dates['trial_end'] = $new_next_payment;
			}

			$this->logger->info( sprintf(
				'[set_on_checkout] subscription_id=%d order_id=%d days=%d base_date=%s new_next_payment=%s trial_end=%s',
				$subscription_id,
				$order->get_id(),
				$plan_days,
				$current_next_payment,
				$new_next_payment,
				$trial_end ?: 'none'
			), $this->log_context );

			$subscription->update_dates( $dates );
			$subscription->save();

			$this->logger->info( sprintf(
				'[set_on_checkout] subscription_id=%d — saved',
				$subscription_id
			), $this->log_context );
		} catch ( \Throwable $th ) {
			$this->logger->error( sprintf(
				'[set_on_checkout] subscription_id=%d error=%s',
				$subscription->get_id(),
				$th->getMessage()
			), $this->log_context );

			bh_plugins_error_log( [
				'error'    => $th->getMessage(),
				'function' => 'AH_Subscription_Next_Payment_Date::set_on_checkout',
				'args'     => [ $subscription->get_id(), $order->get_id() ],
			] );
		}
	}
}

add_action( 'woocommerce_loaded', function () {
	new AH_Subscription_Next_Payment_Date();
} );

}