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
| `Subscriber` | `class-subscriber.php` | Value object for a watchlist row — `from_row()` factory, status helpers (`is_watching/notifying/notified/unsubscribed`), `display_name()` |
| `Plugin` | `class-plugin.php` | Orchestrator — instantiates all classes, wires hooks, handles unsubscribe token on `init` |
| `Database` | `class-database.php` | `dbDelta` table install, all CRUD (`$wpdb->prepare()` everywhere); CRUD methods return `Subscriber` instances; paginated read methods (`get_subscribers_paginated`, `get_products_with_counts`, `get_stats`) return raw arrays for REST layer |
| `Frontend` | `class-frontend.php` | Renders notify form on out-of-stock product pages and optionally on archive pages; enqueues frontend assets (resolves i18n messages from settings); computes CSS custom properties from style settings (`hex_to_rgb()`, `hex_darken()` private helpers) and outputs them via `wp_add_inline_style()`; private `render_form_template()` shared by `render_form()` (single product) and `render_archive_form()` (archive loop) |
| `Admin` | `class-admin.php` | WC submenu "Lime Watchlist" (`lime-stock-watchlist`), enqueues React bundle on plugin page |
| `Product_Settings` | `class-product-settings.php` | WC Product Data tab "Watchlist" — per-product enable/disable |
| `Rest_API` | `class-rest-api.php` | Registers all 7 REST routes; `settings_with_placeholders()` private helper used by both GET and POST settings handlers |
| `Email` | `class-email.php` | `send_to_one()` — back-in-stock per-subscriber; `send_confirmation()` — subscription confirmation; `handle_queued_notification()` — AS callback; `send_notifications()` — sync fallback; `process_shortcodes()` — token replacement |
| `Stock_Watcher` | `class-stock-watcher.php` | Hooks all 4 WC stock hooks (see below) → guards on `notifications_enabled` AND `notification_email_enabled` → queues AS actions + calls `Database::mark_notifying()` (sync fallback skips mark_notifying) |

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

**WooCommerce stock hooks — WC fires separate hooks for variations vs. other products:**

| Hook | Fires for | Trigger |
|------|-----------|---------|
| `woocommerce_variation_set_stock_status` | variations | status change |
| `woocommerce_variation_set_stock` | variations | quantity change |
| `woocommerce_product_set_stock_status` | simple / variable parent | status change |
| `woocommerce_product_set_stock` | simple / variable parent | quantity change |

All four are hooked. When variable parent fires instock, `Stock_Watcher` also iterates child variations and notifies each variation's subscribers (using `get_post_meta($variation_id, '_stock_status', true)` to bypass WC object cache). Double-send is impossible — `get_subscribers()` only returns `notified=0` rows.

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
| `style_accent_color` | `'#5d9e3f'` | Frontend button/accent colour → `--lswl-accent` CSS var |
| `style_btn_text_color` | `'#ffffff'` | Button text colour → `--lswl-btn-text` CSS var |
| `style_btn_radius` | `3` | Button border-radius in px → `--lswl-btn-radius` CSS var |
| `style_btn_padding_v` | `10` | Button vertical padding in px → `--lswl-btn-padding` CSS var |
| `style_btn_padding_h` | `20` | Button horizontal padding in px → `--lswl-btn-padding` CSS var |
| `style_input_border_color` | `'#e0e0e0'` | Input border colour → `--lswl-input-border` CSS var |
| `style_input_radius` | `5` | Input border-radius in px → `--lswl-input-radius` CSS var |
| `style_input_padding_v` | `10` | Input vertical padding in px → `--lswl-input-padding` CSS var |
| `style_input_padding_h` | `14` | Input horizontal padding in px → `--lswl-input-padding` CSS var |
| `style_heading_color` | `''` | Heading colour → `--lswl-heading-color` CSS var (empty = inherit theme) |
| `style_custom_css` | `''` | Arbitrary CSS appended after CSS var block (stripped of tags) |
| `form_display_mode` | `'inline'` | `'inline'` — form rendered directly on page; `'popup'` — trigger button opens modal overlay |
| `popup_trigger_label` | `''` | Popup trigger button text (empty = falls back to form title / "Notify me when available") |
| `show_on_archive` | `false` | Show form on shop/category/search pages for OOS simple products (variable skipped) |

