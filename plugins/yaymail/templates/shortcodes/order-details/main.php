<?php
defined( 'ABSPATH' ) || exit;

use YayMail\Shortcodes\OrderDetails\OrderDetailsRenderer;

$is_placeholder = isset( $args['is_placeholder'] ) ? $args['is_placeholder'] : false;

$render_data = isset( $args['render_data'] ) ? $args['render_data'] : [];

$order_data = isset( $render_data['order'] ) ? $render_data['order'] : null;

$element_data = isset( $args['element']['data'] ) ? $args['element']['data'] : [];

$order_details = new OrderDetailsRenderer( $render_data['order'], $element_data, $is_placeholder );

$order_details->render();
