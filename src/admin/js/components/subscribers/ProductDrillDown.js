/**
 * Drill-down view showing all subscribers for a single product.
 */
import { useState, useRef } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import UserView from './UserView';

const STATUS_OPTIONS = [
	{ label: __( 'All Statuses', 'lime-stock-watchlist' ), value: 'all' },
	{ label: __( 'Watching', 'lime-stock-watchlist' ),     value: 'watching' },
	{ label: __( 'Notifying', 'lime-stock-watchlist' ),    value: 'notifying' },
	{ label: __( 'Notified', 'lime-stock-watchlist' ),     value: 'notified' },
	{ label: __( 'Unsubscribed', 'lime-stock-watchlist' ), value: 'unsubscribed' },
];

/**
 * @param {{
 *   productId: number,
 *   productName: string,
 *   productUrl: string,
 *   onBack: Function,
 * }} props
 * @return {JSX.Element}
 */
export default function ProductDrillDown( { productId, productName, productUrl, onBack } ) {
	const [ inputValue, setInputValue ] = useState( '' );
	const [ search, setSearch ]         = useState( '' );
	const [ status, setStatus ]         = useState( 'all' );
	const debounceRef = useRef( null );

	function handleSearchInput( value ) {
		setInputValue( value );
		clearTimeout( debounceRef.current );
		debounceRef.current = setTimeout( () => setSearch( value ), 300 );
	}

	return (
		<div className="lswl-drill-down">
			<div className="lswl-drill-down__header">
				<Button
					variant="tertiary"
					className="lswl-drill-down__back"
					onClick={ onBack }
				>
					{ __( '← Back to Products', 'lime-stock-watchlist' ) }
				</Button>
			</div>
			<div className="lswl-controls-row lswl-controls-row--drill-down">
				<h3 className="lswl-drill-down__title">
					{ __( 'Subscribers for:', 'lime-stock-watchlist' ) }{ ' ' }
					{ productUrl
						? <a href={ productUrl } target="_blank" rel="noreferrer" className="lswl-drill-down__product-link">{ productName }</a>
						: productName
					}
				</h3>
				<div className="lswl-controls-row__filters">
					<input
						type="search"
						className="lswl-filter-search"
						placeholder={ __( 'Search by email…', 'lime-stock-watchlist' ) }
						value={ inputValue }
						onChange={ ( e ) => handleSearchInput( e.target.value ) }
					/>
					<select
						className="lswl-filter-select"
						value={ status }
						onChange={ ( e ) => setStatus( e.target.value ) }
						aria-label={ __( 'Filter by status', 'lime-stock-watchlist' ) }
					>
						{ STATUS_OPTIONS.map( ( opt ) => (
							<option key={ opt.value } value={ opt.value }>{ opt.label }</option>
						) ) }
					</select>
				</div>
			</div>
			<UserView productId={ productId } search={ search } status={ status } />
		</div>
	);
}
