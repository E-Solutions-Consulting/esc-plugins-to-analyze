<?php

namespace Objectiv\Plugins\Checkout\Features;

use Objectiv\Plugins\Checkout\Admin\Pages\PageAbstract;
use Objectiv\Plugins\Checkout\Interfaces\SettingsGetterInterface;
use Objectiv\Plugins\Checkout\Managers\SettingsManager;
use Objectiv\Plugins\Checkout\Managers\PlanManager;

/**
 * @link checkoutwc.com
 * @since 5.0.0
 */
class TrustBadges extends FeaturesAbstract {
	protected $trust_badges_field_name;
	private $wc_badges_cache = null;
	private $cache_cart_hash = null;

	/**
	 * TrustBadges constructor.
	 *
	 * @param bool                    $enabled Is feature enabled.
	 * @param bool                    $available Is feature available.
	 * @param string                  $required_plans_list The list of required plans.
	 * @param SettingsGetterInterface $settings_getter The settings getter.
	 * @param string                  $trust_badges_field_name The field name.
	 */
	public function __construct( bool $enabled, bool $available, string $required_plans_list, SettingsGetterInterface $settings_getter, string $trust_badges_field_name ) {
		$this->trust_badges_field_name = $trust_badges_field_name;

		parent::__construct( $enabled, $available, $required_plans_list, $settings_getter );
	}

	protected function run_if_cfw_is_enabled() {
		// Hook into the trust badges filter to add WC review badges
		add_filter( 'cfw_trust_badges', array( $this, 'add_wc_review_badges' ), 10, 2 );

		// Always hook the output action - let it determine at render time if there are badges to show
		$position = $this->settings_getter->get_setting( 'trust_badge_position' );

		$action = 'cfw_checkout_cart_summary';

		if ( 'below_checkout_form' === $position ) {
			$action = 'woocommerce_after_checkout_form';
		}

		if ( 'in_footer' === $position ) {
			$action = 'cfw_before_footer';
		}

		/**
		 * Filter the action to output the trust badges
		 *
		 * @since 9.0.0
		 * @param string $action The action to output the trust badges
		 * @param string $position The position of the trust badges
		 */
		$action = apply_filters( 'cfw_trust_badges_output_action', $action, $position );

		add_action( $action, array( $this, 'output_trust_badges' ), 71 );
	}

	public function output_trust_badges() {
		?>
		<div id="cfw_trust_badges_list" class="cfw-module cfw-trust-badges-position-<?php echo esc_attr( $this->settings_getter->get_setting( 'trust_badge_position' ) ); ?>">
			<h4 class="cfw-trust-badges-list-title"><?php echo do_shortcode( $this->settings_getter->get_setting( 'trust_badges_title' ) ); ?></h4>

			<div class="cfw-tw">
				<div id="cfw-trust-badges"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Add WooCommerce review badges to the trust badges array
	 *
	 * @param array $badges Existing trust badges.
	 * @param bool  $apply_rules Whether to apply rules.
	 * @return array
	 */
	public function add_wc_review_badges( array $badges, bool $apply_rules ): array {
		// Only add WC review badges on frontend, never in admin contexts
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $badges;
		}

		// Add WooCommerce review badges if enabled
		if ( PlanManager::can_access_feature( 'enable_wc_review_badges', 'pro' ) ) {
			$wc_review_badges = $this->get_wc_review_badges();
			$badges           = array_merge( $badges, $wc_review_badges );
		}

		return $badges;
	}

	/**
	 * Get WooCommerce review badges
	 *
	 * @return array
	 */
	private function get_wc_review_badges(): array {

		$badges     = array();
		$limit      = (int) $this->settings_getter->get_setting( 'wc_review_limit' ) ?: 3;
		$min_rating = (int) $this->settings_getter->get_setting( 'wc_review_min_rating' ) ?: 4;
		$source     = $this->settings_getter->get_setting( 'wc_review_source' ) ?: 'cart_first';

		$reviews = $this->get_filtered_wc_reviews( $source, $min_rating, $limit );

		foreach ( $reviews as $review ) {
			$rating      = (int) get_comment_meta( $review->comment_ID, 'rating', true );
			$product     = wc_get_product( $review->comment_post_ID );
			$is_verified = wc_review_is_from_verified_owner( $review->comment_ID );

			$badge_data = array(
				'id'          => 'wc_review_' . $review->comment_ID,
				'template'    => 'review',
				'title'       => $review->comment_author,
				'subtitle'    => $this->format_review_subtitle( $rating, $product, $is_verified ),
				'description' => wp_trim_words( $review->comment_content, 25 ),
				'mode'        => 'text',
			);

			$avatar_url = get_avatar_url( $review->comment_author_email, array( 'size' => 64, 'default' => '404' ) );
			if ( $this->gravatar_exists( $avatar_url ) ) {
				$badge_data['image'] = array( 'url' => $avatar_url );
			}

			$badges[] = $badge_data;
		}

		/**
		 * Filter WC review badges.
		 * Allows external review sources (like reviewbird) to replace the badges.
		 *
		 * @since 11.0.4
		 * @param array  $badges     Array of badge data arrays.
		 * @param string $source     Review source setting (cart_only, cart_first, sitewide).
		 * @param int    $min_rating Minimum star rating.
		 * @param int    $limit      Maximum number of reviews.
		 * @param array  $cart_product_ids Array of product IDs in cart.
		 */
		return apply_filters(
			'cfw_wc_review_badges',
			$badges,
			$source,
			$min_rating,
			$limit,
			$this->get_cart_product_ids()
		);
	}

