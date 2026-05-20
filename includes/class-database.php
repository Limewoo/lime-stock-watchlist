<?php
/**
 * Database handler — table install and all CRUD operations.
 *
 * @package Lime_Stock_Watchlist
 */

namespace Lime_Stock_Watchlist;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the {prefix}lime_watchlist custom table.
 */
class Database {

	/**
	 * Full table name including prefix.
	 *
	 * @var string
	 */
	private static string $table;

	/**
	 * Return the full table name (with prefix).
	 *
	 * @return string
	 */
	public static function table(): string {
		global $wpdb;
		if ( empty( self::$table ) ) {
			self::$table = $wpdb->prefix . 'lime_watchlist';
		}
		return self::$table;
	}

	/**
	 * Create or upgrade the watchlist table. Called on activation.
	 *
	 * @return void
	 */
	public static function install(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table           = self::table();

		$sql = "CREATE TABLE {$table} (
			id              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
			product_id      BIGINT UNSIGNED  NOT NULL,
			email           VARCHAR(200)     NOT NULL,
			name            VARCHAR(100)     NOT NULL DEFAULT '',
			date_subscribed DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
			notified        TINYINT(1)       NOT NULL DEFAULT 0,
			unsubscribed    TINYINT(1)       NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY product_id (product_id),
			UNIQUE KEY product_email (product_id, email)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'lswl_db_version', LSWL_VERSION );
	}

	/**
	 * Add a subscriber, or reset notified/unsubscribed if already exists.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $email      Subscriber email.
	 * @param string $name       Subscriber name (optional).
	 * @return string 'added' | 'already_subscribed' | 'error'
	 */
	public static function add_or_resubscribe( int $product_id, string $email, string $name = '' ): string {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT notified, unsubscribed FROM `{$wpdb->prefix}lime_watchlist`
				WHERE product_id = %d AND email = %s",
				$product_id,
				$email
			)
		);

		if ( $existing && ! (int) $existing->unsubscribed ) {
			$notified_val = (int) $existing->notified;
			// Active (pending=0) or queued for sending (notifying=2) — both count as already subscribed.
			if ( 0 === $notified_val || 2 === $notified_val ) {
				return 'already_subscribed';
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO `{$wpdb->prefix}lime_watchlist`
					(product_id, email, name, date_subscribed, notified, unsubscribed)
				VALUES
					(%d, %s, %s, NOW(), 0, 0)
				ON DUPLICATE KEY UPDATE
					name             = VALUES(name),
					date_subscribed  = NOW(),
					notified         = 0,
					unsubscribed     = 0",
				$product_id,
				$email,
				$name
			)
		);

		return false !== $result ? 'added' : 'error';
	}

	/**
	 * Get all active (not notified, not unsubscribed) subscribers for a product.
	 *
	 * @param int $product_id Product ID.
	 * @return array<int, object>
	 */
	public static function get_subscribers( int $product_id ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, email, name, date_subscribed
				FROM `{$wpdb->prefix}lime_watchlist`
				WHERE product_id = %d
				  AND notified    = 0
				  AND unsubscribed = 0",
				$product_id
			)
		);
	}

	/**
	 * Get a single subscriber row by ID.
	 *
	 * @param int $id Subscriber ID.
	 * @return object|null
	 */
	public static function get_subscriber_by_id( int $id ): ?object {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, product_id, email, name, date_subscribed, notified, unsubscribed
				FROM `{$wpdb->prefix}lime_watchlist`
				WHERE id = %d",
				$id
			)
		) ?: null;
	}

	/**
	 * Get all subscribers grouped by product ID, for the admin table.
	 *
	 * @return array<int, array<int, object>>
	 */
	public static function get_all_grouped(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			"SELECT id, product_id, email, name, date_subscribed, notified, unsubscribed
			FROM `{$wpdb->prefix}lime_watchlist`
			ORDER BY product_id ASC, date_subscribed DESC"
		);

		$grouped = array();
		foreach ( $rows as $row ) {
			$grouped[ (int) $row->product_id ][] = $row;
		}

		return $grouped;
	}

	/**
	 * Mark a list of subscriber IDs as notified.
	 *
	 * @param int[] $ids Subscriber IDs.
	 * @return bool
	 */
	/**
	 * Mark subscribers as notifying (notified = 2 — queued in Action Scheduler, email in flight).
	 *
	 * @param int[] $ids Subscriber IDs.
	 * @return bool
	 */
	public static function mark_notifying( array $ids ): bool {
		global $wpdb;

		if ( empty( $ids ) ) {
			return false;
		}

		$ids          = array_map( 'absint', $ids );
		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$result = $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"UPDATE `{$wpdb->prefix}lime_watchlist` SET notified = 2 WHERE id IN ({$placeholders})",
				...$ids
			)
		);

		return false !== $result;
	}

	/**
	 * Mark subscribers as notified (email sent successfully).
	 *
	 * @param int[] $ids Subscriber IDs.
	 * @return bool
	 */
	public static function mark_notified( array $ids ): bool {
		global $wpdb;

		if ( empty( $ids ) ) {
			return false;
		}

		$ids         = array_map( 'absint', $ids );
		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$result = $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"UPDATE `{$wpdb->prefix}lime_watchlist` SET notified = 1 WHERE id IN ({$placeholders})",
				...$ids
			)
		);

		return false !== $result;
	}

	/**
	 * Mark a single subscriber as unsubscribed.
	 *
	 * @param int $id Subscriber ID.
	 * @return bool
	 */
	public static function mark_unsubscribed( int $id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->update(
			self::table(),
			array( 'unsubscribed' => 1 ),
			array( 'id' => $id ),
			array( '%d' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete subscriber rows by ID.
	 *
	 * @param int[] $ids Subscriber IDs.
	 * @return bool
	 */
	public static function delete_subscribers( array $ids ): bool {
		global $wpdb;

		if ( empty( $ids ) ) {
			return false;
		}

		$ids          = array_map( 'absint', $ids );
		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$result = $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"DELETE FROM `{$wpdb->prefix}lime_watchlist` WHERE id IN ({$placeholders})",
				...$ids
			)
		);

		return false !== $result;
	}
}
