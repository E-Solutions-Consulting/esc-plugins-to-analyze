<?php

defined( 'ABSPATH' ) || exit;

?>
<div class="yaymail-product-short-description">
    <?php
        echo wp_kses_post( "(#{$short_description})" );
    ?>
    </div>
<?php
