<?php
/**
 * Unit tests for the Metadata Engine feature.
 *
 * @package PA\EditorialEngine\Tests\Unit\Features\Metadata
 */

namespace PA\EditorialEngine\Tests\Unit\Features\Metadata;

use PA\EditorialEngine\Features\Metadata\Metadata;
use PHPUnit\Framework\TestCase;

class MetadataTest extends TestCase {

	private Metadata $metadata;

	protected function setUp(): void {
		parent::setUp();
		$this->metadata = new Metadata();
	}

	public function test_is_enabled_always_true(): void {
		$this->assertTrue( $this->metadata->is_enabled() );
	}

	public function test_get_defaults_returns_empty_array(): void {
		$this->assertSame( [], $this->metadata->get_defaults() );
	}

	public function test_get_taxonomy_map_returns_empty_when_no_option(): void {
		$GLOBALS['pa_test_options'] = [];
		$GLOBALS['pa_test_cache']   = [];

		$map = $this->metadata->get_taxonomy_map();
		$this->assertSame( [], $map );
	}

	public function test_get_taxonomy_map_returns_cached_value(): void {
		$expected = [
			[
				'rule_id' => 'test-rule',
				'label'   => 'Test',
				'active'  => true,
			],
		];

		$GLOBALS['pa_test_cache']['pa_taxonomy_map'] = $expected;

		$map = $this->metadata->get_taxonomy_map();
		$this->assertSame( $expected, $map );
	}

	public function test_get_taxonomy_map_falls_back_to_option(): void {
		$expected = [
			[
				'rule_id' => 'option-rule',
				'label'   => 'From Option',
				'active'  => true,
			],
		];

		$GLOBALS['pa_test_cache']   = []; // Empty cache.
		$GLOBALS['pa_test_options'] = [ 'pa_taxonomy_map' => $expected ];

		$map = $this->metadata->get_taxonomy_map();
		$this->assertSame( $expected, $map );
	}

	protected function tearDown(): void {
		unset( $GLOBALS['pa_test_options'], $GLOBALS['pa_test_cache'] );
		parent::tearDown();
	}
}
