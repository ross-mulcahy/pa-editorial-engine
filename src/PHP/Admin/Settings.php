<?php
/**
 * Admin Settings page and REST registration.
 *
 * Registers plugin settings with show_in_rest for the React-based admin UI,
 * enqueues the admin JS bundle, and handles cache invalidation on save.
 *
 * @package PA\EditorialEngine\Admin
 */

namespace PA\EditorialEngine\Admin;

use PA\EditorialEngine\Core\Cache;

class Settings {

	private const SETTINGS_KEY     = 'pa_engine_settings';
	private const TAXONOMY_MAP_KEY = 'pa_taxonomy_map';
	private const PAGE_SLUG        = 'pa-editorial-engine';

	/**
	 * Hook into WordPress.
	 */
	public function init(): void {
		// Register on both admin_init (for admin pages) and rest_api_init (for REST access).
		\add_action( 'admin_init', [ $this, 'register_settings' ] );
		\add_action( 'rest_api_init', [ $this, 'register_settings' ] );
		\add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
		\add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

		// Flush cache when settings are updated.
		\add_action( 'update_option_' . self::SETTINGS_KEY, [ $this, 'flush_settings_cache' ] );
		\add_action( 'update_option_' . self::TAXONOMY_MAP_KEY, [ $this, 'flush_taxonomy_map_cache' ], 10, 2 );

		// Abilities API registration.
		\add_action( 'wp_abilities_api_categories_init', [ $this, 'register_ability_category' ] );
		\add_action( 'wp_abilities_api_init', [ $this, 'register_abilities' ] );
	}

	/**
	 * Register settings with WordPress and expose via REST API.
	 */
	public function register_settings(): void {
		\register_setting(
			'pa_editorial_group',
			self::SETTINGS_KEY,
			[
				'type'              => 'object',
				'show_in_rest'      => [
					'schema' => $this->get_settings_schema(),
				],
				'default'           => [
					'locking_enabled'       => true,
					'cloning_enabled'       => true,
					'syndication_enabled'   => true,
					'global_priority_offset' => 10,
				],
				'sanitize_callback' => [ $this, 'sanitize_settings' ],
			]
		);

		\register_setting(
			'pa_editorial_group',
			self::TAXONOMY_MAP_KEY,
			[
				'type'              => 'array',
				'show_in_rest'      => [
					'schema' => $this->get_taxonomy_map_schema(),
				],
				'default'           => [],
				'sanitize_callback' => [ $this, 'sanitize_taxonomy_map' ],
			]
		);
	}

