<?php
/**
 * Nuclear Locking feature.
 *
 * Replaces the native WordPress soft lock with a hard lock that physically
 * prevents non-lock-holders from saving via REST and enforces role-based
 * lock priority (senior roles cannot be overridden by junior ones).
 *
 * @package PA\EditorialEngine\Features\Locking
 */

namespace PA\EditorialEngine\Features\Locking;

use PA\EditorialEngine\Core\FeatureInterface;
use WP_Error;
use WP_Post;

class Locking implements FeatureInterface {

	/**
	 * Role weight map — higher value = more senior.
	 */
	private const ROLE_WEIGHTS = [
		'administrator' => 50,
		'editor'        => 40,
		'author'        => 30,
		'contributor'   => 20,
		'subscriber'    => 10,
	];

	/**
	 * @param array<string, mixed> $settings Plugin settings array.
	 */
	public function __construct(
		private readonly array $settings = [],
	) {}

	public function is_enabled(): bool {
		return ! empty( $this->settings['locking_enabled'] );
	}

	public function init(): void {
		// Server-side: enforce role priority on heartbeat (remove takeover for junior roles).
		// Note: REST save blocking is handled by the Syndication feature's "locked"
		// post status. We do NOT block saves based on wp_check_post_lock() because
		// WP 7.0 real-time collaboration allows multiple users in a post simultaneously.
		\add_filter( 'heartbeat_received', [ $this, 'filter_heartbeat_for_role_priority' ], 10, 2 );

		// Enqueue editor assets for client-side lockdown.
		\add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );
	}

	/**
	 * Filter heartbeat response to remove the "Take Over" button when the
	 * lock holder has a more senior role than the current user.
	 *
	 * Hooked to `heartbeat_received`.
	 *
	 * @param array<string, mixed> $response Heartbeat response data.
	 * @param array<string, mixed> $data     Heartbeat request data.
	 * @return array<string, mixed> Filtered response.
	 */
	public function filter_heartbeat_for_role_priority( array $response, array $data ): array {
		if ( empty( $data['wp-refresh-post-lock']['post_id'] ) ) {
			return $response;
		}

		$post_id = \absint( $data['wp-refresh-post-lock']['post_id'] );

		if ( ! $post_id ) {
			return $response;
		}

		$lock_holder_id = \wp_check_post_lock( $post_id );

		if ( ! $lock_holder_id ) {
			return $response;
		}

		$current_user_weight    = $this->get_user_role_weight( \get_current_user_id() );
		$lock_holder_weight     = $this->get_user_role_weight( $lock_holder_id );

		// If the current user is junior to (or equal to) the lock holder, remove takeover.
		if ( $current_user_weight <= $lock_holder_weight ) {
			if ( isset( $response['wp-refresh-post-lock']['lock_error'] ) ) {
				// Keep the error message but remove the ability to take over.
				unset( $response['wp-refresh-post-lock']['lock_error']['text'] );
			}
		}

		return $response;
	}

	/**
	 * Enqueue editor JS and CSS for the nuclear lock UI.
	 */
	public function enqueue_editor_assets(): void {
		$asset_file = PA_EDITORIAL_ENGINE_PATH . 'assets/editor.asset.php';

		if ( ! \file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		\wp_enqueue_script(
			'pa-editorial-engine-editor',
			PA_EDITORIAL_ENGINE_URL . 'assets/editor.js',
			$asset['dependencies'] ?? [],
			$asset['version'] ?? PA_EDITORIAL_ENGINE_VERSION,
			true
		);

		// @wordpress/scripts outputs CSS as style-editor.css, not editor.css.
		$css_file = PA_EDITORIAL_ENGINE_PATH . 'assets/style-editor.css';
		if ( \file_exists( $css_file ) ) {
			\wp_enqueue_style(
				'pa-editorial-engine-editor',
				PA_EDITORIAL_ENGINE_URL . 'assets/style-editor.css',
				[],
				$asset['version'] ?? PA_EDITORIAL_ENGINE_VERSION
			);
		}

		// Pass locking config to JS.
		\wp_localize_script(
			'pa-editorial-engine-editor',
			'paEditorialLocking',
			[
				'enabled' => true,
			]
		);
	}

	/**
	 * Check if the post is locked by a user other than the current one.
	 *
	 * @param int $post_id Post ID.
	 * @return bool True if locked by another user.
	 */
	private function is_locked_by_another( int $post_id ): bool {
		// wp_check_post_lock() lives in wp-admin/includes/post.php and
		// is not loaded during REST API requests. Load it if needed.
		if ( ! \function_exists( 'wp_check_post_lock' ) ) {
			require_once ABSPATH . 'wp-admin/includes/post.php';
		}

		$lock = \wp_check_post_lock( $post_id );
		return $lock && $lock !== \get_current_user_id();
	}

	/**
	 * Get the role weight for a given user.
	 *
	 * @param int $user_id User ID.
	 * @return int Role weight (higher = more senior). Returns 0 for unknown roles.
	 */
	public function get_user_role_weight( int $user_id ): int {
		$user = \get_userdata( $user_id );

		if ( ! $user || empty( $user->roles ) ) {
			return 0;
		}

		// Use the highest-weighted role if the user has multiple.
		$max_weight = 0;
		foreach ( $user->roles as $role ) {
			$weight = self::ROLE_WEIGHTS[ $role ] ?? 0;
			if ( $weight > $max_weight ) {
				$max_weight = $weight;
			}
		}

		return $max_weight;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_defaults(): array {
		return [
			'locking_enabled' => true,
		];
	}
}
