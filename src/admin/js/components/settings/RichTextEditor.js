/**
 * Rich text email body editor using WordPress's bundled TinyMCE.
 * Falls back gracefully to a plain textarea if TinyMCE is unavailable.
 */
import { useEffect, useRef } from '@wordpress/element';

const TOOLBAR = 'bold italic underline | link | bullist numlist | removeformat';
const CONTENT_STYLE = 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; font-size: 14px; color: #1d2327; margin: 8px; }';

/**
 * @param {{
 *   id: string,
 *   label: string,
 *   help?: string,
 *   value: string,
 *   onChange: (value: string) => void,
 * }} props
 * @return {JSX.Element}
 */
export default function RichTextEditor( { id, label, help, value, onChange } ) {
	const onChangeRef = useRef( onChange );
	onChangeRef.current = onChange;

	// Always holds the latest value so the init callback can read it.
	const valueRef = useRef( value );
	valueRef.current = value;

	useEffect( () => {
		const tmce = window.tinymce;
		if ( ! tmce ) return;

		tmce.init( {
			selector: `#${ id }`,
			menubar: false,
			statusbar: false,
			branding: false,
			plugins: 'link lists',
			toolbar: TOOLBAR,
			height: 200,
			content_style: CONTENT_STYLE,
			setup( editor ) {
				editor.on( 'init', () => {
					// Set content after TinyMCE is ready — valueRef has the latest prop.
					editor.setContent( valueRef.current || '' );
				} );
				editor.on( 'change keyup', () => {
					onChangeRef.current( editor.getContent() );
				} );
			},
		} );

		return () => {
			const editor = tmce.get( id );
			if ( editor ) editor.remove();
		};
	}, [ id ] );

	// Sync value when changed externally after init (e.g. after save/re-fetch).
	useEffect( () => {
		const editor = window.tinymce?.get( id );
		if ( editor && editor.initialized && editor.getContent() !== ( value || '' ) ) {
			editor.setContent( value || '' );
		}
	}, [ id, value ] );

	return (
		<div className="lswl-rte-wrap">
			{ label && (
				<label className="lswl-rte-wrap__label" htmlFor={ id }>
					{ label }
				</label>
			) }
			<textarea id={ id } defaultValue={ value || '' } />
			{ help && (
				<p className="components-base-control__help lswl-rte-wrap__help">{ help }</p>
			) }
		</div>
	);
}