Both GET and POST `/settings` return `_placeholders` — computed real defaults for React input placeholders (via shared `settings_with_placeholders()` method). Never saved to DB.

### REST API

Namespace: `lime-stock-watchlist/v1`

| Method | Route | Auth |
|--------|-------|------|
| `POST` | `/subscribe` | public — 200 new, 409 already active, 503 disabled, 404 no product, 409 in-stock |
| `GET` | `/subscribers` | `manage_woocommerce` |
| `GET` | `/subscribers/stats` | `manage_woocommerce` |
| `DELETE` | `/subscribers/{id}` | `manage_woocommerce` |
| `DELETE` | `/subscribers` | `manage_woocommerce` (bulk, `ids[]` in body) |
| `GET` | `/settings` | `manage_woocommerce` |
| `POST` | `/settings` | `manage_woocommerce` |

**`/subscribers/stats` must be registered BEFORE the `(?P<id>\d+)` route** to prevent regex collision.

**GET `/subscribers` query params:**

| Param | Type | Default | Notes |
|-------|------|---------|-------|
| `view` | string | `users` | `users` or `products` |
| `page` | int | `1` | 1-based |
| `per_page` | int | `20` | |
| `status` | string | `all` | `all`, `watching`, `notifying`, `notified`, `unsubscribed` |
| `search` | string | `''` | email LIKE (users view); product name filter (products view) |
| `product_id` | int | `0` | `0` = all products; `>0` = drill-down for one product |

**Response shapes:**

`view=users` → `{ items: [...], total: int, pages: int }` — each item includes `product_name`, `product_thumbnail`, `product_url` augmented from `wc_get_product()`.

`view=products` → `{ items: [{ product_id, product_name, product_thumbnail, product_url, subscriber_count }], total: int, pages: int }`.

`/subscribers/stats` → `{ total, watching, notifying, notified, unsubscribed }`.

Subscriber rows return `notified` as **integer** (0/2/1), not bool. React uses strict `=== 0/2/1` comparisons.

### Admin UI

WC submenu: **"Stock watchlist"** — `PAGE_SLUG = 'lime-stock-watchlist'`, hook suffix `woocommerce_page_lime-stock-watchlist`.

`render_page()` outputs `<div class="wrap"><div id="lswl-admin-root"></div></div>` — the `.wrap` class is required for standard WP admin margins.

Single React SPA. Three tabs via `@wordpress/components` `TabPanel`:

**Subscribers tab** — TanStack Table v8 + TanStack Query v5. Both packages are **bundled** (not WP externals) — `build/admin.asset.php` does NOT list them.

Layout:
- Stats bar: Total / Watching / Notifying / Notified / Unsubscribed (from `GET /subscribers/stats`)
- `NotifyingNotice` when `stats.notifying > 0`
- Controls row: **By Subscriber** / **By Product** toggle (left) + search input + status select (right, native HTML elements — not WP SearchControl/SelectControl)
- View area: `UserView` or `ProductView` (or `ProductDrillDown` when drilling into a product)

`UserView` columns: checkbox | email | product | status badge | date subscribed | delete icon. Product column hidden when `productId > 0` (drill-down). Single + bulk delete with `window.confirm()`. `pageSize: 20`.

`ProductView` columns: product (thumbnail + linked name) | subscriber count | "View Subscribers" button. No delete, no bulk select.

`ProductDrillDown`: back button → controls row with "Subscribers for: [linked product name]" left + search/status filters right → `UserView` with `productId` set.

**`view` URL param** — `SubscribersTab` reads `?view=` on mount via `getInitialView()` to restore the active toggle (`users`/`products`). `handleViewChange` calls `syncViewToUrl(newView)` via `history.replaceState`. Omits param for `'users'` (default). Unknown values fall back to `'users'`.

**`paged` URL param** — both `UserView` and `ProductView` sync current page to the URL as `?paged=N` (1-based) via `history.replaceState`. On mount, `getInitialPageIndex()` reads `paged` from the URL so page survives a browser refresh. An `isFirstRender` ref prevents the filter-change `useEffect` from overwriting the URL-initialized page on first mount. `SubscribersTab` calls `clearPagedParam()` on view switch (`handleViewChange`), drill-down entry (`handleDrillDown`), and back (`handleBack`) to prevent stale `paged` leaking across views. Module-level helpers `getInitialPageIndex()` and `syncPageToUrl(pageIndex)` are duplicated in each view file.

