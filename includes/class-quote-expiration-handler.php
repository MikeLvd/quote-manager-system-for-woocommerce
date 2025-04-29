<?php
/**
 * Handle quote expiration checks and updates
 *
 * @link       https://goldenbath.gr
 * @since      1.0.0
 *
 * @package    Quote_Manager_System_For_Woocommerce
 * @subpackage Quote_Manager_System_For_Woocommerce/includes
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class Quote_Manager_Expiration_Handler
{
    /**
     * Initialize the class
     */
    public function __construct()
    {
        // Hook into the daily expiration check event
        add_action('quote_manager_daily_expiration_check', array($this, 'check_expired_quotes'));
    }

    /**
     * Check for expired quotes and update their status
     * 
     * @return int Number of quotes marked as expired
     */
    public function check_expired_quotes()
    {
        // Get current date in YYYY-MM-DD format for comparison
        $current_date = current_time('Y-m-d');
        $expired_count = 0;

        // Get quotes that are draft or sent and have an expiration date
        $args = array(
            'post_type' => 'customer_quote',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_quote_status',
                    'value' => array(
                        Quote_Manager_System_For_Woocommerce::STATUS_DRAFT, 
                        Quote_Manager_System_For_Woocommerce::STATUS_SENT
                    ),
                    'compare' => 'IN'
                ),
                array(
                    'key' => '_quote_expiration_date',
                    'compare' => 'EXISTS'
                )
            )
        );

        $quotes = get_posts($args);

        foreach ($quotes as $quote) {
            $expiration_date = get_post_meta($quote->ID, '_quote_expiration_date', true);
            
            if (empty($expiration_date)) {
                continue;
            }

            // Convert DD/MM/YYYY to YYYY-MM-DD for comparison
            $expiration_parts = explode('/', $expiration_date);
            if (count($expiration_parts) !== 3) {
                continue;
            }
            
            $expiration_formatted = sprintf('%04d-%02d-%02d', 
                intval($expiration_parts[2]), 
                intval($expiration_parts[1]), 
                intval($expiration_parts[0])
            );

            // Check if expired
            if ($expiration_formatted < $current_date) {
                // Update status to expired
                update_post_meta($quote->ID, '_quote_status', Quote_Manager_System_For_Woocommerce::STATUS_EXPIRED);
                
                // Log the expiration
                $this->log_expiration($quote->ID, $expiration_date);
                
                // Send notification email to customer
                $this->send_expiration_notification($quote->ID, $expiration_date);
                
                $expired_count++;
            }
        }

        // Save the last check time
        update_option('quote_manager_last_expiration_check', current_time('mysql'));
        
        // Save the number of quotes expired in this check
        if ($expired_count > 0) {
            update_option('quote_manager_last_expired_count', $expired_count);
        }
        
        return $expired_count;
    }
    
    /**
     * Log when a quote is marked as expired
     * 
     * @param int $quote_id Quote ID
     * @param string $expiration_date Original expiration date
     */
    private function log_expiration($quote_id, $expiration_date)
    {
        // Get existing logs
        $logs = get_post_meta($quote_id, '_quote_status_logs', true);
        if (!is_array($logs)) {
            $logs = array();
        }
        
        // Add expiration entry
        $logs[] = array(
            'datetime' => current_time('mysql'),
            'status' => Quote_Manager_System_For_Woocommerce::STATUS_EXPIRED,
            'expiration_date' => $expiration_date,
            'message' => __('Quote automatically marked as expired.', 'quote-manager-system-for-woocommerce')
        );
        
        // Update logs
        update_post_meta($quote_id, '_quote_status_logs', $logs);
    }
    
    /**
     * Send notification email to customer when quote expires
     * 
     * @param int $quote_id Quote ID
     * @param string $expiration_date Original expiration date
     * @return bool Whether the email was sent successfully
     */
    private function send_expiration_notification($quote_id, $expiration_date)
    {
        // Get customer email
        $customer_email = get_post_meta($quote_id, '_customer_email', true);
        if (empty($customer_email) || !is_email($customer_email)) {
            // No valid email address
            return false;
        }
        
        // Check if an expiration notification was already sent
        $notification_sent = get_post_meta($quote_id, '_expiration_notification_sent', true);
        if ($notification_sent === 'yes') {
            // Notification already sent
            return false;
        }
        
        // Get customer details
        $customer_first_name = get_post_meta($quote_id, '_customer_first_name', true);
        $customer_name = $customer_first_name;
        if (empty($customer_first_name)) {
            $customer_name = __('Customer', 'quote-manager-system-for-woocommerce');
        }
        
        // Format the quote number
        $quote_number = '#' . str_pad($quote_id, 4, '0', STR_PAD_LEFT);
        
        // Prepare email subject
        $subject = sprintf(
            __('Your Quote %s has expired', 'quote-manager-system-for-woocommerce'),
            $quote_number
        );
        
        // Get company details from settings
        $company_name = get_option('quote_manager_company_name', get_bloginfo('name'));
        
        // Prepare email content
        $message = '<div style="font-family: Arial, sans-serif; line-height: 1.6;">';
        $message .= '<p>' . sprintf(
            __('Dear %s,', 'quote-manager-system-for-woocommerce'),
            '<strong>' . esc_html($customer_name) . '</strong>'
        ) . '</p>';
        
        $message .= '<p>' . sprintf(
            __('We wanted to inform you that quote %s, which was valid until %s, has now expired.', 'quote-manager-system-for-woocommerce'),
            '<strong>' . esc_html($quote_number) . '</strong>',
            '<strong>' . esc_html($expiration_date) . '</strong>'
        ) . '</p>';
        
        $message .= '<p>' . __('If you are still interested in our products or services, please don\'t hesitate to contact us for a new quote.', 'quote-manager-system-for-woocommerce') . '</p>';
        
        $message .= '<p>' . __('Thank you for your interest in our company.', 'quote-manager-system-for-woocommerce') . '</p>';
        
        $message .= '<p>' . __('Best regards,', 'quote-manager-system-for-woocommerce') . '<br>';
        $message .= esc_html($company_name) . '</p>';
        $message .= '</div>';
        
        // Email headers
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $company_name . ' <' . get_option('admin_email') . '>'
        );
        
        // Send the email
        $sent = wp_mail($customer_email, $subject, $message, $headers);
        
        // Log the email in quote email logs
        if ($sent) {
            // Mark notification as sent
            update_post_meta($quote_id, '_expiration_notification_sent', 'yes');
            
            // Add to email logs
            $logs = get_post_meta($quote_id, '_quote_email_logs', true);
            if (!is_array($logs)) {
                $logs = array();
            }
            
            $logs[] = array(
                'datetime' => current_time('mysql'),
                'to' => $customer_email,
                'subject' => $subject,
                'message' => $message,
                'result' => 'success',
                'opened_at' => '', // Will be updated if opened
                'type' => 'expiration_notification'
            );
            
            update_post_meta($quote_id, '_quote_email_logs', $logs);
        }
        
        return $sent;
    }
}