	/**
	 * Add the settings page under the Settings menu.
	 */
	public function add_settings_page(): void {
		\add_options_page(
			__( 'PA Editorial Engine', 'pa-editorial-engine' ),
			__( 'PA Editorial Settings', 'pa-editorial-engine' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_admin_root' ]
		);
	}

	/**
	 * Render the React mount point.
	 */
	public function render_admin_root(): void {
		// Static markup for React mount point — no dynamic content.
		echo \wp_kses_post( '<div class="wrap"><div id="pa-editorial-engine-admin"></div></div>' );
	}

	/**
	 * Enqueue the admin JS bundle only on the plugin settings page.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		$asset_file = PA_EDITORIAL_ENGINE_PATH . 'assets/admin.asset.php';

		if ( ! \file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		\wp_enqueue_script(
			'pa-editorial-engine-admin',
			PA_EDITORIAL_ENGINE_URL . 'assets/admin.js',
			$asset['dependencies'] ?? [],
			$asset['version'] ?? PA_EDITORIAL_ENGINE_VERSION,
			true
		);

		// Only enqueue admin CSS if it exists (build may not produce one).
		$admin_css = PA_EDITORIAL_ENGINE_PATH . 'assets/admin.css';
		if ( \file_exists( $admin_css ) ) {
			\wp_enqueue_style(
				'pa-editorial-engine-admin',
				PA_EDITORIAL_ENGINE_URL . 'assets/admin.css',
				[ 'wp-components' ],
				$asset['version'] ?? PA_EDITORIAL_ENGINE_VERSION
			);
		} else {
			\wp_enqueue_style( 'wp-components' );
		}

		// Pass API credential availability flags to JS.
		\wp_localize_script(
			'pa-editorial-engine-admin',
			'paEditorialEngine',
			[
				'apiCredentialsConfigured' => [
					'paWire'  => \defined( 'PA_WIRE_API_KEY' ) && PA_WIRE_API_KEY,
					'digital' => \defined( 'PA_DIGITAL_API_KEY' ) && PA_DIGITAL_API_KEY,
				],
			]
		);
	}

	/**
	 * Sanitize the settings array before saving.
	 *
	 * @param mixed $value Raw input value.
	 * @return array<string, mixed> Sanitized settings.
	 */
	public function sanitize_settings( mixed $value ): array {
		if ( ! \is_array( $value ) ) {
			return [];
		}

		return [
			'locking_enabled'        => ! empty( $value['locking_enabled'] ),
			'cloning_enabled'        => ! empty( $value['cloning_enabled'] ),
			'syndication_enabled'    => ! empty( $value['syndication_enabled'] ),
			'global_priority_offset' => isset( $value['global_priority_offset'] )
				? \absint( $value['global_priority_offset'] )
				: 10,
		];
	}

	/**
	 * Sanitize the taxonomy map array before saving.
	 *
	 * Validates each rule has the required structure and sanitizes values.
	 *
	 * @param mixed $value Raw input value.
	 * @return array<int, array<string, mixed>> Sanitized rules.
	 */
	public function sanitize_taxonomy_map( mixed $value ): array {
		if ( ! \is_array( $value ) ) {
			return [];
		}

		$sanitized = [];

		foreach ( $value as $rule ) {
			if ( ! \is_array( $rule ) ) {
				continue;
			}

			// Require minimum fields.
			if ( empty( $rule['rule_id'] ) || empty( $rule['label'] ) ) {
				continue;
			}

			$sanitized[] = [
				'rule_id'    => \sanitize_key( $rule['rule_id'] ),
				'label'      => \sanitize_text_field( $rule['label'] ),
				'priority'   => isset( $rule['priority'] ) ? \absint( $rule['priority'] ) : 10,
				'active'     => ! empty( $rule['active'] ),
				'conditions' => $this->sanitize_conditions( $rule['conditions'] ?? [] ),
				'actions'    => $this->sanitize_actions( $rule['actions'] ?? [] ),
			];
		}

		return $sanitized;
	}

	/**
	 * Sanitize a rule's conditions block.
	 *
	 * @param mixed $conditions Raw conditions data.
	 * @return array<string, mixed> Sanitized conditions.
	 */
	private function sanitize_conditions( mixed $conditions ): array {
		if ( ! \is_array( $conditions ) ) {
			return [ 'operator' => 'AND', 'rules' => [] ];
		}

		$operator = in_array( $conditions['operator'] ?? '', [ 'AND', 'OR' ], true )
			? $conditions['operator']
			: 'AND';

		$rules = [];
		foreach ( $conditions['rules'] ?? [] as $rule ) {
			if ( ! \is_array( $rule ) ) {
				continue;
			}

			$type = in_array( $rule['type'] ?? '', [ 'taxonomy', 'meta' ], true )
				? $rule['type']
				: 'taxonomy';

			$sanitized_rule = [ 'type' => $type ];

			if ( 'taxonomy' === $type ) {
				$sanitized_rule['slug']    = \sanitize_key( $rule['slug'] ?? '' );
				$sanitized_rule['term_id'] = \absint( $rule['term_id'] ?? 0 );
			} else {
				$sanitized_rule['key']   = \sanitize_key( $rule['key'] ?? '' );
				$sanitized_rule['value'] = \sanitize_text_field( (string) ( $rule['value'] ?? '' ) );
			}

			$rules[] = $sanitized_rule;
		}

		return [ 'operator' => $operator, 'rules' => $rules ];
	}

	/**
	 * Sanitize a rule's actions block.
	 *
	 * @param mixed $actions Raw actions data.
	 * @return array<string, mixed> Sanitized actions.
	 */
	private function sanitize_actions( mixed $actions ): array {
		if ( ! \is_array( $actions ) ) {
			return [ 'select_taxonomies' => [], 'force_meta' => [], 'ui_notices' => [] ];
		}

		// Sanitize select_taxonomies: slug → array of term IDs.
		$taxonomies = [];
		foreach ( $actions['select_taxonomies'] ?? [] as $slug => $term_ids ) {
			$clean_slug = \sanitize_key( $slug );
			if ( $clean_slug && \is_array( $term_ids ) ) {
				$taxonomies[ $clean_slug ] = \array_map( 'absint', $term_ids );
			}
		}

		// Sanitize force_meta: key → value pairs.
		$meta = [];
		foreach ( $actions['force_meta'] ?? [] as $key => $val ) {
			$clean_key = \sanitize_key( $key );
			if ( $clean_key ) {
				$meta[ $clean_key ] = \sanitize_text_field( (string) $val );
			}
		}

		// Sanitize ui_notices.
		$notices = [];
		foreach ( $actions['ui_notices'] ?? [] as $notice ) {
			if ( ! \is_array( $notice ) ) {
				continue;
			}
			$type = in_array( $notice['type'] ?? '', [ 'info', 'warning', 'error' ], true )
				? $notice['type']
				: 'info';
			$notices[] = [
				'type'    => $type,
				'message' => \sanitize_text_field( $notice['message'] ?? '' ),
			];
		}

		return [
			'select_taxonomies' => $taxonomies,
			'force_meta'        => $meta,
			'ui_notices'        => $notices,
		];
	}

	/**
	 * Flush the settings cache when the option is updated.
	 */
	public function flush_settings_cache(): void {
		Cache::delete( self::SETTINGS_KEY );
	}

	/**
	 * Flush the taxonomy map cache and validate term integrity.
	 *
	 * Cross-references all term_ids in the saved rules against the database.
	 * If any term is missing, sets a transient with the broken rule IDs so
	 * the admin UI can surface a warning.
	 *
	 * @param mixed $old_value Previous option value (unused).
	 * @param mixed $new_value New option value.
	 */
	public function flush_taxonomy_map_cache( mixed $old_value = null, mixed $new_value = null ): void {
		Cache::delete( self::TAXONOMY_MAP_KEY );

		if ( ! \is_array( $new_value ) ) {
			return;
		}

		$broken_rules = $this->validate_term_integrity( $new_value );

		if ( ! empty( $broken_rules ) ) {
			\set_transient( 'pa_taxonomy_map_broken_rules', $broken_rules, HOUR_IN_SECONDS );
		} else {
			\delete_transient( 'pa_taxonomy_map_broken_rules' );
		}
	}

	/**
	 * Validate that all term_ids referenced in the taxonomy map exist in the database.
	 *
	 * @param array<int, array<string, mixed>> $rules The taxonomy map rules.
	 * @return array<string> Rule IDs that reference missing terms.
	 */
	public function validate_term_integrity( array $rules ): array {
		$broken = [];

		foreach ( $rules as $rule ) {
			if ( empty( $rule['rule_id'] ) || empty( $rule['active'] ) ) {
				continue;
			}

			$term_ids = $this->extract_term_ids( $rule );

			foreach ( $term_ids as $term_id ) {
				if ( ! \term_exists( $term_id ) ) {
					$broken[] = $rule['rule_id'];
					break; // One missing term is enough to flag the rule.
				}
			}
		}

		return $broken;
	}

	/**
	 * Extract all term_ids from a rule's conditions and actions.
	 *
	 * @param array<string, mixed> $rule A single rule array.
	 * @return array<int> Term IDs found in the rule.
	 */
	private function extract_term_ids( array $rule ): array {
		$ids = [];

		// Condition term_ids.
		$condition_rules = $rule['conditions']['rules'] ?? [];
		foreach ( $condition_rules as $condition ) {
			if ( ! empty( $condition['term_id'] ) ) {
				$ids[] = (int) $condition['term_id'];
			}
		}

		// Action select_taxonomies term_ids.
		$taxonomies = $rule['actions']['select_taxonomies'] ?? [];
		foreach ( $taxonomies as $term_list ) {
			if ( \is_array( $term_list ) ) {
				foreach ( $term_list as $tid ) {
					$ids[] = (int) $tid;
				}
			}
		}

		return $ids;
	}

	/**
	 * Register ability category for PA Media.
	 */
	public function register_ability_category(): void {
		if ( ! \function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		\wp_register_ability_category( 'pa-media', [
			'label' => __( 'PA Media', 'pa-editorial-engine' ),
		] );
	}

	/**
	 * Register the editorial rules management ability.
	 */
	public function register_abilities(): void {
		if ( ! \function_exists( 'wp_register_ability' ) ) {
			return;
		}

		\wp_register_ability( 'pa-media/manage-editorial-rules', [
			'label'               => __( 'Manage Editorial Rules', 'pa-editorial-engine' ),
			'description'         => __( 'Configure taxonomy dependency rules and editorial workflow settings.', 'pa-editorial-engine' ),
			'category'            => 'pa-media',
			'callback'            => '__return_true',
			'permission_callback' => function () {
				return \current_user_can( 'manage_options' );
			},
			'meta'                => [ 'show_in_rest' => true ],
		] );
	}

	/**
	 * JSON Schema for the pa_engine_settings option.
	 *
	 * @return array<string, mixed>
	 */
	private function get_settings_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'locking_enabled'        => [
					'type'    => 'boolean',
					'default' => true,
				],
				'cloning_enabled'        => [
					'type'    => 'boolean',
					'default' => true,
				],
				'syndication_enabled'    => [
					'type'    => 'boolean',
					'default' => true,
				],
				'global_priority_offset' => [
					'type'    => 'integer',
					'default' => 10,
					'minimum' => 1,
					'maximum' => 100,
				],
			],
		];
	}

	/**
	 * JSON Schema for the pa_taxonomy_map option (spec Section 3.4).
	 *
	 * @return array<string, mixed>
	 */
	private function get_taxonomy_map_schema(): array {
		return [
			'type'  => 'array',
			'items' => [
				'type'       => 'object',
				'properties' => [
					'rule_id'  => [ 'type' => 'string' ],
					'label'    => [ 'type' => 'string' ],
					'priority' => [
						'type'    => 'integer',
						'default' => 10,
					],
					'active'   => [
						'type'    => 'boolean',
						'default' => true,
					],
					'conditions' => [
						'type'       => 'object',
						'properties' => [
							'operator' => [
								'type' => 'string',
								'enum' => [ 'AND', 'OR' ],
							],
							'rules'    => [
								'type'  => 'array',
								'items' => [
									'type'       => 'object',
									'properties' => [
										'type'    => [
											'type' => 'string',
											'enum' => [ 'taxonomy', 'meta' ],
										],
										'slug'    => [ 'type' => 'string' ],
										'key'     => [ 'type' => 'string' ],
										'term_id' => [ 'type' => 'integer' ],
										'value'   => [
										'type' => [ 'string', 'number', 'boolean' ],
									],
									],
								],
							],
						],
					],
					'actions'  => [
						'type'       => 'object',
						'properties' => [
							'select_taxonomies' => [
								'type'                 => 'object',
								'additionalProperties' => [
									'type'  => 'array',
									'items' => [ 'type' => 'integer' ],
								],
							],
							'force_meta'        => [
								'type'                 => 'object',
								'additionalProperties' => true,
							],
							'ui_notices'        => [
								'type'  => 'array',
								'items' => [
									'type'       => 'object',
									'properties' => [
										'type'    => [
											'type' => 'string',
											'enum' => [ 'info', 'warning', 'error' ],
										],
										'message' => [ 'type' => 'string' ],
									],
								],
							],
						],
					],
				],
			],
		];
	}
}
