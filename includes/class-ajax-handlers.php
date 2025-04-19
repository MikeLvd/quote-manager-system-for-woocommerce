<?php
/**
 * Handle AJAX requests for the Quote Manager System
 *
 * @link       https://goldenbath.gr
 * @since      1.0.0
 *
 * @package    Quote_Manager_System_For_Woocommerce
 * @subpackage Quote_Manager_System_For_Woocommerce/includes
 */

class Quote_Manager_Ajax_Handlers {

    /**
     * AJAX handler for product search suggestions.
     */
    public function search_products() {
        // Check permissions (allow only shop managers or admins)
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Access denied', 'quote-manager-system-for-woocommerce'), 403);
        }

        // Get and sanitize search term
        $term = isset($_POST['term']) ? sanitize_text_field(wp_unslash($_POST['term'])) : '';
        if (empty($term)) {
            wp_send_json(array());
        }

        // Query by product title
        $args_title = array(
            'post_type'      => 'product',
            'posts_per_page' => 10,
            'post_status'    => 'publish',
            's'              => $term,
        );
        $query_title = new WP_Query($args_title);

        // Query by SKU (including variations)
        $args_sku = array(
            'post_type'      => array('product', 'product_variation'),
            'posts_per_page' => 10,
            'post_status'    => 'publish',
            'meta_query'     => array(
                array(
                    'key'     => '_sku',
                    'value'   => $term,
                    'compare' => 'LIKE'
                )
            )
        );
        $query_sku = new WP_Query($args_sku);

        // Combine results and ensure uniqueness
        $posts = array();
        if ($query_title->have_posts()) {
            $posts = array_merge($posts, $query_title->posts);
        }
        if ($query_sku->have_posts()) {
            $posts = array_merge($posts, $query_sku->posts);
        }

        $results = array();
        $seen_ids = array();
        foreach ($posts as $post) {
            if (in_array($post->ID, $seen_ids, true)) {
                continue;
            }
            $seen_ids[] = $post->ID;
            $product = wc_get_product($post->ID);
            if (!$product) {
                continue;
            }

            $title = $product->get_name();
            // If variation, use parent product name and variation attributes
            if ($product->is_type('variation')) {
                $parent = wc_get_product($product->get_parent_id());
                if ($parent) {
                    $title = $parent->get_name();
                }
                $attributes = $product->get_attributes();
                $attr_values = array();
                if (!empty($attributes)) {
                    foreach ($attributes as $tax => $val) {
                        $tax = str_replace('attribute_', '', $tax);
                        $tax = str_replace('pa_', '', $tax);
                        $term_obj = get_term_by('slug', $val, 'pa_' . $tax);
                        if ($term_obj instanceof WP_Term) {
                            $attr_values[] = $term_obj->name;
                        } elseif (!is_array($val)) {
                            $attr_values[] = $val;
                        }
                    }
                    if (!empty($attr_values)) {
                        $title .= ' - ' . implode(', ', $attr_values);
                    }
                }
            }

            $image_url = '';
            $image_id = $product->get_image_id();
            if ($image_id) {
                $image_url = wp_get_attachment_url($image_id);
            }
            if (empty($image_url)) {
                $image_url = function_exists('wc_placeholder_img_src') ? wc_placeholder_img_src() : '';
            }

            $results[] = array(
                'id'            => $product->get_id(),
                'title'         => $title,
                'sku'           => $product->get_sku(),
                'price'         => wc_get_price_including_tax($product), // final price without VAT
                'regular_price' => wc_get_price_including_tax($product, array('price' => $product->get_regular_price())), // regular price without VAT
                'image'         => $image_url,
            );
        }

        // Limit to 10 results max
        if (count($results) > 10) {
            $results = array_slice($results, 0, 10);
        }

