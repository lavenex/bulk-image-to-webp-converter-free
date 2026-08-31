<?php
/** Read-only conversion history and diagnostics screen. */

defined( 'ABSPATH' ) || exit;

class BIWEBP_History_Admin {
	/** @var BIWEBP_Usage */
	private $usage;

	/** @var BIWEBP_Validator */
	private $validator;

	/** @var string */
	private $hook_suffix = '';

	public function __construct( BIWEBP_Usage $usage, BIWEBP_Validator $validator ) {
		$this->usage     = $usage;
		$this->validator = $validator;
	}

	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function add_menu() {
		$this->hook_suffix = (string) add_submenu_page(
			'bulk-image-to-webp-converter',
			__( 'WebP History & Diagnostics', 'bulk-image-to-webp-converter' ),
			__( 'History & Diagnostics', 'bulk-image-to-webp-converter' ),
			'upload_files',
			'bulk-image-to-webp-history',
			array( $this, 'render_page' )
		);
	}

	public function enqueue_assets( $hook_suffix ) {
		if ( $this->hook_suffix !== $hook_suffix ) {
			return;
		}
		wp_enqueue_style( 'biwebp-admin', BIWEBP_URL . 'assets/css/webp-admin.css', array(), BIWEBP_VERSION );
		wp_enqueue_script( 'biwebp-history', BIWEBP_URL . 'assets/js/history-admin.js', array(), BIWEBP_VERSION, true );
	}

	public function render_page() {
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_die( esc_html__( 'You do not have permission to view conversion history.', 'bulk-image-to-webp-converter' ) );
		}

