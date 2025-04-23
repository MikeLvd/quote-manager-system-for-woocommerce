<?php
/**
 * Handle customer responses to quotes (accept/reject)
 *
 * @link       https://goldenbath.gr
 * @since      1.6.0
 *
 * @package    Quote_Manager_System_For_Woocommerce
 * @subpackage Quote_Manager_System_For_Woocommerce/public
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class Quote_Manager_Response_Handler
{

    /**
     * Initialize the class
     */
    public function __construct()
    {
        add_action('init', array($this, 'register_endpoints'));
        add_action('template_redirect', array($this, 'handle_quote_response'));
    }

    /**
     * Register custom endpoints for quote responses
     */
    public function register_endpoints()
    {
        // Register the endpoint for viewing a quote
        add_rewrite_endpoint('view-quote', EP_ROOT);

        // Register the endpoint for accepting a quote
        add_rewrite_endpoint('accept-quote', EP_ROOT);

        // Register the endpoint for rejecting a quote
        add_rewrite_endpoint('reject-quote', EP_ROOT);

        // Always flush rewrite rules on endpoint registration
        flush_rewrite_rules(false);
    }

    /**
     * Handle quote response requests
     */
    public function handle_quote_response()
    {
        global $wp;

        // Check if we're on our custom endpoints
        if (isset($wp->query_vars['view-quote'])) {
            $this->handle_view_quote();
            exit;
        }

        if (isset($wp->query_vars['accept-quote'])) {
            $this->handle_accept_quote();
            exit;
        }

        if (isset($wp->query_vars['reject-quote'])) {
            $this->handle_reject_quote();
            exit;
        }
    }

    /**
     * Handle view quote request
     */
    private function handle_view_quote()
    {
        // Get parameters
        $quote_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';

        // Validate quote
        if (!$this->validate_quote_access($quote_id, $token)) {
            wp_die(__('Invalid quote access token. Please contact us for assistance.', 'quote-manager-system-for-woocommerce'));
        }

        // Get template
        $this->load_template('view-quote', array(
            'quote_id' => $quote_id,
            'token' => $token
        ));
    }

    /**
     * Validate quote access token
     *
     * @param int $quote_id Quote ID
     * @param string $token Access token
     * @return bool Whether access is valid
     */
    private function validate_quote_access($quote_id, $token)
    {
        if (empty($quote_id) || empty($token)) {
            return false;
        }

        // Get the quote
        $quote = get_post($quote_id);
        if (!$quote || $quote->post_type !== 'customer_quote') {
            return false;
        }

        // Check if quote has expired
        $expiration_date = get_post_meta($quote_id, '_quote_expiration_date', true);
        if (!empty($expiration_date)) {
            $expiry_timestamp = strtotime(str_replace('/', '-', $expiration_date));
            $current_timestamp = current_time('timestamp');

            if ($expiry_timestamp < $current_timestamp) {
                // Quote has expired - update status if not already expired
                $current_status = get_post_meta($quote_id, '_quote_status', true);
                if ($current_status !== Quote_Manager_System_For_Woocommerce::STATUS_EXPIRED) {
                    update_post_meta($quote_id, '_quote_status', Quote_Manager_System_For_Woocommerce::STATUS_EXPIRED);
                }

                return false;
            }
        }

        // Check if the token matches
        $saved_token = get_post_meta($quote_id, '_quote_access_token', true);
        if (empty($saved_token)) {
            // Generate a new token if none exists
            $saved_token = $this->generate_quote_token($quote_id);
        }

        return hash_equals($saved_token, $token);
    }

    /**
     * Generate a secure token for quote access
     *
     * @param int $quote_id Quote ID
     * @return string Generated token
     */
    private function generate_quote_token($quote_id)
    {
        // Generate a random token
        $token = bin2hex(random_bytes(16));

        // Save it for this quote
        update_post_meta($quote_id, '_quote_access_token', $token);

        return $token;
    }

    /**
     * Load a template file
     *
     * @param string $template Template name
     * @param array $vars Variables to extract into template
     */
    private function load_template($template, $vars = array())
    {
        // Extract variables to use in template
        extract($vars);

        // Set up template path
        $template_path = plugin_dir_path(dirname(__FILE__)) . 'public/templates/' . $template . '.php';

        // Check if template exists
        if (!file_exists($template_path)) {
            wp_die(sprintf(
                __('Template file %s not found.', 'quote-manager-system-for-woocommerce'),
                $template
            ));
        }

        // Include header
        get_header();

        // Load template
        include $template_path;

        // Include footer
        get_footer();
    }

    /**
     * Handle accept quote request
     */
    private function handle_accept_quote()
    {
        // Get parameters
        $quote_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';

        // Validate quote
        if (!$this->validate_quote_access($quote_id, $token)) {
            wp_die(__('Invalid quote access token. Please contact us for assistance.', 'quote-manager-system-for-woocommerce'));
        }

        // Check if form was submitted
        if (isset($_POST['quote_accept_submit'])) {
            // Verify nonce
            if (!isset($_POST['quote_accept_nonce']) || !wp_verify_nonce($_POST['quote_accept_nonce'], 'quote_accept_' . $quote_id)) {
                wp_die(__('Security check failed. Please try again.', 'quote-manager-system-for-woocommerce'));
            }

            // Process form submission
            $signature = isset($_POST['signature']) ? sanitize_text_field($_POST['signature']) : '';

            if (empty($signature)) {
                // Show form again with error
                $this->load_template('accept-quote', array(
                    'quote_id' => $quote_id,
                    'token' => $token,
                    'error' => __('Please provide your signature to accept the quote.', 'quote-manager-system-for-woocommerce')
                ));
                exit;
            }

            // Save the signature
            update_post_meta($quote_id, '_customer_signature', $signature);
            update_post_meta($quote_id, '_quote_status', Quote_Manager_System_For_Woocommerce::STATUS_ACCEPTED);
            update_post_meta($quote_id, '_quote_accepted_date', current_time('mysql'));

            // Generate PDF with signature
            $this->generate_signed_pdf($quote_id);

            // Send notifications
            $this->send_quote_accepted_notifications($quote_id);

            // Redirect to thank you page
            $redirect_url = add_query_arg(array(
                'quote_id' => $quote_id,
                'status' => 'accepted'
            ), home_url('quote-response'));

            wp_redirect($redirect_url);
            exit;
        }

        // Show the acceptance form
        $this->load_template('accept-quote', array(
            'quote_id' => $quote_id,
            'token' => $token
        ));
    }

    /**
     * Generate signed PDF with customer signature
     *
     * @param int $quote_id Quote ID
     */
    private function generate_signed_pdf($quote_id)
    {
        // Load PDF generator
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-pdf-generator-raw.php';

        // Get signature data
        $signature = get_post_meta($quote_id, '_customer_signature', true);

        // Set a flag to include signature in the PDF
        update_post_meta($quote_id, '_include_signature_in_pdf', '1');

        // Generate PDF
        $pdf_generator = new Quote_Manager_PDF_Generator_Raw();
        $pdf_output = $pdf_generator->generate($quote_id);

        // Remove the flag
        delete_post_meta($quote_id, '_include_signature_in_pdf');

        // Define file paths
        $upload_dir = wp_upload_dir();
        $pdf_dir = $upload_dir['basedir'] . '/quote-manager/quotes/';
        $original_pdf_path = $pdf_dir . 'PROSFORA_' . $quote_id . '.pdf';

        // Override the original PDF with the signed version
        file_put_contents($original_pdf_path, $pdf_output);

        // Store the path in meta
        update_post_meta($quote_id, '_signed_quote_pdf_path', $original_pdf_path);

        // Add a flag to indicate this quote has been signed
        update_post_meta($quote_id, '_quote_pdf_is_signed', '1');
    }

    /**
     * Send notifications when a quote is accepted
     *
     * @param int $quote_id Quote ID
     */
    private function send_quote_accepted_notifications($quote_id)
    {
        // Get quote data
        $quote = get_post($quote_id);
        $customer_name = get_post_meta($quote_id, '_customer_first_name', true) . ' ' . get_post_meta($quote_id, '_customer_last_name', true);
        $customer_email = get_post_meta($quote_id, '_customer_email', true);

        // Get signed PDF path
        $signed_pdf_path = get_post_meta($quote_id, '_signed_quote_pdf_path', true);

        // Prepare emails
        $subject = sprintf(
            __('Quote #%s has been accepted', 'quote-manager-system-for-woocommerce'),
            str_pad($quote_id, 4, '0', STR_PAD_LEFT)
        );

        // Customer email
        $customer_message = sprintf(
            __('Dear %s,<br><br>Thank you for accepting our quote. We have attached a copy of the signed quote for your records.<br><br>We will be in touch shortly to discuss the next steps.<br><br>Best regards,<br>%s', 'quote-manager-system-for-woocommerce'),
            esc_html($customer_name),
            esc_html(get_bloginfo('name'))
        );

        // Admin email
        $admin_email = get_option('admin_email');
        $admin_message = sprintf(
            __('Quote #%s has been accepted by %s.<br><br>A copy of the signed quote is attached. Please follow up with the customer to discuss the next steps.<br><br>View the quote: %s', 'quote-manager-system-for-woocommerce'),
            str_pad($quote_id, 4, '0', STR_PAD_LEFT),
            esc_html($customer_name),
            admin_url('post.php?post=' . $quote_id . '&action=edit')
        );

        // Send customer email
        $this->send_email(
            $customer_email,
            $subject,
            $customer_message,
            array($signed_pdf_path)
        );

        // Send admin email
        $this->send_email(
            $admin_email,
            $subject . ' - ' . __('ADMIN NOTIFICATION', 'quote-manager-system-for-woocommerce'),
            $admin_message,
            array($signed_pdf_path)
        );

        // Log the emails
        $this->log_email_event($quote_id, 'accepted', $customer_email);
    }

    /**
     * Send an email with attachments
     *
     * @param string $to Email recipient
     * @param string $subject Email subject
     * @param string $message Email message
     * @param array $attachments Email attachments
     * @return bool Whether the email was sent
     */
    private function send_email($to, $subject, $message, $attachments = array())
    {
        $headers = array('Content-Type: text/html; charset=UTF-8');

        return wp_mail($to, $subject, $message, $headers, $attachments);
    }

    /**
     * Log an email event
     *
     * @param int $quote_id Quote ID
     * @param string $event Event type (accepted/rejected)
     * @param string $email_to Email recipient
     */
    private function log_email_event($quote_id, $event, $email_to)
    {
        $logs = get_post_meta($quote_id, '_quote_email_logs', true);
        if (!is_array($logs)) {
            $logs = array();
        }

        $logs[] = array(
            'datetime' => current_time('mysql'),
            'to' => $email_to,
            'subject' => sprintf(
                __('Quote %s notification', 'quote-manager-system-for-woocommerce'),
                $event
            ),
            'message' => sprintf(
                __('Quote has been %s by the customer.', 'quote-manager-system-for-woocommerce'),
                $event
            ),
            'result' => 'success',
            'opened_at' => current_time('mysql')
        );

        update_post_meta($quote_id, '_quote_email_logs', $logs);
    }

    /**
     * Handle reject quote request
     */
    private function handle_reject_quote()
    {
        // Get parameters
        $quote_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';

        // Validate quote
        if (!$this->validate_quote_access($quote_id, $token)) {
            wp_die(__('Invalid quote access token. Please contact us for assistance.', 'quote-manager-system-for-woocommerce'));
        }

        // Check if form was submitted
        if (isset($_POST['quote_reject_submit'])) {
            // Verify nonce
            if (!isset($_POST['quote_reject_nonce']) || !wp_verify_nonce($_POST['quote_reject_nonce'], 'quote_reject_' . $quote_id)) {
                wp_die(__('Security check failed. Please try again.', 'quote-manager-system-for-woocommerce'));
            }

            // Process form submission
            $reason = isset($_POST['reject_reason']) ? sanitize_textarea_field($_POST['reject_reason']) : '';

            // Save the reason
            update_post_meta($quote_id, '_quote_rejection_reason', $reason);
            update_post_meta($quote_id, '_quote_status', Quote_Manager_System_For_Woocommerce::STATUS_REJECTED);
            update_post_meta($quote_id, '_quote_rejected_date', current_time('mysql'));

            // Delete PDF files for rejected quote
            Quote_Manager_System_For_Woocommerce::delete_quote_pdf_files($quote_id);

            // Send notifications
            $this->send_quote_rejected_notifications($quote_id);

            // Redirect to thank you page
            $redirect_url = add_query_arg(array(
                'quote_id' => $quote_id,
                'status' => 'rejected'
            ), home_url('quote-response'));

            wp_redirect($redirect_url);
            exit;
        }

        // Show the rejection form
        $this->load_template('reject-quote', array(
            'quote_id' => $quote_id,
            'token' => $token
        ));
    }

    /**
     * Send notifications when a quote is rejected
     *
     * @param int $quote_id Quote ID
     */
    private function send_quote_rejected_notifications($quote_id)
    {
        // Get quote data
        $quote = get_post($quote_id);
        $customer_name = get_post_meta($quote_id, '_customer_first_name', true) . ' ' . get_post_meta($quote_id, '_customer_last_name', true);
        $customer_email = get_post_meta($quote_id, '_customer_email', true);
        $rejection_reason = get_post_meta($quote_id, '_quote_rejection_reason', true);

        // Prepare emails
        $subject = sprintf(
            __('Quote #%s has been declined', 'quote-manager-system-for-woocommerce'),
            str_pad($quote_id, 4, '0', STR_PAD_LEFT)
        );

        // Customer email
        $customer_message = sprintf(
            __('Dear %s,<br><br>We have received your decision to decline our quote. If you would like to discuss this further or have any questions, please don\'t hesitate to contact us.<br><br>Best regards,<br>%s', 'quote-manager-system-for-woocommerce'),
            esc_html($customer_name),
            esc_html(get_bloginfo('name'))
        );

        // Admin email
        $admin_email = get_option('admin_email');
        $admin_message = sprintf(
            __('Quote #%s has been declined by %s.<br><br>Reason given: %s<br><br>View the quote: %s', 'quote-manager-system-for-woocommerce'),
            str_pad($quote_id, 4, '0', STR_PAD_LEFT),
            esc_html($customer_name),
            !empty($rejection_reason) ? esc_html($rejection_reason) : __('No reason provided', 'quote-manager-system-for-woocommerce'),
            admin_url('post.php?post=' . $quote_id . '&action=edit')
        );

        // Send customer email
        $this->send_email(
            $customer_email,
            $subject,
            $customer_message
        );

        // Send admin email
        $this->send_email(
            $admin_email,
            $subject . ' - ' . __('ADMIN NOTIFICATION', 'quote-manager-system-for-woocommerce'),
            $admin_message
        );

        // Log the emails
        $this->log_email_event($quote_id, 'rejected', $customer_email);
    }

    /**
     * Get a quote token, generating one if needed
     *
     * @param int $quote_id Quote ID
     * @return string Quote token
     */
    public function get_quote_token($quote_id)
    {
        $token = get_post_meta($quote_id, '_quote_access_token', true);

        if (empty($token)) {
            $token = $this->generate_quote_token($quote_id);
        }

        return $token;
    }
}