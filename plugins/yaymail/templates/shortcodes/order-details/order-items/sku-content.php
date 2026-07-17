<?php

defined( 'ABSPATH' ) || exit;

?>
    <div class="yaymail-product-sku">
<?php
    echo wp_kses_post( ' (#' . $sku . ')' );
?>
    </div>
<?php
