<?php
/**
 * Subscription confirmation email — inner content only.
 * Wrapped with WooCommerce header/footer by Email::build_confirmation_message().
 *
 * Available variables:
 *   $product     WC_Product — the product the subscriber joined the waitlist for.
 *   $subscriber  object     — object with email and name properties.
 *   $subject     string     — email subject line.
 *   $email_body  string     — pre-processed body text (shortcodes already resolved).
 *   $footer_text string     — footer notice text (pre-processed).
 *
 * @package Lime_Stock_Watchlist
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<p><?php echo nl2br( wp_kses_post( $email_body ) ); ?></p>

<p style="font-size:12px;"><?php echo wp_kses_post( $footer_text ); ?></p>
