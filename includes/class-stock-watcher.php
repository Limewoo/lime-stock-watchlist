<?php
/**
 * Watches for WooCommerce stock status changes and triggers email notifications.
 *
 * @package Lime_Stock_Watchlist
 */

namespace Lime_Stock_Watchlist;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hooks into WooCommerce stock transitions and fires notifications.
 */
class Stock_Watcher {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'woocommerce_product_set_stock_status', array( $this, 'on_stock_status_change' ), 10, 3 );
	}

	/**
	 * Triggered when a product's stock status changes.
	 *
	 * @param int        $product_id The product ID.
	 * @param string     $stock_status The new stock status.
	 * @param \WC_Product $product The product object.
	 * @return void
	 */
	public function on_stock_status_change( int $product_id, string $stock_status, \WC_Product $product ): void {
		if ( 'instock' !== $stock_status ) {
			return;
		}

		$settings = Plugin::get_settings();

		if ( empty( $settings['notifications_enabled'] ) ) {
			return;
		}

		if ( empty( $settings['notification_email_enabled'] ) ) {
			return;
		}

		$product_enabled = get_post_meta( $product_id, '_lswl_enabled', true );

		if ( 'no' === $product_enabled ) {
			return;
		}

		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			Email::send_notifications( $product_id );
			return;
		}

		$subscribers = Database::get_subscribers( $product_id );

		$queued_ids = array();
		foreach ( $subscribers as $subscriber ) {
			as_enqueue_async_action(
				'lswl_send_notification',
				array( absint( $subscriber->id ), $product_id ),
				'lime-stock-watchlist'
			);
			$queued_ids[] = absint( $subscriber->id );
		}

		if ( ! empty( $queued_ids ) ) {
			Database::mark_notifying( $queued_ids );
		}
	}
}
