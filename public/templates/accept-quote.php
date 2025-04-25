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
                        <span class="quote-button-icon">🗑️</span>
                        <?php esc_html_e('Clear Signature', 'quote-manager-system-for-woocommerce'); ?>
                    </button>
                </div>
                
                <div class="quote-form-actions">
                    <a href="<?php echo esc_url($view_url); ?>"
                       class="quote-action-button quote-action-secondary">
                        <span class="quote-button-icon">↩️</span>
                        <?php esc_html_e('Cancel', 'quote-manager-system-for-woocommerce'); ?>
                    </a>
                
                    <button type="button" id="sign-accept-button" class="quote-action-button quote-accept-button">
                        <span class="quote-button-icon">✓</span>
                        <?php esc_html_e('Sign & Accept', 'quote-manager-system-for-woocommerce'); ?>
                    </button>
                </div>
            
            <div id="quote-accept-message" class="quote-message" style="display:none;margin-top:15px;"></div>
        </div>
    </div>
</div>