(function($) {
    'use strict';

    class BHAdminPhoneValidation {
        constructor() {
            this.init();
        }

        init() {
            $(document).ready(() => {
                this.initializePhoneFields();
                this.addValidationHandlers();
                this.preventInvalidSave();
            });
        }

        initializePhoneFields() {
            console.log('Initializing admin phone fields...');
            
            // Teléfono regular en admin (campo nativo de WooCommerce)
            const phoneFields = $('#_billing_phone');
            phoneFields.each((index, element) => {
                $(element).addClass('bh-phone-formatted');
                
                // Formatear valor existente
                if ($(element).val()) {
                    this.formatRegularPhoneField(element);
                    this.validateDigitCount(element, 'phone');
                }
                
                $(element).on('input', () => {
                    this.formatRegularPhoneField(element);
                    this.validateDigitCount(element, 'phone');
                    this.validatePhoneField(element, 'phone');
                });
                
                $(element).on('blur', () => {
                    this.formatPhoneComplete(element, 'phone');
                    this.validateDigitCount(element, 'phone');
                });
            });

            // Móvil en admin
            const mobileFields = $('#_billing_mobile_phone');
            mobileFields.each((index, element) => {
                $(element).addClass('bh-mobile-phone-formatted');
                
                // Formatear valor existente
                if ($(element).val()) {
                    this.formatMobilePhoneField(element);
                    this.validateDigitCount(element, 'mobile');
                }
                
                $(element).on('input', () => {
                    this.formatMobilePhoneField(element);
                    this.validateDigitCount(element, 'mobile');
                    this.validatePhoneField(element, 'mobile');
                });
                
                $(element).on('blur', () => {
                    this.formatPhoneComplete(element, 'mobile');
                    this.validateDigitCount(element, 'mobile');
                });
                
                $(element).on('focus', () => {
                    if (!$(element).val()) {
                        $(element).val('+1 ');
                    }
                });
            });
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
                    message = `${currentDigits}/${requiredDigits - (type === 'mobile' ? 1 : 0)} digits - INCOMPLETE`;
                    $(element).addClass('bh-phone-incomplete');
                } else if (this.isAllZeros(digitsOnly)) {
                    isValid = false;
                    message = 'Cannot be all zeros';
                    $(element).addClass('bh-phone-invalid');
                } else {
                    message = `${currentDigits}/${requiredDigits - (type === 'mobile' ? 1 : 0)} digits - COMPLETE`;
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
            const $container = $(element).closest('p.form-field');
            const errorClass = 'bh-digit-count ' + type;
            
            $container.find('.bh-digit-count').remove();
            $container.append(`<span class="${errorClass}">${message}</span>`);
        }

        hideDigitCountError(element) {
            const $container = $(element).closest('p.form-field');
            $container.find('.bh-digit-count').remove();
        }

        preventInvalidSave() {
            // Deshabilitar el botón de guardar si hay números inválidos
            $('button.save-order, button.button-primary').on('click', (e) => {
                if (!this.validateAllPhoneFields()) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    alert('Cannot save: Please fix phone number errors. Numbers must have exactly 10 digits and cannot be all zeros.');
                    return false;
                }
                return true;
            });

            // También capturar el submit del formulario
            $('#post, form#post').on('submit', (e) => {
                if (!this.validateAllPhoneFields()) {
                    e.preventDefault();
                    alert('Cannot save: Please fix phone number errors. Numbers must have exactly 10 digits and cannot be all zeros.');
                    return false;
                }
                return true;
            });
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
            return digits.length === 10 && digits.substring(0, 3) !== '555';
        }

        isValidUSMobilePhone(phone) {
            return /^\+\d{1}\s\d{3}-\d{3}-\d{4}$/.test(phone.trim()) && 
                   phone.startsWith('+1') && 
                   phone.substring(3, 6) !== '555';
        }

        toggleFormatError(element, isValid, type) {
            const $container = $(element).closest('p.form-field');
            const errorClass = type === 'mobile' ? 'bh-mobile-format-error' : 'bh-phone-format-error';
            const message = type === 'mobile' ? bh_phone_params.invalid_mobile : bh_phone_params.invalid_phone;
            
            $container.find('.' + errorClass).remove();
            
            if (!isValid && $(element).val().length > 0) {
                $container.append(`<span class="${errorClass} bh-phone-error">${message}</span>`);
            }
        }

        addValidationHandlers() {
            // Validar antes de guardar la orden
            $('#post').on('submit', (e) => {
                return this.validateAllPhoneFields();
            });
        }

         validateAllPhoneFields() {
            let allValid = true;

            // Validar teléfono regular
            const phoneFields = $('#_billing_phone');
            phoneFields.each((index, element) => {
                const value = $(element).val();
                const digits = value.replace(/\D/g, '');
                
                if (value.length > 0 && (digits.length !== 10 || this.isAllZeros(digits))) {
                    allValid = false;
                    this.validateDigitCount(element, 'phone');
                }
            });

            // Validar móvil solo si está visible y tiene valor
            const mobileFields = $('#_billing_mobile_phone');
            mobileFields.each((index, element) => {
                const value = $(element).val();
                const digits = value.replace(/\D/g, '');
                
                if (value.length > 3 && (digits.length !== 11 || this.isAllZeros(digits))) {
                    allValid = false;
                    this.validateDigitCount(element, 'mobile');
                }
            });

            return allValid;
        }
    }

    // Inicializar cuando el documento esté listo
    $(document).ready(() => {
        new BHAdminPhoneValidation();
    });

})(jQuery);