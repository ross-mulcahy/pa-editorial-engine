<?php
/**
 * Minimal WordPress function stubs for unit testing.
 *
 * These stubs allow feature classes to be tested without a full WordPress
 * environment. They use global test variables to control return values.
 */

// ---------------------------------------------------------------
// Guard: only define if WordPress is not loaded.
// ---------------------------------------------------------------
if ( defined( 'ABSPATH' ) ) {
	return;
}

// ---------------------------------------------------------------
// Constants.
// ---------------------------------------------------------------
define( 'PA_EDITORIAL_ENGINE_VERSION', '1.0.0-test' );
define( 'PA_EDITORIAL_ENGINE_PATH', dirname( __DIR__, 2 ) . '/' );
define( 'PA_EDITORIAL_ENGINE_URL', 'https://example.com/wp-content/plugins/pa-editorial-engine/' );

// ---------------------------------------------------------------
// WordPress core function stubs.
// ---------------------------------------------------------------

if ( ! function_exists( 'wp_check_post_lock' ) ) {
	function wp_check_post_lock( int $post_id ): int|false {
		return $GLOBALS['pa_test_post_lock'] ?? false;
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id(): int {
		return $GLOBALS['pa_test_current_user'] ?? 0;
	}
}

if ( ! function_exists( 'get_userdata' ) ) {
	function get_userdata( int $user_id ): object|false {
		// Check role map first (for role-based tests).
		if ( isset( $GLOBALS['pa_test_user_roles'][ $user_id ] ) ) {
			return (object) [
				'ID'           => $user_id,
				'display_name' => 'User ' . $user_id,
				'roles'        => $GLOBALS['pa_test_user_roles'][ $user_id ],
			];
		}

		// Fall back to explicit user data.
		if ( isset( $GLOBALS['pa_test_user_data'] ) ) {
			return $GLOBALS['pa_test_user_data'];
		}

		return false;
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ): int {
		return abs( (int) $value );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( string $capability ): bool {
		return $GLOBALS['pa_test_user_can'] ?? false;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $option, mixed $default = false ): mixed {
		return $GLOBALS['pa_test_options'][ $option ] ?? $default;
	}
}

if ( ! function_exists( 'term_exists' ) ) {
	function term_exists( int|string $term ): bool {
		$existing = $GLOBALS['pa_test_term_exists'] ?? [];
		return in_array( (int) $term, $existing, true );
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( string $transient, mixed $value, int $expiration = 0 ): bool {
		$GLOBALS['pa_test_transients'][ $transient ] = $value;
		return true;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( string $transient ): mixed {
		return $GLOBALS['pa_test_transients'][ $transient ] ?? false;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( string $transient ): bool {
		unset( $GLOBALS['pa_test_transients'][ $transient ] );
		return true;
	}
}

if ( ! function_exists( 'wp_is_post_revision' ) ) {
	function wp_is_post_revision( int $post_id ): bool {
		return $GLOBALS['pa_test_is_revision'] ?? false;
	}
}

if ( ! function_exists( 'wp_is_post_autosave' ) ) {
	function wp_is_post_autosave( int $post_id ): bool {
		return $GLOBALS['pa_test_is_autosave'] ?? false;
	}
}

if ( ! function_exists( 'wp_cache_get' ) ) {
	function wp_cache_get( string $key, string $group = '' ): mixed {
		return $GLOBALS['pa_test_cache'][ $key ] ?? false;
	}
}

if ( ! function_exists( 'wp_cache_set' ) ) {
	function wp_cache_set( string $key, mixed $value, string $group = '', int $expire = 0 ): bool {
		$GLOBALS['pa_test_cache'][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'wp_cache_delete' ) ) {
	function wp_cache_delete( string $key, string $group = '' ): bool {
		unset( $GLOBALS['pa_test_cache'][ $key ] );
		return true;
	}
}

if ( ! function_exists( 'register_setting' ) ) {
	function register_setting( string $group, string $name, array $args = [] ): void {}
}

if ( ! function_exists( 'add_options_page' ) ) {
	function add_options_page( string $page_title, string $menu_title, string $capability, string $menu_slug, callable $callback ): void {}
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): void {
		// No-op in unit tests.
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): void {
		// No-op in unit tests.
	}
}

if ( ! function_exists( 'get_post' ) ) {
	function get_post( int $post_id ): ?object {
		return $GLOBALS['pa_test_posts'][ $post_id ] ?? null;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( int $post_id, string $key = '', bool $single = false ): mixed {
		$meta = $GLOBALS['pa_test_post_meta'][ $post_id ] ?? [];
		if ( $key ) {
			$value = $meta[ $key ] ?? null;
			return $single ? $value : [ $value ];
		}
		return $meta;
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( int $post_id, string $key, mixed $value ): bool {
		$GLOBALS['pa_test_post_meta'][ $post_id ][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_post_meta' ) ) {
	function delete_post_meta( int $post_id, string $key ): bool {
		unset( $GLOBALS['pa_test_post_meta'][ $post_id ][ $key ] );
		return true;
	}
}

if ( ! function_exists( 'wp_insert_post' ) ) {
	function wp_insert_post( array $postarr, bool $wp_error = false ): int|\WP_Error {
		static $next_id = 1000;
		$id = ++$next_id;
		$GLOBALS['pa_test_inserted_posts'][ $id ] = $postarr;
		return $id;
	}
}

if ( ! function_exists( 'get_object_taxonomies' ) ) {
	function get_object_taxonomies( string $post_type ): array {
		return $GLOBALS['pa_test_taxonomies'] ?? [ 'category', 'post_tag' ];
	}
}

if ( ! function_exists( 'wp_get_object_terms' ) ) {
	function wp_get_object_terms( int $post_id, string $taxonomy, array $args = [] ): array {
		return $GLOBALS['pa_test_object_terms'][ $post_id ][ $taxonomy ] ?? [];
	}
}

if ( ! function_exists( 'wp_set_object_terms' ) ) {
	function wp_set_object_terms( int $post_id, array $terms, string $taxonomy ): void {
		$GLOBALS['pa_test_set_terms'][ $post_id ][ $taxonomy ] = $terms;
	}
}

if ( ! function_exists( 'wp_nonce_url' ) ) {
	function wp_nonce_url( string $url, string $action ): string {
		return $url . '&_wpnonce=test_nonce';
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( string $path = '' ): string {
		return 'https://example.com/wp-admin/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( string $url ): string {
		return $url;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( mixed $thing ): bool {
		return $thing instanceof \WP_Error;
	}
}

if ( ! function_exists( 'wp_localize_script' ) ) {
	function wp_localize_script( string $handle, string $object_name, array $l10n ): void {}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( string $handle, string $src = '', array $deps = [], $ver = false, $in_footer = false ): void {}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( string $handle, string $src = '', array $deps = [], $ver = false, string $media = 'all' ): void {}
}

if ( ! function_exists( 'check_admin_referer' ) ) {
	function check_admin_referer( string $action ): void {
		if ( ! ( $GLOBALS['pa_test_nonce_valid'] ?? true ) ) {
			throw new \RuntimeException( 'Invalid nonce' );
		}
	}
}

if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( string $message = '' ): void {
		throw new \RuntimeException( $message );
	}
}

if ( ! function_exists( 'wp_safe_redirect' ) ) {
	function wp_safe_redirect( string $location ): void {
		$GLOBALS['pa_test_redirect'] = $location;
	}
}

if ( ! function_exists( 'get_current_screen' ) ) {
	function get_current_screen(): ?object {
		return $GLOBALS['pa_test_screen'] ?? null;
	}
}

if ( ! function_exists( 'wp_safe_remote_post' ) ) {
	function wp_safe_remote_post( string $url, array $args = [] ): array|\WP_Error {
		$GLOBALS['pa_test_remote_posts'][] = [ 'url' => $url, 'args' => $args ];
		return $GLOBALS['pa_test_remote_response'] ?? [ 'response' => [ 'code' => 200 ] ];
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( array $response ): int {
		return $response['response']['code'] ?? 0;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( mixed $data ): string|false {
		return json_encode( $data );
	}
}

if ( ! function_exists( 'get_permalink' ) ) {
	function get_permalink( int $post_id ): string {
		return 'https://example.com/?p=' . $post_id;
	}
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time( string $type ): string {
		return gmdate( 'c' );
	}
}

if ( ! function_exists( 'as_enqueue_async_action' ) ) {
	function as_enqueue_async_action( string $hook, array $args = [], string $group = '' ): int {
		$GLOBALS['pa_test_async_actions'][] = [
			'hook'  => $hook,
			'args'  => $args,
			'group' => $group,
		];
		return 1;
	}
}

// ---------------------------------------------------------------
// WordPress core classes.
// ---------------------------------------------------------------

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private string $code;
		private string $message;
		private mixed $data;

		public function __construct( string $code = '', string $message = '', mixed $data = '' ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code(): string {
			return $this->code;
		}

		public function get_error_message(): string {
			return $this->message;
		}

		public function get_error_data(): mixed {
			return $this->data;
		}
	}
}

if ( ! class_exists( 'WP_Post' ) ) {
	class WP_Post {
		public int $ID = 0;
		public string $post_title = '';
		public string $post_content = '';
		public string $post_excerpt = '';
		public int $post_author = 0;
		public string $post_status = 'draft';
		public string $post_type = 'post';

		public function __construct( array $data = [] ) {
			foreach ( $data as $key => $value ) {
				if ( property_exists( $this, $key ) ) {
					$this->$key = $value;
				}
			}
		}
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request {
		private array $params = [];

		public function get_param( string $key ): mixed {
			return $this->params[ $key ] ?? null;
		}

		public function set_param( string $key, mixed $value ): void {
			$this->params[ $key ] = $value;
		}
	}
}
