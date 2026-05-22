/**
 * Frontend: AJAX subscribe form via the REST API.
 * Supports inline and popup display modes, simple and variable products.
 */
import '../scss/index.scss';

document.addEventListener( 'DOMContentLoaded', () => {
	const cfg = window.lswlFrontend;
	if ( ! cfg ) return;

	const { restUrl, nonce, productId: parentProductId, isVariable, displayMode, i18n, allowBackorderSubscribe } = cfg;

	if ( displayMode === 'popup' ) {
		initAllPopups();
	} else {
		initAllInlineForms();
	}

	// ── Inline forms ─────────────────────────────────────────────────

	function initAllInlineForms() {
		document.querySelectorAll( '.lswl-notify-form' ).forEach( ( wrapper ) => {
			const pid         = Number( wrapper.dataset.productId || parentProductId );
			let currentPid    = pid;
			const subscribedVariations = new Set();

			const form    = wrapper.querySelector( '.lswl-notify-form__form' );
			const message = wrapper.querySelector( '.lswl-notify-form__message' );
			const button  = wrapper.querySelector( '.lswl-notify-form__button' );
			if ( ! form || ! message || ! button ) return;

			button.dataset.label = button.textContent;

			// Variable product events — only for the single product page form.
			if ( isVariable && window.jQuery && pid === Number( parentProductId ) ) {
				const $ = window.jQuery;

				$( document ).on( 'found_variation', '.variations_form', ( _evt, variation ) => {
					const isOos      = ! variation.is_in_stock;
					const isBackorder = variation.lswl_stock_status === 'onbackorder';
					if ( isOos || ( isBackorder && allowBackorderSubscribe ) ) {
						currentPid = variation.variation_id;
						if ( subscribedVariations.has( variation.variation_id ) ) {
							wrapper.removeAttribute( 'hidden' );
							return;
						}
						resetInlineForm( wrapper, form, message, button );
						wrapper.removeAttribute( 'hidden' );
					} else {
						wrapper.setAttribute( 'hidden', '' );
					}
				} );

				$( document ).on( 'reset_data', '.variations_form', () => {
					wrapper.setAttribute( 'hidden', '' );
					currentPid = pid;
				} );
			}

			form.addEventListener( 'submit', async ( e ) => {
				e.preventDefault();
				await handleSubmit( {
					form,
					message,
					button,
					productId: currentPid,
					restUrl,
					nonce,
					i18n,
					onSuccess: () => {
						if ( isVariable && pid === Number( parentProductId ) ) {
							subscribedVariations.add( currentPid );
							wrapper.querySelector( '.lswl-notify-form__heading' )?.setAttribute( 'hidden', '' );
							form.setAttribute( 'hidden', '' );
						} else {
							wrapper.querySelector( '.lswl-notify-form__heading' )?.remove();
							form.remove();
						}
					},
					onDuplicate: ( msg ) => showMessage( message, msg || i18n.duplicate, 'error' ),
					onError:     ( msg ) => showMessage( message, msg || i18n.error,     'error' ),
				} );
			} );
		} );
	}

	// ── Popup forms ──────────────────────────────────────────────────

	function initAllPopups() {
		document.querySelectorAll( '.lswl-notify-form--popup' ).forEach( ( triggerWrapper ) => {
			const pid      = Number( triggerWrapper.dataset.productId || parentProductId );
			let currentPid = pid;
			const subscribedVariations = new Set();

			const overlay = document.getElementById( `lswl-modal-${ pid }` );
			if ( ! overlay ) return;

			const triggerBtn = triggerWrapper.querySelector( '.lswl-notify-form__trigger' );
			const backdrop   = overlay.querySelector( '.lswl-notify-form__overlay-backdrop' );
			const closeBtn   = overlay.querySelector( '.lswl-notify-form__overlay-close' );
			const form       = overlay.querySelector( '.lswl-notify-form__form' );
			const message    = overlay.querySelector( '.lswl-notify-form__message' );
			const button     = overlay.querySelector( '.lswl-notify-form__button' );
			if ( ! form || ! message || ! button ) return;

			button.dataset.label = button.textContent;

			const open = () => {
				overlay.removeAttribute( 'hidden' );
				document.body.style.overflow = 'hidden';
				const firstInput = overlay.querySelector( '.lswl-notify-form__input:not([hidden])' );
				( firstInput || button ).focus();
			};

			const close = () => {
				overlay.setAttribute( 'hidden', '' );
				document.body.style.overflow = '';
				triggerBtn?.focus();
			};

			triggerBtn?.addEventListener( 'click', open );
			backdrop?.addEventListener( 'click', close );
			closeBtn?.addEventListener( 'click', close );

			document.addEventListener( 'keydown', ( e ) => {
				if ( e.key === 'Escape' && ! overlay.hidden ) close();
			} );

			// Variable product events — only for the single product page.
			if ( isVariable && window.jQuery && pid === Number( parentProductId ) ) {
				const $ = window.jQuery;

				$( document ).on( 'found_variation', '.variations_form', ( _evt, variation ) => {
					const isOos       = ! variation.is_in_stock;
					const isBackorder = variation.lswl_stock_status === 'onbackorder';
					if ( isOos || ( isBackorder && allowBackorderSubscribe ) ) {
						currentPid = variation.variation_id;
						if ( ! overlay.hidden ) resetPopupForm( form, message, button );
						triggerWrapper.removeAttribute( 'hidden' );
					} else {
						triggerWrapper.setAttribute( 'hidden', '' );
						if ( ! overlay.hidden ) close();
					}
				} );

				$( document ).on( 'reset_data', '.variations_form', () => {
					triggerWrapper.setAttribute( 'hidden', '' );
					currentPid = pid;
					if ( ! overlay.hidden ) close();
				} );
			}

			form.addEventListener( 'submit', async ( e ) => {
				e.preventDefault();
				await handleSubmit( {
					form,
					message,
					button,
					productId: currentPid,
					restUrl,
					nonce,
					i18n,
					onSuccess: () => {
						if ( isVariable && pid === Number( parentProductId ) ) {
							subscribedVariations.add( currentPid );
						}
						form.setAttribute( 'hidden', '' );
					},
					onDuplicate: ( msg ) => showMessage( message, msg || i18n.duplicate, 'error' ),
					onError:     ( msg ) => showMessage( message, msg || i18n.error,     'error' ),
				} );
			} );
		} );
	}

	// ── Shared helpers ───────────────────────────────────────────────

	/**
	 * @param {HTMLElement}        el
	 * @param {string}             text
	 * @param {'success'|'error'}  type
	 */
	function showMessage( el, text, type ) {
		el.textContent = text;
		el.className   = `lswl-notify-form__message lswl-notify-form__message--${ type }`;
		el.removeAttribute( 'hidden' );
	}

	/**
	 * Reset inline form fields and messages to initial state.
	 *
	 * @param {HTMLElement} wrapper
	 * @param {HTMLElement} form
	 * @param {HTMLElement} message
	 * @param {HTMLElement} button
	 */
	function resetInlineForm( wrapper, form, message, button ) {
		wrapper.querySelector( '.lswl-notify-form__heading' )?.removeAttribute( 'hidden' );
		form.removeAttribute( 'hidden' );

		const emailInput = form.querySelector( '[name="lswl_email"]' );
		if ( emailInput ) emailInput.value = '';
		const nameInput = form.querySelector( '[name="lswl_name"]' );
		if ( nameInput ) nameInput.value = '';

		button.disabled    = false;
		button.textContent = button.dataset.label;

		message.setAttribute( 'hidden', '' );
		message.textContent = '';
	}

	/**
	 * Reset popup form fields and messages (called when a new variation is selected).
	 *
	 * @param {HTMLElement} form
	 * @param {HTMLElement} message
	 * @param {HTMLElement} button
	 */
	function resetPopupForm( form, message, button ) {
		form.removeAttribute( 'hidden' );

		const emailInput = form.querySelector( '[name="lswl_email"]' );
		if ( emailInput ) emailInput.value = '';
		const nameInput = form.querySelector( '[name="lswl_name"]' );
		if ( nameInput ) nameInput.value = '';

		button.disabled    = false;
		button.textContent = button.dataset.label;

		message.setAttribute( 'hidden', '' );
		message.textContent = '';
	}

	/**
	 * Submit handler shared by inline and popup forms.
	 *
	 * @param {Object}   opts
	 * @param {HTMLElement} opts.form
	 * @param {HTMLElement} opts.message
	 * @param {HTMLElement} opts.button
	 * @param {number}   opts.productId
	 * @param {string}   opts.restUrl
	 * @param {string}   opts.nonce
	 * @param {Object}   opts.i18n
	 * @param {Function} opts.onSuccess
	 * @param {Function} opts.onDuplicate
	 * @param {Function} opts.onError
	 */
	async function handleSubmit( { form, message, button, productId, restUrl, nonce, i18n, onSuccess, onDuplicate, onError } ) {
		message.setAttribute( 'hidden', '' );

		const emailInput = form.querySelector( '[name="lswl_email"]' );
		const nameInput  = form.querySelector( '[name="lswl_name"]' );
		const email      = emailInput ? emailInput.value.trim() : '';
		const name       = nameInput  ? nameInput.value.trim()  : '';

		if ( nameInput && nameInput.required && ! name ) {
			showMessage( message, i18n.nameRequired, 'error' );
			nameInput.focus();
			return;
		}

		if ( ! email || ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( email ) ) {
			showMessage( message, i18n.invalidEmail, 'error' );
			emailInput?.focus();
			return;
		}

		button.disabled    = true;
		button.textContent = i18n.submitting;

		const body = { product_id: Number( productId ), email };
		if ( name ) body.name = name;

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
				showMessage( message, data.message || i18n.success, 'success' );
				onSuccess();
			} else if ( response.status === 409 ) {
				onDuplicate( data.message );
			} else {
				onError( data.message );
			}
		} catch {
			onError( null );
		} finally {
			if ( ! form.hidden ) {
				button.disabled    = false;
				button.textContent = button.dataset.label;
			}
		}
	}
} );