        wp_send_json($results);
    }

    /**
     * Generate PDF Preview
     */
    public function generate_pdf_preview() {
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-pdf-generator.php';
        $pdf_generator = new Quote_Manager_PDF_Generator();
        $pdf_generator->generate_pdf(false);
        exit;
    }

    /**
     * Generate PDF Download
     */
    public function generate_pdf_download() {
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-pdf-generator.php';
        $pdf_generator = new Quote_Manager_PDF_Generator();
        $pdf_generator->generate_pdf(true);
        exit;
    }

    /**
     * Send quote email with custom subject and message
     */
    public function send_email() {
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Access denied.']);
        }

        $quote_id = isset($_POST['quote_id']) ? intval($_POST['quote_id']) : 0;
        $subject  = isset($_POST['subject']) ? sanitize_text_field($_POST['subject']) : '';
        $message  = isset($_POST['message']) ? wp_kses_post($_POST['message']) : '';

        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-email-manager.php';
        $email_manager = new Quote_Manager_Email_Manager();
        $result = $email_manager->send_quote_email($quote_id, $subject, $message);

        if ($result['success']) {
            wp_send_json_success(['message' => $result['message']]);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
        exit;
    }	
	
    /**
     * Handle file upload for quote attachments
     */
    public function upload_attachment() {
        // Check nonce for security
        if (!check_ajax_referer('quote_attachment_upload', 'security', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'quote-manager-system-for-woocommerce')]);
        }
        
        // Check permissions (allow only shop managers or admins)
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Access denied', 'quote-manager-system-for-woocommerce')]);
        }
        
        // Check if file was uploaded
        if (empty($_FILES['file'])) {
            wp_send_json_error(['message' => __('No file was uploaded.', 'quote-manager-system-for-woocommerce')]);
        }
        
        // Get quote ID
        $quote_id = isset($_POST['quote_id']) ? intval($_POST['quote_id']) : 0;
        if (!$quote_id) {
            wp_send_json_error(['message' => __('Invalid quote ID.', 'quote-manager-system-for-woocommerce')]);
        }
        
        // Create upload directory if it doesn't exist
        $upload_dir = wp_upload_dir();
        $quote_attachments_dir = $upload_dir['basedir'] . '/quote-manager/attachments/' . $quote_id;
        
        if (!file_exists($quote_attachments_dir)) {
            wp_mkdir_p($quote_attachments_dir);
            
            // Create .htaccess to protect direct access
            $htaccess_content = <<<HTACCESS
            # Disable directory browsing
            Options -Indexes
            
            # Deny access to .htaccess
            <Files .htaccess>
                Order allow,deny
                Deny from all
            </Files>
            
            # Allow access only through WordPress
            <IfModule mod_rewrite.c>
                RewriteEngine On
                RewriteCond %{HTTP_REFERER} !^.*wp-admin.* [NC]
                RewriteRule .* - [F]
            </IfModule>
            HTACCESS;
            
            file_put_contents($quote_attachments_dir . '/.htaccess', $htaccess_content);
        }
        
        // Handle the file upload
        $file = $_FILES['file'];
        $filename = sanitize_file_name($file['name']);
        $file_path = $quote_attachments_dir . '/' . $filename;
        
        // Check if file with the same name exists
        if (file_exists($file_path)) {
            // Add timestamp to filename to make it unique
            $filename_parts = pathinfo($filename);
            $filename = $filename_parts['filename'] . '-' . time() . '.' . $filename_parts['extension'];
            $file_path = $quote_attachments_dir . '/' . $filename;
        }
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            // Generate URL for the file
            $file_url = $upload_dir['baseurl'] . '/quote-manager/attachments/' . $quote_id . '/' . $filename;
            
            // Return success response
            wp_send_json_success([
                'id' => uniqid('attach_'),
                'url' => $file_url,
                'filename' => $filename,
                'type' => $file['type']
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to upload file.', 'quote-manager-system-for-woocommerce')]);
        }
    }
	
    /**
     * Delete attachment file
     */
    public function delete_attachment() {
        // Check nonce for security
        if (!check_ajax_referer('quote_attachment_delete', 'security', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'quote-manager-system-for-woocommerce')]);
            return;
        }
        
        // Check permissions (allow only shop managers or admins)
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Access denied', 'quote-manager-system-for-woocommerce')]);
            return;
        }
        
        // Get file URL and attachment data
        $file_url = isset($_POST['file_url']) ? esc_url_raw($_POST['file_url']) : '';
        $attachment_id = isset($_POST['attachment_id']) ? sanitize_text_field($_POST['attachment_id']) : '';
        $quote_id = isset($_POST['quote_id']) ? intval($_POST['quote_id']) : 0;
        
        if (empty($file_url) || empty($quote_id)) {
            wp_send_json_error(['message' => __('Missing required information.', 'quote-manager-system-for-woocommerce')]);
            return;
        }
        
        // Convert URL to file path
        $upload_dir = wp_upload_dir();
        $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $file_url);
        
        // Security check - make sure it's in our upload directory
        if (strpos($file_path, $upload_dir['basedir']) !== 0) {
            wp_send_json_error(['message' => __('Invalid file path.', 'quote-manager-system-for-woocommerce')]);
            return;
        }
        
        // Get the current attachments list
        $attachments = get_post_meta($quote_id, '_quote_attachments', true);
        if (!is_array($attachments)) {
            $attachments = [];
        }
        
        // Find and remove the attachment from the array
        $updated_attachments = [];
        $file_found = false;
        
        foreach ($attachments as $attachment) {
            if (isset($attachment['url']) && $attachment['url'] === $file_url) {
                $file_found = true;
                // Skip this attachment (removing it from the list)
                continue;
            }
            $updated_attachments[] = $attachment;
        }
        
        if (!$file_found) {
            wp_send_json_error(['message' => __('Attachment not found in quote data.', 'quote-manager-system-for-woocommerce')]);
            return;
        }
        
        // Update the post meta with the new attachments list
        update_post_meta($quote_id, '_quote_attachments', $updated_attachments);
        
        // Delete the physical file if it exists
        if (file_exists($file_path)) {
            if (!unlink($file_path)) {
                // Even if file deletion fails, we've updated the meta data
                wp_send_json_success([
                    'message' => __('Attachment removed from quote, but file could not be deleted from server.', 'quote-manager-system-for-woocommerce'),
                    'partial' => true
                ]);
                return;
            }
        }
        
        wp_send_json_success(['message' => __('Attachment deleted successfully.', 'quote-manager-system-for-woocommerce')]);
    }
}