Component tree:
```
SubscribersTab
├── UserView       src/admin/js/components/subscribers/UserView.js
├── ProductView    src/admin/js/components/subscribers/ProductView.js
├── ProductDrillDown  src/admin/js/components/subscribers/ProductDrillDown.js
├── TablePagination   src/admin/js/components/subscribers/TablePagination.js
└── StatusBadge    src/admin/js/components/subscribers/StatusBadge.js
```

**Settings tab** — five grouped cards; all but the first hidden when `notifications_enabled` is false:

1. **Enable Stock Watchlist** — master toggle
2. **Subscriber Form** — display mode (`SelectControl`: inline/popup), archive page toggle, form title, button label, name field toggles, success/duplicate/error messages
3. **Email Configuration** — shared from name + from email (placeholders = computed site name / admin email)
4. **Subscription Confirmation Email** — toggle (default on), subject + body textarea (fields hidden when toggle off)
5. **Back-in-Stock Notification Email** — toggle (default on), subject + body textarea (fields hidden when toggle off)

Settings component tree: `SettingsTab` → `settings/WatchlistEnableCard`, `SubscriberFormCard`, `EmailConfigCard`, `ConfirmationEmailCard`, `NotificationEmailCard`. Each card in its own file under `src/admin/js/components/settings/`. Icons in `settings/icons.js`, generic card wrapper in `settings/SettingsCard.js`.

**Style tab** — frontend form appearance; four grouped cards. Shares the same settings load/save API as SettingsTab (`getSettings()` / `saveSettings()`):

1. **Button** — accent colour (`ColorField`), text colour (`ColorField`), border-radius + vertical/horizontal padding (`RangeControl`)
2. **Inputs** — border colour (`ColorField`), border-radius + vertical/horizontal padding (`RangeControl`)
3. **Text** — heading colour (`ColorField`, `allowEmpty` — reset = inherit theme)
4. **Custom CSS** — plain `<textarea>` with dark code-editor styling; appended verbatim after the CSS var block

Style component tree:
```
StyleTab                   src/admin/js/components/StyleTab.js
├── ButtonStyleCard        src/admin/js/components/settings/ButtonStyleCard.js
├── InputStyleCard         src/admin/js/components/settings/InputStyleCard.js
├── TextStyleCard          src/admin/js/components/settings/TextStyleCard.js
└── CustomCssCard          src/admin/js/components/settings/CustomCssCard.js
```

`ColorField` (`src/admin/js/components/settings/ColorField.js`) — Gutenberg-native colour picker using `Dropdown` + `ColorPicker` + `ColorIndicator`. Props: `label`, `value`, `onChange`, `defaultValue`, `allowEmpty`. Reset button appears when `value !== resetTarget`. Handles both modern WP (hex string) and legacy (object with `.hex`) `ColorPicker.onChange` API.

React entry: `src/admin/js/index.js` → `build/admin.js` + `build/admin.css`. Uses `createRoot` (React 18 API). Wrapped with `QueryClientProvider` (singleton `QueryClient` at module level, `staleTime: 30_000, retry: 1`).

**Save state architecture** — `App.js` owns `saving`/`saved` state and a `saveHandlerRef`. `SettingsTab` and `StyleTab` receive `registerSave`, `setSaving`, `setSaved` as props. Each tab registers its `handleSave` (stable `useCallback` — reads latest settings via a `settingsRef`) on mount and clears it on unmount. `App.js` renders a `SaveBar` component in the sticky page header (Settings and Style tabs only); clicking it calls `saveHandlerRef.current()`. `SaveBar` (`src/admin/js/components/SaveBar.js`) is a shared component — props: `onSave`, `saving`, `saved`, `className`. The header instance gets class `lswl-settings__save-bar--header` (zero padding/margin; button gets `height: 37px; padding-inline: 16px`).

**Page header** — `position: sticky; top: 32px` (below WP admin toolbar); `max-width: 800px`; contains the page title (left) and `SaveBar` (right, only on Settings/Style tabs).

**`tab` URL param** — `App.js` reads `?tab=` on mount via `getInitialTab()` and passes it as `initialTabName` to `TabPanel`. `onSelect` calls `syncTabToUrl(tabName)` via `history.replaceState` — omits the param when tab is `'subscribers'` (default) to keep URLs clean. Recognised tab names: `subscribers`, `settings`, `style`; unknown values fall back to `'subscribers'`.

