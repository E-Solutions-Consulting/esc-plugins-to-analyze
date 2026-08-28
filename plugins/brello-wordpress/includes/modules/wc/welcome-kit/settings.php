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
        update_option( 'ah_welcome_kit_product_id', intval( $_POST['ah_welcome_kit_product_id'] ) );
        update_option( 'ah_welcome_kit_default_status', sanitize_text_field( $_POST['ah_welcome_kit_default_status'] ) );

        $valid_schedules = [ 'immediate', '1_day', '2_day', '1_week', '2_week' ];
        $schedule        = sanitize_text_field( $_POST['ah_welcome_kit_schedule'] ?? 'immediate' );
        update_option( 'ah_welcome_kit_schedule', in_array( $schedule, $valid_schedules, true ) ? $schedule : 'immediate' );

        update_option(
            'ah_welcome_kit_allowed_categories',
            isset( $_POST['ah_welcome_kit_allowed_categories'] )
                ? array_map( 'sanitize_text_field', $_POST['ah_welcome_kit_allowed_categories'] )
                : []
        );

        update_option(
            'ah_welcome_kit_trigger_product_ids',
            isset( $_POST['ah_welcome_kit_trigger_product_ids'] )
                ? array_map( 'intval', $_POST['ah_welcome_kit_trigger_product_ids'] )
                : []
        );

        echo '<div class="updated"><p>Settings saved.</p></div>';
    }

    $enabled             = get_option( 'ah_welcome_kit_enabled', 'no' );
    $kit_product_id      = get_option( 'ah_welcome_kit_product_id', '' );
    $default_status      = get_option( 'ah_welcome_kit_default_status', KIT_READY_TO_SEND );
    $schedule            = get_option( 'ah_welcome_kit_schedule', 'immediate' );
    $allowed_categories  = get_option( 'ah_welcome_kit_allowed_categories', [] );
    $trigger_product_ids = get_option( 'ah_welcome_kit_trigger_product_ids', [] );

    $all_products   = wc_get_products( [ 'limit' => -1, 'status' => [ 'publish', 'private' ] ] );
    $all_categories = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false ] );
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
                            <th scope="row">Welcome Kit Product</th>
                            <td>
                                <select name="ah_welcome_kit_product_id">
                                    <option value="">-- Select Product --</option>
                                    <?php foreach ( $all_products as $product ) : ?>
                                        <option value="<?php echo $product->get_id(); ?>" <?php selected( $kit_product_id, $product->get_id() ); ?>>
                                            <?php echo esc_html( $product->get_name() ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">The product that will be added to the kit order.</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">Trigger: Allowed Categories</th>
                            <td>
                                <?php foreach ( $all_categories as $cat ) : ?>
                                    <label>
                                        <input type="checkbox"
                                            name="ah_welcome_kit_allowed_categories[]"
                                            value="<?php echo esc_attr( $cat->slug ); ?>"
                                            <?php checked( in_array( $cat->slug, $allowed_categories, true ) ); ?>>
                                        <?php echo esc_html( $cat->name ); ?>
                                    </label><br>
                                <?php endforeach; ?>
                                <p class="description">A kit will be created if the order contains a product from any of these categories.</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">Trigger: Specific Products</th>
                            <td>
                                <?php foreach ( $all_products as $product ) : ?>
                                    <label>
                                        <input type="checkbox"
                                            name="ah_welcome_kit_trigger_product_ids[]"
                                            value="<?php echo $product->get_id(); ?>"
                                            <?php checked( in_array( $product->get_id(), $trigger_product_ids, true ) ); ?>>
                                        <?php echo esc_html( $product->get_name() ); ?>
                                    </label><br>
                                <?php endforeach; ?>
                                <p class="description">A kit will be created if the order contains any of these specific products. Either this or a category match is enough (OR logic).</p>
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
