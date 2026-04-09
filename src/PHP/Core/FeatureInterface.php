<?php
/**
 * Feature interface — every feature module must implement this contract.
 *
 * @package PA\EditorialEngine\Core
 */

namespace PA\EditorialEngine\Core;

interface FeatureInterface {

	/**
	 * Determine if the feature is enabled in settings.
	 */
	public function is_enabled(): bool;

	/**
	 * Hook into WordPress actions and filters.
	 */
	public function init(): void;

	/**
	 * Define default settings for this feature.
	 *
	 * @return array<string, mixed>
	 */
	public function get_defaults(): array;
}
