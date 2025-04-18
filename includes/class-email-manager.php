<?php
/**
 * Handle email functionality for quotes
 *
 * @link       https://goldenbath.gr
 * @since      1.0.0
 *
 * @package    Quote_Manager_System_For_Woocommerce
 * @subpackage Quote_Manager_System_For_Woocommerce/includes
 */

require_once plugin_dir_path(dirname(__FILE__)) . 'lib/dompdf/autoload.inc.php';
use Dompdf\Dompdf;

class Quote_Manager_Email_Manager {

    /**
     * Create .htaccess file to protect PDF files in the quote-manager directory
     *
     * @return bool True if file created successfully, false otherwise
     */
    public function create_protection_htaccess() {
        // Get the upload directory path
        $upload_dir = wp_upload_dir();
        $pdf_dir = $upload_dir['basedir'] . '/quote-manager/';
        
        // Create directory if it doesn't exist
        if (!file_exists($pdf_dir)) {
            wp_mkdir_p($pdf_dir);
        }
        
        // Path to the .htaccess file
        $htaccess_file = $pdf_dir . '.htaccess';
        
        // .htaccess content to protect the directory
        $htaccess_content = <<<EOT
        # Disable directory browsing
        Options -Indexes
        
        # Prevent direct access to PDF files
        <FilesMatch "\.pdf$">
            # Apache 2.2
            <IfModule !mod_authz_core.c>
                Order deny,allow
                Deny from all
            </IfModule>
            
            # Apache 2.4+
            <IfModule mod_authz_core.c>
                Require all denied
            </IfModule>
        </FilesMatch>
        
        # Add X-Robots-Tag header to prevent indexing
        <IfModule mod_headers.c>
            Header set X-Robots-Tag "noindex, nofollow, noarchive"
        </IfModule>
        
        # Prevent viewing of .htaccess file
        <Files .htaccess>
            # Apache 2.2
            <IfModule !mod_authz_core.c>
                Order deny,allow
                Deny from all
            </IfModule>
            
            # Apache 2.4+
            <IfModule mod_authz_core.c>
                Require all denied
            </IfModule>
        </Files>
        
        # Allow access only from your WordPress site or localhost
        <IfModule mod_rewrite.c>
            RewriteEngine On
            RewriteCond %{HTTP_REFERER} !^https?://(www\.)?{SITE_HOST} [NC]
            RewriteCond %{HTTP_REFERER} !^https?://localhost [NC]
            RewriteCond %{HTTP_REFERER} !^https?://127\.0\.0\.1 [NC]
            RewriteRule \.(pdf)$ - [F]
        </IfModule>
        EOT;
    
        // Replace {SITE_HOST} with the actual domain
        $site_url = parse_url(site_url(), PHP_URL_HOST);
        $htaccess_content = str_replace('{SITE_HOST}', $site_url, $htaccess_content);
        
        // Write the .htaccess file
        $result = file_put_contents($htaccess_file, $htaccess_content);
        
        return ($result !== false);
    }
	
