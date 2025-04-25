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

class Quote_Manager_System_For_Woocommerce_Public {

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param      string    $plugin_name       The name of the plugin.
     * @param      string    $version    The version of this plugin.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {
        // Only load CSS if we're on a quote page or if the shortcode is on the current page
        if ($this->is_quote_page()) {
            wp_enqueue_style(
                $this->plugin_name, 
                plugin_dir_url(__FILE__) . 'css/quote-manager-public.css',
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
    public function enqueue_scripts() {
        // Only load JS if we're on a quote page or if the shortcode is on the current page
        if ($this->is_quote_page()) {
            // Enqueue the main script
            wp_enqueue_script(
                $this->plugin_name,
                plugin_dir_url(__FILE__) . 'js/quote-manager-system-for-woocommerce-public.js',
                array('jquery'),
                $this->version,
                true
            );
            
            // Determine the current view
            $view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : '';
            
            // Load SignaturePad library for accept view
            if ($view === 'accept') {
                wp_enqueue_script(
                    'signature-pad',
                    'https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js',
                    array(),
                    '4.0.0',
                    true
                );
            }
            
            // Add nonce field for AJAX
            if ($view === 'accept') {
                wp_nonce_field('quote_accept_nonce', 'quote-accept-nonce');
            } elseif ($view === 'reject') {
                wp_nonce_field('quote_reject_nonce', 'quote-reject-nonce');
            }
            
            // Localize script with AJAX URL and translations
            wp_localize_script($this->plugin_name, 'quote_manager_vars', array(
                'ajax_url' => admin_url('admin-ajax.php')
            ));
            
            // Add translations
            wp_localize_script($this->plugin_name, 'quote_manager_i18n', array(
                'provide_signature' => __('Please provide your signature to accept the quote.', 'quote-manager-system-for-woocommerce'),
                'processing' => __('Processing...', 'quote-manager-system-for-woocommerce'),
                'submitting' => __('Submitting your response. Please wait...', 'quote-manager-system-for-woocommerce'),
                'quote_accepted' => __('Quote accepted successfully! Redirecting...', 'quote-manager-system-for-woocommerce'),
                'quote_rejected' => __('Quote declined successfully! Redirecting...', 'quote-manager-system-for-woocommerce'),
                'error_submitting' => __('An error occurred while submitting your response. Please try again.', 'quote-manager-system-for-woocommerce'),
                'error_server' => __('A server error occurred. Please try again later.', 'quote-manager-system-for-woocommerce')
            ));
        }
    }
    
    /**
     * Check if the current page is a quote page or contains the shortcode
     *
     * @return bool True if it's a quote page, false otherwise
     */
    private function is_quote_page() {
        global $post;
        
        // Check URL parameters for quote pages
        if (isset($_GET['view']) && in_array($_GET['view'], array('quote', 'accept', 'reject', 'response'))) {
            return true;
        }
        
        // Check if current page contains our shortcode
        if (is_a($post, 'WP_Post')) {
            if (
                has_shortcode($post->post_content, 'quote_manager') || 
                has_shortcode($post->post_content, 'quote_response') ||
                // Also check for Gutenberg block version
                strpos($post->post_content, '<!-- wp:shortcode -->[quote_manager') !== false ||
                strpos($post->post_content, '<!-- wp:shortcode -->[quote_response') !== false
            ) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function register_shortcodes() {
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
    public function quote_response_shortcode() {
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
    public function quote_manager_shortcode($atts) {
        // Initialize response handler
        require_once QUOTE_MANAGER_PATH . 'public/class-quote-response-handler.php';
        $response_handler = new Quote_Manager_Response_Handler();
        
        // Pass the shortcode call to the response handler
        return $response_handler->quote_manager_shortcode($atts);
    }
}