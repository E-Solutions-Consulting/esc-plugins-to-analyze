<div id="yaymail-upgrade-notice" class="notice-info notice is-dismissible">
    <h4 style="color: #000"><?php esc_html_e( 'Recommended: You can use YayMail Pro to integrate with:', 'yaymail' ); ?></h4>
    <ul style="list-style: inside;">
        <?php

        if ( ! empty( $pro_needed_plugins ) ) {

            foreach ( $pro_needed_plugins as $namespace => $third_party ) {
                ?>
                <li><?php echo esc_html( $third_party['plugin_name'] ); ?></li>
                <?php
            }
        }
        ?>
    </ul>
    <p style="padding-left:0">
        <a href="https://yaycommerce.com/yaymail-woocommerce-email-customizer/" target="_blank" data="upgradenow" class="button button-primary yaymail-upgrade-pro" style="margin-right: 5px"><?php esc_html_e( 'Upgrade to Pro', 'yaymail' ); ?></a>
        <a href="javascript:;" data="later" class="yaymail-dismiss-upgrade-notice" style="margin-right: 5px"><?php esc_html_e( 'No, thanks', 'yaymail' ); ?></a>
    </p>
</div>