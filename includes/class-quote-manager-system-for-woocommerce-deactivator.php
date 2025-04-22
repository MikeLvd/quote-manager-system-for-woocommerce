<?php

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Quote_Manager_System_For_Woocommerce
 * @subpackage Quote_Manager_System_For_Woocommerce/includes
 * @author     Mike Lvd <lavdanitis@gmail.com>
 */
class Quote_Manager_System_For_Woocommerce_Deactivator {
    /**
     * Clean up temporary data when plugin is deactivated
     */
    public static function deactivate() {
        // Clear any scheduled events
        wp_clear_scheduled_hook('quote_manager_scheduled_cleanup');
        
        // Remove transients
        delete_transient('quote_manager_directories_checked');
        delete_transient('quote_manager_check_protection');
        
        // Flush rewrite rules since we're removing a custom post type
        flush_rewrite_rules();
    }
}