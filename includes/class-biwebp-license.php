<?php
/**
 * Standalone WordPress Pro entitlement contract.
 *
 * A separately distributed Pro connector is responsible for verifying signed
 * responses from the licensing service. It supplies the verified entitlement
 * through the biwebp_license_entitlement filter. The Free plugin contains no
 * local activation switch and performs no checkout, e-commerce-plugin, or
 * customer-image request.
 */

defined( 'ABSPATH' ) || exit;

class BIWEBP_License {
	const STATUS_FREE    = 'free';
	const STATUS_ACTIVE  = 'active';
	const STATUS_GRACE   = 'grace';
	const STATUS_EXPIRED = 'expired';
	const STATUS_INVALID = 'invalid';

	const MAX_GRACE_SECONDS = 604800; // Seven days.

	/** Register the entitlement adapter with the converter's edition check. */
	public function register() {
		add_filter( 'biwebp_is_pro', array( $this, 'filter_is_pro' ) );
	}

	/**
	 * Preserve an existing trusted Pro result or apply the signed entitlement.
	 *
	 * @param bool $is_pro Existing result from another trusted integration.
	 * @return bool
	 */
	public function filter_is_pro( $is_pro ) {
		return (bool) $is_pro || $this->has_pro_access();
	}

	/** @return bool */
	public function has_pro_access() {
		$status = $this->get_status();
		return in_array( $status['status'], array( self::STATUS_ACTIVE, self::STATUS_GRACE ), true );
	}

	/**
	 * Return a normalized effective entitlement state.
	 *
	 * Entitlement timestamps are UTC Unix timestamps. Site binding ignores the
	 * URL scheme but requires the same host, port, and subdirectory path.
	 *
	 * @return array{status:string,plan:string,site_url:string,expires_at:int,grace_until:int}
	 */
	public function get_status() {
		$default = array(
			'status'      => self::STATUS_FREE,
			'plan'        => 'free',
			'site_url'    => '',
			'expires_at'  => 0,
			'grace_until' => 0,
		);
		$raw = apply_filters( 'biwebp_license_entitlement', $default );
		$raw = is_array( $raw ) ? array_merge( $default, $raw ) : $default;

		$claimed_status = strtolower( preg_replace( '/[^a-z_]/i', '', (string) $raw['status'] ) );
		$site_url       = (string) $raw['site_url'];
		$expires_at     = max( 0, (int) $raw['expires_at'] );
		$grace_until    = max( 0, (int) $raw['grace_until'] );
		$now            = (int) apply_filters( 'biwebp_license_now', time() );
		$result         = array(
			'status'      => self::STATUS_FREE,
			'plan'        => 'pro' === strtolower( (string) $raw['plan'] ) ? 'pro' : 'free',
			'site_url'    => $site_url,
			'expires_at'  => $expires_at,
			'grace_until' => $grace_until,
		);

		if ( self::STATUS_FREE === $claimed_status ) {
			return $result;
		}

		if ( ! in_array( $claimed_status, array( self::STATUS_ACTIVE, self::STATUS_GRACE, self::STATUS_EXPIRED ), true ) ) {
			$result['status'] = self::STATUS_INVALID;
			return $result;
		}

		if ( '' === $this->normalize_site( $site_url ) || $this->normalize_site( $site_url ) !== $this->normalize_site( home_url( '/' ) ) ) {
			$result['status'] = self::STATUS_INVALID;
			return $result;
		}

		if ( $expires_at > 0 && $expires_at >= $now ) {
			$result['status'] = self::STATUS_ACTIVE;
			$result['plan']   = 'pro';
			return $result;
		}

		$grace_is_bounded = $expires_at > 0 && $grace_until >= $now && $grace_until <= $expires_at + self::MAX_GRACE_SECONDS;
		if ( $grace_is_bounded ) {
			$result['status'] = self::STATUS_GRACE;
			$result['plan']   = 'pro';
			return $result;
		}

		$result['status'] = self::STATUS_EXPIRED;
		$result['plan']   = 'free';
		return $result;
	}

	/**
	 * Normalize a site identity for one-site binding.
	 *
	 * @param string $url Site URL.
	 * @return string
	 */
	private function normalize_site( $url ) {
		$parts = wp_parse_url( trim( (string) $url ) );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return '';
		}

		$identity = strtolower( $parts['host'] );
		if ( isset( $parts['port'] ) ) {
			$identity .= ':' . absint( $parts['port'] );
		}
		if ( ! empty( $parts['path'] ) ) {
			$identity .= '/' . trim( $parts['path'], '/' );
		}

		return rtrim( $identity, '/' );
	}
}
