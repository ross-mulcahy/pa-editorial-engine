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
		$GLOBALS['pa_test_remote_posts']  = [];
		$GLOBALS['pa_test_async_actions'] = [];
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
	// block_stopped_post_saves()
	// ---------------------------------------------------------------

	public function test_stop_blocks_all_saves_when_active(): void {
		$GLOBALS['pa_test_post_meta'][42] = [ '_pa_editorial_stop' => true ];

		$prepared     = new \stdClass();
		$prepared->ID = 42;

		$request = new WP_REST_Request();
		$request->set_json_params( [ 'title' => 'Changed', 'content' => 'New content' ] );

		$result = $this->syndication->block_stopped_post_saves( $prepared, $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'editorial_stop_active', $result->get_error_code() );
		$this->assertSame( 403, $result->get_error_data()['status'] );
	}

	public function test_stop_allows_saves_when_inactive(): void {
		$GLOBALS['pa_test_post_meta'][42] = [ '_pa_editorial_stop' => false ];

		$prepared     = new \stdClass();
		$prepared->ID = 42;

		$request = new WP_REST_Request();
		$request->set_json_params( [ 'title' => 'Changed' ] );

		$result = $this->syndication->block_stopped_post_saves( $prepared, $request );

		$this->assertSame( $prepared, $result );
	}

	public function test_stop_allows_meta_only_update_to_toggle_off(): void {
		$GLOBALS['pa_test_post_meta'][42] = [ '_pa_editorial_stop' => true ];

		$prepared     = new \stdClass();
		$prepared->ID = 42;

		// Request with ONLY meta — this is how the toggle-off works.
		$request = new WP_REST_Request();
		$request->set_json_params( [ 'meta' => [ '_pa_editorial_stop' => false ] ] );

		$result = $this->syndication->block_stopped_post_saves( $prepared, $request );

		$this->assertSame( $prepared, $result );
	}

	public function test_stop_blocks_when_meta_plus_content(): void {
		$GLOBALS['pa_test_post_meta'][42] = [ '_pa_editorial_stop' => true ];

		$prepared     = new \stdClass();
		$prepared->ID = 42;

		// Request has meta AND content — should be blocked.
		$request = new WP_REST_Request();
		$request->set_json_params( [ 'meta' => [ '_pa_editorial_stop' => false ], 'title' => 'Sneaky change' ] );

		$result = $this->syndication->block_stopped_post_saves( $prepared, $request );

		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_stop_ignores_new_posts(): void {
		$prepared = new \stdClass();
		// No ID = new post.

		$request = new WP_REST_Request();
		$request->set_json_params( [ 'title' => 'New post' ] );

		$result = $this->syndication->block_stopped_post_saves( $prepared, $request );

		$this->assertSame( $prepared, $result );
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
		$this->assertSame( [ 42 ], $GLOBALS['pa_test_async_actions'][0]['args'] );
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
			$GLOBALS['pa_test_remote_posts'],
			$GLOBALS['pa_test_async_actions'],
		);
		parent::tearDown();
	}
}
