<?php
/**
 * All about checking the Secure-File Configuration.
 *
 * @package Secure-File Package
 */

/**
 * Check PHP version
 */
function sfile_check_php() {
	$php = '5.5.0'; // @see: http://php.net/manual/de/function.password-hash.php .mcrypt_create_iv( 22, MCRYPT_DEV_URANDOM ).
	if ( version_compare( PHP_VERSION, $php, '<' ) ) {
		return sprintf(
			/* translators: 1: PHP version number */
			__( 'This plugin can not be activated because it requires a PHP version greater than %s.', 'sfile' ),
			$php
		);
	}
}

/**
 * Check WordPress version
 */
function sfile_check_wp() {
	 global $wp_version;
	$wp = '4.8';

	if ( version_compare( $wp_version, $wp, '<' ) ) {
		return sprintf(
			/* translators: 1: WordPress version number */
			__( 'It requires a WordPress version greater than %1$s.', 'sfile' ),
			$wp
		);
	}
}

function sfile_check_uploads() {
	if ( defined( 'UPLOADS' ) ) {
		return __( 'Having the constant <code>UPLOADS</code> defined is currently not supported. '.
		'You might have an old db where "ms_files_rewriting" is set "1" or not present (see ms_upload_constants). ' .
		'Things might break.' );
	}
}

/**
 * Check the wp-config file for configuration Problems.
 *
 * @return [string] The problem description.
 */
function sfile_check_config() {
	$not_found = array();

	$msg = '';

	$rgx_check_comment = '^([^#/]*|([^/\n]*/(?!/).*)*)'; // this checks for non existing '//' or '#'.

	// we don't actually want this in the wp-config, but in a server config file outside of any repo.
	if ( ! defined( 'FILE_SALT' ) ) {
		$msg = __( 'Make sure the FILE_SALT constant is defined.', 'sfile' );
	}

	$config_file = dirname( ABSPATH ) . '/wp-config.php';

	if ( ! file_exists( $config_file ) ) {
		return __( 'Your wp-config.php file needs to sit in your root-drectory (dirname(ABSPATH))', 'sfile' );
	}

	// $msg   .= check_defines(); // @todo: add again!
	$dir     = "WP_PLUGIN_DIR . '/secure-file/get-file.php'";
	$require = "require_once $dir;";
	$search  = '^[\t\s ]*' . preg_quote( $require, '%' ); // beginning of line, then only tabs ans spaces.
	$code    = "
		if ( ! empty( WP_PLUGIN_DIR ) ) {
			if ( defined( 'WP_PLUGIN_DIR' ) && ! empty( WP_PLUGIN_DIR ) && file_exists( WP_PLUGIN_DIR . '/secure-file/get-file.php' ) ) {
				require_once WP_PLUGIN_DIR . '/secure-file/get-file.php';
			} else {
				die( 'The file-security plugin does not exist, yet it is referenced in wp-config.php' );
			}
		}
	";
	if ( ! count( sfile_search_in_file( $config_file, $search ) ) ) {
		$msg .= __( 'Please add the following code to your wp-config file:', 'sfile' );
		$msg .= "<code>$code</code><br>";
		$msg .= "Before this:<br>
			<code>
				require_once( ABSPATH . 'wp-settings.php' );
			</code>
		";
	}
	return $msg;

}

/**
 * Check if all necessary things are there.
 *
 * @todo: defines can not be checked like this.
 * - A better aproach: First include the get-file.php.
 * - When this is done load the wp-config.php here.
 * - Add a check to the get-file.php and catch it here.
 *
 * @return void
 */
function check_defines() {
	$defines = array(
		'WP_PLUGIN_DIR',
		'ABSPATH',
		'WP_CONTENT_DIR',
	);

	foreach ( $defines as $define ) {
		$matches = sfile_search_in_file( $config_file, $rgx_check_comment . 'define[^,]*' . $define );

		if ( ! count( $matches ) ) {
			array_push( $not_found, $define );
		}
	}

	if ( count( $not_found ) ) {
		$missing = implode( '<br>', $not_found );
		$msg    .= __( 'Please define the following constant(s) in wp-config.php:', 'sfile' ) . '<br><code>' . $missing . '</code><br>';
	}
}

/**
 * Check the installation's htaccess file
 *
 * @todo: We could add handling having UPLOADS defined, but that doesn't really make sense for a network anyway...
 */
function sfile_check_htaccess() {
	$htaccess_file = dirname( ABSPATH ) . '/.htaccess';
	if ( ! file_exists( $htaccess_file ) ) {
		return __( 'Make sure your htaccess exists', 'sfile' );
	}
	$rew_rule = 'RewriteRule ^([_0-9a-zA-Z-]+/)?uploads/(.+) wp-config.php?sfile=$2 [L]';
	$search   = '^' . preg_quote( $rew_rule, '%' );

	if ( ! count( sfile_search_in_file( $htaccess_file, $search ) ) ) {
		$msg  = __( 'Please add the following code to your .htaccesss file:', 'sfile' );
		$msg .= "<code>$rew_rule</code><br>";
		$msg .= 'Before this:<br>
			<code>
				RewriteCond %{REQUEST_FILENAME} -f [OR] <br>
				RewriteCond %{REQUEST_FILENAME} -d <br>
				RewriteRule ^ - [L] <br>
			</code>
		';
		return $msg;
	}
}

/**
 * Helper function to search contents of file.
 *
 * @param  [string] $file      path and filename of the searched file.
 * @param  [string] $searchfor The string to search for.
 * @return [array] The matches.
 */
function sfile_search_in_file( $file, $searchfor ) {
	// get the file contents, assuming the file to be readable ( and exist).
	$contents = file_get_contents( $file );
	// escape special characters in the query.
	$pattern = $searchfor;
	// finalise the regular expression, matching the whole line.
	$pattern = "%$pattern%m";
	// search, and store all matching occurences in $matches.
	preg_match_all( $pattern, $contents, $matches );
	return $matches[0];
}
