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
        // Register shortcode instead of custom endpoints
        add_shortcode('quote_manager', array($this, 'quote_manager_shortcode'));
        
        // Handle AJAX form submissions
        add_action('wp_ajax_quote_manager_accept', array($this, 'ajax_accept_quote'));
        add_action('wp_ajax_nopriv_quote_manager_accept', array($this, 'ajax_accept_quote'));
        
        add_action('wp_ajax_quote_manager_reject', array($this, 'ajax_reject_quote'));
        add_action('wp_ajax_nopriv_quote_manager_reject', array($this, 'ajax_reject_quote'));
        
        // Add proper titles and meta tags to quote pages
        add_filter('document_title_parts', array($this, 'modify_document_title'), 10);
        add_filter('wpseo_title', array($this, 'modify_yoast_title'), 10);
        add_filter('wpseo_canonical', array($this, 'modify_canonical_url'), 10);
        add_filter('wpseo_opengraph_url', array($this, 'modify_opengraph_url'), 10);
    }
    
    /**
     * Modify document title for quote pages
     */
    public function modify_document_title($title_parts)
    {
        global $wp;
        
        if (isset($_GET['view']) && $_GET['view'] === 'quote' && isset($_GET['id'])) {
            $quote_id = intval($_GET['id']);
            $title_parts['title'] = sprintf(
                __('Quote #%s', 'quote-manager-system-for-woocommerce'),
                str_pad($quote_id, 4, '0', STR_PAD_LEFT)
            );
        } elseif (isset($_GET['view']) && $_GET['view'] === 'accept' && isset($_GET['id'])) {
            $quote_id = intval($_GET['id']);
            $title_parts['title'] = sprintf(
                __('Accept Quote #%s', 'quote-manager-system-for-woocommerce'),
                str_pad($quote_id, 4, '0', STR_PAD_LEFT)
            );
        } elseif (isset($_GET['view']) && $_GET['view'] === 'reject' && isset($_GET['id'])) {
            $quote_id = intval($_GET['id']);
            $title_parts['title'] = sprintf(
                __('Decline Quote #%s', 'quote-manager-system-for-woocommerce'),
                str_pad($quote_id, 4, '0', STR_PAD_LEFT)
            );
        } elseif (isset($_GET['view']) && $_GET['view'] === 'response') {
            if (isset($_GET['status']) && $_GET['status'] === 'accepted') {
                $title_parts['title'] = __('Quote Accepted', 'quote-manager-system-for-woocommerce');
            } elseif (isset($_GET['status']) && $_GET['status'] === 'rejected') {
                $title_parts['title'] = __('Quote Declined', 'quote-manager-system-for-woocommerce');
            } else {
                $title_parts['title'] = __('Quote Response', 'quote-manager-system-for-woocommerce');
            }
        }
        
        return $title_parts;
    }
    
    /**
     * Modify Yoast SEO title if installed
     */
    public function modify_yoast_title($title)
    {
        global $wp;
        
        if (isset($_GET['view']) && $_GET['view'] === 'quote' && isset($_GET['id'])) {
            $quote_id = intval($_GET['id']);
            return sprintf(
                __('Quote #%s - %s', 'quote-manager-system-for-woocommerce'),
                str_pad($quote_id, 4, '0', STR_PAD_LEFT),
                get_bloginfo('name')
            );
        } elseif (isset($_GET['view']) && $_GET['view'] === 'accept' && isset($_GET['id'])) {
            $quote_id = intval($_GET['id']);
            return sprintf(
                __('Accept Quote #%s - %s', 'quote-manager-system-for-woocommerce'),
                str_pad($quote_id, 4, '0', STR_PAD_LEFT),
                get_bloginfo('name')
            );
        } elseif (isset($_GET['view']) && $_GET['view'] === 'reject' && isset($_GET['id'])) {
            $quote_id = intval($_GET['id']);
            return sprintf(
                __('Decline Quote #%s - %s', 'quote-manager-system-for-woocommerce'),
                str_pad($quote_id, 4, '0', STR_PAD_LEFT),
                get_bloginfo('name')
            );
        } elseif (isset($_GET['view']) && $_GET['view'] === 'response') {
            if (isset($_GET['status']) && $_GET['status'] === 'accepted') {
                return __('Quote Accepted - ', 'quote-manager-system-for-woocommerce') . get_bloginfo('name');
            } elseif (isset($_GET['status']) && $_GET['status'] === 'rejected') {
                return __('Quote Declined - ', 'quote-manager-system-for-woocommerce') . get_bloginfo('name');
            } else {
                return __('Quote Response - ', 'quote-manager-system-for-woocommerce') . get_bloginfo('name');
            }
        }
        
        return $title;
    }
    
    /**
     * Modify canonical URL for quote pages to prevent blog URLs
     */
    public function modify_canonical_url($canonical)
    {
        if (isset($_GET['view']) && in_array($_GET['view'], array('quote', 'accept', 'reject', 'response'))) {
            // Use the current page URL as canonical
            global $wp;
            $current_url = home_url(add_query_arg(array(), $wp->request));
            return $current_url;
        }
        
        return $canonical;
    }
    
    /**
     * Modify Open Graph URL for quote pages
     */
    public function modify_opengraph_url($url)
    {
        if (isset($_GET['view']) && in_array($_GET['view'], array('quote', 'accept', 'reject', 'response'))) {
            // Use the current page URL for Open Graph
            global $wp;
            $current_url = home_url(add_query_arg(array(), $wp->request));
            return $current_url;
        }
        
        return $url;
    }

    /**
     * Main shortcode function to handle quote views
     */
    public function quote_manager_shortcode($atts)
    {
        // Extract attributes
        $atts = shortcode_atts(array(
            'default_view' => 'response', // Default view if not specified
        ), $atts, 'quote_manager');
        
        // Buffer output to return as a string
        ob_start();
        
        // Determine which view to show based on URL parameters
        $view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : $atts['default_view'];
        
        // Process the view
        switch ($view) {
            case 'quote':
                $this->render_view_quote();
                break;
                
            case 'accept':
                $this->render_accept_quote();
                break;
                
            case 'reject':
                $this->render_reject_quote();
                break;
                
            case 'response':
            default:
                $this->render_quote_response();
                break;
        }
        
        // Return the buffered content
        return ob_get_clean();
    }
    
    /**
     * Render view quote page
     */
    private function render_view_quote()
    {
        // Get parameters
        $quote_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
        
        // Validate quote access
        if (!$this->validate_quote_access($quote_id, $token)) {
            echo '<div class="quote-error-message">';
            echo '<p>' . esc_html__('Invalid quote access token. Please contact us for assistance.', 'quote-manager-system-for-woocommerce') . '</p>';
            echo '</div>';
            return;
        }
        
        // Output the template
        $this->load_template('view-quote', array(
            'quote_id' => $quote_id,
            'token' => $token
        ));
    }
    
    /**
     * Render accept quote form
     */
    private function render_accept_quote()
    {
        // Get parameters
        $quote_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
        
        // Validate quote access
        if (!$this->validate_quote_access($quote_id, $token)) {
            echo '<div class="quote-error-message">';
            echo '<p>' . esc_html__('Invalid quote access token. Please contact us for assistance.', 'quote-manager-system-for-woocommerce') . '</p>';
            echo '</div>';
            return;
        }
        
        // Check for error message
        $error = isset($_GET['error']) ? sanitize_text_field($_GET['error']) : '';
        
        // Output the template
        $this->load_template('accept-quote', array(
            'quote_id' => $quote_id,
            'token' => $token,
            'error' => $error
        ));
    }
    
    /**
     * Render reject quote form
     */
    private function render_reject_quote()
    {
        // Get parameters
        $quote_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
        
        // Validate quote access
        if (!$this->validate_quote_access($quote_id, $token)) {
            echo '<div class="quote-error-message">';
            echo '<p>' . esc_html__('Invalid quote access token. Please contact us for assistance.', 'quote-manager-system-for-woocommerce') . '</p>';
            echo '</div>';
            return;
        }
        
        // Check for error message
        $error = isset($_GET['error']) ? sanitize_text_field($_GET['error']) : '';
        
        // Output the template
        $this->load_template('reject-quote', array(
            'quote_id' => $quote_id,
            'token' => $token,
            'error' => $error
        ));
    }
    
    /**
     * Render quote response page
     */
    private function render_quote_response()
    {
        // Get parameters
        $quote_id = isset($_GET['quote_id']) ? intval($_GET['quote_id']) : 0;
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        
        // Output the response template
        $this->load_template('quote-response', array(
            'quote_id' => $quote_id,
            'status' => $status
        ));
    }
    
    /**
     * AJAX handler for accepting a quote
     */
    public function ajax_accept_quote()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'quote_accept_nonce')) {
            wp_send_json_error(array(
                'message' => __('Security check failed. Please refresh the page and try again.', 'quote-manager-system-for-woocommerce')
            ));
        }
        
        // Get parameters
        $quote_id = isset($_POST['quote_id']) ? intval($_POST['quote_id']) : 0;
        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
        $signature = isset($_POST['signature']) ? sanitize_text_field($_POST['signature']) : '';
        
        // Validate quote access
        if (!$this->validate_quote_access($quote_id, $token)) {
            wp_send_json_error(array(
                'message' => __('Invalid quote access token. Please contact us for assistance.', 'quote-manager-system-for-woocommerce')
            ));
        }
        
        // Validate signature
        if (empty($signature)) {
            wp_send_json_error(array(
                'message' => __('Please provide your signature to accept the quote.', 'quote-manager-system-for-woocommerce')
            ));
        }
        
        // Save the signature
        update_post_meta($quote_id, '_customer_signature', $signature);
        update_post_meta($quote_id, '_quote_status', Quote_Manager_System_For_Woocommerce::STATUS_ACCEPTED);
        update_post_meta($quote_id, '_quote_accepted_date', current_time('mysql'));
        
        // Generate PDF with signature
        $this->generate_signed_pdf($quote_id);
        
        // Send notifications
        $this->send_quote_accepted_notifications($quote_id);
        
        // Get the quotes page ID
        $quotes_page_id = get_option('quote_manager_page_id');
        
        // If we don't have a quotes page ID, fall back to the response page ID
        if (!$quotes_page_id) {
            $quotes_page_id = get_option('quote_manager_response_page_id');
        }
        
        // Prepare success redirect
        if ($quotes_page_id) {
            $redirect_url = add_query_arg(array(
                'view' => 'response',
                'quote_id' => $quote_id,
                'status' => 'accepted'
            ), get_permalink($quotes_page_id));
        } else {
            // Fallback to home URL if no page is found
            $redirect_url = add_query_arg(array(
                'view' => 'response',
                'quote_id' => $quote_id,
                'status' => 'accepted'
            ), home_url('/quotes/'));
        }
        
        wp_send_json_success(array(
            'redirect' => $redirect_url
        ));
    }
    
    /**
     * AJAX handler for rejecting a quote
     */
    public function ajax_reject_quote()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'quote_reject_nonce')) {
            wp_send_json_error(array(
                'message' => __('Security check failed. Please refresh the page and try again.', 'quote-manager-system-for-woocommerce')
            ));
        }
        
        // Get parameters
        $quote_id = isset($_POST['quote_id']) ? intval($_POST['quote_id']) : 0;
        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
        $reason = isset($_POST['reason']) ? sanitize_textarea_field($_POST['reason']) : '';
        
        // Validate quote access
        if (!$this->validate_quote_access($quote_id, $token)) {
            wp_send_json_error(array(
                'message' => __('Invalid quote access token. Please contact us for assistance.', 'quote-manager-system-for-woocommerce')
            ));
        }
        
        // Save the reason
        update_post_meta($quote_id, '_quote_rejection_reason', $reason);
        update_post_meta($quote_id, '_quote_status', Quote_Manager_System_For_Woocommerce::STATUS_REJECTED);
        update_post_meta($quote_id, '_quote_rejected_date', current_time('mysql'));
        
        // Delete PDF files for rejected quote
        Quote_Manager_System_For_Woocommerce::delete_quote_pdf_files($quote_id);
        
        // Send notifications
        $this->send_quote_rejected_notifications($quote_id);
        
        // Get the quotes page ID
        $quotes_page_id = get_option('quote_manager_page_id');
        
        // If we don't have a quotes page ID, fall back to the response page ID
        if (!$quotes_page_id) {
            $quotes_page_id = get_option('quote_manager_response_page_id');
        }
        
        // Prepare success redirect
        if ($quotes_page_id) {
            $redirect_url = add_query_arg(array(
                'view' => 'response',
                'quote_id' => $quote_id,
                'status' => 'rejected'
            ), get_permalink($quotes_page_id));
        } else {
            // Fallback to home URL if no page is found
            $redirect_url = add_query_arg(array(
                'view' => 'response',
                'quote_id' => $quote_id,
                'status' => 'rejected'
            ), home_url('/quotes/'));
        }
        
        wp_send_json_success(array(
            'redirect' => $redirect_url
        ));
    }

    /**
     * Generate a secure token for quote access
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
     * Validate quote access token
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
     * Load a template file and output its content
     */
    private function load_template($template, $vars = array())
    {
        // Extract variables to use in template
        extract($vars);

        // Set up template path
        $template_path = plugin_dir_path(dirname(__FILE__)) . 'public/templates/' . $template . '.php';

        // Check if template exists
        if (!file_exists($template_path)) {
            echo sprintf(
                esc_html__('Template file %s not found.', 'quote-manager-system-for-woocommerce'),
                esc_html($template)
            );
            return;
        }

        // Include the template
        include $template_path;
    }

    /**
     * Generate signed PDF with customer signature
     */
    private function generate_signed_pdf($quote_id)
    {
        // Load PDF generator
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-pdf-generator-raw.php';

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
        
        // Ensure directory exists
        if (!file_exists($pdf_dir)) {
            wp_mkdir_p($pdf_dir);
        }
        
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
     * Send notifications when a quote is rejected
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
     * Send an email with attachments
     */
    private function send_email($to, $subject, $message, $attachments = array())
    {
        $headers = array('Content-Type: text/html; charset=UTF-8');
        return wp_mail($to, $subject, $message, $headers, $attachments);
    }

    /**
     * Log an email event
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
     * Get a quote token, generating one if needed
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