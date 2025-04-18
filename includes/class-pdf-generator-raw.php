<?php
/**
 * Raw PDF generation functionality
 *
 * @link       https://goldenbath.gr
 * @since      1.0.0
 *
 * @package    Quote_Manager_System_For_Woocommerce
 * @subpackage Quote_Manager_System_For_Woocommerce/includes
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) exit;

// Import the required DomPDF classes at the global scope
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Class that handles the raw PDF generation
 */
class Quote_Manager_PDF_Generator_Raw {

    /**
     * Generate raw PDF content
     *
     * @param int $quote_id The quote ID
     * @return string The PDF content
     */
    public function generate($quote_id) {
        require_once plugin_dir_path(dirname(__FILE__)) . 'lib/dompdf/autoload.inc.php';
        
        if (!$quote_id || get_post_type($quote_id) !== 'customer_quote') {
            return '';
        }
        
        // Get quote data
        $quote = get_post($quote_id);
        $products = get_post_meta($quote_id, '_quote_products', true);
        if (!is_array($products)) $products = [];
        
        $with_vat = get_post_meta($quote_id, '_quote_include_vat', true) === '1';
        
        // Customer details
        $customer_name = get_post_meta($quote_id, '_customer_first_name', true) . ' ' . get_post_meta($quote_id, '_customer_last_name', true);
        $customer_company = get_post_meta($quote_id, '_customer_company', true);
        $customer_address = get_post_meta($quote_id, '_customer_address', true);
        $customer_area = get_post_meta($quote_id, '_customer_area', true);
        $customer_phone = get_post_meta($quote_id, '_customer_phone', true);
        $customer_email = get_post_meta($quote_id, '_customer_email', true);
        
        // Company details (from settings)
        $company_name = get_option('quote_manager_company_name', get_bloginfo('name'));
        $company_address = get_option('quote_manager_company_address', '');
        $company_city = get_option('quote_manager_company_city', '');
        $company_phone = get_option('quote_manager_company_phone', '');
        $company_email = get_option('quote_manager_company_email', get_bloginfo('admin_email'));
        $company_logo = get_option('quote_manager_company_logo', '');
        
        // Dates and quote number
        $date = date_i18n('d/m/Y', strtotime($quote->post_date));
        $expiration_date = get_post_meta($quote_id, '_quote_expiration_date', true);
        if (!$expiration_date) {
            // Default: 30 days from creation
            $expiration_date = date_i18n('d/m/Y', strtotime('+30 days', strtotime($quote->post_date)));
        }
        $quote_number = '#' . str_pad($quote_id, 4, '0', STR_PAD_LEFT);
        
        // Tax settings
        $tax_rate = 24;
        $tax_multiplier = 1 + ($tax_rate / 100);
        
        // Calculate totals
        $subtotal = 0;
        $total_tax = 0;
        
        // Define colors - modern color palette
        $primary_color = '#254343'; // Primary blue color
        $secondary_color = '#052010'; // Dark blue for contrast
        $accent_color = '#052010'; // Light blue for accents
        $highlight_color = '#d3dfe1'; // Very light blue for backgrounds
        $gray_shade = '#f8fafc'; // Light gray for alternating rows
        $border_color = '#e2e8f0'; // Border color
        $text_color = '#1e293b'; // Text color
        $light_text_color = '#64748b'; // Color for less prominent text
        
        // Start HTML buffer
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title><?php echo __('Quote', 'quote-manager-system-for-woocommerce') . ' ' . $quote_number; ?></title>
            <style>
                /* Basic settings */
                @page {
                    margin: 0;
                    padding: 0;
                }
                body {
                    font-family: DejaVu Sans, sans-serif;
                    font-size: 10pt;
                    margin: 0;
                    padding: 0;
                    color: <?php echo $text_color; ?>;
                    line-height: 1.6;
                }
                p {
                    margin: 0 0 5px 0;
                }
                
                /* Use tables for compatibility with DomPDF */
                table {
                    width: 100%;
                    border-collapse: collapse;
                }
                
                /* Containers */
                .page-wrapper {
                    position: relative;
                    padding: 0;
                }
                .content-container {
                    padding-left: 20px;
                    padding-right: 20px;
                }
                
                /* Header Styling */
                .header-area {
                    position: relative;
                    background-color: #fff;
                    padding: 0;
                }
                .header-top-band {
                    height: 15px;
                    width: 100%;
                    background-color: <?php echo $primary_color; ?>;
                }
                .header-curve {
                    height: 60px;
                    width: 100%;
                    background-color: <?php echo $primary_color; ?>;
                    position: relative;
                    z-index: 1;
                }
                .header-curve::after {
                    content: '';
                    position: absolute;
                    bottom: 0;
                    left: 0;
                    width: 100%;
                    height: 60px;
                    background-color: #fff;
                    border-top-right-radius: 60px;
                    z-index: 2;
                }
                .logo-company-container {
                    padding-left: 40px;
                    padding-right: 40px;
                    position: relative;
                    z-index: 5;
                }
                .company-logo {
                    max-height: 160px;
                    max-width: 280px;
                }
                .company-name {
                    font-size: 24pt;
                    font-weight: bold;
                    color: <?php echo $primary_color; ?>;
                    margin: 0;
                    padding: 0;
                }
                .company-info {
                    font-size: 9pt;
                    color: <?php echo $light_text_color; ?>;
                }
                
                /* Quote Title & Info */
                .quote-title-band {
                    background-color: <?php echo $primary_color; ?>;
                    text-align: center;
                    margin: 10px 0;
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                }
                .quote-title {
                    color: white;
                    font-size: 20pt;
                    font-weight: bold;
                    letter-spacing: 1px;
                    text-transform: uppercase;
                    margin: 0;
                    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.2);
                }
                .quote-info {
                    background-color: #fff;
                    margin: 20px 0;
                    text-align: right;
                    padding: 5px 0;
                }
                .quote-info-table {
                    width: auto;
                    margin-left: auto;
                }
                .quote-info-label {
                    font-weight: bold;
                    text-align: right;
                    padding-right: 15px;
                }
                .quote-info-value {
                    text-align: right;
                    color: <?php echo $primary_color; ?>;
                    font-weight: bold;
                }
                
