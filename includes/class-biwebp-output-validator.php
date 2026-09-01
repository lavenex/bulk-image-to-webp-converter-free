<?php
/** Strict validation for browser-generated WebP files. */

defined( 'ABSPATH' ) || exit;

class BIWEBP_Validator {
	const FREE_MAX_BYTES = 10485760;

	/**
	 * Return the active per-image input/output ceiling.
	 *
	 * @return int
	 */
	public function plan_max_bytes() {
		return self::FREE_MAX_BYTES;
	}

	/**
	 * Return the lower of the plan ceiling and the current WordPress host limit.
	 *
	 * @return int
	 */
	public function max_bytes() {
		$plan_max = $this->plan_max_bytes();
		$host_max = function_exists( 'wp_max_upload_size' ) ? (int) wp_max_upload_size() : 0;

		return $host_max > 0 ? min( $plan_max, $host_max ) : $plan_max;
	}

	/**
	 * Validate a temporary upload and return its dimensions and size.
	 *
	 * @param array $file A normalized entry from $_FILES.
	 * @return array|WP_Error
	 */
	public function validate( $file ) {
		if ( ! is_array( $file ) || empty( $file['tmp_name'] ) ) {
			return new WP_Error( 'biwebp_missing_file', __( 'The generated WebP file is missing.', 'bulk-image-to-webp-converter' ) );
		}

		$error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
		if ( UPLOAD_ERR_OK !== $error ) {
			return new WP_Error( 'biwebp_upload_error', __( 'The generated WebP could not be uploaded for validation.', 'bulk-image-to-webp-converter' ) );
		}

		$size      = isset( $file['size'] ) ? (int) $file['size'] : 0;
		$max_bytes = $this->max_bytes();
		if ( $size <= 0 || $size > $max_bytes ) {
			$limit = function_exists( 'size_format' ) ? size_format( $max_bytes ) : (string) $max_bytes . ' bytes';
			/* translators: %s: Formatted maximum generated WebP size. */
			return new WP_Error( 'biwebp_invalid_size', sprintf( __( 'The generated WebP is empty or larger than %s.', 'bulk-image-to-webp-converter' ), $limit ) );
		}

		$header = file_get_contents( $file['tmp_name'], false, null, 0, 12 );
		if ( false === $header || 12 !== strlen( $header ) || 'RIFF' !== substr( $header, 0, 4 ) || 'WEBP' !== substr( $header, 8, 4 ) ) {
			return new WP_Error( 'biwebp_invalid_signature', __( 'The generated file is not a valid WebP image.', 'bulk-image-to-webp-converter' ) );
		}

		$image = wp_getimagesize( $file['tmp_name'] );
		if ( ! is_array( $image ) || empty( $image['mime'] ) || 'image/webp' !== $image['mime'] ) {
			return new WP_Error( 'biwebp_invalid_mime', __( 'The generated file did not pass WebP image validation.', 'bulk-image-to-webp-converter' ) );
		}

		$width  = isset( $image[0] ) ? (int) $image[0] : 0;
		$height = isset( $image[1] ) ? (int) $image[1] : 0;
		if ( $width < 1 || $height < 1 ) {
			return new WP_Error( 'biwebp_invalid_dimensions', __( 'The generated WebP has invalid dimensions.', 'bulk-image-to-webp-converter' ) );
		}

		return array( 'size' => $size, 'width' => $width, 'height' => $height );
	}
}
