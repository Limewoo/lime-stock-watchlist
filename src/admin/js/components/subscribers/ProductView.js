/**
 * Product-based subscriber table (aggregate view) using TanStack Table + Query.
 */
import { useState, useEffect } from '@wordpress/element';
import { Notice, Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import {
	useReactTable,
	getCoreRowModel,
	createColumnHelper,
	flexRender,
} from '@tanstack/react-table';
import { useQuery, keepPreviousData } from '@tanstack/react-query';
import { getSubscribers } from '../../api';
import TablePagination from './TablePagination';

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
 * @param {{ search: string, onDrillDown: (productId: number, productName: string) => void }} props
 * @return {JSX.Element}
 */
export default function ProductView( { search, onDrillDown } ) {
	const [ pagination, setPagination ] = useState( { pageIndex: 0, pageSize: 20 } );

	const { pageIndex, pageSize } = pagination;

	// Reset to page 1 when search changes from parent
	useEffect( () => {
		setPagination( ( prev ) => ( { ...prev, pageIndex: 0 } ) );
	}, [ search ] );

	const { data, isFetching, isLoading, isError } = useQuery( {
		queryKey: [ 'subscribers', 'products', { search, pageIndex, pageSize } ],
		queryFn: () => getSubscribers( {
			view: 'products',
			page: pageIndex + 1,
			per_page: pageSize,
			search,
		} ),
		placeholderData: keepPreviousData,
	} );

	const columns = [
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
		columnHelper.accessor( 'subscriber_count', {
			header: __( 'Subscribers', 'lime-stock-watchlist' ),
			cell: ( info ) => (
				<span className="lswl-product-row__count">
					{ sprintf(
						/* translators: %d: number of subscribers */
						__( '%d', 'lime-stock-watchlist' ),
						info.getValue()
					) }
				</span>
			),
		} ),
		columnHelper.display( {
			id: 'actions',
			header: '',
			cell: ( { row } ) => (
				<button
					type="button"
					className="lswl-view-btn"
					onClick={ () => onDrillDown( row.original.product_id, row.original.product_name, row.original.product_url ) }
				>
					{ __( 'View Subscribers', 'lime-stock-watchlist' ) }
					<svg width="13" height="13" viewBox="0 0 16 16" fill="none" aria-hidden="true">
						<path d="M6 4l4 4-4 4" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
					</svg>
				</button>
			),
		} ),
	];

	const table = useReactTable( {
		data: data?.items ?? [],
		columns,
		pageCount: data?.pages ?? -1,
		state: { pagination },
		onPaginationChange: setPagination,
		getCoreRowModel: getCoreRowModel(),
		manualPagination: true,
		manualFiltering: true,
		getRowId: ( row ) => String( row.product_id ),
	} );

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
				{ __( 'Failed to load products.', 'lime-stock-watchlist' ) }
			</Notice>
		);
	}

	return (
		<div className="lswl-product-view">
			{ data?.items?.length === 0 && ! isFetching ? (
				<div className="lswl-empty">
					<div className="lswl-empty__icon">
						<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
							<rect x="3" y="3" width="7" height="7" rx="1" />
							<rect x="14" y="3" width="7" height="7" rx="1" />
							<rect x="3" y="14" width="7" height="7" rx="1" />
							<path d="M14 17.5h7M17.5 14v7" strokeWidth="2" strokeLinecap="round" />
						</svg>
					</div>
					<h2 className="lswl-empty__title">
						{ __( 'No products found', 'lime-stock-watchlist' ) }
					</h2>
					<p className="lswl-empty__text">
						{ search
							? __( 'Try adjusting your search.', 'lime-stock-watchlist' )
							: __( 'No subscribers yet across any products.', 'lime-stock-watchlist' )
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
										<th key={ header.id }>
											{ flexRender( header.column.columnDef.header, header.getContext() ) }
										</th>
									) ) }
								</tr>
							) ) }
						</thead>
						<tbody>
							{ table.getRowModel().rows.map( ( row ) => (
								<tr key={ row.id }>
									{ row.getVisibleCells().map( ( cell ) => (
										<td
											key={ cell.id }
											className={ cell.column.id === 'actions' ? 'lswl-table__actions' : '' }
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
