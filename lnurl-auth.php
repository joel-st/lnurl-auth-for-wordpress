<?php
/*
* Plugin Name: LNURL Auth
* Plugin URI: https://wordpress.org/plugins/lnurl-auth/
* Description: This plugin provides LNURL Auth for WordPress. Login to WordPress with Bitcoin Lightning ⚡️
* Version: 1.0.14
* Author: joelmelon
* Author URI: https://lnurl-auth-for-wordpress.joelstuedle.ch
* Requires at least: 6.0
* Requires PHP: 8.0.15
* Text Domain: lnurl-auth
* Domain Path: /languages
*/

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/////////////////////////////
// AUTOLOAD VENDOR PLUGINS //
/////////////////////////////

/**
 * Load vendor plugins
 *
 * @since    1.0.0
 */

require_once 'vendor/autoload.php';

/////////////////////////////
// AUTOLOAD PLUGIN CLASSES //
/////////////////////////////

/**
 * This lot auto-loads a class or trait just when you need it. You don't need to
 * use require, include or anything to get the class/trait files, as long
 * as they are stored in the correct folder and use the correct namespaces.
 *
 * See http://www.php-fig.org/psr/psr-4/ for an explanation of the file structure
 * and https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader-examples.md for usage examples.
 *
 * @since    1.0.0
 */

spl_autoload_register(
	function ( $class ) {

		// project-specific namespace prefix
		$prefix = 'JoelMelon\\Plugins\\LNURLAuth\\Plugin\\';

		// base directory for the namespace prefix
		$base_dir = __DIR__ . '/classes/';

		// does the class use the namespace prefix?
		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			// no, move to the next registered autoloader
			return;
		}

		// get the relative class name
		$relative_class = substr( $class, $len );

		// replace the namespace prefix with the base directory, replace namespace
		// separators with directory separators in the relative class name, append
		// with .php
		$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

		// if the file exists, require it
		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

/////////////////
// LOAD PLUGIN //
/////////////////

require_once 'classes/Plugin.php';

/**
 * Load and run the plugin class
 *
 * @since    1.0.0
 */

function lnurl_auth() {
	return JoelMelon\Plugins\LNURLAuth\Plugin::get_instance( __FILE__ );
}

lnurl_auth();
