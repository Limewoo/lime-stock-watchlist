# Lime Stock Watchlist for WooCommerce

Let customers subscribe to back-in-stock email notifications for out-of-stock WooCommerce products.

> More free WooCommerce plugins at [limewoo.com](https://limewoo.com).

## Features

- Subscribe form on out-of-stock single product pages — inline or popup display mode
- Optional: show subscribe forms on shop / category / search archive pages
- Full variable product support — per-variation subscriptions and notifications
- Optional name field (configurable; can be required)
- Subscription confirmation email sent immediately on sign-up
- Automatic back-in-stock email notifications via WooCommerce Action Scheduler
- Unsubscribe link in every notification email
- Backorder subscribe option
- HPOS and block cart/checkout compatible

## Requirements

- WordPress 6.5+
- WooCommerce 8.0+
- PHP 8.0+

## Development

```bash
bun install          # install JS dependencies
bun run build        # production build → build/
bun run start        # watch mode (dev)
bun run lint:js      # ESLint
bun run lint:style   # stylelint
bun run zip          # production zip → lime-stock-watchlist.zip
```

```bash
composer install     # install phpcs + wpcs
composer lint        # check WP coding standards
composer lint-fix    # auto-fix where possible
```

## Architecture

See [CLAUDE.md](CLAUDE.md) for full class map, DB schema, REST API, settings reference, and build details.

## License

GPL-2.0-or-later — see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).
