/**
 * Settings tab — orchestrates settings cards.
 */
import { useState, useEffect } from '@wordpress/element';
import { Button, Spinner, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { getSettings, saveSettings } from '../api';
import WatchlistEnableCard from './settings/WatchlistEnableCard';
import SubscriberFormCard from './settings/SubscriberFormCard';
import EmailConfigCard from './settings/EmailConfigCard';
import ConfirmationEmailCard from './settings/ConfirmationEmailCard';
import NotificationEmailCard from './settings/NotificationEmailCard';

/**
 * @return {JSX.Element}
 */
export default function SettingsTab() {
	const [ settings, setSettings ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ saved, setSaved ] = useState( false );
	const [ error, setError ] = useState( '' );

	useEffect( () => {
		getSettings()
			.then( setSettings )
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

	/**
	 * Save all settings via REST.
	 */
	async function handleSave() {
		setSaving( true );
		setError( '' );
		setSaved( false );
		try {
			const updated = await saveSettings( settings );
			setSettings( updated );
			setSaved( true );
			setTimeout( () => setSaved( false ), 4000 );
		} catch {
			setError( __( 'Could not save settings. Please try again.', 'lime-stock-watchlist' ) );
		} finally {
			setSaving( false );
		}
	}

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

			<div className="lswl-settings__footer">
				<Button
					variant="primary"
					onClick={ handleSave }
					isBusy={ saving }
					disabled={ saving }
				>
					{ __( 'Save settings', 'lime-stock-watchlist' ) }
				</Button>
				{ saved && (
					<span className="lswl-settings__saved-msg">
						{ __( 'Settings saved', 'lime-stock-watchlist' ) }
					</span>
				) }
			</div>
		</div>
	);
}
