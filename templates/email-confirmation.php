<?php
/**
 * Subscription confirmation email template.
 *
 * Available variables:
 *   $product    WC_Product — the product the subscriber joined the waitlist for.
 *   $subscriber object     — object with email and name properties.
 *   $subject    string     — email subject line.
 *   $email_body string     — pre-processed body text (shortcodes already resolved).
 *
 * @package Lime_Stock_Watchlist
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$site_name      = get_bloginfo( 'name' );
$wc_base        = get_option( 'woocommerce_email_base_color', '#7f54b3' );
$wc_bg          = get_option( 'woocommerce_email_background_color', '#f7f7f7' );
$wc_body_bg     = get_option( 'woocommerce_email_body_background_color', '#ffffff' );
$wc_text        = get_option( 'woocommerce_email_text_color', '#3d3d3d' );
$wc_header_text = wc_light_or_dark( $wc_base, '#202020', '#ffffff' );
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_locale() ); ?>">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title><?php echo esc_html( $subject ); ?></title>
<style>
	body { margin: 0; padding: 0; background: <?php echo esc_attr( $wc_bg ); ?>; font-family: Arial, sans-serif; color: <?php echo esc_attr( $wc_text ); ?>; }
	.lswl-email { max-width: 600px; margin: 32px auto; background: <?php echo esc_attr( $wc_body_bg ); ?>; border-radius: 4px; overflow: hidden; }
	.lswl-email__header { background: <?php echo esc_attr( $wc_base ); ?>; padding: 24px 32px; }
	.lswl-email__header-title { margin: 0; color: <?php echo esc_attr( $wc_header_text ); ?>; font-size: 20px; }
	.lswl-email__body { padding: 32px; color: <?php echo esc_attr( $wc_text ); ?>; }
	.lswl-email__body p { line-height: 1.6; margin: 0 0 16px; }
	.lswl-email__footer { padding: 16px 32px; border-top: 1px solid rgba(0,0,0,0.1); font-size: 12px; color: <?php echo esc_attr( $wc_text ); ?>; opacity: 0.7; }
</style>
</head>
<body>
<div class="lswl-email">
	<div class="lswl-email__header">
		<h1 class="lswl-email__header-title"><?php echo esc_html( $site_name ); ?></h1>
	</div>
	<div class="lswl-email__body">
		<p><?php echo nl2br( wp_kses_post( $email_body ) ); ?></p>
	</div>
	<div class="lswl-email__footer">
		<p><?php esc_html_e( 'You received this email because you signed up for back-in-stock notifications.', 'lime-stock-watchlist' ); ?></p>
	</div>
</div>
</body>
</html>
