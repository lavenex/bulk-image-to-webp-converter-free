<?php
/** WordPress administrator UI and authenticated WebP validation endpoint. */

defined( 'ABSPATH' ) || exit;

class BIWEBP_Admin {
	/** @var BIWEBP_Usage */
	private $usage;

	/** @var BIWEBP_Validator */
	private $validator;

	/** @var BIWEBP_Idempotency */
	private $idempotency;

	public function __construct( BIWEBP_Usage $usage, BIWEBP_Validator $validator, BIWEBP_Idempotency $idempotency ) {
		$this->usage       = $usage;
		$this->validator   = $validator;
		$this->idempotency = $idempotency;
	}

	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_biwebp_validate_conversion', array( $this, 'validate_conversion' ) );
		add_action( 'wp_ajax_biwebp_scan_media', array( $this, 'scan_media' ) );
	}

	public function add_menu() {
		add_menu_page(
			__( 'Image to WebP', 'bulk-image-to-webp-converter' ),
			__( 'Image to WebP', 'bulk-image-to-webp-converter' ),
			'upload_files',
			'bulk-image-to-webp-converter',
			array( $this, 'render_page' ),
			'dashicons-format-image',
			58
		);
	}

	public function enqueue_assets( $hook_suffix ) {
		if ( 'toplevel_page_bulk-image-to-webp-converter' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'biwebp-admin', BIWEBP_URL . 'assets/css/webp-admin.css', array(), BIWEBP_VERSION );
		wp_enqueue_script( 'biwebp-admin', BIWEBP_URL . 'assets/js/webp-admin.js', array( 'media-editor' ), BIWEBP_VERSION, true );

		$is_pro         = $this->usage->is_pro();
		$plan_max_bytes = $this->validator->plan_max_bytes( $is_pro );
		$host_max_bytes = (int) wp_max_upload_size();
		$max_bytes      = $this->validator->max_bytes( $is_pro );
		wp_localize_script(
			'biwebp-admin',
			'biwebpConfig',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'biwebp_convert' ),
				'isPro'      => $is_pro,
				'batchLimit'  => $this->usage->batch_limit(),
				'maxBytes'   => $max_bytes,
				'maxLabel'   => size_format( $max_bytes ),
				'planMaxBytes' => $plan_max_bytes,
				'hostMaxBytes' => $host_max_bytes,
				'hostConstrained' => $max_bytes < $plan_max_bytes,
				'mediaScanPageSize' => 25,
				'pricingCatalog' => $this->pricing_catalog(),
				'strings'    => array(
					'processing' => __( 'Processing…', 'bulk-image-to-webp-converter' ),
					'complete'   => __( 'Converted', 'bulk-image-to-webp-converter' ),
					'failed'     => __( 'Failed', 'bulk-image-to-webp-converter' ),
					'limit'      => __( 'The current safe queue is full. Finish or clear it before adding another batch.', 'bulk-image-to-webp-converter' ),
				),
			)
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_die( esc_html__( 'You do not have permission to convert images.', 'bulk-image-to-webp-converter' ) );
		}

		$is_pro         = $this->usage->is_pro();
		$plan_max_bytes = $this->validator->plan_max_bytes( $is_pro );
		$max_bytes      = $this->validator->max_bytes( $is_pro );
		$max_label      = size_format( $max_bytes );
		$plan_max_label = size_format( $plan_max_bytes );
		$host_constrained = $max_bytes < $plan_max_bytes;
		$upgrade_url = (string) apply_filters( 'biwebp_upgrade_url', '' );
		$pricing     = $this->localized_pricing();
		?>
		<div class="wrap biwebp-wrap">
			<h1><?php echo esc_html__( 'Bulk Image to WebP Converter', 'bulk-image-to-webp-converter' ); ?></h1>
			<p class="biwebp-subtitle"><?php echo esc_html__( 'Convert new uploads or existing WordPress Media Library images into modern WebP files locally in your browser.', 'bulk-image-to-webp-converter' ); ?></p>
			<p class="description"><?php echo esc_html__( 'Works through the standard WordPress Media Library, so it can be used with any properly coded WordPress theme or page builder—including Elementor, WPBakery, and Gutenberg. No WooCommerce installation is required. Generated WebP files are new Media Library items; existing page content is not automatically rewritten.', 'bulk-image-to-webp-converter' ); ?></p>

			<div class="biwebp-usage" aria-live="polite">
				<strong><?php echo esc_html( $is_pro ? __( 'Pro conversions', 'bulk-image-to-webp-converter' ) : __( 'Free conversions', 'bulk-image-to-webp-converter' ) ); ?></strong>
				<span id="biwebp-usage-count"><?php echo esc_html__( 'Unlimited', 'bulk-image-to-webp-converter' ); ?></span>
				<span id="biwebp-remaining"><?php echo esc_html( sprintf( __( 'Process up to %d images per safe batch', 'bulk-image-to-webp-converter' ), $this->usage->batch_limit() ) ); ?></span>
			</div>
			<?php if ( ! $is_pro ) : ?>
				<section class="biwebp-pro-offer" id="biwebp-pro-upgrade" aria-labelledby="biwebp-pro-offer-title">
					<div class="biwebp-pro-offer-copy">
						<span class="biwebp-pro-badge"><?php echo esc_html__( 'LOCAL PRICE', 'bulk-image-to-webp-converter' ); ?> · <span id="biwebp-price-country"><?php echo esc_html( $pricing['country_label'] ); ?></span></span>
						<h2 id="biwebp-pro-offer-title"><?php echo esc_html__( 'Convert every image with WebP Pro', 'bulk-image-to-webp-converter' ); ?></h2>
						<p><?php echo esc_html__( 'Add one-click Media Library conversion, smart suggestions, larger safe batches, up to 25 MB per image, and premium support—using the same high-quality local pipeline.', 'bulk-image-to-webp-converter' ); ?></p>
					</div>
					<div class="biwebp-pro-offer-price">
						<strong class="biwebp-monthly-price"><span id="biwebp-monthly-price"><?php echo esc_html( $pricing['monthly'] ); ?></span> <span><?php echo esc_html__( '/ month', 'bulk-image-to-webp-converter' ); ?></span></strong>
						<small class="biwebp-yearly-price"><span id="biwebp-yearly-price"><?php echo esc_html( $pricing['yearly'] ); ?></span><?php echo esc_html__( '/year · 1 site · save with yearly billing', 'bulk-image-to-webp-converter' ); ?></small>
						<small class="biwebp-local-price-note"><?php echo esc_html__( 'Localized from the site country/locale; checkout confirms the final price.', 'bulk-image-to-webp-converter' ); ?></small>
						<?php if ( '' !== $upgrade_url ) : ?>
							<a class="button button-primary biwebp-upgrade-button" href="<?php echo esc_url( $upgrade_url ); ?>"><?php echo esc_html__( 'Upgrade to Pro', 'bulk-image-to-webp-converter' ); ?></a>
						<?php else : ?>
							<span class="button button-primary biwebp-upgrade-button" aria-label="<?php echo esc_attr__( 'Upgrade checkout coming soon', 'bulk-image-to-webp-converter' ); ?>"><?php echo esc_html__( 'Upgrade to Pro · Coming soon', 'bulk-image-to-webp-converter' ); ?></span>
						<?php endif; ?>
					</div>
				</section>
			<?php endif; ?>

			<div class="biwebp-panel">
				<div class="biwebp-hero-grid">
					<label class="biwebp-dropzone" for="biwebp-files">
						<span class="dashicons dashicons-upload" aria-hidden="true"></span>
						<strong><?php echo esc_html__( 'Choose images', 'bulk-image-to-webp-converter' ); ?></strong>
						<span id="biwebp-file-help"><?php echo esc_html( sprintf( __( 'PNG or JPG/JPEG · maximum %s per image · no fixed pixel-dimension limit', 'bulk-image-to-webp-converter' ), $max_label ) ); ?></span>
					</label>
					<div class="biwebp-impact" id="biwebp-impact" aria-live="polite" aria-atomic="true">
						<span class="biwebp-impact-label"><?php echo esc_html__( 'Estimated image speed impact', 'bulk-image-to-webp-converter' ); ?></span>
						<div class="biwebp-impact-time">
							<strong id="biwebp-impact-time">0.00 sec</strong>
							<span><?php echo esc_html__( 'estimated site image load time saved', 'bulk-image-to-webp-converter' ); ?></span>
						</div>
						<div class="biwebp-impact-meter" id="biwebp-impact-meter" role="progressbar" aria-label="<?php echo esc_attr__( 'Estimated image transfer reduction', 'bulk-image-to-webp-converter' ); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-valuetext="<?php echo esc_attr__( 'No measured reduction yet', 'bulk-image-to-webp-converter' ); ?>">
							<span id="biwebp-impact-bar"></span>
						</div>
						<span id="biwebp-impact-summary"><?php echo esc_html__( 'Complete a conversion to calculate savings.', 'bulk-image-to-webp-converter' ); ?></span>
						<small><?php echo esc_html__( 'Based on image-byte reduction at 10 Mbps 4G; not a guaranteed whole-site score.', 'bulk-image-to-webp-converter' ); ?></small>
					</div>
				</div>
				<input id="biwebp-files" type="file" accept="image/png,image/jpeg,.png,.jpg,.jpeg" aria-describedby="biwebp-file-help" multiple>
				<?php if ( $host_constrained ) : ?>
					<p class="biwebp-host-limit-notice" role="status"><?php echo esc_html( sprintf( __( 'This WordPress host currently allows %1$s per upload, so that lower server limit applies. Your plan supports up to %2$s per image.', 'bulk-image-to-webp-converter' ), $max_label, $plan_max_label ) ); ?></p>
				<?php endif; ?>
				<div class="biwebp-source-actions">
					<button type="button" class="button" id="biwebp-media-library"><?php echo esc_html__( 'Choose from Media Library', 'bulk-image-to-webp-converter' ); ?></button>
					<span id="biwebp-media-selection" aria-live="polite"></span>
				</div>
				<?php if ( $is_pro ) : ?>
					<section class="biwebp-pro-media" aria-labelledby="biwebp-pro-media-title">
						<div>
							<span class="biwebp-pro-media-badge"><?php echo esc_html__( 'PRO MEDIA ASSISTANT', 'bulk-image-to-webp-converter' ); ?></span>
							<h2 id="biwebp-pro-media-title"><?php echo esc_html__( 'Convert your Media Library faster', 'bulk-image-to-webp-converter' ); ?></h2>
							<p><?php echo esc_html__( 'Finds eligible PNG/JPEG originals that do not already have a WebP generated by this plugin. Originals remain untouched and processing stays sequential.', 'bulk-image-to-webp-converter' ); ?></p>
						</div>
						<div class="biwebp-pro-media-actions">
							<button type="button" class="button button-secondary" id="biwebp-scan-media"><?php echo esc_html__( 'Find suggested media', 'bulk-image-to-webp-converter' ); ?></button>
							<button type="button" class="button button-primary" id="biwebp-convert-all-media"><?php echo esc_html__( 'Convert all eligible media', 'bulk-image-to-webp-converter' ); ?></button>
						</div>
						<p id="biwebp-media-scan-summary" class="biwebp-media-scan-summary" role="status" aria-live="polite"><?php echo esc_html__( 'Run a scan to see smart suggestions.', 'bulk-image-to-webp-converter' ); ?></p>
						<div id="biwebp-media-suggestions" class="biwebp-media-suggestions" hidden>
							<label><input type="checkbox" id="biwebp-select-all-suggestions" checked> <?php echo esc_html__( 'Select all displayed suggestions', 'bulk-image-to-webp-converter' ); ?></label>
							<ul id="biwebp-media-suggestion-list" aria-label="<?php echo esc_attr__( 'Suggested Media Library images', 'bulk-image-to-webp-converter' ); ?>"></ul>
							<button type="button" class="button button-secondary" id="biwebp-queue-suggestions"><?php echo esc_html__( 'Convert selected suggestions', 'bulk-image-to-webp-converter' ); ?></button>
						</div>
					</section>
				<?php endif; ?>

				<div class="biwebp-controls">
					<label for="biwebp-quality"><?php echo esc_html__( 'WebP quality', 'bulk-image-to-webp-converter' ); ?></label>
					<input id="biwebp-quality" type="range" min="40" max="100" value="85" step="1" aria-valuetext="85 percent WebP quality">
					<output id="biwebp-quality-value" for="biwebp-quality">85%</output>
					<button type="button" class="button button-primary" id="biwebp-convert">
						<?php echo esc_html__( 'Convert selected images', 'bulk-image-to-webp-converter' ); ?>
					</button>
				</div>
				<div class="biwebp-queue-controls" role="group" aria-label="<?php echo esc_attr__( 'Queue controls', 'bulk-image-to-webp-converter' ); ?>">
					<button type="button" class="button" id="biwebp-pause"><?php echo esc_html__( 'Pause queue', 'bulk-image-to-webp-converter' ); ?></button>
					<button type="button" class="button" id="biwebp-resume"><?php echo esc_html__( 'Resume queue', 'bulk-image-to-webp-converter' ); ?></button>
					<button type="button" class="button" id="biwebp-retry"><?php echo esc_html__( 'Retry failed', 'bulk-image-to-webp-converter' ); ?></button>
					<button type="button" class="button" id="biwebp-cancel"><?php echo esc_html__( 'Cancel pending', 'bulk-image-to-webp-converter' ); ?></button>
					<button type="button" class="button" id="biwebp-clear"><?php echo esc_html__( 'Clear finished', 'bulk-image-to-webp-converter' ); ?></button>
					<span id="biwebp-queue-summary" aria-live="polite" aria-atomic="true"><?php echo esc_html__( 'Queue empty', 'bulk-image-to-webp-converter' ); ?></span>
				</div>
				<div class="biwebp-compression-progress">
					<div class="biwebp-compression-progress-heading">
						<strong><?php echo esc_html__( 'Image compression progress', 'bulk-image-to-webp-converter' ); ?></strong>
						<span id="biwebp-compression-progress-text">0 of 0 processed · 0%</span>
					</div>
					<div id="biwebp-compression-progress-meter" class="biwebp-compression-progress-meter" role="progressbar" aria-label="<?php echo esc_attr__( 'Image compression progress', 'bulk-image-to-webp-converter' ); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-valuetext="<?php echo esc_attr__( '0 of 0 images processed, 0 percent', 'bulk-image-to-webp-converter' ); ?>">
						<span id="biwebp-compression-progress-bar"></span>
					</div>
				</div>
				<p class="description"><?php echo esc_html__( '85% is recommended. Very large pixel dimensions depend on available browser memory and may fail safely. Free and Pro use the same output quality.', 'bulk-image-to-webp-converter' ); ?></p>
				<p class="description"><?php echo esc_html__( 'The refresh-safe queue is stored in this browser. Processing resumes when this converter page is open; local-first conversion does not continue after the browser is closed.', 'bulk-image-to-webp-converter' ); ?></p>
				<p id="biwebp-storage-notice" class="biwebp-storage-notice" role="status" aria-live="polite" aria-atomic="true" hidden></p>
				<p id="biwebp-message" class="biwebp-message" role="status" aria-live="polite" aria-atomic="true"></p>
			</div>

			<div id="biwebp-results" class="biwebp-results" role="list" aria-label="<?php echo esc_attr__( 'Conversion results', 'bulk-image-to-webp-converter' ); ?>" aria-live="polite" aria-relevant="additions text"></div>
			<p class="description"><?php echo esc_html__( 'Original images are never modified or deleted. Generated WebP files are saved only in this customer-owned WordPress Media Library. The plugin does not send or retain customer images on an external service.', 'bulk-image-to-webp-converter' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Return the localized display price. The catalogue is filterable so the
	 * commercial checkout/licensing service remains the pricing authority.
	 *
	 * @return array{monthly:string,yearly:string,country_label:string}
	 */
	private function localized_pricing() {
		$country = $this->pricing_country_code();
		$catalog = $this->pricing_catalog();
		$pricing = isset( $catalog[ $country ] ) ? $catalog[ $country ] : $catalog['US'];

		return (array) apply_filters( 'biwebp_localized_pricing', $pricing, $country );
	}

	/**
	 * Local display-price catalogue. Checkout remains authoritative and may
	 * replace this catalogue through the filter.
	 *
	 * @return array<string,array{monthly:string,yearly:string,country_label:string}>
	 */
	private function pricing_catalog() {
		$catalog = array(
			'IN' => array( 'monthly' => '₹199', 'yearly' => '₹1,500', 'country_label' => 'India' ),
			'US' => array( 'monthly' => '$2.49', 'yearly' => '$18.99', 'country_label' => 'United States' ),
			'GB' => array( 'monthly' => '£1.99', 'yearly' => '£14.99', 'country_label' => 'United Kingdom' ),
			'EU' => array( 'monthly' => '€2.29', 'yearly' => '€16.99', 'country_label' => 'European Union' ),
			'CA' => array( 'monthly' => 'C$3.49', 'yearly' => 'C$25.99', 'country_label' => 'Canada' ),
			'AU' => array( 'monthly' => 'A$3.79', 'yearly' => 'A$27.99', 'country_label' => 'Australia' ),
			'AE' => array( 'monthly' => 'AED 8.99', 'yearly' => 'AED 66.99', 'country_label' => 'United Arab Emirates' ),
			'SG' => array( 'monthly' => 'S$3.29', 'yearly' => 'S$24.99', 'country_label' => 'Singapore' ),
		);

		return (array) apply_filters( 'biwebp_pricing_catalog', $catalog );
	}

	/**
	 * Infer a display country without an IP lookup. Checkout performs the final
	 * billing-country decision.
	 *
	 * @return string
	 */
	private function pricing_country_code() {
		$timezone = wp_timezone_string();
		$country  = 'US';
		$timezone_countries = array(
			'Asia/Kolkata' => 'IN',
			'Asia/Calcutta' => 'IN',
			'Europe/London' => 'GB',
			'Australia/Sydney' => 'AU',
			'America/Toronto' => 'CA',
			'Asia/Dubai' => 'AE',
			'Asia/Singapore' => 'SG',
		);
		if ( isset( $timezone_countries[ $timezone ] ) ) {
			$country = $timezone_countries[ $timezone ];
		} else {
			$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
			if ( preg_match( '/[_-]([A-Z]{2})$/i', $locale, $matches ) ) {
				$country = strtoupper( $matches[1] );
			}
		}
		$eu_countries = array( 'AT', 'BE', 'CY', 'DE', 'EE', 'ES', 'FI', 'FR', 'GR', 'HR', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PT', 'SI', 'SK' );
		if ( in_array( $country, $eu_countries, true ) ) {
			$country = 'EU';
		}

		return strtoupper( (string) apply_filters( 'biwebp_pricing_country_code', $country ) );
	}

	public function validate_conversion() {
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bulk-image-to-webp-converter' ) ), 403 );
		}

		check_ajax_referer( 'biwebp_convert', 'nonce' );

		$is_pro    = $this->usage->is_pro();
		$job_key   = isset( $_POST['jobKey'] ) ? $this->idempotency->normalize_key( wp_unslash( $_POST['jobKey'] ) ) : '';
		if ( '' === $job_key ) {
			wp_send_json_error( array( 'message' => __( 'The queue job identifier is invalid. Refresh the page and retry.', 'bulk-image-to-webp-converter' ) ), 400 );
		}

		$cached = $this->idempotency->get_result( $job_key );
		if ( false !== $cached ) {
			$cached['remaining'] = $this->usage->remaining();
			$cached['replayed']  = true;
			wp_send_json_success( $cached );
		}

		if ( ! $this->idempotency->acquire( $job_key ) ) {
			wp_send_json_error( array( 'message' => __( 'This queue job is already processing in another tab. Retry shortly.', 'bulk-image-to-webp-converter' ) ), 409 );
		}

		$file      = isset( $_FILES['webp'] ) ? $_FILES['webp'] : array();
		$validated = $this->validator->validate( $file, $is_pro );
		if ( is_wp_error( $validated ) ) {
			$this->idempotency->release( $job_key );
			wp_send_json_error( array( 'message' => $validated->get_error_message() ), 400 );
		}

		$filename = isset( $_POST['filename'] ) ? sanitize_file_name( wp_unslash( $_POST['filename'] ) ) : 'converted.webp';
		$filename = preg_replace( '/\.[^.]+$/', '', $filename ) . '.webp';
		$source_attachment_id = isset( $_POST['sourceAttachmentId'] ) ? absint( $_POST['sourceAttachmentId'] ) : 0;
		$quality = isset( $_POST['quality'] ) ? max( 40, min( 100, absint( $_POST['quality'] ) ) ) : 85;
		$saved = $this->save_to_media_library( $file, $filename, $source_attachment_id, $quality );
		if ( is_wp_error( $saved ) ) {
			$this->idempotency->release( $job_key );
			wp_send_json_error( array( 'message' => $saved->get_error_message() ), 500 );
		}

		$response = array(
				'filename'     => $saved['filename'],
				'remaining'    => PHP_INT_MAX,
				'size'         => $validated['size'],
				'width'        => $validated['width'],
				'height'       => $validated['height'],
				'attachmentId' => $saved['attachment_id'],
				'attachmentUrl' => $saved['url'],
				'editUrl'      => $saved['edit_url'],
				'replayed'     => false,
			);
		$this->idempotency->store_result( $job_key, $response );
		$this->idempotency->release( $job_key );
		wp_send_json_success( $response );
	}

	/** Return unconverted PNG/JPEG Media Library suggestions to active Pro sites. */
	public function scan_media() {
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bulk-image-to-webp-converter' ) ), 403 );
		}
		check_ajax_referer( 'biwebp_convert', 'nonce' );
		if ( ! $this->usage->is_pro() ) {
			wp_send_json_error( array( 'message' => __( 'Convert All Media is available with an active Pro license.', 'bulk-image-to-webp-converter' ) ), 403 );
		}

		global $wpdb;
		$page     = isset( $_POST['page'] ) ? max( 1, absint( $_POST['page'] ) ) : 1;
		$per_page = 25;
		$offset   = ( $page - 1 ) * $per_page;
		$where    = "p.post_type = 'attachment' AND p.post_status = 'inherit' AND p.post_mime_type IN ('image/png','image/jpeg') AND NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} converted WHERE converted.meta_key = '_biwebp_source_attachment_id' AND CAST(converted.meta_value AS UNSIGNED) = p.ID)";
		$total    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} p WHERE {$where}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed query with core table names and no user data.
		$ids      = $wpdb->get_col( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p WHERE {$where} ORDER BY p.post_modified_gmt DESC, p.ID DESC LIMIT %d OFFSET %d", $per_page, $offset ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$items    = array();
		foreach ( $ids as $attachment_id ) {
			$attachment_id = absint( $attachment_id );
			$file          = get_attached_file( $attachment_id );
			$url           = wp_get_attachment_url( $attachment_id );
			$mime          = get_post_mime_type( $attachment_id );
			if ( ! $file || ! $url || ! in_array( $mime, array( 'image/png', 'image/jpeg' ), true ) || ! file_exists( $file ) ) {
				continue;
			}
			$bytes  = (int) filesize( $file );
			$reason = $bytes >= 524288 ? __( 'Large image — recommended first', 'bulk-image-to-webp-converter' ) : ( 'image/png' === $mime ? __( 'PNG optimization candidate', 'bulk-image-to-webp-converter' ) : __( 'Eligible JPEG', 'bulk-image-to-webp-converter' ) );
			$items[] = array(
				'id'       => $attachment_id,
				'filename' => basename( $file ),
				'url'      => esc_url_raw( $url ),
				'mime'     => $mime,
				'bytes'    => $bytes,
				'reason'   => $reason,
			);
		}
		wp_send_json_success( array( 'items' => $items, 'page' => $page, 'total' => $total, 'hasMore' => $offset + $per_page < $total ) );
	}

	/**
	 * Save the validated generated WebP in this site's Media Library.
	 *
	 * @param array  $file Uploaded WebP entry.
	 * @param string $filename Safe output filename.
	 * @param int    $source_attachment_id Existing Media attachment, when applicable.
	 * @param int    $quality Selected browser encoding quality.
	 * @return array|WP_Error
	 */
	private function save_to_media_library( $file, $filename, $source_attachment_id, $quality ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$sideload         = $file;
		$sideload['name'] = $filename;
		$attachment_id    = media_handle_sideload(
			$sideload,
			0,
			preg_replace( '/\.[^.]+$/', '', $filename ),
			array( 'post_mime_type' => 'image/webp' )
		);
		if ( is_wp_error( $attachment_id ) ) {
			return new WP_Error( 'biwebp_media_save_failed', sprintf( __( 'The WebP was created but could not be saved to the Media Library: %s', 'bulk-image-to-webp-converter' ), $attachment_id->get_error_message() ) );
		}

		update_post_meta( $attachment_id, '_biwebp_source_attachment_id', $source_attachment_id );
		update_post_meta( $attachment_id, '_biwebp_quality', $quality );

		return array(
			'attachment_id' => $attachment_id,
			'filename'      => basename( get_attached_file( $attachment_id ) ),
			'url'           => wp_get_attachment_url( $attachment_id ),
			'edit_url'      => get_edit_post_link( $attachment_id, 'raw' ),
		);
	}
}
