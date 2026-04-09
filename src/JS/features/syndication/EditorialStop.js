/**
 * Editorial Stop — full content freeze.
 *
 * When active, the post is frozen: no content, title, or status changes
 * are allowed. Only users with edit_others_posts (editors+) can toggle
 * the stop. The name of the user who activated it is displayed.
 */

import { useEntityProp } from '@wordpress/core-data';
import { useSelect, useDispatch } from '@wordpress/data';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { ToggleControl, Notice } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
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

	// Title is in the parent document.
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

	// Grey out header and sidebar.
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

	const config = window.paEditorialSyndication || {};
	const canToggle = config.canToggleStop;
	const stopByName = config.stopByName;

	// Lock/unlock the editor when stop state changes.
	useEffect( () => {
		if ( stopActive ) {
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
		setMeta( {
			...meta,
			_pa_editorial_stop: value,
			// Store who activated it; clear when deactivated.
			_pa_editorial_stop_by: value ? 0 : 0, // Server will set via current user.
		} );
	};

	// Build the frozen notice message.
	let frozenMessage = __(
		'This post is frozen. Content changes are blocked until the stop is removed.',
		'pa-editorial-engine'
	);
	if ( stopByName ) {
		frozenMessage = sprintf(
			/* translators: %s: display name of the editor who activated the stop */
			__(
				'Signed off by %s. Content changes are blocked until the stop is removed.',
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
			{ stopActive && (
				<Notice status="warning" isDismissible={ false }>
					{ frozenMessage }
				</Notice>
			) }
			{ canToggle ? (
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __(
						'Activate Editorial Stop',
						'pa-editorial-engine'
					) }
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
			) : (
				<p>
					{ stopActive
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
