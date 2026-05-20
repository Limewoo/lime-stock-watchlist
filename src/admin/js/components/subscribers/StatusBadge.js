/**
 * Status badge for a subscriber row.
 */
import { __ } from '@wordpress/i18n';

/**
 * @param {{ subscriber: Object }} props
 * @return {JSX.Element}
 */
export default function StatusBadge( { subscriber } ) {
	if ( subscriber.unsubscribed ) {
		return (
			<span className="lswl-badge lswl-badge--unsubscribed">
				<span className="lswl-badge__dot" />
				{ __( 'Unsubscribed', 'lime-stock-watchlist' ) }
			</span>
		);
	}
	if ( subscriber.notified === 2 ) {
		return (
			<span className="lswl-badge lswl-badge--notifying">
				<span className="lswl-badge__dot" />
				{ __( 'Notifying', 'lime-stock-watchlist' ) }
			</span>
		);
	}
	if ( subscriber.notified === 1 ) {
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
			{ __( 'Watching', 'lime-stock-watchlist' ) }
		</span>
	);
}
