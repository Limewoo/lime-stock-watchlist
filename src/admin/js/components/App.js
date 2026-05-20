/**
 * Root admin app — page header + tab navigation.
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

/**
 * @return {JSX.Element}
 */
export default function App() {
	return (
		<div className="lswl-admin">
			<div className="lswl-admin__header">
				<h1 className="lswl-admin__title">
					{ __( 'Lime Stock Watchlist', 'lime-stock-watchlist' ) }
				</h1>
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
