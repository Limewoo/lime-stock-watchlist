<?php
/**
 * Back-in-stock notification email — inner content only.
 * Wrapped with WooCommerce header/footer by Email::build_message().
 *
 * Available variables:
 *   $product         WC_Product — the restocked product.
 *   $subscriber      object     — row from lime_watchlist table.
 *   $unsubscribe_url string     — stateless unsubscribe URL.
 *   $subject         string     — email subject line.
 *   $email_body      string     — custom body (pre-processed shortcodes), or '' for default.
 *   $footer_text     string     — footer notice text (pre-processed).
 *
 * @package Lime_Stock_Watchlist
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$site_name   = get_bloginfo( 'name' );
$product_url = get_permalink( $product->get_id() );
$has_name    = ! empty( $subscriber->name );
$first_name  = $has_name ? explode( ' ', trim( $subscriber->name ) )[0] : '';
?>

<?php if ( ! empty( $email_body ) ) : ?>
	<p><?php echo nl2br( wp_kses_post( $email_body ) ); ?></p>
<?php else : ?>
	<p>
		<?php
		if ( $has_name ) {
			printf(
				/* translators: %s: subscriber first name */
				esc_html__( 'Hi %s,', 'lime-stock-watchlist' ),
				esc_html( $first_name )
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
		<?php
		printf(
			/* translators: %s: site name */
			esc_html__( 'Thank you for shopping with %s.', 'lime-stock-watchlist' ),
			esc_html( $site_name )
		);
		?>
	</p>
<?php endif; ?>

<p>
	<a href="<?php echo esc_url( $product_url ); ?>" class="button"><?php esc_html_e( 'Shop Now', 'lime-stock-watchlist' ); ?></a>
</p>

<p style="font-size:12px;"><?php echo wp_kses_post( $footer_text ); ?> <a href="<?php echo esc_url( $unsubscribe_url ); ?>"><?php esc_html_e( 'Unsubscribe', 'lime-stock-watchlist' ); ?></a></p>
