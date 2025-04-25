=== Plugin Name ===
Plugin Name:       Quote Manager System For WooCommerce
Plugin URI:        https://github.com/MikeLvd/quote-manager-system-for-woocommerce
Description:       A custom WordPress plugin that allows you to create detailed product offers inside the WooCommerce backend. Ideal for retail stores, B2B sales, and client advanced quotations.
Version:           1.8.9
Author:            Mike Lvd
Author URI:        https://goldenbath.gr/
Requires at least: 5.9
Tested up to:      6.8
Requires PHP:      8.0
WC requires at least: 9.0
WC tested up to:   9.8
License:           GPL-2.0+
License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
Text Domain:       quote-manager-system-for-woocommerce
Domain Path:       /languages
Requires Plugins:  woocommerce

A powerful custom WooCommerce plugin to generate detailed product offers for your clients, including internal pricing analysis and advanced price handling.

== Description ==

Offer Manager for WooCommerce is a custom-built solution that allows store owners to create detailed quotations inside the WordPress admin panel. Ideal for retail, wholesale, and B2B operations.

**Key Features:**

- AJAX-powered product search by title or SKU
- Manual product entry with full price control
- VAT toggle option
- Support for both list price and discounts
- Automatic calculation of:
  - Final price (excl. & incl. VAT)
  - Total line value
  - Cost with VAT from _wc_cog_cost
  - Profit margin and markup (%)
- Internal analysis table visible only to admins
- Image selector (from Media or URL)

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to **Offers > Add New** to start creating offers.

== Frequently Asked Questions ==

= Does it send offers to clients? =
Not yet. Currently, the system is internal. PDF export or email is a planned feature.

= Can I change the VAT rate? =
The plugin auto-detects the default WooCommerce tax rate. Per-offer tax toggle is supported.

= Are images stored in the Media Library? =
You can choose from Media Library or insert a remote image URL.

== Screenshots ==

1. Offer creation interface with product rows
2. AJAX search for WooCommerce products
3. Internal pricing analysis table

== Changelog ==
= 1.8.9 =
Bug Fixes & Improvements
• refactored and merged css styles
• refactored and merged js code
• proper file structure
• One more font missing from dompdf library

= 1.8.8 =
Bug Fixes & Improvements
• Created a centralized shortcode [quote_manager] to handle all quote-related views
• Fixed template loading to prevent raw shortcode output in the footer
• Implemented AJAX form submission for better user experience
• Added proper SEO handling for page titles and canonical URLs
• Implemented better error handling and feedback
• Added compatibility with page builders (like WPBakery)
• Improved security with nonce verification and proper data sanitization
• Added CSS fixes to prevent theme conflicts
• Made the quote handling pages more robust against errors

= 1.8.7 =
Compatibility
• Make the cost field [_wc_cog_cost] compatible ready with the Cost of Goods core version of woocommerce [_cogs_total_value] (COGS)

= 1.8.5 =
Email Template Customization Feature
• Added a new field in plugin settings to customize the default email template
• Added ability to save custom email templates in WordPress database
• Implemented secure HTML sanitization for email content
• Added "Reset to Default" button for email templates
• Updated Quote Manager core to use the saved template for emails
• Added JavaScript functionality to reset the email template to default
• Added proper localization for all new text strings
• Added missing fonts from dompdf

= 1.8.2 =
Bug Fixes & Improvements
• Fixed some issues with the css styles on pdf export
• Better security checks and nonces for customer search
• Fixed some translation issues

= 1.8.0 =
New Feature
• Added customer search ability and autofill the customer fields

= 1.7.0 =
Compatibility
• Resolved an issue where the purchase_price field was not correctly retrieving the product cost from the _wc_cog_cost meta key (used by the Cost of Goods for WooCommerce plugin by SkyVerge).

= 1.6.9 =
Bug Fixes & Improvements

• Fixed: When a quote is signed, it now overwrites the original PDF instead of creating a separate file, reducing directory size
• Removed: Eliminated creation of backup PDF files to improve storage efficiency
• Added: Automatic deletion of PDF files when a quote is rejected
• Security: Prevented access to PDF files for rejected quotes
• Reformated the code files for better readability and maintainability

Signature Handling

• Fixed: Customer signatures now properly display in PDFs
• Improved: Better positioning of signature in PDF to prevent overlap with terms section
• Enhanced: Signature section styling for better readability

URL Handling

