<?php
/**
 * Fired when the plugin is uninstalled.
 * @link       https://goldenbath.gr
 * @since      1.0.0
 *
 * @package    Quote_Manager_System_For_Woocommerce
 */

// If uninstall is not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete all quotes
$quotes = get_posts([
    'post_type'    => 'customer_quote',
    'numberposts'  => -1,
    'post_status'  => 'any',
    'fields'       => 'ids',
]);

foreach ($quotes as $quote_id) {
    wp_delete_post($quote_id, true); // true = force delete (bypass trash)
}

// Remove plugin options
$options = [
    'quote_manager_company_name',
    'quote_manager_company_address',
    'quote_manager_company_city',
    'quote_manager_company_phone',
    'quote_manager_company_email',
    'quote_manager_company_logo',
    'quote_manager_default_terms'
];

// Check if we should delete files
$delete_files = get_option('quote_manager_delete_files_on_uninstall') === 'yes';

// Always remove all plugin settings at the end
$options[] = 'quote_manager_delete_files_on_uninstall';

foreach ($options as $option) {
    delete_option($option);
}

// Only delete files if the setting is enabled
if ($delete_files) {
    // Remove upload directories
    $upload_dir = wp_upload_dir();
    $quote_manager_dir = $upload_dir['basedir'] . '/quote-manager/';

    // Recursive directory removal function
    function quote_manager_recursive_rmdir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . DIRECTORY_SEPARATOR . $object)) {
                        quote_manager_recursive_rmdir($dir . DIRECTORY_SEPARATOR . $object);
                    } else {
                        unlink($dir . DIRECTORY_SEPARATOR . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }

    // Delete all files
    quote_manager_recursive_rmdir($quote_manager_dir);
}