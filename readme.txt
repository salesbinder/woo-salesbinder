=== Woo + SalesBinder ===
Contributors: salesbinder
Tags: inventory management, e-commerce, shopping cart, billing, invoicing, crm
Requires at least: 3.5
Tested up to: 4.7.2
Stable tag: 1.2.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

SalesBinder's official plugin for WooCommerce. Allows you to automatically sync all your SalesBinder data with WooCommerce’s e-commerce plugin.

== Description ==

SalesBinder is our awesome cloud based online inventory management system. WooCommerce is a free eCommerce plugin that allows you to sell anything, beautifully. Built to integrate seamlessly with WordPress, WooCommerce is the world’s favorite eCommerce solution that gives both store owners and developers complete control.

With endless flexibility and access to hundreds of free and premium WordPress extensions, WooCommerce now powers 30% of all online stores -- more than any other platform.

Using our official Woo + SalesBinder plugin, you can integrate all of your inventory data (in real-time) directly into your website and have all your internet sales automatically entered into your SalesBinder account. This plugin will automatically sync your SalesBinder data into the WooCommerce plugin and place your inventory data into the “Products” section. No custom theming required.

* Sync your SalesBinder inventory into WooCommerce’s Product List
* Data synchronization includes, item details, photos, and even custom fields
* Automatically save online website orders directly into SalesBinder as either invoices or estimates
* Save WooCommerce’s customer data into SalesBinder (with built-in existing customer check)

For more information on SalesBinder integrations, please visit: http://www.salesbinder.com/integrations/woocommerce/

== Installation ==

1. Install and activate the WooCommerce plugin.
2. Upload the entire woo-salesbinder folder to your /wp-content/plugins/ directory.
3. Activate the plugin through the 'Plugins' menu in WordPress.
4. You will find a new ‘Woo + SalesBinder’ tab under “WooCommerce” -> “Settings”.
5. Enter your Web Address, API Key, change any options if you like, and Save Changes.

Optional Settings:

* Set your Account Context and Document Context for where you’d like WooCommerce’s completed orders to be saved (ie. Customer and Invoice)
* Set the Sync Interval rate for polling your SalesBinder account looking for inventory changes

== Frequently Asked Questions ==

= Do I need a SalesBinder Account for this plug to work? =

Yes. This plugin connects to your SalesBinder account so you will need to register an account at www.salesbinder.com first.

= Where do I find more information about WooCommerce? =

You can visit their official website found here: http://www.woothemes.com/woocommerce/ or visit their plugin page on WordPress.org here: https://wordpress.org/plugins/woocommerce/

= How do I get my inventory to display on my WordPress site? =

This plugin works seamlessly with WooCommerce’s shopping cart plugin, which works with any theme and attempts to follow existing styles in your theme. Changing your theme isn’t a requirement but there’s plenty of options to customize how it looks by using all of WooCommerce’s great themes and tools.

= Do I need an SSL certificate? =

If you plan to take credit card numbers on your website, you must install and activate an SSL certificate to secure communication between your website visitors and your web server. If you plan to take payments through an offsite payment system, such as PayPal Payments Standard, you do not need an SSL certificate. Even if you don't need one, an SSL certificate can boost your storefront's credibility and does provide protection for other sensitive customer information.

== Screenshots ==

Screenshots are posted online [here](http://www.salesbinder.com/integrations/woocommerce/ "SalesBinder + WooCommerce screenshots")

== Changelog ==

= 1.2.2 =
* Added built-in product duplicate checking to prevent possible duplications caused by overlapping syncing processes

= 1.2.1 =
* Bug fix: Backordered stock that was replenished would sometimes not be updated as back "in stock" in WooCommerce

= 1.2.0 =
* New: Allow/disallow backordering of products (found in Woo + SalesBinder settings tab)

= 1.1.0 =
* Updated plugin to utilize SalesBinder's API v2.0 (faster, smarter syncing of data)
* Bug fix: Inventory item's primary photo will always be displayed first

= 1.0.8 =
* Bug fix: In certain scenarios only primary product image would sync into WooCommerce
* Bug fix: Some WooCommerce sites experienced incorrect tax amounts syncing into SalesBinder from completed online orders

= 1.0.7 =
* WooCommerce 2.6.x compatibility improvements

= 1.0.6 =
* Minor improvements
* New features & options coming soon

= 1.0.5 =
* New: Incremental Syncing allows for more frequent, more efficient data fetching of latest changed SalesBinder data (every 5 minutes)
* New: View how recently your data has been synced with SalesBinder (Woo + SalesBinder settings screen)
* Bug fixes for certain web hosting configuration syncing issues

= 1.0.4 =
* New: Syncing can now resume in case of server timeouts with large inventory lists
* Bug fix: In certain scenarios, images would get duplicated on the server instead of replaced

= 1.0.3 =
* Bug fix related to syncing category names with special characters

= 1.0.2 =
* Bug fixes and performance tuning

= 1.0.1 =
* Initial stable release

== Upgrade Notice ==

Replace the entire “woo-salesbinder” folder in your plugins directory.
