import { __ } from '@wordpress/i18n';
import SettingsCard from './SettingsCard';
import { PaintBrushIcon } from './icons';

/**
 * @param {{label: string, help?: string, settingKey: string, settings: Object, update: Function}} props
 * @return {JSX.Element}
 */
function ColorField( { label, help, settingKey, settings, update } ) {
	const value = String( settings[ settingKey ] || '' );
	return (
		<div className="lswl-color-field">
			<label className="lswl-color-field__label">{ label }</label>
			<div className="lswl-color-field__row">
				<input
					type="color"
					className="lswl-color-field__swatch"
					value={ value }
					onChange={ ( e ) => update( settingKey, e.target.value ) }
				/>
				<input
					type="text"
					className="lswl-color-field__hex"
					value={ value }
					onChange={ ( e ) => update( settingKey, e.target.value ) }
					maxLength={ 7 }
					placeholder="#000000"
					spellCheck={ false }
				/>
			</div>
			{ help && <p className="components-base-control__help lswl-color-field__help">{ help }</p> }
		</div>
	);
}

/**
 * @param {{settings: Object, update: Function}} props
 * @return {JSX.Element}
 */
export default function FormAppearanceCard( { settings, update } ) {
	const radius = settings.style_btn_radius ?? 3;
	return (
		<SettingsCard
			icon={ <PaintBrushIcon /> }
			title={ __( 'Form Appearance', 'lime-stock-watchlist' ) }
		>
			<ColorField
				label={ __( 'Accent color', 'lime-stock-watchlist' ) }
				help={ __( 'Top border, button background, and input focus rings.', 'lime-stock-watchlist' ) }
				settingKey="style_accent_color"
				settings={ settings }
				update={ update }
			/>
			<ColorField
				label={ __( 'Button text color', 'lime-stock-watchlist' ) }
				settingKey="style_btn_text_color"
				settings={ settings }
				update={ update }
			/>
			<div className="lswl-color-field">
				<label className="lswl-color-field__label">
					{ __( 'Button border radius', 'lime-stock-watchlist' ) }
				</label>
				<div className="lswl-color-field__row">
					<input
						type="number"
						className="lswl-color-field__number"
						value={ radius }
						onChange={ ( e ) =>
							update( 'style_btn_radius', Math.max( 0, parseInt( e.target.value, 10 ) || 0 ) )
						}
						min={ 0 }
						max={ 100 }
					/>
					<span className="lswl-color-field__unit">px</span>
				</div>
				<p className="components-base-control__help lswl-color-field__help">
					{ __( 'Default: 3px. Set 0 for square, 50+ for pill shape.', 'lime-stock-watchlist' ) }
				</p>
			</div>
		</SettingsCard>
	);
}
