<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

class Reviewbird extends CompatibilityAbstract {

	public function is_available(): bool {
		return function_exists( 'reviewbird_get_product_reviews' )
			&& function_exists( 'reviewbird_get_store_id' )
			&& reviewbird_get_store_id();
	}

	public function run_immediately() {
		add_filter( 'cfw_wc_review_badges', array( $this, 'provide_reviewbird_badges' ), 10, 5 );
	}

	/**
	 * Replace WC review badges with reviewbird badges.
	 *
	 * @param array  $badges           Default WC badges (fallback if reviewbird fails).
	 * @param string $source           Review source setting.
	 * @param int    $min_rating       Minimum star rating.
	 * @param int    $limit            Maximum reviews.
	 * @param array  $cart_product_ids Product IDs in cart.
	 * @return array
	 */
	public function provide_reviewbird_badges( array $badges, string $source, int $min_rating, int $limit, array $cart_product_ids ): array {
		$reviews = $this->get_reviewbird_reviews( $source, $min_rating, $limit, $cart_product_ids );

		// If no reviewbird reviews found, return original WC badges
		if ( empty( $reviews ) ) {
			return $badges;
		}

		// Convert reviewbird reviews to badge format (matches WC review badge format exactly)
		$reviewbird_badges = array();

		foreach ( $reviews as $review ) {
			$author_name = $review['author']['name'] ?? 'Anonymous';
			$product     = isset( $review['product_id'] ) ? wc_get_product( $review['product_id'] ) : null;
			$is_verified = ! empty( $review['verified_purchase'] );

			$badge_data = array(
				'id'          => 'wc_review_' . $review['id'],
				'template'    => 'review',
				'title'       => $author_name,
				'subtitle'    => $this->format_review_subtitle( (int) $review['rating'], $product, $is_verified ),
				'description' => wp_trim_words( $review['body'] ?? '', 25 ),
				'mode'        => 'text',
			);

			$avatar_url = get_avatar_url(
				$review['author']['email_hash'] . '@md5.gravatar.com',
				array(
					'size'    => 64,
					'default' => '404',
				)
			);
			if ( $this->gravatar_exists( $avatar_url ) ) {
				$badge_data['image'] = array( 'url' => $avatar_url );
			}

			$reviewbird_badges[] = $badge_data;
		}

		return $reviewbird_badges;
	}

	/**
	 * Get reviews from reviewbird based on source setting.
	 *
	 * @param string $source           Review source setting.
	 * @param int    $min_rating       Minimum star rating.
	 * @param int    $limit            Maximum reviews.
	 * @param array  $cart_product_ids Product IDs in cart.
	 * @return array
	 */
	private function get_reviewbird_reviews( string $source, int $min_rating, int $limit, array $cart_product_ids ): array {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$cache_key = 'cfw_reviewbird_reviews_' . md5( $source . $min_rating . $limit . serialize( $cart_product_ids ) );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$args = array(
			'sort'   => 'rating_high',
			'rating' => $min_rating,
		);

		// Early return for cart_only with empty cart - no need to cache this
		if ( 'cart_only' === $source && empty( $cart_product_ids ) ) {
			return array();
		}

		$reviews = array();

		if ( 'cart_only' === $source ) {
			$response = reviewbird_get_product_reviews( $cart_product_ids, $limit, $args );
			$reviews  = $this->extract_reviews( $response );
		} elseif ( 'cart_first' === $source ) {
			if ( ! empty( $cart_product_ids ) ) {
				$response = reviewbird_get_product_reviews( $cart_product_ids, $limit, $args );
				$reviews  = $this->extract_reviews( $response );
			}

			// Fill remaining with sitewide reviews
			if ( count( $reviews ) < $limit ) {
				$response = reviewbird_get_product_reviews( array(), $limit, $args );
				$sitewide = $this->extract_reviews( $response );

				$existing_ids = array_column( $reviews, 'id' );
				foreach ( $sitewide as $review ) {
					if ( count( $reviews ) >= $limit ) {
						break;
					}
					if ( ! in_array( $review['id'], $existing_ids, true ) ) {
						$reviews[] = $review;
					}
				}
			}
		} else {
			// Sitewide
			$response = reviewbird_get_product_reviews( array(), $limit, $args );
			$reviews  = $this->extract_reviews( $response );
		}

		set_transient( $cache_key, $reviews, HOUR_IN_SECONDS );
		return $reviews;
	}

	/**
	 * Extract reviews array from API response, returning empty array on error.
	 *
	 * @param mixed $response API response.
	 * @return array
	 */
	private function extract_reviews( $response ): array {
		if ( is_wp_error( $response ) || empty( $response['reviews'] ) ) {
			return array();
		}
		return $response['reviews'];
	}

	/**
	 * Format review subtitle with product name and verified indicator.
	 *
	 * @param int              $rating      Star rating.
	 * @param \WC_Product|null $product     Product object.
	 * @param bool             $is_verified Whether the review is from a verified purchaser.
	 * @return string
	 */
	private function format_review_subtitle( int $rating, $product, bool $is_verified ): string {
		$subtitle = $product ? $product->get_name() : '';

		if ( $is_verified ) {
			$subtitle .= $subtitle ? ' • Verified Purchase' : 'Verified Purchase';
		}

		return $subtitle;
	}

	/**
	 * Check if a gravatar exists by making a HEAD request
	 *
	 * @param string $url Gravatar URL with d=404.
	 * @return bool
	 */
	private function gravatar_exists( string $url ): bool {
		// Extract email hash from URL for cache key
		preg_match( '/avatar\/([a-f0-9]+)/', $url, $matches );
		$cache_key = 'cfw_gravatar_exists_' . ( $matches[1] ?? md5( $url ) );

		$cached = get_transient( $cache_key );
		if ( false !== $cached ) {
			return 'yes' === $cached;
		}

		$response = wp_remote_head( $url );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$exists = wp_remote_retrieve_response_code( $response ) === 200;

		set_transient( $cache_key, $exists ? 'yes' : 'no', DAY_IN_SECONDS );
		return $exists;
	}
}
