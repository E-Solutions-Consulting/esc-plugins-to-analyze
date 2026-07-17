<?php

defined( 'ABSPATH' ) || exit;

?>
<div class='yaymail-product-image'>
    <?php
        empty( $item ) ? yaymail_kses_post_e( $image ) : yaymail_kses_post_e( apply_filters( 'woocommerce_order_item_thumbnail', $image, $item ) );
    ?>
    </div>
<?php
