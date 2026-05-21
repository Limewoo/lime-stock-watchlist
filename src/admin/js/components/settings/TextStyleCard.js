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
		</SettingsCard>
	);
}
