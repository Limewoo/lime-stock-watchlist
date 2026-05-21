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
 * Renders the "Notify me when available" form on single product and archive pages.
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
		add_action( 'woocommerce_after_shop_loop_item', array( $this, 'render_archive_form' ), 99 );
		add_action( 'woocommerce_before_single_product', array( $this, 'maybe_show_unsubscribe_notice' ) );
	}

	/**
	 * Enqueue frontend assets — on single product pages and (optionally) archive pages.
	 *
	 * @return void
	 */
	public function enqueue(): void {
		$settings   = Plugin::get_settings();
		$on_single  = is_product();
		$on_archive = ! empty( $settings['show_on_archive'] ) && ( is_shop() || is_product_category() || is_product_tag() || is_search() );

		if ( ! $on_single && ! $on_archive ) {
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

		// Output CSS custom properties so frontend form is fully themeable.
		$accent        = sanitize_hex_color( $settings['style_accent_color'] ?? '' ) ?: '#5d9e3f';
		$btn_text      = sanitize_hex_color( $settings['style_btn_text_color'] ?? '' ) ?: '#ffffff';
		$btn_radius    = absint( $settings['style_btn_radius'] ?? 3 );
		$btn_pad_v     = absint( $settings['style_btn_padding_v'] ?? 10 );
		$btn_pad_h     = absint( $settings['style_btn_padding_h'] ?? 20 );
		$input_border  = sanitize_hex_color( $settings['style_input_border_color'] ?? '' ) ?: '#e0e0e0';
		$input_radius  = absint( $settings['style_input_radius'] ?? 5 );
		$input_pad_v   = absint( $settings['style_input_padding_v'] ?? 10 );
		$input_pad_h   = absint( $settings['style_input_padding_h'] ?? 14 );
		$heading_color = sanitize_hex_color( $settings['style_heading_color'] ?? '' );

		$rgb    = self::hex_to_rgb( $accent );
		$dark   = self::hex_darken( $accent, 0.82 );
		$darker = self::hex_darken( $accent, 0.64 );

		$vars = array(
			'--lswl-accent'        => $accent,
			'--lswl-accent-rgb'    => implode( ',', $rgb ),
			'--lswl-accent-dark'   => $dark,
			'--lswl-accent-darker' => $darker,
			'--lswl-btn-text'      => $btn_text,
			'--lswl-btn-radius'    => $btn_radius . 'px',
			'--lswl-btn-padding'   => $btn_pad_v . 'px ' . $btn_pad_h . 'px',
			'--lswl-input-border'  => $input_border,
			'--lswl-input-radius'  => $input_radius . 'px',
			'--lswl-input-padding' => $input_pad_v . 'px ' . $input_pad_h . 'px',
		);

		if ( $heading_color ) {
			$vars['--lswl-heading-color'] = $heading_color;
		}

		$declarations = implode(
			';',
			array_map(
				fn( $k, $v ) => $k . ':' . $v,
				array_keys( $vars ),
				array_values( $vars )
			)
		);

		// Apply CSS vars to both the inline form wrapper and the popup overlay.
		$css_vars = '.lswl-notify-form,.lswl-notify-form__overlay{' . $declarations . '}';

		if ( ! empty( $settings['style_custom_css'] ) ) {
			$css_vars .= str_replace( '</style>', '', $settings['style_custom_css'] );
		}

		wp_add_inline_style( 'lswl-frontend', $css_vars );

		$current_product = $on_single ? wc_get_product( get_the_ID() ) : null;

		wp_localize_script(
			'lswl-frontend',
			'lswlFrontend',
			array(
				'restUrl'     => esc_url_raw( rest_url( 'lime-stock-watchlist/v1/' ) ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'productId'   => $on_single ? get_the_ID() : 0,
				'isVariable'  => $on_single && $current_product && $current_product->is_type( 'variable' ),
				'displayMode' => $settings['form_display_mode'] ?? 'inline',
				'i18n'        => array(
					'success'      => ! empty( $settings['msg_success'] )
						? $settings['msg_success']
						: __( 'Thank you! We\'ll notify you when this product is back in stock.', 'lime-stock-watchlist' ),
					'duplicate'    => ! empty( $settings['msg_duplicate'] )
						? $settings['msg_duplicate']
						: __( 'You\'re already on the waitlist for this product.', 'lime-stock-watchlist' ),
					'nameRequired' => __( 'Please enter your name.', 'lime-stock-watchlist' ),
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
	 * Convert a hex colour string to [R, G, B] integer array.
	 *
	 * @param string $hex e.g. '#5d9e3f' or '5d9e3f'.
	 * @return int[]
	 */
	private static function hex_to_rgb( string $hex ): array {
		$hex = ltrim( $hex, '#' );
		if ( strlen( $hex ) === 3 ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		return array(
			hexdec( substr( $hex, 0, 2 ) ),
			hexdec( substr( $hex, 2, 2 ) ),
			hexdec( substr( $hex, 4, 2 ) ),
		);
	}

	/**
	 * Darken a hex colour by multiplying each channel by $factor (0–1).
	 *
	 * @param string $hex    Source colour e.g. '#5d9e3f'.
	 * @param float  $factor Value < 1 darkens; 0.82 ≈ "lime-dark".
	 * @return string Darkened hex colour.
	 */
	private static function hex_darken( string $hex, float $factor ): string {
		$rgb = self::hex_to_rgb( $hex );
		return sprintf(
			'#%02x%02x%02x',
			max( 0, (int) round( $rgb[0] * $factor ) ),
			max( 0, (int) round( $rgb[1] * $factor ) ),
			max( 0, (int) round( $rgb[2] * $factor ) )
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
	 * Render the notify form on the single product page.
	 * Only shown when: notifications are enabled AND (variable product OR out of stock).
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

		if ( 'no' === get_post_meta( $product->get_id(), '_lswl_enabled', true ) ) {
			return;
		}

		$is_variable = $product->is_type( 'variable' );

		// Simple (and other non-variable) products: only render when out of stock.
		if ( ! $is_variable && $product->is_in_stock() ) {
			return;
		}

		// Variable products render hidden; JS reveals the form when an OOS variation is selected.
		$this->render_form_template( $product, $settings, $is_variable );
	}

	/**
	 * Render the notify form on product archive/loop pages (shop, category, search).
	 * Only shown for simple out-of-stock products when show_on_archive is enabled.
	 *
	 * @return void
	 */
	public function render_archive_form(): void {
		$settings = Plugin::get_settings();

		if ( empty( $settings['notifications_enabled'] ) || empty( $settings['show_on_archive'] ) ) {
			return;
		}

		global $product;

		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		if ( ! $product->is_type( 'simple' ) ) {
			return;
		}

		if ( $product->is_in_stock() ) {
			return;
		}

		if ( 'no' === get_post_meta( $product->get_id(), '_lswl_enabled', true ) ) {
			return;
		}

		$this->render_form_template( $product, $settings, false, true );
	}

	/**
	 * Include the frontend form template with all required variables in scope.
	 *
	 * @param \WC_Product         $product    Current product.
	 * @param array<string,mixed> $settings   Plugin settings.
	 * @param bool                $is_hidden  Whether the form wrapper starts hidden (variable products).
	 * @param bool                $is_archive Whether rendering inside a product archive loop.
	 * @return void
	 */
	private function render_form_template( \WC_Product $product, array $settings, bool $is_hidden, bool $is_archive = false ): void {
		$show_name            = ! empty( $settings['show_name_field'] );
		$name_required        = ! empty( $settings['name_field_required'] );
		$form_title           = ! empty( $settings['form_title'] ) ? $settings['form_title'] : '';
		$form_button_label    = ! empty( $settings['form_button_label'] ) ? $settings['form_button_label'] : '';
		$display_mode         = $settings['form_display_mode'] ?? 'inline';
		$popup_trigger_label  = ! empty( $settings['popup_trigger_label'] ) ? $settings['popup_trigger_label'] : '';
		$product_id           = $product->get_id();

		include LSWL_PATH . 'templates/frontend-form.php';
	}
}
