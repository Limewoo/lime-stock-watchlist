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

		$success = Database::add_or_resubscribe( $product_id, $email, $name );

		if ( ! $success ) {
			return new \WP_REST_Response(
				array( 'message' => __( 'Could not save your subscription. Please try again.', 'lime-stock-watchlist' ) ),
				500
			);
		}

		return new \WP_REST_Response(
			array( 'message' => __( 'You\'ve been added to the watchlist.', 'lime-stock-watchlist' ) ),
			200
		);
	}

	/**
	 * GET /subscribers
	 *
	 * @return \WP_REST_Response
	 */
	public function handle_get_subscribers(): \WP_REST_Response {
		$grouped = Database::get_all_grouped();
		$data    = array();

		foreach ( $grouped as $product_id => $rows ) {
			$product = wc_get_product( $product_id );
			$data[]  = array(
				'product_id'   => $product_id,
				'product_name' => $product ? esc_html( $product->get_name() ) : sprintf(
					/* translators: %d: product ID */
					__( 'Deleted product #%d', 'lime-stock-watchlist' ),
					$product_id
				),
				'subscribers'  => array_map(
					fn( $row ) => array(
						'id'              => (int) $row->id,
						'email'           => esc_html( $row->email ),
						'name'            => esc_html( $row->name ),
						'date_subscribed' => esc_html( $row->date_subscribed ),
						'notified'        => (bool) $row->notified,
						'unsubscribed'    => (bool) $row->unsubscribed,
					),
					$rows
				),
			);
		}

		return new \WP_REST_Response( $data, 200 );
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
		return new \WP_REST_Response( Plugin::get_settings(), 200 );
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
			'notifications_enabled' => (bool) $request->get_param( 'notifications_enabled' ),
			'show_name_field'       => (bool) $request->get_param( 'show_name_field' ),
			'name_field_required'   => (bool) $request->get_param( 'name_field_required' ),
			'from_name'             => sanitize_text_field( (string) $request->get_param( 'from_name' ) ),
			'from_email'            => sanitize_email( (string) $request->get_param( 'from_email' ) ),
			'email_subject'         => sanitize_text_field( (string) $request->get_param( 'email_subject' ) ),
		);

		// Validate email if provided.
		if ( ! empty( $updated['from_email'] ) && ! is_email( $updated['from_email'] ) ) {
			return new \WP_REST_Response(
				array( 'message' => __( 'Invalid from email address.', 'lime-stock-watchlist' ) ),
				422
			);
		}

		update_option( 'lswl_settings', array_merge( $current, $updated ) );

		return new \WP_REST_Response( Plugin::get_settings(), 200 );
	}

	/**
	 * Argument definitions for the POST /settings route.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function settings_args(): array {
		return array(
			'notifications_enabled' => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'show_name_field'       => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'name_field_required'   => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'from_name'             => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'from_email'            => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_email',
			),
			'email_subject'         => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}
}
