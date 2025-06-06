<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://goldenbath.gr
 * @since      1.0.0
 *
 * @package    Quote_Manager_System_For_Woocommerce
 * @subpackage Quote_Manager_System_For_Woocommerce/admin
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * The admin-specific functionality of the plugin.
 */
class Quote_Manager_System_For_Woocommerce_Admin
{

    /**
     * The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @param string $plugin_name The name of this plugin.
     * @param string $version The version of this plugin.
     */
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        // Ensure PDF directory has protection
        add_action('admin_init', [$this, 'ensure_pdf_protection']);
    }

    /**
     * Ensure PDF directory has protection
     */
    public function ensure_pdf_protection()
    {
        // Only run this check occasionally (once per session)
        if (!get_transient('quote_manager_check_protection')) {
            // Set transient to avoid checking too often
            set_transient('quote_manager_check_protection', true, 12 * HOUR_IN_SECONDS);

            // Check if .htaccess exists
            $upload_dir = wp_upload_dir();
            $pdf_dir = $upload_dir['basedir'] . '/quote-manager/quotes/';
            $htaccess_file = $pdf_dir . '.htaccess';

            if (!file_exists($htaccess_file) && current_user_can('manage_options')) {
                // Create protection
                require_once QUOTE_MANAGER_PATH . 'includes/class-email-manager.php';
                $email_manager = new Quote_Manager_Email_Manager();
                $email_manager->create_protection_htaccess($pdf_dir);

                // Add an admin notice
                add_action('admin_notices', function () {
                    echo '<div class="notice notice-success is-dismissible"><p>' .
                        esc_html__('Quote Manager: PDF directory protection has been set up.', 'quote-manager-system-for-woocommerce') .
                        '</p></div>';
                });
            }
        }
    }

    /**
     * Determine the current admin page context.
     *
     * @param string $hook The current admin page hook.
     * @return array Context information about the current page.
     */
    private function get_admin_page_context($hook)
    {
        global $post;

        // Initialize context array
        $context = [
            'is_quote_edit_page' => false,
            'is_quote_list_page' => false,
            'is_settings_page' => false,
        ];

        // Check if we're on the quote edit page
        $context['is_quote_edit_page'] = ($hook === 'post.php' || $hook === 'post-new.php') &&
            isset($post) && $post instanceof WP_Post &&
            $post->post_type === 'customer_quote';

        // Check if we're on the quotes list page
        $context['is_quote_list_page'] = $hook === 'edit.php' &&
            isset($_GET['post_type']) &&
            $_GET['post_type'] === 'customer_quote';

        // Check if we're on the plugin settings page
        $context['is_settings_page'] = strpos($hook, 'quote_manager_settings') !== false;

        return $context;
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @param string $hook The current admin page hook.
     */
    public function enqueue_styles($hook)
    {
        $context = $this->get_admin_page_context($hook);
        
        // Return early if not on a relevant page
        if (!$context['is_quote_edit_page'] && !$context['is_quote_list_page'] && !$context['is_settings_page']) {
            return;
        }

        // Always load main CSS for all relevant pages
        wp_enqueue_style(
            $this->plugin_name,
            QUOTE_MANAGER_URL . 'admin/css/quote-manager-system-for-woocommerce-admin.css',
            [],
            $this->version
        );

        // Load quote status styles on list and edit pages
        if ($context['is_quote_edit_page'] || $context['is_quote_list_page']) {
            wp_enqueue_style(
                $this->plugin_name . '-status',
                QUOTE_MANAGER_URL . 'admin/css/quote-manager-status.css',
                [],
                $this->version
            );
        }
        
        // jQuery UI styles for datepicker
        if ($context['is_quote_edit_page']) {
            wp_enqueue_style(
                'jquery-ui-style', 
                '//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css',
                [],
                null
            );
        }
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @param string $hook The current admin page hook.
     */
    public function enqueue_scripts($hook)
    {
        global $post;
        
        $context = $this->get_admin_page_context($hook);
        
        // Return early if not on a relevant page
        if (!$context['is_quote_edit_page'] && !$context['is_quote_list_page'] && !$context['is_settings_page']) {
            return;
        }

        // Load media API for edit and settings pages
        if ($context['is_quote_edit_page'] || $context['is_settings_page']) {
            wp_enqueue_media();
        }

        // Settings script for both edit and settings pages
        if ($context['is_quote_edit_page'] || $context['is_settings_page']) {
            wp_enqueue_script(
                'quote-manager-settings-js',
                QUOTE_MANAGER_URL . 'admin/js/quote-manager-settings.js',
                ['jquery'],
                $this->version,
                true
            );
            
            // Localize settings script
            wp_localize_script('quote-manager-settings-js', 'quote_manager_data', [
                'i18n' => [
                    'reset_terms_confirm' => __('Reset to default terms? This will replace your current text.', 'quote-manager-system-for-woocommerce'),
                    'reset_email_confirm' => __('Reset to default email template? This will replace your current template.', 'quote-manager-system-for-woocommerce')
                ],
                'vars' => [
                    'default_terms' => get_option('quote_manager_default_terms', ''),
                    'default_email' => Quote_Manager_System_For_Woocommerce::get_default_email_template()
                ]
            ]);
        }

        // Quote list page scripts
        if ($context['is_quote_list_page']) {
            wp_enqueue_script(
                $this->plugin_name . '-status-js',
                QUOTE_MANAGER_URL . 'admin/js/quote-manager-status.js',
                ['jquery'],
                $this->version,
                true
            );
            
            wp_localize_script($this->plugin_name . '-status-js', 'quoteManagerStatusData', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'isQuoteList' => true,
                'statusNonce' => wp_create_nonce('quote_status_update'),
                'expiredNonce' => wp_create_nonce('quote_expired_check'),
                'i18n' => [
                    'updatingStatus' => __('Updating status...', 'quote-manager-system-for-woocommerce'),
                    'errorUpdating' => __('Error updating status.', 'quote-manager-system-for-woocommerce'),
                    'expiredNotice' => __('%d quotes have been marked as expired.', 'quote-manager-system-for-woocommerce')
                ]
            ]);
        }

        // Quote edit page scripts
        if ($context['is_quote_edit_page']) {
            // jQuery UI datepicker
            wp_enqueue_script('jquery-ui-datepicker');
            
            // TableDnD for sortable tables
            wp_enqueue_script(
                'tablednd',
                QUOTE_MANAGER_URL . 'admin/js/jquery.tablednd.min.js',
                ['jquery'],
                $this->version,
                true
            );
            
            // Main admin JS
            wp_enqueue_script(
                'quote-manager-admin-js',
                QUOTE_MANAGER_URL . 'admin/js/quote-manager-admin.js',
                ['jquery', 'tablednd'],
                $this->version,
                true
            );
            
            // Status management script
            wp_enqueue_script(
                $this->plugin_name . '-status-js',
                QUOTE_MANAGER_URL . 'admin/js/quote-manager-status.js',
                ['jquery'],
                $this->version,
                true
            );
            
            // Prepare common localization data
            $localization_data = $this->get_admin_localization_data($post);
            
            // Localize the admin script
            wp_localize_script('quote-manager-admin-js', 'quoteManagerData', $localization_data);
            
            // Localize the status script
            wp_localize_script($this->plugin_name . '-status-js', 'quoteManagerStatusData', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'isQuoteList' => false,
                'statusNonce' => wp_create_nonce('quote_status_update'),
                'expiredNonce' => wp_create_nonce('quote_expired_check'),
                'i18n' => [
                    'updatingStatus' => __('Updating status...', 'quote-manager-system-for-woocommerce'),
                    'errorUpdating' => __('Error updating status.', 'quote-manager-system-for-woocommerce'),
                    'expiredNotice' => __('%d quotes have been marked as expired.', 'quote-manager-system-for-woocommerce')
                ]
            ]);
        }
    }
    
    /**
     * Prepare localization data for admin scripts.
     *
     * @param WP_Post $post Current post object.
     * @return array Localization data.
     */
    private function get_admin_localization_data($post)
    {
        // Get placeholder image
        $placeholder_img = function_exists('wc_placeholder_img_src') ? wc_placeholder_img_src() : '';
        
        // Calculate VAT percentage from WooCommerce settings
        $tax_rate_percent = 0;
        if (class_exists('WC_Tax')) {
            $base_tax_rates = WC_Tax::get_rates('');
            if (!empty($base_tax_rates)) {
                $tax_rate = reset($base_tax_rates);
                if (isset($tax_rate['rate'])) {
                    $tax_rate_percent = floatval($tax_rate['rate']);
                }
            }
        }
        
        // Get WooCommerce currency settings
        $currency_symbol = get_woocommerce_currency_symbol();
        $thousand_separator = wc_get_price_thousand_separator();
        $decimal_separator = wc_get_price_decimal_separator();
        $decimals = wc_get_price_decimals();
        
        // Return the localization data array
        return [
            'placeholderImage' => $placeholder_img,
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'taxRatePercent' => $tax_rate_percent,
            'includeVAT' => get_post_meta($post->ID, '_quote_include_vat', true) === '1',
            'currencySymbol' => $currency_symbol,
            'thousandSeparator' => $thousand_separator,
            'decimalSeparator' => $decimal_separator,
            'decimals' => $decimals,
            'quoteId' => $post->ID,
            'attachmentNonce' => wp_create_nonce('quote_attachment_upload'),
            'attachmentDeleteNonce' => wp_create_nonce('quote_attachment_delete'),
            'statesNonce' => wp_create_nonce('quote_manager_get_states'),
            'createCustomerNonce' => wp_create_nonce('create_customer_from_quote'),
            'searchCustomersNonce' => wp_create_nonce('quote_manager_search_customers'),
            
            // Add translations
            'i18n' => [
                // Decimal separator warning
                'decimalSeparatorWarning' => __('Please enter a value with one monetary decimal point (%s) without thousand separators and currency symbols.', 'quote-manager-system-for-woocommerce'),
                
                // Modal text
                'selectImage' => __('Select Image', 'quote-manager-system-for-woocommerce'),
                'mediaLibrary' => __('Media Library', 'quote-manager-system-for-woocommerce'),
                'fromURL' => __('From URL', 'quote-manager-system-for-woocommerce'),
                'enterImageURL' => __('Enter Image URL', 'quote-manager-system-for-woocommerce'),
                'useThisImage' => __('Use this image', 'quote-manager-system-for-woocommerce'),
                'confirm' => __('Confirm', 'quote-manager-system-for-woocommerce'),
                'cancel' => __('Cancel', 'quote-manager-system-for-woocommerce'),
                
                // Email text
                'sendingInProgress' => __('📨 Sending in progress...', 'quote-manager-system-for-woocommerce'),
                'emailSentSuccess' => __('✅ Email sent successfully.', 'quote-manager-system-for-woocommerce'),
                'emailSendError' => __('❌ Error:', 'quote-manager-system-for-woocommerce'),
                'failedToSend' => __('Failed to send.', 'quote-manager-system-for-woocommerce'),
                'errorWhileSending' => __('❌ Error while sending email.', 'quote-manager-system-for-woocommerce'),
                
                // Table text
                'cost' => __('Cost', 'quote-manager-system-for-woocommerce'),
                'remove' => __('Remove', 'quote-manager-system-for-woocommerce'),
                'totals' => __('Totals', 'quote-manager-system-for-woocommerce'),
                'inclVAT' => __('incl. VAT', 'quote-manager-system-for-woocommerce'),
                'totalNetProfit' => __('Total Net Profit', 'quote-manager-system-for-woocommerce'),
                
                // Error messages
                'productSearchFailed' => __('Product search failed:', 'quote-manager-system-for-woocommerce'),
                
                // Attachment text
                'uploadingFile' => __('Uploading file...', 'quote-manager-system-for-woocommerce'),
                'fileUploaded' => __('File uploaded successfully.', 'quote-manager-system-for-woocommerce'),
                'uploadError' => __('Error uploading file:', 'quote-manager-system-for-woocommerce'),
                'selectFile' => __('Select File', 'quote-manager-system-for-woocommerce'),
                'removeAttachment' => __('Remove Attachment', 'quote-manager-system-for-woocommerce'),
                'viewFile' => __('View File', 'quote-manager-system-for-woocommerce'),
                
                // Customer Text
                'creatingCustomer' => __('Creating customer...', 'quote-manager-system-for-woocommerce'),
                'customerCreated' => __('Customer created successfully. ', 'quote-manager-system-for-woocommerce'),
                'viewCustomer' => __('View customer', 'quote-manager-system-for-woocommerce'),
                'errorCreatingCustomer' => __('Error creating customer.', 'quote-manager-system-for-woocommerce'),
                
                // Attachments uploads translations
                'fileSizeExceeded' => __('File size exceeds the 2MB limit.', 'quote-manager-system-for-woocommerce'),
                'invalidFileType' => __('Invalid file type. Only JPG, JPEG, PNG and PDF files are allowed.', 'quote-manager-system-for-woocommerce'),
                'maxAttachmentsReached' => __('Maximum number of attachments reached (10).', 'quote-manager-system-for-woocommerce')
            ]
        ];
    }
}