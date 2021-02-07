<?php
/**
 * This is not your standard WordPress pulign file (readme.md for more).
 * It is loaded in wp-config.php and notices an admin, if the plugin is not active.
 */

add_action(
	'admin_notices',
	function() {
		if ( is_plugin_active( 'secure-file/secure-file.php' ) ) {
			return;
		}

		$message = __(
			'This installation includes the secure-file plugin (probably via. wp-config). ' .
			'Make sure the plugin network-activated, odd things may happen otherwise ;)',
			'sfile'
		);
		$class = 'notice notice-warning';

		printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
	}
);
