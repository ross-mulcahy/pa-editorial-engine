<?php
/**
 * Syndication & Correction Hooks feature.
 *
 * Controls outgoing data flow: the "Editorial Stop" freezes a post
 * using a custom `locked` status after sign-off, and the "Correction
 * Flag" triggers async outbound API calls via Action Scheduler.
 *
 * @package PA\EditorialEngine\Features\Syndication
 */

namespace PA\EditorialEngine\Features\Syndication;

use PA\EditorialEngine\Core\FeatureInterface;
use PA\EditorialEngine\Utilities\SyndicationClient;

class Syndication implements FeatureInterface {

	public const LOCKED_STATUS = 'locked';

	/**
	 * @param array<string, mixed> $settings Plugin settings array.
	 */
	public function __construct(
		private readonly array $settings = [],
	) {}

	public function is_enabled(): bool {
		return ! empty( $this->settings['syndication_enabled'] );
	}

	public function init(): void {
		// Register the custom "locked" post status.
		\add_action( 'init', [ $this, 'register_locked_status' ] );

		// Editorial Stop: block ALL content changes when post is locked.
		\add_filter( 'rest_pre_insert_post', [ $this, 'block_stopped_post_saves' ], 5, 2 );

		// Post list: show "Locked" indicator and locked-by info.
		\add_filter( 'display_post_states', [ $this, 'add_locked_post_state' ], 10, 2 );

		// Correction Flags: schedule async API call on publish.
		\add_action( 'transition_post_status', [ $this, 'handle_correction_flag' ], 10, 3 );

		// Track who locked the post.
		\add_action( 'transition_post_status', [ $this, 'track_lock_user' ], 10, 3 );

		// Heartbeat: notify all users in the post when it gets locked.
		\add_filter( 'heartbeat_received', [ $this, 'heartbeat_check_lock' ], 10, 2 );

		// Action Scheduler callback for sending corrections.
		\add_action( 'pa_send_correction', [ $this, 'send_correction' ] );

		// Enqueue editor sidebar components.
		\add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );
	}

	/**
	 * Register the custom "locked" post status.
	 */
	public function register_locked_status(): void {
		\register_post_status( self::LOCKED_STATUS, [
			'label'                     => __( 'Locked', 'pa-editorial-engine' ),
			'public'                    => true,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			// translators: %s: number of locked posts.
			'label_count'               => \_n_noop(
				'Locked <span class="count">(%s)</span>',
				'Locked <span class="count">(%s)</span>',
				'pa-editorial-engine'
			),
		] );
	}

	/**
	 * Block ALL content changes when the post status is "locked".
	 *
	 * The only allowed transitions are: locked → previous status (unlock),
	 * and meta-only updates (for the toggle itself).
	 *
	 * Hooked to `rest_pre_insert_post` at priority 5.
	 *
	 * @param \stdClass        $prepared_post The prepared post data.
	 * @param \WP_REST_Request $request       The REST request.
	 * @return \stdClass|\WP_Error The prepared post or an error.
	 */
	public function block_stopped_post_saves( \stdClass $prepared_post, \WP_REST_Request $request ): \stdClass|\WP_Error {
		$post_id = $prepared_post->ID ?? 0;

		if ( ! $post_id ) {
			return $prepared_post;
		}

		$current_status = \get_post_status( $post_id );

		if ( self::LOCKED_STATUS !== $current_status ) {
			return $prepared_post;
		}

		// Allow status change FROM locked (unlocking the post) — editors only.
		$params = $request->get_json_params();
		if ( $params && isset( $params['status'] ) && self::LOCKED_STATUS !== $params['status'] ) {
			if ( self::current_user_is_editor_or_above() ) {
				return $prepared_post;
			}
		}

		// Allow meta-only updates (e.g. toggling the stop off triggers a status change).
		if ( $params && isset( $params['meta'] ) && \count( $params ) === 1 ) {
			return $prepared_post;
		}

		return new \WP_Error(
			'editorial_stop_active',
			__(
				'Editorial Stop is active. This post has been signed off and cannot be modified.',
				'pa-editorial-engine'
			),
			[ 'status' => 403 ]
		);
	}

	/**
	 * Show a "Locked" indicator next to the post title in the admin post list.
	 *
	 * @param array<string, string> $states Current post states.
	 * @param \WP_Post              $post   Post object.
	 * @return array<string, string> Modified states.
	 */
	public function add_locked_post_state( array $states, \WP_Post $post ): array {
		if ( self::LOCKED_STATUS !== \get_post_status( $post->ID ) ) {
			return $states;
		}

		$locked_by_id = \get_post_meta( $post->ID, '_pa_editorial_stop_by', true );
		$label        = __( 'Locked', 'pa-editorial-engine' );

		if ( $locked_by_id ) {
			$user = \get_userdata( (int) $locked_by_id );
			if ( $user ) {
				$label = \sprintf(
					/* translators: %s: display name of the editor who locked the post */
					__( 'Locked by %s', 'pa-editorial-engine' ),
					$user->display_name
				);
			}
		}

		$states['pa_locked'] = $label;

		return $states;
	}

	/**
	 * Track who locked the post when status transitions to "locked".
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post object.
	 */
	public function track_lock_user( string $new_status, string $old_status, \WP_Post $post ): void {
		if ( self::LOCKED_STATUS === $new_status && self::LOCKED_STATUS !== $old_status ) {
			\update_post_meta( $post->ID, '_pa_editorial_stop_by', \get_current_user_id() );
			\update_post_meta( $post->ID, '_pa_pre_lock_status', $old_status );
		} elseif ( self::LOCKED_STATUS !== $new_status && self::LOCKED_STATUS === $old_status ) {
			\delete_post_meta( $post->ID, '_pa_editorial_stop_by' );
			\delete_post_meta( $post->ID, '_pa_pre_lock_status' );
		}
	}

	/**
	 * Heartbeat handler: check if the post has been locked since the user opened it.
	 *
	 * The JS client sends the post ID on every heartbeat tick. This handler
	 * checks the current post status and returns lock info if the post is
	 * now in the 'locked' status. The JS client then triggers the lock UI
	 * for users already in the editor.
	 *
	 * @param array<string, mixed> $response Heartbeat response data.
	 * @param array<string, mixed> $data     Heartbeat request data.
	 * @return array<string, mixed> Modified response.
	 */
	public function heartbeat_check_lock( array $response, array $data ): array {
		if ( empty( $data['pa_editorial_stop_check'] ) ) {
			return $response;
		}

		$post_id = \absint( $data['pa_editorial_stop_check'] );

		if ( ! $post_id ) {
			return $response;
		}

		$status = \get_post_status( $post_id );

		if ( self::LOCKED_STATUS === $status ) {
			$locked_by_id   = \get_post_meta( $post_id, '_pa_editorial_stop_by', true );
			$locked_by_name = '';

			if ( $locked_by_id ) {
				$user = \get_userdata( (int) $locked_by_id );
				if ( $user ) {
					$locked_by_name = $user->display_name;
				}
			}

			$response['pa_editorial_stop'] = [
				'locked'      => true,
				'locked_by'   => $locked_by_name,
				'can_unlock'  => self::current_user_is_editor_or_above(),
			];
		} else {
			$response['pa_editorial_stop'] = [
				'locked' => false,
			];
		}

		return $response;
	}

	/**
	 * Handle the correction flag on publish — schedule async outbound API call.
	 *
	 * @param string   $new_status New post status.
	 * @param string   $old_status Previous post status.
	 * @param \WP_Post $post       Post object.
	 */
	public function handle_correction_flag( string $new_status, string $old_status, \WP_Post $post ): void {
		if ( 'publish' !== $new_status ) {
			return;
		}

		$is_correction = \get_post_meta( $post->ID, '_pa_is_correction', true );

		if ( ! $is_correction ) {
			return;
		}

		if ( \function_exists( 'as_enqueue_async_action' ) ) {
			\as_enqueue_async_action( 'pa_send_correction', [ $post->ID ], 'pa-editorial-engine' );
		}
	}

	/**
	 * Send a correction notification to the PA Wire API.
	 *
	 * @param int $post_id Post ID.
	 */
	public function send_correction( int $post_id ): void {
		$client = new SyndicationClient();
		$client->send_correction( $post_id );
	}

	/**
	 * Enqueue editor assets for the syndication sidebar panels.
	 */
	public function enqueue_editor_assets(): void {
		global $post;

		$stop_by_name   = '';
		$pre_lock_status = '';
		$is_locked       = false;

		if ( $post ) {
			$is_locked = ( self::LOCKED_STATUS === \get_post_status( $post->ID ) );

			$stop_by_id = \get_post_meta( $post->ID, '_pa_editorial_stop_by', true );
			if ( $stop_by_id ) {
				$user = \get_userdata( (int) $stop_by_id );
				if ( $user ) {
					$stop_by_name = $user->display_name;
				}
			}

			$pre_lock_status = \get_post_meta( $post->ID, '_pa_pre_lock_status', true ) ?: '';
		}

		\wp_localize_script(
			'pa-editorial-engine-editor',
			'paEditorialSyndication',
			[
				'enabled'        => true,
				'canToggleStop'  => self::current_user_is_editor_or_above(),
				'isLocked'       => $is_locked,
				'stopByName'     => $stop_by_name,
				'preLockStatus'  => $pre_lock_status,
				'lockedStatus'   => self::LOCKED_STATUS,
			]
		);
	}

	/**
	 * Check if the current user has the editor role or above (editor, administrator).
	 *
	 * Uses role-based check rather than capability check because some sites
	 * (e.g. Newspack) grant edit_others_posts to authors.
	 *
	 * @return bool True if user is editor or administrator.
	 */
	public static function current_user_is_editor_or_above(): bool {
		$user = \wp_get_current_user();

		if ( ! $user || ! $user->ID ) {
			return false;
		}

		$allowed_roles = [ 'editor', 'administrator' ];

		foreach ( $user->roles as $role ) {
			if ( \in_array( $role, $allowed_roles, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_defaults(): array {
		return [
			'syndication_enabled' => true,
		];
	}
}
