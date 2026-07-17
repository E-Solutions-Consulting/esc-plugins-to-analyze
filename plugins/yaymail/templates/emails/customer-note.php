<?php

use YayMail\Emails\CustomerNote;

defined( 'ABSPATH' ) || exit;

$template = CustomerNote::get_instance()->template;

if ( ! empty( $template ) ) {
    $content = $template->get_content( $args ); // TODO: process args later.
    yaymail_kses_post_e( $content );
}
