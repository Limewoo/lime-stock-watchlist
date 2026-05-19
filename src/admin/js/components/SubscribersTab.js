/**
 * Subscribers tab — stats bar, product group cards, status badges, bulk delete.
 */
import { useState, useEffect, useCallback, useMemo } from '@wordpress/element';
import { CheckboxControl, Spinner, Notice } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { getSubscribers, deleteSubscriber, bulkDeleteSubscribers } from '../api';

/**
 * Format a UTC datetime string to a readable local date.
 *
 * @param {string} dateStr
 * @return {string}
 */
function formatDate( dateStr ) {
	if ( ! dateStr ) return '—';
	const d = new Date( dateStr );
	return d.toLocaleDateString( undefined, { year: 'numeric', month: 'short', day: 'numeric' } );
}

/**
 * Status badge component.
 *
 * @param {{subscriber: Object}} props
 * @return {JSX.Element}
 */
function StatusBadge( { subscriber } ) {
	if ( subscriber.unsubscribed ) {
		return (
			<span className="lswl-badge lswl-badge--unsubscribed">
				<span className="lswl-badge__dot" />
				{ __( 'Unsubscribed', 'lime-stock-watchlist' ) }
			</span>
		);
	}
	if ( subscriber.notified ) {
		return (
			<span className="lswl-badge lswl-badge--notified">
				<span className="lswl-badge__dot" />
				{ __( 'Notified', 'lime-stock-watchlist' ) }
			</span>
		);
	}
	return (
		<span className="lswl-badge lswl-badge--waiting">
			<span className="lswl-badge__dot" />
			{ __( 'Waiting', 'lime-stock-watchlist' ) }
		</span>
	);
}

/**
 * Trash icon SVG.
 *
 * @return {JSX.Element}
 */
function TrashIcon() {
	return (
		<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
			<polyline points="3 6 5 6 21 6" />
			<path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
			<path d="M10 11v6M14 11v6" />
			<path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2" />
		</svg>
	);
}

/**
 * Inbox empty icon SVG.
 *
 * @return {JSX.Element}
 */
function EmptyIcon() {
	return (
		<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
			<path d="M22 12h-6l-2 3h-4l-2-3H2" />
			<path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z" />
		</svg>
	);
}

/**
 * @return {JSX.Element}
 */
