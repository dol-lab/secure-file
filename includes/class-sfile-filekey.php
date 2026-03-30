<?php
/**
 * File-level key handling for secure-file plugin.
 *
 * @package sfile
 */

if ( ! defined( 'FILE_SALT' ) ) {
	define( 'FILE_SALT', '' );
}

// Logger Class.
require_once 'class-sfile-logger.php';

/**
 * Handles secure, time-limited access keys for protected files.
 * Designed to function independently of the WordPress core load.
 */
class SFile_FileKey {

	/** @var string The URL or path of the requested file.*/
	private $file_url;

	/** @var string Derived signing key (via HKDF) to protect the master salt. */
	private $derived_key;

	/**
	 * Initialize the key handler for a specific file.
	 *
	 * @param string $file_url The URL or path of the requested file.
	 */
	public function __construct( $file_url ) {
		$this->file_url = $file_url;

		// Enforce a valid salt to prevent predictable hashes.
		$salt = ( defined( 'FILE_SALT' ) && FILE_SALT !== '' ) ? FILE_SALT : 'sfile_fallback_salt_replace_me';

		// Derive a specific key for file signing using HKDF.
		$this->derived_key = hash_hkdf( 'sha256', $salt, 32, 'sfile-filekey' );
	}

	/**
	 * Generates a signature and expiration timestamp for the file.
	 * @param int $ttl_seconds Time to live in seconds (default: 7200 / 2 hours).
	 * @return array Contains 'expires' timestamp and the HMAC 'signature'.
	 */
	public function generate_credentials( $ttl_seconds = 7200 ) {
		$expires = time() + $ttl_seconds;

		// Bind the file URL and expiration time together.
		$data      = $this->file_url . '|' . $expires;
		$signature = hash_hmac( 'sha256', $data, $this->derived_key );

		return array(
			'expires'   => $expires,
			'signature' => $signature,
		);
	}

	/**
	 * Verifies if the provided signature is valid and the link hasn't expired.
	 *
	 * @param string $provided_signature The signature passed in the request.
	 * @param int    $expires            The expiration timestamp passed in the request.
	 * @return bool True if valid and unexpired, false otherwise.
	 */
	public function verify_access( $provided_signature, $expires ) {
		// 1. Check if the timestamp has passed.
		if ( time() > (int) $expires ) {
			return false;
		}

		// 2. Reconstruct the expected signature based on the file and provided timestamp.
		$data               = $this->file_url . '|' . $expires;
		$expected_signature = hash_hmac( 'sha256', $data, $this->derived_key );

		// 3. Compare using a timing-safe function.
		return hash_equals( $expected_signature, $provided_signature );
	}
}
