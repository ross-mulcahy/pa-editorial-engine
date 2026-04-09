<?php
/**
 * "New Lead" Cloning Engine feature.
 *
 * Allows editors to branch a story into a new draft version while
 * tracking lineage via parent post meta. Strips headline, abstract,
 * featured image, and editorial stop from the clone.
 *
 * @package PA\EditorialEngine\Features\Cloning
 */

namespace PA\EditorialEngine\Features\Cloning;

use PA\EditorialEngine\Core\FeatureInterface;
use WP_Post;

class Cloning implements FeatureInterface {

	/**
	 * @param array<string, mixed> $settings Plugin settings array.
	 */
	public function __construct(
		private readonly array $settings = [],
	) {}

	public function is_enabled(): bool {
		return ! empty( $this->settings['cloning_enabled'] );
	}

	public function init(): void {
		\add_filter( 'post_row_actions', [ $this, 'add_clone_action' ], 10, 2 );
		\add_action( 'admin_action_pa_clone_post', [ $this, 'handle_clone_request' ] );
		\add_action( 'admin_notices', [ $this, 'show_clone_notice' ] );
		\add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );
	}

	/**
	 * Add "Add New Lead" link to post list row actions.
	 *
	 * @param array<string, string> $actions Existing row actions.
	 * @param WP_Post               $post    Current post object.
	 * @return array<string, string> Modified row actions.
	 */
	public function add_clone_action( array $actions, WP_Post $post ): array {
		if ( ! \current_user_can( 'edit_others_posts' ) ) {
			return $actions;
		}

		if ( 'post' !== $post->post_type ) {
			return $actions;
		}

		$url = \wp_nonce_url(
			\admin_url( 'admin.php?action=pa_clone_post&post=' . $post->ID ),
			'pa_clone_' . $post->ID
		);

		$actions['pa_clone'] = \sprintf(
			'<a href="%s">%s</a>',
			\esc_url( $url ),
			\esc_html__( 'Add New Lead', 'pa-editorial-engine' )
		);

		return $actions;
	}

	/**
	 * Handle the clone admin action request.
	 */
	public function handle_clone_request(): void {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- absint handles sanitization.
		$post_id = isset( $_GET['post'] ) ? \absint( \wp_unslash( $_GET['post'] ) ) : 0;

		if ( ! $post_id ) {
			\wp_die( \esc_html__( 'No post specified.', 'pa-editorial-engine' ) );
		}

		\check_admin_referer( 'pa_clone_' . $post_id );

		if ( ! \current_user_can( 'edit_others_posts' ) ) {
			\wp_die( \esc_html__( 'You do not have permission to clone posts.', 'pa-editorial-engine' ) );
		}

		$new_id = $this->clone_post( $post_id );

		if ( \is_wp_error( $new_id ) ) {
			\wp_die( \esc_html( $new_id->get_error_message() ) );
		}

		\wp_safe_redirect(
			\admin_url( 'post.php?post=' . $new_id . '&action=edit&pa_cloned=1' )
		);
		exit;
	}

	/**
	 * Clone a post into a new draft with stripped metadata.
	 *
	 * @param int $post_id Source post ID.
	 * @return int|\WP_Error New post ID on success.
	 */
	public function clone_post( int $post_id ): int|\WP_Error {
		$source = \get_post( $post_id );

		if ( ! $source ) {
			return new \WP_Error(
				'pa_clone_not_found',
				__( 'Source post not found.', 'pa-editorial-engine' )
			);
		}

		// Create the new post — copy content and author, strip title/excerpt.
		$new_post = [
			'post_title'   => '',
			'post_content' => $source->post_content,
			'post_excerpt' => '',
			'post_author'  => (int) $source->post_author,
			'post_status'  => 'draft',
			'post_type'    => $source->post_type,
		];

		$new_id = \wp_insert_post( $new_post, true );

		if ( \is_wp_error( $new_id ) ) {
			return $new_id;
		}

		// Copy all taxonomy terms from the source.
		$taxonomies = \get_object_taxonomies( $source->post_type );
		foreach ( $taxonomies as $taxonomy ) {
			$terms = \wp_get_object_terms( $post_id, $taxonomy, [ 'fields' => 'ids' ] );
			if ( ! \is_wp_error( $terms ) && ! empty( $terms ) ) {
				\wp_set_object_terms( $new_id, $terms, $taxonomy );
			}
		}

		// Strip featured image and editorial stop from the clone.
		\delete_post_meta( $new_id, '_thumbnail_id' );
		\update_post_meta( $new_id, '_pa_editorial_stop', false );

		// Track parent relationship.
		\update_post_meta( $new_id, '_pa_parent_story_id', $post_id );

		return $new_id;
	}

	/**
	 * Show admin notice after successful clone.
	 */
	public function show_clone_notice(): void {
		$screen = \get_current_screen();

		if ( ! $screen || 'post' !== $screen->base ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only; nonce verified at clone time.
		if ( empty( $_GET['pa_cloned'] ) ) {
			return;
		}

		\printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			\esc_html__( 'New Lead created. Please provide a new headline and featured image.', 'pa-editorial-engine' )
		);
	}

	/**
	 * Enqueue editor assets for the "Add New Lead" button.
	 */
	public function enqueue_editor_assets(): void {
		global $post;

		if ( ! $post || 'post' !== $post->post_type ) {
			return;
		}

		if ( ! \current_user_can( 'edit_others_posts' ) ) {
			return;
		}

		\wp_localize_script(
			'pa-editorial-engine-editor',
			'paEditorialCloning',
			[
				'enabled'  => true,
				'cloneUrl' => \wp_nonce_url(
					\admin_url( 'admin.php?action=pa_clone_post&post=' . $post->ID ),
					'pa_clone_' . $post->ID
				),
			]
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_defaults(): array {
		return [
			'cloning_enabled' => true,
		];
	}
}
