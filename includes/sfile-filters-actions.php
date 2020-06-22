<?php
/**
 * Contains filters and actions for the secure-file plugin.
 *
 * @package plugin secure-file
 */

/**
 * Triggered on plugin activation.
 * Dies and disables Plugin if requirements are not met.
 *
 * @return void
 */
function sfile_activate_plugin() {
	require_once 'sfile-install-checks.php';

	$msg   = array();
	$msg[] = sfile_check_wp();
	$msg[] = sfile_check_php();
	$msg[] = sfile_check_config();
	$msg[] = sfile_check_htaccess();
	$msg[] = sfile_check_uploads();

	$msg = array_filter( $msg );

	if ( ! empty( $msg ) ) {
		array_unshift( $msg, __( 'The plugin could not be activated.', 'sfile' ) );
		$msg[] = '<a href="' . admin_url( 'plugins.php' ) . '">' . __( 'go back', 'sfile' ) . '</a>';
		deactivate_plugins( basename( __FILE__ ) );
		$elems = implode( '</p><p>', $msg );
		$msg   = "
			<p>$elems</p>
			<style>code{background-color:whitesmoke;display:inline-block;padding:10px;}</style>
		";
		wp_die( $msg );
	}
}


/** Delete all secure-file cookies from the client on logout */
function sfile_delete_all_cookies() {
	require_once 'class-sfile-logger.php';
	require_once 'class-sfile-cookie.php';
	$cookie = new SFile_Cookie( '', array() );
	$cookie->remove_all_file_cookies();
}

/**
 * When a user uploads a file with the name "f..oo...txt" consecutive dots are reduced to one ("f.oo.txt").
 * This prevents errors as SFile_Manager - Class strips ".." to prevent accessing parent directories.
 *
 * @param string $filename The name of the uploaded file.
 * @return string
 */
function sfile_strip_consecutive_dots_in_filename( $filename ) {
	return preg_replace( '/\.+/', '.', $filename );
}

/**
 * Triggered by the filter upload_dir.
 *
 * This is basically a copy of the _wp_upload_dir function.
 *
 * This handles an issue with WordPress where it handles uploads for the main_site( blog_id = 1)
 * differently than every other blog. instead of putting uploads in uploads/sites/1/year/...
 * it puts them direyly in the uploads folder like uploads/year/...
 *
 * As we manage access via upload path we don't want this...
 *
 * @param array $uploads {
 *     Array of information about the upload directory.
 *
 *     @type string       $path    Base directory and subdirectory or full path to upload directory.
 *     @type string       $url     Base URL and subdirectory or absolute URL to upload directory.
 *     @type string       $subdir  Subdirectory if uploads use year/month folders option is on.
 *     @type string       $basedir Path without subdir.
 *     @type string       $baseurl URL path without subdir.
 *     @type string|false $error   False or error message.
 * }
 * @return array
 */
function sfile_change_main_site_upload_dir( $uploads ) {
	if ( 1 !== get_current_blog_id() ) {
		return $uploads;
	}
	if ( false !== strpos( $uploads['path'], '/sites/' ) ) { // we already filtered the value.
		return $uploads;
	}

	$siteurl     = get_option( 'siteurl' );
	$upload_path = trim( get_option( 'upload_path' ) );

	if ( empty( $upload_path ) || 'wp-content/uploads' == $upload_path ) {
		$dir = WP_CONTENT_DIR . '/uploads';
	} elseif ( 0 !== strpos( $upload_path, ABSPATH ) ) {
		// $dir is absolute, $upload_path is (maybe) relative to ABSPATH.
		$dir = path_join( ABSPATH, $upload_path );
	} else {
		$dir = $upload_path;
	}

	$url = get_option( 'upload_url_path' );
	if ( ! $url ) {
		if ( empty( $upload_path ) || ( 'wp-content/uploads' == $upload_path ) || ( $upload_path == $dir ) ) {
			$url = WP_CONTENT_URL . '/uploads';
		} else {
			$url = trailingslashit( $siteurl ) . $upload_path;
		}
	}
	if ( defined( 'UPLOADS' ) && ! ( is_multisite() && get_site_option( 'ms_files_rewriting' ) ) ) {
		$dir = ABSPATH . UPLOADS;
		$url = trailingslashit( $siteurl ) . UPLOADS;
	}

	if ( defined( 'MULTISITE' ) ) {
		$ms_dir = '/sites/' . get_current_blog_id();
	} else {
		$ms_dir = '/' . get_current_blog_id();
	}

	$dir .= $ms_dir;
	$url .= $ms_dir;
	$basedir = $dir;
	$baseurl = $url;
	$subdir = $uploads['subdir'];

	$dir .= $subdir;
	$url .= $subdir;

	$uploads = array(
		'path'    => $dir,
		'url'     => $url,
		'subdir'  => $subdir,
		'basedir' => $basedir,
		'baseurl' => $baseurl,
		'error'   => false,
	);
	return $uploads;
}
