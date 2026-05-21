import { ColorPicker, ColorIndicator, Dropdown, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Gutenberg-native colour field: compact trigger button opening a ColorPicker popover.
 *
 * @param {{
 *   label: string,
 *   help?: string,
 *   value: string,
 *   onChange: (hex: string) => void,
 *   defaultValue?: string,
 *   allowEmpty?: boolean,
 * }} props
 * @return {JSX.Element}
 */
export default function ColorField( {
	label,
	help,
	value,
	onChange,
	defaultValue = '',
	allowEmpty = false,
} ) {
	const displayValue = value || ( allowEmpty ? '' : '#000000' );

	// Show reset when value differs from its reset target.
	const resetTarget = allowEmpty ? '' : defaultValue;
	const showReset = defaultValue !== '' || allowEmpty
		? value !== resetTarget
		: false;

	return (
		<div className="lswl-color-field">
			<div className="lswl-color-field__row">
				<span className="lswl-color-field__label">{ label }</span>
				<div className="lswl-color-field__controls">
					{ showReset && (
						<Button
							variant="tertiary"
							size="small"
							onClick={ () => onChange( resetTarget ) }
							className="lswl-color-field__reset"
						>
							{ __( 'Reset', 'lime-stock-watchlist' ) }
						</Button>
					) }
					<Dropdown
						popoverProps={ { placement: 'bottom-end' } }
						renderToggle={ ( { isOpen, onToggle } ) => (
							<Button
								onClick={ onToggle }
								aria-expanded={ isOpen }
								className={ `lswl-color-field__trigger${ isOpen ? ' is-open' : '' }` }
							>
								{ displayValue
									? <ColorIndicator colorValue={ displayValue } />
									: <span className="lswl-color-field__empty-swatch" /> }
								<span className="lswl-color-field__hex">
									{ displayValue || __( '—', 'lime-stock-watchlist' ) }
								</span>
							</Button>
						) }
						renderContent={ () => (
							<div className="lswl-color-field__picker-wrap">
								<ColorPicker
									color={ displayValue || '#000000' }
									onChange={ ( color ) => {
										const hex = typeof color === 'string' ? color : color?.hex;
										if ( hex ) onChange( hex );
									} }
									enableAlpha={ false }
									copyFormat="hex"
								/>
							</div>
						) }
					/>
				</div>
			</div>
			{ help && <p className="components-base-control__help lswl-color-field__help">{ help }</p> }
		</div>
	);
}
