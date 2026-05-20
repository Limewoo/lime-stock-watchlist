/**
 * Frontend: AJAX subscribe form via the REST API.
 * Supports simple (static) and variable products (variation-aware).
 */
import '../scss/index.scss';

document.addEventListener( 'DOMContentLoaded', () => {
	const wrapper = document.getElementById( 'lswl-notify-form' );
	if ( ! wrapper ) return;

	const form    = wrapper.querySelector( '.lswl-notify-form__form' );
	const message = wrapper.querySelector( '.lswl-notify-form__message' );
	const button  = wrapper.querySelector( '.lswl-notify-form__button' );

	if ( ! form || ! message || ! button ) return;

	const { restUrl, nonce, productId, isVariable, i18n } = window.lswlFrontend || {};

	// Tracks the ID to subscribe against — updated per selected variation.
	let currentProductId = productId;

	// Variation IDs successfully subscribed this page session.
	const subscribedVariations = new Set();

	/**
	 * Show an inline message inside the form wrapper.
	 *
	 * @param {string}            text
	 * @param {'success'|'error'} type
	 */
	function showMessage( text, type ) {
		message.textContent = text;
		message.className   = `lswl-notify-form__message lswl-notify-form__message--${ type }`;
		message.removeAttribute( 'hidden' );
	}

	/**
	 * Reset the form to its initial visible state (used when a new OOS variation is selected).
	 */
	function resetForm() {
		const heading = wrapper.querySelector( '.lswl-notify-form__heading' );
		if ( heading ) heading.removeAttribute( 'hidden' );
		form.removeAttribute( 'hidden' );

		const emailInput = form.querySelector( '#lswl-email' );
		if ( emailInput ) emailInput.value = '';
		const nameInput = form.querySelector( '#lswl-name' );
		if ( nameInput ) nameInput.value = '';

		button.disabled    = false;
		button.textContent = button.dataset.label;

		message.setAttribute( 'hidden', '' );
		message.textContent = '';
	}

	// Variable product: listen to WooCommerce jQuery variation events.
	if ( isVariable && window.jQuery ) {
		const $ = window.jQuery;

		$( document ).on( 'found_variation', '.variations_form', ( _event, variation ) => {
			if ( ! variation.is_in_stock ) {
				currentProductId = variation.variation_id;

				if ( subscribedVariations.has( variation.variation_id ) ) {
					// Already subscribed this session — show success message, not the form.
					wrapper.removeAttribute( 'hidden' );
					return;
				}

				resetForm();
				wrapper.removeAttribute( 'hidden' );
			} else {
				wrapper.setAttribute( 'hidden', '' );
			}
		} );

		$( document ).on( 'reset_data', '.variations_form', () => {
			wrapper.setAttribute( 'hidden', '' );
			currentProductId = productId;
		} );
	}

	form.addEventListener( 'submit', async ( e ) => {
		e.preventDefault();
		message.setAttribute( 'hidden', '' );

		const emailInput = form.querySelector( '#lswl-email' );
		const nameInput  = form.querySelector( '#lswl-name' );
		const email      = emailInput ? emailInput.value.trim() : '';
		const name       = nameInput  ? nameInput.value.trim()  : '';

		if ( nameInput && nameInput.required && ! name ) {
			showMessage( i18n.nameRequired, 'error' );
			nameInput.focus();
			return;
		}

		if ( ! email || ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( email ) ) {
			showMessage( i18n.invalidEmail, 'error' );
			emailInput && emailInput.focus();
			return;
		}

		button.disabled    = true;
		button.textContent = i18n.submitting;

		const body = { product_id: Number( currentProductId ), email };
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
				if ( isVariable ) {
					subscribedVariations.add( Number( currentProductId ) );
					wrapper.querySelector( '.lswl-notify-form__heading' )?.setAttribute( 'hidden', '' );
					form.setAttribute( 'hidden', '' );
				} else {
					wrapper.querySelector( '.lswl-notify-form__heading' )?.remove();
					form.remove();
				}
			} else if ( response.status === 409 ) {
				showMessage( i18n.duplicate, 'error' );
			} else {
				showMessage( data.message || i18n.error, 'error' );
			}
		} catch {
			showMessage( i18n.error, 'error' );
		} finally {
			if ( ! isVariable || ! form.hidden ) {
				button.disabled    = false;
				button.textContent = button.dataset.label;
			}
		}
	} );

	// Store original button label.
	button.dataset.label = button.textContent;
} );
