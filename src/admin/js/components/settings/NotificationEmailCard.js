import { TextControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import SettingsCard from './SettingsCard';
import { MailIcon } from './icons';
import RichTextEditor from './RichTextEditor';

/**
 * @param {{settings: Object, placeholders: Object, update: Function}} props
 * @return {JSX.Element}
 */
export default function NotificationEmailCard( { settings, placeholders, update } ) {
	return (
		<SettingsCard
			icon={ <MailIcon /> }
			title={ __( 'Back-in-Stock Notification Email', 'lime-stock-watchlist' ) }
		>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Send notification when product is back in stock', 'lime-stock-watchlist' ) }
				help={ __( 'Automatically email subscribers when a product comes back in stock.', 'lime-stock-watchlist' ) }
				checked={ !! settings.notification_email_enabled }
				onChange={ ( v ) => update( 'notification_email_enabled', v ) }
			/>
			{ settings.notification_email_enabled && (
				<>
					<TextControl
						className="lswl-field--section-start"
						label={ __( 'Email subject', 'lime-stock-watchlist' ) }
						placeholder={ placeholders.email_subject }
						value={ settings.email_subject }
						onChange={ ( v ) => update( 'email_subject', v ) }
					/>
					<RichTextEditor
						id="lswl-rte-notification-body"
						label={ __( 'Email body', 'lime-stock-watchlist' ) }
						help={ __( 'Shortcodes: {site_name}, {product_name}, {product_url}, {subscriber_name}, {subscriber_email}', 'lime-stock-watchlist' ) }
						value={ settings.email_body }
						onChange={ ( v ) => update( 'email_body', v ) }
					/>
					<TextControl
						label={ __( 'Email footer', 'lime-stock-watchlist' ) }
						placeholder={ placeholders.email_footer }
						value={ settings.email_footer }
						onChange={ ( v ) => update( 'email_footer', v ) }
					/>
				</>
			) }
		</SettingsCard>
	);
}
