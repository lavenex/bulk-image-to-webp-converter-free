<?php
/** Remove plugin-owned transient state without deleting customer media. */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/** Delete short-lived idempotency locks and cached replay results for one site. */
function biwebp_uninstall_ephemeral_options() {
	global $wpdb;

	$lock_pattern   = $wpdb->esc_like( 'biwebp_job_lock_' ) . '%';
	$result_pattern = $wpdb->esc_like( 'biwebp_job_result_' ) . '%';
	$legacy_usage_pattern = $wpdb->esc_like( 'biwebp_usage_slot_' ) . '%';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time uninstall cleanup of plugin-owned ephemeral options.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
			$lock_pattern,
			$result_pattern,
			$legacy_usage_pattern
		)
	);
}

if ( is_multisite() ) {
	$biwebp_site_ids = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
	foreach ( $biwebp_site_ids as $biwebp_site_id ) {
		switch_to_blog( (int) $biwebp_site_id );
		biwebp_uninstall_ephemeral_options();
		restore_current_blog();
	}
} else {
	biwebp_uninstall_ephemeral_options();
}

// Intentionally preserved: every original and generated Media Library attachment.
