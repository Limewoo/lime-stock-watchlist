<?php
/**
 * Frontend "Notify me when available" form template.
 *
 * Available variables:
 *   $show_name         bool   — whether to show the name field.
 *   $name_required     bool   — whether the name field is required.
 *   $form_title        string — custom form heading (empty = default).
 *   $form_button_label string — custom button label (empty = default).
 *   $is_hidden         bool   — whether the wrapper starts hidden (variable products).
 *   $display_mode         string — 'inline' or 'popup'.
 *   $popup_trigger_label  string — trigger button text (popup mode; empty = default form title).
 *   $is_archive           bool   — true when rendered inside a product archive loop.
 *   $product_id           int    — product ID (used for unique element IDs).
 *
 * @package Lime_Stock_Watchlist
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lswl_default_title   = __( 'Notify me when available', 'lime-stock-watchlist' );
$lswl_default_button  = __( 'Notify me', 'lime-stock-watchlist' );
$lswl_title           = ! empty( $form_title ) ? $form_title : $lswl_default_title;
$lswl_button          = ! empty( $form_button_label ) ? $form_button_label : $lswl_default_button;
$lswl_trigger         = ! empty( $popup_trigger_label ) ? $popup_trigger_label : $lswl_title;
$lswl_pid            = (int) $product_id;

if ( 'popup' === $display_mode ) :
?>
<div class="lswl-notify-form lswl-notify-form--popup<?php echo ! empty( $is_archive ) ? ' lswl-notify-form--archive' : ''; ?>" data-product-id="<?php echo esc_attr( $lswl_pid ); ?>"<?php echo ! empty( $is_hidden ) ? ' hidden' : ''; ?>>
	<button type="button" class="lswl-notify-form__trigger">
		<?php echo esc_html( $lswl_trigger ); ?>
	</button>
</div>

<div class="lswl-notify-form__overlay" id="lswl-modal-<?php echo esc_attr( $lswl_pid ); ?>" hidden
	role="dialog" aria-modal="true"
	aria-labelledby="lswl-modal-title-<?php echo esc_attr( $lswl_pid ); ?>">
	<div class="lswl-notify-form__overlay-backdrop"></div>
	<div class="lswl-notify-form__overlay-dialog">
		<button type="button" class="lswl-notify-form__overlay-close"
			aria-label="<?php esc_attr_e( 'Close', 'lime-stock-watchlist' ); ?>">&#215;</button>

		<div class="lswl-notify-form__heading" id="lswl-modal-title-<?php echo esc_attr( $lswl_pid ); ?>">
			<?php echo esc_html( $lswl_title ); ?>
		</div>

		<form class="lswl-notify-form__form" novalidate>
			<?php if ( $show_name ) : ?>
				<div class="lswl-notify-form__field">
					<label for="lswl-name-<?php echo esc_attr( $lswl_pid ); ?>" class="screen-reader-text">
						<?php esc_html_e( 'Your name', 'lime-stock-watchlist' ); ?>
					</label>
					<input
						type="text"
						id="lswl-name-<?php echo esc_attr( $lswl_pid ); ?>"
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
				<label for="lswl-email-<?php echo esc_attr( $lswl_pid ); ?>" class="screen-reader-text">
					<?php esc_html_e( 'Your email address', 'lime-stock-watchlist' ); ?>
				</label>
				<input
					type="email"
					id="lswl-email-<?php echo esc_attr( $lswl_pid ); ?>"
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
					<?php echo esc_html( $lswl_button ); ?>
				</button>
			</div>
		</form>

		<div class="lswl-notify-form__message" role="alert" aria-live="assertive" hidden></div>
	</div>
</div>
<?php else : ?>
<div class="lswl-notify-form" data-product-id="<?php echo esc_attr( $lswl_pid ); ?>" aria-live="polite"<?php echo ! empty( $is_hidden ) ? ' hidden' : ''; ?>>
	<div class="lswl-notify-form__heading">
		<?php echo esc_html( $lswl_title ); ?>
	</div>

	<form class="lswl-notify-form__form" novalidate>
		<?php if ( $show_name ) : ?>
			<div class="lswl-notify-form__field">
				<label for="lswl-name-<?php echo esc_attr( $lswl_pid ); ?>" class="screen-reader-text">
					<?php esc_html_e( 'Your name', 'lime-stock-watchlist' ); ?>
				</label>
				<input
					type="text"
					id="lswl-name-<?php echo esc_attr( $lswl_pid ); ?>"
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
			<label for="lswl-email-<?php echo esc_attr( $lswl_pid ); ?>" class="screen-reader-text">
				<?php esc_html_e( 'Your email address', 'lime-stock-watchlist' ); ?>
			</label>
			<input
				type="email"
				id="lswl-email-<?php echo esc_attr( $lswl_pid ); ?>"
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
				<?php echo esc_html( $lswl_button ); ?>
			</button>
		</div>
	</form>

	<div class="lswl-notify-form__message" role="alert" aria-live="assertive" hidden></div>
</div>
<?php endif; ?>
