import { TextControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import SettingsCard from './SettingsCard';
import { FormIcon } from './icons';

/**
 * @param {{settings: Object, placeholders: Object, update: Function}} props
 * @return {JSX.Element}
 */
export default function SubscriberFormCard( { settings, placeholders, update } ) {
	return (
		<SettingsCard
			icon={ <FormIcon /> }
			title={ __( 'Subscriber Form', 'lime-stock-watchlist' ) }
		>
			<TextControl
				label={ __( 'Form title', 'lime-stock-watchlist' ) }
				placeholder={ placeholders.form_title }
				value={ settings.form_title }
				onChange={ ( v ) => update( 'form_title', v ) }
			/>
			<TextControl
				label={ __( 'Button label', 'lime-stock-watchlist' ) }
				placeholder={ placeholders.form_button_label }
				value={ settings.form_button_label }
				onChange={ ( v ) => update( 'form_button_label', v ) }
			/>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Show name field', 'lime-stock-watchlist' ) }
				help={ __( 'Display an optional name input alongside the email field.', 'lime-stock-watchlist' ) }
				checked={ !! settings.show_name_field }
				onChange={ ( v ) => update( 'show_name_field', v ) }
			/>
			{ settings.show_name_field && (
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Make name field required', 'lime-stock-watchlist' ) }
					help={ __( 'Customers must enter a name to complete the subscription.', 'lime-stock-watchlist' ) }
					checked={ !! settings.name_field_required }
					onChange={ ( v ) => update( 'name_field_required', v ) }
				/>
			) }
			<TextControl
				label={ __( 'Success message', 'lime-stock-watchlist' ) }
				placeholder={ placeholders.msg_success }
				value={ settings.msg_success }
				onChange={ ( v ) => update( 'msg_success', v ) }
			/>
			<TextControl
				label={ __( 'Already subscribed message', 'lime-stock-watchlist' ) }
				placeholder={ placeholders.msg_duplicate }
				value={ settings.msg_duplicate }
				onChange={ ( v ) => update( 'msg_duplicate', v ) }
			/>
			<TextControl
				label={ __( 'Error message', 'lime-stock-watchlist' ) }
				placeholder={ placeholders.msg_error }
				value={ settings.msg_error }
				onChange={ ( v ) => update( 'msg_error', v ) }
			/>
		</SettingsCard>
	);
}
