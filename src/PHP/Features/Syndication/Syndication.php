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

		// Expose the locked status to the REST API so the editor can set it.
		\add_filter( 'rest_prepare_status', [ $this, 'expose_locked_status_rest' ], 10, 2 );

		// Correction Flags: schedule async API call on publish.
		\add_action( 'transition_post_status', [ $this, 'handle_correction_flag' ], 10, 3 );

		// Track who locked the post.
		\add_action( 'transition_post_status', [ $this, 'track_lock_user' ], 10, 3 );

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

		// Allow status change FROM locked (unlocking the post).
		$params = $request->get_json_params();
		if ( $params && isset( $params['status'] ) && self::LOCKED_STATUS !== $params['status'] ) {
			// Only editors can unlock.
			if ( \current_user_can( 'edit_others_posts' ) ) {
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
	 * Expose the locked status in REST API responses.
	 *
	 * @param \WP_REST_Response $response The response object.
	 * @param object            $status   The status object.
	 * @return \WP_REST_Response Modified response.
	 */
	public function expose_locked_status_rest( $response, $status ): mixed {
		return $response;
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
			// Entering locked — store who did it and what the previous status was.
			\update_post_meta( $post->ID, '_pa_editorial_stop_by', \get_current_user_id() );
			\update_post_meta( $post->ID, '_pa_pre_lock_status', $old_status );
		} elseif ( self::LOCKED_STATUS !== $new_status && self::LOCKED_STATUS === $old_status ) {
			// Leaving locked — clear tracking.
			\delete_post_meta( $post->ID, '_pa_editorial_stop_by' );
			\delete_post_meta( $post->ID, '_pa_pre_lock_status' );
		}
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
				'canToggleStop'  => \current_user_can( 'edit_others_posts' ),
				'isLocked'       => $is_locked,
				'stopByName'     => $stop_by_name,
				'preLockStatus'  => $pre_lock_status,
				'lockedStatus'   => self::LOCKED_STATUS,
			]
		);
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
