<?php
/**
 * Template for quote response confirmation
 *
 * @package    Quote_Manager_System_For_Woocommerce
 * @subpackage Quote_Manager_System_For_Woocommerce/public/templates
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Get parameters
$quote_id = isset($_GET['quote_id']) ? intval($_GET['quote_id']) : 0;
$status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';

// Validate quote
$quote = get_post($quote_id);
if (!$quote || $quote->post_type !== 'customer_quote') {
    wp_die(__('Quote not found.', 'quote-manager-system-for-woocommerce'));
}

$quote_number = '#' . str_pad($quote_id, 4, '0', STR_PAD_LEFT);
$customer_name = get_post_meta($quote_id, '_customer_first_name', true) . ' ' . get_post_meta($quote_id, '_customer_last_name', true);

// Determine message based on status
if ($status === 'accepted') {
    $title = __('Quote Accepted', 'quote-manager-system-for-woocommerce');
    $message = sprintf(
        __('Thank you, %s! Your acceptance of quote %s has been recorded. We have sent you a confirmation email with the signed quote for your records. We will be in touch shortly to discuss the next steps.', 'quote-manager-system-for-woocommerce'),
        '<strong>' . esc_html($customer_name) . '</strong>',
        '<strong>' . esc_html($quote_number) . '</strong>'
    );
    $icon_class = 'quote-response-icon-accepted';
    $icon = '✓';
} elseif ($status === 'rejected') {
    $title = __('Quote Declined', 'quote-manager-system-for-woocommerce');
    $message = sprintf(
        __('Thank you, %s. We have recorded your decision to decline quote %s. If you would like to discuss this further or have any questions, please don\'t hesitate to contact us.', 'quote-manager-system-for-woocommerce'),
        '<strong>' . esc_html($customer_name) . '</strong>',
        '<strong>' . esc_html($quote_number) . '</strong>'
    );
    $icon_class = 'quote-response-icon-rejected';
    $icon = '✗';
} else {
    $title = __('Quote Response', 'quote-manager-system-for-woocommerce');
    $message = __('Your response has been recorded.', 'quote-manager-system-for-woocommerce');
    $icon_class = 'quote-response-icon-default';
    $icon = '!';
}
?>

<div class="quote-response-container">
    <div class="quote-response-content">
        <div class="quote-response-icon <?php echo esc_attr($icon_class); ?>">
            <?php echo esc_html($icon); ?>
        </div>
        
        <h1 class="quote-response-title"><?php echo esc_html($title); ?></h1>
        
        <div class="quote-response-message">
            <?php echo wp_kses_post($message); ?>
        </div>
        
        <div class="quote-response-actions">
            <a href="<?php echo esc_url(home_url()); ?>" class="quote-action-button quote-action-secondary">
                <?php _e('Return to Homepage', 'quote-manager-system-for-woocommerce'); ?>
            </a>
        </div>
    </div>
</div>