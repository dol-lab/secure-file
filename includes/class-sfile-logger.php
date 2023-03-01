<?php
/**
 * SFile_Cookie class
 *
 * @package sfile
 * @file
 */

/**
 * This class is created to work without WordPress.
 * Used in SFile_Cookie and SFile_Manager Classes
 */
class SFile_Logger {

	/**
	 * If the logger tries to log before WordPress
	 * we set the error_log location the the default wp-location.
	 *
	 * @var boolean
	 */
	private static $init = false;

	/**
	 * The logger can be prefixed on construct so error messages show like
	 * [my Class] my error
	 *
	 * @var string
	 */
	private $prefix = '';

	/**
	 * Wether the logger is verbose or not.
	 *
	 * @var boolean
	 */
	public $verbose = false;

	/**
	 * Init class.
	 *
	 * @param [string|class] $prefix Prefix your log messages with class (name).
	 */
	public function __construct( $prefix = '' ) {
		if ( is_object( $prefix ) ) {
			$this->prefix = get_class( $prefix );
		} else {
			$this->prefix = $prefix;
		}
		return $this;
	}

	/**
	 * Logs things
	 *
	 * @param [string|object|array] $data feed it whatever.
	 * @param string                $descr a description.
	 * @return void
	 */
	public function log( $data, $descr = '' ) {
		if ( ! self::$init ) {
			ini_set( 'error_log', WP_CONTENT_DIR . '/debug.log' );
			ini_set( 'error_reporting', E_ALL );
			self::$init = true;
		}

		if ( $this->verbose ) {
			$descr  = $descr ? $descr . ': ' : '';
			$prefix = $this->prefix;
			error_log( "[$prefix] " . $descr . print_r( $data, true ) );
		}
	}
}
