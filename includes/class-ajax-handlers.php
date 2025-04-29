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

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class Quote_Manager_Ajax_Handlers
{

    /**
     * AJAX handler for product search suggestions.
     */
    public function search_products()
    {
        // Check permissions (allow only shop managers or admins)
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Access denied', 'quote-manager-system-for-woocommerce'), 403);
        }
    
        // Get and sanitize search term
        $term = isset($_POST['term']) ? sanitize_text_field(wp_unslash($_POST['term'])) : '';
        if (empty($term)) {
            wp_send_json(array());
        }
    
        // Query by product title - include publish, draft, and private statuses
        $args_title = array(
            'post_type' => 'product',
            'posts_per_page' => 50, // Increased to account for showing variations
            'post_status' => array('publish', 'draft', 'private'),
            's' => $term,
        );
        $query_title = new WP_Query($args_title);
    
        // Query by SKU (including variations) - include publish, draft, and private statuses
        $args_sku = array(
            'post_type' => array('product', 'product_variation'),
            'posts_per_page' => 50, // Increased to account for showing variations
            'post_status' => array('publish', 'draft', 'private'),
            'meta_query' => array(
                array(
                    'key' => '_sku',
                    'value' => $term,
                    'compare' => 'LIKE'
                )
            )
        );
        $query_sku = new WP_Query($args_sku);
    
        // Combine results and ensure uniqueness
        $products_array = array();
        $parent_products = array();
        $variations = array();
        $seen_ids = array();
    
        // Process products from title search
        if ($query_title->have_posts()) {
            foreach ($query_title->posts as $post) {
                if (in_array($post->ID, $seen_ids, true)) {
                    continue;
                }
                $seen_ids[] = $post->ID;
                
                $product = wc_get_product($post->ID);
                if (!$product) {
                    continue;
                }
                
                if ($product->is_type('variable')) {
                    // Store parent product for later
                    $parent_products[$post->ID] = array(
                        'id' => $product->get_id(),
                        'title' => $product->get_name(),
                        'sku' => $product->get_sku(),
                        'price' => wc_get_price_including_tax($product),
                        'regular_price' => wc_get_price_including_tax($product, array('price' => $product->get_regular_price())),
                        'image' => $this->get_product_image($product),
                        'purchase_price' => $this->get_product_cost($product->get_id()),
                        'is_parent' => true
                    );
                    
                    // Get all variations
                    $args = array(
                        'post_type'     => 'product_variation',
                        'post_status'   => array('publish', 'draft', 'private'),
                        'posts_per_page' => -1,
                        'post_parent'   => $post->ID
                    );
                    
                    $variation_posts = get_posts($args);
                    
                    foreach ($variation_posts as $variation_post) {
                        $variation = wc_get_product($variation_post->ID);
                        if (!$variation) continue;
                        
                        $attribute_string = $this->get_variation_attributes_string($variation);
                        $variation_title = $product->get_name() . ' - ' . $attribute_string;
                        
                        $variations[] = array(
                            'id' => $variation->get_id(),
                            'title' => $variation_title,
                            'sku' => $variation->get_sku(),
                            'price' => wc_get_price_including_tax($variation),
                            'regular_price' => wc_get_price_including_tax($variation, array('price' => $variation->get_regular_price())),
                            'image' => $this->get_product_image($variation),
                            'purchase_price' => $this->get_product_cost($variation->get_id()),
                            'parent_id' => $product->get_id()
                        );
                        
                        $seen_ids[] = $variation->get_id();
                    }
                } else {
                    // Simple product
                    $products_array[] = array(
                        'id' => $product->get_id(),
                        'title' => $product->get_name(),
                        'sku' => $product->get_sku(),
                        'price' => wc_get_price_including_tax($product),
                        'regular_price' => wc_get_price_including_tax($product, array('price' => $product->get_regular_price())),
                        'image' => $this->get_product_image($product),
                        'purchase_price' => $this->get_product_cost($product->get_id())
                    );
                }
            }
        }
        
        // Process products from SKU search (only process products not already found)
        if ($query_sku->have_posts()) {
            foreach ($query_sku->posts as $post) {
                if (in_array($post->ID, $seen_ids, true)) {
                    continue;
                }
                $seen_ids[] = $post->ID;
                
                $product = wc_get_product($post->ID);
                if (!$product) {
                    continue;
                }
                
                if ($product->is_type('variation')) {
                    // For variation, check if parent is already processed
                    $parent_id = $product->get_parent_id();
                    $parent_product = wc_get_product($parent_id);
                    
                    if (!$parent_product) continue;
                    
                    // Add parent if not already added
                    if (!isset($parent_products[$parent_id]) && !in_array($parent_id, $seen_ids, true)) {
                        $parent_products[$parent_id] = array(
                            'id' => $parent_product->get_id(),
                            'title' => $parent_product->get_name(),
                            'sku' => $parent_product->get_sku(),
                            'price' => wc_get_price_including_tax($parent_product),
                            'regular_price' => wc_get_price_including_tax($parent_product, array('price' => $parent_product->get_regular_price())),
                            'image' => $this->get_product_image($parent_product),
                            'purchase_price' => $this->get_product_cost($parent_product->get_id()),
                            'is_parent' => true
                        );
                        $seen_ids[] = $parent_id;
                    }
                    
                    // Add the variation
                    $attribute_string = $this->get_variation_attributes_string($product);
                    $variation_title = $parent_product->get_name() . ' - ' . $attribute_string;
                    
                    $variations[] = array(
                        'id' => $product->get_id(),
                        'title' => $variation_title,
                        'sku' => $product->get_sku(),
                        'price' => wc_get_price_including_tax($product),
                        'regular_price' => wc_get_price_including_tax($product, array('price' => $product->get_regular_price())),
                        'image' => $this->get_product_image($product),
                        'purchase_price' => $this->get_product_cost($product->get_id()),
                        'parent_id' => $parent_id
                    );
                } else {
                    // Simple product from SKU search
                    $products_array[] = array(
                        'id' => $product->get_id(),
                        'title' => $product->get_name(),
                        'sku' => $product->get_sku(),
                        'price' => wc_get_price_including_tax($product),
                        'regular_price' => wc_get_price_including_tax($product, array('price' => $product->get_regular_price())),
                        'image' => $this->get_product_image($product),
                        'purchase_price' => $this->get_product_cost($product->get_id())
                    );
                }
            }
        }
        
        // Build final results array - first add parent products, then variations organized by parent
        $results = array();
        
        // Add simple products first
        foreach ($products_array as $product_data) {
            $results[] = $product_data;
        }
        
        // Add variable products and their variations
        foreach ($parent_products as $parent_id => $parent_data) {
            // Add parent first
            $results[] = $parent_data;
            
            // Then add its variations
            foreach ($variations as $variation) {
                if ($variation['parent_id'] == $parent_id) {
                    $results[] = $variation;
                }
            }
        }
        
        // Add any remaining variations (whose parents weren't in the search results)
        $added_parents = array_keys($parent_products);
        foreach ($variations as $variation) {
            if (!in_array($variation['parent_id'], $added_parents)) {
                $results[] = $variation;
            }
        }
    
        // Limit to a reasonable number for display
        if (count($results) > 50) {
            $results = array_slice($results, 0, 50);
        }
    
        wp_send_json($results);
    }
    
    /**
     * Get product image URL
     *
     * @param WC_Product $product
     * @return string Image URL
     */
    private function get_product_image($product) {
        $image_url = '';
        $image_id = $product->get_image_id();
        if ($image_id) {
            $image_url = wp_get_attachment_url($image_id);
        }
        if (empty($image_url)) {
            $image_url = function_exists('wc_placeholder_img_src') ? wc_placeholder_img_src() : '';
        }
        return $image_url;
    }
    
    /**
     * Get product cost (from meta)
     *
     * @param int $product_id
     * @return string Formatted cost
     */
    private function get_product_cost($product_id) {
        // First check for _cogs_total_value (compatible with WooCommerce COGS)
        $cost = get_post_meta($product_id, '_cogs_total_value', true);
        
        // If not found, try _wc_cog_cost (compatible with SkyVerge Cost of Goods)
        if ('' === $cost) {
            $cost = get_post_meta($product_id, '_wc_cog_cost', true);
        }
        
        return $cost;
    }
    
    /**
     * Get formatted variation attributes string
     *
     * @param WC_Product_Variation $variation
     * @return string Formatted attribute string
     */
    private function get_variation_attributes_string($variation) {
        $attributes = $variation->get_attributes();
        $attribute_values = array();
        
        if (!empty($attributes)) {
            foreach ($attributes as $tax => $val) {
                $taxonomy = str_replace('attribute_', '', $tax);
                
                // If it's a taxonomy term, get the term name
                if (taxonomy_exists($taxonomy)) {
                    $term = get_term_by('slug', $val, $taxonomy);
                    if ($term && !is_wp_error($term)) {
                        $attribute_values[] = $term->name;
                    } else {
                        $attribute_values[] = $val;
                    }
                } else {
                    // For custom product attributes
                    $attribute_values[] = $val;
                }
            }
        }
        
        return implode(', ', $attribute_values);
    }

    /**
     * AJAX handler for getting states for a country
     */
    public function get_states()
    {
        // Verify nonce
        if (!check_ajax_referer('quote_manager_get_states', 'security', false)) {
            wp_send_json_error(__('Security check failed.', 'quote-manager-system-for-woocommerce'));
        }

        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Access denied', 'quote-manager-system-for-woocommerce'));
        }

        // Get country code from request
        $country = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '';

        if (empty($country)) {
            wp_send_json_error(__('Country is required', 'quote-manager-system-for-woocommerce'));
        }

        // Get states for the country
        $states = WC()->countries->get_states($country);

        if (is_array($states) && !empty($states)) {
            wp_send_json_success($states);
        } else {
            wp_send_json_error(__('No states found for this country', 'quote-manager-system-for-woocommerce'));
        }
    }


    /**
     * Search for existing WooCommerce customers - real-time version
     */
    public function search_customers() {
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Access denied', 'quote-manager-system-for-woocommerce')]);
        }
    
        // Verify nonce
        if (!check_ajax_referer('quote_manager_search_customers', 'security', false)) {
            wp_send_json_error(['message' => __('Security check failed', 'quote-manager-system-for-woocommerce')]);
        }
    
        // Get and sanitize search term
        $term = isset($_POST['term']) ? sanitize_text_field(wp_unslash($_POST['term'])) : '';
        if (empty($term) || strlen($term) < 2) {
            wp_send_json([]);
        }
    
        // Prepare the search - use direct DB query for better performance
        global $wpdb;
        
        // Escape the search term for SQL
        $like = '%' . $wpdb->esc_like($term) . '%';
        
        // Get users by email, name, or meta values
        $sql = $wpdb->prepare(
            "SELECT DISTINCT u.ID 
            FROM {$wpdb->users} u 
            LEFT JOIN {$wpdb->usermeta} um1 ON (u.ID = um1.user_id AND um1.meta_key = 'billing_email')
            LEFT JOIN {$wpdb->usermeta} um2 ON (u.ID = um2.user_id AND um2.meta_key = 'billing_first_name')
            LEFT JOIN {$wpdb->usermeta} um3 ON (u.ID = um3.user_id AND um3.meta_key = 'billing_last_name')
            LEFT JOIN {$wpdb->usermeta} um4 ON (u.ID = um4.user_id AND um4.meta_key = 'billing_company')
            LEFT JOIN {$wpdb->usermeta} um5 ON (u.ID = um5.user_id AND um5.meta_key = 'billing_phone')
            WHERE 
                u.user_email LIKE %s OR 
                um1.meta_value LIKE %s OR
                um2.meta_value LIKE %s OR 
                um3.meta_value LIKE %s OR
                um4.meta_value LIKE %s OR
                um5.meta_value LIKE %s OR
                CONCAT(um2.meta_value, ' ', um3.meta_value) LIKE %s
            LIMIT 10",
            $like, $like, $like, $like, $like, $like, $like
        );
        
        $user_ids = $wpdb->get_col($sql);
        $results = array();
    
        // Process each user
        if (!empty($user_ids)) {
            foreach ($user_ids as $user_id) {
                // Get user object
                $user = get_userdata($user_id);
                if (!$user) continue;
                
                // Check if user has customer role or admin role
                if (!in_array('customer', $user->roles) && !in_array('administrator', $user->roles)) {
                    continue;
                }
                
                // Get user meta
                $first_name = get_user_meta($user_id, 'billing_first_name', true);
                $last_name = get_user_meta($user_id, 'billing_last_name', true);
                $company = get_user_meta($user_id, 'billing_company', true);
                $email = $user->user_email;
                
                // Format data for frontend
                $results[] = array(
                    'id' => $user_id,
                    'data' => array(
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'company' => $company,
                        'address' => get_user_meta($user_id, 'billing_address_1', true),
                        'city' => get_user_meta($user_id, 'billing_city', true),
                        'postcode' => get_user_meta($user_id, 'billing_postcode', true),
                        'country' => get_user_meta($user_id, 'billing_country', true),
                        'state' => get_user_meta($user_id, 'billing_state', true),
                        'phone' => get_user_meta($user_id, 'billing_phone', true),
                        'email' => $email,
    					
    					/*
    					// (for the quote purpose this its not needed, as the admin can hit the Copy billing address button)
                        'shipping_first_name' => get_user_meta($user_id, 'shipping_first_name', true),
                        'shipping_last_name' => get_user_meta($user_id, 'shipping_last_name', true),
                        'shipping_company' => get_user_meta($user_id, 'shipping_company', true),
                        'shipping_address' => get_user_meta($user_id, 'shipping_address_1', true),
                        'shipping_city' => get_user_meta($user_id, 'shipping_city', true),
                        'shipping_postcode' => get_user_meta($user_id, 'shipping_postcode', true),
                        'shipping_country' => get_user_meta($user_id, 'shipping_country', true),
                        'shipping_state' => get_user_meta($user_id, 'shipping_state', true),
                        'shipping_phone' => get_user_meta($user_id, 'shipping_phone', true),
    					*/
                    )
                );
            }
        }
    
        wp_send_json($results);
    }

    /**
     * Create a new WooCommerce customer from quote data
     */
    public function create_customer_from_quote()
    {
        // Verify nonce
        check_ajax_referer('create_customer_from_quote', 'security');

        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Access denied', 'quote-manager-system-for-woocommerce')]);
        }

        // Get quote ID
        $quote_id = isset($_POST['quote_id']) ? intval($_POST['quote_id']) : 0;
        if (!$quote_id) {
            wp_send_json_error(['message' => __('Invalid quote ID', 'quote-manager-system-for-woocommerce')]);
        }

        // Get customer details from quote
        $first_name = get_post_meta($quote_id, '_customer_first_name', true);
        $last_name = get_post_meta($quote_id, '_customer_last_name', true);
        $email = get_post_meta($quote_id, '_customer_email', true);
        $phone = get_post_meta($quote_id, '_customer_phone', true);
        $company = get_post_meta($quote_id, '_customer_company', true);

        // Required fields check
        if (empty($email)) {
            wp_send_json_error(['message' => __('Customer email is required', 'quote-manager-system-for-woocommerce')]);
            return;
        }

        // Check if email already exists
        if (email_exists($email)) {
            wp_send_json_error([
                'message' => __('A user with this email already exists', 'quote-manager-system-for-woocommerce'),
                'customer_id' => get_user_by('email', $email)->ID
            ]);
            return;
        }

        // Generate username from email if first/last name not provided
        $username = sanitize_user(current(explode('@', $email)), true);

        // Ensure username is unique
        $counter = 1;
        $new_username = $username;
        while (username_exists($new_username)) {
            $new_username = $username . $counter;
            $counter++;
        }
        $username = $new_username;

        // Generate a random password
        $password = wp_generate_password(12, true);

        // Create user data
        $userdata = array(
            'user_login' => $username,
            'user_pass' => $password,
            'user_email' => $email,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'role' => 'customer'
        );

        // Insert user directly with WordPress function
        $customer_id = wp_insert_user($userdata);

        // If an error occurred creating the customer
        if (is_wp_error($customer_id)) {
            wp_send_json_error(['message' => $customer_id->get_error_message()]);
            return;
        }

        // Update customer data
        update_user_meta($customer_id, 'billing_first_name', $first_name);
        update_user_meta($customer_id, 'billing_last_name', $last_name);
        update_user_meta($customer_id, 'billing_company', $company);
        update_user_meta($customer_id, 'billing_address_1', get_post_meta($quote_id, '_customer_address', true));
        update_user_meta($customer_id, 'billing_city', get_post_meta($quote_id, '_customer_city', true));
        update_user_meta($customer_id, 'billing_postcode', get_post_meta($quote_id, '_customer_postcode', true));
        update_user_meta($customer_id, 'billing_country', get_post_meta($quote_id, '_customer_country', true));
        update_user_meta($customer_id, 'billing_state', get_post_meta($quote_id, '_customer_state', true));
        update_user_meta($customer_id, 'billing_phone', $phone);
        update_user_meta($customer_id, 'billing_email', $email);

        // Set shipping details
        update_user_meta($customer_id, 'shipping_first_name', get_post_meta($quote_id, '_shipping_first_name', true));
        update_user_meta($customer_id, 'shipping_last_name', get_post_meta($quote_id, '_shipping_last_name', true));
        update_user_meta($customer_id, 'shipping_company', get_post_meta($quote_id, '_shipping_company', true));
        update_user_meta($customer_id, 'shipping_address_1', get_post_meta($quote_id, '_shipping_address', true));
        update_user_meta($customer_id, 'shipping_city', get_post_meta($quote_id, '_shipping_city', true));
        update_user_meta($customer_id, 'shipping_postcode', get_post_meta($quote_id, '_shipping_postcode', true));
        update_user_meta($customer_id, 'shipping_country', get_post_meta($quote_id, '_shipping_country', true));
        update_user_meta($customer_id, 'shipping_state', get_post_meta($quote_id, '_shipping_state', true));
        update_user_meta($customer_id, 'shipping_phone', get_post_meta($quote_id, '_shipping_phone', true));

        // Set additional WooCommerce data
        update_user_meta($customer_id, 'paying_customer', '0');
        update_user_meta($customer_id, '_created_via', 'quote_manager');

        // Update the user's WordPress capabilities
        $customer = new WC_Customer($customer_id);
        $customer->set_role('customer');

        // Store customer ID in quote meta
        update_post_meta($quote_id, '_customer_user_id', $customer_id);

        // Get customer dashboard URL
        $edit_url = admin_url('user-edit.php?user_id=' . $customer_id);

        // Return success with customer ID and edit URL
        wp_send_json_success([
            'message' => __('Customer created successfully', 'quote-manager-system-for-woocommerce'),
            'customer_id' => $customer_id,
            'edit_url' => $edit_url
        ]);
    }

    /**
     * Generate PDF Preview
     */
    public function generate_pdf_preview()
    {
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-pdf-generator.php';
        $pdf_generator = new Quote_Manager_PDF_Generator();
        $pdf_generator->generate_pdf(false);
        exit;
    }

    /**
     * Generate PDF Download
     */
    public function generate_pdf_download()
    {
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-pdf-generator.php';
        $pdf_generator = new Quote_Manager_PDF_Generator();
        $pdf_generator->generate_pdf(true);
        exit;
    }

    /**
     * Send quote email with custom subject and message
     */
    public function send_email()
    {
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Access denied.']);
        }

        $quote_id = isset($_POST['quote_id']) ? intval($_POST['quote_id']) : 0;
        $subject = isset($_POST['subject']) ? sanitize_text_field($_POST['subject']) : '';
        $message = isset($_POST['message']) ? wp_kses_post($_POST['message']) : '';

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
    public function upload_attachment()
    {
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

        // Check file size limit (2MB = 2 * 1024 * 1024 bytes)
        $max_size = 2 * 1024 * 1024; // 2MB in bytes
        if ($_FILES['file']['size'] > $max_size) {
            wp_send_json_error(['message' => __('File size exceeds the 2MB limit.', 'quote-manager-system-for-woocommerce')]);
            return;
        }

        // Check maximum number of attachments (10)
        $max_attachments = 10;
        $attachments = get_post_meta($quote_id, '_quote_attachments', true);
        if (is_array($attachments) && count($attachments) >= $max_attachments) {
            wp_send_json_error(['message' => sprintf(__('Maximum number of attachments (%d) reached.', 'quote-manager-system-for-woocommerce'), $max_attachments)]);
            return;
        }

        // Check file type/extension for security
        $file = $_FILES['file'];
        $filename = sanitize_file_name($file['name']);
        $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // Define allowed file types
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];

        if (!in_array($file_extension, $allowed_extensions)) {
            wp_send_json_error(['message' => __('Invalid file type. Only JPG, JPEG, PNG and PDF files are allowed.', 'quote-manager-system-for-woocommerce')]);
            return;
        }

        // Additional MIME type check for better security
        $allowed_mime_types = [
            'image/jpeg',
            'image/png',
            'application/pdf'
        ];

        // Use wp_check_filetype for more reliable mime type detection
        $file_type_check = wp_check_filetype($filename, null);
        if (empty($file_type_check['type']) || !in_array($file_type_check['type'], $allowed_mime_types)) {
            wp_send_json_error(['message' => __('Invalid file type detected. Only JPG, JPEG, PNG and PDF files are allowed.', 'quote-manager-system-for-woocommerce')]);
            return;
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
                'type' => $file_type_check['type']
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to upload file.', 'quote-manager-system-for-woocommerce')]);
        }
    }

    /**
     * Delete attachment file
     */
    public function delete_attachment()
    {
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

        // Extract the filename from the URL
        $url_parts = parse_url($file_url);
        $path_parts = pathinfo($url_parts['path']);
        $filename = $path_parts['basename'];

        // Construct file path
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/quote-manager/attachments/' . $quote_id . '/' . $filename;

        // Check if file exists
        $file_exists = file_exists($file_path);

        // Try to delete the file if it exists
        $deletion_success = $file_exists ? @unlink($file_path) : false;

        // If the attachment is not yet saved in the database, we consider it a success
        // even though it's not found in the stored attachments
        $attachments = get_post_meta($quote_id, '_quote_attachments', true);
        if (!is_array($attachments)) {
            $attachments = [];
        }

        // Find and remove the attachment from the array if it exists
        $updated_attachments = [];
        $file_found_in_db = false;

        foreach ($attachments as $attachment) {
            if (isset($attachment['url']) && $attachment['url'] === $file_url) {
                $file_found_in_db = true;
                // Skip this attachment (removing it from the list)
                continue;
            }
            $updated_attachments[] = $attachment;
        }

        // Update the database only if we found the attachment in it
        if ($file_found_in_db) {
            update_post_meta($quote_id, '_quote_attachments', $updated_attachments);
        }

        // Determine response based on what happened
        if (!$file_exists) {
            // File doesn't exist but we should still report success
            wp_send_json_success([
                'message' => __('Attachment removed successfully.', 'quote-manager-system-for-woocommerce')
            ]);
        } else if ($deletion_success) {
            // File existed and was deleted successfully
            wp_send_json_success([
                'message' => __('Attachment deleted successfully.', 'quote-manager-system-for-woocommerce')
            ]);
        } else {
            // File existed but could not be deleted
            wp_send_json_success([
                'message' => __('Attachment removed, but file could not be deleted from server.', 'quote-manager-system-for-woocommerce'),
                'partial' => true
            ]);
        }
    }

    /**
     * AJAX handler for updating quote status
     */
    public function update_quote_status()
    {
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Access denied', 'quote-manager-system-for-woocommerce')]);
        }

        // Verify nonce
        if (!check_ajax_referer('quote_status_update', 'security', false)) {
            wp_send_json_error(['message' => __('Security check failed', 'quote-manager-system-for-woocommerce')]);
        }

        // Get parameters
        $quote_id = isset($_POST['quote_id']) ? intval($_POST['quote_id']) : 0;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

        if (!$quote_id || empty($status)) {
            wp_send_json_error(['message' => __('Invalid parameters', 'quote-manager-system-for-woocommerce')]);
        }

        // Verify the quote exists
        $quote = get_post($quote_id);
        if (!$quote || $quote->post_type !== 'customer_quote') {
            wp_send_json_error(['message' => __('Quote not found', 'quote-manager-system-for-woocommerce')]);
        }

        // Check if status is valid
        $valid_statuses = array_keys(Quote_Manager_System_For_Woocommerce::get_quote_statuses());
        if (!in_array($status, $valid_statuses)) {
            wp_send_json_error(['message' => __('Invalid status', 'quote-manager-system-for-woocommerce')]);
        }

        // Update the status
        update_post_meta($quote_id, '_quote_status', $status);

        // Delete PDF files if status is 'rejected'
        if ($status === Quote_Manager_System_For_Woocommerce::STATUS_REJECTED) {
            Quote_Manager_System_For_Woocommerce::delete_quote_pdf_files($quote_id);
        }

        // Return success
        wp_send_json_success([
            'message' => __('Status updated successfully', 'quote-manager-system-for-woocommerce'),
            'status' => $status,
            'status_label' => Quote_Manager_System_For_Woocommerce::get_status_label($status)
        ]);
    }

    /**
     * AJAX handler for checking expired quotes
     */
    public function check_expired_quotes()
    {
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Access denied', 'quote-manager-system-for-woocommerce')]);
        }
    
        // Verify nonce
        if (!check_ajax_referer('quote_expired_check', 'security', false)) {
            wp_send_json_error(['message' => __('Security check failed', 'quote-manager-system-for-woocommerce')]);
        }
    
        // Use the expiration handler class to check for expired quotes
        require_once QUOTE_MANAGER_PATH . 'includes/class-quote-expiration-handler.php';
        $handler = new Quote_Manager_Expiration_Handler();
        $expired_count = $handler->check_expired_quotes();
    
        // Return results
        wp_send_json_success([
            'expired_count' => $expired_count,
            'message' => sprintf(
                _n(
                    '%d quote has been marked as expired.',
                    '%d quotes have been marked as expired.',
                    $expired_count,
                    'quote-manager-system-for-woocommerce'
                ),
                $expired_count
            )
        ]);
    }
}