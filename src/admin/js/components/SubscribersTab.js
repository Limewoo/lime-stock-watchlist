/**
 * Subscribers tab — stats bar, view toggle, user/product views.
 */
import { useState } from '@wordpress/element';
import { Button, Notice } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useQuery } from '@tanstack/react-query';
import { getSubscriberStats } from '../api';
import UserView from './subscribers/UserView';
import ProductView from './subscribers/ProductView';
import ProductDrillDown from './subscribers/ProductDrillDown';

/**
 * @param {{ stats: Object }} props
 * @return {JSX.Element}
 */
function StatsBar( { stats } ) {
	return (
		<div className="lswl-stats">
			<div className="lswl-stat">
				<div className="lswl-stat__value">{ stats.total }</div>
				<div className="lswl-stat__label">{ __( 'Total', 'lime-stock-watchlist' ) }</div>
			</div>
			<div className="lswl-stat lswl-stat--lime">
				<div className="lswl-stat__value">{ stats.watching }</div>
				<div className="lswl-stat__label">{ __( 'Watching', 'lime-stock-watchlist' ) }</div>
			</div>
			<div className="lswl-stat lswl-stat--purple">
				<div className="lswl-stat__value">{ stats.notifying }</div>
				<div className="lswl-stat__label">{ __( 'Notifying', 'lime-stock-watchlist' ) }</div>
			</div>
			<div className="lswl-stat lswl-stat--blue">
				<div className="lswl-stat__value">{ stats.notified }</div>
				<div className="lswl-stat__label">{ __( 'Notified', 'lime-stock-watchlist' ) }</div>
			</div>
			<div className="lswl-stat lswl-stat--grey">
				<div className="lswl-stat__value">{ stats.unsubscribed }</div>
				<div className="lswl-stat__label">{ __( 'Unsubscribed', 'lime-stock-watchlist' ) }</div>
			</div>
		</div>
	);
}

/**
 * @param {{ count: number }} props
 * @return {JSX.Element}
 */
function NotifyingNotice( { count } ) {
	const message = count === 1
		? __( '1 email is currently being sent via WooCommerce Action Scheduler. Reload the page in a while to see the updated status.', 'lime-stock-watchlist' )
		: sprintf(
			/* translators: %d: number of emails being sent */
			__( '%d emails are currently being sent via WooCommerce Action Scheduler. Reload the page to see updated statuses.', 'lime-stock-watchlist' ),
			count
		);

	return (
		<Notice status="warning" isDismissible={ false } className="lswl-notifying-notice">
			{ message }
		</Notice>
	);
}

/**
 * @param {{ view: string, onChange: Function, disabled: boolean }} props
 * @return {JSX.Element}
 */
function ViewToggle( { view, onChange, disabled } ) {
	return (
		<div className="lswl-view-toggle">
			<Button
				variant={ view === 'users' ? 'primary' : 'secondary' }
				onClick={ () => onChange( 'users' ) }
				disabled={ disabled }
			>
				{ __( 'By Subscriber', 'lime-stock-watchlist' ) }
			</Button>
			<Button
				variant={ view === 'products' ? 'primary' : 'secondary' }
				onClick={ () => onChange( 'products' ) }
				disabled={ disabled }
			>
				{ __( 'By Product', 'lime-stock-watchlist' ) }
			</Button>
		</div>
	);
}

/**
 * @return {JSX.Element}
 */
export default function SubscribersTab() {
	const [ view, setView ]         = useState( 'users' );
	const [ drillDown, setDrillDown ] = useState( null );

	const { data: stats } = useQuery( {
		queryKey: [ 'subscribers-stats' ],
		queryFn: getSubscriberStats,
		staleTime: 60_000,
	} );

	function handleViewChange( newView ) {
		setView( newView );
		setDrillDown( null );
	}

	function handleDrillDown( productId, productName ) {
		setDrillDown( { productId, productName } );
	}

	function handleBack() {
		setDrillDown( null );
	}

	return (
		<div className="lswl-subscribers">
			{ stats && <StatsBar stats={ stats } /> }

			{ stats?.notifying > 0 && <NotifyingNotice count={ stats.notifying } /> }

			{ ! drillDown && (
				<ViewToggle
					view={ view }
					onChange={ handleViewChange }
					disabled={ false }
				/>
			) }

			{ drillDown ? (
				<ProductDrillDown
					productId={ drillDown.productId }
					productName={ drillDown.productName }
					onBack={ handleBack }
				/>
			) : view === 'users' ? (
				<UserView productId={ 0 } />
			) : (
				<ProductView onDrillDown={ handleDrillDown } />
			) }
		</div>
	);
}
