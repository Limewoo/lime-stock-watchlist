<?php
/**
 * Per-product watchlist settings in the WooCommerce Product Data tabs.
 *
 * @package Lime_Stock_Watchlist
 */

namespace Lime_Stock_Watchlist;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds a "Watchlist" tab to the WooCommerce Product Data meta box.
 */
class Product_Settings {

	/**
	 * Product meta key for per-product enable/disable.
	 *
	 * @var string
	 */
	const META_KEY = '_lswl_enabled';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_meta' ) );
	}

	/**
	 * Add the "Watchlist" tab to Product Data.
	 *
	 * @param array<string, array<string, string>> $tabs Existing tabs.
	 * @return array<string, array<string, string>>
	 */
	public function add_tab( array $tabs ): array {
		$tabs['lswl_watchlist'] = array(
			'label'    => __( 'Watchlist', 'lime-stock-watchlist' ),
			'target'   => 'lswl_watchlist_data',
			'class'    => array(),
			'priority' => 90,
		);
		return $tabs;
	}

	/**
	 * Render the Watchlist panel content.
	 *
	 * @return void
	 */
	public function render_panel(): void {
		global $post;

		$value = get_post_meta( $post->ID, self::META_KEY, true );
		if ( '' === $value ) {
			$value = 'inherit';
		}

		echo '<div id="lswl_watchlist_data" class="panel woocommerce_options_panel">';
		echo '<div class="options_group">';

		woocommerce_wp_select(
			array(
				'id'          => 'lswl_enabled',
				'name'        => 'lswl_enabled',
				'label'       => __( 'Watchlist', 'lime-stock-watchlist' ),
				'description' => __( 'Control the back-in-stock notification form for this product.', 'lime-stock-watchlist' ),
				'desc_tip'    => true,
				'value'       => esc_attr( $value ),
				'options'     => array(
					'inherit' => __( 'Inherit global setting', 'lime-stock-watchlist' ),
					'yes'     => __( 'Enabled', 'lime-stock-watchlist' ),
					'no'      => __( 'Disabled', 'lime-stock-watchlist' ),
				),
			)
		);

		echo '</div></div>';
	}

	/**
	 * Save per-product meta on product save.
	 *
	 * @param int $post_id Product post ID.
	 * @return void
	 */
	public function save_meta( int $post_id ): void {
		if ( ! isset( $_POST['lswl_enabled'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		$allowed = array( 'inherit', 'yes', 'no' );
		$value   = sanitize_text_field( wp_unslash( $_POST['lswl_enabled'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! in_array( $value, $allowed, true ) ) {
			$value = 'inherit';
		}

		if ( 'inherit' === $value ) {
			delete_post_meta( $post_id, self::META_KEY );
		} else {
			update_post_meta( $post_id, self::META_KEY, $value );
		}
	}
}
