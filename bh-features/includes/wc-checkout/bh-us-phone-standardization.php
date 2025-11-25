<?php
/**
 * Plugin Name: BH Features - US Phone Standardization
 * Description: Estandariza números de teléfono y móvil USA en WooCommerce para OTP
 * Version: 1.0.0
 * Author: BH Team
 */

// Evitar acceso directo
defined('ABSPATH') || exit;

class BH_US_Phone_Standardization {
    
    public function __construct() {
        // Scripts y estilos
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        
        // Campos del checkout
        add_filter('woocommerce_checkout_fields', array($this, 'modify_checkout_fields'));
        add_filter('woocommerce_billing_fields', array($this, 'modify_billing_fields'));
        
        // Validaciones - MÁS TEMPRANO en el proceso
        add_action('woocommerce_checkout_process', array($this, 'validate_phone_fields'));
        add_action('woocommerce_after_checkout_validation', array($this, 'validate_checkout_phones'), 10, 2);
        
        // Guardar campos - HPOS compatible con VALIDACIÓN EXTRA
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_custom_fields'), 5, 1);
        add_action('woocommerce_customer_save_address', array($this, 'save_customer_fields'), 10, 2);
        
        // Validación en admin - MÁS ESTRICTA
        // add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'add_mobile_fields_to_admin_order'), 10, 1);
        add_action('woocommerce_admin_order_data_after_order_details', array($this, 'add_mobile_fields_to_admin_order'), 999, 1);
        add_action('woocommerce_process_shop_order_meta', array($this, 'save_admin_order_fields'), 999, 2);
        
        // Validación EXTRA para asegurar datos limpios
        add_filter('woocommerce_process_checkout_field_billing_phone', array($this, 'validate_phone_before_checkout'), 10, 1);
        
        // AJAX para mostrar/ocultar móvil
        add_action('wp_ajax_bh_toggle_mobile_field', array($this, 'toggle_mobile_field'));
        add_action('wp_ajax_nopriv_bh_toggle_mobile_field', array($this, 'toggle_mobile_field'));

        add_action('wp_footer', array($this, 'enhance_frontend_validation'));

        add_filter('woocommerce_order_get_billing_phone', [$this, 'format_phone_customer_details'], 10, 2);
        add_filter('woocommerce_subscription_get_billing_phone', [$this, 'format_phone_customer_details'], 10, 2);
    }
    
    /**
     * Validación EXTRA antes del checkout
     */
    public function validate_phone_before_checkout($phone) {
        if (!empty($phone)) {
            $digits_only = preg_replace('/\D/', '', $phone);
            
            // Validación estricta - debe tener 10 dígitos y no ser todos ceros
            if (strlen($digits_only) !== 10 || $this->is_all_zeros($digits_only)) {
                wc_add_notice(__('Please enter a valid US phone number with 10 digits.', 'bh-features'), 'error');
                return ''; // Devolver vacío para forzar error
            }
        }
        return $phone;
    }
    
    /**
     * Verificar si todos los dígitos son cero
     */
    private function is_all_zeros($phone) {
        return preg_match('/^0+$/', $phone);
    }
    
