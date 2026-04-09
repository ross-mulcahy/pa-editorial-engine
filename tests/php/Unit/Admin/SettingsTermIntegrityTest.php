<?php
/**
 * Unit tests for the term integrity validation in Settings.
 *
 * @package PA\EditorialEngine\Tests\Unit\Admin
 */

namespace PA\EditorialEngine\Tests\Unit\Admin;

use PA\EditorialEngine\Admin\Settings;
use PHPUnit\Framework\TestCase;

class SettingsTermIntegrityTest extends TestCase {

	private Settings $settings;

	protected function setUp(): void {
		parent::setUp();
		$this->settings = new Settings();
	}

	public function test_validate_term_integrity_returns_empty_when_all_valid(): void {
		// All referenced terms exist.
		$GLOBALS['pa_test_term_exists'] = [ 10, 20, 30 ];

		$rules = [
			[
				'rule_id'    => 'politics-uk',
				'label'      => 'Politics → UK',
				'active'     => true,
				'conditions' => [
					'operator' => 'AND',
					'rules'    => [
						[ 'type' => 'taxonomy', 'slug' => 'topic', 'term_id' => 10 ],
					],
				],
				'actions'    => [
					'select_taxonomies' => [
						'territory' => [ 20 ],
						'service'   => [ 30 ],
					],
				],
			],
		];

		$broken = $this->settings->validate_term_integrity( $rules );
		$this->assertSame( [], $broken );
	}

	public function test_validate_term_integrity_flags_missing_condition_term(): void {
		// Term 10 exists, term 99 does not.
		$GLOBALS['pa_test_term_exists'] = [ 10 ];

		$rules = [
			[
				'rule_id'    => 'broken-rule',
				'label'      => 'Broken',
				'active'     => true,
				'conditions' => [
					'operator' => 'AND',
					'rules'    => [
						[ 'type' => 'taxonomy', 'slug' => 'topic', 'term_id' => 99 ],
					],
				],
				'actions'    => [
					'select_taxonomies' => [
						'territory' => [ 10 ],
					],
				],
			],
		];

		$broken = $this->settings->validate_term_integrity( $rules );
		$this->assertSame( [ 'broken-rule' ], $broken );
	}

	public function test_validate_term_integrity_flags_missing_action_term(): void {
		// Term 10 exists, term 88 does not.
		$GLOBALS['pa_test_term_exists'] = [ 10 ];

		$rules = [
			[
				'rule_id'    => 'bad-action',
				'label'      => 'Bad Action',
				'active'     => true,
				'conditions' => [
					'operator' => 'AND',
					'rules'    => [
						[ 'type' => 'taxonomy', 'slug' => 'topic', 'term_id' => 10 ],
					],
				],
				'actions'    => [
					'select_taxonomies' => [
						'service' => [ 88 ],
					],
				],
			],
		];

		$broken = $this->settings->validate_term_integrity( $rules );
		$this->assertSame( [ 'bad-action' ], $broken );
	}

	public function test_validate_term_integrity_skips_inactive_rules(): void {
		$GLOBALS['pa_test_term_exists'] = [];

		$rules = [
			[
				'rule_id'    => 'inactive',
				'label'      => 'Inactive Rule',
				'active'     => false,
				'conditions' => [
					'operator' => 'AND',
					'rules'    => [
						[ 'type' => 'taxonomy', 'slug' => 'topic', 'term_id' => 999 ],
					],
				],
				'actions'    => [
					'select_taxonomies' => [],
				],
			],
		];

		$broken = $this->settings->validate_term_integrity( $rules );
		$this->assertSame( [], $broken );
	}

	public function test_validate_term_integrity_handles_meta_conditions(): void {
		// Meta conditions don't reference term_ids, should not break.
		$GLOBALS['pa_test_term_exists'] = [ 10 ];

		$rules = [
			[
				'rule_id'    => 'meta-rule',
				'label'      => 'Meta Only',
				'active'     => true,
				'conditions' => [
					'operator' => 'AND',
					'rules'    => [
						[ 'type' => 'meta', 'key' => '_pa_format', 'value' => 'breaking' ],
					],
				],
				'actions'    => [
					'select_taxonomies' => [
						'service' => [ 10 ],
					],
				],
			],
		];

		$broken = $this->settings->validate_term_integrity( $rules );
		$this->assertSame( [], $broken );
	}

	protected function tearDown(): void {
		unset( $GLOBALS['pa_test_term_exists'] );
		parent::tearDown();
	}
}
