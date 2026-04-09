/**
 * Nuclear Locking — editor integration.
 *
 * Subscribes to the post lock state and, when locked by another user:
 * 1. Applies a CSS lockdown class to disable all editor interaction.
 * 2. Renders a non-dismissible modal with the lock holder's name.
 * 3. Disables native WP 7.0 real-time collaboration via Abilities API.
 * 4. Listens for heartbeat-driven editorial stop changes in real time.
 */

/* global jQuery */
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

// Heartbeat-driven lock state — updated when another user locks the post.
let heartbeatLocked = false;
let heartbeatLockerName = '';

/**
 * Set up the heartbeat client to detect editorial stop changes in real time.
 *
 * Sends the post ID on each heartbeat tick. The server checks the post
 * status and returns lock info. If the post was just locked by another
 * editor, we update the local state and the subscribe() loop picks it up.
 */
function initHeartbeatLockCheck() {
	if ( typeof jQuery === 'undefined' || ! jQuery( document ).on ) {
		return;
	}

	const editor = select( 'core/editor' );
	if ( ! editor ) {
		return;
	}

	// Send the post ID on each heartbeat tick.
	jQuery( document ).on( 'heartbeat-send', ( event, data ) => {
		const currentPost = editor.getCurrentPost();
		if ( currentPost?.id ) {
			data.pa_editorial_stop_check = currentPost.id;
		}
	} );

	// Process the heartbeat response.
	jQuery( document ).on( 'heartbeat-tick', ( event, data ) => {
		if ( ! data.pa_editorial_stop ) {
			return;
		}

		const lockInfo = data.pa_editorial_stop;

		if ( lockInfo.locked && ! lockInfo.can_unlock ) {
			heartbeatLocked = true;
			heartbeatLockerName = lockInfo.locked_by || '';
		} else {
			heartbeatLocked = false;
			heartbeatLockerName = '';
		}
	} );
}

/**
 * Initialise the nuclear lock subscriber.
 */
export function initNuclearLocking() {
	if ( ! window.paEditorialLocking?.enabled ) {
		return;
	}

	// Also check for editorial-stop locked status.
	const syndicationConfig = window.paEditorialSyndication || {};
	const lockedStatus = syndicationConfig.lockedStatus || 'locked';
	const canToggleStop = syndicationConfig.canToggleStop;
	const stopByName = syndicationConfig.stopByName || '';

	// Start listening for heartbeat-driven lock changes.
	initHeartbeatLockCheck();

	subscribe( () => {
		const editor = select( 'core/editor' );

		if ( ! editor ) {
			return;
		}

		// Check native WP post lock (another user editing).
		const isWPLocked = editor.isPostLocked();
		const lockDetails = editor.getPostLockUser?.() || {};
		const wpLockerName = lockDetails?.name || lockDetails?.nickname || '';

		// Check editorial stop — from initial page load or heartbeat.
		const postStatus = editor.getEditedPostAttribute( 'status' );
		const isEditorialLocked =
			( postStatus === lockedStatus && ! canToggleStop ) ||
			heartbeatLocked;

		const shouldLock = isWPLocked || isEditorialLocked;
		const lockerName = isEditorialLocked
			? heartbeatLockerName || stopByName
			: wpLockerName;

		if ( shouldLock && ! lockApplied ) {
			lockApplied = true;
			toggleLockdownCSS( true );
			toggleModal( true, lockerName );
			disableCollaboration();
			document.addEventListener( 'keydown', blockKeyboardSave, true );
		} else if ( ! shouldLock && lockApplied ) {
			lockApplied = false;
			toggleLockdownCSS( false );
			toggleModal( false, '' );
			document.removeEventListener( 'keydown', blockKeyboardSave, true );
		}
	} );
}
