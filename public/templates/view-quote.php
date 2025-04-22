<?php
/**
 * Template for viewing a quote
 *
 * @package    Quote_Manager_System_For_Woocommerce
 * @subpackage Quote_Manager_System_For_Woocommerce/public/templates
 */

// Security check
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get quote data
$quote = get_post($quote_id);
if (!$quote || $quote->post_type !== 'customer_quote') {
    wp_die(__('Quote not found.', 'quote-manager-system-for-woocommerce'));
}

$customer_name = get_post_meta($quote_id, '_customer_first_name', true) . ' ' . get_post_meta($quote_id, '_customer_last_name', true);
$quote_status = get_post_meta($quote_id, '_quote_status', true);
$expiration_date = get_post_meta($quote_id, '_quote_expiration_date', true);
$quote_products = get_post_meta($quote_id, '_quote_products', true);
$include_vat = get_post_meta($quote_id, '_quote_include_vat', true) === '1';

// Calculate total
$quote_total = 0;
if (is_array($quote_products)) {
    foreach ($quote_products as $product) {
        $price = isset($product['final_price_excl']) ? floatval($product['final_price_excl']) : 0;
        $qty = isset($product['qty']) ? intval($product['qty']) : 1;
        
        if ($include_vat) {
            // Add 24% VAT
            $price *= 1.24;
        }
        
        $quote_total += $price * $qty;
    }
}

// Format the quote number
$quote_number = '#' . str_pad($quote_id, 4, '0', STR_PAD_LEFT);

// Format the total
$formatted_total = number_format($quote_total, 2, '.', ',');

// Generate action URLs
$accept_url = add_query_arg(array(
    'id' => $quote_id,
    'token' => $token
), site_url('accept-quote'));

$reject_url = add_query_arg(array(
    'id' => $quote_id,
    'token' => $token
), site_url('reject-quote'));

// Get any messages
$message = isset($_GET['message']) ? sanitize_text_field($_GET['message']) : '';
?>

<div class="quote-view-container">
    <div class="quote-view-header">
        <h1><?php echo sprintf(__('Quote %s', 'quote-manager-system-for-woocommerce'), $quote_number); ?></h1>
        
        <?php if (!empty($message)): ?>
            <div class="quote-message">
                <?php echo esc_html($message); ?>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="quote-view-content">
        <div class="quote-view-info">
            <div class="quote-info-row">
                <div class="quote-info-label"><?php _e('Customer:', 'quote-manager-system-for-woocommerce'); ?></div>
                <div class="quote-info-value"><?php echo esc_html($customer_name); ?></div>
            </div>
            
            <div class="quote-info-row">
                <div class="quote-info-label"><?php _e('Date:', 'quote-manager-system-for-woocommerce'); ?></div>
                <div class="quote-info-value"><?php echo get_the_date('', $quote); ?></div>
            </div>
            
            <div class="quote-info-row">
                <div class="quote-info-label"><?php _e('Valid until:', 'quote-manager-system-for-woocommerce'); ?></div>
                <div class="quote-info-value"><?php echo esc_html($expiration_date); ?></div>
            </div>
            
            <div class="quote-info-row">
                <div class="quote-info-label"><?php _e('Total:', 'quote-manager-system-for-woocommerce'); ?></div>
                <div class="quote-info-value quote-total"><?php echo esc_html($formatted_total); ?> €</div>
            </div>
            
            <div class="quote-info-row">
                <div class="quote-info-label"><?php _e('Status:', 'quote-manager-system-for-woocommerce'); ?></div>
                <div class="quote-info-value quote-status quote-status-<?php echo esc_attr($quote_status); ?>">
                    <?php echo esc_html(Quote_Manager_System_For_Woocommerce::get_status_label($quote_status)); ?>
                </div>
            </div>
        </div>
        
        <div class="quote-view-actions">
            <p><?php _e('You can download the quote as a PDF, or respond to it using the buttons below.', 'quote-manager-system-for-woocommerce'); ?></p>
            
            <div class="quote-action-buttons">
                <a href="<?php echo esc_url(admin_url('admin-ajax.php?action=quote_download_pdf&quote_id=' . $quote_id)); ?>" class="quote-action-button quote-download-button">
                    <?php _e('Download PDF', 'quote-manager-system-for-woocommerce'); ?>
                </a>
                
                <?php if ($quote_status === Quote_Manager_System_For_Woocommerce::STATUS_DRAFT || $quote_status === Quote_Manager_System_For_Woocommerce::STATUS_SENT): ?>
                    <a href="<?php echo esc_url($accept_url); ?>" class="quote-action-button quote-accept-button">
                        <?php _e('Accept Quote', 'quote-manager-system-for-woocommerce'); ?>
                    </a>
                    
                    <a href="<?php echo esc_url($reject_url); ?>" class="quote-action-button quote-reject-button">
                        <?php _e('Decline Quote', 'quote-manager-system-for-woocommerce'); ?>
                    </a>
                <?php elseif ($quote_status === Quote_Manager_System_For_Woocommerce::STATUS_ACCEPTED): ?>
                    <div class="quote-action-notice quote-accepted-notice">
                        <?php _e('You have accepted this quote.', 'quote-manager-system-for-woocommerce'); ?>
                    </div>
                <?php elseif ($quote_status === Quote_Manager_System_For_Woocommerce::STATUS_REJECTED): ?>
                    <div class="quote-action-notice quote-rejected-notice">
                        <?php _e('You have declined this quote.', 'quote-manager-system-for-woocommerce'); ?>
                    </div>
                <?php elseif ($quote_status === Quote_Manager_System_For_Woocommerce::STATUS_EXPIRED): ?>
                    <div class="quote-action-notice quote-expired-notice">
                        <?php _e('This quote has expired.', 'quote-manager-system-for-woocommerce'); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>