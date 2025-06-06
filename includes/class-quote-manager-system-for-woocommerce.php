<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
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

/**
 * The core plugin class.
 */
class Quote_Manager_System_For_Woocommerce
{

    /**
     * Quote statuses
     */
    const STATUS_DRAFT = 'draft';
    const STATUS_SENT = 'sent';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_REJECTED = 'rejected';
    const STATUS_EXPIRED = 'expired';
    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      Quote_Manager_System_For_Woocommerce_Loader $loader Maintains and registers all hooks for the plugin.
     */
    protected $loader;
    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string $plugin_name The string used to uniquely identify this plugin.
     */
    protected $plugin_name;
    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string $version The current version of the plugin.
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     */
    public function __construct()
    {
        if (defined('QUOTE_MANAGER_VERSION')) {
            $this->version = QUOTE_MANAGER_VERSION;
        } else {
            $this->version = '1.0.0';
        }
        $this->plugin_name = 'quote-manager-system-for-woocommerce';

        // Ensure required directories exist before proceeding
        require_once QUOTE_MANAGER_PATH . 'includes/class-quote-manager-system-for-woocommerce-activator.php';
        Quote_Manager_System_For_Woocommerce_Activator::ensure_directories();

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();

        // Add email tracking handler
        $this->setup_email_tracking();
		
        // Initialize quote expiration handler
        $this->setup_expiration_handler();		
    }

    /**
     * Load the required dependencies for this plugin.
     */
    private function load_dependencies()
    {
        // Core classes
        require_once QUOTE_MANAGER_PATH . 'includes/class-quote-manager-system-for-woocommerce-loader.php';
        require_once QUOTE_MANAGER_PATH . 'includes/class-quote-manager-system-for-woocommerce-i18n.php';

        // Admin and public classes
        require_once QUOTE_MANAGER_PATH . 'admin/class-quote-manager-system-for-woocommerce-admin.php';
        require_once QUOTE_MANAGER_PATH . 'public/class-quote-manager-system-for-woocommerce-public.php';

        // Quote manager specific classes
        require_once QUOTE_MANAGER_PATH . 'includes/class-post-type.php';
        require_once QUOTE_MANAGER_PATH . 'includes/class-metaboxes.php';
        require_once QUOTE_MANAGER_PATH . 'includes/class-ajax-handlers.php';
        require_once QUOTE_MANAGER_PATH . 'includes/class-pdf-generator.php';
        require_once QUOTE_MANAGER_PATH . 'includes/class-email-manager.php';
        require_once QUOTE_MANAGER_PATH . 'includes/class-settings.php';
		require_once QUOTE_MANAGER_PATH . 'includes/class-quote-expiration-handler.php';

        $this->loader = new Quote_Manager_System_For_Woocommerce_Loader();
    }

