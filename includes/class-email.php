<?php
/**
 * Email notifications for back-in-stock subscribers.
 *
 * @package Lime_Stock_Watchlist
 */

namespace Lime_Stock_Watchlist;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds and sends wp_mail() notifications to watchlist subscribers.
 */
class Email {

	/**
	 * Send back-in-stock notifications for all active subscribers of a product.
	 * Used as synchronous fallback when Action Scheduler is unavailable.
	 *
	 * @param int $product_id Product ID.
	 * @return void
	 */
	public static function send_notifications( int $product_id ): void {
		$subscribers = Database::get_subscribers( $product_id );

		if ( empty( $subscribers ) ) {
			return;
		}

		$settings = Plugin::get_settings();
		$product  = wc_get_product( $product_id );

		if ( ! $product ) {
			return;
		}

		$notified_ids = array();

		foreach ( $subscribers as $subscriber ) {
			if ( self::send_to_one( $subscriber, $product, $settings ) ) {
				$notified_ids[] = (int) $subscriber->id;
			}
		}

		if ( ! empty( $notified_ids ) ) {
			Database::mark_notified( $notified_ids );
		}
	}

	/**
	 * Action Scheduler callback: send notification for a single subscriber.
	 * Hook: lswl_send_notification( int $subscriber_id, int $product_id )
	 *
	 * @param int $subscriber_id Subscriber ID.
	 * @param int $product_id    Product ID.
	 * @return void
	 */
	public static function handle_queued_notification( int $subscriber_id, int $product_id ): void {
		$subscriber = Database::get_subscriber_by_id( $subscriber_id );

		if ( ! $subscriber || $subscriber->is_notified() || $subscriber->is_unsubscribed() ) {
			return;
		}

		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return;
		}

		$settings = Plugin::get_settings();

