<?php
/**
 * REST API: registers all custom routes for the watchlist.
 *
 * @package Lime_Stock_Watchlist
 */

namespace Lime_Stock_Watchlist;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the lime-stock-watchlist/v1 REST namespace and all routes.
 */
class Rest_API {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	const NAMESPACE = 'lime-stock-watchlist/v1';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register all routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// POST /subscribe — public.
		register_rest_route(
			self::NAMESPACE,
			'/subscribe',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_subscribe' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'product_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => fn( $v ) => $v > 0,
					),
					'email'      => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
						'validate_callback' => fn( $v ) => is_email( $v ),
					),
					'name'       => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// GET /subscribers — admin.
		register_rest_route(
			self::NAMESPACE,
			'/subscribers',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'handle_get_subscribers' ),
					'permission_callback' => array( $this, 'admin_permission' ),
					'args'                => array(
						'view'       => array(
							'type'    => 'string',
							'default' => 'users',
							'enum'    => array( 'users', 'products' ),
						),
						'page'       => array(
							'type'              => 'integer',
							'default'           => 1,
							'sanitize_callback' => 'absint',
						),
						'per_page'   => array(
							'type'              => 'integer',
							'default'           => 25,
							'sanitize_callback' => 'absint',
						),
						'status'     => array(
							'type'    => 'string',
							'default' => 'all',
							'enum'    => array( 'all', 'watching', 'notifying', 'notified', 'unsubscribed' ),
						),
						'search'     => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'product_id' => array(
							'type'              => 'integer',
							'default'           => 0,
							'sanitize_callback' => 'absint',
						),
					),
				),
				// DELETE /subscribers — bulk.
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'handle_bulk_delete' ),
					'permission_callback' => array( $this, 'admin_permission' ),
					'args'                => array(
						'ids' => array(
							'required'          => true,
							'type'              => 'array',
							'items'             => array( 'type' => 'integer' ),
							'sanitize_callback' => fn( $v ) => array_map( 'absint', (array) $v ),
							'validate_callback' => fn( $v ) => is_array( $v ) && ! empty( $v ),
						),
					),
				),
			)
		);

		// GET /subscribers/stats — admin. Must be registered before the /{id} route.
		register_rest_route(
			self::NAMESPACE,
			'/subscribers/stats',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_get_stats' ),
				'permission_callback' => array( $this, 'admin_permission' ),
			)
		);

		// DELETE /subscribers/{id} — single.
		register_rest_route(
			self::NAMESPACE,
			'/subscribers/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'handle_delete_subscriber' ),
				'permission_callback' => array( $this, 'admin_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => fn( $v ) => $v > 0,
					),
				),
			)
		);

		// GET/POST /settings — admin.
		register_rest_route(
			self::NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'handle_get_settings' ),
					'permission_callback' => array( $this, 'admin_permission' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'handle_save_settings' ),
					'permission_callback' => array( $this, 'admin_permission' ),
					'args'                => $this->settings_args(),
				),
			)
		);
	}

	/**
	 * Permission callback for admin-only routes.
	 *
	 * @return bool|\WP_Error
	 */
	public function admin_permission() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return new \WP_Error(
				'lswl_forbidden',
				__( 'You do not have permission to access this resource.', 'lime-stock-watchlist' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	/**
	 * POST /subscribe
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function handle_subscribe( \WP_REST_Request $request ): \WP_REST_Response {
		$settings = Plugin::get_settings();

		if ( empty( $settings['notifications_enabled'] ) ) {
			return new \WP_REST_Response(
				array( 'message' => __( 'Watchlist is currently disabled.', 'lime-stock-watchlist' ) ),
				503
			);
		}

		$product_id = $request->get_param( 'product_id' );
		$email      = $request->get_param( 'email' );
		$name       = $request->get_param( 'name' );

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return new \WP_REST_Response(
				array( 'message' => __( 'Product not found.', 'lime-stock-watchlist' ) ),
				404
			);
		}

		if ( $product->is_in_stock() ) {
			return new \WP_REST_Response(
				array( 'message' => __( 'This product is already in stock.', 'lime-stock-watchlist' ) ),
				409
			);
		}

		$product_enabled = get_post_meta( $product_id, '_lswl_enabled', true );
		if ( 'no' === $product_enabled ) {
			return new \WP_REST_Response(
				array( 'message' => __( 'Watchlist is disabled for this product.', 'lime-stock-watchlist' ) ),
				403
			);
		}

		$status = Database::add_or_resubscribe( $product_id, $email, $name );

		if ( 'already_subscribed' === $status ) {
			return new \WP_REST_Response(
				array( 'message' => __( 'You\'re already on the waitlist for this product.', 'lime-stock-watchlist' ) ),
				409
			);
		}

		if ( 'error' === $status ) {
			return new \WP_REST_Response(
				array( 'message' => __( 'Could not save your subscription. Please try again.', 'lime-stock-watchlist' ) ),
				500
			);
		}

		if ( ! empty( $settings['confirmation_email_enabled'] ) ) {
			Email::send_confirmation( $product, new Subscriber( 0, 0, $email, $name ), $settings );
		}

		return new \WP_REST_Response(
			array( 'message' => __( 'You\'ve been added to the watchlist.', 'lime-stock-watchlist' ) ),
			200
		);
	}

	/**
	 * GET /subscribers
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function handle_get_subscribers( \WP_REST_Request $request ): \WP_REST_Response {
		$view = $request->get_param( 'view' );

		if ( 'products' === $view ) {
			$result = Database::get_products_with_counts(
				array(
					'page'     => $request->get_param( 'page' ),
					'per_page' => $request->get_param( 'per_page' ),
					'search'   => $request->get_param( 'search' ),
				)
			);
			return new \WP_REST_Response( $result, 200 );
		}

		$result        = Database::get_subscribers_paginated(
			array(
				'page'       => $request->get_param( 'page' ),
				'per_page'   => $request->get_param( 'per_page' ),
				'status'     => $request->get_param( 'status' ),
				'search'     => $request->get_param( 'search' ),
				'product_id' => $request->get_param( 'product_id' ),
				'orderby'    => 'date_subscribed',
				'order'      => 'DESC',
			)
		);
		$product_cache = array();

		$result['items'] = array_map(
			function ( $row ) use ( &$product_cache ) {
				$pid = (int) $row->product_id;

				if ( ! isset( $product_cache[ $pid ] ) ) {
					$product               = wc_get_product( $pid );
					$product_cache[ $pid ] = array(
						'product_name'      => $product
							? esc_html( $product->get_name() )
							: sprintf(
								/* translators: %d: product ID */
								__( 'Deleted product #%d', 'lime-stock-watchlist' ),
								$pid
							),
						'product_thumbnail' => $product ? ( get_the_post_thumbnail_url( $pid, 'thumbnail' ) ?: '' ) : '',
						'product_url'       => $product ? esc_url( get_permalink( $pid ) ) : '',
					);
				}

				return array_merge(
					array(
						'id'              => (int) $row->id,
						'product_id'      => $pid,
						'email'           => esc_html( $row->email ),
						'name'            => esc_html( $row->name ),
						'date_subscribed' => esc_html( $row->date_subscribed ),
						'notified'        => (int) $row->notified,
						'unsubscribed'    => (bool) $row->unsubscribed,
					),
					$product_cache[ $pid ]
				);
			},
			$result['items']
		);

		return new \WP_REST_Response( $result, 200 );
	}

	/**
	 * GET /subscribers/stats
	 *
	 * @return \WP_REST_Response
	 */
	public function handle_get_stats(): \WP_REST_Response {
		return new \WP_REST_Response( Database::get_stats(), 200 );
	}

	/**
	 * DELETE /subscribers/{id}
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function handle_delete_subscriber( \WP_REST_Request $request ): \WP_REST_Response {
		$id      = $request->get_param( 'id' );
		$success = Database::delete_subscribers( array( $id ) );

		if ( ! $success ) {
			return new \WP_REST_Response(
				array( 'message' => __( 'Could not delete subscriber.', 'lime-stock-watchlist' ) ),
				500
			);
		}

		return new \WP_REST_Response( array( 'deleted' => true ), 200 );
	}

	/**
	 * DELETE /subscribers (bulk)
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function handle_bulk_delete( \WP_REST_Request $request ): \WP_REST_Response {
		$ids     = $request->get_param( 'ids' );
		$success = Database::delete_subscribers( $ids );

		if ( ! $success ) {
			return new \WP_REST_Response(
				array( 'message' => __( 'Could not delete subscribers.', 'lime-stock-watchlist' ) ),
				500
			);
		}

		return new \WP_REST_Response( array( 'deleted' => true, 'count' => count( $ids ) ), 200 );
	}

	/**
	 * GET /settings
	 *
	 * @return \WP_REST_Response
	 */
	public function handle_get_settings(): \WP_REST_Response {
		return new \WP_REST_Response( $this->settings_with_placeholders(), 200 );
	}

	/**
	 * Return plugin settings merged with computed placeholder defaults.
	 *
	 * @return array<string, mixed>
	 */
	private function settings_with_placeholders(): array {
		$settings = Plugin::get_settings();

		$settings['_placeholders'] = array(
			'from_name'                  => get_bloginfo( 'name' ),
			'from_email'                 => get_option( 'admin_email' ),
			'confirmation_email_subject' => __( "You're on the waitlist for {product_name}!", 'lime-stock-watchlist' ),
			/* translators: placeholders are literal shortcode tokens, not translated */
			'confirmation_email_body'    => __( "Hi {subscriber_name},\n\nYou're on the waitlist for {product_name}. We'll let you know as soon as it's back.\n\nThank you for shopping with {site_name}.", 'lime-stock-watchlist' ),
			/* translators: placeholder is a literal shortcode token, not translated */
			'email_subject'              => __( '{product_name} is back in stock!', 'lime-stock-watchlist' ),
			/* translators: placeholders are literal shortcode tokens, not translated */
			'email_body'                 => __( "Great news! {product_name} is now back in stock.\n\nThank you for shopping with {site_name}.", 'lime-stock-watchlist' ),
			'form_title'                 => __( 'Notify me when available', 'lime-stock-watchlist' ),
			'form_button_label'          => __( 'Notify me', 'lime-stock-watchlist' ),
			'popup_trigger_label'        => __( 'Notify me when available', 'lime-stock-watchlist' ),
			'msg_success'                => __( "Thank you! We'll notify you when this product is back in stock.", 'lime-stock-watchlist' ),
			'msg_duplicate'              => __( "You're already on the waitlist for this product.", 'lime-stock-watchlist' ),
			'msg_error'                  => __( 'Something went wrong. Please try again.', 'lime-stock-watchlist' ),
		);

		return $settings;
	}

	/**
	 * POST /settings
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function handle_save_settings( \WP_REST_Request $request ): \WP_REST_Response {
		$current = Plugin::get_settings();

		$updated = array(
			'notifications_enabled'       => (bool) $request->get_param( 'notifications_enabled' ),
			'form_title'                  => sanitize_text_field( (string) $request->get_param( 'form_title' ) ),
			'form_button_label'           => sanitize_text_field( (string) $request->get_param( 'form_button_label' ) ),
			'show_name_field'             => (bool) $request->get_param( 'show_name_field' ),
			'name_field_required'         => (bool) $request->get_param( 'name_field_required' ),
			'msg_success'                 => sanitize_text_field( (string) $request->get_param( 'msg_success' ) ),
			'msg_duplicate'               => sanitize_text_field( (string) $request->get_param( 'msg_duplicate' ) ),
			'msg_error'                   => sanitize_text_field( (string) $request->get_param( 'msg_error' ) ),
			'from_name'                   => sanitize_text_field( (string) $request->get_param( 'from_name' ) ),
			'from_email'                  => sanitize_email( (string) $request->get_param( 'from_email' ) ),
			'confirmation_email_enabled'  => (bool) $request->get_param( 'confirmation_email_enabled' ),
			'confirmation_email_subject'  => sanitize_text_field( (string) $request->get_param( 'confirmation_email_subject' ) ),
			'confirmation_email_body'     => wp_kses_post( (string) $request->get_param( 'confirmation_email_body' ) ),
			'notification_email_enabled'  => (bool) $request->get_param( 'notification_email_enabled' ),
			'email_subject'               => sanitize_text_field( (string) $request->get_param( 'email_subject' ) ),
			'email_body'                  => wp_kses_post( (string) $request->get_param( 'email_body' ) ),
			'style_accent_color'          => sanitize_hex_color( (string) $request->get_param( 'style_accent_color' ) ) ?: '#5d9e3f',
			'style_btn_text_color'        => sanitize_hex_color( (string) $request->get_param( 'style_btn_text_color' ) ) ?: '#ffffff',
			'style_btn_radius'            => absint( $request->get_param( 'style_btn_radius' ) ),
			'style_btn_padding_v'         => absint( $request->get_param( 'style_btn_padding_v' ) ),
			'style_btn_padding_h'         => absint( $request->get_param( 'style_btn_padding_h' ) ),
			'style_input_border_color'    => sanitize_hex_color( (string) $request->get_param( 'style_input_border_color' ) ) ?: '#e0e0e0',
			'style_input_radius'          => absint( $request->get_param( 'style_input_radius' ) ),
			'style_input_padding_v'       => absint( $request->get_param( 'style_input_padding_v' ) ),
			'style_input_padding_h'       => absint( $request->get_param( 'style_input_padding_h' ) ),
			'style_heading_color'         => sanitize_hex_color( (string) $request->get_param( 'style_heading_color' ) ) ?: '',
			'style_custom_css'            => wp_strip_all_tags( (string) $request->get_param( 'style_custom_css' ) ),
			'form_display_mode'           => in_array( $request->get_param( 'form_display_mode' ), array( 'inline', 'popup' ), true )
				? (string) $request->get_param( 'form_display_mode' )
				: 'inline',
			'popup_trigger_label'         => sanitize_text_field( (string) $request->get_param( 'popup_trigger_label' ) ),
			'show_on_archive'             => (bool) $request->get_param( 'show_on_archive' ),
		);

		// Validate email if provided.
		if ( ! empty( $updated['from_email'] ) && ! is_email( $updated['from_email'] ) ) {
			return new \WP_REST_Response(
				array( 'message' => __( 'Invalid from email address.', 'lime-stock-watchlist' ) ),
				422
			);
		}

		update_option( 'lswl_settings', array_merge( $current, $updated ) );

		return new \WP_REST_Response( $this->settings_with_placeholders(), 200 );
	}

	/**
	 * Argument definitions for the POST /settings route.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function settings_args(): array {
		return array(
			'notifications_enabled'      => array( 'type' => 'boolean', 'default' => true ),
			'form_title'                 => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
			'form_button_label'          => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
			'show_name_field'            => array( 'type' => 'boolean', 'default' => false ),
			'name_field_required'        => array( 'type' => 'boolean', 'default' => false ),
			'msg_success'                => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
			'msg_duplicate'              => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
			'msg_error'                  => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
			'from_name'                  => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
			'from_email'                 => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_email' ),
			'confirmation_email_enabled' => array( 'type' => 'boolean', 'default' => true ),
			'confirmation_email_subject' => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
			'confirmation_email_body'    => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'wp_kses_post' ),
			'notification_email_enabled' => array( 'type' => 'boolean', 'default' => true ),
			'email_subject'              => array( 'type' => 'string',  'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
			'email_body'                 => array( 'type' => 'string',  'default' => '', 'sanitize_callback' => 'wp_kses_post' ),
			'style_accent_color'         => array( 'type' => 'string',  'default' => '#5d9e3f', 'sanitize_callback' => 'sanitize_hex_color' ),
			'style_btn_text_color'       => array( 'type' => 'string',  'default' => '#ffffff', 'sanitize_callback' => 'sanitize_hex_color' ),
			'style_btn_radius'           => array( 'type' => 'integer', 'default' => 3,          'sanitize_callback' => 'absint' ),
			'style_btn_padding_v'        => array( 'type' => 'integer', 'default' => 10,         'sanitize_callback' => 'absint' ),
			'style_btn_padding_h'        => array( 'type' => 'integer', 'default' => 20,         'sanitize_callback' => 'absint' ),
			'style_input_border_color'   => array( 'type' => 'string',  'default' => '#e0e0e0', 'sanitize_callback' => 'sanitize_hex_color' ),
			'style_input_radius'         => array( 'type' => 'integer', 'default' => 5,          'sanitize_callback' => 'absint' ),
			'style_input_padding_v'      => array( 'type' => 'integer', 'default' => 10,         'sanitize_callback' => 'absint' ),
			'style_input_padding_h'      => array( 'type' => 'integer', 'default' => 14,         'sanitize_callback' => 'absint' ),
			'style_heading_color'        => array( 'type' => 'string',  'default' => '',         'sanitize_callback' => 'sanitize_hex_color' ),
			'style_custom_css'           => array( 'type' => 'string',  'default' => '',         'sanitize_callback' => 'wp_strip_all_tags' ),
			'form_display_mode'          => array( 'type' => 'string',  'default' => 'inline',   'sanitize_callback' => 'sanitize_text_field' ),
			'popup_trigger_label'        => array( 'type' => 'string',  'default' => '',         'sanitize_callback' => 'sanitize_text_field' ),
			'show_on_archive'            => array( 'type' => 'boolean', 'default' => false ),
		);
	}
}
