<?php
/**
 * Feature Manager — the "brain" of the plugin.
 *
 * Reads settings from the object cache (falling back to wp_options),
 * then instantiates and initialises only the enabled feature modules.
 *
 * @package PA\EditorialEngine\Core
 */

namespace PA\EditorialEngine\Core;

use PA\EditorialEngine\Admin\Settings;
use PA\EditorialEngine\Features\Cloning\Cloning;
use PA\EditorialEngine\Features\Locking\Locking;
use PA\EditorialEngine\Features\Metadata\Metadata;
use PA\EditorialEngine\Features\Syndication\Syndication;

class FeatureManager {

	private const SETTINGS_KEY = 'pa_engine_settings';

	/**
	 * Registered feature classes.
	 *
	 * @var array<class-string<FeatureInterface>>
	 */
	private array $features = [
		Locking::class,
		Metadata::class,
		Cloning::class,
		Syndication::class,
	];

	/**
	 * Boot the plugin — hooked to `plugins_loaded`.
	 */
	public function boot(): void {
		$settings = $this->load_settings();

		// Admin settings page is always active (not a toggleable feature).
		( new Settings() )->init();

		// Register post meta keys (always available regardless of feature state).
		add_action( 'init', [ $this, 'register_meta' ] );

		// Initialise enabled features.
		foreach ( $this->features as $class ) {
			/** @var FeatureInterface $feature */
			$feature = new $class( $settings );

			if ( $feature->is_enabled() ) {
				$feature->init();
			}
		}
	}

	/**
	 * Load settings from cache, falling back to the options table.
	 *
	 * @return array<string, mixed>
	 */
	private function load_settings(): array {
		$cached = Cache::get( self::SETTINGS_KEY );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$settings = get_option( self::SETTINGS_KEY, $this->get_all_defaults() );

		if ( ! is_array( $settings ) ) {
			$settings = $this->get_all_defaults();
		}

		Cache::set( self::SETTINGS_KEY, $settings );

		return $settings;
	}

	/**
	 * Merge defaults from all registered features.
	 *
	 * @return array<string, mixed>
	 */
	private function get_all_defaults(): array {
		$defaults = [];

		foreach ( $this->features as $class ) {
			/** @var FeatureInterface $feature */
			$feature  = new $class( [] );
			$defaults = array_merge( $defaults, $feature->get_defaults() );
		}

		return $defaults;
	}

	/**
	 * Register plugin post meta keys.
	 *
	 * These are registered globally (not behind feature toggles) so data
	 * is never orphaned if a feature is temporarily disabled.
	 */
	public function register_meta(): void {
		$post_types = [ 'post' ];

		foreach ( $post_types as $post_type ) {
			register_post_meta(
				$post_type,
				'_pa_editorial_stop',
				[
					'type'              => 'boolean',
					'default'           => false,
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'rest_sanitize_boolean',
					'auth_callback'     => function () {
						return current_user_can( 'edit_posts' );
					},
				]
			);

			register_post_meta(
				$post_type,
				'_pa_is_correction',
				[
					'type'              => 'boolean',
					'default'           => false,
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'rest_sanitize_boolean',
					'auth_callback'     => function () {
						return current_user_can( 'edit_posts' );
					},
				]
			);

			register_post_meta(
				$post_type,
				'_pa_correction_note',
				[
					'type'              => 'string',
					'default'           => '',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'sanitize_textarea_field',
					'auth_callback'     => function () {
						return current_user_can( 'edit_posts' );
					},
				]
			);

			register_post_meta(
				$post_type,
				'_pa_parent_story_id',
				[
					'type'              => 'integer',
					'default'           => 0,
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'absint',
					'auth_callback'     => function () {
						return current_user_can( 'edit_posts' );
					},
				]
			);

			register_post_meta(
				$post_type,
				'_pa_auto_mapped_rules',
				[
					'type'         => 'array',
					'default'      => [],
					'single'       => true,
					'show_in_rest' => [
						'schema' => [
							'type'  => 'array',
							'items' => [
								'type' => 'string',
							],
						],
					],
					'auth_callback' => function () {
						return current_user_can( 'edit_posts' );
					},
				]
			);
		}
	}

	/**
	 * Activation hook callback — set default settings.
	 */
	public static function activate(): void {
		add_option( 'pa_engine_settings', ( new self() )->get_all_defaults() );
	}
}
