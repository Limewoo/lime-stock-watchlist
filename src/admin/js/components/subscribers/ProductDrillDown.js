/**
 * Drill-down view showing all subscribers for a single product.
 */
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import UserView from './UserView';

/**
 * @param {{
 *   productId: number,
 *   productName: string,
 *   onBack: Function,
 * }} props
 * @return {JSX.Element}
 */
export default function ProductDrillDown( { productId, productName, onBack } ) {
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
				<h3 className="lswl-drill-down__title">
					{ sprintf(
						/* translators: %s: product name */
						__( 'Subscribers for: %s', 'lime-stock-watchlist' ),
						productName
					) }
				</h3>
			</div>
			<UserView productId={ productId } />
		</div>
	);
}
