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
 *
 * WooCommerce fires separate hooks for variations vs. simple/parent products:
 *   - woocommerce_variation_set_stock_status  — variation status changes
 *   - woocommerce_variation_set_stock         — variation quantity changes
 *   - woocommerce_product_set_stock_status    — simple/grouped/variable-parent status changes
 *   - woocommerce_product_set_stock           — simple/grouped quantity changes
 *
 * All four are hooked so notifications fire regardless of how WC updates stock.
 */
class Stock_Watcher {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		// Variation-specific hooks (WC never fires the generic hooks for variations).
		add_action( 'woocommerce_variation_set_stock_status', array( $this, 'on_stock_status_change' ), 10, 3 );
		add_action( 'woocommerce_variation_set_stock', array( $this, 'on_stock_quantity_change' ), 10, 1 );

		// Simple product and variable-parent hooks.
		add_action( 'woocommerce_product_set_stock_status', array( $this, 'on_stock_status_change' ), 10, 3 );
		add_action( 'woocommerce_product_set_stock', array( $this, 'on_stock_quantity_change' ), 10, 1 );

		// Mark subscriber as failed when Action Scheduler permanently gives up on the action.
		add_action( 'action_scheduler_failed_action', array( $this, 'on_notification_failed' ), 10, 1 );
	}

	/**
	 * Fires when a product or variation stock STATUS transitions to a new value.
	 * Signature: ( int $product_id, string $stock_status, WC_Product $product )
	 *
	 * @param int         $product_id   The product or variation ID.
	 * @param string      $stock_status The new stock status.
	 * @param \WC_Product $product      The product object.
	 * @return void
	 */
	public function on_stock_status_change( int $product_id, string $stock_status, \WC_Product $product ): void {
		if ( 'instock' !== $stock_status ) {
			return;
		}

		$this->process( $product );
	}

	/**
	 * Fires when a product or variation stock QUANTITY changes.
	 * Signature: ( WC_Product $product )
	 *
	 * Covers restocks triggered by quantity edits rather than manual status changes.
	 *
	 * @param \WC_Product $product The product object with updated stock data.
	 * @return void
	 */
	public function on_stock_quantity_change( \WC_Product $product ): void {
		if ( ! $product->is_in_stock() ) {
			return;
		}

		$this->process( $product );
	}

	/**
	 * Core notification logic — shared by both handlers.
	 *
	 * @param \WC_Product $product Product or variation object.
	 * @return void
	 */
	private function process( \WC_Product $product ): void {
		$settings = Plugin::get_settings();

		if ( empty( $settings['notifications_enabled'] ) || empty( $settings['notification_email_enabled'] ) ) {
			return;
		}

		// Variable parent going instock: notify subscribers of each instock variation.
		if ( $product->is_type( 'variable' ) ) {
			$parent_enabled = get_post_meta( $product->get_id(), '_lswl_enabled', true );
			if ( 'no' === $parent_enabled ) {
				return;
			}

			// Legacy subscriptions stored under the parent ID.
			$this->notify_for_product( $product->get_id() );

			// Per-variation subscriptions: check stock status via meta (bypasses WC object cache).
			foreach ( $product->get_children() as $variation_id ) {
				$variation_stock = get_post_meta( $variation_id, '_stock_status', true );
				if ( 'instock' === $variation_stock ) {
					$this->notify_for_product( $variation_id );
				}
			}

			return;
		}

		// Variation or simple product.
		$check_id        = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
		$product_enabled = get_post_meta( $check_id, '_lswl_enabled', true );

		if ( 'no' === $product_enabled ) {
			return;
		}

		$this->notify_for_product( $product->get_id() );
	}

	/**
	 * Fires when Action Scheduler permanently fails an action (after all attempts exhausted).
	 * Marks the subscriber as failed so the admin can see it and manually resend.
	 *
	 * @param int $action_id Action Scheduler action ID.
	 * @return void
	 */
	public function on_notification_failed( int $action_id ): void {
		if ( ! class_exists( '\ActionScheduler' ) ) {
			return;
		}

		$action = \ActionScheduler::store()->fetch_action( $action_id );

		if ( ! $action || 'lswl_send_notification' !== $action->get_hook() ) {
			return;
		}

		$args          = $action->get_args();
		$subscriber_id = isset( $args[0] ) ? absint( $args[0] ) : 0;

		if ( $subscriber_id > 0 ) {
			Database::mark_failed( array( $subscriber_id ) );
		}
	}

	/**
	 * Queue (or synchronously send) notifications for all pending subscribers of a product.
	 *
	 * @param int $product_id Product or variation ID.
	 * @return void
	 */
	private function notify_for_product( int $product_id ): void {
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			Email::send_notifications( $product_id );
			return;
		}

		$subscribers = Database::get_subscribers( $product_id );

		if ( empty( $subscribers ) ) {
			return;
		}

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