• Fixed: 404 error when accessing quote-response page after accepting a quote
• Improved: Quote response page now properly handles all status types
• Enhanced: Endpoint registration to ensure proper URL routing

Security Enhancements

• Added: Status checking to prevent download of rejected quotes
• Fixed: Proper cleanup of temporary signature flags
• Improved: PDF access controls based on quote status
• Added direct checks on all php files

Frontend Experience

• Improved: Better messaging for rejected quotes
• Updated: View quote page now hides download buttons for rejected quotes
• Enhanced: More consistent user experience throughout quote workflow

= 1.6.5 =
* Started implementing the quote status feature with automated accept/reject function

= 1.6.0 =
Enhancements
• Redesigned Customer & Shipping Details layout to match WooCommerce admin interface
• Added side-by-side field layouts for better space utilization
• Added field icons for improved visual cues
• Added "Save as New Customer" feature to easily convert quotes to customers
• Restricted country list to only show countries enabled in WooCommerce settings
• Added file type restrictions to only allow JPG, JPEG, PNG, and PDF file uploads
• Implemented file size limit of 2MB per uploaded attachment
• Set maximum attachment limit to 10 files per quote

Security Enhancements
• Added MIME type validation for uploaded files using WordPress's wp_check_filetype()
• Implemented proper security with .htaccess protection for both quotes and attachments directories

Fixes
• Fixed an issue where attachments weren't being deleted from the server when removed
• Fixed the "Attachment not found in quote data" error when removing newly uploaded files before saving
• Resolved directory creation issues during plugin activation
• Fixed shipping phone field not saving when creating a new customer
• Prevented automatic welcome emails when creating customers through the Quote Manager
• Fixed JavaScript issues in the address interaction handlers
• Improved field styling with proper margins and visual hierarchy
• Added proper input validation and error handling for the customer creation process

Code Improvements
• Reorganized activation code to use the dedicated Activator class
• Added a more reliable directory structure with separate folders for quotes and attachments
• Added multiple fallback mechanisms to ensure required directories exist
• Improved error logging for easier troubleshooting
• Moved inline JavaScript to external files for better maintenance
• Added dynamic state/county fields based on selected country
• Implemented better success/error notifications for user actions
• Added CSS classes for better styling control
• Optimized AJAX handlers for country/state selection

Directory Structure
• Changed storage path for quotes from /quote-manager/ to /quote-manager/quotes/
• Created a more organized and consistent file structure

New Features
• Added plugin setting to control whether to delete files on uninstallation
• Implemented a proper uninstall script that respects user preferences
• Added warning message for data deletion during uninstallation

= 1.4.9 =
New Feature

*Added Terms & Conditions metabox for quotes with rich text editor
*Added default Terms & Conditions setting in plugin settings page
*Added support for Terms & Conditions section in PDF output
*Added "Reset to Default Terms" button for quick restoration of default terms

Enhancements

*Terms & Conditions support placeholders for dynamic content (customer name, quote ID, etc.)
*Added formatting capabilities (bold, bullets, etc.) for professional-looking terms
*Improved PDF layout with dedicated Terms & Conditions section
*Added proper HTML sanitization to maintain security while preserving formatting

Technical Improvements

*Added proper script loading on both settings and quote edit pages
*Added proper data localization for JavaScript functionality
*Enhanced TinyMCE configuration for better editing experience
*Added CSS styling for terms in PDF output

= 1.4.5 =
* The whole plugin refactored from the ground
* Renamed the plugin name and directory from: offer-manager-for-woocommerce ------> quote-manager-system-for-woocommerce
* Better price fields handling for matching woocommerce currency settings
* Complete translate files for greek language
* Optimized css file and correct some fields

= 1.4.0 =
* Added Project name field to offers. Now the admin can add a project name to the offer.
* Added Project name colum to offer list
* Added Project name to PDF export

= 1.3.0 =
* Added support for drag and drop product lines for better organization in offers with TableDnD js library
* Added plugin settings page for company information
* Completely redesigned pdf template
* Added predefined due date and custom date picker
* Added dynamic fields to product tables and internal cost and margin
* Added more custom columns on offer page like offer number, date expiry etc
* Other small fixes

= 1.2.0 =
* Added email history for offers
* Added PDF export functionality
* Improved AJAX search performance
* Added support for product cost from _wc_cog_cost meta_key

= 1.1.0 =
* Initial release with full offer builder, internal cost analysis, and dynamic product pricing fields.