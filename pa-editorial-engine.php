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

require_once __DIR__ . '/vendor/autoload.php';

// Activation hook must be registered at top-level scope.
register_activation_hook( __FILE__, [ Core\FeatureManager::class, 'activate' ] );

add_action( 'plugins_loaded', [ new Core\FeatureManager(), 'boot' ] );
