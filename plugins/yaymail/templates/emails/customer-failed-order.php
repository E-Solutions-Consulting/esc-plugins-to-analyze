<?php

use YayMail\Emails\CustomerFailedOrder;

defined( 'ABSPATH' ) || exit;

$template = CustomerFailedOrder::get_instance()->template;

if ( ! empty( $template ) ) {
    $content = $template->get_content( $args );
    // TODO: process args later.
    yaymail_kses_post_e( $content );
}
