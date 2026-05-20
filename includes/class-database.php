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
	 * @return Subscriber[]
	 */
	public static function get_subscribers( int $product_id ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, product_id, email, name, date_subscribed, notified, unsubscribed
				FROM `{$wpdb->prefix}lime_watchlist`
				WHERE product_id = %d
				  AND notified    = 0
				  AND unsubscribed = 0",
				$product_id
			)
		);

		return array_map( array( Subscriber::class, 'from_row' ), $rows );
	}

	/**
	 * Get a single subscriber row by ID.
	 *
	 * @param int $id Subscriber ID.
	 * @return Subscriber|null
	 */
	public static function get_subscriber_by_id( int $id ): ?Subscriber {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, product_id, email, name, date_subscribed, notified, unsubscribed
				FROM `{$wpdb->prefix}lime_watchlist`
				WHERE id = %d",
				$id
			)
		);

		return $row ? Subscriber::from_row( $row ) : null;
	}

	/**
	 * Get all subscribers grouped by product ID, for the admin table.
	 *
	 * @return array<int, Subscriber[]>
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
			$grouped[ (int) $row->product_id ][] = Subscriber::from_row( $row );
		}

		return $grouped;
	}

	/**
	 * Get aggregate subscriber counts per status, for the admin stats bar.
	 *
	 * @return array{ total: int, watching: int, notifying: int, notified: int, unsubscribed: int }
	 */
	public static function get_stats(): array {
		global $wpdb;
		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			"SELECT
				COUNT(*) AS total,
				SUM(notified = 0 AND unsubscribed = 0) AS watching,
				SUM(notified = 2 AND unsubscribed = 0) AS notifying,
				SUM(notified = 1 AND unsubscribed = 0) AS notified_count,
				SUM(unsubscribed = 1) AS unsubscribed_count
			FROM `{$table}`"
		);

		return array(
			'total'        => (int) ( $row->total ?? 0 ),
			'watching'     => (int) ( $row->watching ?? 0 ),
			'notifying'    => (int) ( $row->notifying ?? 0 ),
			'notified'     => (int) ( $row->notified_count ?? 0 ),
			'unsubscribed' => (int) ( $row->unsubscribed_count ?? 0 ),
		);
	}

	/**
	 * Get a paginated, filtered list of subscribers.
	 *
	 * @param array{
	 *   page: int,
	 *   per_page: int,
	 *   status: string,
	 *   search: string,
	 *   product_id: int,
	 *   orderby: string,
	 *   order: string,
	 * } $args Query arguments.
	 * @return array{ items: array, total: int, pages: int }
	 */
	public static function get_subscribers_paginated( array $args ): array {
		global $wpdb;
		$table = self::table();

		$page       = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page   = max( 1, min( 100, (int) ( $args['per_page'] ?? 25 ) ) );
		$status     = $args['status'] ?? 'all';
		$search     = $args['search'] ?? '';
		$product_id = (int) ( $args['product_id'] ?? 0 );
		$orderby    = in_array( $args['orderby'] ?? '', array( 'date_subscribed', 'email' ), true ) ? $args['orderby'] : 'date_subscribed';
		$order      = 'ASC' === strtoupper( $args['order'] ?? 'DESC' ) ? 'ASC' : 'DESC';
		$offset     = ( $page - 1 ) * $per_page;

		$where  = array( '1=1' );
		$values = array();

		switch ( $status ) {
			case 'watching':
				$where[] = 'notified = 0 AND unsubscribed = 0';
				break;
			case 'notifying':
				$where[] = 'notified = 2 AND unsubscribed = 0';
				break;
			case 'notified':
				$where[] = 'notified = 1 AND unsubscribed = 0';
				break;
			case 'unsubscribed':
				$where[] = 'unsubscribed = 1';
				break;
		}

		if ( '' !== $search ) {
			$where[]  = 'email LIKE %s';
			$values[] = '%' . $wpdb->esc_like( $search ) . '%';
		}

		if ( $product_id > 0 ) {
			$where[]  = 'product_id = %d';
			$values[] = $product_id;
		}

		$where_sql = implode( ' AND ', $where );
		$count_sql = "SELECT COUNT(*) FROM `{$table}` WHERE {$where_sql}";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$total = (int) $wpdb->get_var( empty( $values ) ? $count_sql : $wpdb->prepare( $count_sql, ...$values ) );

		$select_sql  = "SELECT id, product_id, email, name, date_subscribed, notified, unsubscribed FROM `{$table}` WHERE {$where_sql} ORDER BY `{$orderby}` {$order} LIMIT %d OFFSET %d";
		$select_args = array_merge( $values, array( $per_page, $offset ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$rows = $wpdb->get_results( $wpdb->prepare( $select_sql, ...$select_args ) );

		return array(
			'items' => $rows ?? array(),
			'total' => $total,
			'pages' => $total > 0 ? (int) ceil( $total / $per_page ) : 0,
		);
	}

	/**
	 * Get products with subscriber counts, for the product-based admin view.
	 *
	 * @param array{ page: int, per_page: int, search: string } $args Query arguments.
	 * @return array{ items: array, total: int, pages: int }
	 */
	public static function get_products_with_counts( array $args ): array {
		global $wpdb;
		$table = self::table();

		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = max( 1, min( 100, (int) ( $args['per_page'] ?? 25 ) ) );
		$search   = $args['search'] ?? '';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			"SELECT product_id, COUNT(*) AS subscriber_count
			FROM `{$table}`
			GROUP BY product_id
			ORDER BY subscriber_count DESC"
		);

		if ( empty( $rows ) ) {
			return array( 'items' => array(), 'total' => 0, 'pages' => 0 );
		}

		$items = array();

		foreach ( $rows as $row ) {
			$pid     = (int) $row->product_id;
			$product = wc_get_product( $pid );
			$name    = $product
				? $product->get_name()
				: sprintf(
					/* translators: %d: product ID */
					__( 'Deleted product #%d', 'lime-stock-watchlist' ),
					$pid
				);

			if ( '' !== $search && false === stripos( $name, $search ) ) {
				continue;
			}

			$items[] = array(
				'product_id'        => $pid,
				'product_name'      => esc_html( $name ),
				'product_thumbnail' => $product ? ( get_the_post_thumbnail_url( $pid, 'thumbnail' ) ?: '' ) : '',
				'product_url'       => $product ? esc_url( get_permalink( $pid ) ) : '',
				'subscriber_count'  => (int) $row->subscriber_count,
			);
		}

		$total = count( $items );
		$slice = array_slice( $items, ( $page - 1 ) * $per_page, $per_page );

		return array(
			'items' => $slice,
			'total' => $total,
			'pages' => $total > 0 ? (int) ceil( $total / $per_page ) : 0,
		);
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