Data layer: `@wordpress/api-fetch` + `wp_rest` nonce. Uses `url:` (not `path:`) in all `apiFetch` calls.  
`CheckboxControl` and `ToggleControl` require `__nextHasNoMarginBottom` prop (deprecation since `@wordpress/components` 6.7).

`lswlAdmin` JS object includes `restUrl`, `nonce`, and `dateFormat` (`get_option('date_format')`). `UserView` uses `@wordpress/date` `dateI18n( dateFormat, dateStr )` to format the Date Subscribed column — respects the site's date format from Settings → General. `wp-date` is listed in script dependencies (auto-detected by webpack from the import and present in `build/admin.asset.php`).

### Status badges

| Value | Badge | Color | Dot |
|-------|-------|-------|-----|
| `notified=0, unsub=0` | Watching | grey (`#e5e5e5`) | `$lswl-waiting-dot` |
| `notified=2, unsub=0` | Notifying | green (`#c6e1c6`, pulsing dot) | `$lswl-notifying-dot` |
| `notified=1, unsub=0` | Notified | blue | `$lswl-notified-dot` |
| `unsubscribed=1` | Unsubscribed | grey | `$lswl-unsub-dot` |

Each badge has a `data-tooltip` attribute with a concise description shown on hover via CSS `::after` pseudo-element (no JS). Tooltip text lives in `StatusBadge.js` and is i18n-wrapped.

### Frontend form

**Single product page:** rendered via `woocommerce_single_product_summary` (priority 31).  
**Archive pages:** rendered via `woocommerce_after_shop_loop_item` (priority 11) when `show_on_archive` is true; simple OOS products only — variable products skipped.

Template: `templates/frontend-form.php`. Variables: `$show_name` (bool), `$name_required` (bool), `$form_title` (string), `$form_button_label` (string), `$is_hidden` (bool), `$display_mode` (string), `$popup_trigger_label` (string), `$is_archive` (bool), `$product_id` (int). Empty string = use translatable default.  
Submits via `fetch()` → `POST /wp-json/lime-stock-watchlist/v1/subscribe`.  
i18n strings (success / duplicate / error) resolved from settings in `Frontend::enqueue()` and passed via `lswlFrontend.i18n`.

**Display modes:**

`inline` (default) — form rendered directly in the page. Wrapper has class `.lswl-notify-form` and `data-product-id` attribute.

`popup` — a trigger button (`.lswl-notify-form--popup`) sits on the page; clicking it opens a modal overlay (`div.lswl-notify-form__overlay#lswl-modal-{product_id}`). Trigger button text = `popup_trigger_label` setting, falling back to form title. On 200 success: form hidden, success message shown, overlay stays open. Overlay persists success state until page reload — re-opening shows the success message. Variable product events show/hide the trigger wrapper (same logic as inline wrapper). Overlay close: X button, backdrop click, or Escape key. CSS vars scoped to both `.lswl-notify-form` and `.lswl-notify-form__overlay` so popup form is also themed.

**Inline — simple products:** only rendered when OOS. On 200 success: heading + form removed from DOM.

**Inline — variable products:** always rendered with `hidden` attribute (`$is_hidden = true`). JS reveals on OOS variation select. On 200 success: heading + form hidden (not removed). `subscribedVariations` Set (session-scoped) tracks subscribed variation IDs.

**Archive forms:** all simple products — same inline/popup behavior as single product page, no variable product logic. Archive popup wrapper gets additional class `lswl-notify-form--archive`; trigger button uses `width: auto` (inherits theme width) instead of full-width.

`lswlFrontend` JS object includes:
- `restUrl`, `nonce`, `productId` (parent ID; `0` on archive pages), `isVariable` (bool; always `false` on archive), `displayMode` (`'inline'`|`'popup'`), `i18n`

JS uses `wrapper.dataset.productId` for product ID (reliable on both single and archive). `lswlFrontend.productId` used only to detect the single-product-page form for variable product events (`pid === Number(parentProductId)` guard).

**Variable product JS flow (inline and popup):**
- Listens to WC jQuery events `found_variation` + `reset_data` on `.variations_form`
- `found_variation` + `!variation.is_in_stock` → set `currentProductId = variation.variation_id`, show wrapper
- `found_variation` + in-stock, or `reset_data` → hide wrapper (close popup if open)
- Submit uses `currentProductId` (variation ID) as `product_id` in REST body

