import { TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import SettingsCard from './SettingsCard';
import { ConfigIcon } from './icons';

/**
 * @param {{settings: Object, placeholders: Object, update: Function}} props
 * @return {JSX.Element}
 */
export default function EmailConfigCard( { settings, placeholders, update } ) {
	return (
		<SettingsCard
			icon={ <ConfigIcon /> }
			title={ __( 'Email Configuration', 'lime-stock-watchlist' ) }
		>
			<TextControl
				label={ __( 'From name', 'lime-stock-watchlist' ) }
				help={ __( 'Sender name used in all notification emails. Defaults to site name.', 'lime-stock-watchlist' ) }
				placeholder={ placeholders.from_name }
				value={ settings.from_name }
				onChange={ ( v ) => update( 'from_name', v ) }
			/>
			<TextControl
				label={ __( 'From email', 'lime-stock-watchlist' ) }
				help={ __( 'Sender address used in all notification emails. Defaults to admin email.', 'lime-stock-watchlist' ) }
				type="email"
				placeholder={ placeholders.from_email }
				value={ settings.from_email }
				onChange={ ( v ) => update( 'from_email', v ) }
			/>
		</SettingsCard>
	);
}
