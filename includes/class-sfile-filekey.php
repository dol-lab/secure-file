<?php
/**
 * Class SFile_FileKey
 *
 * Requires the SFile
 *
 * @package sfile
 * @file
 */

require_once 'class-sfile-logger.php';

/**
 * This class is created to work without WordPress.
 * Add a key to a protected file (via queryvar) to make it accessible
 */
class SFile_FileKey {

	/**
	 * The url of the requested file.
	 *
	 * @var [type]
	 */
	private $file_url;

	/**
	 * Log steps.
	 *
	 * @var [SFile_Logger] contains an instance of the SFile_Logger Class
	 */
	private $logger;

	/**
	 * Set values for clss
	 *
	 * @param [type] $file_url the url of the requested file.
	 */
	public function __construct( $file_url ) {

		$this->logger   = new SFile_Logger( $this );
		$this->key      = $key;
		$this->file_url = $file_url;

		// @todo we probably need a valid_until key which needs to be within the hash.
	}


	public function can_access( $check_string ) {
		if ( make_key( $check_string ) === $check_string ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Create a key for a file
	 *
	 * @param string $string the string that needs to be hashed (a file url in our case).
	 * @return string
	 */
	public function make_key( $string ) {

		$slat = $_SERVER['SERVER_SOFTWARE'] . FILE_SALT;
		$salt = hash( 'sha256', $salt );

		$options = [
			'cost' => 10,
			'salt' => $salt, // mcrypt_create_iv( 22, MCRYPT_DEV_URANDOM ).
		];

		/**
		 * PASSWORD_BCRYPT is a slow hash. It does not need to be very fast in this case, because
		 * files with keys aren't used very often.
		 */
		return password_hash( $string, PASSWORD_BCRYPT, $options );
	}

}
