<?php
/**
 * SFile_Manager class
 *
 * @package sfile
 */

/**
 * This class is created to work without WordPress.
 * Requires SFile_Cookie and SFile_Log class.
 *
 * Manages secure files.
 */
class SFile_Manager {

	/**
	 * Contains the subfolder names of the uploads dir e.c. array('sites', '123').
	 *
	 * @var array
	 */
	private $upload_subdir_arr;

	/**
	 * Stores the absolute path to the the file
	 *
	 * @var string
	 */
	private $abs_path;

	/**
	 * Stores the name of the requested file (e.c. test.jpg)
	 *
	 * @var string
	 */
	private $filename;

	/**
	 * Describes what the users gets, when she is not allowed to see a file.
	 * Can be 'message' or 'image'.
	 *
	 * @var [type]
	 */
	private $user_error;

	/**
	 * The default error image.
	 *
	 * @var string
	 */
	private $error_image; // set with setter.

	/**
	 * The WordPress Upload directory.
	 * Absolute Server Path.
	 * WP_CONTENT_DIR/uploads.
	 *
	 * @var string
	 */
	private static $upload_dir;

	/**
	 * An instance of the FileCookie class.
	 *
	 * @var FileCookie
	 */
	private $f_cookie;    // FileCookie Class.

	/**
	 * An instance of the SFile_Logger Class
	 *
	 * @var SFile_Logger
	 */
	private $logger;

	/**
	 * Set true if WordPress is loaded.
	 *
	 * @var boolean
	 */
	private $wp_initialized = false;

