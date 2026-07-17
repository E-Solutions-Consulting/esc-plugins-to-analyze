<?php
defined( 'ABSPATH' ) || exit;
?>
<style>
    #wpcontent #wpbody .notice,
    .error, .updated {
        display: none;
    }
    #wpfooter {
        display: none;
    }
</style>
<div style="display: none;">
    <?php
    wp_editor(
        '',
        'yaymail-wp-editor-placeholder',
        [
            'quicktags'     => false,
            'media_buttons' => true,
            'tinymce'       => true,
        ]
    );
    ?>
    </div>
<div id="yaymail-main-pages">
    <div class="yaymail-pre-loading" style="width: 20px; height: 20px; background: url(images/spinner-2x.gif); background-size: contain; background-repeat: no-repeat; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);">
    </div>
</div>

