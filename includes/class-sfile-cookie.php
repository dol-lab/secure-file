<?php
/**
 * Cookie handling for secure-file plugin.
 *
 * @package sfile
 *
 * @todo: somebody could have overlapping cookies and would always be blocked [ .kisd.de vs spaces.kisd.de ]? check all relevant cookies?
 */

if ( ! defined( 'FILE_SALT' ) ) {
	define( 'FILE_SALT', '' );
}

// Logger Class.
require_once 'class-sfile-logger.php';

/**
 * This Class is created to work without WordPress (it can init wp though).
 * Reads, validates, and deletes Cookies.
 * Requires SFile_Logger class to work.
 */
class SFile_Cookie {

	/**
	 * The prefix for the cookie (client side)
	 *
	 * @var string
	 */
	private static $prefix = 'pv_';

	/**
	 * Path to the requested file starting from uploads (exclusive) like
	 * array('sites', '999', '2018', ... )
	 *
	 * @var array
	 */
	private $upload_subdir_arr;

	/**
	 * Instance of the Logger Class
	 *
	 * @var SFile_Logger
	 */
	public $logger;

	/**
	 * Setup the SFile_Cookie Class.
	 *
	 * @param string $abs_path Absolute path to the requested file.
	 * @param array  $upload_subdir_arr Path segments from uploads dir.
	 */
	public function __construct( $abs_path, array $upload_subdir_arr ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed
		$this->upload_subdir_arr = $upload_subdir_arr;
		$this->logger            = new SFile_Logger( $this );
	}

	/**
	 * Check if the client has a valid cookie.
	 *
	 * @return bool
	 */
	public function has_valid_cookie() {

		$cookie_username = $this->extract_username();

		if ( ! $cookie_username ) {
			$cookie_username = $this->get_anon_user();
		}

		$cookie_key = $this->get_client_cookie( $this->upload_subdir_arr );

		$readable_dir = implode( '/', $this->upload_subdir_arr );
		if ( $cookie_key ) {
			if ( $this->is_cookie_valid( $cookie_key, $cookie_username ) ) {
				$this->logger->log( "Vaid cookie [$cookie_key] found for user $cookie_username and dir: $readable_dir" );
				return true;
			} else {
				$this->remove_client_cookie( $cookie_key );
				$this->logger->log( "Your cookie [$cookie_key] was not valid. We destroyed it." );
			}
		}
		$this->logger->log( "No vaid cookie found for user '$cookie_username' and dir '$readable_dir'" );
		return false;
	}

	/**
	 * Create a Cookie for the user if she has access rights.
	 * You can access all WP-Functions after requiring wp-settings.php.
	 *
	 * @param array $dir the directory array.
	 * @param int   $valid_minutes The amount of minutes the cookie is valid.
	 * @return void
	 */
	public function make_cookie( $dir, $valid_minutes ) {

		require_once ABSPATH . 'wp-settings.php';

		if ( is_user_logged_in() ) {
			$username = wp_get_current_user()->data->user_login;
		} else {
			$username = $this->get_anon_user();
		}

		$key_name = $this->make_cookie_key( $dir );
		$value    = $this->make_cookie_value( $username, $key_name, $valid_minutes );
		$expire   = time() + $valid_minutes * 60;

		$domain   = '';
		$secure   = is_ssl();
		$samesite = 'Strict';
		$path     = '/';

		setcookie(
			$key_name,
			$value,
			array(
				'expires'  => $expire,
				'path'     => $path,
				'domain'   => $domain,
				'secure'   => $secure,
				'httponly' => true,
				'samesite' => $samesite,
			)
		);
		$this->logger->log( "User $username receives a key for $valid_minutes mins for dir: " . implode( '/', $dir ) );
	}

