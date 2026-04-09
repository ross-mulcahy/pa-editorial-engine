/**
 * Editorial Stop — full content freeze.
 *
 * When active, the post is frozen: no content, title, or status changes
 * are allowed. The editor is made fully read-only by disabling
 * contenteditable (including inside the editor iframe), locking the
 * post save, and intercepting keyboard saves.
 */

import { useEntityProp } from '@wordpress/core-data';
import { useSelect, useDispatch } from '@wordpress/data';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { ToggleControl, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useEffect, useRef } from '@wordpress/element';

/**
 * Get the editor iframe document (WP 7.0+ renders blocks in an iframe).
 * Falls back to the main document if no iframe is found.
 *
 * @return {Document} The document containing the editor content.
 */
function getEditorDocument() {
	const iframe = document.querySelector( 'iframe[name="editor-canvas"]' );
	if ( iframe?.contentDocument ) {
		return iframe.contentDocument;
	}
	return document;
}

/**
 * Disable or re-enable all contenteditable elements in the editor.
 *
 * @param {boolean} frozen Whether the editor should be frozen.
 */
function setEditorFrozen( frozen ) {
	const editorDoc = getEditorDocument();

	// Disable all contenteditable elements inside the editor (iframe or main doc).
	const editables = editorDoc.querySelectorAll( '[contenteditable]' );
	editables.forEach( ( el ) => {
		if ( frozen ) {
			el.setAttribute( 'contenteditable', 'false' );
			el.dataset.paWasFrozen = 'true';
		} else if ( el.dataset.paWasFrozen ) {
			el.setAttribute( 'contenteditable', 'true' );
			delete el.dataset.paWasFrozen;
		}
	} );

	// Also cover the title in the parent document (may be outside iframe).
	const titleEl = document.querySelector(
		'.editor-post-title__input, .wp-block-post-title'
	);
	if ( titleEl ) {
		if ( frozen ) {
			titleEl.setAttribute( 'contenteditable', 'false' );
			titleEl.style.pointerEvents = 'none';
		} else {
			titleEl.setAttribute( 'contenteditable', 'true' );
			titleEl.style.pointerEvents = '';
		}
	}

	// Grey out the editor visually.
	const lockTargets = [
		'.editor-styles-wrapper',
		'.editor-header',
		'.interface-interface-skeleton__sidebar',
	];
	lockTargets.forEach( ( selector ) => {
		const el = document.querySelector( selector );
		if ( el ) {
			el.classList.toggle( 'pa-editorial-stopped', frozen );
		}
	} );

	// Also apply visual styles inside the iframe body.
	if ( editorDoc !== document && editorDoc.body ) {
		editorDoc.body.style.pointerEvents = frozen ? 'none' : '';
		editorDoc.body.style.userSelect = frozen ? 'none' : '';
		editorDoc.body.style.opacity = frozen ? '0.5' : '';
	}
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
	const [ meta, setMeta ] = useEntityProp( 'postType', 'post', 'meta' );
	const stopActive = meta?._pa_editorial_stop || false;
	const { createErrorNotice } = useDispatch( 'core/notices' );
	const { lockPostSaving, unlockPostSaving } = useDispatch( 'core/editor' );
	const isSavingPost = useSelect( ( selectFn ) =>
		selectFn( 'core/editor' ).isSavingPost()
	);
	const prevSaving = useRef( false );

	// Lock/unlock the editor when stop state changes.
	useEffect( () => {
		if ( stopActive ) {
			lockPostSaving( 'pa-editorial-stop' );
			document.addEventListener( 'keydown', blockSave, true );

			// Block keyboard input inside the iframe too.
			const editorDoc = getEditorDocument();
			if ( editorDoc !== document ) {
				editorDoc.addEventListener( 'keydown', blockSave, true );
			}

			// Delay to let the editor render before freezing.
			const timer = setTimeout( () => setEditorFrozen( true ), 200 );
			return () => {
				clearTimeout( timer );
				document.removeEventListener( 'keydown', blockSave, true );
				if ( editorDoc !== document ) {
					editorDoc.removeEventListener( 'keydown', blockSave, true );
				}
			};
		}

		unlockPostSaving( 'pa-editorial-stop' );
		setEditorFrozen( false );
		document.removeEventListener( 'keydown', blockSave, true );
	}, [ stopActive, lockPostSaving, unlockPostSaving ] );

	// Re-freeze when DOM changes (blocks re-rendered, undo, etc.).
	useEffect( () => {
		if ( ! stopActive ) {
			return;
		}

		const editorDoc = getEditorDocument();
		// eslint-disable-next-line no-undef -- MutationObserver is a browser global.
		const observer = new MutationObserver( () => {
			setEditorFrozen( true );
		} );

		const target =
			editorDoc.querySelector( '.editor-styles-wrapper' ) ||
			editorDoc.body;
		if ( target ) {
			observer.observe( target, {
				childList: true,
				subtree: true,
				attributes: true,
				attributeFilter: [ 'contenteditable' ],
			} );
		}

		return () => observer.disconnect();
	}, [ stopActive ] );

	// Show error notice when a save fails due to the stop.
	useEffect( () => {
		if ( prevSaving.current && ! isSavingPost && stopActive ) {
			createErrorNotice(
				__(
					'Editorial Stop is active. This post has been signed off and cannot be modified.',
					'pa-editorial-engine'
				),
				{ type: 'snackbar', isDismissible: true }
			);
		}
		prevSaving.current = isSavingPost;
	}, [ isSavingPost, stopActive, createErrorNotice ] );

	const handleToggle = ( value ) => {
		setMeta( { ...meta, _pa_editorial_stop: value } );
	};

	return (
		<PluginDocumentSettingPanel
			name="pa-editorial-stop"
			title={ __( 'Editorial Stop', 'pa-editorial-engine' ) }
			className="pa-editorial-stop-panel"
		>
			{ stopActive && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'This post is frozen. Content changes are blocked until the stop is removed.',
						'pa-editorial-engine'
					) }
				</Notice>
			) }
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Activate Editorial Stop', 'pa-editorial-engine' ) }
				help={
					stopActive
						? __(
								'Post is signed off. All changes are blocked.',
								'pa-editorial-engine'
						  )
						: __(
								'Enable to freeze this post after sign-off.',
								'pa-editorial-engine'
						  )
				}
				checked={ stopActive }
				onChange={ handleToggle }
			/>
		</PluginDocumentSettingPanel>
	);
}
