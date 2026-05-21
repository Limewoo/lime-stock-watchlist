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
$lswl_table = $wpdb->prefix . 'lime_watchlist';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS `{$lswl_table}`" );

// Remove plugin options.
delete_option( 'lswl_settings' );
delete_option( 'lswl_db_version' );
