=== Ratio Pricing ===
Contributors: kesso
Tags: woocommerce, pricing, dynamic pricing, artwork, acf, scf, custom fields
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Dynamic pricing for WooCommerce artwork products based on size selections from ACF or SCF fields and print-ratio CPT.

== Description ==

Ratio Pricing calculates product prices dynamically based on the size selected by the customer. The plugin works with Simple WooCommerce products that have artwork_type set to "print" and are linked to a Ratio CPT containing available sizes and their prices.

== Features ==

* Dynamic pricing based on size selection
* Optional texture add-on with configurable percentage
* Server-side price calculation (never trust frontend)
* Price range display on product and archive pages
* Form validation before add to cart
* Configurable ACF / SCF field mappings

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/ratio-pricing` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Ensure WooCommerce and either Advanced Custom Fields (ACF) or Smart Custom Fields (SCF) are installed and activated for pricing to work (the settings page is always available)
4. Go to Ratio Pricing in the admin menu to configure settings

== Requirements ==

* WooCommerce 5.0 or higher
* Advanced Custom Fields (ACF) or Smart Custom Fields (SCF) plugin (at least one required for dynamic pricing)
* print-ratio custom post type (must exist)
* Custom fields (ACF or SCF) configured on products and print-ratio CPT

== Frequently Asked Questions ==

= Does this work with product variations? =

No, this plugin only works with Simple products. Products must be Simple type with artwork_type = "print".

= How does the pricing work? =

The plugin retrieves the base price from the selected size in the Ratio CPT's print_ratios repeater field. If texture is selected, a percentage is added to the base price. All calculations happen server-side.

= Can I customize the field names? =

Yes, the plugin settings page allows you to map custom ACF or SCF field names for all required fields.

== Changelog ==

= 1.0.0 =
* Initial release

