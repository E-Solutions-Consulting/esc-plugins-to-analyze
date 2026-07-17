<?php

use YayMail\Emails\CustomerInvoice;

defined( 'ABSPATH' ) || exit;

$template = CustomerInvoice::get_instance()->template;

if ( ! empty( $template ) ) {
    $content = $template->get_content( $args ); // TODO: process args later.
    yaymail_kses_post_e( $content );
}
