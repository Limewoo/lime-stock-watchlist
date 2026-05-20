/**
 * Search and status filter controls for subscriber tables.
 */
import { useRef, useState } from '@wordpress/element';
import { SearchControl, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const STATUS_OPTIONS = [
	{ label: __( 'All statuses', 'lime-stock-watchlist' ), value: 'all' },
	{ label: __( 'Watching', 'lime-stock-watchlist' ), value: 'watching' },
	{ label: __( 'Notifying', 'lime-stock-watchlist' ), value: 'notifying' },
	{ label: __( 'Notified', 'lime-stock-watchlist' ), value: 'notified' },
	{ label: __( 'Unsubscribed', 'lime-stock-watchlist' ), value: 'unsubscribed' },
];

/**
 * @param {{
 *   view: 'users'|'products',
 *   search: string,
 *   onSearchChange: Function,
 *   status: string,
 *   onStatusChange: Function,
 * }} props
 * @return {JSX.Element}
 */
export default function SubscriberFilters( {
	view,
	search,
	onSearchChange,
	status,
	onStatusChange,
} ) {
	const [ inputValue, setInputValue ] = useState( search );
	const debounceRef = useRef( null );

	function handleSearchChange( value ) {
		setInputValue( value );
		clearTimeout( debounceRef.current );
		debounceRef.current = setTimeout( () => onSearchChange( value ), 300 );
	}

	const searchPlaceholder = view === 'products'
		? __( 'Search by product name…', 'lime-stock-watchlist' )
		: __( 'Search by email…', 'lime-stock-watchlist' );

	return (
		<div className="lswl-filters">
			<SearchControl
				__nextHasNoMarginBottom
				className="lswl-filters__search"
				placeholder={ searchPlaceholder }
				value={ inputValue }
				onChange={ handleSearchChange }
			/>
			{ view === 'users' && (
				<SelectControl
					__nextHasNoMarginBottom
					className="lswl-filters__status"
					value={ status }
					options={ STATUS_OPTIONS }
					onChange={ onStatusChange }
					aria-label={ __( 'Filter by status', 'lime-stock-watchlist' ) }
				/>
			) }
		</div>
	);
}
