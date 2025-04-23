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
    wp_die(__('Quote not found.', 'quote-manager-system-for-woocommerce'));
}

$customer_name = get_post_meta($quote_id, '_customer_first_name', true) . ' ' . get_post_meta($quote_id, '_customer_last_name', true);
$quote_number = '#' . str_pad($quote_id, 4, '0', STR_PAD_LEFT);

// Process any errors
$error_message = isset($error) ? $error : '';
?>

<div class="quote-reject-container">
    <div class="quote-reject-header">
        <h1><?php echo sprintf(__('Decline Quote %s', 'quote-manager-system-for-woocommerce'), $quote_number); ?></h1>
    </div>

    <div class="quote-reject-content">
        <p>
            <?php echo sprintf(
                __('Dear %s, you are about to decline quote %s. If you wish, you can provide a reason for declining this quote.', 'quote-manager-system-for-woocommerce'),
                '<strong>' . esc_html($customer_name) . '</strong>',
                '<strong>' . esc_html($quote_number) . '</strong>'
            ); ?>
        </p>

        <?php if (!empty($error_message)): ?>
            <div class="quote-form-error">
                <?php echo esc_html($error_message); ?>
            </div>
        <?php endif; ?>

        <form method="post" id="quote-reject-form">
            <?php wp_nonce_field('quote_reject_' . $quote_id, 'quote_reject_nonce'); ?>

            <div class="quote-form-section">
                <label for="reject-reason"><?php _e('Reason for declining (optional):', 'quote-manager-system-for-woocommerce'); ?></label>
                <textarea name="reject_reason" id="reject-reason" rows="5"
                          placeholder="<?php esc_attr_e('Please let us know why you are declining this quote...', 'quote-manager-system-for-woocommerce'); ?>"></textarea>
            </div>

            <div class="quote-form-actions">
                <a href="<?php echo esc_url(site_url("view-quote?id=$quote_id&token=$token")); ?>"
                   class="quote-action-button quote-action-secondary">
                    <?php _e('Cancel', 'quote-manager-system-for-woocommerce'); ?>
                </a>

                <button type="submit" name="quote_reject_submit" class="quote-action-button quote-reject-button">
                    <?php _e('Decline Quote', 'quote-manager-system-for-woocommerce'); ?>
                </button>
            </div>
        </form>
    </div>
</div>