/**
 * Settings tab — orchestrates settings cards.
 */
import { useState, useEffect, useRef, useCallback } from '@wordpress/element';
import { Spinner, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
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
 * @param {Object}   props
 * @param {Function} props.registerSave
 * @param {boolean}  props.saving
 * @param {boolean}  props.saved
 * @param {Function} props.setSaving
 * @param {Function} props.setSaved
 * @return {JSX.Element}
 */
export default function SettingsTab( { registerSave, saving, saved, setSaving, setSaved } ) {
	const [ settings, setSettings ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );

	// Keep a ref so handleSave (stable via useCallback) always reads latest settings.
	const settingsRef = useRef( settings );
	useEffect( () => {
		settingsRef.current = settings;
	}, [ settings ] );

	useEffect( () => {
		getSettings()
			.then( ( data ) => {
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
				setSettings( merged );
			} )
			.catch( () => setError( __( 'Failed to load settings.', 'lime-stock-watchlist' ) ) )
			.finally( () => setLoading( false ) );
	}, [] );

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
			setSettings( updated );
			setSaved( true );
			setTimeout( () => setSaved( false ), 4000 );
		} catch {
			setError( __( 'Could not save settings. Please try again.', 'lime-stock-watchlist' ) );
		} finally {
			setSaving( false );
		}
	}, [ setSaving, setSaved ] );

	useEffect( () => {
		registerSave( handleSave );
		return () => registerSave( null );
	}, [ handleSave, registerSave ] );

	if ( loading ) {
		return (
			<div style={ { padding: '48px', textAlign: 'center' } }>
				<Spinner style={ { width: '32px', height: '32px' } } />
			</div>
		);
	}

	if ( error && ! settings ) {
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
