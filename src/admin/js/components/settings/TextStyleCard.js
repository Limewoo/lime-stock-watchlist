import { __ } from '@wordpress/i18n';
import SettingsCard from './SettingsCard';
import ColorField from './ColorField';
import { TextIcon } from './icons';

/**
 * @param {{settings: Object, update: Function}} props
 * @return {JSX.Element}
 */
export default function TextStyleCard( { settings, update } ) {
	return (
		<SettingsCard
			icon={ <TextIcon /> }
			title={ __( 'Text', 'lime-stock-watchlist' ) }
		>
			<ColorField
				label={ __( 'Heading color', 'lime-stock-watchlist' ) }
				help={ __( 'Leave empty to inherit the theme text color.', 'lime-stock-watchlist' ) }
				value={ settings.style_heading_color || '' }
				onChange={ ( v ) => update( 'style_heading_color', v ) }
				allowEmpty
			/>
			<ColorField
				label={ __( 'Success text', 'lime-stock-watchlist' ) }
				value={ settings.style_success_color || '#2a6028' }
				defaultValue="#2a6028"
				onChange={ ( v ) => update( 'style_success_color', v ) }
			/>
			<ColorField
				label={ __( 'Success background', 'lime-stock-watchlist' ) }
				value={ settings.style_success_bg || '#edf7ec' }
				defaultValue="#edf7ec"
				onChange={ ( v ) => update( 'style_success_bg', v ) }
			/>
			<ColorField
				label={ __( 'Success border', 'lime-stock-watchlist' ) }
				value={ settings.style_success_border || '#b3ddb0' }
				defaultValue="#b3ddb0"
				onChange={ ( v ) => update( 'style_success_border', v ) }
			/>
			<ColorField
				label={ __( 'Error text', 'lime-stock-watchlist' ) }
				value={ settings.style_error_color || '#8a2020' }
				defaultValue="#8a2020"
				onChange={ ( v ) => update( 'style_error_color', v ) }
			/>
			<ColorField
				label={ __( 'Error background', 'lime-stock-watchlist' ) }
				value={ settings.style_error_bg || '#fdf1f1' }
				defaultValue="#fdf1f1"
				onChange={ ( v ) => update( 'style_error_bg', v ) }
			/>
			<ColorField
				label={ __( 'Error border', 'lime-stock-watchlist' ) }
				value={ settings.style_error_border || '#e6b8b8' }
				defaultValue="#e6b8b8"
				onChange={ ( v ) => update( 'style_error_border', v ) }
			/>
		</SettingsCard>
	);
}
