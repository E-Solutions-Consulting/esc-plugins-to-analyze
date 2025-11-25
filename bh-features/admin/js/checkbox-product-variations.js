jQuery(document).ready(function($) {
    $('#productos-select2').select2({
        placeholder: 'Select Products',
        allowClear: true,
        templateSelection: formatState,
        /*templateResult: formatState,*/
    });

    function formatState (state) {
        if (!state.id) {
          return state.text;
        }
        let parent=$(state.element).data('parent');
        if(!parent)
            return state.text;
        
        let subscription    =   state.text.replace('Subscription: ', '');
        let productname     =   parent + ' ' + subscription;
        return productname;
      };
    $('#productos-select2').on('change', function() {
        var selectedProducts = $(this).val();
        selectedProducts.forEach(function(productId) {
            $('#productos-select2 option[data-producto="' + productId + '"]').each(function() {
                $(this).prop('selected', true);
            });
        });
    });    
});

jQuery(document).ready(function($) {
    $('.update_next_payment').on('click', function() {
            let subscription_id =   jQuery(this).data('subscription_id')
            console.log(subscription_id);
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'actualizar_next_payment',
                    subscription_id: subscription_id
                },
                success: function(response) {
                    console.log(response.response);
                }
            });
    });
});

