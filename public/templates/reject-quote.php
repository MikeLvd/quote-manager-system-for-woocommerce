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
                        <span class="quote-button-icon">↩️</span>
                        <?php esc_html_e('Cancel', 'quote-manager-system-for-woocommerce'); ?>
                    </a>
                
                    <button type="button" id="decline-quote-button" class="quote-action-button quote-reject-button">
                        <span class="quote-button-icon">✗</span>
                        <?php esc_html_e('Decline Quote', 'quote-manager-system-for-woocommerce'); ?>
                    </button>
                </div>
            
            <div id="quote-reject-message" class="quote-message" style="display:none;margin-top:15px;"></div>
        </div>
    </div>
</div>