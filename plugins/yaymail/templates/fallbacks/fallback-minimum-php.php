<?php

defined( 'ABSPATH' ) || exit;

add_action(
    'admin_notices',
    function() {
        if ( current_user_can( 'activate_plugins' ) ) {
            ?>
<div class="notice notice-error is-dismissible">
    <p>
        <strong>
            <?php
             // translators: %1$s: current PHP version,
            printf( esc_html__( 'YayMail requires PHP 7.2.0 to work and does not support your current PHP version %1$s. Please contact your host and request a PHP upgrade to the latest one.', 'yaymail' ), esc_html( phpversion() ) )
            ?>
        </strong>
    </p>
</div>
            <?php
        }
    }
);
