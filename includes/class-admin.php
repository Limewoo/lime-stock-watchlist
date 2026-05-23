<?php
/**
 * Admin: WooCommerce submenu page with React SPA.
 *
 * @package Lime_Stock_Watchlist
 */

namespace Lime_Stock_Watchlist;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the WooCommerce submenu and enqueues the React admin bundle.
 */
class Admin {

	/**
	 * Admin page slug.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'lime-stock-watchlist';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( LSWL_FILE ), array( $this, 'add_action_links' ) );
	}

	/**
	 * Add Manage link to the plugin action links.
	 *
	 * @param string[] $links Existing action links.
	 * @return string[]
	 */
	public function add_action_links( array $links ): array {
		$manage_link = '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) . '">' . esc_html__( 'Manage', 'lime-stock-watchlist' ) . '</a>';
		array_unshift( $links, $manage_link );

		return $links;
	}

	/**
	 * Add WooCommerce submenu item.
	 *
	 * @return void
	 */
	public function add_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Lime Stock Watchlist', 'lime-stock-watchlist' ),
			__( 'Stock Watchlist', 'lime-stock-watchlist' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the admin page shell (React mounts here).
	 *
	 * @return void
	 */
	public function render_page(): void {
		echo '<div class="wrap"><div id="lswl-admin-root"></div></div>';
	}

	/**
	 * Enqueue React bundle — only on the plugin admin page.
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue( string $hook ): void {
		if ( 'woocommerce_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		$asset_file = LSWL_PATH . 'build/admin.asset.php';
		$asset      = file_exists( $asset_file )
			? require $asset_file
			: array(
				'dependencies' => array(),
				'version'      => LSWL_VERSION,
			);

		wp_enqueue_editor();

		$deps = array_unique(
			array_merge(
				$asset['dependencies'],
				array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n', 'wp-data', 'wp-date', 'wp-tinymce' )
			)
		);

		wp_enqueue_style( 'wp-components' );

		wp_enqueue_style(
			'lswl-admin',
			LSWL_URL . 'build/admin.css',
			array( 'wp-components' ),
			$asset['version']
		);

		wp_enqueue_script(
			'lswl-admin',
			LSWL_URL . 'build/admin.js',
			$deps,
			$asset['version'],
			true
		);

		wp_set_script_translations( 'lswl-admin', 'lime-stock-watchlist' );

		wp_localize_script(
			'lswl-admin',
			'lswlAdmin',
			array(
				'restUrl'             => esc_url_raw( rest_url( 'lime-stock-watchlist/v1/' ) ),
				'nonce'               => wp_create_nonce( 'wp_rest' ),
				'dateFormat'          => get_option( 'date_format' ),
				'actionSchedulerUrl'  => esc_url( admin_url( 'admin.php?page=wc-status&tab=action-scheduler&s=lswl_send_notification' ) ),
			)
		);
	}
}
