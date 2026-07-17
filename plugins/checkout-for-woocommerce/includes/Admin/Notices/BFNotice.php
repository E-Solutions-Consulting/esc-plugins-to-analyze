<?php

namespace Objectiv\Plugins\Checkout\Admin\Notices;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Objectiv\Plugins\Checkout\Managers\PlanManager;

class BFNotice extends NoticeAbstract {
	public function maybe_add( string $id = '', string $title = '', string $message = '', array $options = array() ) {
		$id      = 'cfw_black_friday_2025';
		$title   = __( 'Black Friday Sale - 40% Off', 'checkout-wc' );
		$message = '<p>' . __( "You're missing out on revenue growth tools like Order Bumps, Upsells, Abandoned Cart Recovery and more! Save 40% on CheckoutWC pro plans for Black Friday, ends December 4th", 'checkout-wc' ) . '</p>';

		// Add a button linking to the Black Friday page
		$message .= sprintf(
			'<p><a href="%s" target="_blank" class="button button-primary">%s</a></p>',
			add_query_arg(
				array(
					'utm_source'   => 'WordPress',
					'utm_medium'   => 'bfnotice',
					'utm_campaign' => 'liteplugin',
				),
				'https://www.checkoutwc.com/black-friday-cyber-monday/'
			),
			__( 'Get CheckoutWC Pro Now!', 'checkout-wc' )
		);

		parent::maybe_add(
			$id,
			$title,
			$message,
			array(
				'type'        => 'info',
				'dismissible' => true,
			)
		);
	}

	protected function should_add(): bool {
		if ( PlanManager::has_premium_plan_or_higher() ) {
			return false;
		}

		if ( empty( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}

		$page = sanitize_text_field( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'wc-orders' !== $page && stripos( $page, 'cfw-' ) === false ) {
			return false;
		}

		try {
			// Create deadline: December 4, 2025 at 11:59:59 PM EST
			$deadline = new DateTimeImmutable( '2025-12-04 23:59:59', new DateTimeZone( 'America/New_York' ) );

			// Get current time in EST
			$now = new DateTimeImmutable( 'now', new DateTimeZone( 'America/New_York' ) );

			// Return true if we're before the deadline
			return $now < $deadline;
		} catch ( Exception $e ) {
			return false;
		}

		return false;
	}
}
