<?php
/**
 * Template for rejecting a quote
 *
 * @package    Quote_Manager_System_For_Woocommerce
 * @subpackage Quote_Manager_System_For_Woocommerce/public/templates
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

// Get quote data
$quote = get_post($quote_id);
if (!$quote || $quote->post_type !== 'customer_quote') {
    echo '<div class="quote-error-message">';
    echo '<p>' . esc_html__('Quote not found.', 'quote-manager-system-for-woocommerce') . '</p>';
    echo '</div>';
    return;
}

$customer_name = get_post_meta($quote_id, '_customer_first_name', true) . ' ' . get_post_meta($quote_id, '_customer_last_name', true);
$quote_number = '#' . str_pad($quote_id, 4, '0', STR_PAD_LEFT);

// Process any errors
$error_message = isset($error) ? $error : '';

// Get page permalink for the return URL
$page_url = get_permalink();
$view_url = add_query_arg(array(
    'view' => 'quote',
    'id' => $quote_id,
    'token' => $token
), $page_url);

// Generate nonce for AJAX submission
$ajax_nonce = wp_create_nonce('quote_reject_nonce');
?>

<div class="quote-reject-container">
    <div class="quote-reject-header">
        <h1><?php echo sprintf(esc_html__('Decline Quote %s', 'quote-manager-system-for-woocommerce'), esc_html($quote_number)); ?></h1>
    </div>

    <div class="quote-reject-content">
        <p>
            <?php echo sprintf(
                esc_html__('Dear %s, you are about to decline quote %s. If you wish, you can provide a reason for declining this quote.', 'quote-manager-system-for-woocommerce'),
                '<strong>' . esc_html($customer_name) . '</strong>',
                '<strong>' . esc_html($quote_number) . '</strong>'
            ); ?>
        </p>

        <?php if (!empty($error_message)): ?>
            <div class="quote-form-error">
                <?php echo esc_html($error_message); ?>
            </div>
        <?php endif; ?>

        <div id="quote-reject-form-container">
            <div class="quote-form-section">
                <label for="reject-reason"><?php esc_html_e('Reason for declining (optional):', 'quote-manager-system-for-woocommerce'); ?></label>
                <textarea name="reject_reason" id="reject-reason" rows="5"
                      placeholder="<?php esc_attr_e('Please let us know why you are declining this quote...', 'quote-manager-system-for-woocommerce'); ?>"></textarea>
            </div>

            <div class="quote-form-actions">
                <a href="<?php echo esc_url($view_url); ?>"
                   class="quote-action-button quote-action-secondary">
                    <?php esc_html_e('Cancel', 'quote-manager-system-for-woocommerce'); ?>
                </a>

                <button type="button" id="decline-quote-button" class="quote-action-button quote-reject-button">
                    <?php esc_html_e('Decline Quote', 'quote-manager-system-for-woocommerce'); ?>
                </button>
            </div>
            
            <div id="quote-reject-message" class="quote-message" style="display:none;margin-top:15px;"></div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Submit button (using AJAX)
    document.getElementById('decline-quote-button').addEventListener('click', function (e) {
        // Show loading state
        var button = this;
        var originalText = button.textContent;
        button.disabled = true;
        button.textContent = '<?php echo esc_js(__('Processing...', 'quote-manager-system-for-woocommerce')); ?>';
        
        // Show message
        var messageEl = document.getElementById('quote-reject-message');
        messageEl.style.display = 'block';
        messageEl.textContent = '<?php echo esc_js(__('Submitting your response. Please wait...', 'quote-manager-system-for-woocommerce')); ?>';
        messageEl.className = 'quote-message';

        // Get reason text
        var reason = document.getElementById('reject-reason').value;
        
        // Prepare form data
        var formData = new FormData();
        formData.append('action', 'quote_manager_reject');
        formData.append('nonce', '<?php echo esc_js($ajax_nonce); ?>');
        formData.append('quote_id', '<?php echo esc_js($quote_id); ?>');
        formData.append('token', '<?php echo esc_js($token); ?>');
        formData.append('reason', reason);

                // Send AJAX request
                var xhr = new XMLHttpRequest();
                xhr.open('POST', '<?php echo esc_js(admin_url('admin-ajax.php')); ?>', true);
                        xhr.onload = function () {
                            if (xhr.status >= 200 && xhr.status < 400) {
                                var response;
                                try {
                                    response = JSON.parse(xhr.responseText);
                                } catch (e) {
                                    console.error('Error parsing response:', e);
                                    messageEl.textContent = '<?php echo esc_js(__('An unexpected error occurred. Please try again.', 'quote-manager-system-for-woocommerce')); ?>';
                                    messageEl.className = 'quote-message error';
                                    button.disabled = false;
                                    button.textContent = originalText;
                                    return;
                                }
                                
                if (response.success && response.data && response.data.redirect) {
                    // Success - redirect to the success page
                    messageEl.textContent = '<?php echo esc_js(__('Quote accepted successfully! Redirecting...', 'quote-manager-system-for-woocommerce')); ?>';
                    messageEl.className = 'quote-message success';
                    
                    // Log the redirect URL for debugging
                    console.log('Redirecting to: ' + response.data.redirect);
                    
                    setTimeout(function() {
                        window.location.href = response.data.redirect;
                    }, 1000); // Short delay for user to see the success message
                } 
				else {
                    // Error
                    messageEl.textContent = response.data && response.data.message ? 
                        response.data.message : 
                        '<?php echo esc_js(__('An error occurred while submitting your response. Please try again.', 'quote-manager-system-for-woocommerce')); ?>';
                    messageEl.className = 'quote-message error';
                    button.disabled = false;
                    button.textContent = originalText;
                }
            } else {
                // HTTP error
                messageEl.textContent = '<?php echo esc_js(__('A server error occurred. Please try again later.', 'quote-manager-system-for-woocommerce')); ?>';
                messageEl.className = 'quote-message error';
                button.disabled = false;
                button.textContent = originalText;
            }
        };
        
        xhr.onerror = function() {
            // Network error
            messageEl.textContent = '<?php echo esc_js(__('A network error occurred. Please check your connection and try again.', 'quote-manager-system-for-woocommerce')); ?>';
            messageEl.className = 'quote-message error';
            button.disabled = false;
            button.textContent = originalText;
        };
        
        xhr.send(formData);
    });
});
</script>