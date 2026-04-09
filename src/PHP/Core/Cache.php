<?php
/**
 * Object cache wrapper.
 *
 * Centralises cache key management and group handling for the plugin.
 * All configuration data (settings, taxonomy map) must be served from
 * the object cache to avoid hitting wp_options on every post load.
 *
 * @package PA\EditorialEngine\Core
 */

namespace PA\EditorialEngine\Core;

class Cache {

	public const GROUP = 'pa_editorial_engine';

	/**
	 * Retrieve a value from the object cache.
	 *
	 * @param string $key Cache key.
	 * @return mixed Cached value, or false on miss.
	 */
	public static function get( string $key ): mixed {
		return wp_cache_get( $key, self::GROUP );
	}

	/**
	 * Store a value in the object cache.
	 *
	 * @param string $key    Cache key.
	 * @param mixed  $value  Data to cache.
	 * @param int    $expiry Optional. Expiration in seconds. Default 0 (no expiry).
	 */
	public static function set( string $key, mixed $value, int $expiry = 0 ): bool {
		return wp_cache_set( $key, $value, self::GROUP, $expiry );
	}

	/**
	 * Delete a value from the object cache.
	 *
	 * @param string $key Cache key.
	 */
	public static function delete( string $key ): bool {
		return wp_cache_delete( $key, self::GROUP );
	}
}
