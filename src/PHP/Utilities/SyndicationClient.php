<?php
/**
 * Syndication Client — handles outbound API calls to the PA Wire endpoint.
 *
 * All calls are async (invoked via Action Scheduler). This class is
 * responsible for building the payload, making the HTTP request, and
 * handling failures via VIP logging.
 *
 * @package PA\EditorialEngine\Utilities
 */

namespace PA\EditorialEngine\Utilities;

class SyndicationClient {

	/**
	 * Send a correction notification for a published post.
	 *
	 * @param int $post_id Post ID.
	 * @return bool True on success, false on failure.
	 */
	public function send_correction( int $post_id ): bool {
		$post = get_post( $post_id );

		if ( ! $post || 'publish' !== $post->post_status ) {
			return false;
		}

		$correction_note = get_post_meta( $post_id, '_pa_correction_note', true );
		$endpoint        = $this->get_endpoint();

		if ( ! $endpoint ) {
			$this->log_error( $post_id, 'PA Wire API endpoint not configured.' );
			return false;
		}

		$payload = $this->build_payload( $post, $correction_note );

		$response = wp_safe_remote_post(
			$endpoint,
			[
				'headers' => [
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $this->get_api_key(),
				],
				'body'    => wp_json_encode( $payload ),
				'timeout' => 3,
			]
		);

		if ( is_wp_error( $response ) ) {
			$this->log_error( $post_id, $response->get_error_message() );
			return false;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code < 200 || $status_code >= 300 ) {
			$this->log_error(
				$post_id,
				sprintf( 'PA Wire API returned HTTP %d.', $status_code )
			);
			return false;
		}

		return true;
	}

	/**
	 * Build the correction payload from post data.
	 *
	 * @param \WP_Post $post            Post object.
	 * @param string   $correction_note Correction note text.
	 * @return array<string, mixed> API payload.
	 */
	private function build_payload( \WP_Post $post, string $correction_note ): array {
		return [
			'post_id'         => $post->ID,
			'title'           => $post->post_title,
			'url'             => get_permalink( $post->ID ),
			'correction_note' => $correction_note,
			'corrected_at'    => current_time( 'c' ),
			'author_id'       => $post->post_author,
		];
	}

	/**
	 * Get the PA Wire API endpoint URL.
	 *
	 * @return string|null Endpoint URL, or null if not configured.
	 */
	private function get_endpoint(): ?string {
		if ( defined( 'PA_WIRE_API_ENDPOINT' ) && PA_WIRE_API_ENDPOINT ) {
			return PA_WIRE_API_ENDPOINT;
		}

		return null;
	}

	/**
	 * Get the PA Wire API key from environment.
	 *
	 * @return string API key, or empty string if not configured.
	 */
	private function get_api_key(): string {
		if ( defined( 'PA_WIRE_API_KEY' ) && PA_WIRE_API_KEY ) {
			return PA_WIRE_API_KEY;
		}

		return '';
	}

	/**
	 * Log a syndication error via VIP logging (or error_log fallback).
	 *
	 * @param int    $post_id Post ID.
	 * @param string $message Error message.
	 */
	private function log_error( int $post_id, string $message ): void {
		$log_message = sprintf(
			'[PA Editorial Engine] Correction syndication failed for post %d: %s',
			$post_id,
			$message
		);

		if ( function_exists( 'wpcomvip_log' ) ) {
			wpcomvip_log( $log_message );
		} else {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( $log_message );
		}
	}
}
