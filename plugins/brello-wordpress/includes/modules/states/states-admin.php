<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AH_States_Admin_Page {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'register_menu' ], 5 );
        add_action( 'admin_post_ah_save_states', [ __CLASS__, 'handle_save' ] );
    }

    /**
     * Add admin menu item under Settings or WooCommerce (you choose).
     */
    public static function register_menu() {
        
        add_submenu_page(
			PARENT_MENU_SLUG,
			'Licensed States',
			'Licensed States',
			'manage_options',
			PARENT_MENU_SLUG . '--licensed-states',
			[__CLASS__, 'render_page'],
            'dashicons-admin-site',
		);
    }

    /**
     * Render admin page.
     */
    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $config     = AH_Licensed_States_Manager::get_config();
        $all_states = AH_States::get_all();
        $statuses   = $config['statuses'];
        $visual     = $config['visual'];

        ?>
        <div class="wrap">
            <h1>Licensed States Configuration</h1>

            <?php if ( isset( $_GET['updated'] ) ): ?>
                <div class="notice notice-success"><p>Settings saved.</p></div>
            <?php endif; ?>

            <form action="<?php echo admin_url( 'admin-post.php' ); ?>" method="POST">
                <?php wp_nonce_field( 'ah_save_states' ); ?>
                <input type="hidden" name="action" value="ah_save_states">

                <h2>1. Status Labels & Descriptions</h2>
                <p>You may edit how each status is displayed in tooltips and descriptions.</p>

                <div class="status-labels">
                    <ul>
                        
                <?php foreach ( $statuses as $slug => $data ): ?>
                    <li>
                    <h3><?php echo esc_html( ucfirst( $slug ) ); ?></h3>
                        <p>
                            <label><strong>Label:</strong></label><br>
                            <input type="text" name="statuses[<?php echo $slug; ?>][label]" 
                                value="<?php echo esc_attr( $data['label'] ); ?>" 
                                class="regular-text">
                        </p>

                        <p>
                            <label><strong>Description:</strong></label><br>
                            <input type="text" name="statuses[<?php echo $slug; ?>][description]" 
                                value="<?php echo esc_attr( $data['description'] ); ?>" 
                                class="regular-text">
                        </p>    
                    </li>
                <?php endforeach; ?>
                        
                    </ul>
                </div>

                <hr>

                <h2>2. Visual Config (Map Colors)</h2>

                <div class="status-labels">
                    <ul>

                <?php foreach ( $visual as $slug => $colors ): ?>
                    <li>
                    <h3><?php echo esc_html( ucfirst( $slug ) ); ?></h3>

                    <table class="form-table">
                        <tr>
                            <th>State Color</th>
                            <td><input type="color" name="visual[<?php echo $slug; ?>][state_color]" value="<?php echo esc_attr( $colors['state_color'] ); ?>"></td>
                        </tr>
                        <tr>
                            <th>Label Color</th>
                            <td><input type="color" name="visual[<?php echo $slug; ?>][label_color]" value="<?php echo esc_attr( $colors['label_color'] ); ?>"></td>
                        </tr>
                        <tr>
                            <th>Hover Color</th>
                            <td><input type="color" name="visual[<?php echo $slug; ?>][state_hover_color]" value="<?php echo esc_attr( $colors['state_hover_color'] ); ?>"></td>
                        </tr>
                        <tr>
                            <th>Label Hover Color</th>
                            <td><input type="color" name="visual[<?php echo $slug; ?>][label_hover_color]" value="<?php echo esc_attr( $colors['label_hover_color'] ); ?>"></td>
                        </tr>
                    </table>
                    </li>
                <?php endforeach; ?>
                        
                    </ul>
                </div>

                <hr>

                <h2>3. State Licenses</h2>
                <p>Select the status for each state.</p>

                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>State</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php 
                        $per_state_visual = $config['per_state_visual'] ?? [];

                        foreach ( $all_states as $code => $label ): 
                            $current_status = $config['states_statuses'][ $code ] ?? 'unavailable';
                            $override       = $per_state_visual[ $code ] ?? [];
                            $override_on    = ! empty( $override['override'] );
                    ?>
                        <tr>
                            <td><strong><?php echo esc_html( $label ); ?></strong> (<?php echo $code; ?>)</td>
                            <td>
                                <?php foreach ( $statuses as $slug => $data ): ?>
                                <label>
                                    <input type="radio" name="states_statuses[<?php echo $code; ?>]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $slug, $current_status ); ?>>
                                    <?php echo esc_html( $data['label'] ); ?>
                                </label>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <p><button type="submit" class="button button-primary">Save Settings</button></p>
            </form>
        </div>
        <style>
            .status-labels > ul {display: flex;gap: 2rem;}
            input[type="color"] {padding: 0;border: none;background: transparent;inline-size: 2rem;block-size: 2rem;cursor:pointer;}
        </style>
        <?php
    }

    /**
     * Save handler
     */
    public static function handle_save() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        check_admin_referer( 'ah_save_states' );

        $incoming = [
            'states_statuses' => $_POST['states_statuses'] ?? [],
            'statuses'        => $_POST['statuses'] ?? [],
            'visual'          => $_POST['visual'] ?? [],
        ];

        $saved = AH_Licensed_States_Manager::save_config( $incoming );

        $redirect = admin_url( 'admin.php?page=' . PARENT_MENU_SLUG . '--licensed-states&updated=1' );
        wp_redirect( $redirect );
        exit;
    }
}

if ( class_exists( 'AH_States_Admin_Page' ) ) {
    AH_States_Admin_Page::init();
}

