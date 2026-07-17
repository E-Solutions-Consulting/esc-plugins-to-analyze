<?php

namespace Objectiv\Plugins\Checkout\Admin\Notices;

use Objectiv\Plugins\Checkout\Model\AlternativePlugin;
use Objectiv\Plugins\Checkout\Model\DetectedPaymentGateway;
use CheckoutWC\StellarWP\Installer\Installer;

class GatewayProblemsNotice extends NoticeAbstract {
	protected function should_add(): bool {
		return true;
	}

	public function build( DetectedPaymentGateway $gateway ) {
		$message  = sprintf( '<p><strong>%s</strong> has problems with CheckoutWC.</p>', $gateway->title );
		$message .= sprintf( '<p>Details: %s</p>', $gateway->recommendation );

		if ( $gateway->substitute instanceof AlternativePlugin && $gateway->substitute->can_be_installed ) {
			$installer = Installer::get();
			$installer->register_plugin( $gateway->substitute->slug, $gateway->substitute->title );

			if ( $installer->is_active( $gateway->substitute->slug ) ) {
				return;
			}

			if ( ! $installer->is_installed( $gateway->substitute->slug ) ) {
				$message .= $installer->get_plugin_button( $gateway->substitute->slug, 'install', sprintf( 'Install %s', $gateway->substitute->title ), cfw_get_current_admin_url() );
			} else {
				$message .= $installer->get_plugin_button( $gateway->substitute->slug, 'activate', sprintf( 'Activate %s', $gateway->substitute->title ), cfw_get_current_admin_url() );
			}
		}

		$options = [
			'type'  => 'error',
			'image' => file_get_contents( CFW_PATH . '/assets/images/cfw.svg' ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			'mode'  => 'deferred',
		];

		$this->maybe_add( 'cfw_gateway_problem_' . $gateway->id, 'CheckoutWC Gateway Problem Detected', $message, $options );
	}
}
