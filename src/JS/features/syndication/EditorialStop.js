/**
 * Editorial Stop — full content freeze using custom "locked" post status.
 *
 * When activated, changes the post status to "locked" and saves immediately.
 * When deactivated, restores the previous status. Only editors can toggle.
 * Shows who locked the post.
 */

import { useSelect, useDispatch } from '@wordpress/data';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { Button, Notice } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useEffect } from '@wordpress/element';

const OVERLAY_ID = 'pa-editorial-stop-overlay';

/**
 * Get the editor iframe document.
 *
 * @return {Document|null} The iframe document, or null.
 */
function getEditorIframeDoc() {
	const iframe = document.querySelector( 'iframe[name="editor-canvas"]' );
	return iframe?.contentDocument || null;
}

/**
 * Apply or remove the content freeze overlay.
 *
 * @param {boolean} frozen Whether the editor should be frozen.
 */
function setEditorFrozen( frozen ) {
	const iframeDoc = getEditorIframeDoc();

	if ( iframeDoc ) {
		let overlay = iframeDoc.getElementById( OVERLAY_ID );
		if ( frozen && ! overlay ) {
			overlay = iframeDoc.createElement( 'div' );
			overlay.id = OVERLAY_ID;
			overlay.style.cssText = [
				'position: fixed',
				'inset: 0',
				'z-index: 999999',
				'background: transparent',
				'cursor: not-allowed',
			].join( '; ' );
			iframeDoc.body.appendChild( overlay );
			iframeDoc.body.style.userSelect = 'none';
		} else if ( ! frozen && overlay ) {
			overlay.remove();
			iframeDoc.body.style.userSelect = '';
		}
	}

	const titleEl = document.querySelector(
		'.editor-post-title__input, .wp-block-post-title'
	);
	if ( titleEl ) {
		titleEl.style.pointerEvents = frozen ? 'none' : '';
		titleEl.style.opacity = frozen ? '0.5' : '';
		if ( frozen ) {
			titleEl.setAttribute( 'contenteditable', 'false' );
		} else {
			titleEl.setAttribute( 'contenteditable', 'true' );
		}
	}

	[ '.editor-header', '.interface-interface-skeleton__sidebar' ].forEach(
		( selector ) => {
			const el = document.querySelector( selector );
			if ( el ) {
				el.classList.toggle( 'pa-editorial-stopped', frozen );
			}
		}
	);
}

/**
 * Block Cmd/Ctrl+S when frozen.
 *
 * @param {KeyboardEvent} event Keyboard event.
 */
function blockSave( event ) {
	if ( ( event.metaKey || event.ctrlKey ) && event.key === 's' ) {
		event.preventDefault();
		event.stopImmediatePropagation();
	}
}

export function EditorialStop() {
	const config = window.paEditorialSyndication || {};
	const canToggle = config.canToggleStop;
	const stopByName = config.stopByName;
	const preLockStatus = config.preLockStatus || 'draft';
	const lockedStatus = config.lockedStatus || 'locked';

	const { editPost, savePost, lockPostSaving, unlockPostSaving } =
		useDispatch( 'core/editor' );

	const postStatus = useSelect( ( selectFn ) =>
		selectFn( 'core/editor' ).getEditedPostAttribute( 'status' )
	);

	const isLocked = postStatus === lockedStatus;

	// Apply/remove visual freeze based on lock status.
	useEffect( () => {
		if ( isLocked ) {
			lockPostSaving( 'pa-editorial-stop' );
			document.addEventListener( 'keydown', blockSave, true );

			const iframeDoc = getEditorIframeDoc();
			if ( iframeDoc ) {
				iframeDoc.addEventListener( 'keydown', blockSave, true );
			}

			const timer = setTimeout( () => setEditorFrozen( true ), 300 );
			return () => {
				clearTimeout( timer );
				document.removeEventListener( 'keydown', blockSave, true );
				if ( iframeDoc ) {
					iframeDoc.removeEventListener( 'keydown', blockSave, true );
				}
			};
		}

		unlockPostSaving( 'pa-editorial-stop' );
		setEditorFrozen( false );
		document.removeEventListener( 'keydown', blockSave, true );
	}, [ isLocked, lockPostSaving, unlockPostSaving ] );

	const handleLock = () => {
		editPost( { status: lockedStatus } );
		savePost();
	};

	const handleUnlock = () => {
		editPost( { status: preLockStatus || 'draft' } );
		savePost();
	};

	// Build the notice message.
	let frozenMessage = __(
		'This post is frozen. Content changes are blocked.',
		'pa-editorial-engine'
	);
	if ( stopByName ) {
		frozenMessage = sprintf(
			/* translators: %s: display name of the editor who locked the post */
			__(
				'Signed off by %s. Content changes are blocked.',
				'pa-editorial-engine'
			),
			stopByName
		);
	}

	return (
		<PluginDocumentSettingPanel
			name="pa-editorial-stop"
			title={ __( 'Editorial Stop', 'pa-editorial-engine' ) }
			className="pa-editorial-stop-panel"
		>
			{ isLocked && (
				<Notice status="warning" isDismissible={ false }>
					{ frozenMessage }
				</Notice>
			) }

			{ canToggle && isLocked && (
				<Button variant="secondary" onClick={ handleUnlock }>
					{ __( 'Remove Editorial Stop', 'pa-editorial-engine' ) }
				</Button>
			) }
			{ canToggle && ! isLocked && (
				<Button variant="primary" onClick={ handleLock }>
					{ __( 'Activate Editorial Stop', 'pa-editorial-engine' ) }
				</Button>
			) }
			{ ! canToggle && (
				<p>
					{ isLocked
						? __(
								'Only editors can remove the Editorial Stop.',
								'pa-editorial-engine'
						  )
						: __(
								'Only editors can activate the Editorial Stop.',
								'pa-editorial-engine'
						  ) }
				</p>
			) }
		</PluginDocumentSettingPanel>
	);
}