                /* Client Box */
                .client-box {
                    margin: 15px 0;
                    border-left: 5px solid <?php echo $primary_color; ?>;
                    background-color: <?php echo $highlight_color; ?>;
                    padding: 15px;
                    position: relative;
                }
                .client-label {
                    position: absolute;
                    top: -12px;
                    left: 15px;
                    background-color: <?php echo $primary_color; ?>;
                    color: white;
                    padding: 3px 15px;
                    font-weight: bold;
                    text-transform: uppercase;
                    font-size: 9pt;
                    border-radius: 3px;
                }
                .client-name {
                    font-weight: bold;
                    font-size: 12pt;
                    margin-top: 10px;
                }
                .client-details {
                    font-size: 9pt;
                }
                .project-name {
                    font-size: 16px;
                    margin-bottom: 15px;
                    color: #333;
                }
                .project-name strong {
                    color: #0073aa;
                }
                /* Products Table */
                .products-container {
                    margin: 20px 0;
                }
                .products-table {
                    width: 100%;
                    border-collapse: separate;
                    border-spacing: 0;
                    border-radius: 5px;
                    overflow: hidden;
                    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
                }
                .products-table th {
                    background-color: <?php echo $primary_color; ?>;
                    color: white;
                    text-align: center;
                    padding: 5px 5px;
                    font-weight: bold;
                    font-size: 7pt;
                    border-bottom: 2px solid <?php echo $secondary_color; ?>;
                }
                .products-table td {
                    font-size: 8pt;
                    padding: 8px;
                    text-align: center;
                    border-bottom: 1px solid <?php echo $border_color; ?>;
                    vertical-align: middle;
                }
                .products-table tr:nth-child(even) td {
                    background-color: <?php echo $gray_shade; ?>;
                }
                .products-table tr:last-child td {
                    font-size: 9pt;
                    border-bottom: none;
                }
                .products-table .product-image {
                    max-width: 60px;
                    max-height: 60px;
                }
                .product-name-cell {
                    text-align: left;
                    font-weight: 500;
                }
                .discount-cell {
                    color: #b6c3c7; /* Red for discounts */
                    font-weight: bold;
                }
                .price-original {
                    text-decoration: line-through;
                    color: <?php echo $light_text_color; ?>;
                    font-size: 8pt;
                }
                .price-final {
                    font-weight: bold;
                    color: <?php echo $text_color; ?>;
                }
                .quantity-cell {
                    font-weight: bold;
                }
                .total-cell {
                    font-weight: bold;
                    color: <?php echo $secondary_color; ?>;
                }
                