**CSS note:** `.lswl-notify-form`, `.lswl-notify-form__form`, `.lswl-notify-form__heading` all have explicit `display` set — `[hidden]` selectors with `!important` are required to override them. `.lswl-notify-form__overlay[hidden]` also uses `display: none !important`.

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

**Admin SCSS** — full design system: light page header with lime bar accent, lime tab underline, stats bar (5 columns), TanStack Table styles (thead caps-label, zebra stripe, lime hover), number-based pagination, filter controls (native inputs), settings cards, status badge pills (including `--notifying` with pulsing dot animation). See "Limewoo Admin UI Design System" section for full token reference.

**Frontend SCSS** — intentionally minimal, but all colours and spacing driven by CSS custom properties so they work consistently across themes. `Frontend::enqueue()` outputs a `<style>` block via `wp_add_inline_style()` with vars: `--lswl-accent`, `--lswl-accent-rgb`, `--lswl-accent-dark`, `--lswl-accent-darker`, `--lswl-btn-text`, `--lswl-btn-radius`, `--lswl-btn-padding`, `--lswl-input-border`, `--lswl-input-radius`, `--lswl-input-padding`, `--lswl-heading-color` (only when non-empty). SCSS uses `var(--lswl-accent, #{$lswl-lime})` pattern for graceful fallback. Input/button sizing and colour properties use `!important` to override theme stylesheets at same/higher specificity.

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
| Hex colour | `sanitize_hex_color()` — returns `null` for invalid input; use `?: '#default'` fallback |
| Custom CSS (no tags) | `wp_strip_all_tags()` |

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
- For filter controls (search, select), use native `<input type="search">` and `<select>` — NOT `SearchControl`/`SelectControl`. WP wrappers add DOM layers that make consistent height/border impossible via CSS alone.
- TanStack Table v8 (`@tanstack/react-table`) and TanStack Query v5 (`@tanstack/react-query`) are bundled — import from the packages directly, do not add them to WP script dependencies.
- Style settings use Gutenberg components: `Dropdown` + `ColorPicker` + `ColorIndicator` for colour fields; `RangeControl` for numeric values. Wrap each `RangeControl` in a `.lswl-range-wrap` div to apply the lime accent override (see Design System section).

---

## Limewoo Admin UI Design System

> **Portable section.** Copy this entire block into any future Limewoo plugin's `CLAUDE.md`. It defines the shared visual language so all admin pages look like one product family.

### Guiding principles

- Match WordPress admin norms (`.wrap` container, WP typography scale) — don't fight them.
- Use native HTML `<input>`/`<select>` instead of WP `SearchControl`/`SelectControl` for filter controls — WP wrappers make consistent height impossible.
- No full-width overrides. Let WP's `.wrap` class provide standard page margins.
- All colours are WP-standard greys (`#dcdcde`, `#f6f7f7`, `#1d2327`, `#646970`) plus the brand accent. Never use green-tinted greys for borders or backgrounds.

### Brand tokens

| Token | Value | Use |
|-------|-------|-----|
| `$lswl-lime` | `#5d9e3f` | Primary accent — active states, buttons, focus rings |
| `$lswl-lime-dark` | `#4a8030` | Button hover bg |
| `$lswl-lime-darker` | `#3a6626` | Text links, icon fills |
| `$lswl-lime-light` | `#ecf7e4` | Badge/icon background tint |
| `$lswl-lime-mid` | `#c5e6a8` | Subtle borders on tinted surfaces |

### Neutral tokens (WP-standard)

| Value | Use |
|-------|-----|
| `#dcdcde` | All borders — cards, inputs, table, pagination, dividers |
| `#f6f7f7` | Surface 2 — card headers, table `thead`, input background, pagination bg |
| `#f0f0f1` | Row dividers inside tables and toggle lists |
| `#1d2327` | Primary text |
| `#646970` | Secondary text, help text, column labels |
| `#fff` | Card / table body background |

### Shape & shadow

