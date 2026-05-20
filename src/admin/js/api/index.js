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
 * Fetch subscribers (paginated, filtered).
 *
 * @param {Object} params
 * @param {'users'|'products'} [params.view]
 * @param {number}             [params.page]
 * @param {number}             [params.per_page]
 * @param {string}             [params.status]
 * @param {string}             [params.search]
 * @param {number}             [params.product_id]
 * @return {Promise<{items: Array, total: number, pages: number}>}
 */
export function getSubscribers( params = {} ) {
	const entries = Object.entries( params ).filter(
		( [ , v ] ) => v !== undefined && v !== '' && v !== 0
	);
	const qs = entries.length ? '?' + new URLSearchParams( Object.fromEntries( entries ) ).toString() : '';
	return apiFetch( { url: url( 'subscribers' ) + qs } );
}

/**
 * Fetch aggregate subscriber stats for the stats bar.
 *
 * @return {Promise<{total: number, watching: number, notifying: number, notified: number, unsubscribed: number}>}
 */
export function getSubscriberStats() {
	return apiFetch( { url: url( 'subscribers/stats' ) } );
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
