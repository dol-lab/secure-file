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

if ( isset( $_GET['sfile'] ) ) {

	require_once 'includes/class-sfile-logger.php';
	require_once 'includes/class-sfile-status-codes.php';
	require_once 'includes/class-sfile-cookie.php';
	require_once 'includes/class-sfile-serve.php';
	require_once 'includes/class-sfile-manager.php';

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- runs without WordPress; sanitized via filter_input.
	$key = isset( $_GET['key'] ) ? filter_input( INPUT_GET, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) : false;
	// Path sanitization (directory traversal) is handled inside SFile_Manager.
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- runs without WordPress; sanitized via filter_input.
	$sfile = filter_input( INPUT_GET, 'sfile', FILTER_SANITIZE_URL );
	$pv    = new SFile_Manager( $sfile );
	$pv->maybe_serve_file();

}
