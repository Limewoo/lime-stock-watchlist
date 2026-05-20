# Lime Stock Watchlist

## Repository

GitHub: https://github.com/Limewoo/lime-stock-watchlist  
WP.org: submitted for review (slug: `lime-stock-watchlist`)

## Commands

### JS / Build

```bash
bun install          # install dependencies
bun run build        # production build → build/
bun run start        # watch mode (dev)
bun run lint:js      # ESLint via wp-scripts
bun run lint:style   # stylelint via wp-scripts
```

### PHP Lint

```bash
composer install     # install phpcs + wpcs (first time)
composer lint        # phpcs — check WP coding standards
composer lint-fix    # phpcbf — auto-fix what it can
```

Note: WPCS sniffs are incompatible with PHP_CodeSniffer 4.x — lint command currently errors. Use `php -l` for syntax checking.

## Architecture

### PHP flow

`lime-stock-watchlist.php` → defines constants → `register_activation_hook` → `lime_stock_watchlist_init()` on `plugins_loaded` → instantiates `Plugin` class → `Plugin` wires all other classes.

Also hooks `before_woocommerce_init` (top-level, outside init) to declare HPOS + block cart/checkout compatibility via `FeaturesUtil::declare_compatibility()`.

### Constants

| Constant | Value |
|----------|-------|
| `LSWL_VERSION` | `'1.0.0'` |
| `LSWL_FILE` | `__FILE__` |
| `LSWL_PATH` | `plugin_dir_path( LSWL_FILE )` |
| `LSWL_URL` | `plugin_dir_url( LSWL_FILE )` |

### PHP classes (`includes/`)

| Class | File | Responsibility |
|-------|------|----------------|
| `Plugin` | `class-plugin.php` | Orchestrator — instantiates all classes, wires hooks, handles unsubscribe token on `init` |
| `Database` | `class-database.php` | `dbDelta` table install, all CRUD (`$wpdb->prepare()` everywhere) |
| `Frontend` | `class-frontend.php` | Renders notify form on out-of-stock product pages, enqueues frontend assets (resolves i18n messages from settings) |
| `Admin` | `class-admin.php` | WC submenu "Lime Watchlist" (`lime-stock-watchlist`), enqueues React bundle on plugin page |
| `Product_Settings` | `class-product-settings.php` | WC Product Data tab "Watchlist" — per-product enable/disable |
| `Rest_API` | `class-rest-api.php` | Registers all 6 REST routes; `settings_with_placeholders()` private helper used by both GET and POST settings handlers |
| `Email` | `class-email.php` | `send_to_one()` — back-in-stock per-subscriber; `send_confirmation()` — subscription confirmation; `handle_queued_notification()` — AS callback; `send_notifications()` — sync fallback; `process_shortcodes()` — token replacement |
| `Stock_Watcher` | `class-stock-watcher.php` | `woocommerce_product_set_stock_status` hook → guards on `notifications_enabled` AND `notification_email_enabled` → queues AS actions + calls `Database::mark_notifying()` (sync fallback skips mark_notifying) |

### DB table: `{prefix}lime_watchlist`

| Column | Type | Notes |
|--------|------|-------|
| `id` | `BIGINT UNSIGNED` | Primary key |
| `product_id` | `BIGINT UNSIGNED` | Indexed |
| `email` | `VARCHAR(200)` | Unique per product |
| `name` | `VARCHAR(100)` | Optional, default `''` |
| `date_subscribed` | `DATETIME` | Default `CURRENT_TIMESTAMP` |
| `notified` | `TINYINT(1)` | 0 = pending, 2 = notifying (queued), 1 = notified |
| `unsubscribed` | `TINYINT(1)` | 0 = active, 1 = unsubscribed |

UNIQUE KEY on `(product_id, email)`.

**`notified` state machine:** `0` (pending) → `2` (notifying — AS action queued) → `1` (notified — email sent).

**Re-subscribe rules** (`Database::add_or_resubscribe()`):
- `notified=0 OR notified=2, unsubscribed=0` → `'already_subscribed'` (REST 409) — active or queued
- `notified=1` OR `unsubscribed=1` → allow re-subscribe (reset to `notified=0, unsubscribed=0`)

