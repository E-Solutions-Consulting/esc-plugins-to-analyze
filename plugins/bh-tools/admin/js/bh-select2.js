jQuery(document).ready(function($) {
    $('.wc-enhanced-select').select2({
        width:'resolve',
        placeholder: 'Select State(s)',
        allowClear: true,
        templateSelection: formatState,
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
    $('.wc-enhanced-select').on('change', function() {
        var selectedProducts = $(this).val();
        selectedProducts.forEach(function(productId) {
            $('.wc-enhanced-select option[data-state="' + productId + '"]').each(function() {
                $(this).prop('selected', true);
            });
        });
    });    
});
