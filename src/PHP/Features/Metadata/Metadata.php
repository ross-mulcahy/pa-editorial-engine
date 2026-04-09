<?php
/**
 * Metadata Engine feature.
 *
 * Automates taxonomy dependency mapping — when a Topic is selected in
 * the editor, the corresponding Service and Territory terms are
 * programmatically applied based on the admin-configured rule set.
 *
 * @package PA\EditorialEngine\Features\Metadata
 */

namespace PA\EditorialEngine\Features\Metadata;

use PA\EditorialEngine\Core\Cache;
use PA\EditorialEngine\Core\FeatureInterface;

class Metadata implements FeatureInterface {

	private const TAXONOMY_MAP_KEY = 'pa_taxonomy_map';

	/**
	 * @param array<string, mixed> $settings Plugin settings array (unused; required by FeatureInterface contract).
	 */
	public function __construct(
		private readonly array $settings = [], // @phpstan-ignore property.onlyWritten
	) {}

	public function is_enabled(): bool {
		// Metadata engine is always active when the plugin is loaded;
		// it relies on the taxonomy map having rules configured.
		return true;
	}

	public function init(): void {
		\add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );
		\add_action( 'save_post', [ $this, 'log_auto_mapped_rules' ], 20, 2 );
	}

	/**
	 * Enqueue editor assets and pass the taxonomy map to JS.
	 */
	public function enqueue_editor_assets(): void {
		$asset_file = PA_EDITORIAL_ENGINE_PATH . 'assets/editor.asset.php';

		if ( ! \file_exists( $asset_file ) ) {
			return;
		}

		$taxonomy_map = $this->get_taxonomy_map();

		\wp_localize_script(
			'pa-editorial-engine-editor',
			'paEditorialMetadata',
			[
				'enabled'     => true,
				'taxonomyMap' => $taxonomy_map,
				'brokenRules' => \get_transient( 'pa_taxonomy_map_broken_rules' ) ?: [],
			]
		);
	}

	/**
	 * Log which auto-mapping rules were applied on save (audit trail).
	 *
	 * The JS client sends the list of fired rule IDs in the post meta
	 * `_pa_auto_mapped_rules` via the REST API on save.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function log_auto_mapped_rules( int $post_id, \WP_Post $post ): void {
		// Only log for standard posts; skip revisions and autosaves.
		if ( \wp_is_post_revision( $post_id ) || \wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// The meta value is already set by the editor via REST; this hook
		// exists as an extension point for future audit logging.
	}

	/**
	 * Retrieve the taxonomy map from cache or options.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_taxonomy_map(): array {
		$cached = Cache::get( self::TAXONOMY_MAP_KEY );

		if ( false !== $cached && \is_array( $cached ) ) {
			return $cached;
		}

		$map = \get_option( self::TAXONOMY_MAP_KEY, [] );

		if ( ! \is_array( $map ) ) {
			$map = [];
		}

		Cache::set( self::TAXONOMY_MAP_KEY, $map );

		return $map;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_defaults(): array {
		return [];
	}
}
