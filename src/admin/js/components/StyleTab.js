/**
 * Style tab — controls visual appearance of the frontend notify form.
 */
import { useState, useEffect, useRef, useCallback } from '@wordpress/element';
import { Spinner, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { getSettings, saveSettings } from '../api';
import ButtonStyleCard from './settings/ButtonStyleCard';
import InputStyleCard from './settings/InputStyleCard';
import TextStyleCard from './settings/TextStyleCard';
import CustomCssCard from './settings/CustomCssCard';

/**
 * @param {Object}   props
 * @param {Function} props.registerSave
 * @param {boolean}  props.saving
 * @param {boolean}  props.saved
 * @param {Function} props.setSaving
 * @param {Function} props.setSaved
 * @return {JSX.Element}
 */
export default function StyleTab( { registerSave, saving, saved, setSaving, setSaved } ) {
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

		</div>
	);
}
