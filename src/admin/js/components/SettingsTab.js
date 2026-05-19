/**
 * Settings tab — global plugin settings form.
 */
import { useState, useEffect } from '@wordpress/element';
import {
	Button,
	TextControl,
	ToggleControl,
	Spinner,
	Notice,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { getSettings, saveSettings } from '../api';

/**
 * @return {JSX.Element}
 */
export default function SettingsTab() {
	const [ settings, setSettings ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( '' );
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
		setSettings( ( prev ) => ( { ...prev, [ key ]: value } ) );
	}

	/**
	 * Save settings via REST.
	 */
	async function handleSave() {
		setSaving( true );
		setError( '' );
		try {
			const saved = await saveSettings( settings );
			setSettings( saved );
			setNotice( __( 'Settings saved.', 'lime-stock-watchlist' ) );
		} catch {
			setError( __( 'Could not save settings.', 'lime-stock-watchlist' ) );
		} finally {
			setSaving( false );
		}
	}

	if ( loading ) {
		return <Spinner />;
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
			{ notice && (
				<Notice status="success" isDismissible onRemove={ () => setNotice( '' ) }>
					{ notice }
				</Notice>
			) }

			<VStack spacing={ 4 } className="lswl-settings__form">
				<ToggleControl
					label={ __( 'Enable watchlist notifications', 'lime-stock-watchlist' ) }
					help={ __( 'Show the notify form on out-of-stock product pages and send back-in-stock emails.', 'lime-stock-watchlist' ) }
					checked={ !! settings.notifications_enabled }
					onChange={ ( v ) => update( 'notifications_enabled', v ) }
				/>

				<ToggleControl
					label={ __( 'Show name field on form', 'lime-stock-watchlist' ) }
					help={ __( 'Display an optional name input alongside the email field.', 'lime-stock-watchlist' ) }
					checked={ !! settings.show_name_field }
					onChange={ ( v ) => update( 'show_name_field', v ) }
				/>

				{ settings.show_name_field && (
					<ToggleControl
						label={ __( 'Make name field required', 'lime-stock-watchlist' ) }
						checked={ !! settings.name_field_required }
						onChange={ ( v ) => update( 'name_field_required', v ) }
					/>
				) }

				<TextControl
					label={ __( 'From name', 'lime-stock-watchlist' ) }
					help={ __( 'Sender name shown in notification emails. Defaults to site name.', 'lime-stock-watchlist' ) }
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
					help={ __( 'Notification email subject line. Defaults to "{product name} is back in stock!".', 'lime-stock-watchlist' ) }
					value={ settings.email_subject }
					onChange={ ( v ) => update( 'email_subject', v ) }
				/>

				<Button
					variant="primary"
					onClick={ handleSave }
					isBusy={ saving }
					disabled={ saving }
				>
					{ __( 'Save settings', 'lime-stock-watchlist' ) }
				</Button>
			</VStack>
		</div>
	);
}
