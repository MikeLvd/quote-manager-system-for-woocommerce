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

/**
 * The core plugin class.
 */
class Quote_Manager_System_For_Woocommerce {

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      Quote_Manager_System_For_Woocommerce_Loader    $loader    Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $plugin_name    The string used to uniquely identify this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $version    The current version of the plugin.
     */
    protected $version;
    
    /**
     * Define the core functionality of the plugin.
     */
    public function __construct() {
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
    }

    /**
     * Quote statuses
     */
    const STATUS_DRAFT = 'draft';
    const STATUS_SENT = 'sent';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_REJECTED = 'rejected';
    const STATUS_EXPIRED = 'expired';
    
    /**
     * Get all available quote statuses
     * 
     * @return array Array of status keys and labels
     */
    public static function get_quote_statuses() {
        return array(
            self::STATUS_DRAFT => __('Draft', 'quote-manager-system-for-woocommerce'),
            self::STATUS_SENT => __('Sent', 'quote-manager-system-for-woocommerce'),
            self::STATUS_ACCEPTED => __('Accepted', 'quote-manager-system-for-woocommerce'),
            self::STATUS_REJECTED => __('Rejected', 'quote-manager-system-for-woocommerce'),
            self::STATUS_EXPIRED => __('Expired', 'quote-manager-system-for-woocommerce'),
        );
    }
    
    /**
     * Get status label from status key
     * 
     * @param string $status Status key
     * @return string Status label
     */
    public static function get_status_label($status) {
        $statuses = self::get_quote_statuses();
        return isset($statuses[$status]) ? $statuses[$status] : __('Unknown', 'quote-manager-system-for-woocommerce');
    }

    /**
     * Setup email tracking functionality
     */
    private function setup_email_tracking() {
        require_once QUOTE_MANAGER_PATH . 'includes/class-email-manager.php';
        $email_manager = new Quote_Manager_Email_Manager();
        add_action('init', array($email_manager, 'handle_email_open_tracking'));
    }

    /**
     * Load the required dependencies for this plugin.
     */
    private function load_dependencies() {
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
        
        $this->loader = new Quote_Manager_System_For_Woocommerce_Loader();
    }

    /**
     * Ensure required directories exist
     */
    private function ensure_required_directories() {
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

    /**
     * Define the locale for this plugin for internationalization.
     *
     * Uses the Quote_Manager_System_For_Woocommerce_i18n class in order to set the domain and to register the hook
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function set_locale() {
        $plugin_i18n = new Quote_Manager_System_For_Woocommerce_i18n();
        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }
    
    /**
     * Register all of the hooks related to the admin area functionality
     */
    private function define_admin_hooks() {
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
		$this->loader->add_action('wp_ajax_quote_manager_create_customer', $ajax, 'create_customer_from_quote');
		$this->loader->add_action('wp_ajax_quote_manager_update_status', $ajax, 'update_quote_status');
		$this->loader->add_action('wp_ajax_quote_manager_check_expired', $ajax, 'check_expired_quotes');
        
        // Settings
        $settings = new Quote_Manager_Settings();
        $this->loader->add_action('admin_init', $settings, 'register_settings');
        $this->loader->add_action('admin_menu', $settings, 'add_settings_page');
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_public_hooks() {
    $plugin_public = new Quote_Manager_System_For_Woocommerce_Public($this->get_plugin_name(), $this->get_version());
    
    $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
    $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
    
    // Init Response Handler
    require_once QUOTE_MANAGER_PATH . 'public/class-quote-response-handler.php';
    $response_handler = new Quote_Manager_Response_Handler();
}

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function run() {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @since     1.0.0
     * @return    string    The name of the plugin.
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @since     1.0.0
     * @return    Quote_Manager_System_For_Woocommerce_Loader    Orchestrates the hooks of the plugin.
     */
    public function get_loader() {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since     1.0.0
     * @return    string    The version number of the plugin.
     */
    public function get_version() {
        return $this->version;
    }
    
    /**
     * Get the default email template with placeholders
     *
     * @return string Default email template
     */
    public static function get_default_email_template() {
        return '
        <p>Dear {{customer_first_name}},</p>
        
        <p>Thank you for your interest in our products and services. We are pleased to present you with a customized solution for your needs.</p>
    
    
        
        <p>Attached you will find our detailed quote (no. #{{quote_id}}) in PDF format, which has been prepared specifically for you.</p>
        
        <p><strong>You can also view and respond to this quote online at:</strong> <a href="{{quote_view_url}}">{{quote_view_url}}</a></p>
        
        <p><strong>Important information:</strong><br>
        - Quote valid until: {{quote_expiry}}<br>
        - For immediate assistance: (+30) 210 XXX XXXX</p>
        
        <p>Please review the quote and do not hesitate to contact us for any clarifications or adjustments you would like. We are always available to discuss how we can better meet your needs.</p>
        
        <p>Thank you for choosing our company and we look forward to working with you!</p>
        
        <p>Best regards,<br>
        {{site_name}}</p>';
    }
}