<?php
/**
 * Distribution-plan compatibility and entitlement handling.
 */

defined( 'ABSPATH' ) || exit;

class BIWEBP_Usage {
	const FREE_BATCH_LIMIT = 25;
	const PRO_BATCH_LIMIT  = 100;

	/**
	 * Whether a separately installed Pro edition has enabled Pro mode.
	 *
	 * @return bool
	 */
	public function is_pro() {
		return (bool) apply_filters( 'biwebp_is_pro', false );
	}

	/**
	 * Return a compatibility usage record for older integrations.
	 *
	 * @return array{date:string,count:int}
	 */
	public function get_usage() {
		return array(
			'date'  => current_time( 'Y-m-d' ),
			'count' => 0,
		);
	}

	/**
	 * Conversions are not commercially quota-limited in the public Free plugin.
	 *
	 * @return int
	 */
	public function remaining() {
		return PHP_INT_MAX;
	}

	/**
	 * Preserve the legacy method contract without consuming a commercial quota.
	 *
	 * @return int|WP_Error Remaining conversions, or an error.
	 */
	public function consume_success() {
		return PHP_INT_MAX;
	}

	/**
	 * Safe per-action queue size. Administrators can start another batch after
	 * the current queue finishes; this is not a daily or lifetime quota.
	 *
	 * @return int
	 */
	public function batch_limit() {
		return $this->is_pro() ? self::PRO_BATCH_LIMIT : self::FREE_BATCH_LIMIT;
	}
}