		$is_pro    = $this->usage->is_pro();
		$max_bytes = $this->validator->max_bytes( $is_pro );
		$uploads   = wp_upload_dir( null, false );
		$query     = new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'image/webp',
				'posts_per_page' => 50,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- bounded read-only history screen.
					array(
						'key'     => '_biwebp_source_attachment_id',
						'compare' => 'EXISTS',
					),
				),
			)
		);
		$rows = array();
		foreach ( $query->posts as $attachment ) {
			$output_id    = (int) $attachment->ID;
			$output_file  = get_attached_file( $output_id );
			$source_id    = absint( get_post_meta( $output_id, '_biwebp_source_attachment_id', true ) );
			$source_file  = $source_id ? get_attached_file( $source_id ) : '';
			$output_bytes = $output_file && file_exists( $output_file ) ? (int) filesize( $output_file ) : 0;
			$source_bytes = $source_file && file_exists( $source_file ) ? (int) filesize( $source_file ) : 0;
			$rows[]       = array(
				'id'           => $output_id,
				'filename'     => $output_file ? basename( $output_file ) : get_the_title( $output_id ),
				'output_bytes' => $output_bytes,
				'source_id'    => $source_id,
				'source_bytes' => $source_bytes,
				'quality'      => absint( get_post_meta( $output_id, '_biwebp_quality', true ) ),
				'date'         => get_the_date( get_option( 'date_format' ), $output_id ) . ' ' . get_the_time( get_option( 'time_format' ), $output_id ),
			);
		}

		$recent_source = array_sum( wp_list_pluck( $rows, 'source_bytes' ) );
		$recent_output = array_sum( wp_list_pluck( $rows, 'output_bytes' ) );
		$recent_saved  = max( 0, $recent_source - $recent_output );
		?>
		<div class="wrap biwebp-wrap biwebp-history-wrap">
			<h1><?php echo esc_html__( 'WebP History & Diagnostics', 'bulk-image-to-webp-converter' ); ?></h1>
			<p class="biwebp-subtitle"><?php echo esc_html__( 'Review recent WebP files created by this plugin and check whether this browser and WordPress site are ready for conversion.', 'bulk-image-to-webp-converter' ); ?></p>

			<div class="biwebp-history-summary" aria-label="<?php echo esc_attr__( 'Recent conversion summary', 'bulk-image-to-webp-converter' ); ?>">
				<div><span><?php echo esc_html__( 'Generated files', 'bulk-image-to-webp-converter' ); ?></span><strong><?php echo esc_html( number_format_i18n( (int) $query->found_posts ) ); ?></strong></div>
				<div><span><?php echo esc_html__( 'Recent source data', 'bulk-image-to-webp-converter' ); ?></span><strong><?php echo esc_html( size_format( $recent_source ) ); ?></strong></div>
				<div><span><?php echo esc_html__( 'Recent WebP data', 'bulk-image-to-webp-converter' ); ?></span><strong><?php echo esc_html( size_format( $recent_output ) ); ?></strong></div>
				<div><span><?php echo esc_html__( 'Recent data saved', 'bulk-image-to-webp-converter' ); ?></span><strong><?php echo esc_html( size_format( $recent_saved ) ); ?></strong></div>
			</div>

			<section class="biwebp-history-section" aria-labelledby="biwebp-history-title">
				<h2 id="biwebp-history-title"><?php echo esc_html__( 'Recent generated WebP files', 'bulk-image-to-webp-converter' ); ?></h2>
				<p><?php echo esc_html__( 'Newest conversions appear first. This is a read-only view and never deletes or changes Media Library files.', 'bulk-image-to-webp-converter' ); ?></p>
				<?php if ( $rows ) : ?>
					<div class="biwebp-history-table-scroll">
						<table class="widefat striped biwebp-history-table">
							<thead><tr><th><?php echo esc_html__( 'WebP file', 'bulk-image-to-webp-converter' ); ?></th><th><?php echo esc_html__( 'Source', 'bulk-image-to-webp-converter' ); ?></th><th><?php echo esc_html__( 'Savings', 'bulk-image-to-webp-converter' ); ?></th><th><?php echo esc_html__( 'Quality', 'bulk-image-to-webp-converter' ); ?></th><th><?php echo esc_html__( 'Created', 'bulk-image-to-webp-converter' ); ?></th><th><?php echo esc_html__( 'Actions', 'bulk-image-to-webp-converter' ); ?></th></tr></thead>
							<tbody>
							<?php foreach ( $rows as $row ) :
								$change = $row['source_bytes'] > 0 ? round( ( 1 - $row['output_bytes'] / $row['source_bytes'] ) * 100 ) : null;
								/* translators: %d: Source Media Library attachment ID. */
								$source_label = sprintf( __( 'Media #%d', 'bulk-image-to-webp-converter' ), $row['source_id'] );
								/* translators: %d: Percentage size reduction. */
								$smaller_label = sprintf( __( '%d%% smaller', 'bulk-image-to-webp-converter' ), $change );
								/* translators: %d: Percentage size increase. */
								$larger_label = sprintf( __( '%d%% larger', 'bulk-image-to-webp-converter' ), abs( $change ) );
								?>
								<tr>
									<td><div class="biwebp-history-file"><?php echo wp_kses_post( wp_get_attachment_image( $row['id'], array( 56, 56 ), false, array( 'alt' => '' ) ) ); ?><div><strong><?php echo esc_html( $row['filename'] ); ?></strong><small><?php echo esc_html( size_format( $row['output_bytes'] ) ); ?></small></div></div></td>
									<td><?php echo $row['source_id'] ? '<a href="' . esc_url( get_edit_post_link( $row['source_id'], 'raw' ) ) . '">' . esc_html( $source_label ) . '</a>' : esc_html__( 'Local upload', 'bulk-image-to-webp-converter' ); ?><small><?php echo $row['source_bytes'] ? esc_html( size_format( $row['source_bytes'] ) ) : esc_html__( 'Source size unavailable', 'bulk-image-to-webp-converter' ); ?></small></td>
									<td><?php echo null === $change ? esc_html__( 'Not measured', 'bulk-image-to-webp-converter' ) : esc_html( $change >= 0 ? $smaller_label : $larger_label ); ?></td>
									<td><?php echo esc_html( $row['quality'] ? $row['quality'] . '%' : '—' ); ?></td>
									<td><?php echo esc_html( $row['date'] ); ?></td>
									<td><a class="button button-small" href="<?php echo esc_url( get_edit_post_link( $row['id'], 'raw' ) ); ?>"><?php echo esc_html__( 'View in Media Library', 'bulk-image-to-webp-converter' ); ?></a></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php else : ?>
					<div class="biwebp-empty-history"><strong><?php echo esc_html__( 'No conversion history yet', 'bulk-image-to-webp-converter' ); ?></strong><p><?php echo esc_html__( 'Convert an image and its generated WebP will appear here.', 'bulk-image-to-webp-converter' ); ?></p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=bulk-image-to-webp-converter' ) ); ?>"><?php echo esc_html__( 'Open converter', 'bulk-image-to-webp-converter' ); ?></a></div>
				<?php endif; ?>
			</section>

			<section class="biwebp-history-section" aria-labelledby="biwebp-diagnostics-title">
				<div class="biwebp-diagnostics-heading"><div><h2 id="biwebp-diagnostics-title"><?php echo esc_html__( 'Conversion readiness', 'bulk-image-to-webp-converter' ); ?></h2><p><?php echo esc_html__( 'These checks contain technical environment information only—never image contents or license keys.', 'bulk-image-to-webp-converter' ); ?></p></div><button type="button" class="button button-secondary" id="biwebp-download-diagnostics"><?php echo esc_html__( 'Download support report', 'bulk-image-to-webp-converter' ); ?></button></div>
				<dl class="biwebp-diagnostics" id="biwebp-diagnostics" data-plugin-version="<?php echo esc_attr( BIWEBP_VERSION ); ?>">
					<div><dt><?php echo esc_html__( 'Plan', 'bulk-image-to-webp-converter' ); ?></dt><dd data-diagnostic="plan"><?php echo esc_html( $is_pro ? __( 'Pro', 'bulk-image-to-webp-converter' ) : __( 'Free', 'bulk-image-to-webp-converter' ) ); ?></dd></div>
					<div><dt><?php echo esc_html__( 'Plugin limit', 'bulk-image-to-webp-converter' ); ?></dt><dd data-diagnostic="plugin-limit"><?php echo esc_html( size_format( $max_bytes ) . ' per image' ); ?></dd></div>
					<div><dt><?php echo esc_html__( 'WordPress / PHP', 'bulk-image-to-webp-converter' ); ?></dt><dd data-diagnostic="runtime"><?php echo esc_html( get_bloginfo( 'version' ) . ' / ' . PHP_VERSION ); ?></dd></div>
					<div><dt><?php echo esc_html__( 'Host upload limit', 'bulk-image-to-webp-converter' ); ?></dt><dd data-diagnostic="host-limit"><?php echo esc_html( size_format( wp_max_upload_size() ) ); ?></dd></div>
					<div><dt><?php echo esc_html__( 'Uploads path', 'bulk-image-to-webp-converter' ); ?></dt><dd data-diagnostic="uploads"><?php echo empty( $uploads['error'] ) ? esc_html__( 'Available', 'bulk-image-to-webp-converter' ) : esc_html( $uploads['error'] ); ?></dd></div>
					<div><dt><?php echo esc_html__( 'Browser WebP encoder', 'bulk-image-to-webp-converter' ); ?></dt><dd data-diagnostic="browser-webp" id="biwebp-browser-webp"><?php echo esc_html__( 'Checking…', 'bulk-image-to-webp-converter' ); ?></dd></div>
					<div><dt><?php echo esc_html__( 'Refresh-safe queue storage', 'bulk-image-to-webp-converter' ); ?></dt><dd data-diagnostic="queue-storage" id="biwebp-browser-storage"><?php echo esc_html__( 'Checking…', 'bulk-image-to-webp-converter' ); ?></dd></div>
				</dl>
				<p class="description"><?php echo esc_html__( 'If the host upload limit is below the plugin limit, the smaller host value controls successful Media Library saving.', 'bulk-image-to-webp-converter' ); ?></p>
			</section>
		</div>
		<?php
		wp_reset_postdata();
	}
}