| Property | Value |
|----------|-------|
| Card / table border-radius | `6px` |
| Input / button border-radius | `3px` |
| Icon container border-radius | `6px` |
| Card box-shadow | `0 1px 3px rgba(0,0,0,0.07), 0 0 0 1px rgba(0,0,0,0.04)` |
| Input inset shadow | `inset 0 1px 2px rgba(0,0,0,0.04)` |
| Transition | `160ms cubic-bezier(0.25,0.46,0.45,0.94)` on border-color, box-shadow, background |

### Focus ring (all interactive elements)

```scss
border-color: $lswl-lime;
box-shadow: 0 0 0 2px rgba(93, 158, 63, 0.2);
outline: none;
```

### Page layout

PHP `render_page()` must output `<div class="wrap"><div id="plugin-root"></div></div>` — the `.wrap` class provides standard WP admin margins and max-width.

```scss
.plugin-admin {
    margin-top: 24px;  // matches WP admin spacing
}
```

### Page header

```scss
.plugin-admin__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
    padding-bottom: 18px;
    border-bottom: 1px solid #dcdcde;
}

.plugin-admin__title {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0;
    font-size: 1.375rem;
    font-weight: 600;
    color: #1d2327;

    &::before {                   // 4px lime accent bar
        content: '';
        display: block;
        width: 4px;
        height: 1.3em;
        background: $lswl-lime;
        border-radius: 2px;
        flex-shrink: 0;
    }
}
```

### Tab panel (WP TabPanel override)

```scss
.plugin-admin .components-tab-panel__tabs {
    padding: 0;
    border-bottom: 1px solid #dcdcde;
    margin-bottom: 20px;

    button.components-tab-panel__tabs-item {
        border: none;
        border-bottom: 2px solid transparent;
        border-radius: 0;
        margin-bottom: -1px;
        padding: 0 18px;
        height: 40px;
        font-size: 13px;
        font-weight: 500;
        color: #646970;
        background: none;
        box-shadow: none;

        &:hover:not(.is-active) { color: #1d2327; background: none; }
        &.is-active { color: #1d2327; border-bottom-color: $lswl-lime; font-weight: 600; }
    }
}

.plugin-admin .components-tab-panel__tab-content { padding: 0; }
```

### Filter controls row (native HTML)

Use native `<input type="search">` and `<select>` — never WP `SearchControl`/`SelectControl`.

```scss
// Shared base — apply to both
.plugin-filter-search,
.plugin-filter-select {
    height: 32px;
    border: 1px solid #dcdcde;
    border-radius: 3px;
    padding: 0 8px;
    font-size: 13px;
    background: #f6f7f7;
    color: #1d2327;
    box-shadow: inset 0 1px 2px rgba(0,0,0,0.04);
    transition: border-color 160ms, box-shadow 160ms;
    outline: none;
    box-sizing: border-box;

    &:focus {
        border-color: $lswl-lime;
        box-shadow: 0 0 0 2px rgba(93, 158, 63, 0.2);
    }
}

.plugin-filter-search { width: 200px; flex-shrink: 0; }
.plugin-filter-select { min-width: 150px; flex-shrink: 0; cursor: pointer; }

// Layout: toggle buttons left, filters right
.plugin-controls-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 14px;

    &__filters { display: flex; align-items: center; gap: 8px; margin-left: auto; flex-shrink: 0; }
}
```

### Data table

```scss
.plugin-table-wrap {
    position: relative;
    background: #fff;
    border: 1px solid #dcdcde;
    border-radius: 6px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.07), 0 0 0 1px rgba(0,0,0,0.04);

    &--fetching { pointer-events: none; opacity: 0.6; transition: opacity 0.15s; }
}

.plugin-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    margin: 0;
    border: none;

    thead tr { background: #f6f7f7; }

    thead th {
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.07em;
        color: #646970;
        padding: 14px;
        border-bottom: 1px solid #dcdcde;
        text-align: left;
        white-space: nowrap;
    }

    th, td {
        padding: 12px 14px;
        border-bottom: 1px solid #f0f0f1;
        vertical-align: middle;
    }

    tbody {
        tr:last-child td { border-bottom: none; }

        tr {
            transition: background 160ms cubic-bezier(0.25,0.46,0.45,0.94);
            &:nth-child(odd) td  { background: #f6f7f7; }
            &:nth-child(even) td { background: #fff; }
            &:hover td           { background: #f5fbf2; }  // lime tint on hover
        }
    }
}
```

### Pagination (number-based, no per-page select)

Layout: total count left, numbered buttons right. Use a delta=2 range-with-dots algorithm.

