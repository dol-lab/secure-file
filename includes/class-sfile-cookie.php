<?php
/**
 * SFile_Cookie class
 *
 * Requires SFile_Logger class
 *
 * @package sfile
 * @file
 *
 * @todo: validate & sanitize input variables.
 * @todo: somebody could have overlapping cookies and would always be blocked [ .kisd.de vs spaces.kisd.de ]? check all relevant cookies?
 */

require_once 'class-sfile-logger.php';
/**
 * This Class is created to work without wordoress (it can init wp though).
 * Reads, validates, and deletes Cookies
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
	 * Absolute path to the requested file
	 *
	 * @var string
	 */
	private $abs_path;

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
	 * @var [type]
	 */
	public $logger;


	public function __construct( $abs_path, array $upload_subdir_arr ) {

		$this->abs_path          = $abs_path;
		$this->upload_subdir_arr = $upload_subdir_arr;
		$this->logger            = new SFile_Logger( $this );
		// $this->logger->verbose   = SFILE_DEBUG;
	}

	/**
	 * Check if the client has a valid cookie.
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
	 * @param [type] $dir the directory array.
	 * @param [type] $valid_minutes The amount of minutes the cookie is valid.
	 * @return void
	 */
	public function make_cookie( $dir, $valid_minutes ) {

		global $table_prefix;

		require_once ABSPATH . 'wp-settings.php';

		if ( is_user_logged_in() ) {
			$username = wp_get_current_user()->data->user_login;
		} else {
			$username = $this->get_anon_user();
		}

		$key_name = $this->make_cookie_key( $dir );
		$value    = $this->make_cookie_value( $username, $key_name, $valid_minutes );
		$expire   = time() + $valid_minutes * 60; // + rand(0,35);

		$domain   = '';
		$secure   = is_ssl();
		$httponly = true; // the cookie won't be accessible by scripting languages, such as JavaScript.
		$samesite = 'Strict';
		$path     = '/';

		setcookie( $key_name, $value, $expire, $path, $domain, $secure, $httponly );

		$this->logger->log( "User $username receives a key for $valid_minutes mins for dir: " . implode( '/', $dir ) );

	}

	/**
	 * Validates an existing cookie
	 *
	 * @param [string] $key_name The key of the cookie.
	 * @param [string] $pretended_username The username the cookie contains.
	 * @return boolean
	 */
	private function is_cookie_valid( $key_name, $pretended_username ) {

		$value          = rawurldecode( $_COOKIE[ $key_name ] );
		$cookie_content = $this->no_db_crypt( $value, $key_name, false );

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

		if ( $cdata['key_name'] !== $key_name ) { // hm, this probably can't happen...
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
		if ( $cdata['hash'] !== $correct_hash ) {
			$this->logger->log( 'hash mismatch' );
			return false;
		}

		// we validated the timestamp via the hash.
		return true;

	}


	/**
	 * The cookie value contains:
	 *  a hashed secret
	 *  the username
	 *  and the time the cookie is valid
	 *
	 * this info is encrypted.
	 *
	 * the hashed secret also depends on the timestamp.
	 */
	private function make_cookie_value( $username, $key_name, $valid_mins = 20 ) {
		$timestamp = time() + $valid_mins * 60;
		$hash      = $this->no_db_hash( $username, $key_name, $timestamp );
		$c_string  = $hash . '|' . $username . '|' . $key_name . '|' . $timestamp;
		$val       = $this->no_db_crypt( $c_string, $key_name );
		if ( ! $val ) {
			$this->go_away( 'something went wrong encrypting the cookie.' );
		}

		return $val;
	}

	private function no_db_hash( $username, $key_name, $timestamp ) {
		$secret = hexdec( substr( bin2hex( $username . $key_name ), 0, 8 ) ) * $timestamp;
		return str_replace( '|', '', hash( 'sha256', $secret, FILE_SALT ) );
	}

	/**
	 * Encrypt and Decrypt (openssl) a String
	 *
	 * @param string  $string The string you want to en-/decrypt.
	 * @param string  $iv A non-NULL Initialization Vector. @see http://php.net/manual/de/function.openssl-decrypt.php .
	 * @param boolean $encrypt set false to decrypt.
	 * @return string
	 */
	private function no_db_crypt( $string, $iv, $encrypt = true ) {

		$secret_key     = $iv;// $iv.$_SERVER['PATH']; //hm, always the same for a docker image...
		$secret_iv      = $_SERVER['SERVER_SOFTWARE'] . $iv;
		$output         = false;
		$encrypt_method = 'AES-256-CBC';
		$key            = hash( 'sha256', $secret_key );
		$iv             = substr( hash( 'sha256', $secret_iv ), 0, 16 );

		if ( $encrypt ) {
			$output = base64_encode( openssl_encrypt( $string, $encrypt_method, $key, 0, $iv ) );
		} else {
			$output = openssl_decrypt( base64_decode( $string ), $encrypt_method, $key, 0, $iv );
		}
		return $output;
	}

	/**
	 * Create all possible cookie names that could apply to the path of the requested file.
	 *
	 * @param [array] $upload_subdir_arr The path of the requested file.
	 * @param boolean $limit Limit the depth of the path.
	 * @return array all possible cookie names. looks like array('a', 'a_b', 'a_b_c').
	 */
	private function make_all_cookie_keys( $upload_subdir_arr, $limit = false ) {
		$keys = array();
		if ( $limit ) {
			$upload_subdir_arr = array_slice( $upload_subdir_arr, 0, $limit );
		}
		$elems = count( $upload_subdir_arr );
		while ( $elems ) {
			array_push( $keys, $this->make_cookie_key( $upload_subdir_arr, $limit ) );
			array_pop( $upload_subdir_arr );
			$elems--;
		}
		return array_reverse( $keys );
	}

	private function make_cookie_key( $upload_subdir_arr, $limit = false ) {
		if ( $limit ) {
			$upload_subdir_arr = array_slice( $upload_subdir_arr, 0, $limit );
		}
		return self::$prefix . implode( '_', $upload_subdir_arr );
	}

	/**
	 * Get the most specific cookie key which maches the directory the user is trying to acccess.
	 *
	 * @param [array] $upload_subdir_arr the diectory array [blogid, year, month, ...].
	 * @return string | boolean the key, which is save in the client cookie or false.
	 */
	private function get_client_cookie( $upload_subdir_arr ) {

		$cookie_keys = $this->make_all_cookie_keys( $upload_subdir_arr ); // unspecific to specific.

		$key_found = false;
		foreach ( $cookie_keys as $key ) {
			if ( isset( $_COOKIE[ $key ] ) ) {
				// we overwrite the less specific cookie key with the more specific one (if it exists).
				$key_found = $key;
			}
		}
		return $key_found;
	}

	/**
	 * Remove all cookies that were created by this class.
	 *
	 * @return void
	 */
	public function remove_all_file_cookies() {
		$removed = [];
		foreach ( $_COOKIE as $key => $value ) {
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
	 * Remve a cookie
	 *
	 * @param [string] $key The key name of the cookie you want to remove.
	 * @return void
	 */
	private function remove_client_cookie( $key ) {
		if ( isset( $_COOKIE[ $key ] ) ) {
			unset( $_COOKIE[ $key ] );
			setcookie( $key, '', time() - 3600, '/' ); // empty value and old timestamp.
		}
	}

	/**
	 * If nobody is logged in, the user still gets a username.
	 *
	 * @return string
	 */
	public static function get_anon_user() {
		return 'anon'; // todo: some hashing, change daily!
	}
	/**
	 * Extract the username of the wp_logged in cookie
	 *
	 * @return string username or false
	 */
	public static function extract_username() {
		foreach ( $_COOKIE as $key => $value ) {
			if ( 'wordpress_logged_in_' === substr( $key, 0, 20 ) ) {
				$logged_in_val   = $value;
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
