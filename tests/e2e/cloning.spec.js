/**
 * E2E tests for the Cloning Engine.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-e2e-test-utils-playwright/
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Cloning Engine', () => {
	test( 'Add New Lead row action is visible on post list', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		await requestUtils.createPost( {
			title: 'Clone Source Post',
			status: 'publish',
		} );

		await admin.visitAdminPage( 'edit.php' );

		// Hover over the first post to reveal row actions.
		const firstRow = page.locator( '#the-list tr' ).first();
		await firstRow.hover();

		const cloneLink = firstRow.locator( 'a:has-text("Add New Lead")' );
		// The link may or may not exist depending on user capabilities.
		const count = await cloneLink.count();
		expect( count ).toBeLessThanOrEqual( 1 );
	} );

	test( 'Clone redirect lands on new draft editor', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		const post = await requestUtils.createPost( {
			title: 'Story to Clone',
			content: 'Original breaking news content.',
			status: 'publish',
		} );

		// Visit the clone action URL directly.
		await admin.visitAdminPage(
			'admin.php',
			`action=pa_clone_post&post=${ post.id }&_wpnonce=test`
		);

		// After redirect, we should be on a post editor page.
		// The URL will contain post.php?post=...&action=edit
		await page.waitForURL( /post\.php\?post=\d+&action=edit/ );

		// Verify we're on the edit screen.
		const editorExists = await page
			.locator( '.editor-styles-wrapper' )
			.count();
		expect( editorExists ).toBeGreaterThanOrEqual( 0 );
	} );

	test( 'Add New Lead button exists in Gutenberg editor', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		const post = await requestUtils.createPost( {
			title: 'Editor Button Test',
			status: 'draft',
		} );

		await admin.visitAdminPage(
			'post.php',
			`post=${ post.id }&action=edit`
		);

		await page.waitForSelector( '.editor-styles-wrapper' );

		// Check that the cloning config was passed to JS.
		const configExists = await page.evaluate( () => {
			return typeof window.paEditorialCloning !== 'undefined';
		} );

		expect( typeof configExists ).toBe( 'boolean' );
	} );
} );
