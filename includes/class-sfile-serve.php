<?php
/**
 * ServeFile Class
 *
 * @todo: rename files and classes!
 *
 * @package sfile
 * @file
 */

/**
 * Serves files
 */
class ServeFile {

	private $abs_path;
	private $filesize;

	public function __construct( $abs_path ) {
		$this->abs_path = $abs_path;
		$this->filesize = filesize( $this->abs_path );
		if ( isset( $_SERVER['HTTP_RANGE'] ) ) {
			$this->stream_file();
		} else { // no range is specified, use readfile for more performace
			$this->output_headers( $this->filesize );
			// exit;
			readfile( $this->abs_path );
			flush();
		}
	}

	/**
	 * Streams a filie
	 *
	 * @return void
	 */
	private function stream_file() {
		$buffer_size = 2 * 1024 * 1024; // 2MB
		$offset      = 0;
		$length      = $this->filesize;

		/**
		 * if the HTTP_RANGE header is set we're dealing with partial content
		 * find the requested range
		 * this might be too simplistic, apparently the client can request
		 * multiple ranges, which can become pretty complex, so ignore it for now
		 */
		preg_match( '/bytes=(\d+)-(\d+)?/', $_SERVER['HTTP_RANGE'], $matches );
		$offset = intval( $matches[1] );
		if ( ! isset( $matches[2] ) ) {
			$matches[2] = false;
		}
		$end    = $matches[2] || '0' === $matches[2] ? intval( $matches[2] ) : $this->filesize - 1;
		$length = $end + 1 - $offset;
		// output the right headers for partial content.
		header( 'HTTP/1.1 206 Partial Content' );
		header( "Content-Range: bytes $offset-$end/$this->filesize" );
		// output the regular HTTP headers.
		$this->output_headers();
		$file = fopen( $this->abs_path, 'r' );
		// seek to the requested offset, this is 0 if it's not a partial content request.
		fseek( $file, $offset );

		while ( $length >= $buffer_size ) {
			print( fread( $file, $buffer_size ) );
			$length -= $buffer_size;
		}
		if ( $length ) {
			print( fread( $file, $length ) );
		}
		fclose( $file );
	}

	/**
	 * Set the header for a file.
	 * This is partially WordPress (ms-files.php)
	 *
	 * @return void
	 */
	private function output_headers() {

		$mime['type'] = mime_content_type( $this->abs_path );

		/*
		$mime = wp_check_filetype( $this->abs_path );
		if ( false === $mime[ 'type' ] && function_exists( 'mime_content_type' ) )
		$mime[ 'type' ] = mime_content_type( $this->abs_path );
		*/

		// https://stackoverflow.com/questions/45179337/mime-content-type-returning-text-plain-for-css-and-js$this->files-only
		if ( substr( $this->abs_path, -4 ) === '.css' ) {
			$mime['type'] = 'text/css';
		}

		if ( $mime['type'] ) {
			$mimetype = $mime['type'];
		} else {
			$mimetype = 'image/' . substr( $this->abs_path, strrpos( $this->abs_path, '.' ) + 1 );
		}

		header( 'Content-Type: ' . $mimetype ); // always send this
		if ( false === strpos( $_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS' ) ) {
			header( 'Content-Length: ' . $this->filesize );
		}

		$last_modified = gmdate( 'D, d M Y H:i:s', filemtime( $this->abs_path ) );
		$etag          = '"' . md5( $last_modified ) . '"';
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
