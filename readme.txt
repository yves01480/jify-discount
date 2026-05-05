=== Jify Discount ===
Contributors: jifycloud
Tags: discount, fees, promotion, woocommerce, marketing
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 2.1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Flexible threshold-based discount engine with scheduling and fee-based calculation for WooCommerce.

== Description ==

Jify Discount allows you to create sophisticated discount rules that apply as negative fees in the cart. Unlike standard coupons, these discounts apply automatically based on cart thresholds and can be scheduled for specific dates.

**Key Features:**

*   **Threshold Rules**: Set discounts based on cart subtotal (e.g., Spend $1000, get $100 off).
*   **Flexible Calculation**: Support for both Fixed Amount ($) and Percentage (%) discounts.
*   **Scheduled Promotions**: Schedule discounts to start and end on specific dates automatically.
*   **Marketing Messages**: Display custom marketing messages directly in the cart totals area to encourage upselling.
*   **Fee-Based Logic**: Discounts are applied as a WooCommerce "Fee" (negative value), ensuring compatibility with tax and shipping calculations.
*   **Smart Sorting**: Custom logic to ensure discounts appear in the correct order relative to taxes in the cart totals.

== Installation ==

1.  Upload the plugin files to the `/wp-content/plugins/jify-discount` directory.
2.  Activate the plugin through the 'Plugins' screen in WordPress.
3.  Go to Product Data > Jify Discount tab to configure rules.

== Frequently Asked Questions ==

= Does this work with coupons? =
Yes, Jify Discount applies as a fee, so it can stack with standard WooCommerce coupons depending on your store's configuration.

= Can I schedule a discount for next month? =
Yes, use the Start Date and End Date fields in the product settings to schedule promotions in advance.

== Changelog ==

= 2.1.0 =
*   Added percentage-based discount logic.
*   Improved fee sorting mechanism.
*   Added date scheduling features.