	/**
	 * Get filtered WooCommerce reviews
	 *
	 * @param string $source Review source priority.
	 * @param int    $min_rating Minimum star rating.
	 * @param int    $limit Maximum number of reviews.
	 * @return array
	 */
	private function get_filtered_wc_reviews( string $source, int $min_rating, int $limit ): array {
		$base_args = array(
			'status'     => 'approve',
			'type'       => 'review',
			'orderby'    => 'rand', // Random selection
			'meta_query' => array(
				array(
					'key'     => 'rating',
					'value'   => $min_rating,
					'compare' => '>=',
				),
			),
		);

		if ( 'cart_only' === $source ) {
			return $this->get_cart_only_reviews( $base_args, $limit );
		}

		if ( 'cart_first' === $source ) {
			return $this->get_cart_first_reviews( $base_args, $limit );
		}

		// Site-wide random reviews
		$base_args['number'] = $limit;
		$comments            = get_comments( $base_args );

		return is_array( $comments ) ? $comments : array();
	}

	/**
	 * Get reviews only from cart products
	 */
	private function get_cart_only_reviews( array $base_args, int $limit ): array {
		$cart_product_ids = $this->get_cart_product_ids();
		if ( empty( $cart_product_ids ) ) {
			return array();
		}

		$base_args['post__in'] = $cart_product_ids;
		$base_args['number']   = $limit;
		$comments              = get_comments( $base_args );

		return is_array( $comments ) ? $comments : array();
	}

	/**
	 * Get reviews from cart products first, then fill remaining slots with non-cart reviews
	 */
	private function get_cart_first_reviews( array $base_args, int $limit ): array {
		$cart_product_ids = $this->get_cart_product_ids();
		$reviews          = array();

		// First get reviews from cart products
		if ( ! empty( $cart_product_ids ) ) {
			$cart_args             = $base_args;
			$cart_args['post__in'] = $cart_product_ids;
			$cart_args['number']   = $limit; // Get up to limit from cart
			$cart_reviews          = get_comments( $cart_args );

			if ( is_array( $cart_reviews ) ) {
				$reviews = $cart_reviews;
			}
		}

		// If we need more reviews, get random non-cart reviews
		$remaining = $limit - count( $reviews );
		if ( $remaining > 0 ) {

			$non_cart_args = $base_args;
			if ( ! empty( $cart_product_ids ) ) {
				$non_cart_args['post__not_in'] = $cart_product_ids; // Exclude cart products
			}
			$non_cart_args['number'] = $remaining;
			$non_cart_reviews        = get_comments( $non_cart_args );

			if ( is_array( $non_cart_reviews ) ) {
				$reviews = array_merge( $reviews, $non_cart_reviews );
			}
		}

		return $reviews;
	}

	/**
	 * Get product IDs from current cart
	 *
	 * @return array
	 */
	private function get_cart_product_ids(): array {
		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			return array();
		}

		$product_ids = array();
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product_ids[] = $cart_item['product_id'];
			// Also include variation parent if it's a variation
			if ( $cart_item['variation_id'] ) {
				$product_ids[] = $cart_item['variation_id'];
			}
		}

		return array_unique( $product_ids );
	}

	/**
	 * Format review subtitle with product name and verified purchase indicator
	 *
	 * @param int        $rating Star rating.
	 * @param WC_Product $product Product object.
	 * @param bool       $is_verified Whether the review is from a verified purchaser.
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
		$response = wp_remote_head( $url );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		return wp_remote_retrieve_response_code( $response ) === 200;
	}
}