                /* Totals Area */
                .totals-area {
                    width: 40%;
                    float: right;
                }
                .totals-table {
                    width: 100%;
                    border-collapse: collapse;
                }
                .totals-table td {
                    padding: 8px 10px;
                    text-align: right;
                    border-bottom: 1px solid <?php echo $border_color; ?>;
                }
                .totals-table tr:last-child td {
                    border-bottom: none;
                    padding-top: 5px;
                }
                .totals-table .total-label {
                    width: 60%;
                    font-weight: normal;
                    color: <?php echo $light_text_color; ?>;
                }
                .totals-table .total-value {
                    width: 40%;
                    font-weight: bold;
                }
                .grand-total-row td {
                    background-color: <?php echo $highlight_color; ?>;
                    font-size: 11pt;
                    padding: 15px;
                    border-bottom: none;
                }
                .grand-total-label {
                    color: <?php echo $text_color; ?>;
                    font-weight: bold;
                }
                .grand-total-value {
                    color: <?php echo $primary_color; ?>;
                    font-weight: bold;
                }
                
                /* Signature Area */
                .footer-content {
                    clear: both;
                    padding-top: 100px;
                    position: relative;
                }
                .signature-area {
                    width: 50%;
                    float: right;
                }
                .signature-line {
                    border-top: 1px solid <?php echo $text_color; ?>;
                    padding-top: 8px;
                    text-align: center;
                    color: <?php echo $light_text_color; ?>;
                    font-style: italic;
                }
                
                /* Notes and Terms */
                .notes-area {
                    clear: both;
                    padding-top: 20px;
                    margin-top: 30px;
                    font-size: 9pt;
                    color: <?php echo $light_text_color; ?>;
                    text-align: left;
                    font-style: italic;
                    border-top: 1px dashed <?php echo $border_color; ?>;
                    padding-bottom: 20px;
                }
                
                /* Bottom Area */
                .page-bottom {
                    background-color: <?php echo $primary_color; ?>;
                    padding: 15px 40px;
                    color: white;
                    font-size: 9pt;
                    text-align: center;
                    position: absolute;
                    bottom: 0;
                    width: 100%;
                    box-sizing: border-box;
                }
                
                /* Helper Classes */
                .text-right { text-align: right; }
                .text-center { text-align: center; }
                .page-break { page-break-after: always; }
                .no-margin { margin: 0; }
                .no-padding { padding: 0; }
            </style>
        </head>
        <body>
            <div class="page-wrapper">
                <!-- Header with blue curve -->
                <div class="header-area">
                    <div class="header-top-band"></div>
                    <div class="header-curve"></div>
                    
