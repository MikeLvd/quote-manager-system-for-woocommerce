<?php
/**
 * Template for accepting a quote
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
$ajax_nonce = wp_create_nonce('quote_accept_nonce');
?>

<div class="quote-accept-container">
    <div class="quote-accept-header">
        <h1><?php echo sprintf(esc_html__('Accept Quote %s', 'quote-manager-system-for-woocommerce'), esc_html($quote_number)); ?></h1>
    </div>

    <div class="quote-accept-content">
        <p>
            <?php echo sprintf(
                esc_html__('Dear %s, you are about to accept quote %s. Please sign below to confirm your acceptance.', 'quote-manager-system-for-woocommerce'),
                '<strong>' . esc_html($customer_name) . '</strong>',
                '<strong>' . esc_html($quote_number) . '</strong>'
            ); ?>
        </p>

        <?php if (!empty($error_message)): ?>
            <div class="quote-form-error">
                <?php echo esc_html($error_message); ?>
            </div>
        <?php endif; ?>

        <div id="quote-accept-form-container">
            <div class="quote-form-section">
                <label for="signature-pad"><?php esc_html_e('Your Signature:', 'quote-manager-system-for-woocommerce'); ?></label>
                <div class="signature-pad-container">
                    <canvas id="signature-pad" width="600" height="200"></canvas>
                    <input type="hidden" name="signature" id="signature-data" required>
                </div>

                <div class="signature-controls">
                    <button type="button" id="clear-signature" class="quote-action-button quote-action-secondary">
                        <?php esc_html_e('Clear Signature', 'quote-manager-system-for-woocommerce'); ?>
                    </button>
                </div>
            </div>

            <div class="quote-form-actions">
                <a href="<?php echo esc_url($view_url); ?>"
                   class="quote-action-button quote-action-secondary">
                    <?php esc_html_e('Cancel', 'quote-manager-system-for-woocommerce'); ?>
                </a>

                <button type="button" id="sign-accept-button" class="quote-action-button quote-accept-button">
                    <?php esc_html_e('Sign & Accept Quote', 'quote-manager-system-for-woocommerce'); ?>
                </button>
            </div>
            
            <div id="quote-accept-message" class="quote-message" style="display:none;margin-top:15px;"></div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var canvas = document.getElementById('signature-pad');
    var signaturePad = new SignaturePad(canvas, {
        backgroundColor: 'rgb(255, 255, 255)',
        penColor: 'rgb(0, 0, 0)'
    });

    // Handle window resize
    function resizeCanvas() {
        var ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        canvas.getContext("2d").scale(ratio, ratio);
        signaturePad.clear(); // Otherwise isEmpty() might return incorrect value
    }

    window.addEventListener("resize", resizeCanvas);
    resizeCanvas();

    // Clear button
    document.getElementById('clear-signature').addEventListener('click', function () {
        signaturePad.clear();
    });

    // Submit button (using AJAX)
    document.getElementById('sign-accept-button').addEventListener('click', function (e) {
        if (signaturePad.isEmpty()) {
            alert('<?php echo esc_js(__('Please provide your signature to accept the quote.', 'quote-manager-system-for-woocommerce')); ?>');
            return false;
        }

        // Show loading state
        var button = this;
        var originalText = button.textContent;
        button.disabled = true;
        button.textContent = '<?php echo esc_js(__('Processing...', 'quote-manager-system-for-woocommerce')); ?>';
        
        // Show message
        var messageEl = document.getElementById('quote-accept-message');
        messageEl.style.display = 'block';
        messageEl.textContent = '<?php echo esc_js(__('Submitting your acceptance. Please wait...', 'quote-manager-system-for-woocommerce')); ?>';
        messageEl.className = 'quote-message';

        // Get signature data
        var signatureData = signaturePad.toDataURL();
        
        // Prepare form data
        var formData = new FormData();
        formData.append('action', 'quote_manager_accept');
        formData.append('nonce', '<?php echo esc_js($ajax_nonce); ?>');
        formData.append('quote_id', '<?php echo esc_js($quote_id); ?>');
        formData.append('token', '<?php echo esc_js($token); ?>');
        formData.append('signature', signatureData);

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
                        '<?php echo esc_js(__('An error occurred while submitting your acceptance. Please try again.', 'quote-manager-system-for-woocommerce')); ?>';
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