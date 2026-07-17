<?php

use YayMail\Emails\NewOrder;

defined( 'ABSPATH' ) || exit;

$template = NewOrder::get_instance()->template;

if ( ! empty( $template ) ) {
    $content = $template->get_content( $args ); // TODO: process args later.
    yaymail_kses_post_e( $content );
}
