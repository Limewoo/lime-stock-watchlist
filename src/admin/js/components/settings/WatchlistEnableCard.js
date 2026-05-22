import { ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import SettingsCard from './SettingsCard';
import { WatchlistIcon } from './icons';

/**
 * @param {{settings: Object, update: Function}} props
 * @return {JSX.Element}
 */
export default function WatchlistEnableCard( { settings, update } ) {
	return (
		<SettingsCard
			icon={ <WatchlistIcon /> }
			title={ __( 'Enable Stock Watchlist', 'lime-stock-watchlist' ) }
		>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Enable stock watchlist', 'lime-stock-watchlist' ) }
				help={ __( 'Show the notify form on out-of-stock product pages and allow subscriptions.', 'lime-stock-watchlist' ) }
				checked={ !! settings.notifications_enabled }
				onChange={ ( v ) => update( 'notifications_enabled', v ) }
			/>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Enable on backorder products', 'lime-stock-watchlist' ) }
				help={ __( 'Allow customers to join the watchlist when a product is available on backorder.', 'lime-stock-watchlist' ) }
				checked={ !! settings.allow_backorder_subscribe }
				onChange={ ( v ) => update( 'allow_backorder_subscribe', v ) }
			/>
		</SettingsCard>
	);
}
