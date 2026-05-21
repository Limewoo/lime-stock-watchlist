import { RangeControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import SettingsCard from './SettingsCard';
import ColorField from './ColorField';
import { InputIcon } from './icons';

/**
 * @param {{settings: Object, update: Function}} props
 * @return {JSX.Element}
 */
export default function InputStyleCard( { settings, update } ) {
	return (
		<SettingsCard
			icon={ <InputIcon /> }
			title={ __( 'Inputs', 'lime-stock-watchlist' ) }
		>
			<ColorField
				label={ __( 'Border color', 'lime-stock-watchlist' ) }
				value={ settings.style_input_border_color || '#e0e0e0' }
				onChange={ ( v ) => update( 'style_input_border_color', v ) }
				defaultValue="#e0e0e0"
			/>
			<div className="lswl-range-wrap">
				<RangeControl
					__nextHasNoMarginBottom
					label={ __( 'Border radius', 'lime-stock-watchlist' ) }
					value={ settings.style_input_radius ?? 5 }
					onChange={ ( v ) => update( 'style_input_radius', v ?? 5 ) }
					min={ 0 }
					max={ 20 }
					step={ 1 }
				/>
			</div>
			<div className="lswl-range-wrap">
				<RangeControl
					__nextHasNoMarginBottom
					label={ __( 'Vertical padding', 'lime-stock-watchlist' ) }
					value={ settings.style_input_padding_v ?? 10 }
					onChange={ ( v ) => update( 'style_input_padding_v', v ?? 10 ) }
					min={ 0 }
					max={ 30 }
					step={ 1 }
					help={ __( 'Top and bottom padding in px.', 'lime-stock-watchlist' ) }
				/>
			</div>
			<div className="lswl-range-wrap lswl-range-wrap--last">
				<RangeControl
					__nextHasNoMarginBottom
					label={ __( 'Horizontal padding', 'lime-stock-watchlist' ) }
					value={ settings.style_input_padding_h ?? 14 }
					onChange={ ( v ) => update( 'style_input_padding_h', v ?? 14 ) }
					min={ 0 }
					max={ 40 }
					step={ 1 }
					help={ __( 'Left and right padding in px.', 'lime-stock-watchlist' ) }
				/>
			</div>
		</SettingsCard>
	);
}
