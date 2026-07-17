<div id="yaymail-suggest-addons-notice" class="notice-info notice is-dismissible">
    <h4 style="color: #000"><?php esc_html_e( 'Recommended: You can use YayMail to customize all email templates of:', 'yaymail' ); ?></h4>
    <ul style="list-style: inside;">
        <?php
        if ( ! empty( $addon_needed_plugins ) ) {

            foreach ( $addon_needed_plugins as $namespace => $third_party ) {
                ?>
                <li><?php echo esc_html( sprintf( __( '%s (Addon)', 'yaymail' ), $third_party['plugin_name'] ) ); ?></li>
    
                <?php
            }
        }
        ?>
    </ul>
    <p style="padding-left:0">
        <a href="https://yaycommerce.com/yaymail-addons/" target="_blank" data="upgradenow" class="button button-primary yaymail-see-addons" style="margin-right: 5px"><?php esc_html_e( 'See Addons', 'yaymail' ); ?></a>
        <a href="javascript:;" data="later" class="yaymail-dismiss-suggest-addons-notice" style="margin-right: 5px"><?php esc_html_e( 'No, thanks', 'yaymail' ); ?></a>
    </p>
</div>