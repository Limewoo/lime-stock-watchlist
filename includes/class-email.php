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

		$from_name  = ! empty( $settings['from_name'] )
			? $settings['from_name']
			: get_bloginfo( 'name' );

		$from_email = ! empty( $settings['from_email'] ) && is_email( $settings['from_email'] )
			? $settings['from_email']
			: get_option( 'admin_email' );

		$subject = ! empty( $settings['email_subject'] )
			? $settings['email_subject']
			: sprintf(
				/* translators: %s: product name */
				__( '%s is back in stock!', 'lime-stock-watchlist' ),
				$product->get_name()
			);

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', $from_name, $from_email ),
		);

		$notified_ids = array();

		foreach ( $subscribers as $subscriber ) {
			$unsubscribe_url = self::unsubscribe_url( (int) $subscriber->id, $subscriber->email );

			$message = self::build_message(
				array(
					'product'         => $product,
					'subscriber'      => $subscriber,
					'unsubscribe_url' => $unsubscribe_url,
					'subject'         => $subject,
				)
			);

			$sent = wp_mail(
				$subscriber->email,
				$subject,
				$message,
				$headers
			);

			if ( $sent ) {
				$notified_ids[] = (int) $subscriber->id;
			}
		}

		if ( ! empty( $notified_ids ) ) {
			Database::mark_notified( $notified_ids );
		}
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
	 * Build the HTML email message from the template.
	 *
	 * @param array<string, mixed> $args Template variables.
	 * @return string HTML email body.
	 */
	private static function build_message( array $args ): string {
		$product         = $args['product'];
		$subscriber      = $args['subscriber'];
		$unsubscribe_url = $args['unsubscribe_url'];
		$subject         = $args['subject'];

		ob_start();
		include LSWL_PATH . 'templates/email-notification.php';
		return ob_get_clean();
	}
}
