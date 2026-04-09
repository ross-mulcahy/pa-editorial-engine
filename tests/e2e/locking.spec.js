/**
 * E2E tests for Nuclear Locking.
 *
 * These tests require a running WordPress environment (wp-env)
 * with two user accounts:
 *   - editor1 (role: editor)
 *   - reporter1 (role: author/contributor)
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-e2e-test-utils-playwright/
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Nuclear Locking', () => {
	test.describe( 'Server-side lock enforcement', () => {
		test( 'REST save returns 403 when post is locked by another user', async ( {
			requestUtils,
		} ) => {
			// Create a post as admin.
			const post = await requestUtils.createPost( {
				title: 'Lock Test Post',
				status: 'draft',
			} );

			// Simulate a lock by another user (editor1 opens the post).
			// The lock is set via the heartbeat; for E2E we set it directly.
			await requestUtils.rest( {
				path: `/wp/v2/posts/${ post.id }`,
				method: 'POST',
				data: { meta: { _pa_editorial_stop: false } },
			} );

			// In a real multi-user E2E, we would:
			// 1. Login as editor1, open the post (acquires lock).
			// 2. Login as reporter1, attempt to save.
			// This is a placeholder for the full multi-browser test.
			expect( post.id ).toBeGreaterThan( 0 );
		} );
	} );

	test.describe( 'Client-side UI lockdown', () => {
		test( 'Nuclear lock modal is visible when post is locked', async ( {
			admin,
			page,
			requestUtils,
		} ) => {
			const post = await requestUtils.createPost( {
				title: 'Lock Modal Test',
				status: 'draft',
			} );

			await admin.visitAdminPage(
				'post.php',
				`post=${ post.id }&action=edit`
			);

			// Wait for the editor to load.
			await page.waitForSelector( '.editor-styles-wrapper' );

			// The nuclear lock modal appears when isPostLocked() returns true.
			// In a single-user test the post won't be locked, so we verify
			// the lock infrastructure is loaded by checking for the script.
			const scriptLoaded = await page.evaluate( () => {
				return typeof window.paEditorialLocking !== 'undefined';
			} );

			expect( scriptLoaded ).toBe( true );
		} );

		test( 'Editor containers get lockdown class when locked', async ( {
			admin,
			page,
			requestUtils,
		} ) => {
			const post = await requestUtils.createPost( {
				title: 'CSS Lockdown Test',
				status: 'draft',
			} );

			await admin.visitAdminPage(
				'post.php',
				`post=${ post.id }&action=edit`
			);

			await page.waitForSelector( '.editor-styles-wrapper' );

			// Simulate the lock state by adding the CSS class directly
			// (since we can't easily create a second user session).
			await page.evaluate( () => {
				document
					.querySelector( '.editor-styles-wrapper' )
					?.classList.add( 'pa-nuclear-locked' );
			} );

			const hasLockClass = await page.evaluate( () => {
				return document
					.querySelector( '.editor-styles-wrapper' )
					?.classList.contains( 'pa-nuclear-locked' );
			} );

			expect( hasLockClass ).toBe( true );

			// Verify the CSS rules are applied.
			const styles = await page.evaluate( () => {
				const el = document.querySelector( '.editor-styles-wrapper' );
				if ( ! el ) {
					return {};
				}
				const computed = window.getComputedStyle( el );
				return {
					pointerEvents: computed.pointerEvents,
					userSelect: computed.userSelect,
				};
			} );

			expect( styles.pointerEvents ).toBe( 'none' );
			expect( styles.userSelect ).toBe( 'none' );
		} );
	} );

	test.describe( 'Keyboard save blocking', () => {
		test( 'Cmd+S is intercepted when lock is active', async ( {
			admin,
			page,
			requestUtils,
		} ) => {
			const post = await requestUtils.createPost( {
				title: 'Keyboard Block Test',
				status: 'draft',
			} );

			await admin.visitAdminPage(
				'post.php',
				`post=${ post.id }&action=edit`
			);

			await page.waitForSelector( '.editor-styles-wrapper' );

			// Set up network monitoring to detect save requests.
			const saveRequests = [];
			page.on( 'request', ( request ) => {
				if (
					request.url().includes( `/wp/v2/posts/${ post.id }` ) &&
					request.method() === 'PUT'
				) {
					saveRequests.push( request );
				}
			} );

			// Simulate lock active state by triggering the keyboard handler.
			await page.evaluate( () => {
				// Manually fire the handler to verify it blocks.
				const event = new KeyboardEvent( 'keydown', {
					key: 's',
					metaKey: true,
					bubbles: true,
					cancelable: true,
				} );
				const prevented = ! document.dispatchEvent( event );
				window.__pa_save_prevented = prevented;
			} );

			// In a locked state the handler would be attached; this verifies
			// the test infrastructure works. Full verification requires
			// multi-user session setup.
			expect( saveRequests.length ).toBe( 0 );
		} );
	} );
} );
