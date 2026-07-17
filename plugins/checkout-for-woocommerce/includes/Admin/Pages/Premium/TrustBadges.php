<?php

namespace Objectiv\Plugins\Checkout\Admin\Pages\Premium;

use Objectiv\Plugins\Checkout\Admin\Pages\PageAbstract;
use Objectiv\Plugins\Checkout\Managers\SettingsManager;

/**
 * @link checkoutwc.com
 * @since 5.0.0
 * @package Objectiv\Plugins\Checkout\Admin\Pages
 */
class TrustBadges extends PageAbstract {
	public function __construct() {
		parent::__construct( __( 'Trust Badges', 'checkout-wc' ), 'cfw_manage_trust_badges', 'trust-badges' );
	}

	public function output() {
		?>
		<div id="cfw-new-trust-badge-settings-page"></div>
		<?php
	}

	public function maybe_set_script_data() {
		if ( ! $this->is_current_page() ) {
			return;
		}

		$trust_badges = cfw_get_trust_badges( false );

		// Seed store policies with internal ID that won't change
		foreach ( $trust_badges as $index => $badge ) {
			$badge['id'] = 'tb-' . $index;

			$trust_badges[ $index ] = $badge;
		}

		$this->set_script_data(
			array(
				'settings' => array(
					'enable_trust_badges'     => SettingsManager::instance()->get_setting( 'enable_trust_badges' ) === 'yes',
					'trust_badge_position'    => SettingsManager::instance()->get_setting( 'trust_badge_position' ),
					'trust_badges_title'      => SettingsManager::instance()->get_setting( 'trust_badges_title' ),
					'trust_badges'            => array_values( $trust_badges ),
					'enable_wc_review_badges' => SettingsManager::instance()->get_setting( 'enable_wc_review_badges' ) === 'yes',
					'wc_review_source'        => SettingsManager::instance()->get_setting( 'wc_review_source' ),
					'wc_review_min_rating'    => SettingsManager::instance()->get_setting( 'wc_review_min_rating' ),
					'wc_review_limit'         => (int) SettingsManager::instance()->get_setting( 'wc_review_limit' ),
					'reviewbird_available'    => function_exists( 'reviewbird_get_product_reviews' )
						&& function_exists( 'reviewbird_get_store_id' )
						&& reviewbird_get_store_id(),
				),
				'plan'     => $this->get_plan_data(),
			)
		);
	}
}
