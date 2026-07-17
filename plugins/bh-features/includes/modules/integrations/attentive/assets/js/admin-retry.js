/**
 * Attentive Admin Retry Functionality
 */

jQuery(document).ready(function($) {
    
    // Handle customer profile retry button
    $(document).on('click', '.bh-attentive-retry-customer', function(e) {
        e.preventDefault();
        
        const button = $(this);
        const userId = button.data('user-id');
        const phone = button.data('phone');
        const email = button.data('email');
        const resultDiv = $('#attentive-retry-result-' + userId);
        
        retryAttentiveSubscription({
            action: 'bh_attentive_retry_customer',
            user_id: userId,
            phone: phone,
            email: email
        }, button, resultDiv);
    });
    
    // Handle subscription page retry button
    $(document).on('click', '.bh-attentive-retry-subscription', function(e) {
        e.preventDefault();
        
        const button = $(this);
        const subscriptionId = button.data('subscription-id');
        const userId = button.data('user-id');
        const phone = button.data('phone');
        const email = button.data('email');
        const resultDiv = $('#attentive-retry-result-sub-' + subscriptionId);
        
        retryAttentiveSubscription({
            action: 'bh_attentive_retry_subscription',
            subscription_id: subscriptionId,
            user_id: userId,
            phone: phone,
            email: email
        }, button, resultDiv);
    });
    
    /**
     * Perform AJAX retry request
     */
    function retryAttentiveSubscription(data, button, resultDiv) {
        
        // Check if bhAttentiveRetry is available
        if (typeof bhAttentiveRetry === 'undefined') {
            alert('Script configuration error. Please refresh the page.');
            return;
        }
        
        // Add nonce
        data.nonce = bhAttentiveRetry.nonce;
        
        // Disable button and show loading state
        button.prop('disabled', true).text('Processing...');
        resultDiv.html('<p style="color: #0073aa;">Processing...</p>');
        
        $.ajax({
            url: bhAttentiveRetry.ajaxurl,
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    resultDiv.html('<p style="color: #00a32a;"><strong>Success!</strong> ' + response.data + '</p>');
                    
                    // Reload page after 2 seconds to show updated status
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    resultDiv.html('<p style="color: #d63638;"><strong>Error:</strong> ' + response.data + '</p>');
                }
            },
            error: function(xhr, status, error) {
                resultDiv.html('<p style="color: #d63638;"><strong>Error:</strong> AJAX request failed: ' + error + '</p>');
            },
            complete: function() {
                // Re-enable button
                button.prop('disabled', false).text('Retry Attentive Subscription');
            }
        });
    }
});