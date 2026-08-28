<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AH_States {

    /**
     * Static list of all US states + DC.
     * This list should be stable and NOT represent license availability.
     */
    protected static $states = [
        'AL' => 'Alabama',
        'AK' => 'Alaska',
        'AZ' => 'Arizona',
        'AR' => 'Arkansas',
        'CA' => 'California',
        'CO' => 'Colorado',
        'CT' => 'Connecticut',
        'DE' => 'Delaware',
        'DC' => 'District of Columbia',
        'FL' => 'Florida',
        'GA' => 'Georgia',
        'HI' => 'Hawaii',
        'ID' => 'Idaho',
        'IL' => 'Illinois',
        'IN' => 'Indiana',
        'IA' => 'Iowa',
        'KS' => 'Kansas',
        'KY' => 'Kentucky',
        'LA' => 'Louisiana',
        'ME' => 'Maine',
        'MD' => 'Maryland',
        'MA' => 'Massachusetts',
        'MI' => 'Michigan',
        'MN' => 'Minnesota',
        'MS' => 'Mississippi',
        'MO' => 'Missouri',
        'MT' => 'Montana',
        'NE' => 'Nebraska',
        'NV' => 'Nevada',
        'NH' => 'New Hampshire',
        'NJ' => 'New Jersey',
        'NM' => 'New Mexico',
        'NY' => 'New York',
        'NC' => 'North Carolina',
        'ND' => 'North Dakota',
        'OH' => 'Ohio',
        'OK' => 'Oklahoma',
        'OR' => 'Oregon',
        'PA' => 'Pennsylvania',
        'RI' => 'Rhode Island',
        'SC' => 'South Carolina',
        'SD' => 'South Dakota',
        'TN' => 'Tennessee',
        'TX' => 'Texas',
        'UT' => 'Utah',
        'VT' => 'Vermont',
        'VA' => 'Virginia',
        'WA' => 'Washington',
        'WV' => 'West Virginia',
        'WI' => 'Wisconsin',
        'WY' => 'Wyoming',
    ];

    /**
     * Return all US states as [ 'CA' => 'California', ... ].
     */
    public static function get_all() {
        return self::$states;
    }

    /**
     * Return only state codes (keys).
     */
    public static function get_codes() {
        return array_keys( self::$states );
    }

    /**
     * Get a state label by code or null if not found.
     */
    public static function get_name( $code ) {
        $code = strtoupper( trim( $code ) );

        return isset( self::$states[ $code ] ) ? self::$states[ $code ] : null;
    }

    /**
     * Backwards-compatible helper used in other modules:
     * Now delegates to AH_Licensed_States_Manager.
     */
    public static function is_allowed( $state ) {
        $state = strtoupper( trim( $state ) );

        if ( ! $state ) {
            return false;
        }

        return AH_Licensed_States_Manager::is_state_available( $state );
    }

    /**
     * Return the allowed states based on user role.
     *
     * Guests + customers -> all states EXCEPT 'unavailable'.
     * Admin + customer_service -> full list (no filter).
     *
     * @param array $states Original states array, usually coming from WC.
     * @return array        Filtered states array.
     */
    public static function get_states_for_current_user( $states, $onlyAvailable = false ) {

         // --------------------------------------------------
        // 0) Normalize structure
        // --------------------------------------------------
        if ( ! isset( $states['US'] ) ) {
            $states = [
                'US' => $states,
            ];
        }

        // --------------------------------------------------
        // 1) Privileged roles: they see everything unfiltered
        // --------------------------------------------------
        if ( is_user_logged_in() && ! $onlyAvailable ) {
            $user             = wp_get_current_user();
            $privileged_roles = array( 'administrator', 'customer_services' );

            if ( array_intersect( $privileged_roles, (array) $user->roles ) ) {
                return $states;
            }
        }

        // --------------------------------------------------
        // 2) Build a list excluding only 'unavailable'
        // --------------------------------------------------
        $filtered_states = array();
        foreach ( self::$states as $code => $label ) {
            $status = AH_Licensed_States_Manager::get_state_status( $code );
            if ( $status === 'unavailable' ) {
                continue;
            }
            $filtered_states[ $code ] = $label;
        }

        // --------------------------------------------------
        // 3) Security Fallback
        // --------------------------------------------------
        if ( empty( $filtered_states ) ) {
            return $states;
        }

        // --------------------------------------------------
        // 4) Replace only US
        // --------------------------------------------------
        $states['US'] = $filtered_states;

        return $states;
    }
}

if ( ! class_exists( 'AH_States' ) ) {
    new AH_States();
}
