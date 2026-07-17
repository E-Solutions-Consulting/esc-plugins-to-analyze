<?php

namespace Objectiv\Plugins\Checkout\Factories;

use Objectiv\Plugins\Checkout\Interfaces\BumpInterface;
use Objectiv\Plugins\Checkout\Model\Bumps\Bump;
use Objectiv\Plugins\Checkout\Model\Bumps\BumpAbstract;
use Objectiv\Plugins\Checkout\Model\Bumps\NullBump;

class BumpFactory {
	public static function get( int $post_id ): BumpInterface {
		if ( empty( $post_id ) ) { // because get_post tries to snag the global post if it's empty
			return new NullBump();
		}

		$post = get_post( $post_id );

		if ( empty( $post ) ) {
			return new NullBump();
		}

		$bump = new Bump();
		$bump->load( $post );

		return $bump;
	}

	/**
	 * @param array $status The status of bump posts to return.
	 *
	 * @return array
	 */
	public static function get_all( $status = 'any' ): array {
		$posts = get_posts(
			array(
				'post_type'        => BumpAbstract::get_post_type(),
				'numberposts'      => -1,
				'suppress_filters' => false,
				'orderby'          => 'menu_order',
				'order'            => 'ASC',
				'post_status'      => $status,
				'post_parent'      => 0, // Exclude A/B testing variants
			)
		);

		$non_null_bumps  = array();
		$null_bump_class = get_class( new NullBump() );

		foreach ( $posts as $post ) {
			$bump = self::get( $post->ID );

			if ( get_class( $bump ) === $null_bump_class ) {
				continue;
			}

			$non_null_bumps[] = $bump;
		}

		return array_filter( $non_null_bumps );
	}
}
