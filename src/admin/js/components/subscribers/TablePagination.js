/**
 * Number-based pagination for TanStack Table instances.
 */
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
 *   onGoToPage: (pageIndex: number) => void,
 *   totalItems: number,
 * }} props
 * @return {JSX.Element|null}
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
	onGoToPage,
	totalItems,
} ) {
	if ( pageCount <= 0 ) return null;

	const currentPage = pageIndex + 1;
	const totalPages  = pageCount;

	const delta = 2;
	const range = [];
	for ( let i = 1; i <= totalPages; i++ ) {
		if ( i === 1 || i === totalPages || ( i >= currentPage - delta && i <= currentPage + delta ) ) {
			range.push( i );
		}
	}

	const rangeWithDots = [];
	let l;
	for ( const i of range ) {
		if ( l ) {
			if ( i - l === 2 ) {
				rangeWithDots.push( l + 1 );
			} else if ( i - l > 2 ) {
				rangeWithDots.push( '…' );
			}
		}
		rangeWithDots.push( i );
		l = i;
	}

	return (
		<div className="lswl-pagination">
			<span className="lswl-pagination__total">
				{ sprintf(
					/* translators: %d: total number of items */
					__( '%d total', 'lime-stock-watchlist' ),
					totalItems
				) }
			</span>
			<div className="lswl-pagination__right">
				<button
					type="button"
					className="lswl-pagination__btn"
					disabled={ ! canPreviousPage }
					onClick={ onFirstPage }
					aria-label={ __( 'First page', 'lime-stock-watchlist' ) }
				>
					<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
						<path d="M8 12L4 8l4-4" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
						<path d="M12 12L8 8l4-4" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
					</svg>
				</button>
				<button
					type="button"
					className="lswl-pagination__btn"
					disabled={ ! canPreviousPage }
					onClick={ onPreviousPage }
					aria-label={ __( 'Previous page', 'lime-stock-watchlist' ) }
				>
					<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
						<path d="M10 12L6 8l4-4" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
					</svg>
				</button>

				{ rangeWithDots.map( ( p, idx ) =>
					p === '…'
						? <span key={ `dots-${ idx }` } className="lswl-pagination__dots">{ '…' }</span>
						: (
							<button
								key={ p }
								type="button"
								className={ `lswl-pagination__btn${ p === currentPage ? ' is-current' : '' }` }
								disabled={ p === currentPage }
								onClick={ () => onGoToPage( p - 1 ) }
								aria-label={ sprintf( __( 'Page %d', 'lime-stock-watchlist' ), p ) }
								aria-current={ p === currentPage ? 'page' : undefined }
							>
								{ p }
							</button>
						)
				) }

				<button
					type="button"
					className="lswl-pagination__btn"
					disabled={ ! canNextPage }
					onClick={ onNextPage }
					aria-label={ __( 'Next page', 'lime-stock-watchlist' ) }
				>
					<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
						<path d="M6 4l4 4-4 4" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
					</svg>
				</button>
				<button
					type="button"
					className="lswl-pagination__btn"
					disabled={ ! canNextPage }
					onClick={ onLastPage }
					aria-label={ __( 'Last page', 'lime-stock-watchlist' ) }
				>
					<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
						<path d="M4 4l4 4-4 4" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
						<path d="M8 4l4 4-4 4" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
					</svg>
				</button>
			</div>
		</div>
	);
}