    /**
     * Process and send quote email
     *
     * @param int $quote_id The quote ID
     * @param string $custom_subject Custom email subject
     * @param string $custom_message Custom email message
     * @return array Success status and message
     */
    public function send_quote_email($quote_id, $custom_subject = '', $custom_message = '') {
        if (!$quote_id || get_post_type($quote_id) !== 'customer_quote') {
            error_log('Invalid quote ID: ' . $quote_id);
            return ['success' => false, 'message' => 'Invalid quote ID.'];
        }
    
        // Check customer email
        $email_to = get_post_meta($quote_id, '_customer_email', true);
        if (!is_email($email_to)) {
            error_log('Invalid email address: ' . $email_to);
            return ['success' => false, 'message' => 'Invalid customer email.'];
        }
    
        try {
            // Generate PDF
            error_log('Starting PDF generation');
            
            // Load raw PDF generator
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-pdf-generator-raw.php';
            $raw_generator = new Quote_Manager_PDF_Generator_Raw();
            $pdf_output = $raw_generator->generate($quote_id);
            
            if (empty($pdf_output)) {
                error_log('PDF generation failed - empty output');
                return ['success' => false, 'message' => 'Failed to generate PDF.'];
            }
            
            error_log('PDF generated successfully, size: ' . strlen($pdf_output) . ' bytes');
            
            // Create upload directory
            $upload_dir = wp_upload_dir();
            $pdf_dir = $upload_dir['basedir'] . '/quote-manager/';
            if (!file_exists($pdf_dir)) {
                $dir_created = wp_mkdir_p($pdf_dir);
                error_log('Directory creation result: ' . ($dir_created ? 'Success' : 'Failed'));
                
                if (!$dir_created) {
                    return ['success' => false, 'message' => 'Could not create upload directory.'];
                }
                
                // Create .htaccess protection file
                $this->create_protection_htaccess();
            }
            
            // Check if directory is writable
            if (!is_writable($pdf_dir)) {
                error_log('Directory not writable: ' . $pdf_dir);
                return ['success' => false, 'message' => 'Upload directory not writable.'];
            }
            
            // Save PDF file
            $pdf_path = $pdf_dir . 'QUOTE_' . $quote_id . '.pdf';
            $file_result = file_put_contents($pdf_path, $pdf_output);
            
            if ($file_result === false) {
                error_log('Failed to write PDF file: ' . $pdf_path);
                return ['success' => false, 'message' => 'Could not save PDF file.'];
            }
            
            error_log('PDF file saved: ' . $pdf_path);
    
            // Customer details
            $first_name    = get_post_meta($quote_id, '_customer_first_name', true);
            $last_name     = get_post_meta($quote_id, '_customer_last_name', true);
            $quote_expiry  = get_post_meta($quote_id, '_quote_expiry_date', true) ?: '–';
    
            // Email Subject
            $subject = !empty($custom_subject)
                ? sanitize_text_field($custom_subject)
                : sprintf(__('Your quote from %s', 'quote-manager-system-for-woocommerce'), get_bloginfo('name'));
    
            // Email Message with placeholders
            $default_template = 'Dear {{customer_first_name}}, we are sending you quote number {{quote_id}} in PDF format. Thank you for your interest!';
            $template = !empty($custom_message) ? $custom_message : $default_template;
    
            // Parse placeholders
            $message = nl2br(wp_kses_post($this->parse_email_placeholders($template, [
                'customer_first_name' => $first_name,
                'customer_last_name'  => $last_name,
                'customer_name'       => trim($first_name . ' ' . $last_name),
                'quote_id'            => $quote_id,
                'quote_expiry'        => $quote_expiry,
            ])));
    
            // Email headers & attachments
            $headers     = ['Content-Type: text/html; charset=UTF-8'];
            $attachments = [$pdf_path];
    
            // Logging before sending
            $existing_logs = get_post_meta($quote_id, '_quote_email_logs', true);
            if (!is_array($existing_logs)) {
                $existing_logs = [];
            }
            $log_index = count($existing_logs);
    
            // Tracking Pixel
            $tracking_url = add_query_arg([
                'quote_email_open' => 1,
                'quote_id'         => $quote_id,
                'log_index'        => $log_index,
            ], site_url('/'));
    
            $tracking_pixel = '<img src="' . esc_url($tracking_url) . '" width="1" height="1" style="display:none;" alt="" />';
            $final_message = $message . '<br>' . $tracking_pixel;
    
            // Send Email
            $sent = wp_mail($email_to, $subject, $final_message, $headers, $attachments);
    
            // Log Entry
            $log_entry = [
                'datetime'   => current_time('mysql'),
                'to'         => $email_to,
                'subject'    => $subject,
                'message'    => $final_message,
                'result'     => $sent ? 'success' : 'error',
                'opened_at'  => '',
            ];
    
            $existing_logs[] = $log_entry;
            update_post_meta($quote_id, '_quote_email_logs', $existing_logs);
    
            if ($sent) {
                return ['success' => true, 'message' => 'Email sent successfully.'];
            } else {
                return ['success' => false, 'message' => 'Error sending email.'];
            }
        } catch (Exception $e) {
            // Add this catch block to handle any exceptions
            error_log('Error in send_quote_email: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
	
    /**
     * Parse email placeholders in templates
     *
     * @param string $template Email template with placeholders
     * @param array $data Data to replace placeholders
     * @return string Processed message
     */
    private function parse_email_placeholders($template, $data) {
        $replacements = [
            '{{customer_name}}'        => $data['customer_name'] ?? '',
            '{{customer_first_name}}'  => $data['customer_first_name'] ?? '',
            '{{customer_last_name}}'   => $data['customer_last_name'] ?? '',
            '{{quote_id}}'             => $data['quote_id'] ?? '',
            '{{quote_expiry}}'         => $data['quote_expiry'] ?? '',
            '{{site_name}}'            => get_bloginfo('name'),
            '{{today}}'                => date_i18n('d/m/Y'),
        ];

        return strtr($template, $replacements);
    }

    /**
     * Handle email open tracking
     */
    public function handle_email_open_tracking() {
        if (
            isset($_GET['quote_email_open']) &&
            $_GET['quote_email_open'] == '1' &&
            isset($_GET['quote_id']) &&
            isset($_GET['log_index'])
        ) {
            $quote_id = absint($_GET['quote_id']);
            $log_index = absint($_GET['log_index']);

            // Ensure this is a valid quote
            if (!$quote_id || get_post_type($quote_id) !== 'customer_quote') {
                return;
            }

            // Get logs
            $logs = get_post_meta($quote_id, '_quote_email_logs', true);
            if (!is_array($logs) || !isset($logs[$log_index])) {
                return;
            }

            // If not already opened, record the date
            if (empty($logs[$log_index]['opened_at'])) {
                $logs[$log_index]['opened_at'] = current_time('mysql');
                update_post_meta($quote_id, '_quote_email_logs', $logs);
            }

            // Return 1x1 transparent pixel
            header('Content-Type: image/gif');
            echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
            exit;
        }
    }
}