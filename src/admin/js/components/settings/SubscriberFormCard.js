import { SelectControl, TextControl, ToggleControl } from '@wordpress/components';
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
			<SelectControl
				label={ __( 'Display mode', 'lime-stock-watchlist' ) }
				value={ settings.form_display_mode ?? 'inline' }
				options={ [
					{ value: 'inline', label: __( 'Inline - Shown directly on the product page', 'lime-stock-watchlist' ) },
					{ value: 'popup',  label: __( 'Popup - Opens in a modal when triggered', 'lime-stock-watchlist' ) },
				] }
				onChange={ ( v ) => update( 'form_display_mode', v ) }
				__nextHasNoMarginBottom
			/>
			{ settings.form_display_mode === 'popup' && (
				<TextControl
					label={ __( 'Trigger button text', 'lime-stock-watchlist' ) }
					placeholder={ placeholders.popup_trigger_label }
					value={ settings.popup_trigger_label }
					onChange={ ( v ) => update( 'popup_trigger_label', v ) }
				/>
			) }
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Show on product archive pages', 'lime-stock-watchlist' ) }
				help={ __( 'Display the form on shop, category, and search result pages for out-of-stock simple products.', 'lime-stock-watchlist' ) }
				checked={ !! settings.show_on_archive }
				onChange={ ( v ) => update( 'show_on_archive', v ) }
			/>
			<TextControl
				className="lswl-field--section-start"
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
				className="lswl-field--section-start"
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
