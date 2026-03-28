<?php
/**
 * Phone Standardization Module
 *
 * Visual format: (XXX) XXX-XXXX
 * Saved format (billing & shipping): +1XXXXXXXXXX (E.164)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class AH_Checkout_Phone_Standardization {

    public function __construct() {

        // Enqueue masking script
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_phone_mask_script' ] );

        // Validate phone at checkout
        add_action( 'woocommerce_checkout_process', [ $this, 'validate_phone_format' ] );

        // Normalize phone before saving to order
        add_filter( 'woocommerce_checkout_fields', [ $this, 'normalize_checkout_phone_fields' ] );

        // Save normalized phone to user (optional)
        add_action( 'woocommerce_checkout_update_user_meta', [ $this, 'save_normalized_phone_to_user' ], 10, 2 );

        // Always save normalized phone to order meta
        add_action( 'woocommerce_checkout_update_order_meta', [ $this, 'save_normalized_phone_to_order' ], 10, 2 );


        add_action( 'woocommerce_checkout_create_order', [$this, 'normalize_phone_field'], 10, 2 );

        // Admin order edit screen enhancements
        add_action( 'admin_enqueue_scripts', [ $this, 'load_script_backend'] );
        add_action( 'woocommerce_process_shop_order_meta', [$this,  'save_admin_process'], 999, 2);

        // User profile phone formatting
        add_action( 'admin_enqueue_scripts', [$this, 'add_script_edit_profile'] );
        add_action( 'personal_options_update', [$this, 'ah_normalize_user_phone'] );
        add_action( 'edit_user_profile_update', [$this, 'ah_normalize_user_phone'] );
    }


    /**
     * Enqueue JS mask for phone visual formatting
     */
    public function enqueue_phone_mask_script() {
        if ( ! is_checkout() ) return;

        wp_enqueue_script( 'bh-phone-mask', plugin_dir_url( __FILE__ ) . 'assets/js/bh-phone-mask.js', array( 'jquery' ), '1.0.0', true );

    }


    /**
     * JS mask for phone field using pure JS (no external library)
     */
    private function get_mask_script__() {
        return "
            jQuery(function($){
                function formatPhone(value) {
                    value = value.replace(/\\D/g, '').substring(0, 10);
                    var area = value.substring(0,3);
                    var mid = value.substring(3,6);
                    var end = value.substring(6,10);
                    if(value.length > 6){
                        return '(' + area + ') ' + mid + '-' + end;
                    } else if(value.length > 3){
                        return '(' + area + ') ' + mid;
                    } else if(value.length > 0){
                        return '(' + area;
                    }
                    return '';
                }

                // Billing
                $('#billing_phone').on('input', function(){
                    this.value = formatPhone(this.value);
                });

                // Shipping (if enabled)
                $('#shipping_phone').on('input', function(){
                    this.value = formatPhone(this.value);
                });
            });
        ";
    }

    /**
     * Validate phone format: must be 10 digits (US format)
     */
    public function validate_phone_format() {
        if ( empty( $_POST['billing_phone'] ) ) {
            wc_add_notice( 'Please enter a valid phone number.', 'error' );
            return;
        }

        $raw = preg_replace( '/\D+/', '', sanitize_text_field( $_POST['billing_phone'] ) );

        if ( strlen( $raw ) !== 10 ) {
            wc_add_notice( 'Phone number must be 10 digits (e.g. (727) 326-3213).', 'error' );
        }
    }


    /**
     * Normalize phone into +1XXXXXXXXXX before saving
     */
    public function normalize_checkout_phone_fields( $fields ) {

        $normalize = function( $value ) {
            $digits = preg_replace( '/\D+/', '', $value );
            if ( strlen( $digits ) === 10 ) {
                return '+1' . $digits;
            }
            return $value; // fallback
        };

        if ( isset( $fields['billing']['billing_phone']['default'] ) ) {
            $fields['billing']['billing_phone']['default'] =
                $normalize( $fields['billing']['billing_phone']['default'] );
        }

        if ( isset( $fields['shipping']['shipping_phone']['default'] ) ) {
            $fields['shipping']['shipping_phone']['default'] =
                $normalize( $fields['shipping']['shipping_phone']['default'] );
        }

        return $fields;
    }


    /**
     * Save normalized phone to user meta
     */
    public function save_normalized_phone_to_user( $customer_id, $posted ) {
       if ( empty( $data['billing_phone'] ) ) return;

        $normalized = $this->normalize_to_e164_us( $data['billing_phone'] );
        if ( strpos($normalized, '+1') !== 0 ) return;

        update_user_meta( $customer_id, 'billing_phone', $normalized );
    }


    /**
     * Save normalized phone to order meta (HPOS compatible)
     */
    public function save_normalized_phone_to_order( $order_id, $posted_data ) {
        if ( ! isset( $posted_data['billing_phone'] ) ) return;

        $digits = preg_replace( '/\D+/', '', $posted_data['billing_phone'] );
        if ( strlen( $digits ) !== 10 ) return;

        $standard = '+1' . $digits;

        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        $order->update_meta_data( '_billing_phone_normalized', $standard );
        $order->update_meta_data( '_shipping_phone_normalized', $standard );
        $order->save();
    }


    /**
     * Replace order address phone fields with normalized E.164 format (HPOS compatible)
     */
    function normalize_phone_field( $order, $data ) {

        if ( empty( $data['billing_phone'] ) ) return;

        $normalized = $this->normalize_to_e164_us( $data['billing_phone'] );
        if ( strpos($normalized, '+1') === 0 ) {
            $order->set_billing_phone( $normalized );
        }

        if ( ! empty( $data['shipping_phone'] ) ) {
            $normalized_shipping = $this->normalize_to_e164_us( $data['shipping_phone'] );
            if ( strpos($normalized_shipping, '+1') === 0 ) {
                $order->set_shipping_phone( $normalized_shipping );
            }
        }

    }
    private function normalize_to_e164_us( $phone ) {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        // If 11 digits starting with 1, strip country code
        if ( strlen($digits) === 11 && substr($digits, 0, 1) === '1' ) {
            $digits = substr($digits, 1);
        }

        // Safety: if longer, keep last 10
        if ( strlen($digits) > 10 ) {
            $digits = substr($digits, -10);
        }

        if ( strlen($digits) !== 10 ) {
            return $phone; // fallback if invalid
        }

        return '+1' . $digits;
    }


    
    function load_script_backend( $hook ) {
        // _print( 'Hook: %s', esc_html( $hook ) );
        // _print( 'Post Type: %s', esc_html( get_post_type() ) );
        // Only load on order edit screen
        if ( $hook !== 'woocommerce_page_wc-orders' ) return;
        // if ( get_post_type() !== 'shop_order' ) return;

        wp_add_inline_script( 'jquery', "
            jQuery(function($){

                function digitsOnly(v) {
                    return (v || '').toString().replace(/\\D/g, '');
                }

                function normalizeTo10(v) {
                    var d = digitsOnly(v);

                    // E.164 +1XXXXXXXXXX → strip leading 1
                    if (d.length === 11 && d.charAt(0) === '1') {
                        d = d.substring(1);
                    }

                    // Hard limit: 10 digits real
                    d = d.substring(0, 10);

                    return d;
                }

                function formatUS(v){
                    var d = normalizeTo10(v);

                    var area = d.substring(0,3);
                    var mid  = d.substring(3,6);
                    var end  = d.substring(6,10);

                    if (d.length > 6) return '('+area+') '+mid+'-'+end;
                    if (d.length > 3) return '('+area+') '+mid;
                    if (d.length > 0) return '('+area;
                    return '';
                }

                function applyMask(selector){
                    var el = $(selector);
                    if (!el.length) return;

                    el.attr('placeholder', '(XXX) XXX-XXXX');
                    el.val( formatUS(el.val()) );

                    el.on('input', function(){
                        var raw = digitsOnly(this.value).substring(0,10);
                        this.value = formatUS(raw);
                    });

                    el.on('keypress', function(e){
                        var d = digitsOnly(this.value);
                        if (d.length >= 10 && /[0-9]/.test(e.key)) {
                            e.preventDefault();
                        }
                    });
                }

                // Primary fields WooCommerce uses
                applyMask('input[name=\"_billing_phone\"]');
                applyMask('input[name=\"_shipping_phone\"]');

                // Fallbacks if WooCommerce regenerates fields dynamically
                applyMask('#_billing_phone');
                applyMask('#_shipping_phone');

                // For order edit meta boxes that might use different markup
                applyMask('#order_data .billing-field input[type=\"text\"]');
                applyMask('#order_data .shipping-field input[type=\"text\"]');

            });
        " );
    }
    
    function save_admin_process( $order_id, $order ) {

        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        // Billing phone
        if ( isset($_POST['_billing_phone']) ) {

            $normalized = preg_replace('/\D+/', '', $_POST['_billing_phone']);

            // Remove leading 1 if 11 digits
            if ( strlen($normalized) === 11 && substr($normalized, 0, 1) === '1' ) {
                $normalized = substr($normalized, 1);
            }

            // Keep last 10 if somehow longer
            if ( strlen($normalized) > 10 ) {
                $normalized = substr($normalized, -10);
            }

            if ( strlen($normalized) === 10 ) {
                $order->set_billing_phone( '+1' . $normalized );
            }
        }

        // Shipping phone
        if ( isset($_POST['_shipping_phone']) ) {

            $normalized_s = preg_replace('/\D+/', '', $_POST['_shipping_phone']);

            if ( strlen($normalized_s) === 11 && substr($normalized_s, 0, 1) === '1' ) {
                $normalized_s = substr($normalized_s, 1);
            }

            if ( strlen($normalized_s) > 10 ) {
                $normalized_s = substr($normalized_s, -10);
            }

            if ( strlen($normalized_s) === 10 ) {
                $order->set_shipping_phone( '+1' . $normalized_s );
            }
        }

        // Save
        $order->save();

    }

    
    function add_script_edit_profile( $hook ) {

        if ( $hook !== 'user-edit.php' && $hook !== 'profile.php' ) {
            return;
        }

        wp_add_inline_script( 'jquery', "
            jQuery(function($){

                function digitsOnly(v){
                    return (v || '').toString().replace(/\\D/g,'');
                }

                function normalizeTo10(v){
                    var d = digitsOnly(v);

                    if (d.length === 11 && d.charAt(0)==='1'){
                        d = d.substring(1);
                    }

                    d = d.substring(0, 10);

                    return d;
                }

                function formatUS(v){
                    var d = normalizeTo10(v);

                    var area = d.substring(0,3);
                    var mid  = d.substring(3,6);
                    var end  = d.substring(6,10);

                    if (d.length > 6) return '('+area+') '+mid+'-'+end;
                    if (d.length > 3) return '('+area+') '+mid;
                    if (d.length > 0) return '('+area;
                    return '';
                }

                function applyMask(selector){
                    var el = $(selector);
                    if (!el.length) return;

                    el.attr('placeholder', '(XXX) XXX-XXXX');
                    el.val( formatUS(el.val()) );

                    el.on('input', function(){
                        var raw = digitsOnly(this.value).substring(0,10);
                        this.value = formatUS(raw);
                    });

                    el.on('keypress', function(e){
                        var d = digitsOnly(this.value);
                        if (d.length >= 10 && /[0-9]/.test(e.key)){
                            e.preventDefault();
                        }
                    });
                }

                // Primary WooCommerce fields
                applyMask('#billing_phone');
                applyMask('#shipping_phone');

                // Fallbacks (themes or plugins modify admin markup sometimes)
                applyMask('input[name=\"billing_phone\"]');
                applyMask('input[name=\"shipping_phone\"]');
            });
        ");
    }

    function ah_normalize_user_phone($user_id){

        if ( ! current_user_can('edit_user', $user_id) ) {
            return;
        }

        // Billing phone
        if ( isset($_POST['billing_phone']) ) {

            $digits = preg_replace('/\D+/', '', $_POST['billing_phone']);

            if ( strlen($digits) === 11 && substr($digits, 0, 1) === '1' ) {
                $digits = substr($digits, 1);
            }

            if ( strlen($digits) > 10 ) {
                $digits = substr($digits, -10);
            }

            if ( strlen($digits) === 10 ) {
                update_user_meta( $user_id, 'billing_phone', '+1' . $digits );
            }
        }

        // Shipping phone (optional)
        if ( isset($_POST['shipping_phone']) ) {

            $digits_s = preg_replace('/\D+/', '', $_POST['shipping_phone']);

            if ( strlen($digits_s) === 11 && substr($digits_s, 0, 1) === '1' ) {
                $digits_s = substr($digits_s, 1);
            }

            if ( strlen($digits_s) > 10 ) {
                $digits_s = substr($digits_s, -10);
            }

            if ( strlen($digits_s) === 10 ) {
                update_user_meta( $user_id, 'shipping_phone', '+1' . $digits_s );
            }
        }

    }





}

new AH_Checkout_Phone_Standardization();
