<?php
/**
 * Secure File
 *
 * @package Secure-File Package
 *
 * @wordpress-plugin
 * Plugin Name: Secure File
 * Plugin URI: https://github.com/dol-lab/secure-file
 * Version: 0.2
 * Author: Vitus Schuhwerk
 * Description: This plugin is not plug and play. Specify File security. This Plugin exposes a filter "secure_file_cookie". It's designed for a post-mu network.
 * Text Domain: sfile
 * License: GPLv3
 *
 * For todos and prerequisites check readme.md.
 *
 * To better understand the code here check file naming conventions:
 * @see https://stackoverflow.com/questions/2235173/file-name-path-name-base-name-naming-standard-for-pieces-of-a-path
 * @todo
 * @todo: add filter sanitize_file_name to not allow filenames with multiple dots: preg_replace( '/\.+/', '.', $new_filename );
 */

/** Delete all secure-file cookies from the client on logout */
function sfile_delete_all_cookies() {
	require_once 'includes/class-sfile-logger.php';
	require_once 'includes/class-sfile-cookie.php';
	$cookie = new SFile_Cookie( '', array() );
	$cookie->remove_all_file_cookies();
}
add_action( 'wp_logout', 'sfile_delete_all_cookies' );

/**
 * Triggered on plugin activation.
 * Dies and disables Plugin if requirements are not met.
 *
 * @return void
 */
function sfile_activate_plugin() {
	require_once 'includes/sfile-install-checks.php';

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
register_activation_hook( __FILE__, 'sfile_activate_plugin' );

add_filter( 'sanitize_file_name', 'sfile_strip_consecutive_dots_in_filename' );

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
