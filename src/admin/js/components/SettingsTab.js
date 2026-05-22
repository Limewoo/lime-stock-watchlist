/**
 * Settings tab — orchestrates settings cards.
 */
import { useState, useEffect, useRef, useCallback } from '@wordpress/element';
import { Spinner, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { getSettings, saveSettings } from '../api';
import WatchlistEnableCard from './settings/WatchlistEnableCard';
import SubscriberFormCard from './settings/SubscriberFormCard';
import EmailConfigCard from './settings/EmailConfigCard';
import ConfirmationEmailCard from './settings/ConfirmationEmailCard';
import NotificationEmailCard from './settings/NotificationEmailCard';

/**
 * Convert plain text (with \n line breaks) to HTML paragraphs for TinyMCE.
 *
 * @param {string} text
 * @return {string}
 */
function plainTextToHtml( text ) {
	return text
		.split( /\n\n+/ )
		.map( ( para ) => `<p>${ para.replace( /\n/g, '<br>' ) }</p>` )
		.join( '' );
}

/**
 * Apply placeholder defaults to settings, converting plain-text body fields to HTML.
 *
 * @param {Object} data Raw settings from REST API.
 * @return {Object}
 */
function applyDefaults( data ) {
	const defaults = data._placeholders || {};
	const merged = { ...data };
	const htmlBodyFields = new Set( [ 'confirmation_email_body', 'email_body' ] );
	Object.keys( defaults ).forEach( ( key ) => {
		if ( merged[ key ] === '' ) {
			merged[ key ] = htmlBodyFields.has( key )
				? plainTextToHtml( defaults[ key ] )
				: defaults[ key ];
		}
	} );
	return merged;
}

/**
 * @param {Object}   props
 * @param {Function} props.registerSave
 * @param {boolean}  props.saving
 * @param {boolean}  props.saved
 * @param {Function} props.setSaving
 * @param {Function} props.setSaved
 * @return {JSX.Element}
 */
export default function SettingsTab( { registerSave, saving, saved, setSaving, setSaved } ) {
	const queryClient = useQueryClient();

	// Seed local state from cache synchronously so no spinner on tab switch.
	const [ settings, setSettings ] = useState( () => {
		const cached = queryClient.getQueryData( [ 'settings' ] );
		return cached ? applyDefaults( cached ) : null;
	} );
	const [ error, setError ] = useState( '' );

	const { data: queryData, isLoading } = useQuery( {
		queryKey: [ 'settings' ],
		queryFn: getSettings,
	} );

	// Init local state from query data on first fetch (when no cache on mount).
	const initialized = useRef( settings !== null );
	useEffect( () => {
		if ( queryData && ! initialized.current ) {
			initialized.current = true;
			setSettings( applyDefaults( queryData ) );
		}
	}, [ queryData ] );

	// Keep a ref so handleSave (stable via useCallback) always reads latest settings.
	const settingsRef = useRef( settings );
	useEffect( () => {
		settingsRef.current = settings;
	}, [ settings ] );

	/**
	 * Update a single setting key.
	 *
	 * @param {string} key
	 * @param {*}      value
	 */
	function update( key, value ) {
		setSaved( false );
		setSettings( ( prev ) => ( { ...prev, [ key ]: value } ) );
	}

	const handleSave = useCallback( async () => {
		setSaving( true );
		setError( '' );
		setSaved( false );
		try {
			const updated = await saveSettings( settingsRef.current );
			queryClient.setQueryData( [ 'settings' ], updated );
			setSettings( applyDefaults( updated ) );
			setSaved( true );
			setTimeout( () => setSaved( false ), 4000 );
		} catch {
			setError( __( 'Could not save settings. Please try again.', 'lime-stock-watchlist' ) );
		} finally {
			setSaving( false );
		}
	}, [ setSaving, setSaved, queryClient ] );

	useEffect( () => {
		registerSave( handleSave );
		return () => registerSave( null );
	}, [ handleSave, registerSave ] );

	if ( isLoading && ! settings ) {
		return (
			<div style={ { padding: '48px', textAlign: 'center' } }>
				<Spinner style={ { width: '32px', height: '32px' } } />
			</div>
		);
	}

	if ( ! isLoading && error && ! settings ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ error }
			</Notice>
		);
	}

	if ( ! settings ) {
		return null;
	}

	const placeholders = settings._placeholders || {};

	return (
		<div className="lswl-settings">
			{ error && (
				<Notice status="error" isDismissible onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }

			<WatchlistEnableCard settings={ settings } update={ update } />

			{ settings.notifications_enabled && (
				<>
					<SubscriberFormCard settings={ settings } placeholders={ placeholders } update={ update } />
					<EmailConfigCard settings={ settings } placeholders={ placeholders } update={ update } />
					<ConfirmationEmailCard settings={ settings } placeholders={ placeholders } update={ update } />
					<NotificationEmailCard settings={ settings } placeholders={ placeholders } update={ update } />
				</>
			) }

		</div>
	);
}
