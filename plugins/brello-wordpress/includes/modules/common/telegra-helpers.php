<?php
/**
 * Telegra product mapping helpers.
 *
 * The Telegra plugin (telemdnow-wp-plugin) stores the mapping on each product or
 * variation (telemdnow_product_variation_id, telemdnow_product_dispensingQuantity)
 * and reads the project ID from the parent product when it sends the order
 * (send_order_to_telegra()). These helpers read that mapping the same way and
 * give the Telegra product names, cached, without blocking a page on the API.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'BH_TELEGRA_NO_PROCESSING' ) ) {
	define( 'BH_TELEGRA_NO_PROCESSING', 'no_telemdnow_processing' );
}

if ( ! function_exists( 'bh_get_telegra_mapping' ) ) {
	/**
	 * Telegra mapping of a product or variation, as the order sender reads it.
	 *
	 * @param int $product_id Product or variation ID.
	 * @return array{status: string, product_variation: string, quantity: string, project_id: string}
	 *         status: mapped | not_mapped | no_processing.
	 */
	function bh_get_telegra_mapping( int $product_id ): array {
		$product_variation = (string) get_post_meta( $product_id, 'telemdnow_product_variation_id', true );
		$parent_id         = (int) wp_get_post_parent_id( $product_id );

		if ( BH_TELEGRA_NO_PROCESSING === $product_variation ) {
			$status = 'no_processing';
		} elseif ( '' === $product_variation ) {
			$status = 'not_mapped';
		} else {
			$status = 'mapped';
		}

		return array(
			'status'            => $status,
			'product_variation' => $product_variation,
			'quantity'          => (string) get_post_meta( $product_id, 'telemdnow_product_dispensingQuantity', true ),
			'project_id'        => (string) get_post_meta( $parent_id > 0 ? $parent_id : $product_id, 'telemdnow_product_projectId', true ),
		);
	}
}

if ( ! function_exists( 'bh_get_telegra_auth_token' ) ) {
	/**
	 * Telegra API token with a bounded request (15 s), shared with the Telegra plugin.
	 *
	 * Uses the token the Telegra plugin caches (telemdnow_auth_token) and, when there is
	 * none, it was not issued for the current REST URL and affiliate account, or $refresh
	 * is true, asks /auth/client the same way the plugin does. The Telegra settings page
	 * does not clear that token when the account or environment changes, so the account it
	 * belongs to is remembered here. A new token replaces the cached one only on success,
	 * so a failed refresh never leaves orders without a token.
	 *
	 * @param bool $refresh Ask for a new token even when one is cached (it was rejected).
	 * @return string|WP_Error
	 */
	function bh_get_telegra_auth_token( bool $refresh = false ) {
		$base_url = untrailingslashit( (string) get_option( 'telemdnow_rest_url' ) );
		$identity = md5( $base_url . '|' . (string) get_option( 'telemdnow_affiliate_username' ) );

		$cached = get_transient( 'telemdnow_auth_token' );
		if ( ! $refresh && is_string( $cached ) && '' !== $cached && get_option( 'bh_telegra_token_identity' ) === $identity ) {
			return $cached;
		}

		$response = wp_remote_post(
			$base_url . '/auth/client',
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( get_option( 'telemdnow_affiliate_username' ) . ':' . get_option( 'telemdnow_affiliate_password' ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic auth.
					'Origin'        => get_site_url(),
					'Referer'       => get_site_url(),
					'Content-Type'  => 'application/json',
				),
			)
		);

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = is_wp_error( $response ) ? null : json_decode( wp_remote_retrieve_body( $response ) );
		if ( ( 200 !== $code && 201 !== $code ) || ! is_object( $body ) || empty( $body->token ) || ! is_string( $body->token ) ) {
			return new WP_Error( 'bh_telegra_auth', __( 'Telegra did not answer. Showing IDs only.', 'bh-features' ) );
		}

		set_transient( 'telemdnow_auth_token', $body->token, 0 ); // Same key and lifetime as the Telegra plugin.
		update_option( 'bh_telegra_token_identity', $identity, false );

		return $body->token;
	}
}

if ( ! function_exists( 'bh_get_telegra_product_names' ) ) {
	/**
	 * Telegra product variation names keyed by ID (pvt::...), cached for 6 hours per
	 * Telegra REST URL and affiliate account.
	 *
	 * Calls the Telegra API on a cache miss (15 s timeout per page), so do not call it
	 * while rendering a page: use it from an AJAX request.
	 *
	 * @return array<string, string>|WP_Error
	 */
	function bh_get_telegra_product_names() {
		$base_url = untrailingslashit( (string) get_option( 'telemdnow_rest_url' ) );

		// One cache per Telegra environment and account: switching either never shows the other's list.
		$cache_key = 'bh_telegra_product_names_' . md5( $base_url . '|' . (string) get_option( 'telemdnow_affiliate_username' ) );

		$cached = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		if ( '' === $base_url || '' === (string) get_option( 'telemdnow_affiliate_username' ) ) {
			return new WP_Error( 'bh_telegra_not_configured', __( 'Telegra is not configured.', 'bh-features' ) );
		}

		$names    = array();
		$complete = false;
		for ( $page = 1; $page <= 50; $page++ ) {
			$response = null;
			// A cached token may have been revoked by Telegra: on a 401, ask for a new one and retry once.
			for ( $attempt = 0; $attempt < 2; $attempt++ ) {
				$token = bh_get_telegra_auth_token( $attempt > 0 );
				if ( is_wp_error( $token ) ) {
					return $token;
				}
				$url      = add_query_arg(
					array(
						'page'         => $page,
						'access_token' => rawurlencode( $token ),
					),
					$base_url . '/productVariations'
				);
				$response = wp_remote_get( $url, array( 'timeout' => 15 ) );
				if ( 401 !== wp_remote_retrieve_response_code( $response ) ) {
					break;
				}
			}

			// Any failed or malformed page: nothing is cached, so a partial list never hides valid mappings.
			$code = (int) wp_remote_retrieve_response_code( $response );
			$body = is_wp_error( $response ) ? null : json_decode( wp_remote_retrieve_body( $response ) );
			if ( $code < 200 || $code >= 300 || ! is_object( $body ) || ! isset( $body->productVariations ) || ! is_array( $body->productVariations ) ) {
				return new WP_Error( 'bh_telegra_api', __( 'Telegra did not answer. Showing IDs only.', 'bh-features' ) );
			}

			$items = $body->productVariations;
			$new   = 0;
			foreach ( $items as $item ) {
				if ( isset( $item->id ) && ! isset( $names[ (string) $item->id ] ) ) {
					$names[ (string) $item->id ] = (string) ( $item->description ?? $item->id );
					++$new;
				}
			}

			$total = ( is_object( $body ) && isset( $body->count ) ) ? (int) $body->count : 0;
			if ( $total > 0 && count( $names ) >= $total ) {
				$complete = true;
				break;
			}
			// An empty page or one with nothing new (API without pagination) ends the list only when
			// Telegra sends no count; with a count still unread the list is partial and is not cached.
			if ( 0 === $new ) {
				$complete = ( 0 === $total );
				break;
			}
		}

		// Page limit reached, or Telegra stopped before its count: do not cache a partial list.
		if ( ! $complete ) {
			return new WP_Error( 'bh_telegra_api', __( 'Telegra did not answer. Showing IDs only.', 'bh-features' ) );
		}

		set_transient( $cache_key, $names, 6 * HOUR_IN_SECONDS );

		return $names;
	}
}