### Email delivery (Action Scheduler)

On stock change, `Stock_Watcher` checks `notifications_enabled` AND `notification_email_enabled` (both must be true), then:
1. Enqueues one AS async action per subscriber
2. Calls `Database::mark_notifying( $queued_ids )` → sets `notified = 2` so admin sees "Notifying" status immediately

AS action details:
- Hook: `lswl_send_notification( int $subscriber_id, int $product_id )`
- Group: `lime-stock-watchlist`
- Unique: `false` — double-send prevented by `notified === 1` guard in callback
- Callback: `Email::handle_queued_notification()` — guard: `1 === (int)$subscriber->notified` skips (already done); `notified=2` proceeds → sends → `mark_notified()` sets `notified=1`
- Fallback: if `as_enqueue_async_action()` unavailable, falls back to synchronous `Email::send_notifications()` (no intermediate notifying state)

Viewable/retryable in WooCommerce → Status → Action Scheduler.

### Subscription confirmation email

Sent immediately from `Rest_API::handle_subscribe()` after successful insert when `confirmation_email_enabled` is true. Uses `Email::send_confirmation()` → `templates/email-confirmation.php`. No unsubscribe link. No AS queue — fires synchronously on subscribe.

`{subscriber_name}` in confirmation email resolves to first name when available, otherwise `'there'` (never falls back to email address).

### Email shortcodes

`Email::process_shortcodes( string $text, array $map )` replaces tokens in subject/body fields:

| Token | Back-in-stock email | Confirmation email |
|-------|--------------------|--------------------|
| `{site_name}` | ✓ | ✓ |
| `{product_name}` | ✓ | ✓ |
| `{product_url}` | ✓ | — |
| `{subscriber_name}` | ✓ (first name or "there") | ✓ (first name or "there") |
| `{subscriber_email}` | ✓ | ✓ |

Email body fields support basic HTML — sanitized via `wp_kses_post()` (not `sanitize_textarea_field`).

### Settings (`wp_options` key: `lswl_settings`)

| Key | Default | Description |
|-----|---------|-------------|
| `notifications_enabled` | `true` | Master on/off — hides all other settings in UI when false |
| `form_title` | `''` | Frontend form heading (fallback: "Notify me when available") |
| `form_button_label` | `''` | Frontend submit button text (fallback: "Notify me") |
| `show_name_field` | `false` | Show name input on frontend form |
| `name_field_required` | `false` | Make name required |
| `msg_success` | `''` | Success message after subscribe (fallback: default translatable string) |
| `msg_duplicate` | `''` | Already-subscribed message (fallback: default translatable string) |
| `msg_error` | `''` | Generic error message (fallback: default translatable string) |
| `from_name` | `''` | Sender name for all emails (fallback: site name) |
| `from_email` | `''` | Sender address for all emails (fallback: admin email) |
| `confirmation_email_enabled` | `true` | Send confirmation email on subscribe |
| `confirmation_email_subject` | `''` | Confirmation subject (supports shortcodes; fallback: translatable default) |
| `confirmation_email_body` | `''` | Confirmation body (supports shortcodes + HTML; fallback: translatable default) |
| `notification_email_enabled` | `true` | Send back-in-stock emails automatically |
| `email_subject` | `''` | Back-in-stock subject (supports shortcodes; fallback: "{product} is back in stock!") |
| `email_body` | `''` | Back-in-stock body (supports shortcodes + HTML; fallback: template default paragraphs) |

Both GET and POST `/settings` return `_placeholders` — computed real defaults for React input placeholders (via shared `settings_with_placeholders()` method). Never saved to DB.

### REST API

Namespace: `lime-stock-watchlist/v1`

| Method | Route | Auth |
|--------|-------|------|
| `POST` | `/subscribe` | public — 200 new, 409 already active, 503 disabled, 404 no product, 409 in-stock |
| `GET` | `/subscribers` | `manage_woocommerce` |
| `DELETE` | `/subscribers/{id}` | `manage_woocommerce` |
| `DELETE` | `/subscribers` | `manage_woocommerce` (bulk, `ids[]` in body) |
| `GET` | `/settings` | `manage_woocommerce` |
| `POST` | `/settings` | `manage_woocommerce` |

