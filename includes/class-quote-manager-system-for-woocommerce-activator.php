<?php

/**
 * Fired during plugin activation
 *
 * @link       https://goldenbath.gr
 * @since      1.0.0
 *
 * @package    Quote_Manager_System_For_Woocommerce
 * @subpackage Quote_Manager_System_For_Woocommerce/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Quote_Manager_System_For_Woocommerce
 * @subpackage Quote_Manager_System_For_Woocommerce/includes
 * @author     Mike Lvd <lavdanitis@gmail.com>
 */
class Quote_Manager_System_For_Woocommerce_Activator {

/**
 * Create required directories and files during plugin activation.
 *
 * Creates the necessary directories for quotes and attachments
 * and sets up protection for these directories.
 *
 * @since    1.0.0
 */
public static function activate() {
    error_log('Quote Manager: Activation hook called via Activator class');
    
    // Get upload directory
    $upload_dir = wp_upload_dir();
    
    // Create the quotes directory
    $quotes_dir = $upload_dir['basedir'] . '/quote-manager/quotes/';
    if (!file_exists($quotes_dir)) {
        wp_mkdir_p($quotes_dir);
        error_log('Quote Manager: Created quotes directory: ' . $quotes_dir);
        
        // Create .htaccess to protect direct access
        $htaccess_content = <<<HTACCESS
        # Disable directory browsing
        Options -Indexes
        
        # Prevent direct access to PDF files
        <FilesMatch "\.pdf$">
            # Apache 2.2
            <IfModule !mod_authz_core.c>
                Order deny,allow
                Deny from all
            </IfModule>
            
            # Apache 2.4+
            <IfModule mod_authz_core.c>
                Require all denied
            </IfModule>
        </FilesMatch>
        
        # Add X-Robots-Tag header to prevent indexing
        <IfModule mod_headers.c>
            Header set X-Robots-Tag "noindex, nofollow, noarchive"
        </IfModule>
        
        # Prevent viewing of .htaccess file
        <Files .htaccess>
            # Apache 2.2
            <IfModule !mod_authz_core.c>
                Order deny,allow
                Deny from all
            </IfModule>
            
            # Apache 2.4+
            <IfModule mod_authz_core.c>
                Require all denied
            </IfModule>
        </Files>
        
        # Allow access only from your WordPress site or localhost
        <IfModule mod_rewrite.c>
            RewriteEngine On
            RewriteCond %{HTTP_REFERER} !^https?://(www\.)?.* [NC]
            RewriteCond %{HTTP_REFERER} !^https?://localhost [NC]
            RewriteCond %{HTTP_REFERER} !^https?://127\.0\.0\.1 [NC]
            RewriteRule \.(pdf)$ - [F]
        </IfModule>
        HTACCESS;
                    file_put_contents($quotes_dir . '.htaccess', $htaccess_content);
                }
                
                // Create the attachments directory
                $attachments_dir = $upload_dir['basedir'] . '/quote-manager/attachments/';
                if (!file_exists($attachments_dir)) {
                    wp_mkdir_p($attachments_dir);
                    error_log('Quote Manager: Created attachments directory: ' . $attachments_dir);
                    
                    // Create .htaccess to protect direct access
                    $htaccess_content = <<<HTACCESS
        # Disable directory browsing
        Options -Indexes
        
        # Deny access to .htaccess
        <Files .htaccess>
            Order allow,deny
            Deny from all
        </Files>
        
        # Allow access only through WordPress
        <IfModule mod_rewrite.c>
            RewriteEngine On
            RewriteCond %{HTTP_REFERER} !^.*wp-admin.* [NC]
            RewriteRule .* - [F]
        </IfModule>
        HTACCESS;
        file_put_contents($attachments_dir . '.htaccess', $htaccess_content);
    }
    
    error_log('Quote Manager: Directories created during activation');
    
    // Create quote response page
    self::create_response_page();
}


/**
 * Create the quote response page
 */
public static function create_response_page() {
    // Check if the page already exists
    $page_exists = get_page_by_path('quote-response');
    
    if (!$page_exists) {
        // Create the page
        $page_id = wp_insert_post([
            'post_title'     => __('Quote Response', 'quote-manager-system-for-woocommerce'),
            'post_name'      => 'quote-response',
            'post_status'    => 'publish',
            'post_type'      => 'page',
            'post_content'   => '<!-- wp:shortcode -->[quote_response]<!-- /wp:shortcode -->',
            'comment_status' => 'closed',
            'ping_status'    => 'closed'
        ]);
        
        if ($page_id && !is_wp_error($page_id)) {
            // Save the page ID for future reference
            update_option('quote_manager_response_page_id', $page_id);
        }
    }
}

    /**
     * Ensure required directories exist - can be called anytime
     * 
     * This method can be used as a fallback to check and create
     * directories if they don't exist during regular operation.
     * 
     * @since    1.5.1
     * @return   array    Array of created directory paths
     */
    public static function ensure_directories() {
        $upload_dir = wp_upload_dir();
        $dirs = array();
        
        // Create base directory if needed
        $base_dir = $upload_dir['basedir'] . '/quote-manager/';
        if (!file_exists($base_dir)) {
            wp_mkdir_p($base_dir);
            $dirs['base'] = $base_dir;
        }
        
        // Create quotes directory if needed
        $quotes_dir = $base_dir . 'quotes/';
        if (!file_exists($quotes_dir)) {
            wp_mkdir_p($quotes_dir);
            $dirs['quotes'] = $quotes_dir;
        }
        
        // Create attachments directory if needed
        $attachments_dir = $base_dir . 'attachments/';
        if (!file_exists($attachments_dir)) {
            wp_mkdir_p($attachments_dir);
            $dirs['attachments'] = $attachments_dir;
        }
        
        return $dirs;
    }
}