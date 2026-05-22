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
				'SELECT notified, unsubscribed FROM %i
				WHERE product_id = %d AND email = %s',
				self::table(),
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i
					(product_id, email, name, date_subscribed, notified, unsubscribed)
				VALUES
					(%d, %s, %s, NOW(), 0, 0)
				ON DUPLICATE KEY UPDATE
					name             = VALUES(name),
					date_subscribed  = NOW(),
					notified         = 0,
					unsubscribed     = 0',
				self::table(),
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
				'SELECT id, product_id, email, name, date_subscribed, notified, unsubscribed
				FROM %i
				WHERE product_id  = %d
				  AND ( notified = 0 OR notified = 3 )
				  AND unsubscribed = 0',
				self::table(),
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
				'SELECT id, product_id, email, name, date_subscribed, notified, unsubscribed
				FROM %i
				WHERE id = %d',
				self::table(),
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
			$wpdb->prepare(
				'SELECT id, product_id, email, name, date_subscribed, notified, unsubscribed
				FROM %i
				ORDER BY product_id ASC, date_subscribed DESC',
				self::table()
			)
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT
					COUNT(*) AS total,
					SUM(notified = 0 AND unsubscribed = 0) AS watching,
					SUM(notified = 2 AND unsubscribed = 0) AS notifying,
					SUM(notified = 1 AND unsubscribed = 0) AS notified_count,
					SUM(unsubscribed = 1) AS unsubscribed_count,
					SUM(notified = 3 AND unsubscribed = 0) AS failed_count
				FROM %i',
				self::table()
			)
		);

		return array(
			'total'        => (int) ( $row->total ?? 0 ),
			'watching'     => (int) ( $row->watching ?? 0 ),
			'notifying'    => (int) ( $row->notifying ?? 0 ),
			'notified'     => (int) ( $row->notified_count ?? 0 ),
			'unsubscribed' => (int) ( $row->unsubscribed_count ?? 0 ),
			'failed'       => (int) ( $row->failed_count ?? 0 ),
		);
	}

	/**
	 * Get a paginated, filtered list of subscribers.
	 *
	 * @param array $args {
	 *     Query arguments.
	 *
	 *     @type int    $page       Page number (1-based). Default 1.
	 *     @type int    $per_page   Results per page (1–100). Default 25.
	 *     @type string $status     all|watching|notifying|notified|unsubscribed. Default 'all'.
	 *     @type string $search     Email LIKE filter. Default ''.
	 *     @type int    $product_id Limit to one product (0 = all). Default 0.
	 *     @type string $orderby    date_subscribed|email. Default 'date_subscribed'.
	 *     @type string $order      ASC|DESC. Default 'DESC'.
	 * }
	 * @return array{ items: array, total: int, pages: int }
	 */
	public static function get_subscribers_paginated( array $args ): array {
		global $wpdb;

		$page       = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page   = max( 1, min( 100, (int) ( $args['per_page'] ?? 25 ) ) );
		$status     = $args['status'] ?? 'all';
		$search     = $args['search'] ?? '';
		$product_id = (int) ( $args['product_id'] ?? 0 );
		$orderby    = in_array( $args['orderby'] ?? '', array( 'date_subscribed', 'email' ), true ) ? $args['orderby'] : 'date_subscribed';
		$order      = 'ASC' === strtoupper( $args['order'] ?? 'DESC' ) ? 'ASC' : 'DESC';
		$offset     = ( $page - 1 ) * $per_page;

		$status_map  = array(
			'watching'     => 1,
			'notifying'    => 2,
			'notified'     => 3,
			'unsubscribed' => 4,
			'failed'       => 5,
		);
		$status_int  = $status_map[ $status ] ?? 0;
		$search_like = '' !== $search ? '%' . $wpdb->esc_like( $search ) . '%' : '';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i
				WHERE ( %d = 0 OR ( notified = 0 AND unsubscribed = 0 AND %d = 1 ) OR ( notified = 2 AND unsubscribed = 0 AND %d = 2 ) OR ( notified = 1 AND unsubscribed = 0 AND %d = 3 ) OR ( unsubscribed = 1 AND %d = 4 ) OR ( notified = 3 AND unsubscribed = 0 AND %d = 5 ) )
				AND ( %s = \'\' OR email LIKE %s )
				AND ( %d = 0 OR product_id = %d )',
				self::table(),
				$status_int,
				$status_int,
				$status_int,
				$status_int,
				$status_int,
				$status_int,
				$search_like,
				$search_like,
				$product_id,
				$product_id
			)
		);

		$select_sql_args = array(
			self::table(),
			$status_int,
			$status_int,
			$status_int,
			$status_int,
			$status_int,
			$status_int,
			$search_like,
			$search_like,
			$product_id,
			$product_id,
			$orderby,
			$per_page,
			$offset,
		);

		if ( 'ASC' === $order ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- spread passes 14 args matching placeholders
					'SELECT id, product_id, email, name, date_subscribed, notified, unsubscribed FROM %i
					WHERE ( %d = 0 OR ( notified = 0 AND unsubscribed = 0 AND %d = 1 ) OR ( notified = 2 AND unsubscribed = 0 AND %d = 2 ) OR ( notified = 1 AND unsubscribed = 0 AND %d = 3 ) OR ( unsubscribed = 1 AND %d = 4 ) OR ( notified = 3 AND unsubscribed = 0 AND %d = 5 ) )
					AND ( %s = \'\' OR email LIKE %s )
					AND ( %d = 0 OR product_id = %d )
					ORDER BY %i ASC LIMIT %d OFFSET %d',
					...$select_sql_args
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- spread passes 14 args matching placeholders
					'SELECT id, product_id, email, name, date_subscribed, notified, unsubscribed FROM %i
					WHERE ( %d = 0 OR ( notified = 0 AND unsubscribed = 0 AND %d = 1 ) OR ( notified = 2 AND unsubscribed = 0 AND %d = 2 ) OR ( notified = 1 AND unsubscribed = 0 AND %d = 3 ) OR ( unsubscribed = 1 AND %d = 4 ) OR ( notified = 3 AND unsubscribed = 0 AND %d = 5 ) )
					AND ( %s = \'\' OR email LIKE %s )
					AND ( %d = 0 OR product_id = %d )
					ORDER BY %i DESC LIMIT %d OFFSET %d',
					...$select_sql_args
				)
			);
		}

		return array(
			'items' => $rows ?? array(),
			'total' => $total,
			'pages' => $total > 0 ? (int) ceil( $total / $per_page ) : 0,
		);
	}

	/**
	 * Get products with subscriber counts, for the product-based admin view.
	 *
	 * @param array $args {
	 *     Query arguments.
	 *
	 *     @type int    $page     Page number (1-based). Default 1.
	 *     @type int    $per_page Results per page (1–100). Default 25.
	 *     @type string $search   Product name filter. Default ''.
	 * }
	 * @return array{ items: array, total: int, pages: int }
	 */
	public static function get_products_with_counts( array $args ): array {
		global $wpdb;

		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = max( 1, min( 100, (int) ( $args['per_page'] ?? 25 ) ) );
		$search   = $args['search'] ?? '';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT product_id, COUNT(*) AS subscriber_count
				FROM %i
				GROUP BY product_id
				ORDER BY subscriber_count DESC',
				self::table()
			)
		);

		if ( empty( $rows ) ) {
			return array(
				'items' => array(),
				'total' => 0,
				'pages' => 0,
			);
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$result = $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $placeholders expands to "%d, %d, ..." from absint-sanitized IDs
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

		$ids          = array_map( 'absint', $ids );
		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$result = $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $placeholders expands to "%d, %d, ..." from absint-sanitized IDs
				"UPDATE `{$wpdb->prefix}lime_watchlist` SET notified = 1 WHERE id IN ({$placeholders})",
				...$ids
			)
		);

		return false !== $result;
	}

	/**
	 * Mark subscribers as failed (email delivery failed, Action Scheduler gave up).
	 *
	 * @param int[] $ids Subscriber IDs.
	 * @return bool
	 */
	public static function mark_failed( array $ids ): bool {
		global $wpdb;

		if ( empty( $ids ) ) {
			return false;
		}

		$ids          = array_map( 'absint', $ids );
		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$result = $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $placeholders expands to "%d, %d, ..." from absint-sanitized IDs
				"UPDATE `{$wpdb->prefix}lime_watchlist` SET notified = 3 WHERE id IN ({$placeholders})",
				...$ids
			)
		);

		return false !== $result;
	}

	/**
	 * Reset subscribers back to watching state (notified = 0).
	 * Used when a product goes OOS before Action Scheduler fires.
	 *
	 * @param int[] $ids Subscriber IDs.
	 * @return bool
	 */
	public static function mark_watching( array $ids ): bool {
		global $wpdb;

		if ( empty( $ids ) ) {
			return false;
		}

		$ids          = array_map( 'absint', $ids );
		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$result = $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $placeholders expands to "%d, %d, ..." from absint-sanitized IDs
				"UPDATE `{$wpdb->prefix}lime_watchlist` SET notified = 0 WHERE id IN ({$placeholders})",
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$result = $wpdb->query(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- spread passes %i (table) + %d×N (ids)
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $placeholders expands to "%d, %d, ..." from absint-sanitized IDs
				"DELETE FROM %i WHERE id IN ({$placeholders})",
				self::table(),
				...$ids
			)
		);

		return false !== $result;
	}
}
