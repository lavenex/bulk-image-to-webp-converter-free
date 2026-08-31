<?php
/** Server-side duplicate protection for retried conversion jobs. */

defined( 'ABSPATH' ) || exit;

class BIWEBP_Idempotency {
	const RESULT_PREFIX = 'biwebp_job_result_';
	const LOCK_PREFIX   = 'biwebp_job_lock_';
	const LOCK_TTL      = 300;
	const RESULT_TTL    = 604800;

	/**
	 * Normalize an untrusted client job identifier.
	 *
	 * @param string $key Client-generated identifier.
	 * @return string
	 */
	public function normalize_key( $key ) {
		$key = sanitize_text_field( (string) $key );
		return preg_match( '/^[A-Za-z0-9_-]{16,64}$/', $key ) ? $key : '';
	}

	/**
	 * Return a recent successful result for a previously completed job.
	 *
	 * @param string $key Normalized job identifier.
	 * @return array|false
	 */
	public function get_result( $key ) {
		$result = get_option( self::RESULT_PREFIX . $key, false );
		if ( ! is_array( $result ) || empty( $result['completed_at'] ) ) {
			return false;
		}

		if ( time() - (int) $result['completed_at'] > self::RESULT_TTL ) {
			delete_option( self::RESULT_PREFIX . $key );
			return false;
		}

		return $result;
	}

	/**
	 * Acquire a short atomic processing lock.
	 *
	 * @param string $key Normalized job identifier.
	 * @return bool
	 */
	public function acquire( $key ) {
		$name = self::LOCK_PREFIX . $key;
		if ( add_option( $name, time(), '', false ) ) {
			return true;
		}

		$locked_at = (int) get_option( $name, 0 );
		if ( $locked_at > 0 && time() - $locked_at > self::LOCK_TTL ) {
			delete_option( $name );
			return add_option( $name, time(), '', false );
		}

		return false;
	}

	/**
	 * Release the processing lock.
	 *
	 * @param string $key Normalized job identifier.
	 */
	public function release( $key ) {
		delete_option( self::LOCK_PREFIX . $key );
	}

	/**
	 * Cache a successful response for safe replay.
	 *
	 * @param string $key    Normalized job identifier.
	 * @param array  $result Successful response data.
	 */
	public function store_result( $key, $result ) {
		$result['completed_at'] = time();
		update_option( self::RESULT_PREFIX . $key, $result, false );
	}
}