		if ( self::send_to_one( $subscriber, $product, $settings ) ) {
			Database::mark_notified( array( $subscriber_id ) );
		}
	}

	/**
	 * Build and send a single back-in-stock notification email.
	 *
	 * @param Subscriber           $subscriber Subscriber instance.
	 * @param \WC_Product          $product    Product object.
	 * @param array<string, mixed> $settings   Plugin settings.
	 * @return bool Whether wp_mail() succeeded.
	 */
	private static function send_to_one( Subscriber $subscriber, \WC_Product $product, array $settings ): bool {
		$from_name  = ! empty( $settings['from_name'] )
			? $settings['from_name']
			: get_bloginfo( 'name' );

		$from_email = ! empty( $settings['from_email'] ) && is_email( $settings['from_email'] )
			? $settings['from_email']
			: get_option( 'admin_email' );

		$shortcode_map = array(
			'{site_name}'        => get_bloginfo( 'name' ),
			'{product_name}'     => $product->get_name(),
			'{product_url}'      => get_permalink( $product->get_id() ),
			'{subscriber_name}'  => $subscriber->name,
			'{subscriber_email}' => $subscriber->email,
		);

		$subject = ! empty( $settings['email_subject'] )
			? self::process_shortcodes( $settings['email_subject'], $shortcode_map )
			: sprintf(
				/* translators: %s: product name */
				__( '%s is back in stock!', 'lime-stock-watchlist' ),
				$product->get_name()
			);

		$email_body = ! empty( $settings['email_body'] )
			? self::process_shortcodes( $settings['email_body'], $shortcode_map )
			: '';

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', $from_name, $from_email ),
		);

		$unsubscribe_url = self::unsubscribe_url( (int) $subscriber->id, $subscriber->email );

		$message = self::build_message(
			array(
				'product'         => $product,
				'subscriber'      => $subscriber,
				'unsubscribe_url' => $unsubscribe_url,
				'subject'         => $subject,
				'email_body'      => $email_body,
			)
		);

		return wp_mail( $subscriber->email, $subject, $message, $headers );
	}

	/**
	 * Send a subscription confirmation email to a newly-subscribed customer.
	 *
	 * @param \WC_Product          $product    Product object.
	 * @param Subscriber           $subscriber Subscriber instance.
	 * @param array<string, mixed> $settings   Plugin settings.
	 * @return void
	 */
	public static function send_confirmation( \WC_Product $product, Subscriber $subscriber, array $settings ): void {
		$from_name  = ! empty( $settings['from_name'] )
			? $settings['from_name']
			: get_bloginfo( 'name' );

		$from_email = ! empty( $settings['from_email'] ) && is_email( $settings['from_email'] )
			? $settings['from_email']
			: get_option( 'admin_email' );

		$display_name = $subscriber->display_name() ?: __( 'there', 'lime-stock-watchlist' );

		$shortcode_map = array(
			'{site_name}'        => get_bloginfo( 'name' ),
			'{product_name}'     => $product->get_name(),
			'{subscriber_name}'  => $display_name,
			'{subscriber_email}' => $subscriber->email,
		);

		$default_subject = sprintf(
			/* translators: %s: product name */
			__( "You're on the waitlist for %s!", 'lime-stock-watchlist' ),
			$product->get_name()
		);

		$default_body = sprintf(
			/* translators: 1: subscriber first name or "there", 2: product name, 3: site name */
			__( "Hi %1\$s,\n\nYou're on the waitlist for %2\$s. We'll let you know as soon as it's back.\n\nThank you for shopping with %3\$s.", 'lime-stock-watchlist' ),
			$display_name,
			$product->get_name(),
			get_bloginfo( 'name' )
		);

		$subject = ! empty( $settings['confirmation_email_subject'] )
			? self::process_shortcodes( $settings['confirmation_email_subject'], $shortcode_map )
			: $default_subject;

		$email_body = ! empty( $settings['confirmation_email_body'] )
			? self::process_shortcodes( $settings['confirmation_email_body'], $shortcode_map )
			: $default_body;

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', $from_name, $from_email ),
		);

		$message = self::build_confirmation_message(
			array(
				'product'    => $product,
				'subscriber' => $subscriber,
				'subject'    => $subject,
				'email_body' => $email_body,
			)
		);

		wp_mail( $subscriber->email, $subject, $message, $headers );
	}

	/**
	 * Generate a stateless unsubscribe URL for a subscriber.
	 *
	 * @param int    $id    Subscriber ID.
	 * @param string $email Subscriber email.
	 * @return string
	 */
	public static function unsubscribe_url( int $id, string $email ): string {
		$token = wp_hash( $id . $email . NONCE_KEY );

		return add_query_arg(
			array(
				'lswl_unsub' => 1,
				'id'         => $id,
				'token'      => rawurlencode( $token ),
			),
			home_url( '/' )
		);
	}

	/**
	 * Build the HTML back-in-stock email message from the template.
	 *
	 * @param array<string, mixed> $args Template variables.
	 * @return string HTML email body.
	 */
	private static function build_message( array $args ): string {
		$product         = $args['product'];
		$subscriber      = $args['subscriber'];
		$unsubscribe_url = $args['unsubscribe_url'];
		$subject         = $args['subject'];
		$email_body      = $args['email_body'];

		ob_start();
		include LSWL_PATH . 'templates/email-notification.php';
		return ob_get_clean();
	}

	/**
	 * Build the HTML confirmation email message from the template.
	 *
	 * @param array<string, mixed> $args Template variables.
	 * @return string HTML email body.
	 */
	private static function build_confirmation_message( array $args ): string {
		$product    = $args['product'];
		$subscriber = $args['subscriber'];
		$subject    = $args['subject'];
		$email_body = $args['email_body'];

		ob_start();
		include LSWL_PATH . 'templates/email-confirmation.php';
		return ob_get_clean();
	}

	/**
	 * Replace shortcode tokens in a string.
	 *
	 * @param string               $text Text containing shortcode tokens.
	 * @param array<string, mixed> $map  Token => replacement pairs.
	 * @return string
	 */
	private static function process_shortcodes( string $text, array $map ): string {
		return str_replace( array_keys( $map ), array_values( $map ), $text );
	}
}
