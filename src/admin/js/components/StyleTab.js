/**
 * Style tab — controls visual appearance of the frontend notify form.
 */
import { useState, useEffect, useRef, useCallback } from '@wordpress/element';
import { Spinner, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { getSettings, saveSettings } from '../api';
import ButtonStyleCard from './settings/ButtonStyleCard';
import InputStyleCard from './settings/InputStyleCard';
import TextStyleCard from './settings/TextStyleCard';

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
	const queryClient = useQueryClient();

	// Seed local state from cache synchronously so no spinner on tab switch.
	const [ settings, setSettings ] = useState( () => {
		return queryClient.getQueryData( [ 'settings' ] ) ?? null;
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
			setSettings( queryData );
		}
	}, [ queryData ] );

	// Keep a ref so handleSave (stable via useCallback) always reads latest settings.
	const settingsRef = useRef( settings );
	useEffect( () => {
		settingsRef.current = settings;
	}, [ settings ] );

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
			queryClient.setQueryData( [ 'settings' ], updated );
			setSettings( updated );
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

		</div>
	);
}
