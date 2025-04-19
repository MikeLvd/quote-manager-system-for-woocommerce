<?php
/**
 * Register custom post type for Customer Quotes.
 *
 * @link       https://goldenbath.gr
 * @since      1.0.0
 *
 * @package    Quote_Manager_System_For_Woocommerce
 * @subpackage Quote_Manager_System_For_Woocommerce/includes
 */

class Quote_Manager_Post_Type {

    /**
     * Register custom post type for Customer Quotes.
     */
    public function register_post_type() {
        $labels = array(
            'name'               => __('Customer Quotes', 'quote-manager-system-for-woocommerce'),
            'singular_name'      => __('Customer Quote', 'quote-manager-system-for-woocommerce'),
            'add_new'            => __('New Quote', 'quote-manager-system-for-woocommerce'),
            'add_new_item'       => __('Add New Quote', 'quote-manager-system-for-woocommerce'),
            'edit_item'          => __('Edit Quote', 'quote-manager-system-for-woocommerce'),
            'new_item'           => __('New Quote', 'quote-manager-system-for-woocommerce'),
            'view_item'          => __('View Quote', 'quote-manager-system-for-woocommerce'),
            'search_items'       => __('Search Quotes', 'quote-manager-system-for-woocommerce'),
            'not_found'          => __('No Quotes found', 'quote-manager-system-for-woocommerce'),
            'not_found_in_trash' => __('No Quotes found in Trash', 'quote-manager-system-for-woocommerce'),
        );

        register_post_type('customer_quote', array(
            'labels'       => $labels,
            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => true,
            'menu_icon'    => 'dashicons-media-document',
            'supports'     => array('title'),
            'has_archive'  => false,
            'rewrite'      => false,
        ));
    }

    /**
     * Add columns to the quotes list table
     */
    public function set_custom_columns($columns) {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb']; // Checkbox
        $new_columns['quote_id'] = __('Number', 'quote-manager-system-for-woocommerce');
        $new_columns['title'] = __('Quote Name', 'quote-manager-system-for-woocommerce');
        $new_columns['customer'] = __('Customer', 'quote-manager-system-for-woocommerce');
        $new_columns['project_name'] = __('Project', 'quote-manager-system-for-woocommerce');
        $new_columns['expiration'] = __('Expiration Date', 'quote-manager-system-for-woocommerce');
        $new_columns['date'] = $columns['date']; // Keep original date column
        
        return $new_columns;
    }

    /**
     * Display content for custom columns
     */
    public function custom_column($column, $post_id) {
        switch ($column) {
            case 'quote_id':
                echo '#' . str_pad($post_id, 4, '0', STR_PAD_LEFT);
                break;
            
            case 'customer':
                $first_name = get_post_meta($post_id, '_customer_first_name', true);
                $last_name = get_post_meta($post_id, '_customer_last_name', true);
                if (!empty($first_name) || !empty($last_name)) {
                    echo esc_html($first_name . ' ' . $last_name);
                } else {
                    echo '-';
                }
                break;

            case 'project_name':
                $project_name = get_post_meta($post_id, '_project_name', true);
                if (!empty($project_name)) {
                    echo esc_html($project_name);
                } else {
                    echo '—';
                }
                break;
                
            case 'expiration':
                $expiration_date = get_post_meta($post_id, '_offer_expiration_date', true);
                if (!empty($expiration_date)) {
                    echo esc_html($expiration_date);
                } else {
                    $quote_post = get_post($post_id);
                    // Default to 30 days if not set
                    echo esc_html(date_i18n('d/m/Y', strtotime('+30 days', strtotime($quote_post->post_date))));
                }
                break;
        }
    }

    /**
     * Make columns sortable
     */
    public function sortable_columns($columns) {
        $columns['quote_id'] = 'ID';
        $columns['customer'] = 'customer';
        $columns['project_name'] = 'project_name';
        $columns['expiration'] = 'expiration';
        return $columns;
    }

    /**
     * Handle column sorting
     */
    public function column_orderby($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        if ($query->get('post_type') !== 'customer_quote') {
            return;
        }

        $orderby = $query->get('orderby');

        if ('customer' === $orderby) {
            $query->set('meta_key', '_customer_last_name');
            $query->set('orderby', 'meta_value');
        }

        if ('project_name' === $orderby) {
            $query->set('meta_key', '_project_name');
            $query->set('orderby', 'meta_value');
        }
        
        if ('expiration' === $orderby) {
            $query->set('meta_key', '_offer_expiration_date');
            $query->set('orderby', 'meta_value');
        }
    }
}