	/**
	 * Validates an existing cookie
	 *
	 * @param string $key_name The key of the cookie.
	 * @param string $pretended_username The username the cookie contains.
	 * @return boolean
	 */
	private function is_cookie_valid( $key_name, $pretended_username ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- cookie value is decrypted and validated below.
		$value          = isset( $_COOKIE[ $key_name ] ) ? rawurldecode( $_COOKIE[ $key_name ] ) : '';
		$cookie_content = $this->no_db_crypt( $value, $key_name, false );

		if ( ! $cookie_content ) {
			$this->logger->log( "Something is wrong with your cookie [$key_name]. Couldn't decrypt." );
			return false;
		}

		$data = explode( '|', $cookie_content );

		if ( count( $data ) !== 4 ) {
			$this->logger->log( "Something is wrong with your cookie [$key_name]. Couldn't decrypt." );
			return false;
		}
		$cdata = array(
			'hash'      => $data[0],
			'username'  => $data[1],
			'key_name'  => $data[2],
			'timestamp' => $data[3],
		);

		// cheap comparisons first.
		if ( $cdata['username'] !== $pretended_username ) {
			$this->logger->log( "The username of the login-cookie does not match the username stored in the cooke [$key_name]." );
			return false;
		}

		if ( $cdata['key_name'] !== $key_name ) {
			$this->logger->log( "You cookie  key name does not match the one stored in the cookie [$key_name]." );
			return false;
		}

		if ( ( (int) $cdata['timestamp'] - time() ) < 0 ) {
			$this->logger->log( 'Your cookie looks old. Old cookies are no good cookies. Nobody likes them.' );
			return false;
		}

		/**
		 * Timestamp is validated via the hash.
		 * so if somebody forged a cookie (by cracking the sha encryption) and manually
		 * added a timestamp the hash would be wrong an the cookie not valid.
		 */
		$correct_hash = $this->no_db_hash( $cdata['username'], $cdata['key_name'], $cdata['timestamp'] );
		if ( ! hash_equals( $correct_hash, $cdata['hash'] ) ) {
			$this->logger->log( 'hash mismatch' );
			return false;
		}

		// we validated the timestamp via the hash.
		return true;
	}

