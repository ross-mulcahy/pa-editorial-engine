<?php
/**
 * Unit tests for the Nuclear Locking feature.
 *
 * These tests verify the locking logic in isolation by mocking WordPress
 * functions. Integration tests (requiring a full WP environment) belong
 * in a separate suite.
 *
 * @package PA\EditorialEngine\Tests\Unit\Features\Locking
 */

namespace PA\EditorialEngine\Tests\Unit\Features\Locking;

use PA\EditorialEngine\Features\Locking\Locking;
use PHPUnit\Framework\TestCase;
use stdClass;
use WP_Error;
use WP_REST_Request;

class LockingTest extends TestCase {

	private Locking $locking;

	protected function setUp(): void {
		parent::setUp();
		$this->locking = new Locking( [ 'locking_enabled' => true ] );
	}

	// ---------------------------------------------------------------
	// is_enabled()
	// ---------------------------------------------------------------

	public function test_is_enabled_returns_true_when_setting_on(): void {
		$this->assertTrue( $this->locking->is_enabled() );
	}

	public function test_is_enabled_returns_false_when_setting_off(): void {
		$locking = new Locking( [ 'locking_enabled' => false ] );
		$this->assertFalse( $locking->is_enabled() );
	}

	public function test_is_enabled_returns_false_with_empty_settings(): void {
		$locking = new Locking( [] );
		$this->assertFalse( $locking->is_enabled() );
	}

	// ---------------------------------------------------------------
	// get_defaults()
	// ---------------------------------------------------------------

	public function test_get_defaults_contains_locking_enabled(): void {
		$defaults = $this->locking->get_defaults();
		$this->assertArrayHasKey( 'locking_enabled', $defaults );
		$this->assertTrue( $defaults['locking_enabled'] );
	}

	// ---------------------------------------------------------------
	// block_locked_post_saves()
	// ---------------------------------------------------------------

	public function test_block_locked_post_saves_allows_new_posts(): void {
		$prepared = new stdClass();
		// No ID means it's a new post creation.
		$request = $this->createMock( WP_REST_Request::class );

		$result = $this->locking->block_locked_post_saves( $prepared, $request );
		$this->assertSame( $prepared, $result );
	}

	public function test_block_locked_post_saves_allows_when_not_locked(): void {
		// wp_check_post_lock returns false (no lock).
		$GLOBALS['pa_test_post_lock'] = false;

		$prepared     = new stdClass();
		$prepared->ID = 42;
		$request      = $this->createMock( WP_REST_Request::class );

		$result = $this->locking->block_locked_post_saves( $prepared, $request );
		$this->assertSame( $prepared, $result );
	}

	public function test_block_locked_post_saves_allows_when_current_user_holds_lock(): void {
		// wp_check_post_lock returns the current user ID (they hold the lock).
		$GLOBALS['pa_test_post_lock']    = 1;
		$GLOBALS['pa_test_current_user'] = 1;

		$prepared     = new stdClass();
		$prepared->ID = 42;
		$request      = $this->createMock( WP_REST_Request::class );

		$result = $this->locking->block_locked_post_saves( $prepared, $request );
		$this->assertSame( $prepared, $result );
	}

	public function test_block_locked_post_saves_blocks_when_locked_by_another(): void {
		// Lock held by user 2, current user is 1.
		$GLOBALS['pa_test_post_lock']    = 2;
		$GLOBALS['pa_test_current_user'] = 1;
		$GLOBALS['pa_test_user_data']    = (object) [
			'display_name' => 'Jane Editor',
		];

		$prepared     = new stdClass();
		$prepared->ID = 42;
		$request      = $this->createMock( WP_REST_Request::class );

		$result = $this->locking->block_locked_post_saves( $prepared, $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'locked_content', $result->get_error_code() );
		$this->assertSame( 403, $result->get_error_data()['status'] );
	}

	// ---------------------------------------------------------------
	// filter_heartbeat_for_role_priority()
	// ---------------------------------------------------------------

	public function test_heartbeat_passthrough_without_lock_data(): void {
		$response = [ 'some' => 'data' ];
		$data     = [];

		$result = $this->locking->filter_heartbeat_for_role_priority( $response, $data );
		$this->assertSame( $response, $result );
	}

	public function test_heartbeat_removes_takeover_for_junior_role(): void {
		$GLOBALS['pa_test_post_lock']    = 10; // Lock holder.
		$GLOBALS['pa_test_current_user'] = 20; // Current user.

		// Lock holder is editor (40), current user is contributor (20).
		$GLOBALS['pa_test_user_roles'] = [
			10 => [ 'editor' ],
			20 => [ 'contributor' ],
		];

		$response = [
			'wp-refresh-post-lock' => [
				'lock_error' => [
					'text' => 'Take Over',
				],
			],
		];
		$data = [
			'wp-refresh-post-lock' => [ 'post_id' => 42 ],
		];

		$result = $this->locking->filter_heartbeat_for_role_priority( $response, $data );

		$this->assertArrayNotHasKey( 'text', $result['wp-refresh-post-lock']['lock_error'] );
	}

	public function test_heartbeat_keeps_takeover_for_senior_role(): void {
		$GLOBALS['pa_test_post_lock']    = 10; // Lock holder.
		$GLOBALS['pa_test_current_user'] = 20; // Current user.

		// Lock holder is author (30), current user is administrator (50).
		$GLOBALS['pa_test_user_roles'] = [
			10 => [ 'author' ],
			20 => [ 'administrator' ],
		];

		$response = [
			'wp-refresh-post-lock' => [
				'lock_error' => [
					'text' => 'Take Over',
				],
			],
		];
		$data = [
			'wp-refresh-post-lock' => [ 'post_id' => 42 ],
		];

		$result = $this->locking->filter_heartbeat_for_role_priority( $response, $data );

		$this->assertSame( 'Take Over', $result['wp-refresh-post-lock']['lock_error']['text'] );
	}

	// ---------------------------------------------------------------
	// get_user_role_weight()
	// ---------------------------------------------------------------

	public function test_role_weight_administrator(): void {
		$GLOBALS['pa_test_user_roles'] = [ 1 => [ 'administrator' ] ];
		$this->assertSame( 50, $this->locking->get_user_role_weight( 1 ) );
	}

	public function test_role_weight_editor(): void {
		$GLOBALS['pa_test_user_roles'] = [ 1 => [ 'editor' ] ];
		$this->assertSame( 40, $this->locking->get_user_role_weight( 1 ) );
	}

	public function test_role_weight_unknown_role_returns_zero(): void {
		$GLOBALS['pa_test_user_roles'] = [ 1 => [ 'custom_role' ] ];
		$this->assertSame( 0, $this->locking->get_user_role_weight( 1 ) );
	}

	public function test_role_weight_uses_highest_when_multiple(): void {
		$GLOBALS['pa_test_user_roles'] = [ 1 => [ 'subscriber', 'editor' ] ];
		$this->assertSame( 40, $this->locking->get_user_role_weight( 1 ) );
	}

	public function test_role_weight_nonexistent_user_returns_zero(): void {
		$GLOBALS['pa_test_user_roles'] = [];
		$this->assertSame( 0, $this->locking->get_user_role_weight( 999 ) );
	}

	protected function tearDown(): void {
		unset(
			$GLOBALS['pa_test_post_lock'],
			$GLOBALS['pa_test_current_user'],
			$GLOBALS['pa_test_user_data'],
			$GLOBALS['pa_test_user_roles'],
		);
		parent::tearDown();
	}
}
