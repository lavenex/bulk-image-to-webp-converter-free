<?php
/** Customer-facing license status and connector boundary. */

defined( 'ABSPATH' ) || exit;

class BIWEBP_License_Admin {
	/** @var BIWEBP_License */
	private $license;

	public function __construct( BIWEBP_License $license ) {
		$this->license = $license;
	}

	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_biwebp_license_activate', array( $this, 'handle_activate' ) );
		add_action( 'admin_post_biwebp_license_deactivate', array( $this, 'handle_deactivate' ) );
		add_action( 'admin_post_biwebp_license_refresh', array( $this, 'handle_refresh' ) );
	}

	public function add_menu() {
		add_submenu_page(
			'bulk-image-to-webp-converter',
			__( 'WebP Pro License', 'bulk-image-to-webp-converter' ),
			__( 'License', 'bulk-image-to-webp-converter' ),
			'manage_options',
			'bulk-image-to-webp-license',
			array( $this, 'render_page' )
		);
	}

	public function enqueue_assets( $hook_suffix ) {
		if ( false === strpos( (string) $hook_suffix, 'bulk-image-to-webp-license' ) ) {
			return;
		}

		wp_enqueue_style( 'biwebp-admin', BIWEBP_URL . 'assets/css/webp-admin.css', array(), BIWEBP_VERSION );
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage this license.', 'bulk-image-to-webp-converter' ) );
		}

		$status              = $this->license->get_status();
		$connector_available = $this->connector_available();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status flag produced after an authorized admin-post redirect.
		$notice              = isset( $_GET['biwebp_notice'] ) ? sanitize_key( wp_unslash( $_GET['biwebp_notice'] ) ) : '';
		$labels              = array(
			'free'    => __( 'Free', 'bulk-image-to-webp-converter' ),
			'active'  => __( 'Pro active', 'bulk-image-to-webp-converter' ),
			'grace'   => __( 'Pro grace period', 'bulk-image-to-webp-converter' ),
			'expired' => __( 'Expired — Free manual features active', 'bulk-image-to-webp-converter' ),
			'invalid' => __( 'Invalid for this site — Free manual features active', 'bulk-image-to-webp-converter' ),
		);
		$label               = isset( $labels[ $status['status'] ] ) ? $labels[ $status['status'] ] : $labels['free'];
		$expiry              = $status['expires_at'] > 0 ? wp_date( get_option( 'date_format' ), $status['expires_at'] ) : __( 'Not available', 'bulk-image-to-webp-converter' );
		?>
		<div class="wrap biwebp-wrap biwebp-license-wrap">
			<h1><?php echo esc_html__( 'WebP Pro License', 'bulk-image-to-webp-converter' ); ?></h1>
			<p class="biwebp-subtitle"><?php echo esc_html__( 'Manage the one-site Pro entitlement for this WordPress installation.', 'bulk-image-to-webp-converter' ); ?></p>

			<?php if ( '' !== $notice ) : ?>
				<div class="notice <?php echo 'success' === $notice ? 'notice-success' : 'notice-error'; ?> is-dismissible"><p><?php echo esc_html( 'success' === $notice ? __( 'License action completed.', 'bulk-image-to-webp-converter' ) : __( 'The license action could not be completed. Verify the connector and try again.', 'bulk-image-to-webp-converter' ) ); ?></p></div>
			<?php endif; ?>

			<section class="biwebp-license-card" aria-labelledby="biwebp-license-status-title">
				<h2 id="biwebp-license-status-title"><?php echo esc_html__( 'License status', 'bulk-image-to-webp-converter' ); ?></h2>
				<dl class="biwebp-license-details">
					<div><dt><?php echo esc_html__( 'Plan', 'bulk-image-to-webp-converter' ); ?></dt><dd><?php echo esc_html( 'pro' === $status['plan'] ? __( 'Pro', 'bulk-image-to-webp-converter' ) : __( 'Free', 'bulk-image-to-webp-converter' ) ); ?></dd></div>
					<div><dt><?php echo esc_html__( 'Status', 'bulk-image-to-webp-converter' ); ?></dt><dd><?php echo esc_html( $label ); ?></dd></div>
					<div><dt><?php echo esc_html__( 'This site', 'bulk-image-to-webp-converter' ); ?></dt><dd><?php echo esc_html( home_url( '/' ) ); ?></dd></div>
					<div><dt><?php echo esc_html__( 'Expiry', 'bulk-image-to-webp-converter' ); ?></dt><dd><?php echo esc_html( $expiry ); ?></dd></div>
				</dl>
			</section>

			<section class="biwebp-license-card" aria-labelledby="biwebp-license-actions-title">
				<h2 id="biwebp-license-actions-title"><?php echo esc_html__( 'License actions', 'bulk-image-to-webp-converter' ); ?></h2>
				<?php if ( ! $connector_available ) : ?>
					<p class="biwebp-license-warning" role="status"><?php echo esc_html__( 'The private WebP Pro connector is not installed or configured. Activation remains safely unavailable; the Free converter continues to work normally.', 'bulk-image-to-webp-converter' ); ?></p>
				<?php endif; ?>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="biwebp-license-form">
					<input type="hidden" name="action" value="biwebp_license_activate">
					<?php wp_nonce_field( 'biwebp_license_activate' ); ?>
					<label for="biwebp-license-key"><?php echo esc_html__( 'License key', 'bulk-image-to-webp-converter' ); ?></label>
					<input type="password" id="biwebp-license-key" name="license_key" maxlength="256" autocomplete="off" <?php disabled( ! $connector_available ); ?>>
					<button type="submit" class="button button-primary" <?php disabled( ! $connector_available ); ?>><?php echo esc_html__( 'Activate license', 'bulk-image-to-webp-converter' ); ?></button>
				</form>

				<div class="biwebp-license-secondary-actions" role="group" aria-label="<?php echo esc_attr__( 'Existing license actions', 'bulk-image-to-webp-converter' ); ?>">
					<?php $this->action_form( 'biwebp_license_refresh', __( 'Refresh status', 'bulk-image-to-webp-converter' ), $connector_available ); ?>
					<?php $this->action_form( 'biwebp_license_deactivate', __( 'Deactivate license', 'bulk-image-to-webp-converter' ), $connector_available ); ?>
				</div>
				<p class="description"><?php echo esc_html__( 'The converter core does not store or display the raw license key. The private Pro connector must verify signed service responses before it supplies an entitlement.', 'bulk-image-to-webp-converter' ); ?></p>
			</section>

			<p class="description"><?php echo esc_html__( 'License expiry changes Pro access only. Original images and previously generated WebP files are never deleted or locked.', 'bulk-image-to-webp-converter' ); ?></p>
		</div>
		<?php
	}

	private function action_form( $action, $label, $enabled ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>">
			<?php wp_nonce_field( $action ); ?>
			<button type="submit" class="button" <?php disabled( ! $enabled ); ?>><?php echo esc_html( $label ); ?></button>
		</form>
		<?php
	}

	public function handle_activate() {
		$this->authorize( 'biwebp_license_activate' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- authorize() verifies the capability and action nonce before this read.
		$key = isset( $_POST['license_key'] ) ? preg_replace( '/\s+/', '', sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) ) : '';
		if ( '' === $key || strlen( $key ) > 256 ) {
			$this->redirect_with_notice( 'error' );
		}
		$result = apply_filters( 'biwebp_license_activate_result', new WP_Error( 'biwebp_connector_required', __( 'The private Pro connector is required.', 'bulk-image-to-webp-converter' ) ), $key, home_url( '/' ) );
		$this->redirect_with_notice( is_wp_error( $result ) ? 'error' : 'success' );
	}

	public function handle_deactivate() {
		$this->authorize( 'biwebp_license_deactivate' );
		$result = apply_filters( 'biwebp_license_deactivate_result', new WP_Error( 'biwebp_connector_required', __( 'The private Pro connector is required.', 'bulk-image-to-webp-converter' ) ), home_url( '/' ) );
		$this->redirect_with_notice( is_wp_error( $result ) ? 'error' : 'success' );
	}

	public function handle_refresh() {
		$this->authorize( 'biwebp_license_refresh' );
		$result = apply_filters( 'biwebp_license_refresh_result', new WP_Error( 'biwebp_connector_required', __( 'The private Pro connector is required.', 'bulk-image-to-webp-converter' ) ), home_url( '/' ) );
		$this->redirect_with_notice( is_wp_error( $result ) ? 'error' : 'success' );
	}

	private function authorize( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage this license.', 'bulk-image-to-webp-converter' ) );
		}
		check_admin_referer( $action );
		if ( ! $this->connector_available() ) {
			$this->redirect_with_notice( 'error' );
		}
	}

	private function connector_available() {
		return (bool) apply_filters( 'biwebp_license_connector_available', false );
	}

	private function redirect_with_notice( $notice ) {
		$url = add_query_arg( 'biwebp_notice', $notice, admin_url( 'admin.php?page=bulk-image-to-webp-license' ) );
		wp_safe_redirect( $url );
		exit;
	}
}
