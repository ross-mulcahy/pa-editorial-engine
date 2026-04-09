<?php
/**
 * Plugin Name: PA Editorial Engine
 * Plugin URI:  https://www.pa.media
 * Description: Editorial workflow engine for PA Media — locking, metadata mapping, cloning, and syndication.
 * Version:     1.0.0
 * Requires PHP: 8.0
 * Requires at least: 6.7
 * Author:      PA Media
 * Author URI:  https://www.pa.media
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: pa-editorial-engine
 */

namespace PA\EditorialEngine;

defined( 'ABSPATH' ) || exit;

define( 'PA_EDITORIAL_ENGINE_VERSION', '1.0.0' );
define( 'PA_EDITORIAL_ENGINE_PATH', plugin_dir_path( __FILE__ ) );
define( 'PA_EDITORIAL_ENGINE_URL', plugin_dir_url( __FILE__ ) );

// Use Composer autoloader if available, otherwise use built-in PSR-4 loader.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
} else {
	spl_autoload_register( function ( string $class ): void {
		$prefix = 'PA\\EditorialEngine\\';
		if ( ! str_starts_with( $class, $prefix ) ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$file     = __DIR__ . '/src/PHP/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	} );
}

// Activation hook must be registered at top-level scope.
register_activation_hook( __FILE__, [ Core\FeatureManager::class, 'activate' ] );

add_action( 'plugins_loaded', [ new Core\FeatureManager(), 'boot' ] );
