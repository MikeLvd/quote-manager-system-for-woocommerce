<?php
// Απλό class για να οργανώνουμε τα shortcodes
class Quote_Manager_Shortcodes {

    public function __construct() {
        add_shortcode('quote_manager_view', array($this, 'render_view_quote'));
    }

    public function render_view_quote($atts) {
        ob_start();

        $quote_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $token    = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';

        if (!$quote_id || !$token) {
            echo '<div class="quote-error">' . esc_html__('Missing quote parameters.', 'quote-manager') . '</div>';
            return ob_get_clean();
        }

        $valid_token = get_post_meta($quote_id, '_quote_access_token', true);
        if (!$valid_token || !hash_equals($valid_token, $token)) {
            echo '<div class="quote-error">' . esc_html__('Invalid access token. Please check the link provided.', 'quote-manager') . '</div>';
            return ob_get_clean();
        }

        // Κάνουμε setup το global $post αν χρειαστεί WPBakery
        global $post;
        $post = get_post($quote_id);
        setup_postdata($post);

        // Include το template
        $template_path = plugin_dir_path(__FILE__) . '../public/templates/view-quote-shortcode.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<div class="quote-error">' . esc_html__('Quote template not found.', 'quote-manager') . '</div>';
        }

        wp_reset_postdata();
        return ob_get_clean();
    }
}
