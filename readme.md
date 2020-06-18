# Secure File
This is not your standard plug & play plugin, you need to do some manual modifications.

Wordpress does not normally restrict the access to files (it did, a very long time ago). This plugin creates a way to do it, without sacrificing too much performance.
The basic idea is to give every user a key (via. cookies) for a specific folder in the uploads directory, which is valid for a certain time. This way we don't have to load the whole WordPress environment for each file request, but just check the key (and give it to a user if she or he is allowed to access). WP saves files in a path like /uploads/BLOG_ID/YEAR/MONTH/... So you can limit by blog ids or by a year, a month, ...


ℹ WordPress handles uploads for the main_site( blog_id = 1) differently than every other blog.
Instead of putting uploads in `uploads/sites/1/year/...` it puts them direcly in the uploads folder like `uploads/year/...`

🚨 This plugin changes the upload path of newly uploaded files in the main_site. You might want to move them to a single place.


## Prerequisites

* PHP > 5.3 (hash sha)
* openssl_encrypt, openssl_decrypt need to be installed

## Setup

You first need to direct file requests to php either via nginx of apache (depending on the server software you are using).(Check out [the config](https://github.com/dol-lab/trellis/blob/master/nginx-includes/all/secure-file.conf.j2) for [trellis](https://roots.io/trellis/)).

**Nginx** in /etc/nginx/sites-available/spaces.conf:

``` nginx
if (-e $request_filename) {
  # dont't allow access to the uploads folder. we stream all files.
  rewrite ^(/[_\-0-9a-z]+)?/uploads/(.+)$ /wp-config.php?sfile=$2 last;
}
```

**Apache** in .htaccess:

``` apacheconf
RewriteRule ^([_0-9a-zA-Z-]+/)?uploads/(.+) wp-config.php?sfile=$2 [L]
```

The above redirect files to wp-config.php, where the plugin is referenced. It needs to be the wp-config.php, otherwise WordPress loads and we loose lots of performance.

Add the following to your wp-config.php before `require_once( ABSPATH . 'wp-settings.php' );` .

``` php
define('FILE_SALT', 'add your own here!');
if ( ! empty( WP_PLUGIN_DIR ) ) {
	if ( defined( 'WP_PLUGIN_DIR' ) && file_exists( WP_PLUGIN_DIR . '/secure-file/get-file.php' ) ) {
		require_once WP_PLUGIN_DIR . '/secure-file/get-file.php';
	} else {
		die( 'The file-security plugin does not exist, yet it is referenced in wp-config.php.' );
	}
}
```

Now write a plugin and customize your access-rules.

``` php
add_filter( 'secure_file_cookie', 'my_file_security', 10, 4 );

function my_file_security( $args, $file_url, $is_logged_in, $current_user ) {
	$dir  = $args['dir'];
	$mins = $args['valid_minutes'];
	$root = $dir[0]; // the first folder under the uploads folder.

	if ( 'sites' === $root ) { // we only check for blog access.
		$blog_id = intval( $dir[1] );
		if ( $blog_id > 0 ) { // we have a valid blog id.
			if ( ! $is_logged_in ) { // user is not logged in.
				if ( ssf_is_blog_public( $blog_id ) ) { // we are in a public blog.
					$args['message']    = 'User is not logged in but the blog is public.
						Files for this blog are accessible.';
					$args['can_access'] = true;
					$args['dir']        = array_slice( $dir, 0, 2 ); // access all files in this blog. [sites, blogid].
				} else { // don't allow access to non-public blogs for non-logged in users.
					$args['message']    = '
						You are not logged in and the blog (where this file was uploaded) is not public.
						' . ssf_make_login_link( $file_url ) . '
					';
					$args['can_access'] = false;
				}
			} else { // user is logged in.
				$args['message']    = 'User is logged in and can access all sites for now.';
				$args['can_access'] = true;

				/**
				 * Allows everybody to accesss all sites.
				 * The cookie will be named pv_sites.
				 */
				$args['dir']        = array_slice( $dir, 0, 1 );
			}
		} else { // something is wrong with the blog structure.
			$args['message']    = 'Sorry. Something is odd about this file. Please contact and admin.';
			$args['can_access'] = false;
		}
	}
	return $args;
}

```

## Todos
 * Review it, try to hack it! :)
 * Automatically write a random salt to file on installation (via.[WP-Api](https://api.wordpress.org/secret-key/1.1/salt/))
 * Write a test to check if things work. Run it after the installation.
 * Write performance tests (widh [siege](http://www.joedog.org/siege-home)?).


## Questions

* Should public blogs live in a different directory? Do you receive a cookie for public ones? This would reduce key-wear to logged in users.
* What about cookie limits?  http://browsercookielimits.squawky.net/
* What do we do with other files in uploads folder?
* Have the same output for: file does not exist and not allowed?
