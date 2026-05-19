/**
 * Subscribers tab — table grouped by product with single + bulk delete.
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import {
	Button,
	CheckboxControl,
	Spinner,
	Notice,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { getSubscribers, deleteSubscriber, bulkDeleteSubscribers } from '../api';

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

	const allIds = groups.flatMap( ( g ) => g.subscribers.map( ( s ) => s.id ) );
	const allSelected = allIds.length > 0 && allIds.every( ( id ) => selected.has( id ) );

	/**
	 * Toggle select-all.
	 *
	 * @param {boolean} checked
	 */
	function toggleAll( checked ) {
		setSelected( checked ? new Set( allIds ) : new Set() );
	}

	/**
	 * Toggle a single subscriber.
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
	 * Delete a single subscriber by ID.
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
		setBusy( true );
		try {
			await bulkDeleteSubscribers( Array.from( selected ) );
			setSelected( new Set() );
			setNotice(
				// translators: %d: number of deleted subscribers
				sprintf( __( '%d subscriber(s) deleted.', 'lime-stock-watchlist' ), selected.size )
			);
			load();
		} catch {
			setError( __( 'Could not delete subscribers.', 'lime-stock-watchlist' ) );
		} finally {
			setBusy( false );
		}
	}

	if ( loading ) {
		return <Spinner />;
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

			{ groups.length === 0 && ! error && (
				<p>{ __( 'No subscribers yet.', 'lime-stock-watchlist' ) }</p>
			) }

			{ groups.length > 0 && (
				<>
					<div className="lswl-subscribers__bulk-actions">
						<Button
							variant="secondary"
							isDestructive
							disabled={ selected.size === 0 || busy }
							onClick={ handleBulkDelete }
						>
							{ __( 'Delete selected', 'lime-stock-watchlist' ) }
						</Button>
					</div>

					<table className="wp-list-table widefat fixed striped lswl-subscribers__table">
						<thead>
							<tr>
								<th className="check-column">
									<CheckboxControl
										checked={ allSelected }
										onChange={ toggleAll }
										aria-label={ __( 'Select all subscribers', 'lime-stock-watchlist' ) }
									/>
								</th>
								<th>{ __( 'Product', 'lime-stock-watchlist' ) }</th>
								<th>{ __( 'Name', 'lime-stock-watchlist' ) }</th>
								<th>{ __( 'Email', 'lime-stock-watchlist' ) }</th>
								<th>{ __( 'Date subscribed', 'lime-stock-watchlist' ) }</th>
								<th>{ __( 'Status', 'lime-stock-watchlist' ) }</th>
								<th>{ __( 'Actions', 'lime-stock-watchlist' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ groups.map( ( group ) =>
								group.subscribers.map( ( subscriber, i ) => (
									<tr key={ subscriber.id }>
										<td className="check-column">
											<CheckboxControl
												checked={ selected.has( subscriber.id ) }
												onChange={ ( checked ) => toggleOne( subscriber.id, checked ) }
												aria-label={ subscriber.email }
											/>
										</td>
										<td>{ i === 0 ? group.product_name : '' }</td>
										<td>{ subscriber.subscriber_name || '—' }</td>
										<td>{ subscriber.email }</td>
										<td>{ subscriber.date_subscribed }</td>
										<td>
											{ subscriber.unsubscribed
												? __( 'Unsubscribed', 'lime-stock-watchlist' )
												: subscriber.notified
													? __( 'Notified', 'lime-stock-watchlist' )
													: __( 'Waiting', 'lime-stock-watchlist' ) }
										</td>
										<td>
											<Button
												variant="link"
												isDestructive
												disabled={ busy }
												onClick={ () => handleDelete( subscriber.id ) }
											>
												{ __( 'Delete', 'lime-stock-watchlist' ) }
											</Button>
										</td>
									</tr>
								) )
							) }
						</tbody>
					</table>
				</>
			) }
		</div>
	);
}
