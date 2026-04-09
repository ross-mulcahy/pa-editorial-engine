<?php
/**
 * Unit tests for the Syndication & Correction Hooks feature.
 *
 * @package PA\EditorialEngine\Tests\Unit\Features\Syndication
 */

namespace PA\EditorialEngine\Tests\Unit\Features\Syndication;

use PA\EditorialEngine\Features\Syndication\Syndication;
use PHPUnit\Framework\TestCase;
use WP_Error;
use WP_Post;
use WP_REST_Request;

class SyndicationTest extends TestCase {

	private Syndication $syndication;

	protected function setUp(): void {
		parent::setUp();
		$this->syndication = new Syndication( [ 'syndication_enabled' => true ] );

		$GLOBALS['pa_test_post_meta']     = [];
		$GLOBALS['pa_test_posts']         = [];
		$GLOBALS['pa_test_post_status']   = [];
		$GLOBALS['pa_test_remote_posts']  = [];
		$GLOBALS['pa_test_async_actions'] = [];
		$GLOBALS['pa_test_user_can']      = true;
	}

	// ---------------------------------------------------------------
	// is_enabled()
	// ---------------------------------------------------------------

	public function test_is_enabled_true(): void {
		$this->assertTrue( $this->syndication->is_enabled() );
	}

	public function test_is_enabled_false(): void {
		$s = new Syndication( [ 'syndication_enabled' => false ] );
		$this->assertFalse( $s->is_enabled() );
	}

	// ---------------------------------------------------------------
	// block_stopped_post_saves() — status-based
	// ---------------------------------------------------------------

	public function test_stop_blocks_saves_when_post_is_locked(): void {
		$GLOBALS['pa_test_post_status'][42] = 'locked';

		$prepared     = new \stdClass();
		$prepared->ID = 42;

		$request = new WP_REST_Request();
		$request->set_json_params( [ 'title' => 'Changed' ] );

		$result = $this->syndication->block_stopped_post_saves( $prepared, $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'editorial_stop_active', $result->get_error_code() );
		$this->assertSame( 403, $result->get_error_data()['status'] );
	}

	public function test_stop_allows_saves_when_not_locked(): void {
		$GLOBALS['pa_test_post_status'][42] = 'publish';

		$prepared     = new \stdClass();
		$prepared->ID = 42;

		$request = new WP_REST_Request();
		$request->set_json_params( [ 'title' => 'Changed' ] );

		$result = $this->syndication->block_stopped_post_saves( $prepared, $request );

		$this->assertSame( $prepared, $result );
	}

	public function test_stop_allows_unlock_by_editor(): void {
		$GLOBALS['pa_test_post_status'][42] = 'locked';
		$GLOBALS['pa_test_user_can']        = true;

		$prepared     = new \stdClass();
		$prepared->ID = 42;

		$request = new WP_REST_Request();
		$request->set_json_params( [ 'status' => 'publish' ] );

		$result = $this->syndication->block_stopped_post_saves( $prepared, $request );

		$this->assertSame( $prepared, $result );
	}

