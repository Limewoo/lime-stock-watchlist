<?php
/**
 * Main plugin orchestrator.
 *
 * @package Lime_Stock_Watchlist
 */

namespace Lime_Stock_Watchlist;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bootstraps all plugin components and registers shared hooks.
 */
class Plugin {

	/**
	 * Default settings values.
	 *
	 * @var array<string, mixed>
	 */
	public const DEFAULTS = array(
		'notifications_enabled' => true,
		'show_name_field'       => false,
		'name_field_required'   => false,
		'from_name'             => '',
		'from_email'            => '',
		'email_subject'         => '',
	);

	/**
	 * Wire all classes and register hooks.
	 */
	public function __construct() {
		( new Frontend() )->register();
		( new Admin() )->register();
		( new Product_Settings() )->register();
		( new Rest_API() )->register();
		( new Stock_Watcher() )->register();

		add_action( 'init', array( $this, 'handle_unsubscribe' ) );
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Load plugin text domain.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'lime-stock-watchlist',
			false,
			dirname( plugin_basename( LSWL_FILE ) ) . '/languages'
		);
	}

	/**
	 * Handle unsubscribe requests via query vars.
	 * URL: ?lswl_unsub=1&id={id}&token={token}
	 *
	 * @return void
	 */
	public function handle_unsubscribe(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['lswl_unsub'] ) || empty( $_GET['id'] ) || empty( $_GET['token'] ) ) {
			return;
		}

		$id    = absint( $_GET['id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$token = sanitize_text_field( wp_unslash( $_GET['token'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $id <= 0 ) {
			return;
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, email FROM `{$wpdb->prefix}lime_watchlist` WHERE id = %d LIMIT 1",
				$id
			)
		);

		if ( ! $row ) {
			return;
		}

		$expected = wp_hash( $row->id . $row->email . NONCE_KEY );

		if ( ! hash_equals( $expected, $token ) ) {
			return;
		}

		Database::mark_unsubscribed( $id );

		wp_safe_redirect(
			add_query_arg( 'lswl_unsubscribed', '1', home_url( '/' ) )
		);
		exit;
	}

	/**
	 * Get merged plugin settings (stored values over defaults).
	 *
	 * @return array<string, mixed>
	 */
	public static function get_settings(): array {
		$stored = get_option( 'lswl_settings', array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return array_merge( self::DEFAULTS, $stored );
	}
}
