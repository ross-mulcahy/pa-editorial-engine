<?php
/**
 * Syndication & Correction Hooks feature.
 *
 * Controls outgoing data flow: the "Editorial Stop" prevents publishing
 * when active, and the "Correction Flag" triggers async outbound API
 * calls via Action Scheduler when a corrected post is published.
 *
 * @package PA\EditorialEngine\Features\Syndication
 */

namespace PA\EditorialEngine\Features\Syndication;

use PA\EditorialEngine\Core\FeatureInterface;
use PA\EditorialEngine\Utilities\SyndicationClient;

class Syndication implements FeatureInterface {

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
		// Editorial Stop: block ALL content changes when stop is active.
		\add_filter( 'rest_pre_insert_post', [ $this, 'block_stopped_post_saves' ], 5, 2 );

		// Track who activates/deactivates the editorial stop.
		\add_action( 'updated_post_meta', [ $this, 'track_stop_user' ], 10, 4 );
		\add_action( 'added_post_meta', [ $this, 'track_stop_user' ], 10, 4 );

		// Correction Flags: schedule async API call on publish.
		\add_action( 'transition_post_status', [ $this, 'handle_correction_flag' ], 10, 3 );

		// Action Scheduler callback for sending corrections.
		\add_action( 'pa_send_correction', [ $this, 'send_correction' ] );

		// Enqueue editor sidebar components.
		\add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );
	}

	/**
	 * Block ALL content changes when the Editorial Stop is active.
	 *
	 * Once a sub editor activates the stop, the post is frozen — no content,
	 * title, excerpt, or status changes are allowed via REST. The only change
	 * permitted is toggling the stop itself off (via meta update).
	 *
	 * Hooked to `rest_pre_insert_post` at priority 5 (before locking check).
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

		$stop_active = \get_post_meta( $post_id, '_pa_editorial_stop', true );

		if ( ! $stop_active ) {
			return $prepared_post;
		}

		// Allow the request if ONLY the meta is being updated (to toggle stop off).
		$params = $request->get_json_params();
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
	 * Track which user activated or deactivated the editorial stop.
	 *
	 * Hooked to `updated_post_meta` and `added_post_meta`.
	 *
	 * @param int    $meta_id    Meta row ID.
	 * @param int    $post_id    Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value New meta value.
	 */
	public function track_stop_user( int $meta_id, int $post_id, string $meta_key, mixed $meta_value ): void {
		if ( '_pa_editorial_stop' !== $meta_key ) {
			return;
		}

		if ( $meta_value ) {
			// Stop activated — record who did it.
			\update_post_meta( $post_id, '_pa_editorial_stop_by', \get_current_user_id() );
		} else {
			// Stop deactivated — clear the record.
			\delete_post_meta( $post_id, '_pa_editorial_stop_by' );
		}
	}

	/**
	 * Handle the correction flag on publish — schedule async outbound API call.
	 *
	 * Hooked to `transition_post_status`.
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

		// Schedule async API call via Action Scheduler.
		if ( \function_exists( 'as_enqueue_async_action' ) ) {
			\as_enqueue_async_action( 'pa_send_correction', [ $post->ID ], 'pa-editorial-engine' );
		}
	}

	/**
	 * Send a correction notification to the PA Wire API.
	 *
	 * Callback for the `pa_send_correction` Action Scheduler task.
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

		$stop_by_name = '';
		if ( $post ) {
			$stop_by_id = \get_post_meta( $post->ID, '_pa_editorial_stop_by', true );
			if ( $stop_by_id ) {
				$user = \get_userdata( (int) $stop_by_id );
				if ( $user ) {
					$stop_by_name = $user->display_name;
				}
			}
		}

		\wp_localize_script(
			'pa-editorial-engine-editor',
			'paEditorialSyndication',
			[
				'enabled'      => true,
				'canToggleStop' => \current_user_can( 'edit_others_posts' ),
				'stopByName'   => $stop_by_name,
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
