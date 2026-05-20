/**
 * Product-based subscriber table (aggregate view) using TanStack Table + Query.
 */
import { useState } from '@wordpress/element';
import { Button, Notice, Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import {
	useReactTable,
	getCoreRowModel,
	createColumnHelper,
	flexRender,
} from '@tanstack/react-table';
import { useQuery, keepPreviousData } from '@tanstack/react-query';
import { getSubscribers } from '../../api';
import SubscriberFilters from './SubscriberFilters';
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
 * @param {{ onDrillDown: (productId: number, productName: string) => void }} props
 * @return {JSX.Element}
 */
export default function ProductView( { onDrillDown } ) {
	const [ search, setSearch ]         = useState( '' );
	const [ pagination, setPagination ] = useState( { pageIndex: 0, pageSize: 25 } );

	const { pageIndex, pageSize } = pagination;

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

	function handleSearchChange( value ) {
		setSearch( value );
		setPagination( ( prev ) => ( { ...prev, pageIndex: 0 } ) );
	}

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
				<Button
					variant="secondary"
					size="small"
					onClick={ () => onDrillDown( row.original.product_id, row.original.product_name ) }
				>
					{ __( 'View Subscribers', 'lime-stock-watchlist' ) }
				</Button>
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
			<SubscriberFilters
				view="products"
				search={ search }
				onSearchChange={ handleSearchChange }
				status="all"
				onStatusChange={ () => {} }
			/>

			{ data?.items?.length === 0 && ! isFetching ? (
				<div className="lswl-empty">
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
										<td key={ cell.id }>
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
						pageSize={ table.getState().pagination.pageSize }
						onPageSizeChange={ ( size ) => {
							table.setPageSize( size );
							table.setPageIndex( 0 );
						} }
						totalItems={ data?.total ?? 0 }
					/>
				</div>
			) }
		</div>
	);
}
