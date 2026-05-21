/**
 * Shared save button bar — used in the page header and at the bottom of settings tabs.
 *
 * @param {Object}   props
 * @param {Function} props.onSave
 * @param {boolean}  props.saving
 * @param {boolean}  props.saved
 * @param {string}   [props.className]
 */
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function SaveBar( { onSave, saving, saved, className = '' } ) {
	return (
		<div className={ `lswl-settings__save-bar${ className ? ` ${ className }` : '' }` }>
			<Button
				variant="primary"
				onClick={ onSave }
				isBusy={ saving }
				disabled={ saving }
			>
				{ __( 'Save Changes', 'lime-stock-watchlist' ) }
			</Button>
			{ saved && (
				<span className="lswl-settings__saved-msg">
					{ __( 'Settings saved', 'lime-stock-watchlist' ) }
				</span>
			) }
		</div>
	);
}
