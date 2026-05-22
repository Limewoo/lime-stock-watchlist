<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Lime_Stock_Watchlist
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Drop the watchlist table.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'lime_watchlist' ) );

// Remove plugin options.
delete_option( 'lswl_settings' );
delete_option( 'lswl_db_version' );
