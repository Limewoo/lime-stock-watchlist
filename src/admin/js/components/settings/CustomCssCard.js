import { __ } from '@wordpress/i18n';
import SettingsCard from './SettingsCard';
import { CodeIcon } from './icons';

/**
 * @param {{settings: Object, update: Function}} props
 * @return {JSX.Element}
 */
export default function CustomCssCard( { settings, update } ) {
	return (
		<SettingsCard
			icon={ <CodeIcon /> }
			title={ __( 'Custom CSS', 'lime-stock-watchlist' ) }
		>
			<textarea
				className="lswl-custom-css__textarea"
				value={ settings.style_custom_css || '' }
				onChange={ ( e ) => update( 'style_custom_css', e.target.value ) }
				rows={ 10 }
				placeholder={ '/* Add custom CSS for the notify form */\n.lswl-notify-form { }' }
				spellCheck={ false }
			/>
			<p className="components-base-control__help" style={ { marginTop: '8px' } }>
				{ __( 'Applied directly to the frontend. Use .lswl-notify-form as the root selector.', 'lime-stock-watchlist' ) }
			</p>
		</SettingsCard>
	);
}
