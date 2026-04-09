/**
 * Correction Flag — sidebar panel for flagging a post as a correction.
 *
 * When the flag is active and the post is published, an async Action
 * Scheduler task sends the correction note to the PA Wire API.
 */

import { useEntityProp } from '@wordpress/core-data';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { ToggleControl, TextareaControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export function CorrectionFlag() {
	const [ meta, setMeta ] = useEntityProp( 'postType', 'post', 'meta' );
	const isCorrection = meta?._pa_is_correction || false;
	const correctionNote = meta?._pa_correction_note || '';

	const updateMeta = ( key, value ) => {
		setMeta( { ...meta, [ key ]: value } );
	};

	return (
		<PluginDocumentSettingPanel
			name="pa-correction-flag"
			title={ __( 'Correction Flag', 'pa-editorial-engine' ) }
			className="pa-correction-flag-panel"
		>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Flag as Correction', 'pa-editorial-engine' ) }
				help={
					isCorrection
						? __(
								'A correction notice will be sent on publish.',
								'pa-editorial-engine'
						  )
						: __(
								'Enable to flag this post as a correction.',
								'pa-editorial-engine'
						  )
				}
				checked={ isCorrection }
				onChange={ ( val ) => updateMeta( '_pa_is_correction', val ) }
			/>

			{ isCorrection && (
				<TextareaControl
					label={ __( 'Correction Note', 'pa-editorial-engine' ) }
					help={ __(
						'Describe what was corrected. This will be sent with the correction notice.',
						'pa-editorial-engine'
					) }
					value={ correctionNote }
					onChange={ ( val ) =>
						updateMeta( '_pa_correction_note', val )
					}
					rows={ 4 }
				/>
			) }
		</PluginDocumentSettingPanel>
	);
}
