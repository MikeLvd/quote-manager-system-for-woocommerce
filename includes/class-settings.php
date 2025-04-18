<?php
/**
 * Handle plugin settings
 *
 * @link       https://goldenbath.gr
 * @since      1.0.0
 *
 * @package    Quote_Manager_System_For_Woocommerce
 * @subpackage Quote_Manager_System_For_Woocommerce/includes
 */

class Quote_Manager_Settings {

    /**
     * Register company settings
     */
    public function register_settings() {
        // Register settings
        register_setting('quote_manager_company_settings', 'quote_manager_company_name');
        register_setting('quote_manager_company_settings', 'quote_manager_company_address');
        register_setting('quote_manager_company_settings', 'quote_manager_company_city');
        register_setting('quote_manager_company_settings', 'quote_manager_company_phone');
        register_setting('quote_manager_company_settings', 'quote_manager_company_email');
        register_setting('quote_manager_company_settings', 'quote_manager_company_logo');
        
        // Add section
        add_settings_section(
            'quote_manager_company_section',
            __('Company Details', 'quote-manager-system-for-woocommerce'),
            array($this, 'company_section_callback'),
            'quote_manager_company_settings'
        );
        
        // Add fields
        add_settings_field(
            'quote_manager_company_name',
            __('Company Name', 'quote-manager-system-for-woocommerce'),
            array($this, 'text_field_callback'),
            'quote_manager_company_settings',
            'quote_manager_company_section',
            ['label_for' => 'quote_manager_company_name']
        );
        
        add_settings_field(
            'quote_manager_company_address',
            __('Address', 'quote-manager-system-for-woocommerce'),
            array($this, 'text_field_callback'),
            'quote_manager_company_settings',
            'quote_manager_company_section',
            ['label_for' => 'quote_manager_company_address']
        );
        
        add_settings_field(
            'quote_manager_company_city',
            __('City & Postal Code', 'quote-manager-system-for-woocommerce'),
            array($this, 'text_field_callback'),
            'quote_manager_company_settings',
            'quote_manager_company_section',
            ['label_for' => 'quote_manager_company_city']
        );
        
        add_settings_field(
            'quote_manager_company_phone',
            __('Phone', 'quote-manager-system-for-woocommerce'),
            array($this, 'text_field_callback'),
            'quote_manager_company_settings',
            'quote_manager_company_section',
            ['label_for' => 'quote_manager_company_phone']
        );
        
        add_settings_field(
            'quote_manager_company_email',
            __('Email', 'quote-manager-system-for-woocommerce'),
            array($this, 'text_field_callback'),
            'quote_manager_company_settings',
            'quote_manager_company_section',
            ['label_for' => 'quote_manager_company_email']
        );
        
        add_settings_field(
            'quote_manager_company_logo',
            __('Logo', 'quote-manager-system-for-woocommerce'),
            array($this, 'logo_field_callback'),
            'quote_manager_company_settings',
            'quote_manager_company_section',
            ['label_for' => 'quote_manager_company_logo']
        );
    }

    /**
     * Section callback
     */
    public function company_section_callback() {
        echo '<p>' . __('Enter your company details that will appear on the quote PDF.', 'quote-manager-system-for-woocommerce') . '</p>';
    }

    /**
     * Text field callback
     */
    public function text_field_callback($args) {
        $option = get_option($args['label_for']);
        echo '<input type="text" id="' . esc_attr($args['label_for']) . '" name="' . esc_attr($args['label_for']) . '" value="' . esc_attr($option) . '" class="regular-text" />';
    }

    /**
     * Logo field callback
     */
    public function logo_field_callback($args) {
        $logo_url = get_option($args['label_for']);
        ?>
        <div class="logo-upload-field">
            <input type="text" id="<?php echo esc_attr($args['label_for']); ?>" name="<?php echo esc_attr($args['label_for']); ?>" value="<?php echo esc_url($logo_url); ?>" class="regular-text" />
            <button type="button" class="button upload-logo-button" data-input-id="<?php echo esc_attr($args['label_for']); ?>"><?php _e('Select Image', 'quote-manager-system-for-woocommerce'); ?></button>
            <?php if (!empty($logo_url)) : ?>
                <div class="logo-preview">
                    <img src="<?php echo esc_url($logo_url); ?>" alt="<?php _e('Company Logo', 'quote-manager-system-for-woocommerce'); ?>" />
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Add settings page to menu
     */
    public function add_settings_page() {
        add_submenu_page(
            'edit.php?post_type=customer_quote',  // Parent menu
            __('Quote Manager Settings', 'quote-manager-system-for-woocommerce'),  // Page title
            __('Settings', 'quote-manager-system-for-woocommerce'),  // Menu title
            'manage_options',  // Capability
            'quote_manager_settings',  // Slug
            array($this, 'settings_page_callback')  // Callback
        );
    }

    /**
     * Settings page callback
     */
    public function settings_page_callback() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Ensure media scripts are loaded
        if (!did_action('wp_enqueue_media')) {
            wp_enqueue_media();
        }
        
        // Active tab
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'company';
        ?>
        
        <div class="wrap">
            <h1><?php echo esc_html(__('Quote Manager Settings', 'quote-manager-system-for-woocommerce')); ?></h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="?post_type=customer_quote&page=quote_manager_settings&tab=company" class="nav-tab <?php echo $active_tab == 'company' ? 'nav-tab-active' : ''; ?>"><?php _e('Company Details', 'quote-manager-system-for-woocommerce'); ?></a>
                <!-- You can add more tabs here if needed -->
            </h2>
            
            <?php if ($active_tab == 'company'): ?>
                <form action="options.php" method="post">
                    <?php
                    settings_fields('quote_manager_company_settings');
                    do_settings_sections('quote_manager_company_settings');
                    submit_button();
                    ?>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }
}