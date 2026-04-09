/**
 * Editorial Stop — sidebar panel toggle.
 *
 * When active, prevents the post from being published. The server-side
 * `wp_insert_post_data` filter forces the status back to `pending`.
 * This component provides the UI toggle and error feedback.
 */

import { useEntityProp } from '@wordpress/core-data';
import { useSelect, useDispatch } from '@wordpress/data';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useEffect, useRef } from '@wordpress/element';

export function EditorialStop() {
	const [ meta, setMeta ] = useEntityProp( 'postType', 'post', 'meta' );
	const stopActive = meta?._pa_editorial_stop || false;
	const { createErrorNotice } = useDispatch( 'core/notices' );
	const isSavingPost = useSelect( ( select ) =>
		select( 'core/editor' ).isSavingPost()
	);
	const postStatus = useSelect( ( select ) =>
		select( 'core/editor' ).getEditedPostAttribute( 'status' )
	);
	const prevSaving = useRef( false );

	// Show error notice when user tries to publish with stop active.
	useEffect( () => {
		if (
			prevSaving.current &&
			! isSavingPost &&
			stopActive &&
			postStatus === 'pending'
		) {
			createErrorNotice(
				__(
					'Editorial Stop is active. This post cannot be published until the stop is removed.',
					'pa-editorial-engine'
				),
				{ type: 'snackbar', isDismissible: true }
			);
		}
		prevSaving.current = isSavingPost;
	}, [ isSavingPost, stopActive, postStatus, createErrorNotice ] );

	const handleToggle = ( value ) => {
		setMeta( { ...meta, _pa_editorial_stop: value } );
	};

	return (
		<PluginDocumentSettingPanel
			name="pa-editorial-stop"
			title={ __( 'Editorial Stop', 'pa-editorial-engine' ) }
			className="pa-editorial-stop-panel"
		>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Activate Editorial Stop', 'pa-editorial-engine' ) }
				help={
					stopActive
						? __(
								'This post cannot be published while the stop is active.',
								'pa-editorial-engine'
						  )
						: __(
								'Enable to prevent this post from being published.',
								'pa-editorial-engine'
						  )
				}
				checked={ stopActive }
				onChange={ handleToggle }
			/>
		</PluginDocumentSettingPanel>
	);
}
