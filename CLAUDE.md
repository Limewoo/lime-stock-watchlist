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
| `Frontend` | `class-frontend.php` | Renders notify form on out-of-stock product pages, enqueues frontend assets |
| `Admin` | `class-admin.php` | WC submenu, enqueues React bundle on plugin page |
| `Product_Settings` | `class-product-settings.php` | WC Product Data tab "Watchlist" — per-product enable/disable |
| `Rest_API` | `class-rest-api.php` | Registers all 6 REST routes |
| `Email` | `class-email.php` | Builds + sends `wp_mail()` notifications, generates unsubscribe URL |
| `Stock_Watcher` | `class-stock-watcher.php` | `woocommerce_product_set_stock_status` hook → triggers `Email` |

### DB table: `{prefix}lime_watchlist`

| Column | Type | Notes |
|--------|------|-------|
| `id` | `BIGINT UNSIGNED` | Primary key |
| `product_id` | `BIGINT UNSIGNED` | Indexed |
| `email` | `VARCHAR(200)` | Unique per product |
| `name` | `VARCHAR(100)` | Optional, default `''` |
| `date_subscribed` | `DATETIME` | Default `CURRENT_TIMESTAMP` |
| `notified` | `TINYINT(1)` | 0 = pending, 1 = notified |
| `unsubscribed` | `TINYINT(1)` | 0 = active, 1 = unsubscribed |

UNIQUE KEY on `(product_id, email)`. Re-subscribe (reset `notified=0, unsubscribed=0`) only allowed when `notified=1` OR `unsubscribed=1`. Active subscription (`notified=0, unsubscribed=0`) returns `'already_subscribed'` from `Database::add_or_resubscribe()` → REST 409.

### Settings (`wp_options` key: `lswl_settings`)

| Key | Default | Description |
|-----|---------|-------------|
| `notifications_enabled` | `true` | Global on/off |
| `show_name_field` | `false` | Show name input on frontend form |
| `name_field_required` | `false` | Make name required |
| `from_name` | site name | Notification from name |
| `from_email` | admin email | Notification from address |
| `email_subject` | translatable | Notification subject line |

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

### Admin UI

Single React SPA rendered in `<div id="lswl-admin-root">`. Two tabs via `@wordpress/components` `TabPanel`:
- **Subscribers** — stats bar (total / waiting / notified / unsubscribed), subscribers grouped into per-product cards with status badges, per-group checkbox, single + bulk delete
- **Settings** — three grouped cards (Notifications / Subscriber Form / Email Configuration) using `ToggleControl` / `TextControl`, saved via REST

React entry: `src/admin/js/index.js` → `build/admin.js` + `build/admin.css`. Uses `createRoot` (React 18 API).  
Component tree: `App` → `SubscribersTab` / `SettingsTab`. API wrappers in `src/admin/js/api/index.js`.  
Data layer: `@wordpress/api-fetch` + `wp_rest` nonce. Uses `url:` (not `path:`) in all `apiFetch` calls to bypass root-URL middleware resolution issues.  
`CheckboxControl` requires `__nextHasNoMarginBottom` prop (deprecation since `@wordpress/components` 6.7).

### Frontend form

PHP-rendered via `woocommerce_single_product_summary` (priority 31, after price).  
Only shown when product is out-of-stock AND feature enabled (global + per-product check).  
Template: `templates/frontend-form.php`. Variables: `$show_name` (bool), `$name_required` (bool).  
Submits via `fetch()` → `POST /wp-json/lime-stock-watchlist/v1/subscribe`.  
On 200 success: heading + form elements removed from DOM, only success message remains. On 409: inline error message shown, form stays visible.

### Email template (`templates/email-notification.php`)

Greeter uses first name only: `explode( ' ', trim( $subscriber->name ) )[0]`. Falls back to "Hi there," when no name stored. Name field is off by default (`show_name_field: false`) so most installs will use the fallback unless admin enables it.

### Styles

Entry: `src/admin/scss/index.scss` and `src/frontend/scss/index.scss` (imported inside each JS entry).  
BEM under `.lswl-`. Brand accent: `$lswl-lime: #5d9e3f` (SCSS variable, not a CSS custom property).

**Admin SCSS** — full design system: brand header (dark forest gradient), lime tab underline, product-group cards, status badge pills, stats bar, settings cards.

**Frontend SCSS** — intentionally minimal: inherits all typography and input styles from the active theme. Only asserts lime `3px` top-border accent on the container, lime focus ring on inputs, and lime button colour. No hardcoded fonts, backgrounds, or colours that could clash with store themes.

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
