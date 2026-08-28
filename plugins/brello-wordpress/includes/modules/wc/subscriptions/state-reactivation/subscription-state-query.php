<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AH_Subscription_State_Query {

    /**
     * Returns candidate subscription IDs (HPOS-safe).
     *
     * Filters:
     * - shop_subscription
     * - status: on-hold
     * - meta: _on_hold_facility_move = 1
     * - meta: _reactivated_after_state_change != 1 (or not set)
     *
     * Note: We do NOT filter cancelled_state_% here because LIKE meta_query is not supported efficiently.
     *
     * @param int $limit
     * @param int $offset
     * @return int[]
     */
    public function get_candidate_ids( $limit = 50, $offset = 0 ) {

        $limit  = max( 1, intval( $limit ) );
        $offset = max( 0, intval( $offset ) );

        $args = [
            'type'   => 'shop_subscription',
            'status' => [ 'on-hold' ],
            'limit'  => $limit,
            'offset' => $offset,
            'orderby'=> 'ID',
            'order'  => 'DESC',
            'return' => 'ids',
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key'     => '_on_hold_facility_move',
                    'value'   => '1',
                    'compare' => '=',
                ],
                [
                    'relation' => 'OR',
                    [
                        'key'     => '_reactivated_after_state_change',
                        'compare' => 'NOT EXISTS',
                    ],
                    [
                        'key'     => '_reactivated_after_state_change',
                        'value'   => '1',
                        'compare' => '!=',
                    ],
                ],
            ],
        ];
        $ids = wc_get_orders( $args );
        global $wpdb;
        _print($wpdb);
        // $query = new WC_Order_Query( $args );
	    // _print($query);
        // die('test2');

        return is_array( $ids ) ? array_map( 'intval', $ids ) : [];
    }

    public function get_candidate_ids_by_state( $state, $limit = 50, $offset = 0 ) {

        $state  = strtoupper( trim( $state ) );
        $limit  = max( 1, intval( $limit ) );
        $offset = max( 0, intval( $offset ) );

        $args = [
            'type'   => 'shop_subscription',
            'status' => [ 'on-hold' ],
            'limit'  => $limit,
            'offset' => $offset,
            'orderby'=> 'ID',
            'order'  => 'DESC',
            'return' => 'ids',

            'meta_query' => [
                'relation' => 'AND',
                [
                    'key'     => '_cancelled_state_' . $state,
                    'value'   => '1',
                    'compare' => '=',
                ],
                [
                    'relation' => 'OR',
                    [
                        'key'     => '_reactivated_after_state_change',
                        'compare' => 'NOT EXISTS',
                    ],
                    [
                        'key'     => '_reactivated_after_state_change',
                        'value'   => '1',
                        'compare' => '!=',
                    ],
                ],
            ],
        ];

        $ids = wc_get_orders( $args );
        // global $wpdb;_print($wpdb);
        // return wc_get_orders( $args );
        return is_array( $ids ) ? array_map( 'intval', $ids ) : [];
    }

}
