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
    wp_die(__('Quote not found.', 'quote-manager-system-for-woocommerce'));
}

$customer_name = get_post_meta($quote_id, '_customer_first_name', true) . ' ' . get_post_meta($quote_id, '_customer_last_name', true);
$quote_number = '#' . str_pad($quote_id, 4, '0', STR_PAD_LEFT);

// Process any errors
$error_message = isset($error) ? $error : '';
?>

<div class="quote-accept-container">
    <div class="quote-accept-header">
        <h1><?php echo sprintf(__('Accept Quote %s', 'quote-manager-system-for-woocommerce'), $quote_number); ?></h1>
    </div>

    <div class="quote-accept-content">
        <p>
            <?php echo sprintf(
                __('Dear %s, you are about to accept quote %s. Please sign below to confirm your acceptance.', 'quote-manager-system-for-woocommerce'),
                '<strong>' . esc_html($customer_name) . '</strong>',
                '<strong>' . esc_html($quote_number) . '</strong>'
            ); ?>
        </p>

        <?php if (!empty($error_message)): ?>
            <div class="quote-form-error">
                <?php echo esc_html($error_message); ?>
            </div>
        <?php endif; ?>

        <form method="post" id="quote-accept-form">
            <?php wp_nonce_field('quote_accept_' . $quote_id, 'quote_accept_nonce'); ?>

            <div class="quote-form-section">
                <label for="signature-pad"><?php _e('Your Signature:', 'quote-manager-system-for-woocommerce'); ?></label>
                <div class="signature-pad-container">
                    <canvas id="signature-pad" width="600" height="200"></canvas>
                    <input type="hidden" name="signature" id="signature-data" required>
                </div>

                <div class="signature-controls">
                    <button type="button" id="clear-signature" class="quote-action-button quote-action-secondary">
                        <?php _e('Clear Signature', 'quote-manager-system-for-woocommerce'); ?>
                    </button>
                </div>
            </div>

            <div class="quote-form-actions">
                <a href="<?php echo esc_url(site_url("view-quote?id=$quote_id&token=$token")); ?>"
                   class="quote-action-button quote-action-secondary">
                    <?php _e('Cancel', 'quote-manager-system-for-woocommerce'); ?>
                </a>

                <button type="submit" name="quote_accept_submit" class="quote-action-button quote-accept-button">
                    <?php _e('Sign & Accept Quote', 'quote-manager-system-for-woocommerce'); ?>
                </button>
            </div>
        </form>
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

        // Form submission
        document.getElementById('quote-accept-form').addEventListener('submit', function (e) {
            if (signaturePad.isEmpty()) {
                e.preventDefault();
                alert('<?php echo esc_js(__('Please provide your signature to accept the quote.', 'quote-manager-system-for-woocommerce')); ?>');
                return false;
            }

            // Save signature data
            document.getElementById('signature-data').value = signaturePad.toDataURL();
        });
    });
</script>