export default function SubscribersTab() {
	const [ groups, setGroups ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );
	const [ notice, setNotice ] = useState( '' );
	const [ selected, setSelected ] = useState( new Set() );
	const [ busy, setBusy ] = useState( false );

	const load = useCallback( () => {
		setLoading( true );
		setError( '' );
		getSubscribers()
			.then( setGroups )
			.catch( () => setError( __( 'Failed to load subscribers.', 'lime-stock-watchlist' ) ) )
			.finally( () => setLoading( false ) );
	}, [] );

	useEffect( () => {
		load();
	}, [ load ] );

	const allIds = useMemo(
		() => groups.flatMap( ( g ) => g.subscribers.map( ( s ) => s.id ) ),
		[ groups ]
	);

	const allSelected = allIds.length > 0 && allIds.every( ( id ) => selected.has( id ) );

	const stats = useMemo( () => {
		const all = groups.flatMap( ( g ) => g.subscribers );
		return {
			total:        all.length,
			waiting:      all.filter( ( s ) => ! s.notified && ! s.unsubscribed ).length,
			notified:     all.filter( ( s ) => s.notified && ! s.unsubscribed ).length,
			unsubscribed: all.filter( ( s ) => s.unsubscribed ).length,
		};
	}, [ groups ] );

	/**
	 * Toggle select-all.
	 *
	 * @param {boolean} checked
	 */
	function toggleAll( checked ) {
		setSelected( checked ? new Set( allIds ) : new Set() );
	}

	/**
	 * Toggle a single subscriber row.
	 *
	 * @param {number}  id
	 * @param {boolean} checked
	 */
	function toggleOne( id, checked ) {
		setSelected( ( prev ) => {
			const next = new Set( prev );
			checked ? next.add( id ) : next.delete( id );
			return next;
		} );
	}

	/**
	 * Delete a single subscriber.
	 *
	 * @param {number} id
	 */
	async function handleDelete( id ) {
		setBusy( true );
		try {
			await deleteSubscriber( id );
			setSelected( ( prev ) => {
				const next = new Set( prev );
				next.delete( id );
				return next;
			} );
			setNotice( __( 'Subscriber deleted.', 'lime-stock-watchlist' ) );
			load();
		} catch {
			setError( __( 'Could not delete subscriber.', 'lime-stock-watchlist' ) );
		} finally {
			setBusy( false );
		}
	}

	/**
	 * Bulk delete selected subscribers.
	 */
	async function handleBulkDelete() {
		if ( selected.size === 0 ) return;
		const count = selected.size;
		setBusy( true );
		try {
			await bulkDeleteSubscribers( Array.from( selected ) );
			setSelected( new Set() );
			setNotice(
				sprintf(
					/* translators: %d: number of deleted subscribers */
					__( '%d subscriber(s) deleted.', 'lime-stock-watchlist' ),
					count
				)
			);
			load();
		} catch {
			setError( __( 'Could not delete subscribers.', 'lime-stock-watchlist' ) );
		} finally {
			setBusy( false );
		}
	}

	if ( loading ) {
		return (
			<div style={ { padding: '48px', textAlign: 'center' } }>
				<Spinner style={ { width: '32px', height: '32px' } } />
			</div>
		);
	}

	return (
		<div className="lswl-subscribers">
			{ error && (
				<Notice status="error" isDismissible onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }
			{ notice && (
				<Notice status="success" isDismissible onRemove={ () => setNotice( '' ) }>
					{ notice }
				</Notice>
			) }

			{ groups.length > 0 && (
				<>
					{ /* Stats bar */ }
					<div className="lswl-stats">
						<div className="lswl-stat">
							<div className="lswl-stat__value">{ stats.total }</div>
							<div className="lswl-stat__label">{ __( 'Total', 'lime-stock-watchlist' ) }</div>
						</div>
						<div className="lswl-stat lswl-stat--lime">
							<div className="lswl-stat__value">{ stats.waiting }</div>
							<div className="lswl-stat__label">{ __( 'Waiting', 'lime-stock-watchlist' ) }</div>
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

					{ /* Toolbar */ }
					<div className="lswl-subscribers__toolbar">
						<button
							className="lswl-btn lswl-btn--danger"
							disabled={ selected.size === 0 || busy }
							onClick={ handleBulkDelete }
						>
							<TrashIcon />
							{ __( 'Delete selected', 'lime-stock-watchlist' ) }
						</button>
						{ selected.size > 0 && (
							<span className="lswl-selection-count">
								{ sprintf(
									/* translators: %d: number of selected rows */
									__( '%d selected', 'lime-stock-watchlist' ),
									selected.size
								) }
							</span>
						) }
					</div>

					{ /* Product group cards */ }
					{ groups.map( ( group ) => (
						<div key={ group.product_id } className="lswl-product-group">
							<div className="lswl-product-group__header">
								<span className="lswl-product-group__name">
									{ group.product_name }
								</span>
								<span className="lswl-product-group__count">
									{ sprintf(
										/* translators: %d: subscriber count */
										__( '%d subscriber(s)', 'lime-stock-watchlist' ),
										group.subscribers.length
									) }
								</span>
							</div>

							<table className="lswl-table">
								<thead>
									<tr>
										<th className="lswl-table__check">
											<CheckboxControl
												checked={ group.subscribers.every( ( s ) => selected.has( s.id ) ) }
												onChange={ ( checked ) =>
													group.subscribers.forEach( ( s ) => toggleOne( s.id, checked ) )
												}
												aria-label={ __( 'Select all in this product', 'lime-stock-watchlist' ) }
											/>
										</th>
										<th>{ __( 'Name', 'lime-stock-watchlist' ) }</th>
										<th>{ __( 'Email', 'lime-stock-watchlist' ) }</th>
										<th>{ __( 'Date subscribed', 'lime-stock-watchlist' ) }</th>
										<th>{ __( 'Status', 'lime-stock-watchlist' ) }</th>
										<th />
									</tr>
								</thead>
								<tbody>
									{ group.subscribers.map( ( subscriber ) => (
										<tr
											key={ subscriber.id }
											className={ selected.has( subscriber.id ) ? 'is-checked' : '' }
										>
											<td className="lswl-table__check">
												<CheckboxControl
													checked={ selected.has( subscriber.id ) }
													onChange={ ( checked ) => toggleOne( subscriber.id, checked ) }
													aria-label={ subscriber.email }
												/>
											</td>
											<td>
												{ subscriber.subscriber_name || (
													<span className="lswl-table__muted">—</span>
												) }
											</td>
											<td className="lswl-table__email">
												{ subscriber.email }
											</td>
											<td className="lswl-table__date">
												{ formatDate( subscriber.date_subscribed ) }
											</td>
											<td>
												<StatusBadge subscriber={ subscriber } />
											</td>
											<td>
												<button
													className="lswl-icon-btn lswl-icon-btn--danger"
													disabled={ busy }
													onClick={ () => handleDelete( subscriber.id ) }
													aria-label={ __( 'Delete subscriber', 'lime-stock-watchlist' ) }
													title={ __( 'Delete', 'lime-stock-watchlist' ) }
												>
													<TrashIcon />
												</button>
											</td>
										</tr>
									) ) }
								</tbody>
							</table>
						</div>
					) ) }
				</>
			) }

			{ groups.length === 0 && ! error && (
				<div className="lswl-empty">
					<div className="lswl-empty__icon">
						<EmptyIcon />
					</div>
					<h2 className="lswl-empty__title">
						{ __( 'No subscribers yet', 'lime-stock-watchlist' ) }
					</h2>
					<p className="lswl-empty__text">
						{ __( 'When products go out of stock, customers can sign up here to be notified when they\'re back.', 'lime-stock-watchlist' ) }
					</p>
				</div>
			) }
		</div>
	);
}
