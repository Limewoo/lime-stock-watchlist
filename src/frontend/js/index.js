/**
 * Frontend: AJAX subscribe form via the REST API.
 */
import '../scss/index.scss';

document.addEventListener( 'DOMContentLoaded', () => {
	const wrapper = document.getElementById( 'lswl-notify-form' );
	if ( ! wrapper ) return;

	const form    = wrapper.querySelector( '.lswl-notify-form__form' );
	const message = wrapper.querySelector( '.lswl-notify-form__message' );
	const button  = wrapper.querySelector( '.lswl-notify-form__button' );

	if ( ! form || ! message || ! button ) return;

	const { restUrl, nonce, productId, i18n } = window.lswlFrontend || {};

	/**
	 * Show an inline message inside the form.
	 *
	 * @param {string}  text
	 * @param {'success'|'error'} type
	 */
	function showMessage( text, type ) {
		message.textContent = text;
		message.className   = `lswl-notify-form__message lswl-notify-form__message--${ type }`;
		message.removeAttribute( 'hidden' );
	}

	form.addEventListener( 'submit', async ( e ) => {
		e.preventDefault();
		message.setAttribute( 'hidden', '' );

		const emailInput = form.querySelector( '#lswl-email' );
		const nameInput  = form.querySelector( '#lswl-name' );
		const email      = emailInput ? emailInput.value.trim() : '';
		const name       = nameInput  ? nameInput.value.trim()  : '';

		if ( ! email || ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( email ) ) {
			showMessage( i18n.invalidEmail, 'error' );
			emailInput && emailInput.focus();
			return;
		}

		button.disabled    = true;
		button.textContent = i18n.submitting;

		const body = { product_id: Number( productId ), email };
		if ( name ) {
			body.name = name;
		}

		try {
			const response = await fetch( restUrl + 'subscribe', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': nonce,
				},
				body: JSON.stringify( body ),
			} );

			const data = await response.json();

			if ( response.ok ) {
				showMessage( data.message || i18n.success, 'success' );
				wrapper.querySelector( '.lswl-notify-form__heading' )?.remove();
				form.remove();
			} else if ( response.status === 409 ) {
				showMessage( i18n.duplicate, 'error' );
			} else {
				showMessage( data.message || i18n.error, 'error' );
			}
		} catch {
			showMessage( i18n.error, 'error' );
		} finally {
			button.disabled    = false;
			button.textContent = button.dataset.label || button.textContent;
		}
	} );

	// Store original button label.
	button.dataset.label = button.textContent;
} );
