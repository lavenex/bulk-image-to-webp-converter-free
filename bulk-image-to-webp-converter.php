<?php
/**
 * Plugin Name:       Bulk Image to WebP Converter
 * Plugin URI:        https://example.com/bulk-image-to-webp-converter
 * Description:       Convert PNG and JPG/JPEG images into lightweight WebP files in bulk.
 * Version:           0.8.0-alpha
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Project 01
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bulk-image-to-webp-converter
 */

defined( 'ABSPATH' ) || exit;

define( 'BIWEBP_VERSION', '0.8.0-alpha' );
define( 'BIWEBP_FILE', __FILE__ );
define( 'BIWEBP_DIR', plugin_dir_path( __FILE__ ) );
define( 'BIWEBP_URL', plugin_dir_url( __FILE__ ) );

require_once BIWEBP_DIR . 'includes/class-biwebp-usage.php';
require_once BIWEBP_DIR . 'includes/class-biwebp-output-validator.php';
require_once BIWEBP_DIR . 'includes/class-biwebp-idempotency.php';
require_once BIWEBP_DIR . 'includes/class-biwebp-license.php';
require_once BIWEBP_DIR . 'admin/class-biwebp-converter-admin.php';
require_once BIWEBP_DIR . 'admin/class-biwebp-license-admin.php';
require_once BIWEBP_DIR . 'admin/class-biwebp-history-admin.php';

/**
 * Start the plugin after WordPress has loaded active plugins.
 */
function biwebp_boot_plugin() {
	$license   = new BIWEBP_License();
	$license->register();
	$usage     = new BIWEBP_Usage();
	$validator = new BIWEBP_Validator();
	$idempotency = new BIWEBP_Idempotency();
	$admin       = new BIWEBP_Admin( $usage, $validator, $idempotency );
	$admin->register();
	$license_admin = new BIWEBP_License_Admin( $license );
	$license_admin->register();
	$history_admin = new BIWEBP_History_Admin( $usage, $validator );
	$history_admin->register();
}
add_action( 'plugins_loaded', 'biwebp_boot_plugin' );
