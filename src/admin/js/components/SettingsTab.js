/**
 * Settings tab — grouped card layout.
 */
import { useState, useEffect } from '@wordpress/element';
import {
	Button,
	TextControl,
	ToggleControl,
	Spinner,
	Notice,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { getSettings, saveSettings } from '../api';

function BellIcon() {
	return (
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
			<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
			<path d="M13.73 21a2 2 0 0 1-3.46 0" />
		</svg>
	);
}

function FormIcon() {
	return (
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
			<rect x="3" y="3" width="18" height="18" rx="2" ry="2" />
			<path d="M7 8h10M7 12h10M7 16h6" />
		</svg>
	);
}

function MailIcon() {
	return (
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
			<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
			<polyline points="22,6 12,13 2,6" />
		</svg>
	);
}

/**
 * A settings card section.
 *
 * @param {{icon: JSX.Element, title: string, children: JSX.Element}} props
 * @return {JSX.Element}
 */
function SettingsCard( { icon, title, children } ) {
	return (
		<div className="lswl-settings-card">
			<div className="lswl-settings-card__header">
				<span className="lswl-settings-card__icon">{ icon }</span>
				<h3 className="lswl-settings-card__title">{ title }</h3>
			</div>
			<div className="lswl-settings-card__body">{ children }</div>
		</div>
	);
}

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

	return (
		<div className="lswl-settings">
			{ error && (
				<Notice status="error" isDismissible onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }

			<SettingsCard
				icon={ <BellIcon /> }
				title={ __( 'Notifications', 'lime-stock-watchlist' ) }
			>
				<ToggleControl
					label={ __( 'Enable watchlist notifications', 'lime-stock-watchlist' ) }
					help={ __( 'Show the notify form on out-of-stock product pages and send back-in-stock emails.', 'lime-stock-watchlist' ) }
					checked={ !! settings.notifications_enabled }
					onChange={ ( v ) => update( 'notifications_enabled', v ) }
				/>
			</SettingsCard>

			<SettingsCard
				icon={ <FormIcon /> }
				title={ __( 'Subscriber Form', 'lime-stock-watchlist' ) }
			>
				<ToggleControl
					label={ __( 'Show name field', 'lime-stock-watchlist' ) }
					help={ __( 'Display an optional name input alongside the email field.', 'lime-stock-watchlist' ) }
					checked={ !! settings.show_name_field }
					onChange={ ( v ) => update( 'show_name_field', v ) }
				/>
				{ settings.show_name_field && (
					<ToggleControl
						label={ __( 'Make name field required', 'lime-stock-watchlist' ) }
						help={ __( 'Customers must enter a name to complete the subscription.', 'lime-stock-watchlist' ) }
						checked={ !! settings.name_field_required }
						onChange={ ( v ) => update( 'name_field_required', v ) }
					/>
				) }
			</SettingsCard>

			<SettingsCard
				icon={ <MailIcon /> }
				title={ __( 'Email Configuration', 'lime-stock-watchlist' ) }
			>
				<TextControl
					label={ __( 'From name', 'lime-stock-watchlist' ) }
					help={ __( 'Sender name in notification emails. Defaults to site name.', 'lime-stock-watchlist' ) }
					value={ settings.from_name }
					onChange={ ( v ) => update( 'from_name', v ) }
				/>
				<TextControl
					label={ __( 'From email', 'lime-stock-watchlist' ) }
					help={ __( 'Sender address for notification emails. Defaults to admin email.', 'lime-stock-watchlist' ) }
					type="email"
					value={ settings.from_email }
					onChange={ ( v ) => update( 'from_email', v ) }
				/>
				<TextControl
					label={ __( 'Email subject', 'lime-stock-watchlist' ) }
					help={ __( 'Notification subject line. Defaults to "{product name} is back in stock!".', 'lime-stock-watchlist' ) }
					value={ settings.email_subject }
					onChange={ ( v ) => update( 'email_subject', v ) }
				/>
			</SettingsCard>

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