	/**
	 * The cookie value contains a hashed secret, the username,
	 * and the time the cookie is valid. This info is encrypted.
	 * The hashed secret also depends on the timestamp.
	 *
	 * @param string $username The username.
	 * @param string $key_name The cookie key name.
	 * @param int    $valid_mins Minutes the cookie is valid.
	 * @return string
	 */
	private function make_cookie_value( $username, $key_name, $valid_mins ) {
		$timestamp = time() + $valid_mins * 60;
		$hash      = $this->no_db_hash( $username, $key_name, $timestamp );
		$c_string  = $hash . '|' . $username . '|' . $key_name . '|' . $timestamp;
		$val       = $this->no_db_crypt( $c_string, $key_name );
		if ( ! $val ) {
			$this->logger->log( 'Something went wrong encrypting the cookie.' );
			die( 'Something went wrong encrypting the cookie.' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		return $val;
	}

	/**
	 * Create a hash from username, key name and timestamp.
	 *
	 * @param string $username  The username.
	 * @param string $key_name  The cookie key name.
	 * @param int    $timestamp The expiry timestamp.
	 * @return string
	 */
	private function no_db_hash( $username, $key_name, $timestamp ) {
		$data = $username . '|' . $key_name . '|' . $timestamp;
		return str_replace( '|', '', hash_hmac( 'sha256', $data, $this->file_salt() ) );
	}

	/**
	 * Return a non-empty FILE_SALT, or fail loudly.
	 *
	 * FILE_SALT is the only secret keying cookie crypto. When it is undefined or
	 * empty (e.g. missing from .env) PHP 8.1+ turns hash_hkdf()/hash_hmac() into a
	 * fatal ValueError, which surfaces as an opaque empty-body 500 with no log.
	 * Validate it once, in one place, with an actionable message instead.
	 *
	 * @return string The validated salt.
	 */
	private function file_salt() {
		if ( ! defined( 'FILE_SALT' ) || '' === (string) FILE_SALT ) {
			$msg = 'FILE_SALT is undefined or empty — define a non-empty FILE_SALT (e.g. in your .env). '
				. 'Secure-file cannot serve files without it.';
			$this->logger->error( $msg );
			// Static internal message, no user input — runs on the no-WP fast path where esc_* may be absent.
			die( $msg ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		return FILE_SALT;
	}

	/**
	 * Encrypt and Decrypt (openssl) a String
	 *
	 * @param string  $input_string The string you want to en-/decrypt.
	 * @param string  $iv           A non-NULL Initialization Vector.
	 * @param boolean $encrypt      Set false to decrypt.
	 * @return string
	 */
	private function no_db_crypt( $input_string, $iv, $encrypt = true ) {

		$salt = $this->file_salt();

		// Derive a secure key using HKDF based on FILE_SALT.
		$key = hash_hkdf( 'sha256', $salt, 32, 'sfile-cookie-crypt' );

		// Use the $iv (key_name) to derive a deterministic IV (must be 16 bytes for AES-256-CBC).
		$encrypt_iv = substr( hash_hmac( 'sha256', $iv, $salt ), 0, 16 );

		$encrypt_method = 'AES-256-CBC';

		if ( $encrypt ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- used for cookie transport encoding.
			$output = base64_encode( openssl_encrypt( $input_string, $encrypt_method, $key, 0, $encrypt_iv ) );
		} else {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- used for cookie transport decoding.
			$output = openssl_decrypt( base64_decode( $input_string ), $encrypt_method, $key, 0, $encrypt_iv );
		}
		return $output;
	}

	/**
	 * Create a cookie key from the upload subdirectory array.
	 *
	 * @param array       $upload_subdir_arr The path segments.
	 * @param boolean|int $limit             Limit the depth of the path.
	 * @return string
	 */
	private function make_cookie_key( $upload_subdir_arr, $limit = false ) {
		if ( $limit ) {
			$upload_subdir_arr = array_slice( $upload_subdir_arr, 0, $limit );
		}
		return self::$prefix . implode( '_', $upload_subdir_arr );
	}

	/**
	 * Get the most specific cookie key which matches the directory the user is trying to access.
	 * Searches from most specific to least specific, returning on first match.
	 *
	 * @param array $upload_subdir_arr the directory array [blogid, year, month, ...].
	 * @return string|false the key, which is saved in the client cookie or false.
	 */
	private function get_client_cookie( $upload_subdir_arr ) {
		$segments      = $upload_subdir_arr;
		$segment_count = count( $segments );
		while ( $segment_count ) {
			$key = self::$prefix . implode( '_', $segments );
			if ( isset( $_COOKIE[ $key ] ) ) {
				return $key;
			}
			array_pop( $segments );
			--$segment_count;
		}
		return false;
	}

	/**
	 * Remove all cookies that were created by this class.
	 *
	 * @return void
	 */
	public function remove_all_file_cookies() {
		$removed = array();
		foreach ( $_COOKIE as $key => $cookie_value ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			if ( strpos( $key, self::$prefix ) === 0 ) {
				$removed[] = $key;
				$this->remove_client_cookie( $key );
			}
		}
		if ( empty( $removed ) ) {
			$this->logger->log( 'Tried to remove all Cookies, but there were none.' );
		} else {
			$removed = implode( ', ', $removed );
			$this->logger->log( "User logged out. Removed all Cookies ($removed)" );
		}
	}

	/**
	 * Remove a cookie
	 *
	 * @param string $key The key name of the cookie you want to remove.
	 * @return void
	 */
	private function remove_client_cookie( $key ) {
		if ( isset( $_COOKIE[ $key ] ) ) {
			unset( $_COOKIE[ $key ] );
			setcookie(
				$key,
				'',
				array(
					'expires'  => time() - 3600,
					'path'     => '/',
					'secure'   => true,
					'httponly' => true,
					'samesite' => 'Strict',
				)
			);
		}
	}

	/**
	 * If nobody is logged in, the user still gets a username.
	 *
	 * @return string
	 */
	public static function get_anon_user() {
		return 'anon';
	}

	/**
	 * Extract the username of the wp_logged in cookie
	 *
	 * @return string|false username or false
	 */
	public static function extract_username() {
		foreach ( $_COOKIE as $key => $value ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			if ( 'wordpress_logged_in_' === substr( $key, 0, 20 ) ) {
				$cookie_elements = explode( '|', $value );
				if ( count( $cookie_elements ) !== 4 ) {
					return false;
				}
				return $cookie_elements[0];
			}
		}

		return false;
	}
}
