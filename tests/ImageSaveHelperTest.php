<?php
/**
 * Regression tests for the shared base64 image-save helper.
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abcc_test(
	'image save helper writes a valid PNG and returns its URL',
	function () {
		// Minimal valid PNG header payload.
		$png = "\x89PNG\r\n\x1a\n" . str_repeat( "\x00", 16 );
		$url = abcc_save_base64_image( base64_encode( $png ), 'image/png', 'testpng' );

		abcc_assert_true( is_string( $url ), 'Valid PNG must save and return a URL.' );
		abcc_assert_true( false !== strpos( $url, 'testpng-' ), 'URL must contain the filename prefix.' );
		abcc_assert_true( '.png' === substr( $url, -4 ), 'PNG must get a .png extension.' );

		$file = wp_upload_dir()['path'] . '/' . basename( $url );
		abcc_assert_true( file_exists( $file ), 'PNG file must exist on disk.' );
		abcc_assert_same( $png, file_get_contents( $file ), 'Saved bytes must match the decoded payload.' );
		unlink( $file );
	}
);

abcc_test(
	'image save helper writes valid JPEG and WEBP with correct extensions',
	function () {
		$jpeg = "\xFF\xD8\xFF\xE0" . str_repeat( "\x00", 16 );
		$webp = 'RIFF' . "\x20\x00\x00\x00" . 'WEBP' . str_repeat( "\x00", 12 );

		$jpeg_url = abcc_save_base64_image( base64_encode( $jpeg ), 'image/jpeg', 'testjpg' );
		$webp_url = abcc_save_base64_image( base64_encode( $webp ), 'image/webp', 'testwebp' );

		abcc_assert_true( is_string( $jpeg_url ) && '.jpg' === substr( $jpeg_url, -4 ), 'JPEG must save with .jpg extension.' );
		abcc_assert_true( is_string( $webp_url ) && '.webp' === substr( $webp_url, -5 ), 'WEBP must save with .webp extension.' );

		unlink( wp_upload_dir()['path'] . '/' . basename( $jpeg_url ) );
		unlink( wp_upload_dir()['path'] . '/' . basename( $webp_url ) );
	}
);

abcc_test(
	'image save helper rejects invalid base64',
	function () {
		abcc_assert_false(
			abcc_save_base64_image( '!!!not-base64!!!', 'image/png', 'testbad' ),
			'Invalid base64 must be rejected.'
		);
	}
);

abcc_test(
	'image save helper rejects MIME-mismatched magic bytes',
	function () {
		$jpeg = "\xFF\xD8\xFF\xE0" . str_repeat( "\x00", 16 );

		abcc_assert_false(
			abcc_save_base64_image( base64_encode( $jpeg ), 'image/png', 'testmismatch' ),
			'JPEG bytes declared as PNG must be rejected.'
		);
	}
);

abcc_test(
	'image save helper rejects unsupported MIME types',
	function () {
		abcc_assert_false(
			abcc_save_base64_image( base64_encode( 'GIF89a' ), 'image/gif', 'testgif' ),
			'Unsupported MIME type must be rejected.'
		);
	}
);

abcc_test(
	'featured image attaches from a local file without an HTTP round trip',
	function () {
		$dir = wp_upload_dir();
		wp_mkdir_p( $dir['path'] );
		$path = $dir['path'] . '/abcc-test-featured.png';
		// 1x1 transparent PNG.
		file_put_contents( $path, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==' ) );

		$post_id = wp_insert_post( array( 'post_type' => 'post', 'post_title' => 'Has an image' ) );

		// No canned HTTP response is queued: if the implementation makes a
		// request, wp_remote_post returns the 'no_canned_response' WP_Error and
		// this fails — which is exactly the regression we're guarding.
		$attachment_id = abcc_attach_local_image_to_post( $post_id, $path, 'Alt text here' );

		abcc_assert_true( (int) $attachment_id > 0, 'Attachment should be created from local bytes.' );
		abcc_assert_same(
			'Alt text here',
			get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			'Alt text should be stored on the attachment.'
		);
		abcc_assert_same(
			null,
			$GLOBALS['abcc_http_last_request'],
			'Attaching a local file must not perform an HTTP request.'
		);

		unlink( $path );
	}
);

abcc_test(
	'set_featured_image resolves uploads URLs to local paths without HTTP',
	function () {
		// Drive the REAL entry point with the value abcc_save_base64_image
		// actually returns (an uploads URL) — not the helper directly.
		$png = "\x89PNG\r\n\x1a\n" . str_repeat( "\x00", 16 );
		$url = abcc_save_base64_image( base64_encode( $png ), 'image/png', 'integr' );
		abcc_assert_true( is_string( $url ), 'Precondition: image saved.' );

		$post_id = wp_insert_post(
			array(
				'post_type'  => 'post',
				'post_title' => 'Image target',
			)
		);

		$GLOBALS['abcc_http_last_request'] = null;

		$attachment_id = abcc_set_featured_image( $post_id, $url, 'alt' );

		abcc_assert_true( false !== $attachment_id, 'The generated image must attach.' );
		abcc_assert_same(
			null,
			$GLOBALS['abcc_http_last_request'],
			'Attaching our own uploaded file must not make an HTTP request (self-HTTP fails on local/firewalled installs).'
		);

		$file = wp_upload_dir()['path'] . '/' . basename( $url );
		if ( file_exists( $file ) ) {
			unlink( $file );
		}
	}
);
