<?php
/**
 * Handle metaboxes for the Quote post type
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

class Quote_Manager_Metaboxes
{

    /**
     * Register meta boxes for Customer Quote post type.
     */
    public function add_meta_boxes()
    {
        add_meta_box(
            'customer_info_meta',
            __('🧾 Customer & Shipping Details', 'quote-manager-system-for-woocommerce'),
            array($this, 'render_customer_info_meta'),
            'customer_quote',
            'normal',
            'high'
        );

        add_meta_box(
            'quote_products_meta',
            __('🛒 Quote Products', 'quote-manager-system-for-woocommerce'),
            array($this, 'render_quote_products_meta'),
            'customer_quote',
            'normal',
            'high'
        );

        add_meta_box(
            'quote_actions_meta',
            __('📤 Quote Actions', 'quote-manager-system-for-woocommerce'),
            array($this, 'render_quote_actions_meta'),
            'customer_quote',
            'side',
            'core'
        );

        add_meta_box(
            'quote_tools_meta',
            __('⚙️ Quote Tools', 'quote-manager-system-for-woocommerce'),
            array($this, 'render_quote_tools_meta'),
            'customer_quote',
            'side',
            'high'
        );

        add_meta_box(
            'quote_internal_meta',
            __('🔐 Internal Quote Information', 'quote-manager-system-for-woocommerce'),
            array($this, 'render_quote_internal_meta'),
            'customer_quote',
            'normal',
            'default'
        );

        add_meta_box(
            'quote_email_logs_meta',
            __('📧 Email History', 'quote-manager-system-for-woocommerce'),
            array($this, 'render_email_logs_meta'),
            'customer_quote',
            'normal',
            'default'
        );

        add_meta_box(
            'quote_terms_meta',
            __('📝 Terms & Conditions', 'quote-manager-system-for-woocommerce'),
            array($this, 'render_quote_terms_meta'),
            'customer_quote',
            'normal',
            'default'
        );

        add_meta_box(
            'quote_attachments_meta',
            __('📎 Attachments', 'quote-manager-system-for-woocommerce'),
            array($this, 'render_quote_attachments_meta'),
            'customer_quote',
            'normal',
            'default'
        );
    }

    /**
     * Render the Customer Info & Shipping meta box.
     */
    public function render_customer_info_meta($post)
    {
        // Get all meta in a single query for better performance
        $post_meta = get_post_meta($post->ID);

        // Helper to get and escape meta value.
        $get_meta = function ($key) use ($post_meta) {
            $full_key = '_' . $key;
            return isset($post_meta[$full_key]) ? esc_attr($post_meta[$full_key][0]) : '';
        };

        // Field icons mapping
        $field_icons = array(
            'first' => '👤',
            'last' => '👤',
            'name' => '👤',
            'company' => '🏢',
            'address' => '🏠',
            'area' => '📍',
            'postcode' => '🏷️',
            'zip' => '🏷️',
            'city' => '🏙️',
            'country' => '🌎',
            'state' => '🔍',
            'county' => '🔍',
            'region' => '🔍',
            'phone' => '📞',
            'email' => '✉️',
            'project' => '🏗️'
        );

        // Helper function to get icon for a field
        $get_icon = function ($field_name) use ($field_icons) {
            foreach ($field_icons as $key => $icon) {
                if (stripos($field_name, $key) !== false) {
                    return $icon . ' ';
                }
            }
            return '🔹 '; // Default icon
        };

        // Get allowed countries once
        $allowed_countries = $this->get_allowed_countries();

        // Main wrapper
        echo '<div class="quote-address-container">';

        // Billing column
        echo '<div class="quote-address-column">';
        echo '<div class="quote-address-heading">';
        echo '<h3>' . esc_html__('Billing', 'quote-manager-system-for-woocommerce') . '</h3>';
        echo '<a href="#" id="load-billing-address">' . esc_html__('Load billing address', 'quote-manager-system-for-woocommerce') . '</a>';
        echo '</div>';

        // First and Last name - side by side
        echo '<div class="quote-field-row">';

        echo '<div class="quote-field-col">';
        echo '<label for="customer_first_name" class="billing-label">' . $get_icon('first_name') . esc_html__('First name', 'quote-manager-system-for-woocommerce') . '</label>';
        echo '<input type="text" class="quote-input" id="customer_first_name" name="customer_first_name" value="' . esc_attr($get_meta('customer_first_name')) . '" />';
        echo '</div>';

        echo '<div class="quote-field-col">';
        echo '<label for="customer_last_name" class="billing-label">' . $get_icon('last_name') . esc_html__('Last name', 'quote-manager-system-for-woocommerce') . '</label>';
        echo '<input type="text" class="quote-input" id="customer_last_name" name="customer_last_name" value="' . esc_attr($get_meta('customer_last_name')) . '" />';
        echo '</div>';

        echo '</div>'; // End first/last name row

        // Company
        echo '<div class="quote-field">';
        echo '<label for="customer_company" class="billing-label">' . $get_icon('company') . esc_html__('Company', 'quote-manager-system-for-woocommerce') . '</label>';
        echo '<input type="text" class="quote-input" id="customer_company" name="customer_company" value="' . esc_attr($get_meta('customer_company')) . '" />';
        echo '</div>';

        // Address lines
        echo '<div class="quote-field">';
        echo '<label for="customer_address" class="billing-label">' . $get_icon('address') . esc_html__('Address', 'quote-manager-system-for-woocommerce') . '</label>';
        echo '<input type="text" class="quote-input" id="customer_address" name="customer_address" value="' . esc_attr($get_meta('customer_address')) . '" />';
        echo '</div>';

        // City and postal code - side by side
        echo '<div class="quote-field-row">';

        echo '<div class="quote-field-col">';
        echo '<label for="customer_city" class="billing-label">' . $get_icon('city') . esc_html__('City', 'quote-manager-system-for-woocommerce') . '</label>';
        echo '<input type="text" class="quote-input" id="customer_city" name="customer_city" value="' . esc_attr($get_meta('customer_area')) . '" />';
        echo '</div>';

        echo '<div class="quote-field-col">';
        echo '<label for="customer_postcode" class="billing-label">' . $get_icon('postcode') . esc_html__('Postcode', 'quote-manager-system-for-woocommerce') . '</label>';
        echo '<input type="text" class="quote-input" id="customer_postcode" name="customer_postcode" value="' . esc_attr($get_meta('customer_postcode')) . '" />';
        echo '</div>';

        echo '</div>'; // End city/postcode row

        // Country/Region and State - side by side
        echo '<div class="quote-field-row">';

        echo '<div class="quote-field-col">';
        echo '<label for="customer_country" class="billing-label">' . $get_icon('country') . esc_html__('Country', 'quote-manager-system-for-woocommerce') . '</label>';
        echo '<select class="quote-select" id="customer_country" name="customer_country">';

        $selected_country = $get_meta('customer_country') ?: 'GR';
        foreach ($allowed_countries as $code => $name) {
            echo '<option value="' . esc_attr($code) . '" ' . selected($selected_country, $code, false) . '>' . esc_html($name) . '</option>';
        }

        echo '</select>';
        echo '</div>';

        echo '<div class="quote-field-col">';
        echo '<label for="customer_state" class="billing-label">' . $get_icon('state') . esc_html__('State', 'quote-manager-system-for-woocommerce') . '</label>';

        $selected_country = $get_meta('customer_country') ?: 'GR';
        $selected_state = $get_meta('customer_state');
        $states = WC()->countries->get_states($selected_country);

        if (is_array($states) && !empty($states)) {
            echo '<select class="quote-select" id="customer_state" name="customer_state">';
            foreach ($states as $code => $name) {
                echo '<option value="' . esc_attr($code) . '" ' . selected($selected_state, $code, false) . '>' . esc_html($name) . '</option>';
            }
            echo '</select>';
        } else {
            echo '<input type="text" class="quote-input" id="customer_state" name="customer_state" value="' . esc_attr($selected_state) . '" />';
        }

        echo '</div>';

        echo '</div>'; // End country/state row

        // Email and phone - side by side
        echo '<div class="quote-field-row">';

        echo '<div class="quote-field-col">';
        echo '<label for="customer_email" class="billing-label">' . $get_icon('email') . esc_html__('Email address', 'quote-manager-system-for-woocommerce') . '</label>';
        echo '<input type="email" class="quote-input" id="customer_email" name="customer_email" value="' . esc_attr($get_meta('customer_email')) . '" />';
        echo '</div>';

        echo '<div class="quote-field-col">';
        echo '<label for="customer_phone" class="billing-label">' . $get_icon('phone') . esc_html__('Phone', 'quote-manager-system-for-woocommerce') . '</label>';
        echo '<input type="text" class="quote-input" id="customer_phone" name="customer_phone" value="' . esc_attr($get_meta('customer_phone')) . '" />';
        echo '</div>';

        echo '</div>'; // End email/phone row

        echo '</div>'; // End billing column

        // Shipping column
        echo '<div class="quote-address-column">';
        echo '<div class="quote-address-heading">';
        echo '<h3>' . esc_html__('Shipping', 'quote-manager-system-for-woocommerce') . '</h3>';
        echo '<a href="#" id="copy-billing-address">' . esc_html__('Copy billing address', 'quote-manager-system-for-woocommerce') . '</a>';
        echo '</div>';

        // First and Last name - side by side
        echo '<div class="quote-field-row">';

        echo '<div class="quote-field-col">';
        echo '<label for="shipping_first_name" class="shipping-label">' . $get_icon('first_name') . esc_html__('First name', 'quote-manager-system-for-woocommerce') . '</label>';
        echo '<input type="text" class="quote-input" id="shipping_first_name" name="shipping_first_name" value="' . esc_attr($get_meta('shipping_first_name')) . '" />';
        echo '</div>';

        echo '<div class="quote-field-col">';
        echo '<label for="shipping_last_name" class="shipping-label">' . $get_icon('last_name') . esc_html__('Last name', 'quote-manager-system-for-woocommerce') . '</label>';
        echo '<input type="text" class="quote-input" id="shipping_last_name" name="shipping_last_name" value="' . esc_attr($get_meta('shipping_last_name')) . '" />';
        echo '</div>';

        echo '</div>'; // End first/last name row

        // Company
        echo '<div class="quote-field">';
        echo '<label for="shipping_company" class="shipping-label">' . $get_icon('company') . esc_html__('Company', 'quote-manager-system-for-woocommerce') . '</label>';
        echo '<input type="text" class="quote-input" id="shipping_company" name="shipping_company" value="' . esc_attr($get_meta('shipping_company')) . '" />';
        echo '</div>';

        // Address lines
        echo '<div class="quote-field">';
        echo '<label for="shipping_address" class="shipping-label">' . $get_icon('address') . esc_html__('Address', 'quote-manager-system-for-woocommerce') . '</label>';
        echo '<input type="text" class="quote-input" id="shipping_address" name="shipping_address" value="' . esc_attr($get_meta('shipping_address')) . '" />';
        echo '</div>';

        // City and postal code - side by side
        echo '<div class="quote-field-row">';

        echo '<div class="quote-field-col">';
        echo '<label for="shipping_city" class="shipping-label">' . $get_icon('city') . esc_html__('City', 'quote-manager-system-for-woocommerce') . '</label>';
        echo '<input type="text" class="quote-input" id="shipping_city" name="shipping_city" value="' . esc_attr($get_meta('shipping_area')) . '" />';
        echo '</div>';

        echo '<div class="quote-field-col">';
        echo '<label for="shipping_postcode" class="shipping-label">' . $get_icon('postcode') . esc_html__('Postcode', 'quote-manager-system-for-woocommerce') . '</label>';
        echo '<input type="text" class="quote-input" id="shipping_postcode" name="shipping_postcode" value="' . esc_attr($get_meta('shipping_postcode')) . '" />';
        echo '</div>';

        echo '</div>'; // End city/postcode row

        // Country/Region and State - side by side
        echo '<div class="quote-field-row">';

        echo '<div class="quote-field-col">';
        echo '<label for="shipping_country" class="shipping-label">' . $get_icon('country') . esc_html__('Country', 'quote-manager-system-for-woocommerce') . '</label>';
        echo '<select class="quote-select" id="shipping_country" name="shipping_country">';

        $selected_country = $get_meta('shipping_country') ?: 'GR';
        foreach ($allowed_countries as $code => $name) {
            echo '<option value="' . esc_attr($code) . '" ' . selected($selected_country, $code, false) . '>' . esc_html($name) . '</option>';
        }

        echo '</select>';
        echo '</div>';

        echo '<div class="quote-field-col">';
        echo '<label for="shipping_state" class="shipping-label">' . $get_icon('state') . esc_html__('State', 'quote-manager-system-for-woocommerce') . '</label>';

        $selected_country = $get_meta('shipping_country') ?: 'GR';
        $selected_state = $get_meta('shipping_state');
        $states = WC()->countries->get_states($selected_country);

        if (is_array($states) && !empty($states)) {
            echo '<select class="quote-select" id="shipping_state" name="shipping_state">';
            foreach ($states as $code => $name) {
                echo '<option value="' . esc_attr($code) . '" ' . selected($selected_state, $code, false) . '>' . esc_html($name) . '</option>';
            }
            echo '</select>';
        } else {
            echo '<input type="text" class="quote-input" id="shipping_state" name="shipping_state" value="' . esc_attr($selected_state) . '" />';
        }

        echo '</div>';

        echo '</div>'; // End country/state row

        // Phone
        echo '<div class="quote-field">';
        echo '<label for="shipping_phone" class="shipping-label">' . $get_icon('phone') . esc_html__('Phone', 'quote-manager-system-for-woocommerce') . '</label>';
        echo '<input type="text" class="quote-input" id="shipping_phone" name="shipping_phone" value="' . esc_attr($get_meta('shipping_phone')) . '" />';
        echo '</div>';

        echo '</div>'; // End shipping column

        echo '</div>'; // End address container

        // Add "Save as Customer" button with WooCommerce styling
        echo '<div class="save-as-customer-container">';
		
        // Add customer search field
        echo '<div class="search-customer-field">';
        echo '<div class="customer-search-wrap">';
        echo '<input type="text" id="search-customer" class="customer-search-input" placeholder="' . esc_attr__('Search customers by name, email or company...', 'quote-manager-system-for-woocommerce') . '" autocomplete="off" />';
        echo '<div id="customer-suggestions" class="customer-suggestions"></div>';
        echo wp_nonce_field('quote_manager_search_customers', 'search_customers_nonce', false, false);
        echo '</div>';
        echo '</div>';

        echo '<button type="button" id="save-as-customer-btn" class="button button-primary save-customer-button">' .
            '<span class="dashicons dashicons-admin-users"></span> ' .
            esc_html__('Save as New Customer', 'quote-manager-system-for-woocommerce') .
            '</button>';
        echo '<span id="save-customer-status"></span>';
        echo '</div>';

        // Project details section
        echo '<div class="project-details">';
        echo '<h3>' . esc_html__('Project Details', 'quote-manager-system-for-woocommerce') . '</h3>';
        echo '<div class="quote-field">';
        echo '<label for="project_name">' . $get_icon('project') . esc_html__('Project Name', 'quote-manager-system-for-woocommerce') . '</label>';
        echo '<input type="text" class="quote-input" id="project_name" name="project_name" value="' . esc_attr($get_meta('project_name')) . '" />';
        echo '</div>';
        echo '</div>';

        // Nonce field for customer data
        wp_nonce_field('quote_manager_save_customer', 'quote_manager_nonce_customer', false);
    }

    /**
     * Get allowed countries from WooCommerce settings
     *
     * @return array Allowed countries
     */
    private function get_allowed_countries()
    {
        // Get only allowed countries
        $countries_setting = get_option('woocommerce_allowed_countries');

        if ($countries_setting === 'specific') {
            $specific_countries = get_option('woocommerce_specific_allowed_countries', array());
            if (!empty($specific_countries)) {
                return array_intersect_key(WC()->countries->get_countries(), array_flip($specific_countries));
            }
        } elseif ($countries_setting === 'all_except') {
            $excepted_countries = get_option('woocommerce_all_except_countries', array());
            return array_diff_key(WC()->countries->get_countries(), array_flip($excepted_countries));
        }

        // If set to 'all' or no specific setting, include all countries
        return WC()->countries->get_countries();
    }

    /**
     * Render the Quote Products meta box.
     */
    public function render_quote_products_meta($post)
    {
        // Get all meta in a single query
        $post_meta = get_post_meta($post->ID);

        $products = isset($post_meta['_quote_products']) ? maybe_unserialize($post_meta['_quote_products'][0]) : array();
        if (!is_array($products)) {
            $products = array();
        }

        $include_vat = isset($post_meta['_quote_include_vat']) && $post_meta['_quote_include_vat'][0] === '1';
        $tax_rate = 24;

        // VAT status for data attribute
        $vat_status = $include_vat ? 'enabled' : 'disabled';

        echo '<div class="quote-toolbar">';
        echo '<div class="quote-search-wrap">';
        echo '<input type="text" id="quote-product-search" class="quote-search-input" placeholder="' . esc_attr__('🔍 Search products...', 'quote-manager-system-for-woocommerce') . '" autocomplete="off" />';
        echo '<div id="quote-product-suggestions" class="quote-suggestions"></div>';
        echo '</div>';
        echo '<button type="button" class="add-manual-product">' . esc_html__('✚ Add Product', 'quote-manager-system-for-woocommerce') . '</button>';
        echo '</div>';

        // Add data-vat-status to the table
        echo '<div class="quote-product-table-wrapper">';
        echo '<table class="quote-product-table" id="quote-products-table" data-vat-status="' . $vat_status . '">';
        echo '<thead><tr>';
        echo '<th class="quote-th-number">#</th>';
        echo '<th class="quote-th-image">' . esc_html__('Image', 'quote-manager-system-for-woocommerce') . '</th>';
        echo '<th class="quote-th-title">' . esc_html__('Title', 'quote-manager-system-for-woocommerce') . '</th>';
        echo '<th class="quote-th-sku">' . esc_html__('SKU', 'quote-manager-system-for-woocommerce') . '</th>';
        echo '<th class="quote-th-purchase">' . esc_html__('Cost', 'quote-manager-system-for-woocommerce') . '</th>';
        echo '<th class="quote-th-listprice">' . esc_html__('Price', 'quote-manager-system-for-woocommerce') . '</th>';
        echo '<th class="quote-th-discount">' . esc_html__('Discount (%)', 'quote-manager-system-for-woocommerce') . '</th>';
        echo '<th class="quote-th-final-excl">' . esc_html__('Value', 'quote-manager-system-for-woocommerce') . '</th>';
        // Always include VAT column, but hide with CSS if needed
        echo '<th class="quote-th-final-incl" ' . (!$include_vat ? 'style="display:none;"' : '') . '>' . esc_html__('Value (incl. VAT)', 'quote-manager-system-for-woocommerce') . '</th>';
        echo '<th class="quote-th-qty">' . esc_html__('Quantity', 'quote-manager-system-for-woocommerce') . '</th>';
        echo '<th class="quote-th-total">' . esc_html__('Total (€)', 'quote-manager-system-for-woocommerce') . '</th>';
        echo '<th class="quote-th-remove">✖</th>';
        echo '</tr></thead><tbody id="quote-products-sortable">';

        $i = 0;
        foreach ($products as $prod) {
            $num = $i + 1;
            $product_id = isset($prod['id']) ? intval($prod['id']) : 0;

            // Format purchase cost
            $purchase_cost = '';
            if ($product_id > 0) {
                $meta_cost = get_post_meta($product_id, '_wc_cog_cost', true);
                $purchase_cost = $meta_cost !== '' ? floatval($meta_cost) : '';
            }
            if (isset($prod['purchase_price']) && $prod['purchase_price'] !== '') {
                $purchase_cost = floatval($prod['purchase_price']);
            }
            $formatted_purchase_cost = !empty($purchase_cost) ? wc_format_localized_price($purchase_cost) : '';

            // Format other price fields
            $list_price_value = $prod['list_price'] ?? '';
            $formatted_list_price = !empty($list_price_value) ? wc_format_localized_price($list_price_value) : '';

            $discount_value = $prod['discount'] ?? '';
            $formatted_discount = !empty($discount_value) ? wc_format_localized_price($discount_value) : '';

            $final_price_excl_value = $prod['final_price_excl'] ?? '';
            $formatted_final_price_excl = !empty($final_price_excl_value) ? wc_format_localized_price($final_price_excl_value) : '';

            $final_price_incl_value = $prod['final_price_incl'] ?? '';
            $formatted_final_price_incl = !empty($final_price_incl_value) ? wc_format_localized_price($final_price_incl_value) : '';

            echo '<tr class="quote-product-row">';
            echo '<td class="quote-td-number">' . $num . '#</td>';
            echo '<td class="quote-td-image">';
            echo '<div class="quote-img-wrapper">';
            $image_src = $prod['image'] ?? '';
            echo '<img src="' . esc_url($image_src) . '" class="quote-img quote-img-selectable" />';
            echo '<input type="hidden" name="quote_products[' . $i . '][image]" value="' . esc_attr($image_src) . '" class="quote-img-input" />';
            echo '</div></td>';

            echo '<td class="quote-td-title"><input type="text" class="quote-input" name="quote_products[' . $i . '][title]" value="' . esc_attr($prod['title'] ?? '') . '" /></td>';
            echo '<td class="quote-td-sku"><input type="text" class="quote-input" name="quote_products[' . $i . '][sku]" value="' . esc_attr($prod['sku'] ?? '') . '" /></td>';
            echo '<td class="quote-td-purchase"><input type="text" class="quote-input" name="quote_products[' . $i . '][purchase_price]" value="' . esc_attr($formatted_purchase_cost) . '" placeholder="' . esc_attr__('Cost', 'quote-manager-system-for-woocommerce') . '" /></td>';
            echo '<td class="quote-td-listprice"><input type="text" class="quote-input" name="quote_products[' . $i . '][list_price]" value="' . esc_attr($formatted_list_price) . '" /></td>';
            echo '<td class="quote-td-discount"><input type="text" class="quote-input" name="quote_products[' . $i . '][discount]" value="' . esc_attr($formatted_discount) . '" /></td>';
            echo '<td class="quote-td-final-excl"><input type="text" class="quote-input" name="quote_products[' . $i . '][final_price_excl]" value="' . esc_attr($formatted_final_price_excl) . '" /></td>';
            // Always include VAT field, but hide with CSS if needed
            echo '<td class="quote-td-final-incl" ' . (!$include_vat ? 'style="display:none;"' : '') . '>';
            echo '<input type="text" class="quote-input" name="quote_products[' . $i . '][final_price_incl]" value="' . esc_attr($formatted_final_price_incl) . '" readonly />';
            echo '</td>';
            echo '<td class="quote-td-qty"><input type="number" class="quote-input" name="quote_products[' . $i . '][qty]" value="' . esc_attr($prod['qty'] ?? '') . '" /></td>';
            echo '<td class="quote-td-total quote-line-total">0.00€</td>';
            echo '<td class="quote-td-remove">';
            echo '<span class="remove-row" title="' . esc_attr__('Remove', 'quote-manager-system-for-woocommerce') . '">✖</span>';
            echo '<input type="hidden" name="quote_products[' . $i . '][id]" value="' . esc_attr($product_id) . '" />';
            echo '</td>';
            echo '</tr>';
            $i++;
        }

        echo '</tbody><tfoot>';
        echo '<tr class="quote-summary-row">';
        // For Subtotal: colspan will be 9 if no VAT, 10 if VAT
        echo '<td colspan="' . (!$include_vat ? '9' : '10') . '" class="quote-td-label">' . esc_html__('Subtotal:', 'quote-manager-system-for-woocommerce') . '</td>';
        echo '<td class="quote-td-subtotal">0.00€</td>';
        echo '</tr>';

        // VAT row - only shows when include_vat is enabled
        echo '<tr class="quote-summary-row vat-row" ' . (!$include_vat ? 'style="display:none;"' : '') . '>';
        echo '<td colspan="' . (!$include_vat ? '9' : '10') . '" class="quote-td-label">' . esc_html__('VAT:', 'quote-manager-system-for-woocommerce') . '</td>';
        echo '<td class="quote-td-vat">0.00€</td>';
        echo '</tr>';

        echo '<tr class="quote-summary-total">';
        echo '<td colspan="' . (!$include_vat ? '9' : '10') . '" class="quote-td-label"><strong>' . esc_html__('Total:', 'quote-manager-system-for-woocommerce') . '</strong></td>';
        echo '<td class="quote-td-total-all"><strong>0.00€</strong></td>';
        echo '</tr>';
        echo '</tfoot></table>';
        echo '</div>';

        wp_nonce_field('quote_manager_save_products', 'quote_manager_nonce_products', false);
    }

    /**
     * Render the Internal Information meta box.
     */
    public function render_quote_internal_meta($post)
    {
        $post_meta = get_post_meta($post->ID);

        $products = isset($post_meta['_quote_products']) ? maybe_unserialize($post_meta['_quote_products'][0]) : array();
        if (!is_array($products)) {
            $products = array();
        }

        $include_vat = isset($post_meta['_quote_include_vat']) && $post_meta['_quote_include_vat'][0] === '1';
        $tax_rate = 24;
        $tax_multiplier = 1 + ($tax_rate / 100);

        echo '<div class="quote-section">';
        echo '<h4 class="quote-section-title">' . esc_html__('🔐 Internal Quote Information', 'quote-manager-system-for-woocommerce') . '</h4>';
        echo '<table class="quote-internal-table" id="quote-internal-table" style="width:100%; border-collapse:collapse;">';
        echo '<thead><tr>';
        echo '<th class="quote-th-num">' . esc_html__('#', 'quote-manager-system-for-woocommerce') . '</th>';
        echo '<th class="quote-th-title">' . esc_html__('Product', 'quote-manager-system-for-woocommerce') . '</th>';
        echo '<th class="quote-th-cost">' . esc_html__('Purchase Price (excl. VAT)', 'quote-manager-system-for-woocommerce') . '</th>';
        echo '<th class="quote-th-qty">' . esc_html__('Quantity', 'quote-manager-system-for-woocommerce') . '</th>';
        echo '<th class="quote-th-total-cost">' . esc_html__('Total Cost', 'quote-manager-system-for-woocommerce') . '</th>';
        echo '<th class="quote-th-total-price">' . esc_html__('Total Price', 'quote-manager-system-for-woocommerce') . '</th>';
        echo '<th class="quote-th-markup">' . esc_html__('% Markup', 'quote-manager-system-for-woocommerce') . '</th>';
        echo '<th class="quote-th-margin">' . esc_html__('% Margin', 'quote-manager-system-for-woocommerce') . '</th>';
        echo '</tr></thead><tbody>';

        $i = 0;
        $total_cost = 0;
        $total_final_price = 0;
        $total_markup = 0;
        $total_margin = 0;
        $markup_count = 0;

        foreach ($products as $prod) {
            $num = $i + 1;

            // Purchase price without VAT
            $cost_raw = isset($prod['purchase_price']) ? floatval($prod['purchase_price']) : 0;

            // Final price without VAT
            $final_price_excl = isset($prod['final_price_excl']) ? floatval($prod['final_price_excl']) : 0;

            // Quantity
            $qty = isset($prod['qty']) ? intval($prod['qty']) : 1;

            // Calculate totals based on quantity
            $total_cost_item = $cost_raw * $qty;
            $total_price_item = $final_price_excl * $qty;

            $product_title = !empty($prod['title']) ? $prod['title'] : '';

            $markup_percent = 0;
            $margin_percent = 0;

            if ($cost_raw > 0 && $final_price_excl > 0) {
                // Calculate markup and margin based on unit price
                $markup_percent = (($final_price_excl - $cost_raw) / $cost_raw) * 100;
                $margin_percent = (($final_price_excl - $cost_raw) / $final_price_excl) * 100;

                // Add to totals
                $total_cost += $total_cost_item;
                $total_final_price += $total_price_item;
                $total_markup += $markup_percent;
                $total_margin += $margin_percent;
                $markup_count++;
            }

            // Display with VAT (for display only)
            $display_cost = number_format($cost_raw, 2, '.', '') . '€';
            if ($include_vat) {
                $with_vat = number_format($cost_raw * $tax_multiplier, 2, '.', '') . '€';
                $display_cost .= ' <small style="color:#888;">(incl. VAT: ' . $with_vat . ')</small>';
            }

            // Store data as attributes for JavaScript
            echo '<tr class="quote-summary-row" style="border-bottom:1px solid #ccc;" 
                data-product-id="' . (isset($prod['id']) ? esc_attr($prod['id']) : '0') . '"
                data-cost="' . esc_attr($cost_raw) . '"
                data-price="' . esc_attr($final_price_excl) . '"
                data-qty="' . esc_attr($qty) . '">';
            echo '<td class="quote-td-num">' . esc_html($num) . '</td>';
            echo '<td class="quote-td-title">' . esc_html($product_title) . '</td>';
            echo '<td class="quote-td-cost">' . $display_cost . '</td>';
            echo '<td class="quote-td-qty">' . esc_html($qty) . '</td>';
            echo '<td class="quote-td-total-cost">' . number_format($total_cost_item, 2, '.', '') . '€</td>';
            echo '<td class="quote-td-total-price">' . number_format($total_price_item, 2, '.', '') . '€</td>';
            echo '<td class="quote-td-markup">' . ($markup_percent > 0 ? number_format($markup_percent, 2, '.', '') . '%' : '-') . '</td>';
            echo '<td class="quote-td-margin">' . ($margin_percent > 0 ? number_format($margin_percent, 2, '.', '') . '%' : '-') . '</td>';
            echo '</tr>';

            $i++;
        }

        // Calculate averages and total profit
        $avg_markup = $markup_count > 0 ? $total_markup / $markup_count : 0;
        $avg_margin = $markup_count > 0 ? $total_margin / $markup_count : 0;
        $total_profit = $total_final_price - $total_cost;

        // Summary row
        echo '<tr class="quote-summary-total" style="font-weight:bold; background:#f0f0f0;">';
        echo '<td colspan="2" class="quote-td-label" style="text-align:right;">' . esc_html__('Totals:', 'quote-manager-system-for-woocommerce') . '</td>';
        echo '<td class="quote-td-cost-summary">-</td>';
        echo '<td class="quote-td-qty-summary">-</td>';
        echo '<td class="quote-td-cost-total">' . number_format($total_cost, 2, '.', '') . '€</td>';
        echo '<td class="quote-td-price-total">' . number_format($total_final_price, 2, '.', '') . '€</td>';
        echo '<td class="quote-td-markup-avg">' . number_format($avg_markup, 2, '.', '') . '%</td>';
        echo '<td class="quote-td-margin-avg">' . number_format($avg_margin, 2, '.', '') . '%</td>';
        echo '</tr>';

        // Total net profit row
        echo '<tr class="quote-summary-profit" style="font-weight:bold; background:#e8f9e8;">';
        echo '<td colspan="5" class="quote-td-profit-label" style="text-align:right;">' . esc_html__('Total Net Profit:', 'quote-manager-system-for-woocommerce') . '</td>';
        echo '<td colspan="3" class="quote-td-profit">' . number_format($total_profit, 2, '.', '') . '€</td>';
        echo '</tr>';

        echo '</tbody></table>';
        echo '</div>';
        echo '<p class="description">' . esc_html__('*The above information is only visible to administrators and is not included in the quote PDF.', 'quote-manager-system-for-woocommerce') . '</p>';
    }

    /**
     * Render the Quote Actions meta box (Preview / Download PDF).
     */
    public function render_quote_actions_meta($post)
    {
        // Get current status
        $current_status = get_post_meta($post->ID, '_quote_status', true);
        if (empty($current_status)) {
            $current_status = Quote_Manager_System_For_Woocommerce::STATUS_DRAFT;
        }

        // Status dropdown
        echo '<div class="quote-status-section">';
        echo '<p><label style="font-weight:600;">' . esc_html__('Quote Status:', 'quote-manager-system-for-woocommerce') . '</label></p>';

        echo '<select name="quote_status" id="quote_status" style="width:100%; margin-bottom:10px;">';

        $statuses = Quote_Manager_System_For_Woocommerce::get_quote_statuses();
        foreach ($statuses as $status_key => $status_label) {
            echo '<option value="' . esc_attr($status_key) . '" ' . selected($current_status, $status_key, false) . '>' . esc_html($status_label) . '</option>';
        }

        echo '</select>';
        echo '<div id="status-updated-message" style="display:none; margin-bottom:10px; padding:5px; background-color:#f0f8ff; border:1px solid #c3d9ea; border-radius:3px; font-size:12px; color:#0073aa;"></div>';
        echo '</div>';

        echo '<hr style="margin:10px 0;">';

        // VAT Checkbox
        $checked = get_post_meta($post->ID, '_quote_include_vat', true) === '1' ? 'checked' : '';
        echo '<p><label style="font-weight:600;"><input type="checkbox" name="quote_include_vat" value="1" ' . $checked . ' /> ' . esc_html__('Include VAT in quote', 'quote-manager-system-for-woocommerce') . '</label></p>';

        // Expiration date selection
        echo '<hr style="margin:10px 0;">';
        echo '<p><label style="font-weight:600;">' . esc_html__('Quote Validity:', 'quote-manager-system-for-woocommerce') . '</label></p>';

        // Get saved expiration date or create default
        $expiration_date = get_post_meta($post->ID, '_quote_expiration_date', true);
        $quote_expiration_days = get_post_meta($post->ID, '_quote_expiration_days', true);

        if (empty($quote_expiration_days)) {
            $quote_expiration_days = '30'; // Default to 30 days
        }

        // Create dropdown
        echo '<select name="quote_expiration_days" id="quote_expiration_days" style="width:100%; margin-bottom:10px;" >';
        $expiration_options = array(
            '5' => __('5 Days', 'quote-manager-system-for-woocommerce'),
            '15' => __('15 Days', 'quote-manager-system-for-woocommerce'),
            '30' => __('30 Days', 'quote-manager-system-for-woocommerce'),
            '45' => __('45 Days', 'quote-manager-system-for-woocommerce'),
            '60' => __('60 Days', 'quote-manager-system-for-woocommerce'),
            'custom' => __('Custom Date', 'quote-manager-system-for-woocommerce')
        );

        foreach ($expiration_options as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($quote_expiration_days, $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';

        // Custom date picker (shows only when "Custom Date" is selected)
        $custom_date_display = $quote_expiration_days === 'custom' ? 'block' : 'none';
        echo '<div id="custom_date_wrapper" style="display:' . $custom_date_display . '; margin-bottom:10px;">';
        echo '<input type="text" id="quote_expiration_date" name="quote_expiration_date" value="' . esc_attr($expiration_date) . '" class="widefat" placeholder="' . esc_attr__('dd/mm/yyyy', 'quote-manager-system-for-woocommerce') . '" style="width:100%;" />';
        echo '<p class="description">' . esc_html__('Select specific expiration date', 'quote-manager-system-for-woocommerce') . '</p>';
        echo '</div>';

        // Add JavaScript to show/hide datepicker
        ?>
        <script>
            jQuery(document).ready(function ($) {
                // Initialize datepicker
                $('#quote_expiration_date').datepicker({
                    dateFormat: 'dd/mm/yy',
                    changeMonth: true,
                    changeYear: true
                });

                // Show/hide custom date
                $('#quote_expiration_days').on('change', function () {
                    if ($(this).val() === 'custom') {
                        $('#custom_date_wrapper').show();
                    } else {
                        $('#custom_date_wrapper').hide();
                    }
                });
            });
        </script>
        <?php

        // PDF Actions
        echo '<hr style="margin:10px 0;">';
        echo '<a href="' . esc_url(admin_url('admin-ajax.php?action=quote_preview_pdf&quote_id=' . $post->ID)) . '" target="_blank" class="button button-secondary" style="display:block; margin-bottom:10px; width:100%;">📄 ' . esc_html__('Preview PDF', 'quote-manager-system-for-woocommerce') . '</a>';
        echo '<a href="' . esc_url(admin_url('admin-ajax.php?action=quote_download_pdf&quote_id=' . $post->ID)) . '" class="button button-primary" style="display:block; width:100%;">⬇️ ' . esc_html__('Download PDF', 'quote-manager-system-for-woocommerce') . '</a>';
    }

    /**
     * Render the Quote Tools meta box.
     */
    public function render_quote_tools_meta($post)
    {
        $quote_id = $post->ID;

        echo '<button id="quote-send-email" type="button" class="button button-primary" data-quote-id="' . esc_attr($post->ID) . '" style="width:100%;">' . esc_html__('📧 Send Quote Email', 'quote-manager-system-for-woocommerce') . '</button>';
        echo '<div id="send-quote-status" style="margin-top:10px;color:#007cba;font-weight:bold;"></div>';
    }

    /**
     * Render the Email Logs meta box.
     */
    public function render_email_logs_meta($post)
    {
        $logs = get_post_meta($post->ID, '_quote_email_logs', true);
        if (!is_array($logs) || empty($logs)) {
            echo '<p>' . esc_html__('No email history recorded.', 'quote-manager-system-for-woocommerce') . '</p>';
            return;
        }
        echo '<table class="widefat striped">';
        echo '<thead><tr>
            <th>' . esc_html__('📅 Date', 'quote-manager-system-for-woocommerce') . '</th>
            <th>' . esc_html__('📧 Recipient', 'quote-manager-system-for-woocommerce') . '</th>
            <th>' . esc_html__('✉️ Subject', 'quote-manager-system-for-woocommerce') . '</th>
            <th>' . esc_html__('📨 Message', 'quote-manager-system-for-woocommerce') . '</th>
            <th>' . esc_html__('✅ Result', 'quote-manager-system-for-woocommerce') . '</th>
            <th>' . esc_html__('👁️ Viewed', 'quote-manager-system-for-woocommerce') . '</th>
        </tr></thead><tbody>';
        foreach ($logs as $index => $log) {
            $datetime_raw = $log['datetime'] ?? '';
            $datetime = !empty($datetime_raw)
                ? esc_html('📅 ' . date_i18n('d/m/Y', strtotime($datetime_raw)) . ' 🕒 ' . date_i18n('H:i', strtotime($datetime_raw)))
                : '';
            $to = esc_html($log['to'] ?? '');
            $subject = esc_html($log['subject'] ?? '');
            $message_short = esc_html(wp_trim_words(wp_strip_all_tags($log['message'] ?? ''), 10, '...'));
            $result = $log['result'] === 'success'
                ? '<span style="color:green;font-weight:bold;">' . esc_html__('Sent', 'quote-manager-system-for-woocommerce') . '</span>'
                : '<span style="color:red;">' . esc_html__('Failed', 'quote-manager-system-for-woocommerce') . '</span>';
            if (!empty($log['opened_at'])) {
                $formatted = '📅 ' . date_i18n('d/m/Y', strtotime($log['opened_at'])) . ' 🕒 ' . date_i18n('H:i', strtotime($log['opened_at']));
                $tooltip = sprintf(__('Email opened at %s', 'quote-manager-system-for-woocommerce'), $formatted);
                $eye_icon = '<span class="quote-tracking-icon" data-tooltip="' . esc_attr($tooltip) . '">👁️</span>';
            } else {
                $tooltip = __('Not yet opened', 'quote-manager-system-for-woocommerce');
                $eye_icon = '<span class="quote-tracking-icon faded" data-tooltip="' . esc_attr($tooltip) . '">👁️</span>';
            }
            echo '<tr>';
            echo '<td>' . $datetime . '</td>';
            echo '<td>' . $to . '</td>';
            echo '<td>' . $subject . '</td>';
            echo '<td><button type="button" class="button small view-full-message" data-message-index="' . esc_attr($index) . '">' . esc_html__('View full message', 'quote-manager-system-for-woocommerce') . '</button></td>';
            echo '<td>' . $result . '</td>';
            echo '<td>' . $eye_icon . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        // Modals for each email
        foreach ($logs as $index => $log) {
            $message_raw = $log['message'] ?? '';

            // Remove tracking pixel <img> that contains quote_email_open=1
            $message_clean = preg_replace('/<img[^>]+quote_email_open=1[^>]*>/i', '', $message_raw);

            // Convert line breaks to <br>
            $message_formatted = nl2br($message_clean);

            echo '<div id="email-message-modal-' . esc_attr($index) . '" class="email-message-modal" style="display:none;">
                <div class="email-message-modal-content">
                    <span class="close-message-modal" data-message-index="' . esc_attr($index) . '" style="cursor:pointer;font-weight:bold;float:right;">&times;</span>
                    <h3>' . esc_html__('📩 Full Message', 'quote-manager-system-for-woocommerce') . '</h3>
                    <div class="email-message-html">' . wp_kses_post($message_formatted) . '</div>
                </div>
            </div>';
        }
    }

    public function render_quote_terms_meta($post)
    {
        // Get the quote-specific terms, or use the default if none exist
        $quote_terms = get_post_meta($post->ID, '_quote_terms', true);
        $default_terms = get_option('quote_manager_default_terms', '');

        if (empty($quote_terms) && !empty($default_terms)) {
            $quote_terms = $default_terms;
        }

        // Add a hidden nonce for security
        wp_nonce_field('quote_manager_save_terms', 'quote_manager_nonce_terms');

        // Create editor settings
        $editor_settings = array(
            'textarea_name' => 'quote_terms',
            'textarea_rows' => 10,
            'media_buttons' => false,
            'teeny' => false,
            'quicktags' => true,
            'tinymce' => array(
                'forced_root_block' => 'div',  // Use div instead of p to better preserve formatting
                'keep_styles' => true,   // Keep styles when switching between visual/text
                'entities' => '38,amp,60,lt,62,gt', // Preserve entities
                'fix_list_elements' => true,   // Fix list elements
                'preserve_cdata' => true,   // Preserve CDATA
                'remove_redundant_brs' => false, // Don't remove BRs that might be intended
            ),
        );

        // Show a reset button
        echo '<div style="margin-bottom:10px;">';
        echo '<button type="button" id="reset-to-default-terms" class="button">' . __('Reset to Default Terms', 'quote-manager-system-for-woocommerce') . '</button>';
        echo '</div>';

        // Show the editor
        wp_editor($quote_terms, 'quote_terms_editor', $editor_settings);

        // Show placeholders help
        echo '<p class="description">' . __('Available placeholders:', 'quote-manager-system-for-woocommerce') . ' 
            <code>{{customer_name}}</code>, 
            <code>{{customer_first_name}}</code>, 
            <code>{{customer_last_name}}</code>,
            <code>{{quote_id}}</code>,
            <code>{{quote_expiry}}</code>,
            <code>{{company_name}}</code>,
            <code>{{today}}</code>
        </p>';

        echo '<p class="description"><small>' .
            __('Tip: Use Shift+Enter for line breaks, Enter for new paragraphs, and the toolbar buttons for formatting.', 'quote-manager-system-for-woocommerce') .
            '</small></p>';

        wp_localize_script('quote-manager-settings-js', 'quote_manager_vars', array(
            'default_terms' => $default_terms
        ));

        wp_localize_script('quote-manager-settings-js', 'quote_manager_i18n', array(
            'reset_terms_confirm' => __('Reset to default terms? This will replace your current text.', 'quote-manager-system-for-woocommerce')
        ));

    }

    /**
     * Render the Attachments meta box.
     */
    public function render_quote_attachments_meta($post)
    {
        // Get saved attachments
        $attachments = get_post_meta($post->ID, '_quote_attachments', true);
        if (!is_array($attachments)) {
            $attachments = array();
        }

        wp_nonce_field('quote_manager_save_attachments', 'quote_manager_nonce_attachments', false);

        ?>
        <div class="quote-attachments-container">
            <p><?php _e('Add technical documents, specifications, or any other files that should be attached to this quote.', 'quote-manager-system-for-woocommerce'); ?></p>

            <div class="quote-attachment-list" id="quote-attachment-list">
                <?php if (!empty($attachments)): ?>
                    <?php foreach ($attachments as $index => $attachment): ?>
                        <div class="quote-attachment-item" data-index="<?php echo esc_attr($index); ?>">
                            <input type="hidden" name="quote_attachments[<?php echo esc_attr($index); ?>][id]"
                                   value="<?php echo esc_attr($attachment['id'] ?? ''); ?>">
                            <input type="hidden" name="quote_attachments[<?php echo esc_attr($index); ?>][url]"
                                   value="<?php echo esc_attr($attachment['url'] ?? ''); ?>">
                            <input type="hidden" name="quote_attachments[<?php echo esc_attr($index); ?>][filename]"
                                   value="<?php echo esc_attr($attachment['filename'] ?? ''); ?>">
                            <input type="hidden" name="quote_attachments[<?php echo esc_attr($index); ?>][type]"
                                   value="<?php echo esc_attr($attachment['type'] ?? ''); ?>">

                            <div class="attachment-icon">
                                <?php echo $this->get_file_icon($attachment['type'] ?? ''); ?>
                            </div>
                            <div class="attachment-details">
                                <div class="attachment-filename"><?php echo esc_html($attachment['filename'] ?? ''); ?></div>
                                <div class="attachment-type"><?php echo esc_html($this->get_file_type_label($attachment['type'] ?? '')); ?></div>
                            </div>
                            <div class="attachment-actions">
                                <a href="<?php echo esc_url($attachment['url'] ?? ''); ?>" class="button button-small"
                                   target="_blank"
                                   title="<?php esc_attr_e('View File', 'quote-manager-system-for-woocommerce'); ?>">👁️</a>
                                <button type="button" class="button button-small remove-attachment"
                                        title="<?php esc_attr_e('Remove File', 'quote-manager-system-for-woocommerce'); ?>">
                                    ❌
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="quote-attachment-controls">
                <button type="button" id="add-attachment" class="button">
                    <?php _e('Add Attachment', 'quote-manager-system-for-woocommerce'); ?>
                </button>
                <div id="attachment-upload-status"></div>
            </div>
        </div>
        <template id="attachment-item-template">
            <div class="quote-attachment-item" data-index="{index}">
                <input type="hidden" name="quote_attachments[{index}][id]" value="{id}">
                <input type="hidden" name="quote_attachments[{index}][url]" value="{url}">
                <input type="hidden" name="quote_attachments[{index}][filename]" value="{filename}">
                <input type="hidden" name="quote_attachments[{index}][type]" value="{type}">

                <div class="attachment-icon">
                    {icon}
                </div>
                <div class="attachment-details">
                    <div class="attachment-filename">{filename}</div>
                    <div class="attachment-type">{typelabel}</div>
                </div>
                <div class="attachment-actions">
                    <a href="{url}" class="button button-small" target="_blank"
                       title="<?php esc_attr_e('View File', 'quote-manager-system-for-woocommerce'); ?>">👁️</a>
                    <button type="button" class="button button-small remove-attachment"
                            title="<?php esc_attr_e('Remove File', 'quote-manager-system-for-woocommerce'); ?>">❌
                    </button>
                </div>
            </div>
        </template>
        <?php
    }

    /**
     * Get appropriate icon based on file type
     */
    private function get_file_icon($file_type)
    {
        switch ($file_type) {
            case 'application/pdf':
                return '📄';
            case 'application/msword':
            case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
                return '📝';
            case 'application/vnd.ms-excel':
            case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
                return '📊';
            case 'application/vnd.ms-powerpoint':
            case 'application/vnd.openxmlformats-officedocument.presentationml.presentation':
                return '📺';
            case 'image/jpeg':
            case 'image/png':
            case 'image/gif':
                return '🖼️';
            case 'application/zip':
            case 'application/x-rar-compressed':
                return '📦';
            default:
                return '📎';
        }
    }

    /**
     * Get human-readable file type label
     */
    private function get_file_type_label($file_type)
    {
        switch ($file_type) {
            case 'application/pdf':
                return __('PDF Document', 'quote-manager-system-for-woocommerce');
            case 'application/msword':
            case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
                return __('Word Document', 'quote-manager-system-for-woocommerce');
            case 'application/vnd.ms-excel':
            case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
                return __('Excel Spreadsheet', 'quote-manager-system-for-woocommerce');
            case 'application/vnd.ms-powerpoint':
            case 'application/vnd.openxmlformats-officedocument.presentationml.presentation':
                return __('PowerPoint Presentation', 'quote-manager-system-for-woocommerce');
            case 'image/jpeg':
            case 'image/png':
            case 'image/gif':
                return __('Image', 'quote-manager-system-for-woocommerce');
            case 'application/zip':
                return __('ZIP Archive', 'quote-manager-system-for-woocommerce');
            case 'application/x-rar-compressed':
                return __('RAR Archive', 'quote-manager-system-for-woocommerce');
            default:
                return __('File', 'quote-manager-system-for-woocommerce');
        }
    }

    /**
     * Save meta box data when the Customer Quote post is saved.
     */
    public function save_quote_post($post_id)
    {
        // Do not save on autosave or quick edit without nonce.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Verify permissions.
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save customer fields if nonce is valid.
        if (isset($_POST['quote_manager_nonce_customer']) && wp_verify_nonce($_POST['quote_manager_nonce_customer'], 'quote_manager_save_customer')) {
            $fields = array(
                '_customer_first_name' => 'customer_first_name',
                '_customer_last_name' => 'customer_last_name',
                '_customer_company' => 'customer_company',
                '_customer_address' => 'customer_address',
                '_customer_area' => 'customer_city',
                '_customer_city' => 'customer_city',
                '_customer_postcode' => 'customer_postcode',
                '_customer_country' => 'customer_country',
                '_customer_state' => 'customer_state',
                '_customer_phone' => 'customer_phone',
                '_customer_email' => 'customer_email',
                '_shipping_first_name' => 'shipping_first_name',
                '_shipping_last_name' => 'shipping_last_name',
                '_shipping_company' => 'shipping_company',
                '_shipping_address' => 'shipping_address',
                '_shipping_area' => 'shipping_city',
                '_shipping_city' => 'shipping_city',
                '_shipping_postcode' => 'shipping_postcode',
                '_shipping_country' => 'shipping_country',
                '_shipping_state' => 'shipping_state',
                '_shipping_phone' => 'shipping_phone',
                '_project_name' => 'project_name',
            );
            foreach ($fields as $meta_key => $field_name) {
                if (isset($_POST[$field_name])) {
                    if ($field_name === 'customer_note') {
                        update_post_meta($post_id, $meta_key, sanitize_textarea_field(wp_unslash($_POST[$field_name])));
                    } else {
                        update_post_meta($post_id, $meta_key, sanitize_text_field(wp_unslash($_POST[$field_name])));
                    }
                }
            }
        }

        // Save products fields if nonce is valid.
        if (isset($_POST['quote_manager_nonce_products']) && wp_verify_nonce($_POST['quote_manager_nonce_products'], 'quote_manager_save_products')) {
            if (!empty($_POST['quote_products']) && is_array($_POST['quote_products'])) {
                $products_data = array();
                foreach ($_POST['quote_products'] as $item) {
                    $image = isset($item['image']) ? sanitize_text_field($item['image']) : '';
                    $title = isset($item['title']) ? sanitize_text_field($item['title']) : '';
                    $sku = isset($item['sku']) ? sanitize_text_field($item['sku']) : '';
                    $purchase_price = isset($item['purchase_price']) ? wc_format_decimal($item['purchase_price']) : '';
                    $list_price = isset($item['list_price']) ? wc_format_decimal($item['list_price']) : 0;
                    $discount = isset($item['discount']) ? wc_format_decimal($item['discount']) : 0;
                    $final_price_excl = isset($item['final_price_excl']) ? wc_format_decimal($item['final_price_excl']) : 0;
                    $final_price_incl = isset($item['final_price_incl']) ? wc_format_decimal($item['final_price_incl']) : 0;
                    $qty = isset($item['qty']) ? intval($item['qty']) : 0;
                    $id = isset($item['id']) ? sanitize_text_field($item['id']) : '';

                    if (empty($image) && empty($title) && empty($sku) && $list_price <= 0 && $qty <= 0) {
                        continue;
                    }

                    $products_data[] = compact(
                        'image', 'title', 'sku', 'purchase_price',
                        'list_price', 'discount',
                        'final_price_excl', 'final_price_incl',
                        'qty', 'id'
                    );
                }

                if (!empty($products_data)) {
                    update_post_meta($post_id, '_quote_products', $products_data);
                } else {
                    delete_post_meta($post_id, '_quote_products');
                }
            } else {
                delete_post_meta($post_id, '_quote_products');
            }

            // Save VAT checkbox
            if (isset($_POST['quote_include_vat'])) {
                update_post_meta($post_id, '_quote_include_vat', '1');
            } else {
                update_post_meta($post_id, '_quote_include_vat', '0');
            }

            // Handle expiration date
            if (isset($_POST['quote_expiration_days'])) {
                $expiration_days = sanitize_text_field($_POST['quote_expiration_days']);
                update_post_meta($post_id, '_quote_expiration_days', $expiration_days);

                // Calculate expiration date
                if ($expiration_days === 'custom' && isset($_POST['quote_expiration_date'])) {
                    // If custom date selected, save provided date
                    $custom_date = sanitize_text_field($_POST['quote_expiration_date']);
                    update_post_meta($post_id, '_quote_expiration_date', $custom_date);
                } else {
                    // Otherwise calculate expiration date based on selected days
                    $post_date = get_post_field('post_date', $post_id);
                    $expiration_date = date_i18n('d/m/Y', strtotime('+' . intval($expiration_days) . ' days', strtotime($post_date)));
                    update_post_meta($post_id, '_quote_expiration_date', $expiration_date);
                }
            }
        }

        // Save status if present
        if (isset($_POST['quote_status'])) {
            $status = sanitize_text_field($_POST['quote_status']);
            update_post_meta($post_id, '_quote_status', $status);
        }

        // Save attachments if nonce is valid
        if (isset($_POST['quote_manager_nonce_attachments']) && wp_verify_nonce($_POST['quote_manager_nonce_attachments'], 'quote_manager_save_attachments')) {
            if (!empty($_POST['quote_attachments']) && is_array($_POST['quote_attachments'])) {
                $attachments_data = array();

                foreach ($_POST['quote_attachments'] as $item) {
                    $attachment_id = isset($item['id']) ? sanitize_text_field($item['id']) : '';
                    $url = isset($item['url']) ? esc_url_raw($item['url']) : '';
                    $filename = isset($item['filename']) ? sanitize_text_field($item['filename']) : '';
                    $type = isset($item['type']) ? sanitize_text_field($item['type']) : '';

                    if (empty($url) || empty($filename)) {
                        continue;
                    }

                    $attachments_data[] = compact('id', 'url', 'filename', 'type');
                }

                if (!empty($attachments_data)) {
                    update_post_meta($post_id, '_quote_attachments', $attachments_data);
                } else {
                    delete_post_meta($post_id, '_quote_attachments');
                }
            } else {
                delete_post_meta($post_id, '_quote_attachments');
            }
        }

    }

    /**
     * Render modals in admin footer for email sending.
     */
    public function render_modals()
    {
        global $pagenow, $post;

        // Load modal only when editing a quote
        if ($pagenow === 'post.php' && $post && $post->post_type === 'customer_quote') {
            $this->render_send_email_modal($post);
        }
    }

    /**
     * Render the send quote email modal.
     */
    private function render_send_email_modal($post)
    {
        // Get dynamic customer and quote data
        $first_name = get_post_meta($post->ID, '_customer_first_name', true);
        $last_name = get_post_meta($post->ID, '_customer_last_name', true);
        $quote_id = $post->ID;
        $quote_expiry = get_post_meta($post->ID, '_quote_expiration_date', true) ?: '–';

        // Default template
        $default_template = Quote_Manager_System_For_Woocommerce::get_default_email_template();

        // Parse placeholders
        $default_message = $this->parse_email_placeholders($default_template, [
            'customer_first_name' => $first_name,
            'customer_last_name' => $last_name,
            'customer_name' => trim($first_name . ' ' . $last_name),
            'quote_id' => $quote_id,
            'quote_expiry' => $quote_expiry,
        ]);
        ?>
        <div id="quote-email-modal" class="quote-email-modal-overlay" style="display: none;">
            <div class="quote-email-modal-content">
                <h2>✉️ Send Quote Email</h2>

                <!-- Email Subject -->
                <div class="form-group">
                    <label for="quote_email_message"><strong>Message:</strong></label>
                    <?php
                    wp_editor(
                        $default_message,
                        'quote_email_message',
                        [
                            'textarea_name' => 'quote_email_message',
                            'textarea_rows' => 8,
                            'media_buttons' => false,
                            'teeny' => true,
                            'quicktags' => false,
                        ]
                    );
                    ?>					
                    <p class="description">
                        You can use placeholders:
                        <code>{{customer_first_name}}</code>,
                        <code>{{customer_last_name}}</code>,
                        <code>{{customer_name}}</code>,
                        <code>{{quote_id}}</code>,
                        <code>{{quote_expiry}}</code>,
                        <code>{{quote_view_url}}</code>
                    </p>
                </div>

                <!-- Hidden field for quote ID -->
                <input type="hidden" id="modal-quote-id" value="<?php echo esc_attr($post->ID); ?>"/>

                <!-- PDF Attachment and additional attachments -->
                <div class="form-group">
                    <strong>📎 <?php _e('Attachments:', 'quote-manager-system-for-woocommerce'); ?></strong>
                    <ul class="email-attachments-list">
                        <li><code>PROSFORA_#<?php echo esc_attr($post->ID); ?>.pdf</code>
                            (<?php _e('Quote PDF', 'quote-manager-system-for-woocommerce'); ?>)
                        </li>

                        <?php
                        // Get all attachments for this quote
                        $attachments = get_post_meta($post->ID, '_quote_attachments', true);
                        if (is_array($attachments) && !empty($attachments)):
                            foreach ($attachments as $attachment):
                                if (isset($attachment['filename']) && !empty($attachment['filename'])):
                                    ?>
                                    <li><code><?php echo esc_html($attachment['filename']); ?></code>
                                        (<?php _e('Attachment', 'quote-manager-system-for-woocommerce'); ?>)
                                    </li>
                                <?php
                                endif;
                            endforeach;
                        endif;
                        ?>
                    </ul>
                </div>

                <!-- Send Buttons -->
                <div class="form-group" style="margin-top: 20px;">
                    <button id="confirm-send-email" class="button button-primary">📤 Send</button>
                    <button id="cancel-send-email" class="button">❌ Cancel</button>
                </div>

                <!-- Confirmation / Error Message -->
                <div id="send-quote-status" style="margin-top: 15px;"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Parse email placeholders
     */
    private function parse_email_placeholders($template, $data)
    {
        $replacements = [
            '{{customer_name}}' => $data['customer_name'] ?? '',
            '{{customer_first_name}}' => $data['customer_first_name'] ?? '',
            '{{customer_last_name}}' => $data['customer_last_name'] ?? '',
            '{{quote_id}}' => $data['quote_id'] ?? '',
            '{{quote_expiry}}' => $data['quote_expiry'] ?? '',
            '{{site_name}}' => get_bloginfo('name'),
            '{{today}}' => date_i18n('d/m/Y'),
        ];

        return strtr($template, $replacements);
    }

    /**
     * Helper function to render a labeled input field.
     */
    private function render_quote_field($label, $name, $value)
    {
        // Associate certain field name keywords with an icon for label.
        $icons = array(
            'first' => '👤',
            'last' => '👤',
            'name' => '👤',
            'address' => '🏠',
            'area' => '📍',
            'postcode' => '🏷️',
            'city' => '🏙️',
            'phone' => '📞',
            'email' => '✉️',
        );
        $icon = '🔹';
        foreach ($icons as $key => $icn) {
            if (strpos($name, $key) !== false) {
                $icon = $icn;
                break;
            }
        }
        // Output label and text input.
        echo '<div class="quote-field">';
        echo '<label for="' . esc_attr($name) . '">' . $icon . ' ' . esc_html($label) . '</label>';
        echo '<input type="text" class="quote-input" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" />';
        echo '</div>';
    }
}