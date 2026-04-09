<?php
/**
 * Unit tests for the Cloning Engine feature.
 *
 * @package PA\EditorialEngine\Tests\Unit\Features\Cloning
 */

namespace PA\EditorialEngine\Tests\Unit\Features\Cloning;

use PA\EditorialEngine\Features\Cloning\Cloning;
use PHPUnit\Framework\TestCase;
use WP_Post;

class CloningTest extends TestCase {

	private Cloning $cloning;

	protected function setUp(): void {
		parent::setUp();
		$this->cloning = new Cloning( [ 'cloning_enabled' => true ] );

		// Reset global test state.
		$GLOBALS['pa_test_posts']          = [];
		$GLOBALS['pa_test_post_meta']      = [];
		$GLOBALS['pa_test_inserted_posts'] = [];
		$GLOBALS['pa_test_set_terms']      = [];
		$GLOBALS['pa_test_object_terms']   = [];
		$GLOBALS['pa_test_taxonomies']     = [ 'category', 'post_tag', 'topic' ];
		$GLOBALS['pa_test_user_can']       = true;
	}

	// ---------------------------------------------------------------
	// is_enabled()
	// ---------------------------------------------------------------

	public function test_is_enabled_true(): void {
		$this->assertTrue( $this->cloning->is_enabled() );
	}

	public function test_is_enabled_false(): void {
		$cloning = new Cloning( [ 'cloning_enabled' => false ] );
		$this->assertFalse( $cloning->is_enabled() );
	}

	// ---------------------------------------------------------------
	// clone_post()
	// ---------------------------------------------------------------

	public function test_clone_post_returns_error_for_missing_source(): void {
		$result = $this->cloning->clone_post( 999 );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'pa_clone_not_found', $result->get_error_code() );
	}

	public function test_clone_post_creates_draft_with_content(): void {
		$source = new WP_Post( [
			'ID'           => 42,
			'post_title'   => 'Original Title',
			'post_content' => '<p>Breaking news content.</p>',
			'post_excerpt' => 'Original abstract.',
			'post_author'  => 5,
			'post_status'  => 'publish',
			'post_type'    => 'post',
		] );

		$GLOBALS['pa_test_posts'][42]        = $source;
		$GLOBALS['pa_test_object_terms'][42] = [
			'category' => [ 1, 2 ],
			'topic'    => [ 10 ],
		];

		$new_id = $this->cloning->clone_post( 42 );

		$this->assertIsInt( $new_id );
		$this->assertGreaterThan( 0, $new_id );

		// Verify inserted post data.
		$inserted = $GLOBALS['pa_test_inserted_posts'][ $new_id ];
		$this->assertSame( '<p>Breaking news content.</p>', $inserted['post_content'] );
		$this->assertSame( 5, $inserted['post_author'] );
		$this->assertSame( 'draft', $inserted['post_status'] );
	}

	public function test_clone_post_strips_title_and_excerpt(): void {
		$source = new WP_Post( [
			'ID'           => 42,
			'post_title'   => 'Original Title',
			'post_content' => 'Content',
			'post_excerpt' => 'Abstract',
			'post_author'  => 1,
			'post_type'    => 'post',
		] );

		$GLOBALS['pa_test_posts'][42] = $source;

		$new_id  = $this->cloning->clone_post( 42 );
		$inserted = $GLOBALS['pa_test_inserted_posts'][ $new_id ];

		$this->assertSame( '', $inserted['post_title'] );
		$this->assertSame( '', $inserted['post_excerpt'] );
	}

	public function test_clone_post_sets_parent_meta(): void {
		$source = new WP_Post( [
			'ID'           => 42,
			'post_title'   => 'Title',
			'post_content' => 'Content',
			'post_author'  => 1,
			'post_type'    => 'post',
		] );

		$GLOBALS['pa_test_posts'][42] = $source;

		$new_id = $this->cloning->clone_post( 42 );

		$this->assertSame( 42, $GLOBALS['pa_test_post_meta'][ $new_id ]['_pa_parent_story_id'] );
	}

	public function test_clone_post_strips_editorial_stop(): void {
		$source = new WP_Post( [
			'ID'           => 42,
			'post_title'   => 'Title',
			'post_content' => 'Content',
			'post_author'  => 1,
			'post_type'    => 'post',
		] );

		$GLOBALS['pa_test_posts'][42]              = $source;
		$GLOBALS['pa_test_post_meta'][42] = [
			'_pa_editorial_stop' => true,
		];

		$new_id = $this->cloning->clone_post( 42 );

		$this->assertFalse( $GLOBALS['pa_test_post_meta'][ $new_id ]['_pa_editorial_stop'] );
	}

	public function test_clone_post_copies_taxonomy_terms(): void {
		$source = new WP_Post( [
			'ID'           => 42,
			'post_title'   => 'Title',
			'post_content' => 'Content',
			'post_author'  => 1,
			'post_type'    => 'post',
		] );

		$GLOBALS['pa_test_posts'][42]        = $source;
		$GLOBALS['pa_test_object_terms'][42] = [
			'category' => [ 1, 2 ],
			'topic'    => [ 10, 20 ],
		];

		$new_id = $this->cloning->clone_post( 42 );

		$this->assertSame( [ 1, 2 ], $GLOBALS['pa_test_set_terms'][ $new_id ]['category'] );
		$this->assertSame( [ 10, 20 ], $GLOBALS['pa_test_set_terms'][ $new_id ]['topic'] );
	}

	// ---------------------------------------------------------------
	// add_clone_action()
	// ---------------------------------------------------------------

	public function test_add_clone_action_adds_link_for_posts(): void {
		$GLOBALS['pa_test_user_can'] = true;
		$post = new WP_Post( [ 'ID' => 10, 'post_type' => 'post' ] );

		$actions = $this->cloning->add_clone_action( [], $post );
		$this->assertArrayHasKey( 'pa_clone', $actions );
		$this->assertStringContainsString( 'Add New Lead', $actions['pa_clone'] );
	}

	public function test_add_clone_action_skipped_without_capability(): void {
		$GLOBALS['pa_test_user_can'] = false;
		$post = new WP_Post( [ 'ID' => 10, 'post_type' => 'post' ] );

		$actions = $this->cloning->add_clone_action( [], $post );
		$this->assertArrayNotHasKey( 'pa_clone', $actions );
	}

	public function test_add_clone_action_skipped_for_non_posts(): void {
		$GLOBALS['pa_test_user_can'] = true;
		$post = new WP_Post( [ 'ID' => 10, 'post_type' => 'page' ] );

		$actions = $this->cloning->add_clone_action( [], $post );
		$this->assertArrayNotHasKey( 'pa_clone', $actions );
	}

	protected function tearDown(): void {
		unset(
			$GLOBALS['pa_test_posts'],
			$GLOBALS['pa_test_post_meta'],
			$GLOBALS['pa_test_inserted_posts'],
			$GLOBALS['pa_test_set_terms'],
			$GLOBALS['pa_test_object_terms'],
			$GLOBALS['pa_test_taxonomies'],
			$GLOBALS['pa_test_user_can'],
		);
		parent::tearDown();
	}
}
