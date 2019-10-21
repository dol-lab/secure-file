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
 * @todo: The Manager returns an image on error.
 */

if ( isset( $_GET['sfile'] ) ) {

	require_once 'includes/class-sfile-logger.php';
	require_once 'includes/class-sfile-status-codes.php';
	require_once 'includes/class-sfile-cookie.php';
	require_once 'includes/class-sfile-serve.php';
	require_once 'includes/class-sfile-manager.php';

	$key = isset( $_GET['key'] ) ? $_GET['key'] : false;
	// @todo: add a key to a file...
	$pv = new SFile_Manager( $_GET['sfile'] );
	$pv->maybe_serve_file();

}
