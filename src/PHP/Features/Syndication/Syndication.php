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
		// Editorial Stop: block publishing when stop is active.
		add_filter( 'wp_insert_post_data', [ $this, 'enforce_editorial_stop' ], 10, 2 );

		// Correction Flags: schedule async API call on publish.
		add_action( 'transition_post_status', [ $this, 'handle_correction_flag' ], 10, 3 );

		// Action Scheduler callback for sending corrections.
		add_action( 'pa_send_correction', [ $this, 'send_correction' ] );

		// Enqueue editor sidebar components.
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );
	}

	/**
	 * Enforce the editorial stop — prevent publishing when the stop flag is active.
	 *
	 * Hooked to `wp_insert_post_data`.
	 *
	 * @param array<string, mixed> $data    Slashed, sanitized post data.
	 * @param array<string, mixed> $postarr Raw post data array including meta_input.
	 * @return array<string, mixed> Modified post data.
	 */
	public function enforce_editorial_stop( array $data, array $postarr ): array {
		$post_id = $postarr['ID'] ?? 0;

		if ( ! $post_id ) {
			return $data;
		}

		// Only intercept when transitioning to 'publish'.
		if ( 'publish' !== $data['post_status'] ) {
			return $data;
		}

		$stop_active = get_post_meta( $post_id, '_pa_editorial_stop', true );

		if ( $stop_active ) {
			$data['post_status'] = 'pending';
		}

		return $data;
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

		$is_correction = get_post_meta( $post->ID, '_pa_is_correction', true );

		if ( ! $is_correction ) {
			return;
		}

		// Schedule async API call via Action Scheduler.
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( 'pa_send_correction', [ $post->ID ], 'pa-editorial-engine' );
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
		wp_localize_script(
			'pa-editorial-engine-editor',
			'paEditorialSyndication',
			[
				'enabled' => true,
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
