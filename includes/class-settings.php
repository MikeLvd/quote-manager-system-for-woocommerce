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
        register_setting('quote_manager_company_settings', 'quote_manager_default_terms', array(
            'sanitize_callback' => function($input) {
                // Preserve more HTML including line breaks
                return wp_kses($input, array(
                    'p'      => array('style' => array()),
                    'br'     => array(),
                    'em'     => array(),
                    'strong' => array(),
                    'ul'     => array('style' => array()),
                    'ol'     => array('style' => array()),
                    'li'     => array('style' => array()),
                    'span'   => array('style' => array()),
                    'div'    => array('style' => array(), 'class' => array()),
                    'h1'     => array('style' => array()),
                    'h2'     => array('style' => array()),
                    'h3'     => array('style' => array()),
                    'h4'     => array('style' => array()),
                    'h5'     => array('style' => array()),
                    'h6'     => array('style' => array()),
                    'a'      => array('href' => array(), 'target' => array()),
                ));
            }
        ));
        
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
		
        add_settings_field(
            'quote_manager_default_terms',
            __('Default Terms & Conditions', 'quote-manager-system-for-woocommerce'),
            array($this, 'textarea_field_callback'),
            'quote_manager_company_settings',
            'quote_manager_company_section',
            ['label_for' => 'quote_manager_default_terms']
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
     * Terms Textarea field callback
     */
    public function textarea_field_callback($args) {
        $option = get_option($args['label_for']);
        
        // Define editor settings with specific configuration for line breaks
        $editor_settings = array(
            'textarea_name' => $args['label_for'],
            'textarea_rows' => 10,
            'media_buttons' => false,
            'teeny'         => false,
            'quicktags'     => true,
            'tinymce'       => array(
                'forced_root_block' => 'div',  // Use div instead of p to better preserve formatting
                'keep_styles'       => true,   // Keep styles when switching between visual/text
                'entities'          => '38,amp,60,lt,62,gt', // Preserve entities
                'fix_list_elements' => true,   // Fix list elements
                'preserve_cdata'    => true,   // Preserve CDATA
                'remove_redundant_brs' => false, // Don't remove BRs that might be intended
            ),
        );
        
        // Output the editor
        wp_editor($option, 'quote_default_terms_editor', $editor_settings);
        
        // Display description with placeholders
        echo '<p class="description">' . __('These terms will be used as the default for all quotes. You can customize them per quote.', 'quote-manager-system-for-woocommerce') . '</p>';
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