Subscriber rows return `notified` as **integer** (0/2/1), not bool. React uses strict `=== 0/2/1` comparisons.

### Admin UI

WC submenu: **"Lime Watchlist"** — `PAGE_SLUG = 'lime-stock-watchlist'`, hook suffix `woocommerce_page_lime-stock-watchlist`.

Single React SPA rendered in `<div id="lswl-admin-root">`. Two tabs via `@wordpress/components` `TabPanel`:

- **Subscribers** — stats bar (Total / Waiting / Notifying / Notified / Unsubscribed), subscribers grouped into per-product cards with status badges, per-group checkbox, single + bulk delete. Warning notice appears when `stats.notifying > 0` (singular/plural via `sprintf`). Both delete paths show `window.confirm()`. Column order: Name | Email | Status | Date subscribed | action.
- **Settings** — five grouped cards; all but the first hidden when `notifications_enabled` is false:
  1. **Enable Stock Watchlist** — master toggle
  2. **Subscriber Form** — form title, button label, name field toggles, success/duplicate/error messages
  3. **Email Configuration** — shared from name + from email (placeholders = computed site name / admin email)
  4. **Subscription Confirmation Email** — toggle (default on), subject + body textarea (fields hidden when toggle off)
  5. **Back-in-Stock Notification Email** — toggle (default on), subject + body textarea (fields hidden when toggle off)

Settings component tree: `SettingsTab` → `settings/WatchlistEnableCard`, `SubscriberFormCard`, `EmailConfigCard`, `ConfirmationEmailCard`, `NotificationEmailCard`. Each card in its own file under `src/admin/js/components/settings/`. Icons in `settings/icons.js`, generic card wrapper in `settings/SettingsCard.js`.

React entry: `src/admin/js/index.js` → `build/admin.js` + `build/admin.css`. Uses `createRoot` (React 18 API).  
Data layer: `@wordpress/api-fetch` + `wp_rest` nonce. Uses `url:` (not `path:`) in all `apiFetch` calls.  
`CheckboxControl` and `ToggleControl` require `__nextHasNoMarginBottom` prop (deprecation since `@wordpress/components` 6.7).

### Status badges

| Value | Badge | Color | Dot |
|-------|-------|-------|-----|
| `notified=0, unsub=0` | Waiting | amber | `$lswl-waiting-dot` |
| `notified=2, unsub=0` | Notifying | purple (pulsing dot) | `$lswl-notifying-dot` |
| `notified=1, unsub=0` | Notified | blue | `$lswl-notified-dot` |
| `unsubscribed=1` | Unsubscribed | grey | `$lswl-unsub-dot` |

### Frontend form

PHP-rendered via `woocommerce_single_product_summary` (priority 31, after price).  
Only shown when product is out-of-stock AND feature enabled (global + per-product check).  
Template: `templates/frontend-form.php`. Variables: `$show_name` (bool), `$name_required` (bool), `$form_title` (string), `$form_button_label` (string). Empty string = use translatable default.  
Submits via `fetch()` → `POST /wp-json/lime-stock-watchlist/v1/subscribe`.  
i18n strings (success / duplicate / error) resolved from settings in `Frontend::enqueue()` and passed via `lswlFrontend.i18n`.  
On 200 success: heading + form elements removed from DOM, only success message remains. On 409: inline error shown, form stays visible.

### Email templates

**`templates/email-notification.php`** — back-in-stock. Variables: `$product`, `$subscriber`, `$unsubscribe_url`, `$subject`, `$email_body`. If `$email_body` non-empty: renders via `nl2br( wp_kses_post() )`. Else: default greeting + product name + thank-you paragraphs. Always includes Shop Now CTA + unsubscribe footer.

**`templates/email-confirmation.php`** — subscription confirmation. Variables: `$product`, `$subscriber`, `$subject`, `$email_body`. Body always from `$email_body` (shortcodes pre-processed). No CTA, no unsubscribe link.

