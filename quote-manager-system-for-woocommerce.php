<?php
/**
 * The plugin bootstrap file
 *
 * @wordpress-plugin
 * Plugin Name:       Quote Manager System For WooCommerce
 * Plugin URI:        https://github.com/MikeLvd/quote-manager-system-for-woocommerce
 * Description:       A custom WordPress plugin that allows you to create detailed product offers inside the WooCommerce backend. Ideal for retail stores, B2B sales, and client advanced quotations.
 * Version:           1.8.2
 * Author:            Mike Lvd
 * Author URI:        https://goldenbath.gr/
 * Requires at least: 5.9
 * Tested up to:      6.8
 * Requires PHP:      8.0
 * WC requires at least: 9.0
 * WC tested up to:   9.8
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       quote-manager-system-for-woocommerce
 * Domain Path:       /languages
 * Requires Plugins:  woocommerce
 */

// If this file is called directly, abort.
use Automattic\WooCommerce\Utilities\FeaturesUtil;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Currently plugin version.
 */
define('QUOTE_MANAGER_VERSION', '1.8.2');
define('QUOTE_MANAGER_PATH', plugin_dir_path(__FILE__));
define('QUOTE_MANAGER_URL', plugin_dir_url(__FILE__));

/**
 * Load the Activator class for activation tasks
 */
require_once QUOTE_MANAGER_PATH . 'includes/class-quote-manager-system-for-woocommerce-activator.php';
require_once QUOTE_MANAGER_PATH . 'includes/class-quote-manager-system-for-woocommerce-deactivator.php';

/**
 * Register activation hook
 */
register_activation_hook(__FILE__, array('Quote_Manager_System_For_Woocommerce_Activator', 'activate'));

// Register deactivation hook
register_deactivation_hook(__FILE__, array('Quote_Manager_System_For_Woocommerce_Deactivator', 'deactivate'));

/**
 * Check for WooCommerce dependency
 */
add_action('plugins_loaded', 'quote_manager_check_woocommerce_dependency');

add_action('before_woocommerce_init', function () {
    if (class_exists(FeaturesUtil::class)) {
        FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
});

/**
 * Add a fallback check to ensure directories exist
 */
add_action('admin_init', 'quote_manager_ensure_directories_exist');

function quote_manager_ensure_directories_exist()
{
    // Only run once per session
    if (!get_transient('quote_manager_directories_checked')) {
        Quote_Manager_System_For_Woocommerce_Activator::ensure_directories();
        // Set transient to avoid checking too often
        set_transient('quote_manager_directories_checked', true, 24 * HOUR_IN_SECONDS);
    }
}

function quote_manager_check_woocommerce_dependency()
{
    // Check if WooCommerce is active
    $is_woocommerce_active = class_exists('WooCommerce') || in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')));

    if (!$is_woocommerce_active) {
        // Show admin notice
        add_action('admin_notices', function () {
            $message = sprintf(
                esc_html__('Quote Manager System For WooCommerce requires %s to be installed and activated.', 'quote-manager-system-for-woocommerce'),
                '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>'
            );
            echo '<div class="notice notice-error"><p>' . $message . '</p></div>';
        });

        // Deactivate the plugin
        if (is_admin()) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            deactivate_plugins(plugin_basename(__FILE__));

            // Remove the "Plugin activated" notice
            if (isset($_GET['activate'])) {
                unset($_GET['activate']);
            }
        }
    } else {
        // WooCommerce is active - load plugin functionality
        require_once QUOTE_MANAGER_PATH . 'includes/class-quote-manager-system-for-woocommerce.php';

        // Run the plugin
        function run_quote_manager_system_for_woocommerce()
        {
            $plugin = new Quote_Manager_System_For_Woocommerce();
            $plugin->run();
        }

        run_quote_manager_system_for_woocommerce();
    }
}