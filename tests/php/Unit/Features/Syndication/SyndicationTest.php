<?php
/**
 * Unit tests for the Syndication & Correction Hooks feature.
 *
 * @package PA\EditorialEngine\Tests\Unit\Features\Syndication
 */

namespace PA\EditorialEngine\Tests\Unit\Features\Syndication;

use PA\EditorialEngine\Features\Syndication\Syndication;
use PHPUnit\Framework\TestCase;
use WP_Post;

class SyndicationTest extends TestCase {

	private Syndication $syndication;

	protected function setUp(): void {
		parent::setUp();
		$this->syndication = new Syndication( [ 'syndication_enabled' => true ] );

		$GLOBALS['pa_test_post_meta']      = [];
		$GLOBALS['pa_test_posts']          = [];
		$GLOBALS['pa_test_remote_posts']   = [];
		$GLOBALS['pa_test_async_actions']  = [];
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
	// enforce_editorial_stop()
	// ---------------------------------------------------------------

	public function test_editorial_stop_forces_pending_on_publish(): void {
		$GLOBALS['pa_test_post_meta'][42] = [ '_pa_editorial_stop' => true ];

		$data    = [ 'post_status' => 'publish' ];
		$postarr = [ 'ID' => 42 ];

		$result = $this->syndication->enforce_editorial_stop( $data, $postarr );

		$this->assertSame( 'pending', $result['post_status'] );
	}

	public function test_editorial_stop_allows_publish_when_inactive(): void {
		$GLOBALS['pa_test_post_meta'][42] = [ '_pa_editorial_stop' => false ];

		$data    = [ 'post_status' => 'publish' ];
		$postarr = [ 'ID' => 42 ];

		$result = $this->syndication->enforce_editorial_stop( $data, $postarr );

		$this->assertSame( 'publish', $result['post_status'] );
	}

	public function test_editorial_stop_ignores_non_publish_transitions(): void {
		$GLOBALS['pa_test_post_meta'][42] = [ '_pa_editorial_stop' => true ];

		$data    = [ 'post_status' => 'draft' ];
		$postarr = [ 'ID' => 42 ];

		$result = $this->syndication->enforce_editorial_stop( $data, $postarr );

		$this->assertSame( 'draft', $result['post_status'] );
	}

	public function test_editorial_stop_ignores_new_posts(): void {
		$data    = [ 'post_status' => 'publish' ];
		$postarr = []; // No ID = new post.

		$result = $this->syndication->enforce_editorial_stop( $data, $postarr );

		$this->assertSame( 'publish', $result['post_status'] );
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
