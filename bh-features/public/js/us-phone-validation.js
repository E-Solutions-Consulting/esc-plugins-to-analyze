(function($) {
    'use strict';

    class BHUSPhoneValidation {
        constructor() {
            this.init();
        }

        init() {
            $(document).ready(() => {
                this.initializePhoneFields();
                this.initializeMobilePhoneToggle();
                this.addValidationHandlers();
            });

            // Re-inicializar cuando WooCommerce actualice el checkout
            $(document).on('updated_checkout', () => {
                setTimeout(() => {
                    this.initializePhoneFields();
                    this.initializeMobilePhoneToggle();
                }, 100);
            });
        }

        initializePhoneFields() {
            console.log('Initializing frontend phone fields...');
            
            // Teléfono regular
            /*const phoneFields = $('input[name="billing_phone"]');
            phoneFields.each((index, element) => {
                if (!$(element).hasClass('bh-initialized')) {
                    $(element).addClass('bh-phone-formatted bh-initialized');
                    $(element).attr('placeholder', bh_phone_params.phone_placeholder);
                    
                    // Formatear valor existente
                    if ($(element).val()) {
                        this.formatRegularPhoneField(element);
                    }
                    
                    $(element).on('input', () => {
                        this.formatRegularPhoneField(element);
                        this.validatePhoneField(element, 'phone');
                    });
                    
                    $(element).on('blur', () => {
                        this.formatPhoneComplete(element, 'phone');
                        this.validatePhoneField(element, 'phone');
                    });
                    
                    $(element).on('keypress', (e) => {
                        return this.validateKeyPress(e, 'phone');
                    });
                }
            });
            */
           const phoneFields = $('input[name="billing_phone"]');
            phoneFields.each((index, element) => {
                if (!$(element).hasClass('bh-initialized')) {
                    $(element).addClass('bh-phone-formatted bh-initialized');
                    $(element).attr('placeholder', bh_phone_params.phone_placeholder);
                    
                    // Formatear valor existente SI está en formato E.164
                    if ($(element).val()) {
                        const currentValue = $(element).val();
                        // Si parece estar en formato E.164 (+11234567890), formatearlo
                        if (/^\+\d{11}$/.test(currentValue)) {
                            const digitsOnly = currentValue.replace(/\D/g, '').substring(1); // Remover el +1
                            if (digitsOnly.length === 10) {
                                const formatted = '(' + digitsOnly.substring(0, 3) + ') ' + 
                                                digitsOnly.substring(3, 6) + '-' + 
                                                digitsOnly.substring(6, 10);
                                $(element).val(formatted);
                            }
                        }
                        this.validatePhoneField(element, 'phone');
                    }
                    
                    $(element).on('input', () => {
                        this.formatRegularPhoneField(element);
                        this.validatePhoneField(element, 'phone');
                    });
                    
                    $(element).on('blur', () => {
                        this.formatPhoneComplete(element, 'phone');
                        this.validatePhoneField(element, 'phone');
                    });
                }
            });

            // Móvil
            const mobileFields = $('input[name="billing_mobile_phone"]');
            mobileFields.each((index, element) => {
                if (!$(element).hasClass('bh-initialized')) {
                    $(element).addClass('bh-mobile-phone-formatted bh-initialized');
                    $(element).attr('placeholder', bh_phone_params.mobile_placeholder);
                    
                    // Formatear valor existente
                    if ($(element).val()) {
                        this.formatMobilePhoneField(element);
                    }
                    
                    $(element).on('input', () => {
                        this.formatMobilePhoneField(element);
                        this.validatePhoneField(element, 'mobile');
                    });
                    
                    $(element).on('blur', () => {
                        this.formatPhoneComplete(element, 'mobile');
                        this.validatePhoneField(element, 'mobile');
                    });
                    
                    $(element).on('focus', () => {
                        if (!$(element).val()) {
                            $(element).val('+1 ');
                        }
                    });
                    
                    $(element).on('keypress', (e) => {
                        return this.validateKeyPress(e, 'mobile');
                    });
                }
            });
        }

        initializeMobilePhoneToggle() {console.log('Initializing mobile phone toggle...');
            const checkbox = $('input[name="bh_has_mobile_phone"]');
            const mobileField = $('#billing_mobile_phone_field');
            
            if (checkbox.length && mobileField.length) {
                // Crear wrapper si no existe
                if (!mobileField.parent().hasClass('bh-mobile-field-wrapper')) {
                    mobileField.wrap('<div class="bh-mobile-field-wrapper"></div>');
                }
                
                const wrapper = mobileField.parent('.bh-mobile-field-wrapper');
                
                // Estado inicial basado en el checkbox
                if (checkbox.is(':checked')) {
                    wrapper.removeClass('hidden');
                } else {
                    wrapper.addClass('hidden');
                }
                
                // Cambio en checkbox
                checkbox.on('change', (e) => {
                    const isChecked = $(e.target).is(':checked');
                    this.toggleMobileField(isChecked, wrapper);
                });
            }
        }

        toggleMobileField(isVisible, wrapper) {
            if (isVisible) {
                wrapper.removeClass('hidden');
                // Re-inicializar el campo cuando se muestra
                setTimeout(() => {
                    this.initializePhoneFields();
                }, 100);
            } else {
                wrapper.addClass('hidden');
                // Limpiar valor cuando se oculta
                $('input[name="billing_mobile_phone"]').val('');
            }
        }

        formatRegularPhoneField(element) {
            let value = $(element).val().replace(/\D/g, '');
            
            if (value.length > 0) {
                if (value.length <= 3) {
                    value = '(' + value;
                } else if (value.length <= 6) {
                    value = '(' + value.substring(0, 3) + ') ' + value.substring(3);
                } else {
                    value = '(' + value.substring(0, 3) + ') ' + value.substring(3, 6) + '-' + value.substring(6, 10);
                }
                $(element).val(value);
            }
            
            // Validar en tiempo real
            this.validateDigitCount(element, 'phone');
        }

        formatMobilePhoneField(element) {
            let value = $(element).val();
            
            // Si el usuario borra el +1, restaurarlo
            if (!value.startsWith('+1')) {
                value = '+1 ' + value.replace(/\D/g, '');
            }
            
            // Formatear el resto del número
            if (value.startsWith('+1')) {
                let rest = value.substring(2).replace(/\D/g, '');
                
                if (rest.length > 0) {
                    if (rest.length <= 3) {
                        rest = ' ' + rest;
                    } else if (rest.length <= 6) {
                        rest = ' ' + rest.substring(0, 3) + '-' + rest.substring(3);
                    } else {
                        rest = ' ' + rest.substring(0, 3) + '-' + rest.substring(3, 6) + '-' + rest.substring(6, 10);
                    }
                    value = '+1' + rest;
                }
                
                $(element).val(value);
            }
            
            // Validar en tiempo real
            this.validateDigitCount(element, 'mobile');
        }

        formatPhoneComplete(element, type) {
            let value = $(element).val();
            
            if (type === 'mobile' && value.startsWith('+1')) {
                let digits = value.substring(2).replace(/\D/g, '');
                if (digits.length === 10) {
                    value = '+1 ' + digits.substring(0, 3) + '-' + digits.substring(3, 6) + '-' + digits.substring(6, 10);
                    $(element).val(value);
                }
            } else if (type === 'phone') {
                let digits = value.replace(/\D/g, '');
                if (digits.length === 10) {
                    value = '(' + digits.substring(0, 3) + ') ' + digits.substring(3, 6) + '-' + digits.substring(6, 10);
                    $(element).val(value);
                }
            }
        }

        validateDigitCount(element, type) {
            const value = $(element).val();
            const digitsOnly = value.replace(/\D/g, '');
            const requiredDigits = type === 'mobile' ? 11 : 10;
            const currentDigits = type === 'mobile' ? (digitsOnly.length - 1) : digitsOnly.length;
            
            $(element).removeClass('bh-phone-incomplete bh-phone-invalid');
            this.hideDigitCountError(element);
            
            let isValid = true;
            let message = '';
            
            if (value.length > 0) {
                if (digitsOnly.length !== requiredDigits) {
                    isValid = false;
                    message = `${currentDigits}/10 digits - INCOMPLETE`;
                    $(element).addClass('bh-phone-incomplete');
                } else if (this.isAllZeros(digitsOnly)) {
                    isValid = false;
                    message = 'Cannot be all zeros';
                    $(element).addClass('bh-phone-invalid');
                } else {
                    message = `${currentDigits}/10 digits - COMPLETE`;
                }
                
                if (!isValid) {
                    this.showDigitCountError(element, message, 'error');
                } else {
                    this.showDigitCountError(element, message, 'success');
                }
            }
            
            return isValid;
        }

        isAllZeros(phone) {
            return /^0+$/.test(phone);
        }

        showDigitCountError(element, message, type) {
            const $container = $(element).closest('.form-row');
            const errorClass = 'bh-digit-count ' + type;
            
            $container.find('.bh-digit-count').remove();
            $container.append(`<span class="${errorClass}">${message}</span>`);
        }

        hideDigitCountError(element) {
            const $container = $(element).closest('.form-row');
            $container.find('.bh-digit-count').remove();
        }

        validatePhoneField(element, type) {
            const value = $(element).val();
            let isValid = false;

            if (type === 'mobile') {
                isValid = this.isValidUSMobilePhone(value);
            } else {
                isValid = this.isValidUSPhone(value);
            }

            // Solo validar si el campo tiene valor
            const hasValue = value.length > (type === 'mobile' ? 3 : 0);
            
            $(element).toggleClass('woocommerce-valid', isValid && hasValue);
            $(element).toggleClass('woocommerce-invalid', !isValid && hasValue);

            this.toggleFormatError(element, isValid || !hasValue, type);
        }

        isValidUSPhone(phone) {
            const digits = phone.replace(/\D/g, '');
            return digits.length === 10 && 
                   digits.substring(0, 3) !== '555' && 
                   !this.isAllZeros(digits);
        }

        isValidUSMobilePhone(phone) {
            const digits = phone.replace(/\D/g, '');
            return /^\+\d{1}\s\d{3}-\d{3}-\d{4}$/.test(phone.trim()) && 
                   phone.startsWith('+1') && 
                   digits.length === 11 &&
                   digits.substring(1, 4) !== '555' &&
                   !this.isAllZeros(digits);
        }

        validateKeyPress(e, type) {
            const key = e.key;
            
            // Permitir: números, paréntesis, guiones, espacio, backspace, tab, delete, flechas
            const allowedKeys = [
                '0', '1', '2', '3', '4', '5', '6', '7', '8', '9',
                '(', ')', '-', ' ',
                'Backspace', 'Tab', 'Delete', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'
            ];
            
            // Para móvil, permitir también el +
            if (type === 'mobile') {
                allowedKeys.push('+');
            }
            
            return allowedKeys.includes(key) || 
                   e.keyCode === 8 || e.keyCode === 9 || e.keyCode === 46 ||
                   e.keyCode === 37 || e.keyCode === 38 || e.keyCode === 39 || e.keyCode === 40;
        }

        toggleFormatError(element, isValid, type) {
            const $container = $(element).closest('.form-row');
            const errorClass = type === 'mobile' ? 'bh-mobile-format-error' : 'bh-phone-format-error';
            const message = type === 'mobile' ? bh_phone_params.invalid_mobile : bh_phone_params.invalid_phone;
            
            $container.find('.' + errorClass).remove();
            
            if (!isValid && $(element).val().length > 0) {
                $container.append(`<span class="${errorClass} bh-phone-error">${message}</span>`);
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
                    alert('Please fix phone number errors before saving. Numbers must have exactly 10 digits and cannot be all zeros.');
                    return false;
                }
                return true;
            });
        }

        validateAllPhoneFields() {
            let allValid = true;

            // Validar teléfono regular
            const phoneFields = $('input[name="billing_phone"]');
            phoneFields.each((index, element) => {
                const value = $(element).val();
                const digits = value.replace(/\D/g, '');
                
                if (value.length > 0 && (digits.length !== 10 || this.isAllZeros(digits))) {
                    allValid = false;
                    this.validateDigitCount(element, 'phone');
                    this.toggleFormatError(element, false, 'phone');
                }
            });

            // Validar móvil solo si está visible y tiene valor
            const mobileFields = $('input[name="billing_mobile_phone"]:visible');
            mobileFields.each((index, element) => {
                const value = $(element).val();
                const digits = value.replace(/\D/g, '');
                
                if (value.length > 3 && (digits.length !== 11 || this.isAllZeros(digits))) {
                    allValid = false;
                    this.validateDigitCount(element, 'mobile');
                    this.toggleFormatError(element, false, 'mobile');
                }
            });

            if (!allValid) {
                // Scroll al primer error
                const firstError = $('.bh-phone-incomplete, .bh-phone-invalid').first();
                if (firstError.length) {
                    $('html, body').animate({
                        scrollTop: firstError.offset().top - 100
                    }, 500);
                }
                
                // Mostrar mensaje de error
                if ($('form.checkout').length) {
                    this.showCheckoutError();
                }
            }

            return allValid;
        }

        showCheckoutError() {
            // Remover mensaje anterior si existe
            $('.bh-checkout-phone-error').remove();
            
            // Agregar mensaje de error en el checkout
            const errorHtml = `
                <div class="bh-checkout-phone-error woocommerce-error" style="display: block;">
                    Please fix phone number errors before proceeding. Numbers must have exactly 10 digits and cannot be all zeros.
                </div>
            `;
            
            // Insertar después del título o al principio del formulario
            $('form.checkout h3').first().after(errorHtml);
            
            // Hacer scroll al mensaje de error
            $('html, body').animate({
                scrollTop: $('.bh-checkout-phone-error').offset().top - 100
            }, 500);
        }
    }

    // Inicializar cuando el documento esté listo
    $(document).ready(() => {
        new BHUSPhoneValidation();
    });

})(jQuery);