<?php
/**
 * Theme compatibility: outputs targeted CSS overrides for known themes.
 *
 * @package Lime_Stock_Watchlist
 */

namespace Lime_Stock_Watchlist;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects the active theme and appends theme-specific inline styles to the
 * frontend stylesheet when needed.
 */
class Compatibility {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_theme_compat' ), 20 );
	}

	/**
	 * Dispatch to the correct theme-specific compat method, if any.
	 * Runs after Frontend::enqueue() (priority 20 > 10) so the base stylesheet
	 * is already queued when we append to it.
	 *
	 * @return void
	 */
	public function enqueue_theme_compat(): void {
		if ( ! wp_style_is( 'lswl-frontend', 'enqueued' ) ) {
			return;
		}

		$theme = wp_get_theme()->get_template();

		if ( 'kadence' === $theme ) {
			$this->kadence_compat();
		} elseif ( 'twentytwentyfive' === $theme || 'woostify' === $theme ) {
			$this->archive_popup_center_compat();
		}
	}

	/**
	 * Twenty Twenty-Five and Woostify theme overrides.
	 * Archive pages: centre-align the popup trigger button inside the product card.
	 *
	 * @return void
	 */
	private function archive_popup_center_compat(): void {
		wp_add_inline_style(
			'lswl-frontend',
			'.lswl-notify-form--archive.lswl-notify-form--popup{text-align:center;}'
		);
	}

	/**
	 * Kadence theme overrides.
	 * Archive pages: add padding and horizontal margin to the notify form wrapper
	 * so it fits comfortably inside Kadence's product card layout.
	 *
	 * @return void
	 */
	private function kadence_compat(): void {
		wp_add_inline_style(
			'lswl-frontend',
			'.lswl-notify-form--archive.lswl-notify-form{padding:1rem 1rem 1.5rem;margin:0 .5em;}'
		);
	}
}
