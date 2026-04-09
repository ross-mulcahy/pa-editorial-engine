/**
 * Nuclear Locking — editor integration.
 *
 * Subscribes to the post lock state and, when locked by another user:
 * 1. Applies a CSS lockdown class to disable all editor interaction.
 * 2. Renders a non-dismissible modal with the lock holder's name.
 * 3. Disables native WP 7.0 real-time collaboration via Abilities API.
 */

import { subscribe, select } from '@wordpress/data';
import { render, createElement } from '@wordpress/element';
import { NuclearLockModal } from './NuclearLockModal';

let lockApplied = false;

/**
 * Apply or remove the nuclear lock CSS class on editor containers.
 *
 * @param {boolean} locked Whether the lock should be active.
 */
function toggleLockdownCSS( locked ) {
	const selectors = [
		'.editor-styles-wrapper',
		'.interface-interface-skeleton__sidebar',
		'.editor-header',
	];

	selectors.forEach( ( selector ) => {
		const el = document.querySelector( selector );
		if ( el ) {
			el.classList.toggle( 'pa-nuclear-locked', locked );
		}
	} );
}

/**
 * Disable WP 7.0 collaborative editing via the Abilities API.
 */
function disableCollaboration() {
	try {
		// @wordpress/abilities may not be available in all environments.
		const abilities = window.wp?.abilities;
		if ( abilities?.unregisterAbility ) {
			abilities.unregisterAbility( 'core/editor', 'collaborative-edit' );
		}
	} catch ( e ) {
		// Abilities API not available — safe to ignore.
	}
}

/**
 * Mount or unmount the nuclear lock modal.
 *
 * @param {boolean} locked     Whether the lock is active.
 * @param {string}  lockerName Display name of the lock holder.
 */
function toggleModal( locked, lockerName ) {
	const MODAL_CONTAINER_ID = 'pa-nuclear-lock-modal';
	let container = document.getElementById( MODAL_CONTAINER_ID );

	if ( locked ) {
		if ( ! container ) {
			container = document.createElement( 'div' );
			container.id = MODAL_CONTAINER_ID;
			document.body.appendChild( container );
		}
		render( createElement( NuclearLockModal, { lockerName } ), container );
	} else if ( container ) {
		render( null, container );
	}
}

/**
 * Block Cmd/Ctrl+S from firing a save when locked.
 *
 * @param {KeyboardEvent} event Keyboard event.
 */
function blockKeyboardSave( event ) {
	if ( ( event.metaKey || event.ctrlKey ) && event.key === 's' ) {
		event.preventDefault();
		event.stopImmediatePropagation();
	}
}

/**
 * Initialise the nuclear lock subscriber.
 */
export function initNuclearLocking() {
	if ( ! window.paEditorialLocking?.enabled ) {
		return;
	}

	subscribe( () => {
		const editor = select( 'core/editor' );

		if ( ! editor ) {
			return;
		}

		const isLocked = editor.isPostLocked();
		const lockDetails = editor.getPostLockUser?.() || {};
		const lockerName = lockDetails?.name || lockDetails?.nickname || '';

		if ( isLocked && ! lockApplied ) {
			lockApplied = true;
			toggleLockdownCSS( true );
			toggleModal( true, lockerName );
			disableCollaboration();
			document.addEventListener( 'keydown', blockKeyboardSave, true );
		} else if ( ! isLocked && lockApplied ) {
			lockApplied = false;
			toggleLockdownCSS( false );
			toggleModal( false, '' );
			document.removeEventListener( 'keydown', blockKeyboardSave, true );
		}
	} );
}
