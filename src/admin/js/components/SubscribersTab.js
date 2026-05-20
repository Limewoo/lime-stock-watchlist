/**
 * Subscribers tab — stats bar, unified controls row, user/product views.
 */
import { useState, useRef } from '@wordpress/element';
import { Button, Notice } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useQuery } from '@tanstack/react-query';
import { getSubscriberStats } from '../api';
import UserView from './subscribers/UserView';
import ProductView from './subscribers/ProductView';
import ProductDrillDown from './subscribers/ProductDrillDown';

const STATUS_OPTIONS = [
	{ label: __( 'All Statuses', 'lime-stock-watchlist' ), value: 'all' },
	{ label: __( 'Watching', 'lime-stock-watchlist' ),     value: 'watching' },
	{ label: __( 'Notifying', 'lime-stock-watchlist' ),    value: 'notifying' },
	{ label: __( 'Notified', 'lime-stock-watchlist' ),     value: 'notified' },
	{ label: __( 'Unsubscribed', 'lime-stock-watchlist' ), value: 'unsubscribed' },
];

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
 * @return {JSX.Element}
 */
export default function SubscribersTab() {
	const [ view, setView ]           = useState( 'users' );
	const [ drillDown, setDrillDown ] = useState( null );
	const [ inputValue, setInputValue ] = useState( '' );
	const [ search, setSearch ]         = useState( '' );
	const [ status, setStatus ]         = useState( 'all' );
	const debounceRef = useRef( null );

	const { data: stats } = useQuery( {
		queryKey: [ 'subscribers-stats' ],
		queryFn: getSubscriberStats,
		staleTime: 60_000,
	} );

	function clearPagedParam() {
		const urlObj = new URL( window.location.href );
		urlObj.searchParams.delete( 'paged' );
		history.replaceState( null, '', urlObj.toString() );
	}

	function handleViewChange( newView ) {
		setView( newView );
		setDrillDown( null );
		setInputValue( '' );
		setSearch( '' );
		setStatus( 'all' );
		clearPagedParam();
	}

	function handleSearchInput( value ) {
		setInputValue( value );
		clearTimeout( debounceRef.current );
		debounceRef.current = setTimeout( () => setSearch( value ), 300 );
	}

	function handleStatusChange( value ) {
		setStatus( value );
	}

	function handleDrillDown( productId, productName, productUrl ) {
		setDrillDown( { productId, productName, productUrl } );
		clearPagedParam();
	}

	function handleBack() {
		setDrillDown( null );
		clearPagedParam();
	}

	const searchPlaceholder = view === 'products'
		? __( 'Search products…', 'lime-stock-watchlist' )
		: __( 'Search by email…', 'lime-stock-watchlist' );

	return (
		<div className="lswl-subscribers">
			{ stats && <StatsBar stats={ stats } /> }
			{ stats?.notifying > 0 && <NotifyingNotice count={ stats.notifying } /> }

			{ /* Combined controls row — hidden when drilled in */ }
			{ ! drillDown && (
				<div className="lswl-controls-row">
					<div className="lswl-controls-row__toggle">
						<Button
							variant={ view === 'users' ? 'primary' : 'secondary' }
							onClick={ () => handleViewChange( 'users' ) }
						>
							{ __( 'By Subscriber', 'lime-stock-watchlist' ) }
						</Button>
						<Button
							variant={ view === 'products' ? 'primary' : 'secondary' }
							onClick={ () => handleViewChange( 'products' ) }
						>
							{ __( 'By Product', 'lime-stock-watchlist' ) }
						</Button>
					</div>

					<div className="lswl-controls-row__filters">
						<input
							type="search"
							className="lswl-filter-search"
							placeholder={ searchPlaceholder }
							value={ inputValue }
							onChange={ ( e ) => handleSearchInput( e.target.value ) }
						/>
						{ view === 'users' && (
							<select
								className="lswl-filter-select"
								value={ status }
								onChange={ ( e ) => handleStatusChange( e.target.value ) }
								aria-label={ __( 'Filter by status', 'lime-stock-watchlist' ) }
							>
								{ STATUS_OPTIONS.map( ( opt ) => (
									<option key={ opt.value } value={ opt.value }>{ opt.label }</option>
								) ) }
							</select>
						) }
					</div>
				</div>
			) }

			{ drillDown ? (
				<ProductDrillDown
					productId={ drillDown.productId }
					productName={ drillDown.productName }
					productUrl={ drillDown.productUrl }
					onBack={ handleBack }
				/>
			) : view === 'users' ? (
				<UserView productId={ 0 } search={ search } status={ status } />
			) : (
				<ProductView search={ search } onDrillDown={ handleDrillDown } />
			) }
		</div>
	);
}
