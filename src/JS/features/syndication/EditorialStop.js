/**
 * Editorial Stop — full content freeze.
 *
 * When active, the post is frozen: no content, title, or status changes
 * are allowed. The server blocks all REST saves (except toggling the
 * stop itself off). The client greys out the editor and shows a warning.
 */

import { useEntityProp } from '@wordpress/core-data';
import { useSelect, useDispatch } from '@wordpress/data';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { ToggleControl, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useEffect, useRef } from '@wordpress/element';

export function EditorialStop() {
	const [ meta, setMeta ] = useEntityProp( 'postType', 'post', 'meta' );
	const stopActive = meta?._pa_editorial_stop || false;
	const { createErrorNotice } = useDispatch( 'core/notices' );
	const isSavingPost = useSelect( ( select ) =>
		select( 'core/editor' ).isSavingPost()
	);
	const prevSaving = useRef( false );

	// Apply or remove the visual lockdown when stop state changes.
	useEffect( () => {
		const selectors = [ '.editor-styles-wrapper', '.editor-header' ];

		selectors.forEach( ( selector ) => {
			const el = document.querySelector( selector );
			if ( el ) {
				el.classList.toggle( 'pa-editorial-stopped', stopActive );
			}
		} );
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