                    <!-- Logo and company info -->
                    <div class="logo-company-container">
                        <table>
                            <tr>
                                <td width="50%" style="vertical-align: middle;">
                                    <?php if (!empty($company_logo)): ?>
                                    <img src="<?php echo esc_url($company_logo); ?>" alt="<?php echo esc_attr($company_name); ?>" class="company-logo">
                                    <?php else: ?>
                                    <div class="company-name"><?php echo esc_html($company_name); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td width="50%" style="vertical-align: top; text-align: right;" class="company-info">
                                    <?php echo esc_html($company_address); ?><br>
                                    <?php echo esc_html($company_city); ?><br>
                                    <?php _e('Tel:', 'quote-manager-system-for-woocommerce'); ?> <?php echo esc_html($company_phone); ?><br>
                                    <?php echo esc_html($company_email); ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="content-container">
                    <!-- Quote Title -->
                    <div class="quote-title-band">
                        <div class="quote-title"><?php _e('QUOTE', 'quote-manager-system-for-woocommerce'); ?></div>
                    </div>
                    
                    <!-- Quote Info & Client Box -->
                    <table>
                        <tr>
                            <td width="60%" style="vertical-align: top;">
                                <!-- Client Box -->
                                <div class="client-box">
                                    <div class="client-label"><?php _e('TO', 'quote-manager-system-for-woocommerce'); ?></div>
                                    <div class="client-name"><?php echo esc_html($customer_name); ?></div>
                                    <div class="client-details">
                                        <?php if (!empty($customer_company)): ?>
                                        <?php echo esc_html($customer_company); ?><br>
                                        <?php endif; ?>
                                        <?php echo esc_html($customer_address); ?><br>
                                        <?php echo esc_html($customer_area); ?><br>
                                        <?php _e('Tel:', 'quote-manager-system-for-woocommerce'); ?> <?php echo esc_html($customer_phone); ?>
                                        <?php if (!empty($customer_email)): ?>
                                        <br><?php echo esc_html($customer_email); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td width="40%" style="vertical-align: top;">
                                <!-- Quote Information -->
                                <div class="quote-info">
                                    <table class="quote-info-table">
                                        <tr>
                                            <td class="quote-info-label"><?php _e('Number:', 'quote-manager-system-for-woocommerce'); ?></td>
                                            <td class="quote-info-value"><?php echo esc_html($quote_number); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="quote-info-label"><?php _e('Date:', 'quote-manager-system-for-woocommerce'); ?></td>
                                            <td class="quote-info-value"><?php echo esc_html($date); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="quote-info-label"><?php _e('Valid until:', 'quote-manager-system-for-woocommerce'); ?></td>
                                            <td class="quote-info-value"><?php echo esc_html($expiration_date); ?></td>
                                        </tr>
                                        <?php 
                                        // Show project name if exists
                                        $project_name = get_post_meta($quote_id, '_project_name', true);
                                        if (!empty($project_name)) : 
                                        ?>
                                        <tr>
                                            <td class="quote-info-label"><?php _e('Project:', 'quote-manager-system-for-woocommerce'); ?></td>
                                            <td class="quote-info-value"><?php echo esc_html($project_name); ?></td>
                                        </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                            </td>
                        </tr>
                    </table>
                    
