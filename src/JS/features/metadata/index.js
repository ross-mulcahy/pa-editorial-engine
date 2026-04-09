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
 * Map between taxonomy slugs (used in rules) and REST base names
 * (used by the core/editor store's getEditedPostAttribute).
 *
 * WordPress built-in: category → categories, post_tag → tags.
 * Custom taxonomies typically use their slug as the REST base.
 */
const SLUG_TO_REST = {
	category: 'categories',
	post_tag: 'tags',
};

const REST_TO_SLUG = {};
Object.entries( SLUG_TO_REST ).forEach( ( [ slug, rest ] ) => {
	REST_TO_SLUG[ rest ] = slug;
} );

/**
 * Convert a taxonomy slug to its REST base name.
 *
 * @param {string} slug Taxonomy slug (e.g. 'category').
 * @return {string} REST base name (e.g. 'categories').
 */
function slugToRest( slug ) {
	return SLUG_TO_REST[ slug ] || slug;
}

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
		// Rules use taxonomy slugs, but currentTaxonomies uses slugs too
		// (we convert back from REST names when reading).
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

			// Convert taxonomy slug keys to REST base names for editPost().
			if ( Object.keys( result.taxonomyUpdates ).length ) {
				const restUpdates = {};
				Object.entries( result.taxonomyUpdates ).forEach(
					( [ slug, termIds ] ) => {
						restUpdates[ slugToRest( slug ) ] = termIds;
					}
				);
				dispatch( 'core/editor' ).editPost( restUpdates );
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
 * Reads using REST base names (what the editor store uses) but returns
 * the map keyed by taxonomy slugs (what the rules use).
 *
 * @param {Object} editor The core/editor store selectors.
 * @return {Object} Map of taxonomy slug → array of term IDs.
 */
function getCurrentTaxonomyState( editor ) {
	const state = {};

	// REST base names used by getEditedPostAttribute().
	const restNames = [ 'categories', 'tags', 'topic', 'service', 'territory' ];

	restNames.forEach( ( restName ) => {
		const terms = editor.getEditedPostAttribute( restName );
		if ( Array.isArray( terms ) && terms.length ) {
			// Convert REST name back to taxonomy slug for rule matching.
			const slug = REST_TO_SLUG[ restName ] || restName;
			state[ slug ] = terms;
		}
	} );

	return state;
}
