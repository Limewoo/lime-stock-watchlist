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
				<label for="lswl-name" class="lswl-notify-form__label">
					<?php esc_html_e( 'Your name', 'lime-stock-watchlist' ); ?>
					<?php if ( ! $name_required ) : ?>
						<span class="lswl-notify-form__optional">
							<?php esc_html_e( '(optional)', 'lime-stock-watchlist' ); ?>
						</span>
					<?php endif; ?>
				</label>
				<input
					type="text"
					id="lswl-name"
					name="lswl_name"
					class="lswl-notify-form__input"
					autocomplete="name"
					<?php if ( $name_required ) : ?>
						required
						aria-required="true"
					<?php endif; ?>
				/>
			</div>
		<?php endif; ?>

		<div class="lswl-notify-form__field">
			<label for="lswl-email" class="lswl-notify-form__label">
				<?php esc_html_e( 'Your email address', 'lime-stock-watchlist' ); ?>
				<span class="screen-reader-text"><?php esc_html_e( '(required)', 'lime-stock-watchlist' ); ?></span>
			</label>
			<input
				type="email"
				id="lswl-email"
				name="lswl_email"
				class="lswl-notify-form__input"
				autocomplete="email"
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
