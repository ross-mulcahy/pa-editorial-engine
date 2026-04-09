/**
 * Rule Evaluator — evaluates taxonomy dependency map rules against
 * current editor state.
 *
 * Pure functions with no side effects — suitable for unit testing.
 */

/**
 * Evaluate all active rules against the current taxonomy and meta state.
 *
 * @param {Array}  rules             Active rules from the taxonomy map.
 * @param {Object} currentTaxonomies Map of taxonomy slug → array of term IDs.
 * @param {Object} currentMeta       Current post meta values.
 * @return {Array} Array of { ruleId, taxonomyUpdates, notices } for matched rules.
 */
export function evaluateRules( rules, currentTaxonomies, currentMeta ) {
	const results = [];

	// Sort by priority (lower number = higher priority).
	const sorted = [ ...rules ].sort(
		( a, b ) => ( a.priority || 10 ) - ( b.priority || 10 )
	);

	for ( const rule of sorted ) {
		if ( ! rule.active ) {
			continue;
		}

		const matched = evaluateConditions(
			rule.conditions,
			currentTaxonomies,
			currentMeta
		);

		if ( matched ) {
			results.push( {
				ruleId: rule.rule_id,
				taxonomyUpdates: buildTaxonomyUpdates(
					rule.actions,
					currentTaxonomies
				),
				notices: rule.actions?.ui_notices || [],
			} );
		}
	}

	return results;
}

/**
 * Evaluate a conditions block (AND/OR with nested rules).
 *
 * @param {Object} conditions        { operator: 'AND'|'OR', rules: [] }
 * @param {Object} currentTaxonomies Current taxonomy state.
 * @param {Object} currentMeta       Current post meta.
 * @return {boolean} True if conditions are satisfied.
 */
export function evaluateConditions(
	conditions,
	currentTaxonomies,
	currentMeta
) {
	const { operator = 'AND', rules = [] } = conditions;

	if ( ! rules.length ) {
		return false;
	}

	const results = rules.map( ( rule ) =>
		evaluateSingleCondition( rule, currentTaxonomies, currentMeta )
	);

	if ( operator === 'AND' ) {
		return results.every( Boolean );
	}

	// OR
	return results.some( Boolean );
}

/**
 * Evaluate a single condition rule.
 *
 * @param {Object} condition         A single condition { type, slug, term_id, key, value }.
 * @param {Object} currentTaxonomies Current taxonomy state.
 * @param {Object} currentMeta       Current post meta.
 * @return {boolean} True if the condition is met.
 */
export function evaluateSingleCondition(
	condition,
	currentTaxonomies,
	currentMeta
) {
	if ( condition.type === 'taxonomy' ) {
		const terms = currentTaxonomies[ condition.slug ] || [];
		return terms.includes( condition.term_id );
	}

	if ( condition.type === 'meta' ) {
		const metaValue = currentMeta[ condition.key ];
		// Loose comparison to handle string/number type differences.
		// eslint-disable-next-line eqeqeq
		return metaValue == condition.value;
	}

	return false;
}

/**
 * Build taxonomy update payload from a rule's actions.
 *
 * Merges new term IDs into existing selections (doesn't replace).
 *
 * @param {Object} actions           Rule actions { select_taxonomies, ... }.
 * @param {Object} currentTaxonomies Current taxonomy state.
 * @return {Object} Payload for dispatch('core/editor').editPost().
 */
export function buildTaxonomyUpdates( actions, currentTaxonomies ) {
	const updates = {};
	const selectTaxonomies = actions?.select_taxonomies || {};

	for ( const [ taxonomy, termIds ] of Object.entries( selectTaxonomies ) ) {
		if ( ! Array.isArray( termIds ) || ! termIds.length ) {
			continue;
		}

		const existing = currentTaxonomies[ taxonomy ] || [];
		const merged = [ ...new Set( [ ...existing, ...termIds ] ) ];

		// Only include if there are new terms to add.
		if ( merged.length > existing.length ) {
			updates[ taxonomy ] = merged;
		}
	}

	return updates;
}