```scss
.plugin-pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    border-top: 1px solid #dcdcde;
    background: #f6f7f7;

    &__total { font-size: 12px; color: #646970; }
    &__right  { display: flex; align-items: center; gap: 4px; }

    &__btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 30px;
        height: 30px;
        padding: 0 6px;
        font-size: 12px;
        color: #1d2327;
        background: #fff;
        border: 1px solid #dcdcde;
        border-radius: 3px;
        cursor: pointer;
        transition: background 0.15s, border-color 0.15s, color 0.15s;

        &:hover:not(:disabled) { background: $lswl-lime-light; border-color: $lswl-lime; color: $lswl-lime; }
        &.is-current           { background: $lswl-lime; border-color: $lswl-lime; color: #fff; cursor: default; }
        &:disabled:not(.is-current) { opacity: 0.4; cursor: default; }
    }

    &__dots { display: inline-flex; align-items: center; justify-content: center; min-width: 30px; height: 30px; font-size: 12px; color: #646970; user-select: none; }
}
```

Default page size: **20**. No per-page selector.

### Settings cards

```scss
.plugin-settings-card {
    background: #fff;
    border: 1px solid #dcdcde;
    border-radius: 6px;
    margin-bottom: 16px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.07), 0 0 0 1px rgba(0,0,0,0.04);

    &__header {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 13px 20px;
        border-bottom: 1px solid #dcdcde;
        background: #f6f7f7;
    }

    &__icon {
        width: 28px; height: 28px;
        background: $lswl-lime-light;
        border-radius: 6px;
        display: flex; align-items: center; justify-content: center;
        color: $lswl-lime-darker;
        flex-shrink: 0;
    }

    &__title { font-size: 13px; font-weight: 600; color: #1d2327; margin: 0; }

    &__body { padding: 20px 24px; }
}
```

WP component overrides inside `.plugin-settings-card__body`:

```scss
// Labels & help
.components-base-control__label { font-size: 13px; font-weight: 500; color: #1d2327; margin-bottom: 5px; }
.components-base-control__help  { font-size: 12px; color: #646970; margin-top: 5px; }

// Text inputs
.components-text-control__input {
    height: 36px; padding: 0 10px;
    border: 1px solid #dcdcde; border-radius: 3px;
    background: #f6f7f7; color: #1d2327; font-size: 13px;
    box-shadow: inset 0 1px 2px rgba(0,0,0,0.04);
    transition: border-color 160ms, box-shadow 160ms;
    outline: none; width: 100%; box-sizing: border-box;
    &:focus { border-color: $lswl-lime; box-shadow: 0 0 0 2px rgba(93,158,63,0.2); }
}

// Textareas
.components-textarea-control__input {
    padding: 9px 10px;
    border: 1px solid #dcdcde; border-radius: 3px;
    background: #f6f7f7; color: #1d2327; font-size: 13px;
    box-shadow: inset 0 1px 2px rgba(0,0,0,0.04);
    resize: vertical; min-height: 96px; width: 100%;
    &:focus { border-color: $lswl-lime; box-shadow: 0 0 0 2px rgba(93,158,63,0.2); }
}

// Toggle rows
.components-toggle-control {
    padding: 14px 0;
    border-bottom: 1px solid #f0f0f1;
    margin-bottom: 0 !important;
    &:last-child  { border-bottom: none; padding-bottom: 0; }
    &:first-child { padding-top: 0; }
}

// Toggle track colour
.components-form-toggle__track                              { background: #c8cace; }
.components-form-toggle.is-checked .components-form-toggle__track { background: $lswl-lime; }
```

### ColorField component (Gutenberg colour picker)

Use `Dropdown` + `ColorPicker` + `ColorIndicator` from `@wordpress/components` — not a plain `<input type="color">`.

```jsx
<Dropdown
    popoverProps={ { placement: 'bottom-end' } }
    renderToggle={ ( { isOpen, onToggle } ) => (
        <Button onClick={ onToggle } aria-expanded={ isOpen }
            className={ `lswl-color-field__trigger${ isOpen ? ' is-open' : '' }` }>
            <ColorIndicator colorValue={ value } />
            <span className="lswl-color-field__hex">{ value }</span>
        </Button>
    ) }
    renderContent={ () => (
        <ColorPicker
            color={ value }
            onChange={ ( color ) => {
                const hex = typeof color === 'string' ? color : color?.hex;
                if ( hex ) onChange( hex );
            } }
            enableAlpha={ false }
            copyFormat="hex"
        />
    ) }
/>
```

