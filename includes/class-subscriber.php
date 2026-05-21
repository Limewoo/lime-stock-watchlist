<?php
/**
 * Subscriber value object.
 *
 * @package Lime_Stock_Watchlist
 */

namespace Lime_Stock_Watchlist;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Immutable representation of a watchlist subscriber row.
 */
class Subscriber {

	/**
	 * Subscriber row primary key.
	 *
	 * @var int
	 */
	public int $id;

	/**
	 * ID of the watched product.
	 *
	 * @var int
	 */
	public int $product_id;

	/**
	 * Subscriber email address.
	 *
	 * @var string
	 */
	public string $email;

	/**
	 * Subscriber full name (optional).
	 *
	 * @var string
	 */
	public string $name;

	/**
	 * ISO datetime string of subscription.
	 *
	 * @var string
	 */
	public string $date_subscribed;

	/**
	 * Notification state: 0 = watching, 2 = notifying (AS queued), 1 = notified.
	 *
	 * @var int
	 */
	public int $notified;

	/**
	 * Whether the subscriber has opted out.
	 *
	 * @var bool
	 */
	public bool $unsubscribed;

	/**
	 * Create a Subscriber instance.
	 *
	 * @param int    $id              Subscriber ID (0 for unsaved instances).
	 * @param int    $product_id      Product ID (0 for unsaved instances).
	 * @param string $email           Email address.
	 * @param string $name            Full name (optional).
	 * @param string $date_subscribed ISO datetime string.
	 * @param int    $notified        0 | 2 | 1.
	 * @param bool   $unsubscribed    Whether the subscriber has opted out.
	 */
	public function __construct(
		int $id,
		int $product_id,
		string $email,
		string $name = '',
		string $date_subscribed = '',
		int $notified = 0,
		bool $unsubscribed = false
	) {
		$this->id              = $id;
		$this->product_id      = $product_id;
		$this->email           = $email;
		$this->name            = $name;
		$this->date_subscribed = $date_subscribed;
		$this->notified        = $notified;
		$this->unsubscribed    = $unsubscribed;
	}

	/**
	 * Build from a raw $wpdb result row.
	 *
	 * @param object $row stdClass from get_row() / get_results().
	 * @return self
	 */
	public static function from_row( object $row ): self {
		return new self(
			(int) $row->id,
			isset( $row->product_id ) ? (int) $row->product_id : 0,
			(string) $row->email,
			(string) $row->name,
			isset( $row->date_subscribed ) ? (string) $row->date_subscribed : '',
			isset( $row->notified ) ? (int) $row->notified : 0,
			isset( $row->unsubscribed ) ? (bool) $row->unsubscribed : false
		);
	}

	/**
	 * Active and awaiting notification.
	 *
	 * @return bool
	 */
	public function is_watching(): bool {
		return 0 === $this->notified && ! $this->unsubscribed;
	}

	/**
	 * Queued in Action Scheduler — email in flight.
	 *
	 * @return bool
	 */
	public function is_notifying(): bool {
		return 2 === $this->notified && ! $this->unsubscribed;
	}

	/**
	 * Email sent successfully.
	 *
	 * @return bool
	 */
	public function is_notified(): bool {
		return 1 === $this->notified && ! $this->unsubscribed;
	}

	/**
	 * Whether the subscriber has opted out of notifications.
	 *
	 * @return bool
	 */
	public function is_unsubscribed(): bool {
		return $this->unsubscribed;
	}

	/**
	 * First name for email greetings. Returns empty string when name is absent.
	 *
	 * @return string
	 */
	public function display_name(): string {
		if ( empty( $this->name ) ) {
			return '';
		}
		return explode( ' ', trim( $this->name ) )[0];
	}
}
