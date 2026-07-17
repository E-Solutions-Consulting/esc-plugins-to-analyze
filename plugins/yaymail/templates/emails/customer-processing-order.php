<?php

use YayMail\Emails\CustomerProcessingOrder;

defined( 'ABSPATH' ) || exit;

$template = CustomerProcessingOrder::get_instance()->template;

if ( ! empty( $template ) ) {
    $content = $template->get_content( $args ); // TODO: process args later.
    yaymail_kses_post_e( $content );
}
