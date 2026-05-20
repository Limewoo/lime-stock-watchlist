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
		add_action( 'woocommerce_before_single_product', array( $this, 'maybe_show_unsubscribe_notice' ) );
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

		$settings = Plugin::get_settings();

		$current_product = wc_get_product( get_the_ID() );

		wp_localize_script(
			'lswl-frontend',
			'lswlFrontend',
			array(
				'restUrl'    => esc_url_raw( rest_url( 'lime-stock-watchlist/v1/' ) ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'productId'  => get_the_ID(),
				'isVariable' => $current_product && $current_product->is_type( 'variable' ),
				'i18n'       => array(
					'success'      => ! empty( $settings['msg_success'] )
						? $settings['msg_success']
						: __( 'Thank you! We\'ll notify you when this product is back in stock.', 'lime-stock-watchlist' ),
					'duplicate'    => ! empty( $settings['msg_duplicate'] )
						? $settings['msg_duplicate']
						: __( 'You\'re already on the waitlist for this product.', 'lime-stock-watchlist' ),
					'invalidEmail' => __( 'Please enter a valid email address.', 'lime-stock-watchlist' ),
					'error'        => ! empty( $settings['msg_error'] )
						? $settings['msg_error']
						: __( 'Something went wrong. Please try again.', 'lime-stock-watchlist' ),
					'submitting'   => __( 'Please wait…', 'lime-stock-watchlist' ),
				),
			)
		);
	}

	/**
	 * Show a notice on the product page after unsubscribe redirect.
	 *
	 * @return void
	 */
	public function maybe_show_unsubscribe_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['lswl_unsubscribed'] ) ) {
			echo '<div role="alert" class="woocommerce-message">' . esc_html__( "You've been unsubscribed from back-in-stock notifications for this product.", 'lime-stock-watchlist' ) . '</div>';
		} elseif ( ! empty( $_GET['lswl_already_unsubscribed'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div role="alert" class="woocommerce-info">' . esc_html__( "You're already unsubscribed from notifications for this product.", 'lime-stock-watchlist' ) . '</div>';
		}
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

		$settings = Plugin::get_settings();

		if ( empty( $settings['notifications_enabled'] ) ) {
			return;
		}

		$product_enabled = get_post_meta( $product->get_id(), '_lswl_enabled', true );

		if ( 'no' === $product_enabled ) {
			return;
		}

		$is_variable = $product->is_type( 'variable' );

		// Simple (and other non-variable) products: only render when out of stock.
		if ( ! $is_variable && $product->is_in_stock() ) {
			return;
		}

		$show_name         = ! empty( $settings['show_name_field'] );
		$name_required     = ! empty( $settings['name_field_required'] );
		$form_title        = ! empty( $settings['form_title'] ) ? $settings['form_title'] : '';
		$form_button_label = ! empty( $settings['form_button_label'] ) ? $settings['form_button_label'] : '';

		// Variable products render hidden; JS reveals the form when an OOS variation is selected.
		$is_hidden = $is_variable;

		include LSWL_PATH . 'templates/frontend-form.php';
	}
}
