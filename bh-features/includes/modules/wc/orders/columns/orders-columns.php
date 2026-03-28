<?php
/**
 * AH Orders Columns – Screen Options Integration
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'AH_Orders_Columns' ) ) :

class AH_Orders_Columns {

    const OPTIONAL_COLUMNS = [
        'telegra_id' => 'Telegra Order ID',
        'telegra_age' => '⏱ Auth Will Expire',
        
    ];

    const SCREEN_ID = 'woocommerce_page_wc-orders';

    const WARN_DAYS = 5;
    const CRIT_DAYS = 7;

    const EXPIRE_DAYS = 7;

    public function __construct() {

        add_filter(
            'woocommerce_shop_order_list_table_columns',
            [ $this, 'register_columns' ]
        );

        add_action(
            'woocommerce_shop_order_list_table_custom_column',
            [ $this, 'render_column_content' ],
            10,
            2
        );

        add_filter(
            'default_hidden_columns',
            [ $this, 'set_default_hidden' ],
            10,
            2
        );
        add_action( 'admin_head', [ $this, 'column_styles' ] );

    }

    public function register_columns( array $columns ): array {
        $new = [];
        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;
            if ( 'order_status' === $key ) {
                foreach ( self::OPTIONAL_COLUMNS as $col_key => $col_label ) {
                    $new[ $col_key ] = $col_label;
                }
            }
        }
        return $new;
    }

    public function render_column_content( string $column, \WC_Order $order ): void {
        switch ( $column ) {
            case 'telegra_id':
                $this->render_telegra_id( $order );
                break;
            case 'telegra_age':
                $this->render_telegra_age( $order );
                break;
        }
    }

    private function render_telegra_id( \WC_Order $order ): void {
        $order_id           = $order->get_id();
        $telemdnow_entity_id= $order->get_meta( 'telemdnow_entity_id', true );
        if(!$telemdnow_entity_id)
            return ;

        $url    =   TELEGRA_REST_URL . '/orders/' .$telemdnow_entity_id;
        
        echo '<a target="_blank" href="' . $url . '"><small>' . $telemdnow_entity_id . '</small></a>';
    }

    private function render_telegra_age__original( \WC_Order $order ): void {

        $created = $order->get_date_created();
        if ( ! $created ) {
            echo '<span class="telegra-age telegra-age--na">—</span>';
            return;
        }

        $is_parent  =   wcs_order_contains_parent( $order );
        if(!$is_parent || $order->get_status()!='send_to_telegra'){
            return ;
        }
 
        $days = (int) floor( ( time() - $created->getTimestamp() ) / DAY_IN_SECONDS );
 
        if ( $days >= self::CRIT_DAYS ) {
            $cls = 'telegra-age--crit';
        } elseif ( $days >= self::WARN_DAYS ) {
            $cls = 'telegra-age--warn';
        } else {
            $cls = 'telegra-age--ok';
        }

        if(!$days)
            return ;
 
        $label = $days === 1 ? '1 day' : "{$days} days";
        $title = 'Created: ' . $created->date( 'Y-m-d H:i' );
 
        echo "<span class='telegra-age {$cls}' title='" . esc_attr( $title ) . "'>{$label}</span>";
    }
    
    private function render_telegra_age( \WC_Order $order ): void {

        if ( $order->get_status() !== 'send_to_telegra' ) return;
        if ( ! wcs_order_contains_parent( $order ) ) return;

        $created = $order->get_date_created();
        if ( ! $created ) return;

        $elapsed  = (int) floor( ( time() - $created->getTimestamp() ) / DAY_IN_SECONDS );
        $remaining = self::EXPIRE_DAYS - $elapsed;
        if ( $elapsed === 0 ) return;

        if ( $remaining <= 0 ) {
            echo '<span class="telegra-age telegra-age--expired" title="Auth expired">Expired</span>';
            return;
        }

        if ( $remaining === 1 ) {
            $cls = 'telegra-age--crit';
        } elseif ( $remaining <= 2 ) {
            $cls = 'telegra-age--warn';
        } else {
            $cls = 'telegra-age--ok';
        }

        $title = sprintf(
            'Created: %s — %d day%s elapsed',
            $created->date( 'Y-m-d H:i' ),
            $elapsed,
            $elapsed === 1 ? '' : 's'
        );

        $label = $remaining === 1 ? '1 day' : "{$remaining} days";

        echo "<span class='telegra-age {$cls}' title='" . esc_attr( $title ) . "'>{$label}</span>";
    }

    public function set_default_hidden( array $hidden, \WP_Screen $screen ): array {
        if ( self::SCREEN_ID === $screen->id ) {
            foreach ( array_keys( self::OPTIONAL_COLUMNS ) as $col_key ) {
                if ( ! in_array( $col_key, $hidden, true ) ) {
                    $hidden[] = $col_key;
                }
            }
        }
        return $hidden;
    }

    function truncated_order_key( string $key ): string {
        return sprintf(
            '<span class="bh-order-id" data-start="%s" data-end="%s">%s</span>',
            esc_attr( substr( $key, 0, 7 ) ),   // "order::"
            esc_attr( substr( $key, -5 ) ),      // últimos 5 chars
            esc_html( $key )                     // texto real en el DOM
        );
    }

    public function column_styles(): void {
        if ( ! $this->is_orders_screen() ) {
            return;
        }
        ?>
        <style>
        /* ---- Columna NoSync ---- */
        .column-order_nosync  { width: 90px; text-align: center; }
        .bh-col-nosync        { display:inline-block; padding:2px 6px; border-radius:4px; font-size:11px; font-weight:600; }
        .bh-col-nosync--pending { background:#fff3cd; color:#856404; }
        .bh-col-nosync--ok      { background:#d1e7dd; color:#0a3622; }

        /* ---- Columna Questionnaire ---- */
        .column-order_questionnaire { width: 110px; }
        .bh-col-q           { display:inline-block; font-size:12px; font-weight:600; }
        .bh-col-q--done     { color:#0a3622; }
        .bh-col-q--pending  { color:#856404; }
        .bh-col-q--empty    { color:#999; }
        .bh-col-q__list     { margin:2px 0 0; padding:0; list-style:none; font-size:10px; color:#555; display:none; }
        /* Expande al hacer hover en la fila */
        tr:hover .bh-col-q__list { display:block; }

         .column-telegra_age { width: 90px; text-align: center; }
 
        .telegra-age {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
        }
        .telegra-age--na   { color: #999; font-weight: 400; }
        .telegra-age--ok   { /*background: #edf7ed;*/ color: #1e6e28; }
        .telegra-age--warn { /*background: #fff8e5;*/ color: #ff4601; }
        .telegra-age--crit { /*background: #fde8e8;*/ color: #b91c1c;
                             /*animation: telegra-pulse 2s ease-in-out infinite;*/ }
        .telegra-age--expired {
            background: #fef2f2;
            color: #e24b4b;
            text-decoration: line-through;
            opacity: 0.8;
        }
 
        /* @keyframes telegra-pulse {
            0%, 100% { background: #fceeee; }
            50%       { background: #ffc1c1; }
        } */

        /* El span tiene font-size: 0 para ocultar el texto real,
        pero sigue siendo seleccionable y copiable */
        .bh-order-id {
            font-size: 0;
            font-family: monospace;
            user-select: text;
        }
        .bh-order-id::before {
            content: attr(data-start);
            font-size: 13px;
            color: inherit;
        }
        .bh-order-id::after {
            content: "…" attr(data-end);
            font-size: 13px;
            color: #888;
        }
        </style>
        <?php
    }

    private function is_orders_screen(): bool {
        $screen = get_current_screen();
        return $screen && self::SCREEN_ID === $screen->id;
    }

    private function is_column_visible( string $column_key ): bool {
        $screen  = get_current_screen();
        if ( ! $screen ) {
            return false;
        }
        $hidden = get_user_option( 'manage' . $screen->id . 'columnshidden' );
        if ( ! is_array( $hidden ) ) {

            $hidden = get_hidden_columns( $screen );
        }
        return ! in_array( $column_key, $hidden, true );
    }
}

endif;
add_action('woocommerce_loaded', function() {
    new AH_Orders_Columns();
});