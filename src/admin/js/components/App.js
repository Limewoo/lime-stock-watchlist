/**
 * Root admin app — page header + tab navigation.
 */
import { useState, useRef, useCallback } from '@wordpress/element';
import { TabPanel } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import SubscribersTab from './SubscribersTab';
import SettingsTab from './SettingsTab';
import StyleTab from './StyleTab';
import SaveBar from './SaveBar';

const TAB_NAMES = [ 'subscribers', 'settings', 'style' ];

const TABS = [
	{
		name: 'subscribers',
		title: __( 'Subscribers', 'lime-stock-watchlist' ),
	},
	{
		name: 'settings',
		title: __( 'Settings', 'lime-stock-watchlist' ),
	},
	{
		name: 'style',
		title: __( 'Style', 'lime-stock-watchlist' ),
	},
];

function getInitialTab() {
	const tab = new URLSearchParams( window.location.search ).get( 'tab' );
	return TAB_NAMES.includes( tab ) ? tab : 'subscribers';
}

/**
 * @param {string} tabName
 * @return {void}
 */
function syncTabToUrl( tabName ) {
	const urlObj = new URL( window.location.href );
	if ( tabName === 'subscribers' ) {
		urlObj.searchParams.delete( 'tab' );
	} else {
		urlObj.searchParams.set( 'tab', tabName );
	}
	history.replaceState( null, '', urlObj.toString() );
}

/**
 * @return {JSX.Element}
 */
export default function App() {
	const [ activeTab, setActiveTab ] = useState( getInitialTab() );
	const [ saving, setSaving ] = useState( false );
	const [ saved, setSaved ] = useState( false );
	const saveHandlerRef = useRef( null );

	const registerSave = useCallback( ( fn ) => {
		saveHandlerRef.current = fn;
	}, [] );

	async function handleSave() {
		if ( saveHandlerRef.current ) {
			await saveHandlerRef.current();
		}
	}

	function onSelect( tabName ) {
		setActiveTab( tabName );
		syncTabToUrl( tabName );
	}

	const showSaveBar = activeTab === 'settings' || activeTab === 'style';

	return (
		<div className="lswl-admin">
			<div className="lswl-admin__header">
				<div className="lswl-admin__header-content">
					<h1 className="lswl-admin__title">
						{ __( 'Lime Stock Watchlist', 'lime-stock-watchlist' ) }
					</h1>
					{ showSaveBar && (
						<SaveBar
							onSave={ handleSave }
							saving={ saving }
							saved={ saved }
							className="lswl-settings__save-bar--header"
						/>
					) }
				</div>
			</div>

			<div className="lswl-admin__tabs-container">
				<TabPanel
					tabs={ TABS }
					initialTabName={ getInitialTab() }
					onSelect={ onSelect }
				>
					{ ( tab ) => (
						<div className="lswl-admin__panel">
							{ tab.name === 'subscribers' && <SubscribersTab /> }
							{ tab.name === 'settings' && (
								<SettingsTab
									registerSave={ registerSave }
									saving={ saving }
									saved={ saved }
									setSaving={ setSaving }
									setSaved={ setSaved }
								/>
							) }
							{ tab.name === 'style' && (
								<StyleTab
									registerSave={ registerSave }
									saving={ saving }
									saved={ saved }
									setSaving={ setSaving }
									setSaved={ setSaved }
								/>
							) }
						</div>
					) }
				</TabPanel>
			</div>
		</div>
	);
}