	public function test_stop_blocks_unlock_by_non_editor(): void {
		$GLOBALS['pa_test_post_status'][42] = 'locked';
		$GLOBALS['pa_test_user_can']        = false;

		$prepared     = new \stdClass();
		$prepared->ID = 42;

		$request = new WP_REST_Request();
		$request->set_json_params( [ 'status' => 'publish' ] );

		$result = $this->syndication->block_stopped_post_saves( $prepared, $request );

		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_stop_allows_meta_only_update(): void {
		$GLOBALS['pa_test_post_status'][42] = 'locked';

		$prepared     = new \stdClass();
		$prepared->ID = 42;

		$request = new WP_REST_Request();
		$request->set_json_params( [ 'meta' => [ '_pa_is_correction' => true ] ] );

		$result = $this->syndication->block_stopped_post_saves( $prepared, $request );

		$this->assertSame( $prepared, $result );
	}

	public function test_stop_ignores_new_posts(): void {
		$prepared = new \stdClass();

		$request = new WP_REST_Request();
		$request->set_json_params( [ 'title' => 'New' ] );

		$result = $this->syndication->block_stopped_post_saves( $prepared, $request );

		$this->assertSame( $prepared, $result );
	}

	// ---------------------------------------------------------------
	// track_lock_user()
	// ---------------------------------------------------------------

	public function test_track_lock_user_on_lock(): void {
		$GLOBALS['pa_test_current_user'] = 5;

		$post = new WP_Post( [ 'ID' => 42, 'post_type' => 'post' ] );

		$this->syndication->track_lock_user( 'locked', 'publish', $post );

		$this->assertSame( 5, $GLOBALS['pa_test_post_meta'][42]['_pa_editorial_stop_by'] );
		$this->assertSame( 'publish', $GLOBALS['pa_test_post_meta'][42]['_pa_pre_lock_status'] );
	}

	public function test_track_lock_user_on_unlock(): void {
		$GLOBALS['pa_test_post_meta'][42] = [
			'_pa_editorial_stop_by' => 5,
			'_pa_pre_lock_status'   => 'publish',
		];

		$post = new WP_Post( [ 'ID' => 42, 'post_type' => 'post' ] );

		$this->syndication->track_lock_user( 'publish', 'locked', $post );

		$this->assertArrayNotHasKey( '_pa_editorial_stop_by', $GLOBALS['pa_test_post_meta'][42] ?? [] );
		$this->assertArrayNotHasKey( '_pa_pre_lock_status', $GLOBALS['pa_test_post_meta'][42] ?? [] );
	}

	// ---------------------------------------------------------------
	// handle_correction_flag()
	// ---------------------------------------------------------------

	public function test_correction_flag_schedules_action_on_publish(): void {
		$GLOBALS['pa_test_post_meta'][42] = [ '_pa_is_correction' => true ];

		$post = new WP_Post( [
			'ID'          => 42,
			'post_status' => 'publish',
			'post_type'   => 'post',
		] );

		$this->syndication->handle_correction_flag( 'publish', 'draft', $post );

		$this->assertCount( 1, $GLOBALS['pa_test_async_actions'] );
		$this->assertSame( 'pa_send_correction', $GLOBALS['pa_test_async_actions'][0]['hook'] );
	}

	public function test_correction_flag_skipped_when_not_publishing(): void {
		$GLOBALS['pa_test_post_meta'][42] = [ '_pa_is_correction' => true ];
		$post = new WP_Post( [ 'ID' => 42, 'post_type' => 'post' ] );

		$this->syndication->handle_correction_flag( 'draft', 'draft', $post );

		$this->assertEmpty( $GLOBALS['pa_test_async_actions'] );
	}

	public function test_correction_flag_skipped_when_not_flagged(): void {
		$GLOBALS['pa_test_post_meta'][42] = [ '_pa_is_correction' => false ];
		$post = new WP_Post( [ 'ID' => 42, 'post_type' => 'post' ] );

		$this->syndication->handle_correction_flag( 'publish', 'draft', $post );

		$this->assertEmpty( $GLOBALS['pa_test_async_actions'] );
	}

	// ---------------------------------------------------------------
	// get_defaults()
	// ---------------------------------------------------------------

	public function test_get_defaults(): void {
		$defaults = $this->syndication->get_defaults();
		$this->assertArrayHasKey( 'syndication_enabled', $defaults );
		$this->assertTrue( $defaults['syndication_enabled'] );
	}

	protected function tearDown(): void {
		unset(
			$GLOBALS['pa_test_post_meta'],
			$GLOBALS['pa_test_posts'],
			$GLOBALS['pa_test_post_status'],
			$GLOBALS['pa_test_remote_posts'],
			$GLOBALS['pa_test_async_actions'],
			$GLOBALS['pa_test_user_can'],
			$GLOBALS['pa_test_current_user'],
		);
		parent::tearDown();
	}
}