`onChange` must handle both modern WP (hex string) and legacy (object with `.hex`) API.

Reset button: show when `value !== resetTarget` (where `resetTarget = allowEmpty ? '' : defaultValue`). Reset calls `onChange( resetTarget )`.

```scss
.lswl-color-field {
    padding: 10px 0;
    border-bottom: 1px solid #f0f0f1;
    &:last-child { border-bottom: none; }

    &__row { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
    &__label { font-size: 13px; font-weight: 500; color: #1d2327; }
    &__controls { display: flex; align-items: center; gap: 4px; }

    &__trigger {
        display: flex; align-items: center; gap: 6px;
        height: 28px; padding: 0 8px;
        border: 1px solid #dcdcde; border-radius: 3px;
        background: #f6f7f7; font-size: 12px; font-family: monospace;
        cursor: pointer; color: #1d2327;
        &.is-open { border-color: $lswl-lime; box-shadow: 0 0 0 2px rgba(93,158,63,0.2); }
    }
}
```

### RangeControl lime accent override

WP `RangeControl` reads `--wp-admin-theme-color` internally for the slider fill and thumb. Override at the wrapper scope; also reset `-webkit-appearance` on the thumb for WebKit:

```scss
.lswl-range-wrap {
    padding: 8px 0 0;
    border-bottom: 1px solid #f0f0f1;
    --wp-admin-theme-color: #{$lswl-lime};
    --wp-admin-theme-color--rgb: 93, 158, 63;

    &--last,
    &:last-child { border-bottom: none; }

    .components-range-control { margin-bottom: 0; padding-bottom: 12px; }
    .components-range-control__wrapper { margin-top: 4px; }

    .components-range-control__slider {
        accent-color: $lswl-lime !important;

        &::-webkit-slider-thumb {
            -webkit-appearance: none !important;
            appearance: none !important;
            width: 14px; height: 14px; border-radius: 50%;
            background-color: $lswl-lime !important;
            border: 2px solid $lswl-lime-dark !important;
            cursor: pointer;
            box-shadow: 0 1px 3px rgba(0,0,0,0.25);
        }

        &::-moz-range-thumb {
            background-color: $lswl-lime !important;
            border-color: $lswl-lime-dark !important;
        }
    }

    .components-range-control__number:focus {
        border-color: $lswl-lime !important;
        box-shadow: 0 0 0 2px rgba(93,158,63,0.2) !important;
        outline: none !important;
    }
}
```

Always add `__nextHasNoMarginBottom` prop to `RangeControl` (deprecation since `@wordpress/components` 6.7).

### Custom CSS textarea

```scss
.lswl-custom-css__textarea {
    width: 100%;
    min-height: 140px;
    padding: 12px;
    font-family: 'Courier New', Courier, monospace;
    font-size: 12px;
    line-height: 1.6;
    background: #1e2126;
    color: #abb2bf;
    border: 1px solid #3e4451;
    border-radius: 4px;
    resize: vertical;
    box-sizing: border-box;

    &:focus { border-color: $lswl-lime; outline: none; box-shadow: 0 0 0 2px rgba(93,158,63,0.2); }
    &::placeholder { color: #5c6370; }
}
```

### Primary button (WP `is-primary` override)

```scss
.plugin-admin .components-button.is-primary {
    background: $lswl-lime;
    border-color: $lswl-lime-dark;
    color: #fff;
    box-shadow: none;
    font-weight: 500;
    border-radius: 6px;

    &:hover:not(:disabled) {
        background: $lswl-lime-dark;
        border-color: $lswl-lime-darker;
        color: #fff;
        box-shadow: 0 2px 8px rgba(93,158,63,0.26);
    }

    &:focus:not(:disabled) { box-shadow: 0 0 0 2px #fff, 0 0 0 4px $lswl-lime; }
    &:disabled { opacity: 0.45; }
}
```

### SCSS file structure

```
src/admin/scss/
├── _variables.scss   ← brand + neutral tokens, shadows, radii
└── index.scss        ← all component styles, no sub-partials needed
```

`_variables.scss` must be imported via `@use 'variables' as *;` at the top of `index.scss`.
