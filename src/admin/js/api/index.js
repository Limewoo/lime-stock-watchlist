/**
 * REST API wrappers using @wordpress/api-fetch.
 *
 * Uses `url` (not `path`) to avoid path-resolution middleware issues
 * when the namespace root URL is localised via wp_localize_script.
 */
import apiFetch from '@wordpress/api-fetch';

const { restUrl, nonce } = window.lswlAdmin || {};

apiFetch.use( apiFetch.createNonceMiddleware( nonce ) );

/**
 * Build an absolute REST URL for a given endpoint.
 *
 * @param {string} endpoint e.g. 'subscribers' or 'subscribers/5'
 * @return {string}
 */
function url( endpoint ) {
	return restUrl.replace( /\/$/, '' ) + '/' + endpoint;
}

/**
 * Fetch all subscribers grouped by product.
 *
 * @return {Promise<Array>}
 */
export function getSubscribers() {
	return apiFetch( { url: url( 'subscribers' ) } );
}

/**
 * Delete a single subscriber.
 *
 * @param {number} id Subscriber ID.
 * @return {Promise<Object>}
 */
export function deleteSubscriber( id ) {
	return apiFetch( {
		url: url( `subscribers/${ id }` ),
		method: 'DELETE',
	} );
}

/**
 * Bulk delete subscribers.
 *
 * @param {number[]} ids Subscriber IDs.
 * @return {Promise<Object>}
 */
export function bulkDeleteSubscribers( ids ) {
	return apiFetch( {
		url: url( 'subscribers' ),
		method: 'DELETE',
		data: { ids },
	} );
}

/**
 * Fetch current settings.
 *
 * @return {Promise<Object>}
 */
export function getSettings() {
	return apiFetch( { url: url( 'settings' ) } );
}

/**
 * Save settings.
 *
 * @param {Object} settings Settings object.
 * @return {Promise<Object>}
 */
export function saveSettings( settings ) {
	return apiFetch( {
		url: url( 'settings' ),
		method: 'POST',
		data: settings,
	} );
}
