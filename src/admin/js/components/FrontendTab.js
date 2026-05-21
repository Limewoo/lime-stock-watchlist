/**
 * Style tab — controls visual appearance of the frontend notify form.
 */
import { useState, useEffect } from '@wordpress/element';
import { Button, Spinner, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { getSettings, saveSettings } from '../api';
import ButtonStyleCard from './settings/ButtonStyleCard';
import InputStyleCard from './settings/InputStyleCard';
import TextStyleCard from './settings/TextStyleCard';
import CustomCssCard from './settings/CustomCssCard';

/**
 * @return {JSX.Element}
 */
export default function FrontendTab() {
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
	 * @param {string} key
	 * @param {*}      value
	 */
	function update( key, value ) {
		setSaved( false );
		setSettings( ( prev ) => ( { ...prev, [ key ]: value } ) );
	}

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

			<ButtonStyleCard settings={ settings } update={ update } />
			<InputStyleCard settings={ settings } update={ update } />
			<TextStyleCard settings={ settings } update={ update } />
			<CustomCssCard settings={ settings } update={ update } />

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
