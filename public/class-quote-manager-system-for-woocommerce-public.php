<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://goldenbath.gr
 * @since      1.0.0
 *
 * @package    Quote_Manager_System_For_Woocommerce
 * @subpackage Quote_Manager_System_For_Woocommerce/public
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class Quote_Manager_System_For_Woocommerce_Public
{

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $plugin_name The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $version The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @param string $plugin_name The name of the plugin.
     * @param string $version The version of this plugin.
     * @since    1.0.0
     */
    public function __construct($plugin_name, $version)
    {

        $this->plugin_name = $plugin_name;
        $this->version = $version;

    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_styles()
    {
        // Main CSS
        wp_enqueue_style(
            $this->plugin_name,
            plugin_dir_url(__FILE__) . 'css/quote-manager-system-for-woocommerce-public.css',
            array(),
            $this->version,
            'all'
        );
    
        // Check if we're on a quote page or if the shortcode is on the current page
        global $wp;
        global $post;
        
        $is_quote_page = (
            // URL parameters for quote pages
            isset($_GET['view']) && in_array($_GET['view'], array('quote', 'accept', 'reject', 'response'))
        );
        
        // Also check if current page contains our shortcode
        if (!$is_quote_page && is_a($post, 'WP_Post')) {
            if (
                has_shortcode($post->post_content, 'quote_manager') || 
                has_shortcode($post->post_content, 'quote_response') ||
                // Also check for Gutenberg block version
                strpos($post->post_content, '<!-- wp:shortcode -->[quote_manager') !== false ||
                strpos($post->post_content, '<!-- wp:shortcode -->[quote_response') !== false
            ) {
                $is_quote_page = true;
            }
        }
    
        if ($is_quote_page) {
            // Add the quote response CSS
            wp_enqueue_style(
                $this->plugin_name . '-response',
                plugin_dir_url(__FILE__) . 'css/quote-response.css',
                array(),
                $this->version,
                'all'
            );
            
            // Add the fixes CSS (new file to address WPBakery issues)
            wp_enqueue_style(
                $this->plugin_name . '-fixes',
                plugin_dir_url(__FILE__) . 'css/quote-manager-fixes.css',
                array(),
                $this->version,
                'all'
            );
        }
    }

    /**
     * Register the JavaScript for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts()
    {

        /**
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in Quote_Manager_System_For_Woocommerce_Loader as all of the hooks are defined
         * in that particular class.
         *
         * The Quote_Manager_System_For_Woocommerce_Loader will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         */

        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/quote-manager-system-for-woocommerce-public.js', array('jquery'), $this->version, false);

    }

    /**
     * Register quote shortcodes
     *
     * @since    1.6.0
     */
    public function register_shortcodes()
    {
        // Legacy shortcode for backward compatibility
        add_shortcode('quote_response', array($this, 'quote_response_shortcode'));
        
        // New main shortcode for all quote actions
        add_shortcode('quote_manager', array($this, 'quote_manager_shortcode'));
    }
    
    /**
     * Legacy quote response shortcode callback
     *
     * @return   string  Shortcode output
     * @since    1.6.0
     */
    public function quote_response_shortcode()
    {
        // Forward to the main shortcode with response view
        return $this->quote_manager_shortcode(array('default_view' => 'response'));
    }
    
    /**
     * Main quote manager shortcode
     *
     * @param    array   $atts    Shortcode attributes
     * @return   string  Shortcode output
     * @since    2.0.0
     */
    public function quote_manager_shortcode($atts)
    {
        // Initialize response handler
        require_once QUOTE_MANAGER_PATH . 'public/class-quote-response-handler.php';
        $response_handler = new Quote_Manager_Response_Handler();
        
        // Pass the shortcode call to the response handler
        return $response_handler->quote_manager_shortcode($atts);
    }
}