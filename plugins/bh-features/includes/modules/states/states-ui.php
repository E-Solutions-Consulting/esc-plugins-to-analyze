<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AH_States_UI {

    public function __construct() {
		add_action( 'wp_enqueue_scripts', [$this, 'register_map_scripts'] );
		add_shortcode( 'ah_us_map', [$this, 'ah_us_map_shortcode'] );
	}

    /**
	 * Register and enqueue scripts for AH map.
	 */
	function register_map_scripts() {
		// Register SimpleMaps core JS (adjust path as needed).
		wp_register_script(
			'ah-simplemaps-usmap',
			plugins_url( 'assets/js/simplemaps_usmap.js', __FILE__ ),
			[],
			null,
			true
		);
	}
	

	/**
	 * Shortcode [ah_us_map].
	 *
	 * Very lightweight: just enqueues the two JS files and prints the container div.
	 */
	function ah_us_map_shortcode( $atts ) {

		// Ensure the mapdata.js URL exists.
		if ( ! class_exists( 'AH_MapData_Generator' ) ) {
			return '<!-- AH Map: generator class not available -->';
		}

		$mapdata_url = AH_MapData_Generator::get_mapdata_url();

		if ( ! $mapdata_url ) {
			return '<!-- AH Map: mapdata.js not found. Please save states configuration. -->';
		}

		// Enqueue mapdata.js (no version param to keep it fully cacheable; you can manage cache purge via CDN).
		wp_enqueue_script(
			'ah-simplemaps-mapdata',
			$mapdata_url,
			[],
			null,
			true
		);

		// Enqueue SimpleMaps core after mapdata.
		wp_enqueue_script(
			'ah-simplemaps-usmap',
			plugins_url( 'assets/js/simplemaps_usmap.js', __FILE__ ),
			[ 'ah-simplemaps-mapdata' ],
			null,
			true
		);

		// The JS library will target #map by default unless you configure otherwise.
		return '<div id="map"></div>';
	}

}

// Backwards compatibility alias for old code still using BH_Common_States.
//if ( ! class_exists( 'AH_States_UI' ) ) {
    new AH_States_UI();
//}
