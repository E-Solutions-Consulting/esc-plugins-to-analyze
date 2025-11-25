(function($) {
    'use strict';

    class BHUSPhoneValidation {
        constructor() {
            this.init();
        }

        init() {
            $(document).ready(() => {
                this.initializePhoneFields();
                this.addValidationHandlers();
            });

            // Re-inicializar en actualización de checkout
            $(document).on('updated_checkout', () => {
                this.initializePhoneFields();
            });
        }

        initializePhoneFields() {console.log('Initializing public phone fields...');
            const phoneFields = $('input[name="billing_phone"], input[data-phone-us]');
            
            phoneFields.each((index, element) => {
                // Agregar clase para estilo
                $(element).addClass('bh-phone-formatted');
                
                // Aplicar máscara de entrada
                this.applyPhoneMask(element);
                
                // Validar en tiempo real
                $(element).on('input', () => {
                    this.formatPhoneField(element);
                    this.validatePhoneField(element);
                });

                // Formatear al perder foco
                $(element).on('blur', () => {
                    this.formatPhoneComplete(element);
                });

                // Permitir solo números y algunos caracteres especiales
                $(element).on('keypress', (e) => {
                    return this.validateKeyPress(e);
                });
            });
        }

        applyPhoneMask(element) {
            $(element).attr('maxlength', '14');
            $(element).attr('placeholder', bh_us_phone_params.placeholder);
        }

        formatPhoneField(element) {
            let value = $(element).val().replace(/\D/g, '');
            
            if (value.length > 0) {
                if (value.length <= 3) {
                    value = '(' + value;
                } else if (value.length <= 6) {
                    value = '(' + value.substring(0, 3) + ') ' + value.substring(3);
                } else {
                    value = '(' + value.substring(0, 3) + ') ' + value.substring(3, 6) + '-' + value.substring(6, 10);
                }
            }
            
            $(element).val(value);
        }

        formatPhoneComplete(element) {
            let value = $(element).val().replace(/\D/g, '');
            
            if (value.length === 10) {
                value = '(' + value.substring(0, 3) + ') ' + value.substring(3, 6) + '-' + value.substring(6, 10);
                $(element).val(value);
            }
        }

        validatePhoneField(element) {
            const value = $(element).val();
            const isValid = this.isValidUSPhone(value);

            // Actualizar clases de validación
            $(element).toggleClass('woocommerce-valid', isValid);
            $(element).toggleClass('woocommerce-invalid', !isValid && value.length > 0);

            // Mostrar/ocultar mensaje de error
            this.toggleErrorMessage(element, isValid);
        }

        isValidUSPhone(phone) {
            // Patrón para (555) 123-4567
            const pattern = /^\(\d{3}\) \d{3}-\d{4}$/;
            return pattern.test(phone.trim());
        }

        validateKeyPress(e) {
            const key = e.key;
            // Permitir: números, paréntesis, guiones, espacio, backspace, tab, delete
            return /[\d\(\)\-\s]|Backspace|Tab|Delete/.test(key) || 
                   (e.keyCode === 8 || e.keyCode === 9 || e.keyCode === 46);
        }

        toggleErrorMessage(element, isValid) {
            const $row = $(element).closest('.form-row');
            $row.find('.bh-phone-error').remove();
            
            if (!isValid && $(element).val().length > 0) {
                $row.append(
                    `<div class="bh-phone-error">${bh_us_phone_params.invalid_phone}</div>`
                );
            }
        }

        addValidationHandlers() {
            // Validar antes de enviar el formulario de checkout
            $('form.checkout').on('checkout_place_order', () => {
                return this.validateAllPhoneFields();
            });

            // Validar en la página de mi cuenta
            $('form.edit-account, form.edit-address').on('submit', (e) => {
                if (!this.validateAllPhoneFields()) {
                    e.preventDefault();
                    return false;
                }
                return true;
            });
        }

        validateAllPhoneFields() {
            let allValid = true;
            const phoneFields = $('input[name="billing_phone"], input[data-phone-us]');

            phoneFields.each((index, element) => {
                const value = $(element).val();
                if (value.length > 0 && !this.isValidUSPhone(value)) {
                    allValid = false;
                    this.toggleErrorMessage(element, false);
                    $(element).focus();
                }
            });

            if (!allValid) {
                $('html, body').animate({
                    scrollTop: phoneFields.first().offset().top - 100
                }, 500);
            }

            return allValid;
        }
    }

    // Inicializar
    $(document).ready(() => {
        new BHUSPhoneValidation();
    });

})(jQuery);