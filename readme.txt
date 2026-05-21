=== Lime Stock Watchlist for WooCommerce ===
Contributors:      limewoo
Tags:              woocommerce, stock, watchlist, back in stock, notification
Requires at least: 6.5
Tested up to:      7.0
Requires PHP:      7.4
Stable tag:        1.0.0
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Let customers subscribe to back-in-stock email notifications for out-of-stock WooCommerce products.

== Description ==

Lime Stock Watchlist replaces the "Add to Cart" button on out-of-stock product pages with a simple "Notify me when available" form. When you restock a product, all subscribed customers receive an automatic email notification.

**Features**

* Email capture form on out-of-stock single product pages
* Optional name field (admin configurable)
* Subscribers stored in a dedicated database table
* Admin page under WooCommerce showing all subscribers grouped by product
* Single and bulk subscriber delete
* Automatic email notification when stock status changes to "in stock"
* Unsubscribe link in every notification email
* Per-product enable/disable override
* React-powered admin interface with Gutenberg components
* No third-party libraries

== Installation ==

1. Upload the `lime-stock-watchlist` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **WooCommerce > Watchlist** to view subscribers and configure settings.

== Frequently Asked Questions ==

= Does this work with variable products? =

Currently, notifications are triggered at the parent product level. Variable product support is planned for a future release.

= Where are subscriber emails stored? =

Subscriber data is stored in a dedicated `{prefix}lime_watchlist` table in your WordPress database. Emails are never sent to third parties.

= How does the unsubscribe link work? =

Each notification email contains a unique unsubscribe link. Clicking it marks the subscriber as unsubscribed — no login required.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
