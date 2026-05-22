<?php
/**
 * Main plugin orchestrator.
 *
 * @package Lime_Stock_Watchlist
 */

namespace Lime_Stock_Watchlist;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bootstraps all plugin components and registers shared hooks.
 */
class Plugin {

	/**
	 * Default settings values.
	 *
	 * @var array<string, mixed>
	 */
	public const DEFAULTS = array(
		'notifications_enabled'      => true,
		'form_title'                 => '',
		'form_button_label'          => '',
		'show_name_field'            => false,
		'name_field_required'        => false,
		'msg_success'                => '',
		'msg_duplicate'              => '',
		'msg_error'                  => '',
		'from_name'                  => '',
		'from_email'                 => '',
		'confirmation_email_enabled' => true,
		'confirmation_email_subject' => '',
		'confirmation_email_body'    => '',
		'confirmation_email_footer'  => '',
		'notification_email_enabled' => true,
		'email_subject'              => '',
		'email_body'                 => '',
		'email_footer'               => '',
		'style_accent_color'         => '#5d9e3f',
		'style_btn_text_color'       => '#ffffff',
		'style_btn_radius'           => 3,
		'style_btn_padding_v'        => 10,
		'style_btn_padding_h'        => 20,
		'style_input_border_color'   => '#e0e0e0',
		'style_input_radius'         => 5,
		'style_input_padding_v'      => 10,
		'style_input_padding_h'      => 14,
		'style_heading_color'        => '',
		'style_success_color'        => '#2a6028',
		'style_success_bg'           => '#edf7ec',
		'style_success_border'       => '#b3ddb0',
		'style_error_color'          => '#8a2020',
		'style_error_bg'             => '#fdf1f1',
		'style_error_border'         => '#e6b8b8',
		'form_display_mode'          => 'inline',
		'popup_trigger_label'        => '',
		'show_on_archive'            => false,
		'allow_backorder_subscribe'  => false,
	);

	/**
	 * Wire all classes and register hooks.
	 */
	public function __construct() {
		( new Frontend() )->register();
		( new Compatibility() )->register();
		( new Admin() )->register();
		( new Product_Settings() )->register();
		( new Rest_API() )->register();
		( new Stock_Watcher() )->register();

		add_action( 'lswl_send_notification', array( Email::class, 'handle_queued_notification' ), 10, 2 );
		add_action( 'init', array( $this, 'handle_unsubscribe' ) );
	}

	/**
	 * Handle unsubscribe requests via query vars.
	 * URL: ?lswl_unsub=1&id={id}&token={token}
	 *
	 * @return void
	 */
	public function handle_unsubscribe(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['lswl_unsub'] ) || empty( $_GET['id'] ) || empty( $_GET['token'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$id = absint( wp_unslash( $_GET['id'] ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$token = sanitize_text_field( wp_unslash( $_GET['token'] ) );

		if ( $id <= 0 ) {
			return;
		}

		$subscriber = Database::get_subscriber_by_id( $id );

		if ( ! $subscriber ) {
			return;
		}

		$expected = wp_hash( $subscriber->id . $subscriber->email . NONCE_KEY );

		if ( ! hash_equals( $expected, $token ) ) {
			return;
		}

		$product_url = get_permalink( $subscriber->product_id ) ?: home_url( '/' );

		if ( $subscriber->is_unsubscribed() ) {
			wp_safe_redirect( add_query_arg( 'lswl_already_unsubscribed', '1', $product_url ) );
			exit;
		}

		Database::mark_unsubscribed( $id );

		wp_safe_redirect( add_query_arg( 'lswl_unsubscribed', '1', $product_url ) );
		exit;
	}

	/**
	 * Get merged plugin settings (stored values over defaults).
	 *
	 * @return array<string, mixed>
	 */
	public static function get_settings(): array {
		$stored = get_option( 'lswl_settings', array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return array_merge( self::DEFAULTS, $stored );
	}
}
