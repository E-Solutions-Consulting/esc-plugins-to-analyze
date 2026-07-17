<?php

namespace Objectiv\Plugins\Checkout\Factories;

use Objectiv\Plugins\Checkout\Features\ABTesting;
use Objectiv\Plugins\Checkout\Features\ABTesting\ABTestTypeRegistry;
use Objectiv\Plugins\Checkout\Model\ABTests\NullABTest;
use Objectiv\Plugins\Checkout\Interfaces\ABTestInterface;

class ABTestFactory {
	public static function get( int $post_id ): ABTestInterface {
		if ( empty( $post_id ) ) { // because get_post tries to snag the global post if it's empty
			return new NullABTest();
		}

		$post = get_post( $post_id );

		if ( ABTesting::get_post_type() !== $post->post_type ) {
			return new NullABTest();
		}

		if ( empty( $post ) ) {
			return new NullABTest();
		}

		$test_type = get_post_meta( $post_id, 'cfw_ab_test_type', true );

		// Use registry to get the test class
		$test_class = ABTestTypeRegistry::get_test_class( $test_type );

		if ( $test_class && class_exists( $test_class ) ) {
			$test = new $test_class();
			$test->load( $post );
			return $test;
		}

		return new NullABTest();
	}

	/**
	 * @param string $status The status of bump posts to return.
	 * @param array  $args Any additional query args.
	 *
	 * @return array
	 */
	public static function get_all( $status = 'publish', $args = array() ): array {
		$posts = get_posts(
			wp_parse_args(
				$args,
				array(
					'post_type'      => ABTesting::get_post_type(),
					'post_status'    => $status,
					'posts_per_page' => -1,
				)
			)
		);

		$non_null_tests  = array();
		$null_test_class = get_class( new NullABTest() );

		foreach ( $posts as $post ) {
			$test = self::get( $post->ID );

			if ( get_class( $test ) === $null_test_class ) {
				continue;
			}

			$non_null_tests[] = $test;
		}

		return array_filter( $non_null_tests );
	}
}
