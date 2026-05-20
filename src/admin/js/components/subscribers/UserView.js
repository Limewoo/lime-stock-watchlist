/**
 * User-based (flat) subscriber table using TanStack Table + Query.
 */
import { useState, useEffect, useRef } from '@wordpress/element';
import { CheckboxControl, Notice, Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import {
	useReactTable,
	getCoreRowModel,
	createColumnHelper,
	flexRender,
} from '@tanstack/react-table';
import {
	useQuery,
	useMutation,
	useQueryClient,
	keepPreviousData,
} from '@tanstack/react-query';
import { getSubscribers, deleteSubscriber, bulkDeleteSubscribers } from '../../api';
import StatusBadge from './StatusBadge';
import TablePagination from './TablePagination';

/** @return {number} Zero-based page index from the URL `paged` param. */
function getInitialPageIndex() {
	const p = parseInt( new URLSearchParams( window.location.search ).get( 'paged' ), 10 );
	return isNaN( p ) || p < 1 ? 0 : p - 1;
}

/**
 * @param {number} pageIndex Zero-based page index to sync to the URL.
 * @return {void}
 */
function syncPageToUrl( pageIndex ) {
	const urlObj = new URL( window.location.href );
	if ( pageIndex <= 0 ) {
		urlObj.searchParams.delete( 'paged' );
	} else {
		urlObj.searchParams.set( 'paged', String( pageIndex + 1 ) );
	}
	history.replaceState( null, '', urlObj.toString() );
}

/**
 * @param {string} dateStr
 * @return {string}
 */
function formatDate( dateStr ) {
	if ( ! dateStr ) return '—';
	const d = new Date( dateStr );
	return d.toLocaleDateString( undefined, { year: 'numeric', month: 'short', day: 'numeric' } );
}

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
 * @param {{ name: string, thumbnail: string, url: string }} props
 * @return {JSX.Element}
 */
function ProductCell( { name, thumbnail, url } ) {
	if ( ! url ) {
		return <span>{ name }</span>;
	}
	return (
		<a href={ url } target="_blank" rel="noreferrer" className="lswl-product-cell">
			{ thumbnail
				? <img src={ thumbnail } alt="" width={ 32 } height={ 32 } className="lswl-product-cell__thumb" />
				: <span className="lswl-product-cell__thumb lswl-product-cell__thumb--placeholder" aria-hidden="true" />
			}
			<span className="lswl-product-cell__name">{ name }</span>
		</a>
	);
}

const columnHelper = createColumnHelper();

/**
 * @param {{ productId: number, search: string, status: string }} props
 * @return {JSX.Element}
 */
export default function UserView( { productId, search, status } ) {
	const queryClient = useQueryClient();

	const [ pagination, setPagination ]     = useState( { pageIndex: getInitialPageIndex(), pageSize: 20 } );
	const [ rowSelection, setRowSelection ] = useState( {} );
	const [ notice, setNotice ]             = useState( '' );
	const [ error, setError ]               = useState( '' );
	const isFirstRender = useRef( true );

	const { pageIndex, pageSize } = pagination;

	// Reset to page 1 when filters change from parent (skip on first mount).
	useEffect( () => {
		if ( isFirstRender.current ) {
			isFirstRender.current = false;
			return;
		}
		setPagination( ( prev ) => ( { ...prev, pageIndex: 0 } ) );
		syncPageToUrl( 0 );
	}, [ search, status ] );

	const { data, isFetching, isLoading, isError } = useQuery( {
		queryKey: [ 'subscribers', 'users', { productId, search, status, pageIndex, pageSize } ],
		queryFn: () => getSubscribers( {
			view: 'users',
			page: pageIndex + 1,
			per_page: pageSize,
			status,
			search,
			product_id: productId,
		} ),
		placeholderData: keepPreviousData,
	} );

	function invalidateBoth() {
		queryClient.invalidateQueries( { queryKey: [ 'subscribers' ] } );
		queryClient.invalidateQueries( { queryKey: [ 'subscribers-stats' ] } );
	}

	const deleteMutation = useMutation( {
		mutationFn: ( id ) => deleteSubscriber( id ),
		onSuccess: () => {
			setNotice( __( 'Subscriber deleted.', 'lime-stock-watchlist' ) );
			invalidateBoth();
		},
		onError: () => setError( __( 'Could not delete subscriber.', 'lime-stock-watchlist' ) ),
	} );

	const bulkDeleteMutation = useMutation( {
		mutationFn: ( ids ) => bulkDeleteSubscribers( ids ),
		onSuccess: ( _, ids ) => {
			setRowSelection( {} );
			setNotice(
				sprintf(
					/* translators: %d: number of deleted subscribers */
					__( '%d subscriber(s) deleted.', 'lime-stock-watchlist' ),
					ids.length
				)
			);
			invalidateBoth();
		},
		onError: () => setError( __( 'Could not delete subscribers.', 'lime-stock-watchlist' ) ),
	} );

	const busy = deleteMutation.isPending || bulkDeleteMutation.isPending;

	function handleDelete( id ) {
		if ( ! window.confirm( __( 'Delete this subscriber?', 'lime-stock-watchlist' ) ) ) return;
		deleteMutation.mutate( id );
	}

	function handleBulkDelete() {
		const ids = Object.keys( rowSelection ).map( Number );
		if ( ids.length === 0 ) return;
		if ( ! window.confirm(
			sprintf(
				/* translators: %d: number of subscribers to delete */
				__( 'Delete %d subscriber(s)?', 'lime-stock-watchlist' ),
				ids.length
			)
		) ) return;
		bulkDeleteMutation.mutate( ids );
	}

	const showProductColumn = productId === 0;

	const columns = [
		columnHelper.display( {
			id: 'select',
			header: ( { table } ) => (
				<CheckboxControl
					__nextHasNoMarginBottom
					checked={ table.getIsAllPageRowsSelected() }
					onChange={ ( checked ) => table.toggleAllPageRowsSelected( checked ) }
					aria-label={ __( 'Select all on this page', 'lime-stock-watchlist' ) }
				/>
			),
			cell: ( { row } ) => (
				<CheckboxControl
					__nextHasNoMarginBottom
					checked={ row.getIsSelected() }
					onChange={ ( checked ) => row.toggleSelected( checked ) }
					aria-label={ row.original.email }
				/>
			),
		} ),
		columnHelper.accessor( 'email', {
			header: __( 'Email', 'lime-stock-watchlist' ),
			cell: ( info ) => <span className="lswl-table__email">{ info.getValue() }</span>,
		} ),
		columnHelper.accessor( 'name', {
			header: __( 'Name', 'lime-stock-watchlist' ),
			cell: ( info ) => <span className="lswl-table__name">{ info.getValue() || '—' }</span>,
		} ),
		...( showProductColumn ? [
			columnHelper.display( {
				id: 'product',
				header: __( 'Product', 'lime-stock-watchlist' ),
				cell: ( { row } ) => (
					<ProductCell
						name={ row.original.product_name }
						thumbnail={ row.original.product_thumbnail }
						url={ row.original.product_url }
					/>
				),
			} ),
		] : [] ),
		columnHelper.display( {
			id: 'status',
			header: __( 'Status', 'lime-stock-watchlist' ),
			cell: ( { row } ) => <StatusBadge subscriber={ row.original } />,
		} ),
		columnHelper.accessor( 'date_subscribed', {
			header: __( 'Date subscribed', 'lime-stock-watchlist' ),
			cell: ( info ) => <span className="lswl-table__date">{ formatDate( info.getValue() ) }</span>,
		} ),
		columnHelper.display( {
			id: 'actions',
			header: '',
			cell: ( { row } ) => (
				<button
					className="lswl-icon-btn lswl-icon-btn--danger"
					disabled={ busy }
					onClick={ () => handleDelete( row.original.id ) }
					aria-label={ __( 'Delete subscriber', 'lime-stock-watchlist' ) }
					title={ __( 'Delete', 'lime-stock-watchlist' ) }
				>
					<TrashIcon />
				</button>
			),
		} ),
	];

	const table = useReactTable( {
		data: data?.items ?? [],
		columns,
		pageCount: data?.pages ?? -1,
		state: { pagination, rowSelection },
		onPaginationChange: ( updater ) => {
			setPagination( ( prev ) => {
				const next = typeof updater === 'function' ? updater( prev ) : updater;
				syncPageToUrl( next.pageIndex );
				return next;
			} );
		},
		onRowSelectionChange: setRowSelection,
		getCoreRowModel: getCoreRowModel(),
		manualPagination: true,
		manualFiltering: true,
		getRowId: ( row ) => String( row.id ),
		enableRowSelection: true,
	} );

	const selectedIds = Object.keys( rowSelection ).map( Number );

	if ( isLoading ) {
		return (
			<div style={ { padding: '48px', textAlign: 'center' } }>
				<Spinner style={ { width: '32px', height: '32px' } } />
			</div>
		);
	}

	if ( isError ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ __( 'Failed to load subscribers.', 'lime-stock-watchlist' ) }
			</Notice>
		);
	}

	return (
		<div className="lswl-user-view">
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

			{ selectedIds.length > 0 && (
				<div className="lswl-subscribers__toolbar">
					<button
						className="lswl-btn lswl-btn--danger"
						disabled={ busy }
						onClick={ handleBulkDelete }
					>
						<TrashIcon />
						{ __( 'Delete selected', 'lime-stock-watchlist' ) }
					</button>
					<span className="lswl-selection-count">
						{ sprintf(
							/* translators: %d: number of selected rows */
							__( '%d selected', 'lime-stock-watchlist' ),
							selectedIds.length
						) }
					</span>
				</div>
			) }

			{ data?.items?.length === 0 && ! isFetching ? (
				<div className="lswl-empty">
					<div className="lswl-empty__icon">
						<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
							<path d="M22 12h-6l-2 3h-4l-2-3H2" />
							<path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z" />
						</svg>
					</div>
					<h2 className="lswl-empty__title">
						{ __( 'No subscribers found', 'lime-stock-watchlist' ) }
					</h2>
					<p className="lswl-empty__text">
						{ search || status !== 'all'
							? __( 'Try adjusting your filters.', 'lime-stock-watchlist' )
							: __( "When products go out of stock, customers can sign up here to be notified when they're back.", 'lime-stock-watchlist' )
						}
					</p>
				</div>
			) : (
				<div className={ `lswl-table-wrap${ isFetching ? ' lswl-table-wrap--fetching' : '' }` }>
					<table className="lswl-table">
						<thead>
							{ table.getHeaderGroups().map( ( headerGroup ) => (
								<tr key={ headerGroup.id }>
									{ headerGroup.headers.map( ( header ) => (
										<th
											key={ header.id }
											className={ header.column.id === 'select' ? 'lswl-table__check' : '' }
										>
											{ flexRender( header.column.columnDef.header, header.getContext() ) }
										</th>
									) ) }
								</tr>
							) ) }
						</thead>
						<tbody>
							{ table.getRowModel().rows.map( ( row ) => (
								<tr
									key={ row.id }
									className={ row.getIsSelected() ? 'is-checked' : '' }
								>
									{ row.getVisibleCells().map( ( cell ) => (
										<td
											key={ cell.id }
											className={ cell.column.id === 'select' ? 'lswl-table__check' : '' }
										>
											{ flexRender( cell.column.columnDef.cell, cell.getContext() ) }
										</td>
									) ) }
								</tr>
							) ) }
						</tbody>
					</table>
					<TablePagination
						pageIndex={ table.getState().pagination.pageIndex }
						pageCount={ table.getPageCount() }
						canPreviousPage={ table.getCanPreviousPage() }
						canNextPage={ table.getCanNextPage() }
						onPreviousPage={ () => table.previousPage() }
						onNextPage={ () => table.nextPage() }
						onFirstPage={ () => table.setPageIndex( 0 ) }
						onLastPage={ () => table.setPageIndex( table.getPageCount() - 1 ) }
						onGoToPage={ ( idx ) => table.setPageIndex( idx ) }
						totalItems={ data?.total ?? 0 }
					/>
				</div>
			) }
		</div>
	);
}
