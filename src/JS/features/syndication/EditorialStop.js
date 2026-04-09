/**
 * Editorial Stop — full content freeze.
 *
 * When active, the post is frozen: no content, title, or status changes
 * are allowed. The editor is made fully read-only by disabling
 * contenteditable, locking the post, and intercepting keyboard saves.
 */

import { useEntityProp } from '@wordpress/core-data';
import { useSelect, useDispatch } from '@wordpress/data';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { ToggleControl, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useEffect, useRef } from '@wordpress/element';

/**
 * Disable or re-enable all contenteditable elements in the editor.
 *
 * @param {boolean} frozen Whether the editor should be frozen.
 */
function setEditorFrozen( frozen ) {
	// Title field.
	const titleEl = document.querySelector(
		'.editor-post-title__input, .wp-block-post-title'
	);
	if ( titleEl ) {
		titleEl.contentEditable = frozen ? 'false' : 'true';
		titleEl.style.pointerEvents = frozen ? 'none' : '';
	}

	// Block editor content area — disable all editable regions.
	const editables = document.querySelectorAll(
		'.editor-styles-wrapper [contenteditable="true"]'
	);
	editables.forEach( ( el ) => {
		el.contentEditable = frozen ? 'false' : 'true';
	} );

	// Visual lockdown classes.
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

			// Small delay to let the editor render before freezing.
			const timer = setTimeout( () => setEditorFrozen( true ), 100 );
			return () => clearTimeout( timer );
		}

		unlockPostSaving( 'pa-editorial-stop' );
		setEditorFrozen( false );
		document.removeEventListener( 'keydown', blockSave, true );
	}, [ stopActive, lockPostSaving, unlockPostSaving ] );

	// Re-freeze on any DOM changes (new blocks added via undo, etc.).
	useEffect( () => {
		if ( ! stopActive ) {
			return;
		}

		// eslint-disable-next-line no-undef -- MutationObserver is a browser global.
		const observer = new MutationObserver( () => {
			setEditorFrozen( true );
		} );

		const wrapper = document.querySelector( '.editor-styles-wrapper' );
		if ( wrapper ) {
			observer.observe( wrapper, {
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
