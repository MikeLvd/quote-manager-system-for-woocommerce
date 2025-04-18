=== Plugin Name ===
Plugin Name:       Quote Manager System For WooCommerce
Plugin URI:        https://github.com/MikeLvd/quote-manager-system-for-woocommerce
Description:       A custom WordPress plugin that allows you to create detailed product offers inside the WooCommerce backend. Ideal for retail stores, B2B sales, and client advanced quotations.
Version:           1.0.5
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

= 1.5.0 =
New Feature
*Added feature for additional attachments files to the quote

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