/**
 * Root admin app — branded header + tab navigation.
 */
import { TabPanel } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import SubscribersTab from './SubscribersTab';
import SettingsTab from './SettingsTab';

const TABS = [
	{
		name: 'subscribers',
		title: __( 'Subscribers', 'lime-stock-watchlist' ),
	},
	{
		name: 'settings',
		title: __( 'Settings', 'lime-stock-watchlist' ),
	},
];

function LeafIcon() {
	return (
		<svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
			<path d="M17 8C8 10 5.9 16.17 3.82 21.34L5.71 22l1-2.3A4.49 4.49 0 0 0 8 20C19 20 22 3 22 3c-1 2-8 2-13 7.79" />
			<path d="M5 21l1.5-5" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" fill="none" />
		</svg>
	);
}

/**
 * @return {JSX.Element}
 */
export default function App() {
	return (
		<div className="lswl-admin">
			<div className="lswl-admin__header">
				<div className="lswl-admin__logo-icon" aria-hidden="true">
					<LeafIcon />
				</div>
				<div className="lswl-admin__logo-text">
					<h1>{ __( 'Lime Stock Watchlist', 'lime-stock-watchlist' ) }</h1>
					<p>{ __( 'Back-in-stock notification management', 'lime-stock-watchlist' ) }</p>
				</div>
			</div>

			<div className="lswl-admin__tabs-container">
				<TabPanel tabs={ TABS }>
					{ ( tab ) => (
						<div className="lswl-admin__panel">
							{ tab.name === 'subscribers' && <SubscribersTab /> }
							{ tab.name === 'settings' && <SettingsTab /> }
						</div>
					) }
				</TabPanel>
			</div>
		</div>
	);
}
