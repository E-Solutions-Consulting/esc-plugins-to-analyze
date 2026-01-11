jQuery(function($){

    function digitsOnly(v) {
        return (v || '').toString().replace(/\D/g, '');
    }

    function normalizeTo10(v) {
        var d = digitsOnly(v);

        // Remove leading country code if E.164 (+1)
        if (d.length === 11 && d.charAt(0) === '1') {
            d = d.substring(1);
        }

        // Hard limit: ONLY allow 10 digits
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

        // Set placeholder for clarity
        el.attr('placeholder', '(###) ###-####');

        // Initial format (handles +1... from DB)
        el.val(formatUS(el.val()));

        // Restrict input: only digits, max 10
        el.on('input', function(e){
            var raw = digitsOnly(this.value).substring(0,10);
            this.value = formatUS(raw);
        });

        // Prevent typing more than allowed digits
        el.on('keypress', function(e){
            var d = digitsOnly(this.value);

            // If already 10 digits, block further numeric input
            if (d.length >= 10 && /[0-9]/.test(e.key)) {
                e.preventDefault();
            }
        });
    }

    // Frontend fields
    applyMask('#billing_phone');
    applyMask('#shipping_phone');

    // Admin edit order / user profile fields
    applyMask('input[name="_billing_phone"]');
    applyMask('input[name="_shipping_phone"]');
    applyMask('#billing_phone_field input');
    applyMask('#shipping_phone_field input');
});
