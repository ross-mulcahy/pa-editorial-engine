/**
 * Syndication — Gutenberg editor integration.
 *
 * Registers the Editorial Stop and Correction Flag sidebar panels.
 */

import { registerPlugin } from '@wordpress/plugins';
import { EditorialStop } from './EditorialStop';
import { CorrectionFlag } from './CorrectionFlag';

function SyndicationPanels() {
	return (
		<>
			<EditorialStop />
			<CorrectionFlag />
		</>
	);
}

export function initSyndication() {
	if ( ! window.paEditorialSyndication?.enabled ) {
		return;
	}

	registerPlugin( 'pa-editorial-syndication', {
		render: SyndicationPanels,
	} );
}
