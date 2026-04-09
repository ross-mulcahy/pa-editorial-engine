/**
 * Editorial Stop — full content freeze.
 *
 * When active, the post is frozen: no content, title, or status changes
 * are allowed. Uses iframe body overlay + pointer-events to block all
 * interaction, lockPostSaving to disable the save button, and server-side
 * REST blocking as the final safety net.
 */

import { useEntityProp } from '@wordpress/core-data';
import { useSelect, useDispatch } from '@wordpress/data';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { ToggleControl, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useEffect, useRef } from '@wordpress/element';

const OVERLAY_ID = 'pa-editorial-stop-overlay';

/**
 * Get the editor iframe document (WP 7.0+ renders blocks in an iframe).
 *
 * @return {Document|null} The iframe document, or null if not found.
 */
function getEditorIframeDoc() {
	const iframe = document.querySelector( 'iframe[name="editor-canvas"]' );
	return iframe?.contentDocument || null;
}

/**
 * Apply or remove the content freeze.
 *
 * Instead of toggling contenteditable on individual elements (which
 * triggers infinite MutationObserver loops), we place a transparent
 * overlay div over the entire iframe body that blocks all interaction.
 *
 * @param {boolean} frozen Whether the editor should be frozen.
 */
function setEditorFrozen( frozen ) {
	const iframeDoc = getEditorIframeDoc();

	if ( iframeDoc ) {
		let overlay = iframeDoc.getElementById( OVERLAY_ID );

		if ( frozen && ! overlay ) {
			// Create a full-screen overlay inside the iframe that blocks clicks/typing.
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

	// Title is in the parent document — disable it directly.
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

	// Grey out the header and sidebar in the parent document.
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

			// Also block keyboard in the iframe.
			const iframeDoc = getEditorIframeDoc();
			if ( iframeDoc ) {
				iframeDoc.addEventListener( 'keydown', blockSave, true );
			}

			// Delay slightly to let the iframe render.
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
	}, [ stopActive, lockPostSaving, unlockPostSaving ] );

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
