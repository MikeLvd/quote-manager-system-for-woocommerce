<?php
/**
 * Handle quote revisions
 *
 * @link       https://goldenbath.gr
 * @since      1.5.0
 *
 * @package    Quote_Manager_System_For_Woocommerce
 * @subpackage Quote_Manager_System_For_Woocommerce/includes
 */

class Quote_Manager_Revision_Manager {

    /**
     * Create a new revision for a quote
     *
     * @param int $quote_id The quote ID
     * @param string $trigger What triggered the revision (email, publish)
     * @return int|bool The revision ID or false on failure
     */
    public function create_revision($quote_id, $trigger = 'manual') {
        // Check if quote exists
        $quote = get_post($quote_id);
        if (!$quote || $quote->post_type !== 'customer_quote') {
            return false;
        }

        // Get quote data
        $quote_data = $this->get_quote_data($quote_id);
        
        // Create revision post
        $revision_id = wp_insert_post([
            'post_type'      => 'revision',
            'post_parent'    => $quote_id,
            'post_status'    => 'inherit',
            'post_author'    => get_current_user_id(),
            'post_title'     => sprintf(__('Revision for quote #%s', 'quote-manager-system-for-woocommerce'), $quote_id),
            'post_content'   => '',
            'post_mime_type' => 'quote/revision',
        ]);

        if (is_wp_error($revision_id)) {
            return false;
        }

        // Store revision meta
        add_post_meta($revision_id, '_quote_revision_data', $quote_data);
        add_post_meta($revision_id, '_quote_revision_trigger', $trigger);
        add_post_meta($revision_id, '_quote_revision_number', $this->get_next_revision_number($quote_id));
        
        // Update quote meta to reference latest revision
        update_post_meta($quote_id, '_latest_revision_id', $revision_id);
        
        return $revision_id;
    }

    /**
     * Get the next revision number for a quote
     *
     * @param int $quote_id The quote ID
     * @return int The next revision number
     */
    private function get_next_revision_number($quote_id) {
        // Get existing revisions
        $revisions = $this->get_quote_revisions($quote_id);
        return count($revisions) + 1;
    }

    /**
     * Get all revisions for a quote
     *
     * @param int $quote_id The quote ID
     * @return array The revision posts
     */
    public function get_quote_revisions($quote_id) {
        $args = [
            'post_parent'    => $quote_id,
            'post_type'      => 'revision',
            'post_mime_type' => 'quote/revision',
            'post_status'    => 'inherit',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];
        
        return get_posts($args);
    }

    /**
     * Get revision data
     *
     * @param int $revision_id The revision ID
     * @return array The revision data
     */
    public function get_revision_data($revision_id) {
        return get_post_meta($revision_id, '_quote_revision_data', true);
    }

    /**
     * Get quote data for revision
     *
     * @param int $quote_id The quote ID
     * @return array The quote data
     */
    private function get_quote_data($quote_id) {
        // Get quote post data
        $quote = get_post($quote_id);
        
        // Get all post meta
        $meta_keys = [
            '_customer_first_name',
            '_customer_last_name',
            '_customer_address',
            '_customer_area',
            '_customer_phone',
            '_customer_email',
            '_shipping_first_name',
            '_shipping_last_name',
            '_shipping_address',
            '_shipping_area',
            '_shipping_postcode',
            '_shipping_city',
            '_project_name',
            '_quote_products',
            '_quote_include_vat',
            '_quote_expiration_date',
            '_quote_expiration_days',
            '_quote_terms',
            '_quote_attachments'
        ];
        
        $meta_data = [];
        foreach ($meta_keys as $key) {
            $meta_data[$key] = get_post_meta($quote_id, $key, true);
        }
        
        // Return combined data
        return [
            'post' => [
                'post_title' => $quote->post_title,
                'post_date' => $quote->post_date,
                'post_status' => $quote->post_status
            ],
            'meta' => $meta_data
        ];
    }

    /**
     * Restore a quote from a revision
     *
     * @param int $quote_id The quote ID
     * @param int $revision_id The revision ID to restore
     * @return bool Success or failure
     */
    public function restore_revision($quote_id, $revision_id) {
        // Verify the revision belongs to this quote
        $revision = get_post($revision_id);
        if (!$revision || $revision->post_parent != $quote_id || $revision->post_mime_type !== 'quote/revision') {
            return false;
        }
        
        // Get revision data
        $revision_data = $this->get_revision_data($revision_id);
        if (empty($revision_data)) {
            return false;
        }
        
        // Update quote title if needed
        if (!empty($revision_data['post']['post_title'])) {
            wp_update_post([
                'ID' => $quote_id,
                'post_title' => $revision_data['post']['post_title']
            ]);
        }
        
        // Update quote meta data
        foreach ($revision_data['meta'] as $key => $value) {
            update_post_meta($quote_id, $key, $value);
        }
        
        // Create a new revision to record this restore action
        $this->create_revision($quote_id, 'restore');
        
        return true;
    }

