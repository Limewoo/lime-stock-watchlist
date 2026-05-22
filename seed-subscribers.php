<?php
// phpcs:disable
/**
 * Seed script — inserts 30 test subscribers across products 18, 19, 20, 32.
 *
 * Usage (WP-CLI):  wp eval-file wp-content/plugins/lime-stock-watchlist/seed-subscribers.php
 * Usage (direct):  php seed-subscribers.php  (from plugin root, after WP bootstrap)
 *
 * Safe to run multiple times — skips rows that already exist (UNIQUE KEY on product_id+email).
 */

if ( ! defined( 'ABSPATH' ) ) {
	// Bootstrap WordPress when run directly.
	$root = dirname( __FILE__, 5 ); // plugin → plugins → wp-content → public
	if ( file_exists( $root . '/wp-load.php' ) ) {
		require_once $root . '/wp-load.php';
	} else {
		echo "Cannot find wp-load.php. Run via WP-CLI instead.\n";
		exit( 1 );
	}
}

global $wpdb;

$table      = $wpdb->prefix . 'lime_watchlist';
$product_ids = [ 19, 18, 20, 32 ];

$first_names = [
	'Alice', 'Bob', 'Carol', 'Dave', 'Eve', 'Frank', 'Grace', 'Hana',
	'Ivan', 'Julia', 'Karl', 'Lena', 'Marco', 'Nina', 'Oscar', 'Priya',
	'Quinn', 'Rosa', 'Sam', 'Tara', 'Uma', 'Vince', 'Wren', 'Xena',
	'Yusuf', 'Zoe', 'Aiden', 'Bella', 'Cody', 'Dana',
];

$last_names = [
	'Smith', 'Jones', 'Williams', 'Brown', 'Taylor', 'Davies', 'Evans',
	'Wilson', 'Thomas', 'Roberts', 'Johnson', 'Walker', 'Wright', 'Thompson',
	'White', 'Hughes', 'Edwards', 'Green', 'Hall', 'Lewis',
];

// notified values: 0 = watching, 1 = notified, 3 = failed.
// Skip notified=2 (notifying) — no AS job would back it up, causing a stuck notice.
// unsubscribed:    0 = active, 1 = unsubscribed.
$statuses = [
	[ 'notified' => 0, 'unsubscribed' => 0 ], // watching
	[ 'notified' => 0, 'unsubscribed' => 0 ], // watching (weighted more)
	[ 'notified' => 0, 'unsubscribed' => 0 ],
	[ 'notified' => 1, 'unsubscribed' => 0 ], // notified
	[ 'notified' => 1, 'unsubscribed' => 0 ],
	[ 'notified' => 3, 'unsubscribed' => 0 ], // failed — use to test resend feature
	[ 'notified' => 1, 'unsubscribed' => 1 ], // unsubscribed
];

$inserted = 0;
$skipped  = 0;
$total    = 35;

for ( $i = 0; $i < $total; $i++ ) {
	$first      = $first_names[ $i % count( $first_names ) ];
	$last       = $last_names[ array_rand( $last_names ) ];
	$email      = strtolower( $first ) . '.' . strtolower( $last ) . '+seed' . $i . '@example.com';
	$name       = ( 0 === $i % 4 ) ? '' : $first . ' ' . $last; // some have no name
	$product_id = $product_ids[ $i % count( $product_ids ) ];
	$status     = $statuses[ $i % count( $statuses ) ];

	// Spread dates over the past 90 days.
	$offset          = wp_rand( 0, 90 * DAY_IN_SECONDS );
	$date_subscribed = gmdate( 'Y-m-d H:i:s', time() - $offset );

	$result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$table,
		[
			'product_id'      => $product_id,
			'email'           => $email,
			'name'            => $name,
			'date_subscribed' => $date_subscribed,
			'notified'        => $status['notified'],
			'unsubscribed'    => $status['unsubscribed'],
		],
		[ '%d', '%s', '%s', '%s', '%d', '%d' ]
	);

	if ( false === $result ) {
		if ( str_contains( $wpdb->last_error, 'Duplicate' ) ) {
			++$skipped;
		} else {
			echo 'DB error on row ' . $i . ': ' . esc_html( $wpdb->last_error ) . "\n";
		}
	} else {
		++$inserted;
	}
}

echo "Done. Inserted: {$inserted}  Skipped (duplicate): {$skipped}\n";
// phpcs:enable
