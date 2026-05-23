=== Lime Stock Watchlist for WooCommerce ===
Contributors:      limewoo, thenahidul
Tags:              woocommerce, stock, watchlist, back in stock, notification
Requires at least: 6.5
Tested up to:      7.0
Requires PHP:      8.0
Stable tag:        1.0.0
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Let customers subscribe to back-in-stock email notifications for out-of-stock WooCommerce products.

== Description ==

**Lime Stock Watchlist** lets customers subscribe to back-in-stock email notifications directly on your WooCommerce product pages. When you restock a product, subscribers are notified automatically via Action Scheduler — no third-party service required.

> More free WooCommerce plugins at [limewoo.com](https://limewoo.com).

**Core features**

* Subscribe form on out-of-stock single product pages — inline or popup display mode
* Optional: show subscribe forms on shop / category / search archive pages
* Full variable product support — per-variation subscriptions and notifications
* Optional name field (configurable; can be required)
* Subscription confirmation email sent immediately on sign-up
* Automatic back-in-stock email notification via WooCommerce Action Scheduler
* Unsubscribe link in every notification email
* Backorder subscribe option — accept subscriptions on backorder products

**Admin**

* Subscribers tab: view by subscriber or by product, with drill-down into individual products
* Stats bar: Total / Watching / Notifying / Notified / Unsubscribed / Failed
* Failed notification state with one-click resend from the admin
* Single and bulk subscriber delete
* Per-product enable/disable override (WooCommerce Product Data tab)

**Settings & customisation**

* Master on/off toggle
* Customise form title, button label, success/duplicate/error messages
* Configure sender name and address for all outgoing emails
* Customise confirmation and back-in-stock email subject, body, and footer
* Email shortcodes: `{product_name}`, `{product_url}`, `{subscriber_name}`, `{subscriber_email}`, `{site_name}`
* Full style control: button colour, text colour, border-radius, padding; input border colour, radius, padding; heading colour; success and error message colours
* Style preview updates live in the React admin UI

**Technical**

* HPOS (High-Performance Order Storage) compatible
* Block cart and block checkout compatible
* Subscriber data stored in a dedicated `{prefix}lime_watchlist` database table — no external service
* React-powered admin interface (Gutenberg components + TanStack Table)
* Requires WooCommerce 8.0+

== Installation ==

1. Upload the `lime-stock-watchlist` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **WooCommerce > Stock watchlist** to view subscribers and configure settings.

== Frequently Asked Questions ==

= Does this work with variable products? =

Yes. Subscribers are tracked per variation. When a variation comes back in stock only the subscribers for that specific variation are notified.

= Where are subscriber emails stored? =

In a dedicated `{prefix}lime_watchlist` table in your WordPress database. Emails are never sent to third parties.

= How does the unsubscribe link work? =

Each notification email contains a unique signed unsubscribe link. Clicking it marks the subscriber as unsubscribed — no login required.

= What is the popup display mode? =

Instead of rendering the form inline on the page, a trigger button is shown. Clicking it opens a modal overlay containing the form. Both modes are fully themed via the Style settings.

= What happens if a notification email fails to send? =

Action Scheduler marks the action as failed and the subscriber status changes to "Failed" in the admin. You can resend failed notifications individually from the Subscribers tab.

= Can customers re-subscribe after being notified? =

Yes. Once notified (or after unsubscribing), the form reappears and a customer can subscribe again.

= Does it work with backorder products? =

Optionally. Enable **Allow subscriptions on backorder** in Settings → Subscriber Form to show the form and accept subscriptions on products with a backorder stock status.

== Development ==

The plugin's JavaScript and CSS are compiled from source. Full source code is on GitHub:

https://github.com/Limewoo/lime-stock-watchlist

To build from source:

1. Clone: `git clone https://github.com/Limewoo/lime-stock-watchlist.git`
2. Install dependencies: `bun install` (or `npm install`)
3. Production build: `bun run build`
4. Watch mode: `bun run start`

Source files live in `src/`. Compiled output goes to `build/`. Do not edit `build/` directly.

== Screenshots ==

1. Subscriber list — view and manage all watchlist sign-ups with status, product, and date
2. Product view — see subscriber counts grouped by product with drill-down to individual sign-ups
3. Settings — configure the notify form, email sender, confirmation and back-in-stock email content
4. Style — customise button colours, input appearance, and success/error message styles
5. Inline form on a single product page — displayed when the product is out of stock
6. Popup form on a single product page — a trigger button opens an overlay with the notify form
7. Popup form on an archive/shop page — displayed inline in the product grid for out-of-stock items

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
