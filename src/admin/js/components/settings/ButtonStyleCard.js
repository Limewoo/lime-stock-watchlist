import { RangeControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import SettingsCard from './SettingsCard';
import ColorField from './ColorField';
import { ButtonIcon } from './icons';

/**
 * @param {{settings: Object, update: Function}} props
 * @return {JSX.Element}
 */
export default function ButtonStyleCard( { settings, update } ) {
	return (
		<SettingsCard
			icon={ <ButtonIcon /> }
			title={ __( 'Button', 'lime-stock-watchlist' ) }
		>
			<ColorField
				label={ __( 'Background color', 'lime-stock-watchlist' ) }
				value={ settings.style_accent_color || '#5d9e3f' }
				onChange={ ( v ) => update( 'style_accent_color', v ) }
				defaultValue="#5d9e3f"
			/>
			<ColorField
				label={ __( 'Text color', 'lime-stock-watchlist' ) }
				value={ settings.style_btn_text_color || '#ffffff' }
				onChange={ ( v ) => update( 'style_btn_text_color', v ) }
				defaultValue="#ffffff"
			/>
			<div className="lswl-range-wrap">
				<RangeControl
					__nextHasNoMarginBottom
					label={ __( 'Border radius', 'lime-stock-watchlist' ) }
					value={ settings.style_btn_radius ?? 3 }
					onChange={ ( v ) => update( 'style_btn_radius', v ?? 3 ) }
					min={ 0 }
					max={ 50 }
					step={ 1 }
					help={ __( '0 = square corners, 50 = pill shape.', 'lime-stock-watchlist' ) }
				/>
			</div>
			<div className="lswl-range-wrap">
				<RangeControl
					__nextHasNoMarginBottom
					label={ __( 'Vertical padding', 'lime-stock-watchlist' ) }
					value={ settings.style_btn_padding_v ?? 10 }
					onChange={ ( v ) => update( 'style_btn_padding_v', v ?? 10 ) }
					min={ 0 }
					max={ 40 }
					step={ 1 }
					help={ __( 'Top and bottom padding in px.', 'lime-stock-watchlist' ) }
				/>
			</div>
			<div className="lswl-range-wrap lswl-range-wrap--last">
				<RangeControl
					__nextHasNoMarginBottom
					label={ __( 'Horizontal padding', 'lime-stock-watchlist' ) }
					value={ settings.style_btn_padding_h ?? 20 }
					onChange={ ( v ) => update( 'style_btn_padding_h', v ?? 20 ) }
					min={ 0 }
					max={ 80 }
					step={ 1 }
					help={ __( 'Left and right padding in px.', 'lime-stock-watchlist' ) }
				/>
			</div>
		</SettingsCard>
	);
}
