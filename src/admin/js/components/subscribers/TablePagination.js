/**
 * Pagination controls for TanStack Table instances.
 */
import { Button, SelectControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

/**
 * @param {{
 *   pageIndex: number,
 *   pageCount: number,
 *   canPreviousPage: boolean,
 *   canNextPage: boolean,
 *   onPreviousPage: Function,
 *   onNextPage: Function,
 *   onFirstPage: Function,
 *   onLastPage: Function,
 *   pageSize: number,
 *   onPageSizeChange: Function,
 *   totalItems: number,
 * }} props
 * @return {JSX.Element}
 */
export default function TablePagination( {
	pageIndex,
	pageCount,
	canPreviousPage,
	canNextPage,
	onPreviousPage,
	onNextPage,
	onFirstPage,
	onLastPage,
	pageSize,
	onPageSizeChange,
	totalItems,
} ) {
	const currentPage = pageIndex + 1;
	const totalPages  = pageCount > 0 ? pageCount : 1;

	return (
		<div className="lswl-pagination">
			<Button
				variant="tertiary"
				onClick={ onFirstPage }
				disabled={ ! canPreviousPage }
				aria-label={ __( 'First page', 'lime-stock-watchlist' ) }
			>
				{ '«' }
			</Button>
			<Button
				variant="tertiary"
				onClick={ onPreviousPage }
				disabled={ ! canPreviousPage }
				aria-label={ __( 'Previous page', 'lime-stock-watchlist' ) }
			>
				{ '‹' }
			</Button>
			<span className="lswl-pagination__info">
				{ sprintf(
					/* translators: 1: current page, 2: total pages */
					__( 'Page %1$d of %2$d', 'lime-stock-watchlist' ),
					currentPage,
					totalPages
				) }
			</span>
			<Button
				variant="tertiary"
				onClick={ onNextPage }
				disabled={ ! canNextPage }
				aria-label={ __( 'Next page', 'lime-stock-watchlist' ) }
			>
				{ '›' }
			</Button>
			<Button
				variant="tertiary"
				onClick={ onLastPage }
				disabled={ ! canNextPage }
				aria-label={ __( 'Last page', 'lime-stock-watchlist' ) }
			>
				{ '»' }
			</Button>
			<SelectControl
				__nextHasNoMarginBottom
				className="lswl-pagination__per-page"
				value={ String( pageSize ) }
				options={ [
					{ label: '10', value: '10' },
					{ label: '25', value: '25' },
					{ label: '50', value: '50' },
					{ label: '100', value: '100' },
				] }
				onChange={ ( val ) => onPageSizeChange( Number( val ) ) }
				aria-label={ __( 'Rows per page', 'lime-stock-watchlist' ) }
			/>
			<span className="lswl-pagination__total">
				{ sprintf(
					/* translators: %d: total number of items */
					__( '%d total', 'lime-stock-watchlist' ),
					totalItems
				) }
			</span>
		</div>
	);
}
