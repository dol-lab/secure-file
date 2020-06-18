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

require_once 'includes/sfile-filters-actions.php';

add_filter( 'upload_dir', 'sfile_change_main_site_upload_dir', 10, 1 );
add_action( 'wp_logout', 'sfile_delete_all_cookies' );
add_filter( 'sanitize_file_name', 'sfile_strip_consecutive_dots_in_filename' );
register_activation_hook( __FILE__, 'sfile_activate_plugin' );