Both templates pull colors from WC email settings — no hardcoded values:

| PHP var | WC option | Used for |
| ------- | --------- | -------- |
| `$wc_base` | `woocommerce_email_base_color` | Header bg, CTA button bg |
| `$wc_bg` | `woocommerce_email_background_color` | Outer email background |
| `$wc_body_bg` | `woocommerce_email_body_background_color` | Email card background |
| `$wc_text` | `woocommerce_email_text_color` | Body + footer text |
| `$wc_header_text` | `wc_light_or_dark( $wc_base, … )` | Header title + CTA text (auto contrast) |

### Unsubscribe flow

URL: `?lswl_unsub=1&id={id}&token={token}` — handled in `Plugin::handle_unsubscribe()` on `init`.

- Token verified via `wp_hash( $id . $email . NONCE_KEY )`
- If already unsubscribed → redirect to product page with `?lswl_already_unsubscribed=1`
- On success → `Database::mark_unsubscribed($id)` → redirect with `?lswl_unsubscribed=1`
- Falls back to `home_url('/')` if product deleted

Notice via `Frontend::maybe_show_unsubscribe_notice()` on `woocommerce_before_single_product`:
- `?lswl_unsubscribed=1` → green `woocommerce-message`
- `?lswl_already_unsubscribed=1` → blue `woocommerce-info`

### Styles

Entry: `src/admin/scss/index.scss` and `src/frontend/scss/index.scss`.  
Variables in `src/admin/scss/_variables.scss`. BEM under `.lswl-`. Brand accent: `$lswl-lime: #5d9e3f`.

**Admin SCSS** — full design system: brand header, lime tab underline, product-group cards, status badge pills (including `--notifying` with pulsing dot animation), stats bar (5 columns), settings cards.

**Frontend SCSS** — intentionally minimal: inherits theme styles. Only lime `3px` top-border, lime focus ring, lime button colour.

### Build

`@wordpress/scripts` (v32) wraps Webpack. Two named entry points via `webpack.config.js` override.  
Output: `build/admin.js`, `build/admin.css`, `build/admin.asset.php`, `build/frontend.js`, `build/frontend.css`, `build/frontend.asset.php` (+ RTL variants).  
Never edit `build/` manually.

## Git Workflow

- New feature or fix → create new branch from `main` first.
- Commit on feature branch. Merge to `main` only when user explicitly asks.
- Never commit directly to `main`.

## Standards & Conventions

### PHP

- Namespace `Lime_Stock_Watchlist` for all classes.
- Only one global function: `lime_stock_watchlist_init()`.
- Constants prefixed `LSWL_`.
- Every PHP file: `if ( ! defined( 'ABSPATH' ) ) { exit; }` at top.
- No `else` after early return.
- All i18n strings use text domain `lime-stock-watchlist`.
- All DB queries via `$wpdb->prepare()` — no exceptions.
- Nonce verification: `wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'action' )`.
- REST admin routes: `permission_callback` checks `current_user_can( 'manage_woocommerce' )`.

### Sanitization reference

| Input type | Function |
| ---------- | -------- |
| Email | `sanitize_email()` + `is_email()` |
| Name / text | `sanitize_text_field()` |
| Textarea / multiline | `sanitize_textarea_field()` |
| Email body (HTML allowed) | `wp_kses_post()` |
| Integer IDs | `absint()` |
| Array of IDs | `array_map( 'absint', $ids )` |
| Bool settings | `(bool)` cast |

### Escaping reference

| Context | Function |
| ------- | -------- |
| HTML text | `esc_html()` |
| HTML attribute | `esc_attr()` |
| URL | `esc_url()` |
| REST URL in JS | `esc_url_raw()` |
| Email HTML | `wp_kses_post()` |

### JS

- ES6+ only — no CommonJS `require()`.
- JSDoc param/return types on all exported functions.
- All user-facing strings via `@wordpress/i18n`. Text domain: `lime-stock-watchlist`.
- Admin: React + `@wordpress/components`. No custom UI framework.
- `@wordpress/components` `Notice` does not forward `style` prop — use CSS class instead.
