<?php
/**
 * Frontend: notify form on out-of-stock product pages + asset enqueue.
 *
 * @package Lime_Stock_Watchlist
 */

namespace Lime_Stock_Watchlist;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the "Notify me when available" form on single product pages.
 */
class Frontend {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_form' ), 31 );
	}

	/**
	 * Enqueue frontend assets — only on single product pages.
	 *
	 * @return void
	 */
	public function enqueue(): void {
		if ( ! is_product() ) {
			return;
		}

		$asset_file = LSWL_PATH . 'build/frontend.asset.php';
		$asset      = file_exists( $asset_file )
			? require $asset_file
			: array(
				'dependencies' => array(),
				'version'      => LSWL_VERSION,
			);

		wp_enqueue_style(
			'lswl-frontend',
			LSWL_URL . 'build/frontend.css',
			array(),
			$asset['version']
		);

		wp_enqueue_script(
			'lswl-frontend',
			LSWL_URL . 'build/frontend.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_localize_script(
			'lswl-frontend',
			'lswlFrontend',
			array(
				'restUrl'   => esc_url_raw( rest_url( 'lime-stock-watchlist/v1/' ) ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'productId' => get_the_ID(),
				'i18n'      => array(
					'success'    => __( 'Thank you! We\'ll notify you when this product is back in stock.', 'lime-stock-watchlist' ),
					'duplicate'  => __( 'You\'re already on the waitlist for this product.', 'lime-stock-watchlist' ),
					'invalidEmail' => __( 'Please enter a valid email address.', 'lime-stock-watchlist' ),
					'error'      => __( 'Something went wrong. Please try again.', 'lime-stock-watchlist' ),
					'submitting' => __( 'Please wait…', 'lime-stock-watchlist' ),
				),
			)
		);
	}

	/**
	 * Render the notify form on the product page.
	 * Only shown when: product is out of stock AND notifications are enabled.
	 *
	 * @return void
	 */
	public function render_form(): void {
		global $product;

		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		if ( $product->is_in_stock() ) {
			return;
		}

		$settings = Plugin::get_settings();

		if ( empty( $settings['notifications_enabled'] ) ) {
			return;
		}

		$product_enabled = get_post_meta( $product->get_id(), '_lswl_enabled', true );

		if ( 'no' === $product_enabled ) {
			return;
		}

		$show_name = ! empty( $settings['show_name_field'] );
		$name_required = ! empty( $settings['name_field_required'] );

		include LSWL_PATH . 'templates/frontend-form.php';
	}
}
