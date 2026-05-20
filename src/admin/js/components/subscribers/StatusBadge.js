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
			<span
				className="lswl-badge lswl-badge--unsubscribed"
				data-tooltip={ __( 'Opted out of stock notifications', 'lime-stock-watchlist' ) }
			>
				<span className="lswl-badge__dot" />
				{ __( 'Unsubscribed', 'lime-stock-watchlist' ) }
			</span>
		);
	}
	if ( subscriber.notified === 2 ) {
		return (
			<span
				className="lswl-badge lswl-badge--notifying"
				data-tooltip={ __( 'Back-in-stock email is currently being sent', 'lime-stock-watchlist' ) }
			>
				<span className="lswl-badge__dot" />
				{ __( 'Notifying', 'lime-stock-watchlist' ) }
			</span>
		);
	}
	if ( subscriber.notified === 1 ) {
		return (
			<span
				className="lswl-badge lswl-badge--notified"
				data-tooltip={ __( 'Back-in-stock email has been sent', 'lime-stock-watchlist' ) }
			>
				<span className="lswl-badge__dot" />
				{ __( 'Notified', 'lime-stock-watchlist' ) }
			</span>
		);
	}
	return (
		<span
			className="lswl-badge lswl-badge--waiting"
			data-tooltip={ __( 'Waiting to be notified when back in stock', 'lime-stock-watchlist' ) }
		>
			<span className="lswl-badge__dot" />
			{ __( 'Watching', 'lime-stock-watchlist' ) }
		</span>
	);
}
