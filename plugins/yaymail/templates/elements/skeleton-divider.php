<?php
defined( 'ABSPATH' ) || exit;
use YayMail\Utils\TemplateHelpers;

/**
 * $args includes
 * $element
 * $render_data
 * $is_nested
 */
if ( empty( $args['element'] ) ) {
    return;
}

$element = $args['element'];

// Inline styles survive WooCommerce style_inline (Emogrifier) in email preview.
$container_style   = 'background-color:#fff;padding:2.5rem 1rem;box-sizing:border-box;';
$skeleton_style    = 'display:table;width:100%;';
$skeleton_mb_style = $skeleton_style . 'margin-bottom:10px;';
$content_style     = 'display:table-cell;width:100%;vertical-align:top;';
$list_style        = 'padding:0;margin:3px;list-style:none;';
$bar_li_base_style = 'height:15px;list-style:none;background:#f2f2f2;border-radius:100px;display:block;margin:0;padding:0;';
$image_li_style    = 'width:100%;height:300px;list-style:none;background:#f2f2f2;border-radius:10px;display:block;margin:0;padding:0;';

ob_start();
?>
<div class="yaymail-skeleton-divider" style="<?php echo esc_attr( $container_style ); ?>">
    <div class="yaymail-skeleton yaymail-skeleton-round" style="<?php echo esc_attr( $skeleton_mb_style ); ?>">
        <div class="yaymail-skeleton-content" style="<?php echo esc_attr( $content_style ); ?>">
            <ul class="yaymail-skeleton-paragraph" style="<?php echo esc_attr( $list_style ); ?>">
                <li style="<?php echo esc_attr( $bar_li_base_style . 'width:30%;' ); ?>"></li>
            </ul>
        </div>
    </div>
    <div class="yaymail-skeleton yaymail-skeleton-round yaymail-skeleton-divider__image" style="<?php echo esc_attr( $skeleton_mb_style ); ?>">
        <div class="yaymail-skeleton-content" style="<?php echo esc_attr( $content_style ); ?>">
            <ul class="yaymail-skeleton-paragraph" style="<?php echo esc_attr( $list_style ); ?>">
                <li style="<?php echo esc_attr( $image_li_style ); ?>"></li>
            </ul>
        </div>
    </div>
    <div class="yaymail-skeleton yaymail-skeleton-round" style="<?php echo esc_attr( $skeleton_style ); ?>">
        <div class="yaymail-skeleton-content" style="<?php echo esc_attr( $content_style ); ?>">
            <ul class="yaymail-skeleton-paragraph" style="<?php echo esc_attr( $list_style ); ?>">
                <li style="<?php echo esc_attr( $bar_li_base_style . 'width:70%;' ); ?>"></li>
            </ul>
        </div>
    </div>
    <div class="yaymail-skeleton yaymail-skeleton-round" style="<?php echo esc_attr( $skeleton_style ); ?>">
        <div class="yaymail-skeleton-content" style="<?php echo esc_attr( $content_style ); ?>">
            <ul class="yaymail-skeleton-paragraph" style="<?php echo esc_attr( $list_style ); ?>">
                <li style="<?php echo esc_attr( $bar_li_base_style . 'width:100%;' ); ?>"></li>
            </ul>
        </div>
    </div>
    <div class="yaymail-skeleton yaymail-skeleton-round" style="<?php echo esc_attr( $skeleton_style ); ?>">
        <div class="yaymail-skeleton-content" style="<?php echo esc_attr( $content_style ); ?>">
            <ul class="yaymail-skeleton-paragraph" style="<?php echo esc_attr( $list_style ); ?>">
                <li style="<?php echo esc_attr( $bar_li_base_style . 'width:100%;' ); ?>"></li>
            </ul>
        </div>
    </div>
</div>
<?php
$element_content = ob_get_clean();

TemplateHelpers::wrap_element_content( $element_content, $element );
