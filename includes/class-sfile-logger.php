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
	 * The logger can be prefixed on construct so error messages show like "[my Class] my error"
	 *
	 * @var string
	 */
	private $prefix = '';

	/**
	 * Whether the logger is verbose or not.
	 *
	 * @var boolean
	 */
	private $verbose = false;

	/**
	 * Init class.
	 *
	 * @param string|object $prefix Prefix your log messages with class (name).
	 * @param bool|null     $verbose Override verbosity. Defaults to SFILE_DEBUG constant.
	 */
	public function __construct( $prefix = '', $verbose = null ) {
		if ( is_object( $prefix ) ) {
			$this->prefix = get_class( $prefix );
		} else {
			$this->prefix = $prefix;
		}
		$this->verbose = $verbose ?? ( defined( 'SFILE_DEBUG' ) && SFILE_DEBUG );
	}

	/**
	 * Log data with optional description.
	 *
	 * @param mixed  $data  Any loggable data.
	 * @param string $descr Optional description.
	 * @return void
	 */
	public function log( $data, $descr = '' ) {
		if ( ! $this->verbose ) {
			return;
		}

		$descr   = $descr ? $descr . ': ' : '';
		$message = "[{$this->prefix}] {$descr}" . ( is_string( $data ) ? $data : print_r( $data, true ) );

		$this->write( $message );
	}

	/**
	 * Log a critical error, regardless of verbosity.
	 *
	 * Unlike log(), this always writes — serving failures must leave a trace so
	 * an opaque 500 can be diagnosed without first enabling SFILE_DEBUG.
	 *
	 * @param mixed  $data  Any loggable data.
	 * @param string $descr Optional description.
	 * @return void
	 */
	public function error( $data, $descr = '' ) {
		$descr   = $descr ? $descr . ': ' : '';
		$message = "[{$this->prefix}] ERROR: {$descr}" . ( is_string( $data ) ? $data : print_r( $data, true ) );
		$this->write( $message );
	}

	/**
	 * Write a message to the debug log (or PHP's error log without WordPress).
	 *
	 * @param string $message The fully formatted message.
	 * @return void
	 */
	private function write( $message ) {
		if ( defined( 'WP_CONTENT_DIR' ) ) {
			error_log( $message . PHP_EOL, 3, WP_CONTENT_DIR . '/debug.log' );
		} else {
			error_log( $message );
		}
	}
}