    /**
     * Define the locale for this plugin for internationalization.
     *
     * Uses the Quote_Manager_System_For_Woocommerce_i18n class in order to set the domain and to register the hook
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function set_locale()
    {
        $plugin_i18n = new Quote_Manager_System_For_Woocommerce_i18n();
        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    /**
     * Register all of the hooks related to the admin area functionality
     */
    private function define_admin_hooks()
    {
        $plugin_admin = new Quote_Manager_System_For_Woocommerce_Admin($this->get_plugin_name(), $this->get_version());

        // Admin assets
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');

        // Post type and metaboxes
        $post_type = new Quote_Manager_Post_Type();
        $this->loader->add_action('init', $post_type, 'register_post_type');
        $this->loader->add_filter('manage_customer_quote_posts_columns', $post_type, 'set_custom_columns');
        $this->loader->add_action('manage_customer_quote_posts_custom_column', $post_type, 'custom_column', 10, 2);
        $this->loader->add_filter('manage_edit-customer_quote_sortable_columns', $post_type, 'sortable_columns');
        $this->loader->add_action('pre_get_posts', $post_type, 'column_orderby');

        // Metaboxes
        $metaboxes = new Quote_Manager_Metaboxes();
        $this->loader->add_action('add_meta_boxes', $metaboxes, 'add_meta_boxes');
        $this->loader->add_action('save_post_customer_quote', $metaboxes, 'save_quote_post');
        $this->loader->add_action('admin_footer', $metaboxes, 'render_modals');

        // Ajax handlers
        $ajax = new Quote_Manager_Ajax_Handlers();
        $this->loader->add_action('wp_ajax_quote_manager_search_products', $ajax, 'search_products');
        $this->loader->add_action('wp_ajax_quote_preview_pdf', $ajax, 'generate_pdf_preview');
        $this->loader->add_action('wp_ajax_quote_download_pdf', $ajax, 'generate_pdf_download');
        $this->loader->add_action('wp_ajax_quote_manager_send_email', $ajax, 'send_email');
        $this->loader->add_action('wp_ajax_quote_manager_upload_attachment', $ajax, 'upload_attachment');
        $this->loader->add_action('wp_ajax_quote_delete_attachment', $ajax, 'delete_attachment');
        $this->loader->add_action('wp_ajax_quote_manager_get_states', $ajax, 'get_states');
		$this->loader->add_action('wp_ajax_quote_manager_search_customers', $ajax, 'search_customers');
        $this->loader->add_action('wp_ajax_quote_manager_create_customer', $ajax, 'create_customer_from_quote');
        $this->loader->add_action('wp_ajax_quote_manager_update_status', $ajax, 'update_quote_status');
        $this->loader->add_action('wp_ajax_quote_manager_check_expired', $ajax, 'check_expired_quotes');

        // Settings
        $settings = new Quote_Manager_Settings();
        $this->loader->add_action('admin_init', $settings, 'register_settings');
        $this->loader->add_action('admin_menu', $settings, 'add_settings_page');
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @return    string    The name of the plugin.
     * @since     1.0.0
     */
    public function get_plugin_name()
    {
        return $this->plugin_name;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @return    string    The version number of the plugin.
     * @since     1.0.0
     */
    public function get_version()
    {
        return $this->version;
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_public_hooks()
    {
        $plugin_public = new Quote_Manager_System_For_Woocommerce_Public($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
        $this->loader->add_action('init', $plugin_public, 'register_shortcodes');

        // Init Response Handler
        require_once QUOTE_MANAGER_PATH . 'public/class-quote-response-handler.php';
        $response_handler = new Quote_Manager_Response_Handler();
    }

    /**
     * Setup email tracking functionality
     */
    private function setup_email_tracking()
    {
        require_once QUOTE_MANAGER_PATH . 'includes/class-email-manager.php';
        $email_manager = new Quote_Manager_Email_Manager();
        add_action('init', array($email_manager, 'handle_email_open_tracking'));
    }

    /**
     * Initialize the quote expiration handler
     */
    private function setup_expiration_handler() {
        $expiration_handler = new Quote_Manager_Expiration_Handler();
    }

    /**
     * Get status label from status key
     *
     * @param string $status Status key
     * @return string Status label
     */
    public static function get_status_label($status)
    {
        $statuses = self::get_quote_statuses();
        return isset($statuses[$status]) ? $statuses[$status] : __('Unknown', 'quote-manager-system-for-woocommerce');
    }

    /**
     * Get all available quote statuses
     *
     * @return array Array of status keys and labels
     */
    public static function get_quote_statuses()
    {
        return array(
            self::STATUS_DRAFT => __('Draft', 'quote-manager-system-for-woocommerce'),
            self::STATUS_SENT => __('Sent', 'quote-manager-system-for-woocommerce'),
            self::STATUS_ACCEPTED => __('Accepted', 'quote-manager-system-for-woocommerce'),
            self::STATUS_REJECTED => __('Rejected', 'quote-manager-system-for-woocommerce'),
            self::STATUS_EXPIRED => __('Expired', 'quote-manager-system-for-woocommerce'),
        );
    }

/**
 * Get the default email template with placeholders
 *
 * @return string Default email template
 */
public static function get_default_email_template()
{
    // Check if a custom template is saved in options
    $saved_template = get_option('quote_manager_email_template');
    
    // If we have a saved template, use it
    if (!empty($saved_template)) {
        return $saved_template;
    }
    
    // Otherwise, return the hardcoded default
    return '<p><strong>Dear {{customer_first_name}},</strong></p>
    <p>Thank you for the trust you place in our company and for your interest in our products and services.</p>
    <p>We are pleased to send you our personalized offer with reference number <strong>#{{quote_id}}</strong>, specifically tailored to your needs. The corresponding PDF file is attached to this email.</p>
    <p>For your convenience, you can <strong>accept or decline the offer online</strong> via the link below: <br><strong>Offer link: <a href="{{quote_view_url}}">{{quote_view_url}}</a></strong></p>
    <p><strong>Useful information:</strong></p>
    <ul>
        <li>Offer valid until: <strong>{{quote_expiry}}</strong></li>
        <li>For any questions or assistance: <strong>(+30) XXX XXX XXXX</strong></li>
    </ul>
    <p>We would be happy to discuss any modifications or clarifications you may need. Our goal is to provide solutions that fully meet your expectations.</p>
    <p>Thank you once again for your preference. We remain at your disposal and look forward to the opportunity of a successful collaboration.</p>
    <p>Warm regards,<br>The {{site_name}} Team</p>';
}

    /**
     * Delete quote PDF files when a quote is rejected
     *
     * @param int $quote_id The quote ID
     * @return bool Whether files were deleted successfully
     */
    public static function delete_quote_pdf_files($quote_id)
    {
        if (empty($quote_id)) {
            return false;
        }

        $upload_dir = wp_upload_dir();
        $quotes_dir = $upload_dir['basedir'] . '/quote-manager/quotes/';

        // List of possible PDF files for this quote
        $pdf_files = array(
            $quotes_dir . 'PROSFORA_' . $quote_id . '.pdf',
            $quotes_dir . 'SIGNED_QUOTE_' . $quote_id . '.pdf',
            $quotes_dir . 'BACKUP_' . $quote_id . '.pdf'
        );

        $deleted = false;

        // Delete each file if it exists
        foreach ($pdf_files as $file) {
            if (file_exists($file)) {
                if (unlink($file)) {
                    $deleted = true;
                } else {
                    error_log('Quote Manager: Failed to delete file: ' . $file);
                }
            }
        }

        return $deleted;
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function run()
    {
        $this->loader->run();
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @return    Quote_Manager_System_For_Woocommerce_Loader    Orchestrates the hooks of the plugin.
     * @since     1.0.0
     */
    public function get_loader()
    {
        return $this->loader;
    }

    /**
     * Ensure required directories exist
     */
    private function ensure_required_directories()
    {
        $upload_dir = wp_upload_dir();

        // Create quotes directory if it doesn't exist
        $quotes_dir = $upload_dir['basedir'] . '/quote-manager/quotes/';
        if (!file_exists($quotes_dir)) {
            wp_mkdir_p($quotes_dir);
        }

        // Create attachments directory if it doesn't exist
        $attachments_dir = $upload_dir['basedir'] . '/quote-manager/attachments/';
        if (!file_exists($attachments_dir)) {
            wp_mkdir_p($attachments_dir);
        }
    }
}