<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Bh_Checkout_Validations {

    public function __construct() {

        /**
         * 1) Validación global:
         *    - 1 solo producto "weight-loss" por persona (email/phone)
         *    - Aplica a invitados y logueados
         *    - Se salta en flujos de renovación / resuscripción / switch
         */
        add_action( 'woocommerce_checkout_process', [ $this, 'restrict_one_product_per_email_or_phone' ], 10 );

        /**
         * 2) Validación para usuarios logueados:
         *    - 1 suscripción por categoría por usuario
         *    - Se salta en flujos de renovación / resuscripción / switch
         */
        add_action( 'woocommerce_checkout_process', [ $this, 'validate_logged_in_user_restrictions' ], 20 );

        /**
         * 3) Tus otras validaciones existentes (no las toco)
         */
        add_action('woocommerce_checkout_process', [ $this, 'restrict_po_boxes_in_checkout' ]);
        //add_action('woocommerce_after_checkout_validation', [ $this, 'render_po_box_inline_error' ], 10, 2);
        //add_filter('woocommerce_form_field', [ $this, 'add_po_box_error_class' ], 10, 4);

        add_action('woocommerce_before_checkout_process', [ $this, 'associate_existing_customer_checkout' ], 20);

        add_action('woocommerce_checkout_process', [ $this, 'test_checkout_validations'], 50);
    }

    /**
     * Helper: detectar si el checkout actual corresponde a un flujo de suscripción
     * (renovación manual, resuscripción, switch, retry, etc.)
     */
    protected function is_subscription_flow() {

        if ( function_exists( 'wcs_cart_contains_renewal' ) && wcs_cart_contains_renewal() ) {
            return true;
        }

        if ( function_exists( 'wcs_cart_contains_resubscribe' ) && wcs_cart_contains_resubscribe() ) {
            return true;
        }

        if ( function_exists( 'wcs_cart_contains_subscription' ) && wcs_cart_contains_subscription() ) {
            return true;
        }

        return false;
    }

    /**
     * VALIDACIÓN 1:
     *  - Solo 1 compra "weight-loss" por persona (email / phone)
     *  - Aplica a invitados y logueados
     *  - NO se ejecuta en flujos WCS (renovación, resuscribe, switches)
     */
    public function restrict_one_product_per_email_or_phone() {

        // Saltar si es renovación / resuscribe / switch / retry
        if ( $this->is_subscription_flow() ) {
            return;
        }

        // 1) Verificar si en el carrito hay productos de la categoría 'weight-loss'
        $weight_loss_in_cart = false;

        foreach ( WC()->cart->get_cart() as $cart_item ) {
            $product    = $cart_item['data'];
            $product_id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();

            if ( has_term( 'weight-loss', 'product_cat', $product_id ) ) {
                $weight_loss_in_cart = true;
                break;
            }
        }

        if ( ! $weight_loss_in_cart ) {
            return; // No aplica esta validación
        }

        // 2) Obtener email y phone (checkout o meta del usuario)
        $billing_email = '';
        $billing_phone = '';

        if ( is_user_logged_in() ) {

            $user_id = get_current_user_id();

            $billing_email = ! empty( $_POST['billing_email'] )
                ? sanitize_email( wp_unslash( $_POST['billing_email'] ) )
                : get_user_meta( $user_id, 'billing_email', true );

            $billing_phone = ! empty( $_POST['billing_phone'] )
                ? sanitize_text_field( wp_unslash( $_POST['billing_phone'] ) )
                : get_user_meta( $user_id, 'billing_phone', true );

        } else {

            if ( empty( $_POST['billing_email'] ) ) {
                wc_add_notice( __( 'Please enter your email address.', 'woocommerce' ), 'error' );
                return;
            }

            if ( empty( $_POST['billing_phone'] ) ) {
                wc_add_notice( __( 'Please enter your phone number.', 'woocommerce' ), 'error' );
                return;
            }

            $billing_email = sanitize_email( wp_unslash( $_POST['billing_email'] ) );
            $billing_phone = sanitize_text_field( wp_unslash( $_POST['billing_phone'] ) );
        }

        // 3) Normalizar email
        $normalized_email = strtolower( trim( $billing_email ) );

        if ( ! is_email( $normalized_email ) ) {
            wc_add_notice( __( 'Please enter a valid email address.', 'woocommerce' ), 'error' );
            return;
        }

        // 4) Normalizar phone → E.164 digits (USA +1)
        $digits = preg_replace( '/[^0-9]/', '', $billing_phone );

        if ( strlen( $digits ) === 10 ) {
            $digits = '1' . $digits;
        }

        if ( strlen( $digits ) !== 11 ) {
            wc_add_notice( __( 'Please enter a valid phone number.', 'woocommerce' ), 'error' );
            return;
        }

        $normalized_phone = $digits; // ej: 11585588416

        // 5) Buscar órdenes previas (weight-loss) por email O phone
        global $wpdb;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    LOWER(a.email) AS email,
                    REGEXP_REPLACE(REGEXP_REPLACE(a.phone, '[^0-9]', ''), '^1?', '1') AS phone
                 FROM {$wpdb->prefix}wc_orders o
                 JOIN {$wpdb->prefix}wc_order_addresses a ON o.id = a.order_id
                 JOIN {$wpdb->prefix}wc_order_product_lookup opl ON o.id = opl.order_id
                 WHERE 
                    o.status IN ('wc-completed', 'wc-processing', 'wc-on-hold')
                    AND EXISTS (
                        SELECT 1 
                        FROM {$wpdb->prefix}term_relationships tr
                        JOIN {$wpdb->prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                        JOIN {$wpdb->prefix}terms t ON tt.term_id = t.term_id
                        WHERE 
                            (tr.object_id = opl.product_id 
                                OR tr.object_id = (
                                    SELECT post_parent 
                                    FROM {$wpdb->prefix}posts 
                                    WHERE ID = opl.variation_id LIMIT 1
                                ))
                            AND tt.taxonomy = 'product_cat'
                            AND t.slug = 'weight-loss'
                    )
                    AND (
                        LOWER(a.email) = %s
                        OR REGEXP_REPLACE(REGEXP_REPLACE(a.phone, '[^0-9]', ''), '^1?', '1') = %s
                    )",
                $normalized_email,
                $normalized_phone
            )
        );

        if ( empty( $results ) ) {
            return; // No hay match → permitir compra
        }

        // 6) Determinar qué coincidió: email, phone o ambos
        $email_match = false;
        $phone_match = false;

        foreach ( $results as $row ) {

            if ( ! empty( $row->email ) && strtolower( $row->email ) === $normalized_email ) {
                $email_match = true;
            }

            if ( ! empty( $row->phone ) && $row->phone === $normalized_phone ) {
                $phone_match = true;
            }
        }

        // 7) Mensajes según coincidencia
        if ( $email_match && $phone_match ) {

            wc_add_notice(
                __( 'You can only purchase weight-loss products once per person. An existing order was found using both your email and phone number.', 'woocommerce' ),
                'error'
            );

        } elseif ( $email_match ) {

            wc_add_notice(
                __( 'You can only purchase one weight-loss product per email address. An existing order was found using this email.', 'woocommerce' ),
                'error'
            );

        } elseif ( $phone_match ) {

            wc_add_notice(
                __( 'You can only purchase one weight-loss product per phone number. An existing order was found using this phone number.', 'woocommerce' ),
                'error'
            );
        }
    }

    /**
     * VALIDACIÓN 2:
     *  - Usuarios logueados
     *  - 1 suscripción por categoría (Weight loss, Supplements, etc.)
     *  - No se ejecuta en renovaciones / resuscribe / switches
     */
    public function validate_logged_in_user_restrictions() {

        if ( ! is_user_logged_in() ) {
            return;
        }

        // Saltar si es renovación / resuscribe / switch / retry
        if ( $this->is_subscription_flow() ) {
            return;
        }

        $user_id = get_current_user_id();

        // 1) Detectar categorías de productos de suscripción en el carrito
        $subscription_categories = [];

        foreach ( WC()->cart->get_cart() as $cart_item ) {

            $product = $cart_item['data'];

            if ( ! class_exists( 'WC_Subscriptions_Product' ) ) {
                continue;
            }

            if ( ! WC_Subscriptions_Product::is_subscription( $product ) ) {
                continue;
            }

            $product_id = $product->is_type( 'variation' )
                ? $product->get_parent_id()
                : $product->get_id();

            $terms = wp_get_post_terms( $product_id, 'product_cat', [ 'fields' => 'slugs' ] );

            if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                foreach ( $terms as $slug ) {
                    $subscription_categories[] = $slug;
                }
            }
        }

        $subscription_categories = array_unique( $subscription_categories );

        if ( empty( $subscription_categories ) ) {
            return; // No hay productos de suscripción en el carrito
        }

        global $wpdb;

        // 2) Por cada categoría, verificar si el usuario ya tiene una suscripción activa
        foreach ( $subscription_categories as $cat_slug ) {

            $existing = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT 1
                     FROM {$wpdb->prefix}wc_orders o
                     JOIN {$wpdb->prefix}wc_order_product_lookup opl ON o.id = opl.order_id
                     JOIN {$wpdb->prefix}term_relationships tr ON tr.object_id = opl.product_id
                     JOIN {$wpdb->prefix}term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
                     JOIN {$wpdb->prefix}terms t ON t.term_id = tt.term_id
                     WHERE 
                        o.type = 'shop_subscription'
                        AND o.customer_id = %d
                        AND o.status IN ('wc-active', 'wc-pending', 'wc-on-hold')
                        AND tt.taxonomy = 'product_cat'
                        AND t.slug = %s
                     LIMIT 1",
                    $user_id,
                    $cat_slug
                )
            );

            if ( $existing ) {
                wc_add_notice(
                    sprintf(
                        /* translators: %s: product category slug */
                        __( 'You already have an active subscription in the "%s" category. Only one subscription per category is allowed.', 'woocommerce' ),
                        esc_html( $cat_slug )
                    ),
                    'error'
                );
                return;
            }
        }
    }

    /**
     * Placeholders: aquí usas tus implementaciones actuales.
     * No las toco, solo mantengo la firma para que el constructor funcione.
     */

	/**
	 * Restric PO Boxes in Address
	 */
    public function restrict_po_boxes_in_checkout() {
        $error_message = __(
            'Note: PO boxes cannot be used as a delivery address. Please provide a valid physical address.',
            'woocommerce'
        );

        // Campos a validar
        $address_fields = [
            'shipping_address_1',
            'shipping_address_2',
            /*'billing_address_1',
            'billing_address_2',*/
        ];

        // Regex extendido para TODAS las variantes de PO Box
        $po_box_regex = '/
            (
                (post\s*office\s*box) |
                (p\s*[\.\-\s]*o\s*[\.\-\s]*box) |
                (po[-\s]*box) |
                (pob\s*\d*) |
                (\bp\s*o\s*b\b) |
                (\bp\s*o\s*\d) |
                (\bpob\b)
            )
        /ix';

        foreach ($address_fields as $key) {

            if ( empty($_POST[$key]) ) {
                continue;
            }

            // Normalizar espacios
            $value = trim( preg_replace('/\s+/', ' ', sanitize_text_field($_POST[$key])) );

            if ( preg_match($po_box_regex, $value) ) {
                wc_add_notice($error_message, 'error');
                return;
            }
        }
    }
    public function render_po_box_inline_error( $data, $errors ) {
    
        $field = WC()->session->get('po_box_error_field');
        $message = WC()->session->get('po_box_error_message');

        if (!$field || !$message) {
            return;
        }

        // Add inline error to the specific field
        $errors->add(
            $field . '_po_box_error',
            sprintf('<strong>%s</strong>', $message)
        );

        // Clean session so error doesn’t persist
        WC()->session->__unset('po_box_error_field');
        WC()->session->__unset('po_box_error_message');
    }
    public function add_po_box_error_class( $field_html, $key, $args, $value ) {
        $error_key = $key . '_po_box_error';
        if ( wc_get_notices('error') ) {
            foreach (wc_get_notices('error') as $notice) {

                if (!empty($notice['notice']) && strpos($notice['notice'], $error_key) !== false) {

                    // Insert error class
                    $field_html = preg_replace(
                        '/(<p class=".*?form-row)/',
                        '$1 woocommerce-invalid',
                        $field_html,
                        1
                    );
                }
            }
        }

        return $field_html;
    }

	/*
	*	set Session If email is registered
	*/
    public function associate_existing_customer_checkout() {
	    if (is_user_logged_in()) return;// Si ya está logueado, no hacer nada
	    
	    $billing_email = isset($_POST['billing_email']) ? sanitize_email($_POST['billing_email']) : '';
	    if (empty($billing_email)) return;

	    $customer = get_user_by('email', $billing_email);
	    if ($customer) {
	        wp_clear_auth_cookie();
	        wp_set_current_user($customer->ID);
	        wp_set_auth_cookie($customer->ID);
	        
	        WC()->session->set('customer_id', $customer->ID);
	        WC()->customer->set_props(array(
	            'billing_email' => $billing_email,
	            'billing_first_name' => isset($_POST['billing_first_name']) ? $_POST['billing_first_name'] : '',
	            'billing_last_name' => isset($_POST['billing_last_name']) ? $_POST['billing_last_name'] : '',
	            'billing_phone' => isset($_POST['billing_phone']) ? $_POST['billing_phone'] : '',
	        ));
	        WC()->customer->save();
	    }
	}

    public function test_checkout_validations() {
    	wc_add_notice(__('🎯 Testing Checkout Validations', 'woocommerce'), 'error');
    }
}
new Bh_Checkout_Validations();
?>