                    <!-- Products Table -->
                    <div class="products-container">
                        <table class="products-table">
                            <thead>
                                <tr>
                                    <th width="4%">#</th>
                                    <th width="8%"><?php _e('Image', 'quote-manager-system-for-woocommerce'); ?></th>
                                    <th width="34%"><?php _e('Product', 'quote-manager-system-for-woocommerce'); ?></th>
                                    <th width="10%"><?php _e('Price', 'quote-manager-system-for-woocommerce'); ?></th>
                                    <th width="8%"><?php _e('Disc(%)', 'quote-manager-system-for-woocommerce'); ?></th>
                                    <th width="10%"><?php _e('Value', 'quote-manager-system-for-woocommerce'); ?></th>
                                    <?php if ($with_vat): ?>
                                    <th width="15%"><?php _e('Value (incl. VAT)', 'quote-manager-system-for-woocommerce'); ?></th>
                                    <?php endif; ?>
                                    <th width="6%"><?php _e('Qty', 'quote-manager-system-for-woocommerce'); ?></th>
                                    <th width="12%"><?php _e('Total', 'quote-manager-system-for-woocommerce'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $i = 0;
                                $items_per_page = 15; // Items per page
                                $total_items = count($products);
                                $total_pages = ceil($total_items / $items_per_page);
                                $current_page = 1;
                                
                                foreach ($products as $prod) {
                                    $i++;
                                    $title = $prod['title'] ?? '';
                                    $qty = intval($prod['qty'] ?? 1);
                                    $list_price = floatval($prod['list_price'] ?? 0);
                                    $discount = floatval($prod['discount'] ?? 0);
                                    $price_excl = floatval($prod['final_price_excl'] ?? 0);
                                    $price_incl = $price_excl * $tax_multiplier;
                                    $line_total = $price_excl * $qty;
                                    $line_total_with_vat = $price_incl * $qty;
                                    $line_tax = $with_vat ? ($line_total_with_vat - $line_total) : 0;
                
                                    $subtotal += $line_total;
                                    $total_tax += $line_tax;
                
                                    $image_url = $prod['image'] ?? '';
                                    if (empty($image_url)) {
                                        $image_url = function_exists('wc_placeholder_img_src') ? wc_placeholder_img_src() : '';
                                    }
                                    
                                    // Check if we need a page break
                                    if ($i > $items_per_page && ($i - 1) % $items_per_page === 0) {
                                        echo '</tbody></table></div>';
                                        echo '<div class="page-break"></div>';
                                        echo '<div class="products-container"><table class="products-table"><thead><tr>';
                                        echo '<th width="4%">#</th>';
                                        echo '<th width="8%">' . __('Image', 'quote-manager-system-for-woocommerce') . '</th>';
                                        echo '<th width="34%">' . __('Product', 'quote-manager-system-for-woocommerce') . '</th>';
                                        echo '<th width="10%">' . __('Price', 'quote-manager-system-for-woocommerce') . '</th>';
                                        echo '<th width="8%">' . __('Disc(%)', 'quote-manager-system-for-woocommerce') . '</th>';
                                        echo '<th width="10%">' . __('Value', 'quote-manager-system-for-woocommerce') . '</th>';
                                        if ($with_vat) {
                                            echo '<th width="15%">' . __('Value (incl. VAT)', 'quote-manager-system-for-woocommerce') . '</th>';
                                        }
                                        echo '<th width="6%">' . __('Qty', 'quote-manager-system-for-woocommerce') . '</th>';
                                        echo '<th width="12%">' . __('Total', 'quote-manager-system-for-woocommerce') . '</th>';
                                        echo '</tr></thead><tbody>';
                                        $current_page++;
                                    }
                                    ?>
                                    <tr>
                                        <td><?php echo $i; ?></td>
                                        <td>
                                            <?php if (!empty($image_url)): ?>
                                            <img src="<?php echo esc_url($image_url); ?>" alt="" class="product-image">
                                            <?php endif; ?>
                                        </td>
                                        <td class="product-name-cell"><?php echo esc_html($title); ?></td>
                                       <td>
                                           <span class="price-final"><?php echo number_format($list_price, 2, ',', '.'); ?>€</span>
                                       </td>
                                        <td class="discount-cell"><?php echo $discount > 0 ? number_format($discount, 2, ',', '.') . '%' : '-'; ?></td>
                                        <td class="price-final"><?php echo number_format($price_excl, 2, ',', '.'); ?>€</td>
                                        <?php if ($with_vat): ?>
                                        <td><?php echo number_format($price_incl, 2, ',', '.'); ?>€</td>
                                        <?php endif; ?>
                                        <td class="quantity-cell"><?php echo $qty; ?></td>
                                        <td class="total-cell"><?php echo number_format($with_vat ? $line_total_with_vat : $line_total, 2, ',', '.'); ?>€</td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Totals Area -->
                    <div class="totals-area">
                        <table class="totals-table">
                            <tr>
                                <td class="total-label"><?php _e('Subtotal:', 'quote-manager-system-for-woocommerce'); ?></td>
                                <td class="total-value"><?php echo number_format($subtotal, 2, ',', '.'); ?>€</td>
                            </tr>
                            <?php if ($with_vat): ?>
                            <tr>
                                <td class="total-label"><?php _e('VAT:', 'quote-manager-system-for-woocommerce'); ?></td>
                                <td class="total-value"><?php echo number_format($total_tax, 2, ',', '.'); ?>€</td>
                            </tr>
                            <?php endif; ?>
                            <tr class="grand-total-row">
                                <td class="grand-total-label"><?php _e('TOTAL:', 'quote-manager-system-for-woocommerce'); ?></td>
                                <td class="grand-total-value"><?php echo number_format($subtotal + $total_tax, 2, ',', '.'); ?>€</td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Signature -->
                    <div class="footer-content">
                        <div class="signature-area">
                            <div class="signature-line"><?php _e('Signature', 'quote-manager-system-for-woocommerce'); ?></div>
                        </div>
                    </div>
                    
                    <!-- Notes and Terms -->
                    <div class="notes-area">
                        <p><?php _e('This quote is valid until the date specified above, unless otherwise indicated.', 'quote-manager-system-for-woocommerce'); ?></p>
                        <?php 
                        // Add custom quote notes if available
                        $quote_notes = get_post_meta($quote_id, '_quote_notes', true);
                        if (!empty($quote_notes)): 
                        ?>
                        <p style="margin-top: 10px;"><?php echo esc_html($quote_notes); ?></p>
                        <?php endif; 
                        
                        // Add terms & conditions
                        $quote_terms = get_post_meta($quote_id, '_quote_terms', true);
                        if (empty($quote_terms)) {
                            $quote_terms = get_option('quote_manager_default_terms', '');
                        }
                        
                        if (!empty($quote_terms)):
                            // Parse the placeholders in the terms
                            $parsed_terms = $this->parse_terms_placeholders($quote_terms, [
                                'customer_first_name' => get_post_meta($quote_id, '_customer_first_name', true),
                                'customer_last_name'  => get_post_meta($quote_id, '_customer_last_name', true),
                                'customer_name'       => trim(get_post_meta($quote_id, '_customer_first_name', true) . ' ' . get_post_meta($quote_id, '_customer_last_name', true)),
                                'quote_id'            => $quote_id,
                                'quote_expiry'        => $expiration_date,
                                'company_name'        => $company_name,
                                'today'               => date_i18n('d/m/Y'),
                            ]);
                        ?>
                        <div class="quote-terms-section" style="margin-top: 15px; border-top: 1px solid #e2e8f0; padding-top: 10px;">
                            <h3 style="font-size: 12pt; margin-bottom: 8px;"><?php _e('Terms & Conditions', 'quote-manager-system-for-woocommerce'); ?></h3>
                            <?php echo wp_kses_post($parsed_terms); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                
                <!-- Bottom Area -->
                <div class="page-bottom">
                    <?php echo esc_html($company_name . ' - ' . $company_address . ', ' . $company_city); ?>
                </div>
            </div>
        </body>
        </html>
        <?php
        $html = ob_get_clean();
        
        // Increase memory limit for large PDFs
        ini_set('memory_limit', '256M');
        
        // Setup DomPDF options
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        return $dompdf->output();
    }
	
    /**
     * Parse placeholders in terms and conditions
     *
     * @param string $template Terms and conditions template with placeholders
     * @param array $data Data to replace placeholders
     * @return string Processed terms and conditions
     */
    private function parse_terms_placeholders($template, $data) {
        $replacements = [
            '{{customer_name}}'        => $data['customer_name'] ?? '',
            '{{customer_first_name}}'  => $data['customer_first_name'] ?? '',
            '{{customer_last_name}}'   => $data['customer_last_name'] ?? '',
            '{{quote_id}}'             => $data['quote_id'] ?? '',
            '{{quote_expiry}}'         => $data['quote_expiry'] ?? '',
            '{{company_name}}'         => $data['company_name'] ?? '',
            '{{today}}'                => $data['today'] ?? date_i18n('d/m/Y'),
        ];
    
        return strtr($template, $replacements);
    }	
}