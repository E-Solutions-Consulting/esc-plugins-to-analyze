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
        update_option( 'ah_welcome_kit_default_status', sanitize_text_field( $_POST['ah_welcome_kit_default_status'] ) );

        $valid_schedules = [ 'immediate', '1_day', '2_day', '1_week', '2_week' ];
        $schedule        = sanitize_text_field( $_POST['ah_welcome_kit_schedule'] ?? 'immediate' );
        update_option( 'ah_welcome_kit_schedule', in_array( $schedule, $valid_schedules, true ) ? $schedule : 'immediate' );

        // Product/variation -> gift product map. A row only makes it into the
        // map if its checkbox was checked AND a gift product was picked for it.
        $enabled_ids = isset( $_POST['ah_welcome_kit_map_enabled'] )
            ? array_map( 'intval', (array) $_POST['ah_welcome_kit_map_enabled'] )
            : [];
        $gift_raw = (array) ( $_POST['ah_welcome_kit_map_gift'] ?? [] );

        $product_map = [];
        foreach ( $enabled_ids as $trigger_id ) {
            $gift_id = isset( $gift_raw[ $trigger_id ] ) ? intval( $gift_raw[ $trigger_id ] ) : 0;
            if ( $gift_id > 0 ) {
                $product_map[ $trigger_id ] = $gift_id;
            }
        }
        update_option( 'ah_welcome_kit_product_map', $product_map );

        echo '<div class="updated"><p>Settings saved.</p></div>';
    }

    $enabled        = get_option( 'ah_welcome_kit_enabled', 'no' );
    $default_status = get_option( 'ah_welcome_kit_default_status', KIT_READY_TO_SEND );
    $schedule       = get_option( 'ah_welcome_kit_schedule', 'immediate' );
    $product_map    = AH_Welcome_Kit_Eligibility::get_product_map();

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
            'if(this.value){var c=document.getElementById(%s);if(c)c.checked=true;}',
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
                            <th scope="row">Products &amp; Gift Assignment</th>
                            <td>
                                <p class="description" style="margin-top:0;">
                                    Check a product (or one of its variations) to trigger a welcome kit for it, and pick which product gets sent as the gift.
                                    Checking the parent product applies to <strong>all</strong> its variations; checking an individual variation overrides that for just that variation.
                                </p>
                                <table class="widefat striped welcome-kit" style="max-width:1200px;">
                                    <thead>
                                        <tr>
                                            <th style="width:2em;">Send Kit</th>
                                            <th>Product / Variation</th>
                                            <th>Gift Product</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ( $all_products as $product ) : ?>
                                        <?php if ( $product->is_type( 'variable' ) ) : ?>
                                            <?php $parent_id = $product->get_id(); $parent_active = isset( $product_map[ $parent_id ] ); ?>
                                            <tr class="ah-wk-row ah-wk-row--parent<?php echo $parent_active ? ' ah-wk-row--active' : ''; ?>">
                                                <td>
                                                    <input type="checkbox"
                                                        id="ah-wk-check-<?php echo $parent_id; ?>"
                                                        name="ah_welcome_kit_map_enabled[]"
                                                        value="<?php echo $parent_id; ?>"
                                                        <?php checked( $parent_active ); ?>>
                                                </td>
                                                <td class="ah-wk-name">#<?php echo $parent_id; ?> - <span><?php echo esc_html( $product->get_name() ); ?></span> <span class="description">(all variations)</span></td>
                                                <td><?php $render_gift_select( "ah_welcome_kit_map_gift[{$parent_id}]", $product_map[ $parent_id ] ?? '', "ah-wk-check-{$parent_id}" ); ?></td>
                                            </tr>
                                            <?php foreach ( $product->get_children() as $variation_id ) :
                                                $variation = wc_get_product( $variation_id );
                                                if ( ! $variation ) continue;
                                                $variation_active = isset( $product_map[ $variation_id ] );
                                            ?>
                                                <tr class="ah-wk-row ah-wk-row--variation<?php echo $variation_active ? ' ah-wk-row--active' : ''; ?>">
                                                    <td class="ah-wk-indent">
                                                        <input type="checkbox"
                                                            id="ah-wk-check-<?php echo $variation_id; ?>"
                                                            name="ah_welcome_kit_map_enabled[]"
                                                            value="<?php echo $variation_id; ?>"
                                                            <?php checked( $variation_active ); ?>>
                                                    </td>
                                                    <td class="ah-wk-name ah-wk-indent">&#8627; #<?php echo $variation_id; ?> - <?php echo esc_html( $variation->get_name() ); ?></td>
                                                    <td><?php $render_gift_select( "ah_welcome_kit_map_gift[{$variation_id}]", $product_map[ $variation_id ] ?? '', "ah-wk-check-{$variation_id}" ); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else : ?>
                                            <?php $product_id = $product->get_id(); $simple_active = isset( $product_map[ $product_id ] ); ?>
                                            <tr class="ah-wk-row ah-wk-row--simple<?php echo $simple_active ? ' ah-wk-row--active' : ''; ?>">
                                                <td>
                                                    <input type="checkbox"
                                                        id="ah-wk-check-<?php echo $product_id; ?>"
                                                        name="ah_welcome_kit_map_enabled[]"
                                                        value="<?php echo $product_id; ?>"
                                                        <?php checked( $simple_active ); ?>>
                                                </td>
                                                <td class="ah-wk-name">#<?php echo $product_id; ?> - <?php echo esc_html( $product->get_name() ); ?></td>
                                                <td><?php $render_gift_select( "ah_welcome_kit_map_gift[{$product_id}]", $product_map[ $product_id ] ?? '', "ah-wk-check-{$product_id}" ); ?></td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <p class="description">Only the checked rows trigger the kit — sibling variations (e.g. a different duration) do not, even though they share the same parent product.</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">Default Status (when kit price = 0)</th>
                            <td>
                                <select name="ah_welcome_kit_default_status">
                                    <option value="<?php echo esc_attr( KIT_READY_TO_SEND ); ?>" <?php selected( $default_status, KIT_READY_TO_SEND ); ?>>Kit Ready to Send</option>
                                    <option value="processing" <?php selected( $default_status, 'processing' ); ?>>Processing</option>
                                    <option value="completed" <?php selected( $default_status, 'completed' ); ?>>Completed</option>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">Send Schedule</th>
                            <td>
                                <select name="ah_welcome_kit_schedule">
                                    <option value="immediate" <?php selected( $schedule, 'immediate' ); ?>>Immediately (on order completed)</option>
                                    <option value="1_day" <?php selected( $schedule, '1_day' ); ?>>1 day after order completed</option>
                                    <option value="2_day" <?php selected( $schedule, '2_day' ); ?>>2 days after order completed</option>
                                    <option value="1_week" <?php selected( $schedule, '1_week' ); ?>>1 week after order completed</option>
                                    <option value="2_week" <?php selected( $schedule, '2_week' ); ?>>2 weeks after order completed</option>
                                </select>
                                <p class="description">When set to something other than "Immediately", the kit order is created automatically once the delay has passed, via a scheduled background task.</p>
                            </td>
                        </tr>

                    </table>
                    <?php submit_button( 'Save Settings', 'primary', 'ah_welcome_kit_save' ); ?>
                </form>
            </div>
        </div>
    </div>
    <?php
}
