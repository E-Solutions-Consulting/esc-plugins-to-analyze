<?php
namespace Objectiv\Plugins\Checkout\Stats\Consumers;

use ConsumerStrategies_AbstractConsumer;

class WPMixPanelConsumer extends ConsumerStrategies_AbstractConsumer {
	/**
	 * @var string the host to connect to (e.g. api.mixpanel.com)
	 */
	protected $host;

	/**
	 * @var string the host-relative endpoint to write to (e.g. /engage)
	 */
	protected $endpoint;

	/**
	 * @var int timeout The maximum number of seconds to allow HTTP request to execute. Default is 30 seconds.
	 */
	protected $timeout;

	/**
	 * @var string the protocol to use for the HTTP connection
	 */
	protected $protocol;

	/**
	 * Creates a new WPMixPanelConsumer and assigns properties from the $options array
	 *
	 * @param array $options The options array.
	 */
	public function __construct( $options = array() ) {
		parent::__construct( $options );

		$this->host     = $options['host'] ?? 'api.mixpanel.com';
		$this->endpoint = $options['endpoint'] ?? '/track';
		$this->timeout  = $options['timeout'] ?? 30;
		$this->protocol = isset( $options['use_ssl'] ) && true === $options['use_ssl'] ? 'https' : 'http';
	}

	/**
	 * Write to the MixPanel API using the WordPress HTTP API
	 *
	 * @param array $batch The batch array.
	 * @return bool
	 */
	public function persist( $batch ): bool {
		if ( count( $batch ) > 0 ) {
			$url  = $this->protocol . '://' . $this->host . $this->endpoint;
			$data = 'data=' . $this->_encode( $batch );

			if ( $this->_debug() ) {
				$this->_log( "Making WP HTTP request to $url" );
			}

			$response = wp_remote_post(
				$url,
				array(
					'timeout'     => $this->timeout,
					'redirection' => 5,
					'httpversion' => '1.1',
					'blocking'    => true,
					'headers'     => array(
						'Content-Type' => 'application/x-www-form-urlencoded',
						'Accept'       => 'application/json',
					),
					'body'        => $data,
					'cookies'     => array(),
				)
			);

			if ( is_wp_error( $response ) ) {
				$this->_handleError( $response->get_error_code(), $response->get_error_message() );
				return false;
			}

			$response_code = wp_remote_retrieve_response_code( $response );
			$response_body = wp_remote_retrieve_body( $response );

			if ( 200 !== $response_code ) {
				$this->_handleError( $response_code, $response_body );
				return false;
			}

			if ( trim( $response_body ) !== '1' ) {
				$this->_handleError( 0, $response_body );
				return false;
			}
		}

		return true;
	}
}
