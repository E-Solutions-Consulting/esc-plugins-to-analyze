<?php

function section_text() {
    echo '<p>Set your <b>Attentive domain</b> below using the value provided to you by your Client Strategy representative.</p>';
    echo '<p>Your Attentive domain can also be found after clicking "install" on the WooCommerce integration from the <a href="https://ui.attentivemobile.com/integrations">Attentive Marketplace.</a></p>';
    echo '<p>For assistance retrieving this value, please reach out to whiteglove@attentivemobile.com</p>';
}

function attentive_tag_plugin_domain() {
    $options = get_option( 'attentive_tag_plugin_settings' );
    echo "<input id='attentive_tag_plugin_domain' name='attentive_tag_plugin_settings[attentive_domain]' type='text' value='" . esc_attr( $options['attentive_domain'] ) . "' />";
}

function attentive_tag_plugin_settings_validate( $input ) {
    return $input;
}

function register_attentive_tag_settings() {
    register_setting( 'attentive_tag_plugin_settings', 'attentive_tag_plugin_settings', 'attentive_tag_plugin_settings_validate' );
    add_settings_section( 'api_settings', 'Attentive Tag Settings', 'section_text', 'attentive_tag_plugin' );

    add_settings_field( 'attentive_tag_plugin_domain', 'Attentive Domain', 'attentive_tag_plugin_domain', 'attentive_tag_plugin', 'api_settings' );
}
add_action( 'admin_init', 'register_attentive_tag_settings' );

function attentive_tag_settings_render_page() {
    ?>
    <form action="options.php" method="post">
        <?php 
        settings_fields( 'attentive_tag_plugin_settings' );
        do_settings_sections( 'attentive_tag_plugin' );?>
        <input name="submit" class="button button-primary" type="submit" value="<?php esc_attr_e( 'Save' );?>" />
    </form>
    <?php
}

function attentive_tag_settings() {
    add_options_page( 'Attentive Tag plugin page', 'Attentive Tag Plugin Menu', 'manage_options', 'attentive-tag-plugin', 'attentive_tag_settings_render_page' );
}

add_action( 'admin_menu', 'attentive_tag_settings' );