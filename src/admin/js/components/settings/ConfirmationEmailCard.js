import { TextControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import SettingsCard from './SettingsCard';
import { MailCheckIcon } from './icons';
import RichTextEditor from './RichTextEditor';

/**
 * @param {{settings: Object, placeholders: Object, update: Function}} props
 * @return {JSX.Element}
 */
export default function ConfirmationEmailCard( { settings, placeholders, update } ) {
	return (
		<SettingsCard
			icon={ <MailCheckIcon /> }
			title={ __( 'Subscription Confirmation Email', 'lime-stock-watchlist' ) }
		>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Send confirmation email to subscriber', 'lime-stock-watchlist' ) }
				help={ __( 'Email the customer immediately after they join the waitlist.', 'lime-stock-watchlist' ) }
				checked={ !! settings.confirmation_email_enabled }
				onChange={ ( v ) => update( 'confirmation_email_enabled', v ) }
			/>
			{ settings.confirmation_email_enabled && (
				<>
					<TextControl
						className="lswl-field--section-start"
						label={ __( 'Email subject', 'lime-stock-watchlist' ) }
						placeholder={ placeholders.confirmation_email_subject }
						value={ settings.confirmation_email_subject }
						onChange={ ( v ) => update( 'confirmation_email_subject', v ) }
					/>
					<RichTextEditor
						id="lswl-rte-confirmation-body"
						label={ __( 'Email body', 'lime-stock-watchlist' ) }
						help={ __( 'Shortcodes: {site_name}, {product_name}, {subscriber_name}, {subscriber_email}', 'lime-stock-watchlist' ) }
						value={ settings.confirmation_email_body }
						onChange={ ( v ) => update( 'confirmation_email_body', v ) }
					/>
				</>
			) }
		</SettingsCard>
	);
}