    /**
     * Cargar scripts y estilos necesarios en frontend
     */
    public function enqueue_scripts() {
        if (is_checkout() || is_account_page() || is_wc_endpoint_url('edit-address')) {
            wp_enqueue_script('bh-us-phone-validation', BH_FEATURES_PLUGIN_URL . 'public/js/us-phone-validation.js', array('jquery'), '1.0.0', true);
            
            wp_localize_script('bh-us-phone-validation', 'bh_phone_params', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'invalid_phone' => __('Please enter a valid US phone number with 10 digits', 'bh-features'),
                'invalid_mobile' => __('Please enter a valid US mobile number with 10 digits. Format: +1 408-600-4784', 'bh-features'),
                'phone_placeholder' => __('(555) 123-4567', 'bh-features'),
                'mobile_placeholder' => __('+1 408-600-4784', 'bh-features'),
                'nonce' => wp_create_nonce('bh_phone_nonce')
            ));
            
            // CSS para el formato
            //wp_add_inline_style('wc-blocks-style-css', 
            echo '<style>
                .bh-phone-error, .bh-mobile-phone-error {
                    color: #e2401c;
                    font-size: 12px;
                    margin-top: 5px;
                    display: block;
                }
                .bh-phone-formatted, .bh-mobile-phone-formatted {
                    font-family: monospace;
                    letter-spacing: 1px;
                }
                .bh-mobile-field-wrapper {
                    transition: all 0.3s ease;
                    overflow: hidden;
                }
                .bh-mobile-field-wrapper.hidden {
                    display: none;
                }
                .bh-has-mobile-checkbox {
                    margin-bottom: 10px;
                }
                    </style>
            ';
        }
    }
    /**
     * Validación adicional para el frontend
     */
    public function enhance_frontend_validation() {
        if (is_checkout()) {
            ?>
            <script type="text/javascript">
                // Sobrescribir la validación de WooCommerce para teléfonos
                jQuery(document).ready(function($) {
                    // Interceptar la validación del checkout
                    var originalCheckoutValidation = null;
                    
                    if (typeof checkout !== 'undefined') {
                        originalCheckoutValidation = checkout.validate_field;
                        
                        checkout.validate_field = function(field) {
                            var result = originalCheckoutValidation.apply(this, arguments);
                            
                            // Validación adicional para teléfonos
                            if (field === 'billing_phone') {
                                var phoneValue = $('#billing_phone').val();
                                var digitsOnly = phoneValue.replace(/\D/g, '');
                                
                                if (phoneValue.length > 0 && (digitsOnly.length !== 10 || /^0+$/.test(digitsOnly))) {
                                    return false;
                                }
                            }
                            
                            if (field === 'billing_mobile_phone' && $('#bh_has_mobile_phone').is(':checked')) {
                                var mobileValue = $('#billing_mobile_phone').val();
                                var mobileDigits = mobileValue.replace(/\D/g, '');
                                
                                if (mobileValue.length > 3 && (mobileDigits.length !== 11 || /^0+$/.test(mobileDigits))) {
                                    return false;
                                }
                            }
                            
                            return result;
                        };
                    }
                });
            </script>
            <?php
        }
    }

    // Agregar este hook al constructor:

    /**
     * Cargar scripts y estilos en admin
     */
    public function admin_enqueue_scripts($hook) {

        //if ($hook === 'woocommerce_page_wc-orders') {
        if (stripos($hook, 'woocommerce_page_wc-orders') !== false) {
            
            wp_enqueue_script('bh-admin-phone-validation', BH_FEATURES_PLUGIN_URL . 'admin/js/admin-phone-validation.js', array('jquery'), '1.0.0', true);
            
            wp_localize_script('bh-admin-phone-validation', 'bh_phone_params', array(
                'invalid_phone' => __('Please enter a valid US phone number with 10 digits', 'bh-features'),
                'invalid_mobile' => __('Please enter a valid US mobile number with 10 digits. Format: +1 408-600-4784', 'bh-features'),
                'phone_placeholder' => __('(555) 123-4567', 'bh-features'),
                'mobile_placeholder' => __('+1 408-600-4784', 'bh-features')
            ));
            
            wp_add_inline_style('woocommerce-general', '
                .bh-phone-error, .bh-mobile-phone-error {
                    color: #e2401c;
                    font-size: 12px;
                    margin-top: 5px;
                    display: block;
                }
                .bh-phone-formatted, .bh-mobile-phone-formatted {
                    font-family: monospace;
                    letter-spacing: 1px;
                }
                .bh-admin-mobile-section {
                    margin-top: 15px;
                    padding-top: 15px;
                    border-top: 1px solid #ddd;
                }
                #order_data .edit_address .bh-phone-formatted {
                    width: 100%;
                }
                .bh-phone-incomplete {
                    border-color: #e2401c !important;
                    background-color: #fff8f8;
                }
                .bh-digit-count {
                    font-size: 11px;
                    color: #666;
                    margin-top: 3px;
                }
                .bh-digit-count.error {
                    color: #e2401c;
                    font-weight: bold;
                }
                .bh-phone-invalid {
                    border-color: #e2401c !important;
                    background-color: #fff8f8;
                }
            ');
        }
    }
    
    /**
     * Modificar campos del checkout
     */
    public function modify_checkout_fields($fields) {
        // Agregar checkbox para móvil
        $fields['billing']['bh_has_mobile_phone'] = array(
            'label' => __('Add mobile phone for app notifications?', 'bh-features'),
            'type' => 'checkbox',
            'class' => array('form-field form-field-wide', 'bh-has-mobile-checkbox'),
            'clear' => true,
            'priority' => 21
        );
        
        // Agregar campo móvil
        $fields['billing']['billing_mobile_phone'] = array(
            'label' => __('Mobile Phone', 'bh-features'),
            'placeholder' => __('+1 408-600-4784', 'bh-features'),
            'required' => false,
            'class' => array('form-row-wide', 'bh-mobile-field'),
            'clear' => true,
            'type' => 'tel',
            'priority' => 22,
            'custom_attributes' => array(
                'data-mobile-phone-us' => 'true'
            )
        );
        
        // Modificar campo teléfono regular
        if (isset($fields['billing']['billing_phone'])) {
            $fields['billing']['billing_phone']['class'] = array('form-row-wide');
            $fields['billing']['billing_phone']['placeholder'] = __('(555) 123-4567', 'bh-features');
            $fields['billing']['billing_phone']['custom_attributes']['data-phone-us'] = 'true';
            $fields['billing']['billing_phone']['priority'] = 20;
        }
        
        return $fields;
    }
    
    /**
     * Modificar campos de billing para otras páginas
     */
    public function modify_billing_fields($fields) {
        // Solo modificar en páginas de cuenta, no en checkout
        if (!is_checkout()) {
            if (isset($fields['billing_phone'])) {
                $fields['billing_phone']['class'] = array('form-row-wide');
                $fields['billing_phone']['placeholder'] = __('(555) 123-4567', 'bh-features');
                $fields['billing_phone']['custom_attributes']['data-phone-us'] = 'true';
            }
            
            // Agregar campo móvil en cuenta
            $fields['billing_mobile_phone'] = array(
                'label' => __('Mobile Phone', 'bh-features'),
                'placeholder' => __('+1 408-600-4784', 'bh-features'),
                'required' => false,
                'class' => array('form-row-wide'),
                'clear' => true,
                'type' => 'tel',
                'custom_attributes' => array(
                    'data-mobile-phone-us' => 'true'
                )
            );
            
            // Agregar checkbox en cuenta
            $fields['bh_has_mobile_phone'] = array(
                'label' => __('Add mobile phone for app notifications?', 'bh-features'),
                'type' => 'checkbox',
                'class' => array('form-row-wide'),
                'clear' => true
            );
        }
        
        return $fields;
    }
    
    /**
     * Agregar campos móviles al editar orden en admin
     */
    public function add_mobile_fields_to_admin_order($order) {
        if (!is_a($order, 'WC_Subscription')) {
            return ;
        }
        $has_mobile = $order->get_meta('_bh_has_mobile_phone');
        $mobile_phone = $order->get_meta('_billing_mobile_phone');
        ?>
        <div class="bh-admin-mobile-section form-field form-field-wide">
            <p><strong><?php _e('Mobile Phone Information', 'bh-features'); ?></strong></p>
            
            <!-- Checkbox para móvil -->
            <p class="form-field form-field-wide">
                <label for="bh_has_mobile_phone">
                    <input type="checkbox" name="bh_has_mobile_phone" id="bh_has_mobile_phone" value="1" <?php checked($has_mobile, 1); ?> />
                    <?php _e('SMS Subscribed?', 'bh-features'); ?>
                </label>
            </p>
            
            <!-- Campo móvil -->
            <p class="form-field form-field-wide">
                <label for="_billing_mobile_phone"><?php _e('Mobile Phone:', 'bh-features'); ?></label>
                <input type="tel" 
                       name="_billing_mobile_phone" 
                       id="_billing_mobile_phone" 
                       value="<?php echo $mobile_phone ? esc_attr($this->format_phone_to_display($mobile_phone, 'mobile')) : ''; ?>" 
                       placeholder="<?php _e('+1 408-600-4784', 'bh-features'); ?>" 
                       class="bh-mobile-phone-formatted" 
                       data-mobile-phone-us="true" />
            </p>
        </div>
        
        <!--<script type="text/javascript">
            jQuery(document).ready(function($) {
                // Toggle campo móvil en admin
                $('#bh_has_mobile_phone').on('change', function() {
                    if ($(this).is(':checked')) {
                        $('#_billing_mobile_phone').closest('p.form-field').show();
                    } else {
                        $('#_billing_mobile_phone').closest('p.form-field').hide();
                        $('#_billing_mobile_phone').val('');
                    }
                }).trigger('change');
                
                // Formatear teléfono existente cuando se carga la página
                var phoneField = $('#_billing_phone');
                if (phoneField.length && phoneField.val()) {
                    var formattedPhone = bhFormatPhoneToDisplay(phoneField.val(), 'phone');
                    phoneField.val(formattedPhone);
                    phoneField.addClass('bh-phone-formatted');
                    
                    // Validar si está completo
                    var digitsOnly = phoneField.val().replace(/\D/g, '');
                    if (digitsOnly.length !== 10 || bhIsAllZeros(digitsOnly)) {
                        phoneField.addClass('bh-phone-invalid');
                    }
                }
                
                // Agregar clase a los campos existentes
                $('#_billing_phone').addClass('bh-phone-formatted');
            });
            
            // Función para formatear teléfono para visualización
            function bhFormatPhoneToDisplay(phone, type) {
                var digitsOnly = phone.replace(/\D/g, '');
                
                // Remover el 1 inicial si existe
                if (digitsOnly.length === 11 && digitsOnly.substring(0, 1) === '1') {
                    digitsOnly = digitsOnly.substring(1);
                }
                
                if (digitsOnly.length === 10) {
                    if (type === 'mobile') {
                        return '+1 ' + digitsOnly.substring(0, 3) + '-' + digitsOnly.substring(3, 6) + '-' + digitsOnly.substring(6, 10);
                    } else {
                        return '(' + digitsOnly.substring(0, 3) + ') ' + digitsOnly.substring(3, 6) + '-' + digitsOnly.substring(6, 10);
                    }
                }
                
                return phone;
            }
            
            // Función para verificar si todos los dígitos son cero
            function bhIsAllZeros(phone) {
                return /^0+$/.test(phone);
            }
        </script>-->
        <style>
            .bh-admin-mobile-section input[type="checkbox"]{width:auto !important}
            #_billing_mobile_phone {
                width: 100%;
            }
        </style>
        <?php
    }
    
    /**
     * Guardar campos al editar orden en admin - VALIDACIÓN MÁS ESTRICTA
     */
    public function save_admin_order_fields($order_id, $order) {
        // Validar y formatear teléfono regular (campo nativo de WooCommerce)
        if (isset($_POST['_billing_phone'])) {
            $phone = sanitize_text_field($_POST['_billing_phone']);
            $digits_only = preg_replace('/\D/', '', $phone);
            
            // VERIFICACIÓN MÁS ESTRICTA - 10 dígitos Y no todos ceros
            if (strlen($digits_only) === 10 && !$this->is_all_zeros($digits_only)) {
                if ($this->is_valid_us_phone($phone)) {
                    $formatted_phone = $this->format_phone_to_e164($phone, 'phone');
                    $order->set_billing_phone($formatted_phone);
                } else {
                    WC_Admin_Meta_Boxes::add_error(__('Please enter a valid US phone number in the format (555) 123-4567.', 'bh-features'));
                    $this->revert_phone_change($order);
                }
            } else {
                // NO guardar si no tiene 10 dígitos exactos O son todos ceros
                if (strlen($digits_only) !== 10) {
                    WC_Admin_Meta_Boxes::add_error(
                        sprintf(__('Phone number must have exactly 10 digits. Current: %d digits. Number not saved.', 'bh-features'), strlen($digits_only))
                    );
                } else {
                    WC_Admin_Meta_Boxes::add_error(__('Phone number cannot be all zeros. Please enter a valid number.', 'bh-features'));
                }
                $this->revert_phone_change($order);
            }
        }
        
        if (is_a($order, 'WC_Subscription')) {
            // Guardar checkbox de móvil
            $has_mobile = isset($_POST['bh_has_mobile_phone']) ? 1 : 0;
            $order->update_meta_data('_bh_has_mobile_phone', $has_mobile);
            
            // Validar y guardar móvil
            if (isset($_POST['_billing_mobile_phone']) && !empty($_POST['_billing_mobile_phone'])) {
                $mobile_phone = sanitize_text_field($_POST['_billing_mobile_phone']);
                $mobile_digits = preg_replace('/\D/', '', $mobile_phone);
                
                if ($has_mobile) {
                    // VERIFICACIÓN MÁS ESTRICTA - 11 dígitos Y no todos ceros
                    if (strlen($mobile_digits) === 11 && !$this->is_all_zeros($mobile_digits)) {
                        if ($this->is_valid_us_mobile_phone($mobile_phone)) {
                            $formatted_mobile = $this->format_phone_to_e164($mobile_phone, 'mobile');
                            $order->update_meta_data('_billing_mobile_phone', $formatted_mobile);
                        } else {
                            WC_Admin_Meta_Boxes::add_error(__('Please enter a valid US mobile number in the format +1 408-600-4784.', 'bh-features'));
                            $order->delete_meta_data('_billing_mobile_phone');
                        }
                    } else {
                        if (strlen($mobile_digits) !== 11) {
                            WC_Admin_Meta_Boxes::add_error(
                                sprintf(__('Mobile number must have exactly 10 digits. Current: %d digits. Number not saved.', 'bh-features'), (strlen($mobile_digits) - 1))
                            );
                        } else {
                            WC_Admin_Meta_Boxes::add_error(__('Mobile number cannot be all zeros. Please enter a valid number.', 'bh-features'));
                        }
                        $order->delete_meta_data('_billing_mobile_phone');
                    }
                } else {
                    // Si el checkbox no está marcado, limpiar el campo
                    $order->delete_meta_data('_billing_mobile_phone');
                }
            } else {
                // Si no hay valor, limpiar el campo
                $order->delete_meta_data('_billing_mobile_phone');
            }
        }
        
        $order->save();
    }
    
    /**
     * Revertir cambio de teléfono si no es válido
     */
    private function revert_phone_change($order) {
        // No hacer nada - simplemente no actualizar el teléfono
        // El teléfono mantendrá su valor anterior
        return;
    }
    
    /**
     * Validar campos de teléfono durante checkout - MÁS ESTRICTO
     */
    public function validate_phone_fields() {
        // Validar teléfono regular (siempre requerido)
        if (isset($_POST['billing_phone'])) {
            $phone = sanitize_text_field($_POST['billing_phone']);
            $digits_only = preg_replace('/\D/', '', $phone);
            
            if (strlen($digits_only) !== 10) {
                wc_add_notice(
                    sprintf(__('Phone number must have exactly 10 digits. Current: %d digits.', 'bh-features'), strlen($digits_only)), 
                    'error'
                );
            } elseif ($this->is_all_zeros($digits_only)) {
                wc_add_notice(__('Phone number cannot be all zeros. Please enter a valid number.', 'bh-features'), 'error');
            } elseif (!$this->is_valid_us_phone($phone)) {
                wc_add_notice(__('Please enter a valid US phone number in the format (555) 123-4567.', 'bh-features'), 'error');
            }
        }
        
        // Validar móvil solo si el checkbox está marcado y el campo tiene valor
        if (isset($_POST['bh_has_mobile_phone']) && $_POST['bh_has_mobile_phone'] && 
            isset($_POST['billing_mobile_phone']) && !empty($_POST['billing_mobile_phone'])) {
            $mobile_phone = sanitize_text_field($_POST['billing_mobile_phone']);
            $mobile_digits = preg_replace('/\D/', '', $mobile_phone);
            
            if (strlen($mobile_digits) !== 11) {
                wc_add_notice(
                    sprintf(__('Mobile number must have exactly 10 digits. Current: %d digits.', 'bh-features'), (strlen($mobile_digits) - 1)), 
                    'error'
                );
            } elseif ($this->is_all_zeros($mobile_digits)) {
                wc_add_notice(__('Mobile number cannot be all zeros. Please enter a valid number.', 'bh-features'), 'error');
            } elseif (!$this->is_valid_us_mobile_phone($mobile_phone)) {
                wc_add_notice(__('Please enter a valid US mobile number in the format +1 408-600-4784.', 'bh-features'), 'error');
            }
        }
    }
    
    /**
     * Validación adicional en checkout
     */
    public function validate_checkout_phones($data, $errors) {
        // Teléfono regular
        if (isset($data['billing_phone']) && !empty($data['billing_phone'])) {
            $digits_only = preg_replace('/\D/', '', $data['billing_phone']);
            
            if (strlen($digits_only) !== 10) {
                $errors->add('validation', __('Phone number must have exactly 10 digits.', 'bh-features'));
            } elseif ($this->is_all_zeros($digits_only)) {
                $errors->add('validation', __('Phone number cannot be all zeros. Please enter a valid number.', 'bh-features'));
            } elseif (!$this->is_valid_us_phone($data['billing_phone'])) {
                $errors->add('validation', __('Please enter a valid US phone number in the format (555) 123-4567.', 'bh-features'));
            }
        }
        
        // Móvil (condicional)
        if (isset($data['bh_has_mobile_phone']) && $data['bh_has_mobile_phone'] && 
            isset($data['billing_mobile_phone']) && !empty($data['billing_mobile_phone'])) {
            $mobile_digits = preg_replace('/\D/', '', $data['billing_mobile_phone']);
            
            if (strlen($mobile_digits) !== 11) {
                $errors->add('validation', __('Mobile number must have exactly 10 digits.', 'bh-features'));
            } elseif ($this->is_all_zeros($mobile_digits)) {
                $errors->add('validation', __('Mobile number cannot be all zeros. Please enter a valid number.', 'bh-features'));
            } elseif (!$this->is_valid_us_mobile_phone($data['billing_mobile_phone'])) {
                $errors->add('validation', __('Please enter a valid US mobile number in the format +1 408-600-4784.', 'bh-features'));
            }
        }
    }
    
    /**
     * Guardar campos personalizados - HPOS compatible con VALIDACIÓN EXTRA
     */
    public function save_custom_fields($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }
        
        // Guardar teléfono regular (se guarda automáticamente en wc_order_addresses)
        if (isset($_POST['billing_phone']) && !empty($_POST['billing_phone'])) {
            $phone = sanitize_text_field($_POST['billing_phone']);
            $digits_only = preg_replace('/\D/', '', $phone);
            
            // SOLO guardar si tiene 10 dígitos completos Y no son todos ceros
            if (strlen($digits_only) === 10 && !$this->is_all_zeros($digits_only)) {
                $formatted_phone = $this->format_phone_to_e164($phone, 'phone');
                $order->set_billing_phone($formatted_phone);
            }
            // Si no cumple, NO se guarda (mantiene el valor anterior o queda vacío)
        }
        
        // Guardar checkbox y móvil en meta datos
        $has_mobile = isset($_POST['bh_has_mobile_phone']) ? 1 : 0;
        $order->update_meta_data('_bh_has_mobile_phone', $has_mobile);
        
        if ($has_mobile && isset($_POST['billing_mobile_phone']) && !empty($_POST['billing_mobile_phone'])) {
            $mobile_phone = sanitize_text_field($_POST['billing_mobile_phone']);
            $mobile_digits = preg_replace('/\D/', '', $mobile_phone);
            
            // SOLO guardar si tiene 11 dígitos (+1 + 10 dígitos) Y no son todos ceros
            if (strlen($mobile_digits) === 11 && !$this->is_all_zeros($mobile_digits)) {
                $formatted_mobile = $this->format_phone_to_e164($mobile_phone, 'mobile');
                $order->update_meta_data('_billing_mobile_phone', $formatted_mobile);
            }
            // Si no cumple, NO se guarda
        } else {
            // Limpiar si no hay móvil
            $order->delete_meta_data('_billing_mobile_phone');
        }
        
        $order->save();
    }
    
    /**
     * Guardar campos en el cliente
     */
    public function save_customer_fields__($user_id, $address_type) {
        if ($address_type === 'billing') {
            // Teléfono regular
            if (isset($_POST['billing_phone'])) {
                $phone = sanitize_text_field($_POST['billing_phone']);
                $digits_only = preg_replace('/\D/', '', $phone);
                
                // SOLO guardar si tiene 10 dígitos completos Y no son todos ceros
                if (strlen($digits_only) === 10 && !$this->is_all_zeros($digits_only)) {
                    $formatted_phone = $this->format_phone_to_e164($phone, 'phone');
                    update_user_meta($user_id, 'billing_phone', $formatted_phone);
                }
            }
            
            // Checkbox y móvil
            $has_mobile = isset($_POST['bh_has_mobile_phone']) ? 1 : 0;
            update_user_meta($user_id, 'bh_has_mobile_phone', $has_mobile);
            
            if (isset($_POST['billing_mobile_phone'])) {
                $mobile_phone = sanitize_text_field($_POST['billing_mobile_phone']);
                $mobile_digits = preg_replace('/\D/', '', $mobile_phone);
                
                if ($has_mobile && !empty($mobile_phone)) {
                    // SOLO guardar si tiene 11 dígitos (+1 + 10 dígitos) Y no son todos ceros
                    if (strlen($mobile_digits) === 11 && !$this->is_all_zeros($mobile_digits)) {
                        $formatted_mobile = $this->format_phone_to_e164($mobile_phone, 'mobile');
                        update_user_meta($user_id, 'billing_mobile_phone', $formatted_mobile);
                    }
                } else {
                    delete_user_meta($user_id, 'billing_mobile_phone');
                }
            }
        }
    }
    public function save_customer_fields($user_id, $address_type) {
        if ($address_type === 'billing') {
            // Teléfono regular
            if (isset($_POST['billing_phone'])) {
                $phone = sanitize_text_field($_POST['billing_phone']);
                $digits_only = preg_replace('/\D/', '', $phone);
                
                // SOLO guardar si tiene 10 dígitos completos Y no son todos ceros
                if (strlen($digits_only) === 10 && !$this->is_all_zeros($digits_only)) {
                    $formatted_phone = $this->format_phone_to_e164($phone, 'phone');
                    update_user_meta($user_id, 'billing_phone', $formatted_phone);
                    
                    // DEBUG: Log para verificar qué se está guardando
                    error_log("BH Phone Debug - User ID: $user_id, Input: $phone, Digits: $digits_only, Saved: $formatted_phone");
                } else {
                    // DEBUG: Log para números inválidos
                    error_log("BH Phone Debug - Invalid phone for user $user_id: $phone (Digits: $digits_only)");
                }
            }
            
            // Checkbox y móvil
            $has_mobile = isset($_POST['bh_has_mobile_phone']) ? 1 : 0;
            update_user_meta($user_id, 'bh_has_mobile_phone', $has_mobile);
            
            if (isset($_POST['billing_mobile_phone'])) {
                $mobile_phone = sanitize_text_field($_POST['billing_mobile_phone']);
                $mobile_digits = preg_replace('/\D/', '', $mobile_phone);
                
                if ($has_mobile && !empty($mobile_phone)) {
                    // SOLO guardar si tiene 11 dígitos (+1 + 10 dígitos) Y no son todos ceros
                    if (strlen($mobile_digits) === 11 && !$this->is_all_zeros($mobile_digits)) {
                        $formatted_mobile = $this->format_phone_to_e164($mobile_phone, 'mobile');
                        update_user_meta($user_id, 'billing_mobile_phone', $formatted_mobile);
                    }
                } else {
                    delete_user_meta($user_id, 'billing_mobile_phone');
                }
            }
        }
    }
    
    /**
     * AJAX para mostrar/ocultar campo móvil
     */
    public function toggle_mobile_field() {
        check_ajax_referer('bh_phone_nonce', 'nonce');
        
        $has_mobile = isset($_POST['has_mobile']) && $_POST['has_mobile'] === 'true';
        
        if ($has_mobile) {
            $field_html = '
                <p class="form-row form-row-wide bh-mobile-field" id="billing_mobile_phone_field">
                    <label for="billing_mobile_phone" class="">Mobile Phone</label>
                    <span class="woocommerce-input-wrapper">
                        <input type="tel" class="input-text bh-mobile-phone-formatted" name="billing_mobile_phone" id="billing_mobile_phone" placeholder="+1 408-600-4784" data-mobile-phone-us="true" value="">
                    </span>
                </p>
            ';
        } else {
            $field_html = '';
        }
        
        wp_send_json_success(array(
            'html' => $field_html,
            'has_mobile' => $has_mobile
        ));
    }
    
    /**
     * Validar número de teléfono USA regular
     */
    private function is_valid_us_phone($phone) {
        // Limpiar y verificar
        $clean_phone = preg_replace('/\D/', '', $phone);
        
        // Debe tener 10 dígitos
        if (strlen($clean_phone) !== 10) {
            return false;
        }
        
        // No puede ser todos ceros
        if ($this->is_all_zeros($clean_phone)) {
            return false;
        }
        
        // Código de área no puede ser 555
        $area_code = substr($clean_phone, 0, 3);
        if ($area_code === '555') {
            return false;
        }
        
        return true;
    }
    
    /**
     * Validar número de móvil USA
     */
    private function is_valid_us_mobile_phone($phone) {
        // Verificar formato +1 408-600-4784
        if (!preg_match('/^\+\d{1}\s\d{3}-\d{3}-\d{4}$/', trim($phone))) {
            return false;
        }
        
        // Verificar que empiece con +1
        if (substr($phone, 0, 2) !== '+1') {
            return false;
        }
        
        // Extraer dígitos y validar
        $digits_only = preg_replace('/\D/', '', $phone);
        if (strlen($digits_only) !== 11) {
            return false;
        }
        
        // No puede ser todos ceros
        if ($this->is_all_zeros($digits_only)) {
            return false;
        }
        
        $area_code = substr($digits_only, 1, 3);
        return $area_code !== '555';
    }
    
    /**
     * Convertir a formato E.164
     */
    private function format_phone_to_e164__($phone, $type = 'phone') {
        $digits_only = preg_replace('/\D/', '', $phone);
        
        // Para teléfono regular (10 dígitos)
        if (strlen($digits_only) === 10) {
            return '+1' . $digits_only;
        }
        
        // Para móvil o números con código de país
        if (strlen($digits_only) === 11 && substr($digits_only, 0, 1) === '1') {
            return '+' . $digits_only;
        }
        
        return $phone;
    }
    private function format_phone_to_e164($phone, $type = 'phone') {
        $digits_only = preg_replace('/\D/', '', $phone);
        
        // Si ya tiene 11 dígitos y empieza con 1, ya está en formato correcto
        if (strlen($digits_only) === 11 && substr($digits_only, 0, 1) === '1') {
            return '+' . $digits_only;
        }
        
        // Si tiene 10 dígitos, agregar +1
        if (strlen($digits_only) === 10) {
            // Verificar que no sea un código de área inválido
            $area_code = substr($digits_only, 0, 3);
            if ($area_code !== '555') {
                return '+1' . $digits_only;
            }
        }
        
        // Para cualquier otro caso, devolver el número limpio
        return $phone;
    }
    
    /**
     * Formatear para visualización
     */
    public function format_phone_to_display__($phone, $type = 'phone') {
        $digits_only = preg_replace('/\D/', '', $phone);
        
        // Remover el 1 inicial si existe
        if (strlen($digits_only) === 11 && substr($digits_only, 0, 1) === '1') {
            $digits_only = substr($digits_only, 1);
        }
        
        if (strlen($digits_only) === 10) {
            if ($type === 'mobile') {
                return '+1 ' . substr($digits_only, 0, 3) . '-' . substr($digits_only, 3, 3) . '-' . substr($digits_only, 6, 4);
            } else {
                return '(' . substr($digits_only, 0, 3) . ') ' . substr($digits_only, 3, 3) . '-' . substr($digits_only, 6, 4);
            }
        }
        
        return $phone;
    }
    private function format_phone_to_display($phone, $type = 'phone') {
        $digits_only = preg_replace('/\D/', '', $phone);
        
        // Si el número está en formato E.164 (+11234567890)
        if (strlen($digits_only) === 11 && substr($digits_only, 0, 1) === '1') {
            $digits_only = substr($digits_only, 1); // Remover el 1 del código de país
        }
        
        // Formatear solo si tenemos 10 dígitos
        if (strlen($digits_only) === 10) {
            if ($type === 'mobile') {
                return '+1 ' . substr($digits_only, 0, 3) . '-' . substr($digits_only, 3, 3) . '-' . substr($digits_only, 6, 4);
            } else {
                return '(' . substr($digits_only, 0, 3) . ') ' . substr($digits_only, 3, 3) . '-' . substr($digits_only, 6, 4);
            }
        }
        
        // Si no se puede formatear, devolver el original
        return $phone;
    }

    function format_phone_customer_details($phone, $order) {
        if (!empty($phone)) {
            $formatter = new BH_US_Phone_Standardization();
            return $formatter->format_phone_to_display($phone, 'phone');
        }
        return $phone;
    }

    
}

new BH_US_Phone_Standardization();