	/**
	 * Setup the SFileManger Class.
	 * WP_CONTENT_DIR in default-constants.php: define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' ).
	 * ABSPATH in wp_config.php (default): define('ABSPATH', dirname(__FILE__) . '/').
	 * UPLOADS in wp_config (maybe), relative to ABSPATH: define( 'UPLOADS', 'wp-content/media' ).
	 *
	 * @param string $abs_path The absolute path to the requested file.
	 * @param string $user_error can be either 'image' or 'message'.
	 */
	public function __construct( $abs_path, $user_error = 'message' ) {

		require_once 'class-sfile-logger.php';

		$this->logger = new SFile_Logger( $this );

		$this->user_error = $user_error;

		$this->error_image = dirname( __DIR__ ) . '/no-access.jpg';

		/**
		 * Having UPLOADS defined does not really make sense with this. Plugin Security revolves around subfolders.
		 * Defining UPLOADS puts everything in a single folder...
		 */
		if ( defined( 'UPLOADS' ) ) {
			self::$upload_dir = trailingslashit( ABSPATH ) . UPLOADS;
		} else {
			// same as the default set in default-constants.php.
			self::$upload_dir = WP_CONTENT_DIR . '/uploads';
		}

		/**
		 * Apply strict path security sequence: Transform -> Normalize -> Sanitize -> Use.
		 */
		// 1. Transform: clean obvious traversal patterns and trim.
		$relative_path = ltrim( str_replace( '..', '', $abs_path ), '/' );
		$constructed_path = rtrim( self::$upload_dir, '/' ) . '/' . $relative_path;

		// 2. Normalize: resolve canonical paths.
		$normalized_target_dir = realpath( rtrim( self::$upload_dir, '/' ) );
		$normalized_file_path  = realpath( $constructed_path );

		// 3. Sanitize: Validate boundaries using strict suffix checking.
		// Ensure the directory check explicitly prevents partial match vulnerabilities (e.g. /uploads_malicious/).
		if ( false === $normalized_target_dir || false === $normalized_file_path ) {
			$this->go_away( 'Invalid file path requested.', 403 );
		}

		$secured_target_dir = rtrim( $normalized_target_dir, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
		if ( strpos( $normalized_file_path, $secured_target_dir ) !== 0 ) {
			$this->go_away( 'Path traversal detected.', 403 );
		}

		// 4. Use: Assigned the completely vetted file.
		$this->abs_path = $normalized_file_path;

		$arr_path       = explode( '/', $relative_path );
		$this->filename = end( $arr_path );

		if ( ! is_file( $this->abs_path ) ) {
			$this->go_away( '404 &#8212; File not found: ' . $this->filename, 404 );
		}
		array_pop( $arr_path );
		$this->upload_subdir_arr = $arr_path; // the last element is the file.

		if ( ! count( $this->upload_subdir_arr ) ) {
			$this->go_away( 'Looks like you added a file directly to the uploads dir. don\'t like that. Make a subfolder ;)' );
		}

		$this->f_cookie = new SFile_Cookie( $this->abs_path, $this->upload_subdir_arr );
	}

	/**
	 * Serve a file (or a stream) to a user if she has the access rights.
	 *
	 * @return void
	 */
	public function maybe_serve_file() {

		$this->logger->log( "Check if user can access $this->filename" );

		if ( $this->f_cookie->has_valid_cookie() ) {
			/**
			 * The user has a valid cookie. No need to start WP
			 */
			$server = new ServeFile( $this->abs_path );
			$server->serve();
			die();

		} else {

			global $table_prefix; // needs to be before require "wp-settings.php"!

			require_once ABSPATH . 'wp-settings.php';

			$this->wp_initialized = true;

			/** This is the core interface of the plugin:
			 * - A users asks you to give her access to a folder (the one with the file she wants)
			 * - the $upload_subdir_arr contains an array with all parent folders (the uploads folder is the last but excluded)
			 * - it looks like array('sites', 123, 2018, 08) for a file in sites subfolder, blogid 123, year 2018 month 08
			 * - if you don't filter anything, the cookie will give the user access to all files that were created August 2018 in blog 123 for 20mins.
			 * - if you remove 08 and 2018 from the array, the key will be valid for all files uploaded to the blog 123.
			 * - once the user has the cookie, this "key" will unlock folders without the need to load WordPress.
			 */
			$args     = array(
				'dir'           => $this->upload_subdir_arr,
				'valid_minutes' => 120,
				/**
				 * If you don't define a hook, you can not access any file.
				 * So if there is an error files are not accidentally served.
				 */
				'can_access'    => false,
				'message'       => 'Sorry, you can not access this file.',
			);
			$file_url = 'http' . ( isset( $_SERVER['HTTPS'] ) ? 's' : '' ) . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

			$args = apply_filters(
				'secure_file_cookie',
				$args, // the filtered value.
				$file_url,
				is_user_logged_in(),
				wp_get_current_user()
			);

			if ( ! isset( $args['can_access'] ) ) {
				$msg = 'Trying to access ' . $this->filename . ". Something went wrong with the filter 'secure_file_cookie'. The key 'can_access' needs to be defined.";
				$this->logger->log( $msg );
				$this->go_away( $msg . $this->filename );

			}

			if ( ! $args['can_access'] ) {
				$this->go_away( $args['message'] );
			}

			$this->logger->log( $args['message'] );

			$this->f_cookie->make_cookie( $args['dir'], $args['valid_minutes'] );
			/**
			 * The user does not have a valid cookie
			 */
			$server = new ServeFile( $this->abs_path );
			$server->serve();
		}
	}



	/**
	 * Gives an error (image/site) to the user.
	 *
	 * @param string $html_msg the message the user receives, make sure it is safe.
	 * @param string $header the status header (like 404).
	 * @return void
	 */
	private function go_away( $html_msg = '', $header = '' ) {

		if ( 'image' === $this->user_error ) {

			if ( 0 === strpos( $this->error_image, 'http://' ) ) {
				echo "<img src='" . esc_url( $this->error_image ) . "' />";
			} else {
				$server = new ServeFile( $this->error_image );
				$server->serve();
			}
			die();
		}

		$this->logger->log( $html_msg, 'go_away' );

		if ( $header ) {
			header( StatusCodes::httpHeaderFor( $header ) );
		}

		if ( ! $html_msg ) {
			$html_msg = 'Please log in to access this file.';
		}
		if ( true === $this->wp_initialized ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- we want to allow HTML in the message.
			wp_die( $html_msg );
		} else {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- we want to allow HTML in the message.
			die( $html_msg );
		}
	}

	/**
	 * Instead of returning an error message (404 page) you can also return an error-image.
	 * This coule be helpful for emails, so you still have an image-preview.
	 *
	 * @todo: server-path vs. URL?!
	 * @param [string] $img path to an image.
	 * @return void
	 */
	private function set_error_image( $img ) {
		if ( 'image' !== $this->user_error ) {
			$this->logger->log( "You set the error image but don't display it." );
		}
		$this->error_image = $img;
	}
}
