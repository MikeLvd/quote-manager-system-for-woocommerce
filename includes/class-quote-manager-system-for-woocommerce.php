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

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        
        // Add email tracking handler
        $this->setup_email_tracking();
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
}