<?php
/**
 * Frontend "Notify me when available" form template.
 *
 * Available variables:
 *   $show_name     bool — whether to show the name field.
 *   $name_required bool — whether the name field is required.
 *
 * @package Lime_Stock_Watchlist
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="lswl-notify-form" id="lswl-notify-form" aria-live="polite">
	<p class="lswl-notify-form__heading">
		<?php esc_html_e( 'Notify me when available', 'lime-stock-watchlist' ); ?>
	</p>

	<form class="lswl-notify-form__form" novalidate>
		<?php if ( $show_name ) : ?>
			<div class="lswl-notify-form__field">
				<label for="lswl-name" class="screen-reader-text">
					<?php esc_html_e( 'Your name', 'lime-stock-watchlist' ); ?>
				</label>
				<input
					type="text"
					id="lswl-name"
					name="lswl_name"
					class="lswl-notify-form__input"
					autocomplete="name"
					placeholder="<?php echo $name_required ? esc_attr__( 'Your name', 'lime-stock-watchlist' ) : esc_attr__( 'Your name (optional)', 'lime-stock-watchlist' ); ?>"
					<?php if ( $name_required ) : ?>
						required
						aria-required="true"
					<?php endif; ?>
				/>
			</div>
		<?php endif; ?>

		<div class="lswl-notify-form__field">
			<label for="lswl-email" class="screen-reader-text">
				<?php esc_html_e( 'Your email address', 'lime-stock-watchlist' ); ?>
			</label>
			<input
				type="email"
				id="lswl-email"
				name="lswl_email"
				class="lswl-notify-form__input"
				autocomplete="email"
				placeholder="<?php esc_attr_e( 'Your email address', 'lime-stock-watchlist' ); ?>"
				required
				aria-required="true"
			/>
		</div>

		<div class="lswl-notify-form__submit">
			<button type="submit" class="lswl-notify-form__button button">
				<?php esc_html_e( 'Notify Me', 'lime-stock-watchlist' ); ?>
			</button>
		</div>

		<div class="lswl-notify-form__message" role="alert" aria-live="assertive" hidden></div>
	</form>
</div>
