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
        wp_enqueue_style(
            $this->plugin_name,
            plugin_dir_url(__FILE__) . 'css/quote-manager-system-for-woocommerce-public.css',
            array(),
            $this->version,
            'all'
        );

        // Add the quote response CSS for quote-related pages
        global $wp;
        $is_quote_page = (
            isset($wp->query_vars['view-quote']) ||
            isset($wp->query_vars['accept-quote']) ||
            isset($wp->query_vars['reject-quote']) ||
            is_page('quote-response')
        );

        if ($is_quote_page) {
            wp_enqueue_style(
                $this->plugin_name . '-response',
                plugin_dir_url(__FILE__) . 'css/quote-response.css',
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
     * Register quote response shortcode
     *
     * @since    1.6.0
     */
    public function register_shortcodes()
    {
        add_shortcode('quote_response', array($this, 'quote_response_shortcode'));
    }

    /**
     * Quote response shortcode callback
     *
     * @return   string  Shortcode output
     * @since    1.6.0
     */
    public function quote_response_shortcode()
    {
        ob_start();

        // Get parameters
        $quote_id = isset($_GET['quote_id']) ? intval($_GET['quote_id']) : 0;
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';

        // Validate quote
        $quote = get_post($quote_id);
        if (!$quote || $quote->post_type !== 'customer_quote') {
            return '<p>' . esc_html__('Quote not found.', 'quote-manager-system-for-woocommerce') . '</p>';
        }

        $quote_number = '#' . str_pad($quote_id, 4, '0', STR_PAD_LEFT);
        $customer_name = get_post_meta($quote_id, '_customer_first_name', true) . ' ' . get_post_meta($quote_id, '_customer_last_name', true);

        // Determine message based on status
        if ($status === 'accepted') {
            $title = __('Quote Accepted', 'quote-manager-system-for-woocommerce');
            $message = sprintf(
                __('Thank you, %s! Your acceptance of quote %s has been recorded. We have sent you a confirmation email with the signed quote for your records. We will be in touch shortly to discuss the next steps.', 'quote-manager-system-for-woocommerce'),
                '<strong>' . esc_html($customer_name) . '</strong>',
                '<strong>' . esc_html($quote_number) . '</strong>'
            );
            $icon_class = 'quote-response-icon-accepted';
            $icon = '✓';
        } elseif ($status === 'rejected') {
            $title = __('Quote Declined', 'quote-manager-system-for-woocommerce');
            $message = sprintf(
                __('Thank you, %s. We have recorded your decision to decline quote %s. If you would like to discuss this further or have any questions, please don\'t hesitate to contact us.', 'quote-manager-system-for-woocommerce'),
                '<strong>' . esc_html($customer_name) . '</strong>',
                '<strong>' . esc_html($quote_number) . '</strong>'
            );
            $icon_class = 'quote-response-icon-rejected';
            $icon = '✗';
        } else {
            $title = __('Quote Response', 'quote-manager-system-for-woocommerce');
            $message = __('Your response has been recorded.', 'quote-manager-system-for-woocommerce');
            $icon_class = 'quote-response-icon-default';
            $icon = '!';
        }

        // Output the response page HTML
        ?>
        <div class="quote-response-container">
            <div class="quote-response-content">
                <div class="quote-response-icon <?php echo esc_attr($icon_class); ?>">
                    <?php echo esc_html($icon); ?>
                </div>

                <h1 class="quote-response-title"><?php echo esc_html($title); ?></h1>

                <div class="quote-response-message">
                    <?php echo wp_kses_post($message); ?>
                </div>

                <div class="quote-response-actions">
                    <a href="<?php echo esc_url(home_url()); ?>" class="quote-action-button quote-action-secondary">
                        <?php _e('Return to Homepage', 'quote-manager-system-for-woocommerce'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }
}