<?php
/**
 * Back-in-stock notification email template.
 *
 * Available variables:
 *   $product         WC_Product — the restocked product.
 *   $subscriber      object     — row from lime_watchlist table.
 *   $unsubscribe_url string     — stateless unsubscribe URL.
 *   $subject         string     — email subject line.
 *
 * @package Lime_Stock_Watchlist
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$site_name   = get_bloginfo( 'name' );
$product_url = get_permalink( $product->get_id() );
$has_name    = ! empty( $subscriber->subscriber_name );
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_locale() ); ?>">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title><?php echo esc_html( $subject ); ?></title>
<style>
	body { margin: 0; padding: 0; background: #f5f5f5; font-family: Arial, sans-serif; color: #333; }
	.lswl-email { max-width: 600px; margin: 32px auto; background: #ffffff; border-radius: 4px; overflow: hidden; }
	.lswl-email__header { background: #5d9e3f; padding: 24px 32px; }
	.lswl-email__header-title { margin: 0; color: #ffffff; font-size: 20px; }
	.lswl-email__body { padding: 32px; }
	.lswl-email__body p { line-height: 1.6; margin: 0 0 16px; }
	.lswl-email__cta { display: inline-block; margin: 8px 0 24px; padding: 12px 24px; background: #5d9e3f; color: #ffffff; text-decoration: none; border-radius: 4px; font-weight: bold; }
	.lswl-email__footer { padding: 16px 32px; border-top: 1px solid #e0e0e0; font-size: 12px; color: #888; }
	.lswl-email__footer a { color: #888; }
</style>
</head>
<body>
<div class="lswl-email">
	<div class="lswl-email__header">
		<h1 class="lswl-email__header-title"><?php echo esc_html( $site_name ); ?></h1>
	</div>
	<div class="lswl-email__body">
		<p>
			<?php
			if ( $has_name ) {
				printf(
					/* translators: %s: subscriber first name */
					esc_html__( 'Hi %s,', 'lime-stock-watchlist' ),
					esc_html( $subscriber->subscriber_name )
				);
			} else {
				esc_html_e( 'Hi there,', 'lime-stock-watchlist' );
			}
			?>
		</p>
		<p>
			<?php
			printf(
				/* translators: %s: product name */
				esc_html__( 'Great news! %s is now back in stock.', 'lime-stock-watchlist' ),
				'<strong>' . esc_html( $product->get_name() ) . '</strong>'
			);
			?>
		</p>
		<p>
			<a class="lswl-email__cta" href="<?php echo esc_url( $product_url ); ?>">
				<?php esc_html_e( 'Shop Now', 'lime-stock-watchlist' ); ?>
			</a>
		</p>
		<p>
			<?php
			printf(
				/* translators: %s: site name */
				esc_html__( 'Thank you for shopping with %s.', 'lime-stock-watchlist' ),
				esc_html( $site_name )
			);
			?>
		</p>
	</div>
	<div class="lswl-email__footer">
		<p>
			<?php esc_html_e( 'You received this email because you signed up for back-in-stock notifications.', 'lime-stock-watchlist' ); ?>
			<a href="<?php echo esc_url( $unsubscribe_url ); ?>">
				<?php esc_html_e( 'Unsubscribe', 'lime-stock-watchlist' ); ?>
			</a>
		</p>
	</div>
</div>
</body>
</html>
