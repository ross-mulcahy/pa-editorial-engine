/**
 * Metadata Engine — Gutenberg editor integration.
 *
 * Subscribes to taxonomy state changes in the editor and evaluates
 * the taxonomy dependency map. When conditions match, auto-selects
 * the corresponding terms and shows snackbar notices.
 */

import { subscribe, select, dispatch } from '@wordpress/data';
import { evaluateRules } from './ruleEvaluator';

let previousTaxonomies = null;
const firedRuleIds = new Set();

/**
 * Initialise the metadata rule evaluation subscriber.
 */
export function initMetadataEngine() {
	const config = window.paEditorialMetadata;

	if ( ! config?.enabled || ! config?.taxonomyMap?.length ) {
		return;
	}

	const rules = config.taxonomyMap.filter( ( r ) => r.active );

	if ( ! rules.length ) {
		return;
	}

	subscribe( () => {
		const editor = select( 'core/editor' );

		if ( ! editor ) {
			return;
		}

		const currentPost = editor.getCurrentPost();
		if ( ! currentPost ) {
			return;
		}

		// Get current taxonomy selections from the editor.
		const edits = editor.getEditedPostAttribute( 'meta' ) || {};
		const currentTaxonomies = getCurrentTaxonomyState( editor );

		// Skip if nothing changed.
		const serialised = JSON.stringify( currentTaxonomies );
		if ( serialised === previousTaxonomies ) {
			return;
		}
		previousTaxonomies = serialised;

		// Evaluate rules against current state.
		const results = evaluateRules( rules, currentTaxonomies, edits );

		if ( ! results.length ) {
			return;
		}

		// Apply taxonomy updates and show notices for newly fired rules.
		results.forEach( ( result ) => {
			if ( firedRuleIds.has( result.ruleId ) ) {
				return;
			}

			firedRuleIds.add( result.ruleId );

			// Auto-select taxonomy terms.
			if ( Object.keys( result.taxonomyUpdates ).length ) {
				dispatch( 'core/editor' ).editPost( result.taxonomyUpdates );
			}

			// Show snackbar notices.
			result.notices.forEach( ( notice ) => {
				dispatch( 'core/notices' ).createInfoNotice( notice.message, {
					type: 'snackbar',
					isDismissible: true,
				} );
			} );
		} );

		// Update the audit trail meta.
		const appliedRuleIds = Array.from( firedRuleIds );
		dispatch( 'core/editor' ).editPost( {
			meta: { _pa_auto_mapped_rules: appliedRuleIds },
		} );
	} );
}

/**
 * Extract current taxonomy term selections from the editor state.
 *
 * @param {Object} editor The core/editor store selectors.
 * @return {Object} Map of taxonomy slug → array of term IDs.
 */
function getCurrentTaxonomyState( editor ) {
	const state = {};

	// Common PA Media taxonomies — extend as needed.
	const taxonomies = [
		'category',
		'post_tag',
		'topic',
		'service',
		'territory',
	];

	taxonomies.forEach( ( tax ) => {
		const terms = editor.getEditedPostAttribute( tax );
		if ( Array.isArray( terms ) ) {
			state[ tax ] = terms;
		}
	} );

	return state;
}
