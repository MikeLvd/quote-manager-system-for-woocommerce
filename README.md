# 🧾 Quote Manager System For WooCommerce

A powerful custom WooCommerce plugin to generate detailed product offers for your clients, including internal pricing analysis and advanced price handling.

---

## ✨ Features

- 🔍 **AJAX Product Search** by title or SKU
- ✍️ **Manual Product Entry** with full price controls
- 🧾 **Dynamic Pricing Fields**:
- 📄 **Send Quote to customer via email with predefined email message**:
    - List Price (Excl. VAT)
    - Discount (%)
    - Final Price (Excl. VAT / Incl. VAT)
    - Total Row Calculation
- 📥 **VAT toggle option** (include/exclude in quote)
- 📸 **Image from Media Library or URL**
- 📄 **Internal Information Panel** with automatic calculation of:
    - Cost (with or without VAT)
    - Markup (%)
    - Margin (%)
    - Profit summary per item and total
- 📤 Save quotes in admin with all quote data
- 📄 Ready for PDF/print output

---

## 🚀 Installation

1. Download or clone this repository.
2. Upload the plugin folder to your `/wp-content/plugins/` directory.
3. Activate it from the **Plugins > Installed Plugins** section in WordPress admin.
4. Go to **Customer Quotes > Add New** to create your first quote.

---

## 🛠️ Requirements

- WordPress 5.6+
- WooCommerce 8.0+
- PHP 7.4+

---

## 🧪 Development Notes

- The plugin is fully extensible and built following WordPress and WooCommerce standards.
- Data is saved as meta on a custom post type called `customer_quote`.
- Uses `wc_get_price_including_tax()` and product meta `_wc_cog_cost` for accurate profit metrics.

---

## 📌 Roadmap / To-do
- [ ] Add metabox for predefined quote terms and conditions with edit ability
- [ ] Add quote version mechanism for tracking quote changes like products removed/added
- [ ] Add an quote status mechanism (e.g., draft, sent, accepted, rejected) with ability for customer to accept/reject via custom link and signature

---

## 🧑‍💻 Author

Developed by [Mike Lvd]

---

