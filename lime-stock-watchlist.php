<?php
/**
 * Plugin Name:       Lime Stock Watchlist for WooCommerce
 * Plugin URI:        https://github.com/Limewoo/lime-stock-watchlist
 * Description:       Let customers subscribe to back-in-stock notifications for out-of-stock WooCommerce products.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Limewoo
 * Author URI:        https://github.com/Limewoo
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       lime-stock-watchlist
 * Domain Path:       /languages
 * Requires Plugins:  woocommerce
 *
 * @package Lime_Stock_Watchlist
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LSWL_VERSION', '1.0.0' );
define( 'LSWL_FILE', __FILE__ );
define( 'LSWL_PATH', plugin_dir_path( LSWL_FILE ) );
define( 'LSWL_URL', plugin_dir_url( LSWL_FILE ) );
define( 'LSWL_MIN_WC_VERSION', '8.0.0' );

/**
 * Declare compatibility with WooCommerce features.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', LSWL_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', LSWL_FILE, true );
		}
	}
);

/**
 * Activation hook — install DB table.
 */
register_activation_hook(
	LSWL_FILE,
	function () {
		require_once LSWL_PATH . 'includes/class-database.php';
		Lime_Stock_Watchlist\Database::install();
	}
);

/**
 * Bootstrap the plugin after all plugins are loaded.
 */
function lime_stock_watchlist_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-error"><p>';
				echo esc_html__( 'Lime Stock Watchlist requires WooCommerce to be installed and active.', 'lime-stock-watchlist' );
				echo '</p></div>';
			}
		);
		return;
	}

	if ( version_compare( WC_VERSION, LSWL_MIN_WC_VERSION, '<' ) ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-error"><p>';
				printf(
					/* translators: %s: minimum WooCommerce version */
					esc_html__( 'Lime Stock Watchlist requires WooCommerce %s or higher.', 'lime-stock-watchlist' ),
					esc_html( LSWL_MIN_WC_VERSION )
				);
				echo '</p></div>';
			}
		);
		return;
	}

	require_once LSWL_PATH . 'includes/class-database.php';
	require_once LSWL_PATH . 'includes/class-email.php';
	require_once LSWL_PATH . 'includes/class-frontend.php';
	require_once LSWL_PATH . 'includes/class-admin.php';
	require_once LSWL_PATH . 'includes/class-product-settings.php';
	require_once LSWL_PATH . 'includes/class-rest-api.php';
	require_once LSWL_PATH . 'includes/class-stock-watcher.php';
	require_once LSWL_PATH . 'includes/class-plugin.php';

	new Lime_Stock_Watchlist\Plugin();
}
add_action( 'plugins_loaded', 'lime_stock_watchlist_init' );
