<?php
defined( 'ABSPATH' ) || exit;

/**
 * Hidden preheader row (inbox preview text), similar to WooCommerce block email editor.
 *
 * @var string $preheader Plain preheader text (from yaymail_get_content $args).
 */

$preheader = $args['preheader'] ?? '';

$preheader_style = 'font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;color:transparent;';
?>
<table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
    <tbody>
        <tr>
            <td class="yaymail-email-preheader" height="1" style="<?php echo esc_attr( $preheader_style ); ?>">
                <?php echo esc_html( $preheader ); ?>
            </td>
        </tr>
    </tbody>
</table>
