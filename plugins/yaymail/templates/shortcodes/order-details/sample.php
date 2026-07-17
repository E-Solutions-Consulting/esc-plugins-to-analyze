<?php
defined( 'ABSPATH' ) || exit;

use YayMail\Shortcodes\OrderDetails\OrderDetailsRenderer;

$is_placeholder = isset( $args['is_placeholder'] ) ? $args['is_placeholder'] : false;

$element_data = isset( $args['element']['data'] ) ? $args['element']['data'] : [];

$order_details = new OrderDetailsRenderer( null, $element_data, $is_placeholder );

$order_details->render();
