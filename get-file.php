<?php
/**
 * Queryvar to file
 *
 * @package sfile
 * @file
 *
 * This file is created to work without WordPress.
 * The installation's .htaccess file redirects all files from the /uploads folder to
 * wp-config.php?sfile=PATH which includes this file.
 *
 * The Manager returns the file or an error message.
 *
 */

// Nonces intentionally not used: this is a read-only file-serving endpoint targeted by <img>/<a>
// tags across the site. Nonces expire (breaks caching/shared links), are per-user (breaks shared
// previews), and wp_verify_nonce() isn't even loaded on the fast path (valid-cookie bypass of WP).
// CSRF protection is not meaningful here — there is no state change; authorization is enforced by
// the encrypted/HMAC-signed session cookie (SFile_Cookie) and the secure_file_cookie filter.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- see note above.
if ( isset( $_GET['sfile'] ) ) {

	require_once 'includes/class-sfile-logger.php';
	require_once 'includes/class-sfile-status-codes.php';
	require_once 'includes/class-sfile-cookie.php';
	require_once 'includes/class-sfile-serve.php';
	require_once 'includes/class-sfile-manager.php';

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput,WordPress.Security.NonceVerification.Recommended -- runs without WordPress; sanitized via filter_input; nonce N/A (see top of file).
	$key = isset( $_GET['key'] ) ? filter_input( INPUT_GET, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) : false;
	// Path sanitization (directory traversal) is handled inside SFile_Manager via realpath().
	// FILTER_DEFAULT is used here because FILTER_SANITIZE_URL strips non-ASCII bytes (e.g. soft hyphens
	// in filenames like "Screenshot-123"), breaking valid UTF-8 paths before they reach realpath().
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput,WordPress.Security.NonceVerification.Recommended -- runs without WordPress; validated via realpath() in SFile_Manager; nonce N/A (see top of file).
	$sfile = filter_input( INPUT_GET, 'sfile', FILTER_DEFAULT );
	$pv    = new SFile_Manager( $sfile );
	$pv->maybe_serve_file();

}