    /**
     * Compare two revisions and get the differences
     *
     * @param int $revision_id_1 First revision ID
     * @param int $revision_id_2 Second revision ID
     * @return array Differences between revisions
     */
    public function compare_revisions($revision_id_1, $revision_id_2) {
        $revision_data_1 = $this->get_revision_data($revision_id_1);
        $revision_data_2 = $this->get_revision_data($revision_id_2);
        
        if (empty($revision_data_1) || empty($revision_data_2)) {
            return [];
        }
        
        $differences = [];
        
        // Compare meta data
        foreach ($revision_data_1['meta'] as $key => $value) {
            // Special handling for products array
            if ($key === '_quote_products' && is_array($value) && is_array($revision_data_2['meta'][$key])) {
                $product_diff = $this->compare_products_array($value, $revision_data_2['meta'][$key]);
                if (!empty($product_diff)) {
                    $differences['products'] = $product_diff;
                }
            } 
            // Special handling for attachments
            else if ($key === '_quote_attachments' && is_array($value) && is_array($revision_data_2['meta'][$key])) {
                $attachment_diff = $this->compare_attachments_array($value, $revision_data_2['meta'][$key]);
                if (!empty($attachment_diff)) {
                    $differences['attachments'] = $attachment_diff;
                }
            }
            // Regular field comparison
            else if ($value !== $revision_data_2['meta'][$key]) {
                $field_name = str_replace('_', ' ', ltrim($key, '_'));
                $differences['fields'][$field_name] = [
                    'old' => $revision_data_2['meta'][$key],
                    'new' => $value
                ];
            }
        }
        
        return $differences;
    }
    
    /**
     * Compare products arrays between revisions
     *
     * @param array $products_1 First products array
     * @param array $products_2 Second products array
     * @return array Differences in products
     */
    private function compare_products_array($products_1, $products_2) {
        $diff = [];
        
        // Create indexed arrays by product ID and title for easier comparison
        $indexed_1 = [];
        $indexed_2 = [];
        
        foreach ($products_1 as $product) {
            $key = !empty($product['id']) ? $product['id'] : md5($product['title'] . $product['sku']);
            $indexed_1[$key] = $product;
        }
        
        foreach ($products_2 as $product) {
            $key = !empty($product['id']) ? $product['id'] : md5($product['title'] . $product['sku']);
            $indexed_2[$key] = $product;
        }
        
        // Find added products (in 1 but not in 2)
        foreach ($indexed_1 as $key => $product) {
            if (!isset($indexed_2[$key])) {
                $diff['added'][] = $product;
            }
        }
        
        // Find removed products (in 2 but not in 1)
        foreach ($indexed_2 as $key => $product) {
            if (!isset($indexed_1[$key])) {
                $diff['removed'][] = $product;
            }
        }
        
        // Find modified products
        foreach ($indexed_1 as $key => $product_1) {
            if (isset($indexed_2[$key])) {
                $product_2 = $indexed_2[$key];
                $product_diff = [];
                
                // Compare each field
                foreach ($product_1 as $field => $value) {
                    if ($field === 'qty' && (int)$value !== (int)$product_2[$field]) {
                        $product_diff['qty'] = [
                            'old' => $product_2[$field],
                            'new' => $value
                        ];
                    }
                    else if ($field === 'discount' && (float)$value !== (float)$product_2[$field]) {
                        $product_diff['discount'] = [
                            'old' => $product_2[$field],
                            'new' => $value
                        ];
                    }
                    else if ($field === 'final_price_excl' && (float)$value !== (float)$product_2[$field]) {
                        $product_diff['price'] = [
                            'old' => $product_2[$field],
                            'new' => $value
                        ];
                    }
                }
                
                if (!empty($product_diff)) {
                    $product_diff['title'] = $product_1['title'];
                    $diff['modified'][] = $product_diff;
                }
            }
        }
        
        return $diff;
    }
    
    /**
     * Compare attachments arrays between revisions
     *
     * @param array $attachments_1 First attachments array
     * @param array $attachments_2 Second attachments array
     * @return array Differences in attachments
     */
    private function compare_attachments_array($attachments_1, $attachments_2) {
        $diff = [];
        
        // Create indexed arrays by filename for easier comparison
        $indexed_1 = [];
        $indexed_2 = [];
        
        if (is_array($attachments_1)) {
            foreach ($attachments_1 as $attachment) {
                if (isset($attachment['filename'])) {
                    $indexed_1[$attachment['filename']] = $attachment;
                }
            }
        }
        
        if (is_array($attachments_2)) {
            foreach ($attachments_2 as $attachment) {
                if (isset($attachment['filename'])) {
                    $indexed_2[$attachment['filename']] = $attachment;
                }
            }
        }
        
        // Find added attachments (in 1 but not in 2)
        foreach ($indexed_1 as $filename => $attachment) {
            if (!isset($indexed_2[$filename])) {
                $diff['added'][] = $attachment;
            }
        }
        
        // Find removed attachments (in 2 but not in 1)
        foreach ($indexed_2 as $filename => $attachment) {
            if (!isset($indexed_1[$filename])) {
                $diff['removed'][] = $attachment;
            }
        }
        
        return $diff;
    }
}