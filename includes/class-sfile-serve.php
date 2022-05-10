<?php
/**
 * ServeFile Class
 *
 * @todo: rename files and classes!
 *
 * @package sfile
 * @file
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
/**
 * Serves files
 */
class ServeFile {

	private $abs_path;
	private $filesize;
	private $mime_detector;

	public function __construct( $abs_path ) {
		$this->abs_path = $abs_path;
		$this->filesize = filesize( $this->abs_path );

		$map                 = new League\MimeTypeDetection\GeneratedExtensionToMimeTypeMap();
		$this->mime_detector = new League\MimeTypeDetection\FinfoMimeTypeDetector( '', $map );

		if ( isset( $_SERVER['HTTP_RANGE'] ) ) {
			$this->stream_file();
		} else { // no range is specified, use readfile for more performance.
			$this->output_headers( $this->filesize );
			// exit;
			readfile( $this->abs_path );
			flush();
		}
	}

	/**
	 * Streams a file.
	 * Thanks imanghafoori1/laravel-video
	 *
	 * @return void
	 */
	private function stream_file() {

		ob_get_clean();

		$buffer = 1024 * 1024; // 1 MB;

		$start = 0;
		$size  = filesize( $this->abs_path );
		$end   = $size - 1;
		header( 'Accept-Ranges: 0-' . $end );

		$end            = $end;
		list( ,$range ) = explode( '=', $_SERVER['HTTP_RANGE'], 2 );

		if ( strpos( $range, ',' ) !== false ) {
			header( 'HTTP/1.1 416 Requested Range Not Satisfiable' );
			header( "Content-Range: bytes $start-$end/$size" );
			exit;
		}

		if ( $range == '-' ) {
			$start = $size - substr( $range, 1 );
		} else {
			$range = explode( '-', $range );
			$start = $range[0];

			$end = ( isset( $range[1] ) && is_numeric( $range[1] ) ) ? $range[1] : $end;
		}
		$end = ( $end > $end ) ? $end : $end;
		if ( $start > $end || $start > $size - 1 || $end >= $size ) {
			header( 'HTTP/1.1 416 Requested Range Not Satisfiable' );
			header( "Content-Range: bytes $start-$end/$size" );
			exit;
		}

		$length      = $end - $start + 1;
		$file_stream = fopen( $this->abs_path, 'r' );
		fseek( $file_stream, $start );
		header( 'HTTP/1.1 206 Partial Content' );
		header( "Content-Range: bytes $start-$end/" . $size );
		$this->output_headers( $length );

		$i = $start;
		set_time_limit( 0 );
		while ( ! feof( $file_stream ) && $i <= $end ) {

			$bytes_to_read = $buffer;
			if ( ( $i + $bytes_to_read ) > $end ) {
				$bytes_to_read = $end - $i + 1;
			}
			$data = fread( $file_stream, $bytes_to_read );
			echo $data;
			flush();
			$i += $bytes_to_read;
		}
		fclose( $file_stream );
		exit;

	}

	/**
	 * Set the header for a file.
	 * This is partially WordPress (ms-files.php)
	 *
	 * @return void
	 */
	private function output_headers( $content_length ) {

		$mimetype = $this->mime_detector->detectMimeTypeFromPath( $this->abs_path );

		header( 'Content-Type: ' . $mimetype ); // always send this.
		if ( $content_length ) {
			header( 'Content-Length: ' . $content_length );
		}

		$last_modified = gmdate( 'D, d M Y H:i:s', @filemtime( $this->abs_path ) );
		$etag          = '"' . md5( $last_modified ) . '"';
		header( 'Cache-Control: max-age=2592000, public' );
		header( "Last-Modified: $last_modified GMT" );
		header( 'ETag: ' . $etag );
		header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + 100000000 ) . ' GMT' );

		// Support for Conditional GET - use stripslashes to avoid formatting.php dependency.
		$client_etag = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? stripslashes( $_SERVER['HTTP_IF_NONE_MATCH'] ) : false;

		if ( ! isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ) {
			$_SERVER['HTTP_IF_MODIFIED_SINCE'] = false;
		}

		date_default_timezone_set( 'UTC' );

		$client_last_modified = trim( $_SERVER['HTTP_IF_MODIFIED_SINCE'] );
		// If string is empty, return 0. If not, attempt to parse into a timestamp.
		$client_modified_timestamp = $client_last_modified ? strtotime( $client_last_modified . ' UTC' ) : 0;

		// Make a timestamp for our most recent modification...
		$modified_timestamp = strtotime( $last_modified . ' UTC' );

		if ( ( $client_last_modified && $client_etag )
		? ( ( $client_modified_timestamp >= $modified_timestamp ) && ( $client_etag == $etag ) )
		: ( ( $client_modified_timestamp >= $modified_timestamp ) || ( $client_etag == $etag ) )
		) {
			header( StatusCodes::httpHeaderFor( 304 ) );
			exit;
		}

	}
}
