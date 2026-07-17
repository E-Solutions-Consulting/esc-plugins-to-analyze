<?php

defined( 'ABSPATH' ) || exit;

global $wp_version;

add_action(
    'admin_notices',
    function() {
        if ( current_user_can( 'activate_plugins' ) ) {
            ?>
<div class="notice notice-error is-dismissible">
    <p>
        <strong>
            <?php
             // translators: %1$s: current WordPress version,
            printf( esc_html__( 'YayMail requires WordPress 5.2.0 to work and does not support your current WordPress version %1$s.', 'yaymail' ), isset( $wp_version ) ? esc_html( $wp_version ) : '' )
            ?>
        </strong>
    </p>
</div>
            <?php
        }
    }
);
