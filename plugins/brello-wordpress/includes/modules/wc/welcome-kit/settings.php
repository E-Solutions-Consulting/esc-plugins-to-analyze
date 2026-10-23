<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', function() {
    add_submenu_page(
        PARENT_MENU_SLUG,
        'Welcome Kit Settings',
        'Welcome Kit',
        'manage_options',
        PARENT_MENU_SLUG . '--welcome-kit-settings',
        'ah_render_welcome_kit_settings_page'
    );
} );

function ah_render_welcome_kit_settings_page(): void {
    if ( isset( $_POST['ah_welcome_kit_save'] ) ) {
        check_admin_referer( 'ah_welcome_kit_settings_nonce' );

        update_option( 'ah_welcome_kit_enabled', isset( $_POST['ah_welcome_kit_enabled'] ) ? 'yes' : 'no' );
        $status_options   = AH_Welcome_Kit::status_options();
        $schedule_options = AH_Welcome_Kit::schedule_options();

        $default_status = sanitize_text_field( $_POST['ah_welcome_kit_default_status'] ?? KIT_READY_TO_SEND );
        update_option( 'ah_welcome_kit_default_status', isset( $status_options[ $default_status ] ) ? $default_status : KIT_READY_TO_SEND );

        $schedule = sanitize_text_field( $_POST['ah_welcome_kit_schedule'] ?? 'immediate' );
        update_option( 'ah_welcome_kit_schedule', isset( $schedule_options[ $schedule ] ) ? $schedule : 'immediate' );

        // Product/variation -> gift map. A row only makes it into the map if
        // its checkbox was checked AND a gift product was picked for it. Its
        // status/schedule are optional overrides ('' = use the global default).
        $enabled_ids = isset( $_POST['ah_welcome_kit_map_enabled'] )
            ? array_map( 'intval', (array) $_POST['ah_welcome_kit_map_enabled'] )
            : [];
        $gift_raw     = (array) ( $_POST['ah_welcome_kit_map_gift'] ?? [] );
        $status_raw   = (array) ( $_POST['ah_welcome_kit_map_status'] ?? [] );
        $schedule_raw = (array) ( $_POST['ah_welcome_kit_map_schedule'] ?? [] );

        $product_map = [];
        foreach ( $enabled_ids as $trigger_id ) {
            $gift_id = isset( $gift_raw[ $trigger_id ] ) ? intval( $gift_raw[ $trigger_id ] ) : 0;
            if ( $gift_id <= 0 ) continue;

            $row_status   = sanitize_text_field( $status_raw[ $trigger_id ] ?? '' );
            $row_schedule = sanitize_text_field( $schedule_raw[ $trigger_id ] ?? '' );

            $product_map[ $trigger_id ] = [
                'gift'     => $gift_id,
                'status'   => isset( $status_options[ $row_status ] ) ? $row_status : '',
                'schedule' => isset( $schedule_options[ $row_schedule ] ) ? $row_schedule : '',
            ];
        }
        update_option( 'ah_welcome_kit_product_map', $product_map );

        echo '<div class="updated"><p>Settings saved.</p></div>';
    }

    $enabled          = get_option( 'ah_welcome_kit_enabled', 'no' );
    $default_status   = AH_Welcome_Kit::get_default_status();
    $schedule         = AH_Welcome_Kit::get_default_schedule();
    $product_map      = AH_Welcome_Kit_Eligibility::get_product_map();
    $status_options   = AH_Welcome_Kit::status_options();
    $schedule_options = AH_Welcome_Kit::schedule_options();

    $all_products = wc_get_products( [ 'limit' => -1, 'status' => [ 'publish', 'private' ] ] );

    // Flat pool of gift options: simple products, plus every variation of
    // variable products (a variable product itself can't be gifted, same
    // constraint as before).
    $gift_options = [];
    foreach ( $all_products as $product ) {
        if ( $product->is_type( 'variable' ) ) {
            foreach ( $product->get_children() as $variation_id ) {
                $variation = wc_get_product( $variation_id );
                if ( ! $variation ) continue;
                $gift_options[ $variation_id ] = $product->get_name() . ' — ' . $variation->get_name() . " (#{$variation_id})";
            }
        } else {
            $gift_options[ $product->get_id() ] = $product->get_name() . " (#{$product->get_id()})";
        }
    }

    // Picking a gift product auto-checks its "Send Kit" box, so a row never
    // silently drops on save just because the checkbox was forgotten.
    $render_gift_select = function( string $name, $selected_id, string $checkbox_id ) use ( $gift_options ) {
        $onchange = sprintf(
            'if(this.value){var c=document.getElementById(%s);if(c&&!c.checked){c.checked=true;c.dispatchEvent(new Event("change"));}}',
            wp_json_encode( $checkbox_id )
        );
        printf(
            '<select name="%s" onchange="%s">',
            esc_attr( $name ),
            esc_attr( $onchange )
        );
        echo '<option value="">-- Select Gift Product --</option>';
        foreach ( $gift_options as $id => $label ) {
            printf(
                '<option value="%d" %s>%s</option>',
                $id,
                selected( $selected_id, $id, false ),
                esc_html( $label )
            );
        }
        echo '</select>';
    };

    // Per-row status/schedule overrides. The first option ('') keeps the
    // global default, and its label shows what that default currently is.
    $render_override_selects = function( int $trigger_id ) use ( $product_map, $status_options, $schedule_options, $default_status, $schedule ) {
        $row = $product_map[ $trigger_id ] ?? [ 'status' => '', 'schedule' => '' ];

        echo '<td class="ah-wk-status">';
        printf( '<select name="%s">', esc_attr( "ah_welcome_kit_map_status[{$trigger_id}]" ) );
        printf( '<option value="">%s</option>', esc_html( 'Default (' . $status_options[ $default_status ] . ')' ) );
        foreach ( $status_options as $value => $label ) {
            printf( '<option value="%s" %s>%s</option>', esc_attr( $value ), selected( $row['status'], $value, false ), esc_html( $label ) );
        }
        echo '</select></td>';

        echo '<td class="ah-wk-delay">';
        printf( '<select name="%s">', esc_attr( "ah_welcome_kit_map_schedule[{$trigger_id}]" ) );
        printf( '<option value="">%s</option>', esc_html( 'Default (' . $schedule_options[ $schedule ]['label'] . ')' ) );
        foreach ( $schedule_options as $value => $option ) {
            printf( '<option value="%s" %s>%s</option>', esc_attr( $value ), selected( $row['schedule'], $value, false ), esc_html( $option['label'] ) );
        }
        echo '</select></td>';
    };
    ?>
    <div class="wrap">
        <h1>Welcome Kit Settings</h1>
        <div class="postbox">
            <div class="inside">
                <form method="post">
                    <?php wp_nonce_field( 'ah_welcome_kit_settings_nonce' ); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Enable Welcome Kit</th>
                            <td>
                                <label class="switch">
                                    <input type="checkbox" name="ah_welcome_kit_enabled" <?php checked( $enabled, 'yes' ); ?>>
                                    <span class="slider round"></span>
                                </label>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">Default Status</th>
                            <td>
                                <select name="ah_welcome_kit_default_status">
                                    <?php foreach ( $status_options as $value => $label ) : ?>
                                        <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $default_status, $value ); ?>><?php echo esc_html( $label ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Used by every gift whose Status is set to "Default".</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">Default Send Schedule</th>
                            <td>
                                <select name="ah_welcome_kit_schedule">
                                    <option value="immediate" <?php selected( $schedule, 'immediate' ); ?>>Immediately (on order completed)</option>
                                    <option value="1_day" <?php selected( $schedule, '1_day' ); ?>>1 day after order completed</option>
                                    <option value="2_day" <?php selected( $schedule, '2_day' ); ?>>2 days after order completed</option>
                                    <option value="1_week" <?php selected( $schedule, '1_week' ); ?>>1 week after order completed</option>
                                    <option value="2_week" <?php selected( $schedule, '2_week' ); ?>>2 weeks after order completed</option>
                                </select>
                                <p class="description">Used by every gift whose Delay is set to "Default". When the delay is something other than "Immediately", the kit order is created automatically once it has passed, via a scheduled background task.</p>
                            </td>
                        </tr>

                    </table>

                    <div class="ah-wk-section">
                        <div class="ah-wk-section__header">
                            <h2 class="ah-wk-section__title">Products &amp; Gift Assignment</h2>
                            <span class="ah-wk-section__count"><?php echo esc_html( sprintf( '%d configured', count( $product_map ) ) ); ?></span>
                        </div>
                        <p class="description">
                            Check a product (or one of its variations) to trigger a welcome kit for it, and pick which product gets sent as the gift.
                            Checking the parent product applies to <strong>all</strong> its variations; checking an individual variation overrides that for just that variation.
                            Status and Delay are optional per gift: leave them on "Default" to use the defaults above. Gifts with a different status or delay are sent as separate kit orders.
                        </p>
                        <table class="widefat welcome-kit">
                            <thead>
                                <tr>
                                    <th class="ah-wk-check">Send Kit</th>
                                    <th class="ah-wk-name">Product / Variation</th>
                                    <th class="ah-wk-gift">Gift Product</th>
                                    <th class="ah-wk-status">Status</th>
                                    <th class="ah-wk-delay">Delay</th>
                                </tr>
                            </thead>
                        <tbody>
                        <?php foreach ( $all_products as $product ) : ?>
                            <?php if ( $product->is_type( 'variable' ) ) : ?>
                                <?php $parent_id = $product->get_id(); $parent_active = isset( $product_map[ $parent_id ] ); ?>
                                <tr class="ah-wk-row ah-wk-row--parent<?php echo $parent_active ? ' ah-wk-row--active' : ''; ?>">
                                    <td class="ah-wk-check">
                                        <input type="checkbox"
                                            id="ah-wk-check-<?php echo $parent_id; ?>"
                                            name="ah_welcome_kit_map_enabled[]"
                                            value="<?php echo $parent_id; ?>"
                                            <?php checked( $parent_active ); ?>>
                                    </td>
                                    <td class="ah-wk-name"><span class="ah-wk-id">#<?php echo $parent_id; ?></span><?php echo esc_html( $product->get_name() ); ?> <span class="ah-wk-tag">All variations</span></td>
                                    <td class="ah-wk-gift"><?php $render_gift_select( "ah_welcome_kit_map_gift[{$parent_id}]", $product_map[ $parent_id ]['gift'] ?? '', "ah-wk-check-{$parent_id}" ); ?></td>
                                    <?php $render_override_selects( $parent_id ); ?>
                                </tr>
                                <?php foreach ( $product->get_children() as $variation_id ) :
                                    $variation = wc_get_product( $variation_id );
                                    if ( ! $variation ) continue;
                                    $variation_active = isset( $product_map[ $variation_id ] );
                                ?>
                                    <tr class="ah-wk-row ah-wk-row--variation<?php echo $variation_active ? ' ah-wk-row--active' : ''; ?>">
                                        <td class="ah-wk-check">
                                            <input type="checkbox"
                                                id="ah-wk-check-<?php echo $variation_id; ?>"
                                                name="ah_welcome_kit_map_enabled[]"
                                                value="<?php echo $variation_id; ?>"
                                                <?php checked( $variation_active ); ?>>
                                        </td>
                                        <td class="ah-wk-name ah-wk-indent"><span class="ah-wk-branch" aria-hidden="true">&#8627;</span><span class="ah-wk-id">#<?php echo $variation_id; ?></span><?php echo esc_html( $variation->get_name() ); ?></td>
                                        <td class="ah-wk-gift"><?php $render_gift_select( "ah_welcome_kit_map_gift[{$variation_id}]", $product_map[ $variation_id ]['gift'] ?? '', "ah-wk-check-{$variation_id}" ); ?></td>
                                        <?php $render_override_selects( $variation_id ); ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <?php $product_id = $product->get_id(); $simple_active = isset( $product_map[ $product_id ] ); ?>
                                <tr class="ah-wk-row ah-wk-row--simple<?php echo $simple_active ? ' ah-wk-row--active' : ''; ?>">
                                    <td class="ah-wk-check">
                                        <input type="checkbox"
                                            id="ah-wk-check-<?php echo $product_id; ?>"
                                            name="ah_welcome_kit_map_enabled[]"
                                            value="<?php echo $product_id; ?>"
                                            <?php checked( $simple_active ); ?>>
                                    </td>
                                    <td class="ah-wk-name"><span class="ah-wk-id">#<?php echo $product_id; ?></span><?php echo esc_html( $product->get_name() ); ?></td>
                                    <td class="ah-wk-gift"><?php $render_gift_select( "ah_welcome_kit_map_gift[{$product_id}]", $product_map[ $product_id ]['gift'] ?? '', "ah-wk-check-{$product_id}" ); ?></td>
                                    <?php $render_override_selects( $product_id ); ?>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        </tbody>
                        </table>
                        <script>
                            document.querySelectorAll( 'table.welcome-kit input[name="ah_welcome_kit_map_enabled[]"]' ).forEach( function ( checkbox ) {
                                var sync = function () { checkbox.closest( 'tr' ).classList.toggle( 'ah-wk-row--active', checkbox.checked ); };
                                checkbox.addEventListener( 'change', sync );
                            } );
                        </script>
                        <p class="description">Only the checked rows trigger the kit — sibling variations (e.g. a different duration) do not, even though they share the same parent product.</p>
                    </div>

                    <?php submit_button( 'Save Settings', 'primary', 'ah_welcome_kit_save' ); ?>
                </form>
            </div>
        </div>
    </div>
    <?php
}
