<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AH_MapData_Generator {

    const MAP_DIR_NAME  = 'ah-map';
    const MAP_FILE_NAME = 'mapdata.js';

    /**
     * Generate the static mapdata.js file in uploads directory.
     *
     * This should be called after the states config is successfully saved.
     *
     * @return void
     */
    public static function generate() {
        $config      = AH_Licensed_States_Manager::get_config();
        $all_states  = AH_States::get_all();
        $visual      = isset( $config['visual'] ) ? $config['visual'] : [];
        $statuses    = isset( $config['statuses'] ) ? $config['statuses'] : [];

        // Build state-specific settings ---------------------------------------------
        $state_specific = [];

        $per_state_visual = $config['per_state_visual'] ?? [];

        foreach ( $all_states as $code => $label ) {

            // Get status for current state (available, unavailable, in_progress)
            $status_slug = AH_Licensed_States_Manager::get_state_status( $code );

            // Visual config for the status
            $vis = $visual[ $status_slug ] ?? [];

            // Override config for this state (if any)
            $override = $per_state_visual[ $code ] ?? [];

            // Status label/description for tooltips
            $status_label = $statuses[ $status_slug ]['label'] ?? ucfirst( $status_slug );
            $status_desc  = $statuses[ $status_slug ]['description'] ?? $status_label;

            // Base entry
            $entry = [
                'name'        => $label,
                'description' => $status_desc,
            ];

            /**
             * STATE BACKGROUND COLOR
             * Priority:
             *   1. Per-state override
             *   2. Status visual config
             *   3. No fallback → SimpleMaps default
             */
            if ( ! empty( $override['state_color'] ) ) {
                $entry['color'] = $override['state_color'];
            } elseif ( ! empty( $vis['state_color'] ) ) {
                $entry['color'] = $vis['state_color'];
            }

            /**
             * STATE BACKGROUND HOVER COLOR
             */
            if ( ! empty( $override['state_hover_color'] ) ) {
                $entry['hover_color'] = $override['state_hover_color'];
            } elseif ( ! empty( $vis['state_hover_color'] ) ) {
                $entry['hover_color'] = $vis['state_hover_color'];
            }

            /**
             * LABEL TEXT COLOR (NORMAL)
             * This is applied by SimpleMaps only when label_color is set here.
             */
            if ( ! empty( $override['label_color'] ) ) {
                $entry['label_color'] = $override['label_color'];
            } elseif ( ! empty( $vis['label_color'] ) ) {
                $entry['label_color'] = $vis['label_color'];
            }

            /**
             * LABEL TEXT COLOR (HOVER)
             */
            if ( ! empty( $override['label_hover_color'] ) ) {
                $entry['label_hover_color'] = $override['label_hover_color'];
            } elseif ( ! empty( $vis['label_hover_color'] ) ) {
                $entry['label_hover_color'] = $vis['label_hover_color'];
            }

            /**
             * Mark state as inactive when unavailable so SimpleMaps dims it.
             */
            if ( $status_slug === 'unavailable' ) {
                $entry['inactive'] = 'yes';
            }

            // Assign to map
            $state_specific[ $code ] = $entry;
        }


        $map_array = [
            'main_settings'  => self::get_main_settings(),
            'state_specific' => $state_specific,
            'locations'      => new stdClass(), // {}
            'labels'         => self::get_labels(), // you can refine these positions later
            'legend'         => self::build_legend( $statuses, $visual ),
            'regions'        => new stdClass(),
            'data'           => new stdClass(),
        ];

        $js_payload = 'var simplemaps_usmap_mapdata = ' . wp_json_encode(
            $map_array,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) . ';';

        self::write_mapdata_file( $js_payload );
    }

    /**
     * Base main_settings for SimpleMaps US map.
     * You can tweak these to match your previous mapdata.js.
     *
     * @return array
     */
    protected static function get_main_settings() {
        return [
            // General
            'width'                  => 'responsive',
            'background_color'       => '#0c0b0b',
            'background_transparent' => 'yes',
            'border_color'           => '#ffffff',
            'popups'                 => 'detect',

            // State defaults
            'state_description'      => 'State',
            'state_color'            => '#1F0159',
            'state_hover_color'      => '#FAF8A2',
            'state_url'              => '',
            'border_size'            => 1.5,
            'all_states_inactive'    => 'no',
            'all_states_zoomable'    => 'no',

            // Location defaults (not used for now)
            'location_description'   => 'Location',
            'location_color'         => '#FF0067',
            'location_opacity'       => 0.8,
            'location_hover_opacity' => 1,
            'location_url'           => '',
            'location_size'          => 25,
            'location_type'          => 'circle',
            'location_image_source'  => 'marker',
            'location_border_color'  => '#FFFFFF',
            'location_border'        => 2,
            'location_hover_border'  => 2.5,
            'all_locations_inactive' => 'no',

            // Label defaults
            'label_color'            => '#ffffff',
            'label_hover_color'      => '#1F0159',
            'label_size'             => 16,
            'label_font'             => 'Arial',
            'hide_labels'            => 'no',

            // Zoom settings
            'zoom'                   => 'yes',
            'back_image'             => 'no',
            'initial_back'           => 'no',
            'initial_zoom'           => -1,
            'initial_zoom_solo'      => 'no',
            'region_opacity'         => 1,
            'region_hover_opacity'   => 0.6,
            'zoom_out_incrementally' => 'yes',
            'zoom_percentage'        => 0.99,
            'zoom_time'              => 0.5,

            // Popup settings
            'popup_color'            => '#ffffff',
            'popup_opacity'          => 0.9,
            'popup_shadow'           => 1,
            'popup_corners'          => 5,
            'popup_font'             => '12px/1.5 Arial, Helvetica, sans-serif',
            'popup_nocss'            => 'no',

            // Advanced
            'link_text'              => 'View website',
        ];
    }

    /**
     * Labels for each state. You can refine positions based on your current mapdata.js.
     *
     * @return array
     */
    protected static function get_labels() {
        $labels = [
            'NH' => [
                'parent_id' => 'NH',
                'x' => 932,
                'y' => 183,
            ],
            'VT' => [
                'parent_id' => 'VT',
                'x' => 883,
                'y' => 243,
            ],
            'RI' => [
                'parent_id' => 'RI',
                'x' => 932,
                'y' => 273,
            ],
            'NJ' => [
                'parent_id' => 'NJ',
                'x' => 883,
                'y' => 273,
            ],
            'DE' => [
                'parent_id' => 'DE',
                'x' => 883,
                'y' => 303,
            ],
            'MD' => [
                'parent_id' => 'MD',
                'x' => 932,
                'y' => 303,
            ],
            'DC' => [
                'parent_id' => 'DC',
                'x' => 884,
                'y' => 332,
            ],
            'MA' => [
                'parent_id' => 'MA',
                'x' => 932,
                'y' => 213,
            ],
            'CT' => [
                'parent_id' => 'CT',
                'x' => 932,
                'y' => 243,
            ],
            'HI' => [
                'parent_id' => 'HI',
                'x' => 305,
                'y' => 565,
                
            ],
            'AK' => [
                'parent_id' => 'AK',
                'x' => 113,
                'y' => 495,
            ],
            'FL' => [
                'parent_id' => 'FL',
                'x' => 773,
                'y' => 510,
            ],
            'ME' => [
                'parent_id' => 'ME',
                'x' => 893,
                'y' => 85,
            ],
            'NY' => [
                'parent_id' => 'NY',
                'x' => 815,
                'y' => 158,
            ],
            'PA' => [
                'parent_id' => 'PA',
                'x' => 786,
                'y' => 210,
            ],
            'VA' => [
                'parent_id' => 'VA',
                'x' => 790,
                'y' => 282,
            ],
            'WV' => [
                'parent_id' => 'WV',
                'x' => 744,
                'y' => 270,
            ],
            'OH' => [
                'parent_id' => 'OH',
                'x' => 700,
                'y' => 240,
            ],
            'IN' => [
                'parent_id' => 'IN',
                'x' => 650,
                'y' => 250,
            ],
            'IL' => [
                'parent_id' => 'IL',
                'x' => 600,
                'y' => 250,
            ],
            'WI' => [
                'parent_id' => 'WI',
                'x' => 575,
                'y' => 155,
            ],
            'NC' => [
                'parent_id' => 'NC',
                'x' => 784,
                'y' => 326,
            ],
            'TN' => [
                'parent_id' => 'TN',
                'x' => 655,
                'y' => 340,
            ],
            'AR' => [
                'parent_id' => 'AR',
                'x' => 548,
                'y' => 368,
            ],
            'MO' => [
                'parent_id' => 'MO',
                'x' => 548,
                'y' => 293,
            ],
            'GA' => [
                'parent_id' => 'GA',
                'x' => 718,
                'y' => 405,
            ],
            'SC' => [
                'parent_id' => 'SC',
                'x' => 760,
                'y' => 371,
            ],
            'KY' => [
                'parent_id' => 'KY',
                'x' => 680,
                'y' => 300,
            ],
            'AL' => [
                'parent_id' => 'AL',
                'x' => 655,
                'y' => 405,
            ],
            'LA' => [
                'parent_id' => 'LA',
                'x' => 550,
                'y' => 435,
            ],
            'MS' => [
                'parent_id' => 'MS',
                'x' => 600,
                'y' => 405,
            ],
            'IA' => [
                'parent_id' => 'IA',
                'x' => 525,
                'y' => 210,
            ],
            'MN' => [
                'parent_id' => 'MN',
                'x' => 506,
                'y' => 124,
            ],
            'OK' => [
                'parent_id' => 'OK',
                'x' => 460,
                'y' => 360,
            ],
            'TX' => [
                'parent_id' => 'TX',
                'x' => 425,
                'y' => 435,
            ],
            'NM' => [
                'parent_id' => 'NM',
                'x' => 305,
                'y' => 365,
            ],
            'KS' => [
                'parent_id' => 'KS',
                'x' => 445,
                'y' => 290,
            ],
            'NE' => [
                'parent_id' => 'NE',
                'x' => 420,
                'y' => 225,
            ],
            'SD' => [
                'parent_id' => 'SD',
                'x' => 413,
                'y' => 160,
            ],
            'ND' => [
                'parent_id' => 'ND',
                'x' => 416,
                'y' => 96,
            ],
            'WY' => [
                'parent_id' => 'WY',
                'x' => 300,
                'y' => 180,
            ],
            'MT' => [
                'parent_id' => 'MT',
                'x' => 280,
                'y' => 95,
            ],
            'CO' => [
                'parent_id' => 'CO',
                'x' => 320,
                'y' => 275,
            ],
            'UT' => [
                'parent_id' => 'UT',
                'x' => 223,
                'y' => 260,
            ],
            'AZ' => [
                'parent_id' => 'AZ',
                'x' => 205,
                'y' => 360,
            ],
            'NV' => [
                'parent_id' => 'NV',
                'x' => 140,
                'y' => 235,
            ],
            'OR' => [
                'parent_id' => 'OR',
                'x' => 100,
                'y' => 120,
            ],
            'WA' => [
                'parent_id' => 'WA',
                'x' => 130,
                'y' => 55,
            ],
            'ID' => [
                'parent_id' => 'ID',
                'x' => 200,
                'y' => 150,
            ],
            'CA' => [
                'parent_id' => 'CA',
                'x' => 79,
                'y' => 285,
            ],
            'MI' => [
                'parent_id' => 'MI',
                'x' => 663,
                'y' => 185,
            ],
            'PR' => [
                'parent_id' => 'PR',
                'x' => 620,
                'y' => 545,
            ],
            'GU' => [
                'parent_id' => 'GU',
                'x' => 550,
                'y' => 540,
            ],
            'VI' => [
                'parent_id' => 'VI',
                'x' => 680,
                'y' => 519,
            ],
            'MP' => [
                'parent_id' => 'MP',
                'x' => 570,
                'y' => 575,
            ],
            'AS' => [
                'parent_id' => 'AS',
                'x' => 665,
                'y' => 580,
            ],
        ];

        $config           = AH_Licensed_States_Manager::get_config();
        $visual           = $config['visual'] ?? [];
        $per_state_visual = $config['per_state_visual'] ?? [];

        foreach ($labels as $state => &$data) {

            // 1. Eliminar colores heredados del mapa original (necesario siempre)
            unset($data['color']);
            unset($data['hover_color']);

            // 2. Obtener status del estado (available, unavailable, in_progress)
            $status_slug = AH_Licensed_States_Manager::get_state_status($state);

            $vis       = $visual[$status_slug] ?? [];
            $override  = $per_state_visual[$state] ?? [];

            // 3. COLOR NORMAL DEL TEXTO
            if (!empty($override['label_color'])) {
                $data['color'] = $override['label_color'];
            } elseif (!empty($vis['label_color'])) {
                $data['color'] = $vis['label_color'];
            }

            // 4. COLOR HOVER DEL TEXTO
            if (!empty($override['label_hover_color'])) {
                $data['hover_color'] = $override['label_hover_color'];
            } elseif (!empty($vis['label_hover_color'])) {
                $data['hover_color'] = $vis['label_hover_color'];
            }
        }
        unset($data);

        return $labels;

    }

    /**
     * Build legend entries based on statuses & visual config.
     *
     * @param array $statuses
     * @param array $visual
     * @return array
     */
    protected static function build_legend( array $statuses, array $visual ) {
        $entries = [];

        foreach ( $statuses as $slug => $data ) {
            $entries[] = [
                'name'  => isset( $data['label'] ) ? $data['label'] : ucfirst( $slug ),
                'color' => isset( $visual[ $slug ]['state_color'] ) ? $visual[ $slug ]['state_color'] : '#cccccc',
            ];
        }

        return [
            'entries' => $entries,
        ];
    }

    /**
     * Get uploads path for mapdata.js.
     *
     * @return string
     */
    protected static function get_map_file_path() {
        $upload_dir = wp_upload_dir();

        $dir = trailingslashit( $upload_dir['basedir'] ) . self::MAP_DIR_NAME . '/';

        if ( ! wp_mkdir_p( $dir ) ) {
            return '';
        }

        return $dir . self::MAP_FILE_NAME;
    }

    /**
     * Write the JS file contents to uploads.
     *
     * @param string $contents
     * @return void
     */
    protected static function write_mapdata_file( $contents ) {
        $path = self::get_map_file_path();

        if ( ! $path ) {
            return;
        }

        file_put_contents( $path, $contents );
    }

    /**
     * Public helper to get the URL of the generated mapdata.js.
     *
     * @return string
     */
    public static function get_mapdata_url() {
        $upload_dir = wp_upload_dir();

        return trailingslashit( $upload_dir['baseurl'] ) . self::MAP_DIR_NAME . '/' . self::MAP_FILE_NAME;
    }
}
