/**
 * Visual Rule Builder for the Taxonomy Dependency Map.
 *
 * Recursive component that allows editors to create rules with:
 * - Conditions (AND/OR with taxonomy/meta triggers)
 * - Actions (auto-select taxonomies, force meta, UI notices)
 * - JSON source fallback for advanced users
 */

import { useState } from '@wordpress/element';
import {
	Button,
	Panel,
	PanelBody,
	PanelRow,
	TextControl,
	ToggleControl,
	TextareaControl,
	Notice,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { ConditionGroup } from './ConditionGroup';
import { ActionGroup } from './ActionGroup';

/**
 * Create a blank rule with default structure.
 *
 * @return {Object} New empty rule.
 */
function createEmptyRule() {
	return {
		rule_id: `rule-${ Date.now() }`,
		label: '',
		priority: 10,
		active: true,
		conditions: {
			operator: 'AND',
			rules: [],
		},
		actions: {
			select_taxonomies: {},
			force_meta: {},
			ui_notices: [],
		},
	};
}

/**
 * Validate a rule has minimum required fields.
 *
 * @param {Object} rule The rule to validate.
 * @return {string|null} Error message, or null if valid.
 */
function validateRule( rule ) {
	if ( ! rule.rule_id || ! rule.label ) {
		return __( 'Rule ID and label are required.', 'pa-editorial-engine' );
	}
	if ( ! rule.conditions?.rules?.length ) {
		return __(
			'At least one condition is required.',
			'pa-editorial-engine'
		);
	}
	if (
		! Object.keys( rule.actions?.select_taxonomies || {} ).length &&
		! Object.keys( rule.actions?.force_meta || {} ).length
	) {
		return __( 'At least one action is required.', 'pa-editorial-engine' );
	}
	return null;
}

/**
 * @param {Object}   props
 * @param {Array}    props.rules    Current rules array.
 * @param {Function} props.onChange Callback when rules change.
 */
export function RuleBuilder( { rules, onChange } ) {
	const [ showJsonSource, setShowJsonSource ] = useState( false );
	const [ jsonError, setJsonError ] = useState( null );

	const updateRule = ( index, updatedRule ) => {
		const next = [ ...rules ];
		next[ index ] = updatedRule;
		onChange( next );
	};

	const addRule = () => {
		onChange( [ ...rules, createEmptyRule() ] );
	};

	const removeRule = ( index ) => {
		onChange( rules.filter( ( _, i ) => i !== index ) );
	};

	const handleJsonChange = ( json ) => {
		try {
			const parsed = JSON.parse( json );
			if ( ! Array.isArray( parsed ) ) {
				setJsonError(
					__( 'Must be a JSON array.', 'pa-editorial-engine' )
				);
				return;
			}
			setJsonError( null );
			onChange( parsed );
		} catch ( e ) {
			setJsonError( e.message );
		}
	};

	return (
		<div className="pa-rule-builder">
			<div className="pa-rule-builder__header">
				<Button variant="secondary" onClick={ addRule }>
					{ __( '+ Add Rule', 'pa-editorial-engine' ) }
				</Button>
				<Button
					variant="tertiary"
					onClick={ () => setShowJsonSource( ! showJsonSource ) }
				>
					{ showJsonSource
						? __( 'Visual Editor', 'pa-editorial-engine' )
						: __( 'Edit JSON Source', 'pa-editorial-engine' ) }
				</Button>
			</div>

			{ showJsonSource ? (
				<div className="pa-rule-builder__json">
					{ jsonError && (
						<Notice status="error" isDismissible={ false }>
							{ jsonError }
						</Notice>
					) }
					<TextareaControl
						label={ __( 'JSON Source', 'pa-editorial-engine' ) }
						value={ JSON.stringify( rules, null, 2 ) }
						onChange={ handleJsonChange }
						rows={ 20 }
					/>
				</div>
			) : (
				<div className="pa-rule-builder__rules">
					{ rules.length === 0 && (
						<p className="pa-rule-builder__empty">
							{ __(
								'No rules configured. Click "+ Add Rule" to get started.',
								'pa-editorial-engine'
							) }
						</p>
					) }

					{ rules.map( ( rule, index ) => {
						const error = validateRule( rule );
						return (
							<RuleCard
								key={ rule.rule_id || index }
								rule={ rule }
								index={ index }
								error={ error }
								onChange={ ( updated ) =>
									updateRule( index, updated )
								}
								onRemove={ () => removeRule( index ) }
							/>
						);
					} ) }
				</div>
			) }
		</div>
	);
}

/**
 * Single rule card with conditions and actions.
 *
 * @param {Object}   root0          Component props.
 * @param {Object}   root0.rule     The rule data.
 * @param {number}   root0.index    Rule index.
 * @param {string}   root0.error    Validation error message.
 * @param {Function} root0.onChange Callback when rule changes.
 * @param {Function} root0.onRemove Callback to remove the rule.
 */
function RuleCard( { rule, index, error, onChange, onRemove } ) {
	const update = ( key, value ) => {
		onChange( { ...rule, [ key ]: value } );
	};

	return (
		<Panel className="pa-rule-card">
			<PanelBody
				title={ rule.label || `Rule #${ index + 1 }` }
				initialOpen={ index === 0 }
			>
				{ error && (
					<Notice status="warning" isDismissible={ false }>
						{ error }
					</Notice>
				) }

				<PanelRow>
					<TextControl
						label={ __( 'Rule ID', 'pa-editorial-engine' ) }
						value={ rule.rule_id }
						onChange={ ( val ) => update( 'rule_id', val ) }
					/>
				</PanelRow>
				<PanelRow>
					<TextControl
						label={ __( 'Label', 'pa-editorial-engine' ) }
						value={ rule.label }
						onChange={ ( val ) => update( 'label', val ) }
					/>
				</PanelRow>
				<PanelRow>
					<TextControl
						label={ __( 'Priority', 'pa-editorial-engine' ) }
						type="number"
						value={ String( rule.priority ) }
						onChange={ ( val ) =>
							update( 'priority', Number( val ) )
						}
						min={ 1 }
						max={ 100 }
					/>
				</PanelRow>
				<PanelRow>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Active', 'pa-editorial-engine' ) }
						checked={ rule.active }
						onChange={ ( val ) => update( 'active', val ) }
					/>
				</PanelRow>

				<h3>{ __( 'Conditions (IF)', 'pa-editorial-engine' ) }</h3>
				<ConditionGroup
					conditions={ rule.conditions }
					onChange={ ( val ) => update( 'conditions', val ) }
				/>

				<h3>{ __( 'Actions (THEN)', 'pa-editorial-engine' ) }</h3>
				<ActionGroup
					actions={ rule.actions }
					onChange={ ( val ) => update( 'actions', val ) }
				/>

				<PanelRow>
					<Button
						variant="tertiary"
						isDestructive
						onClick={ onRemove }
					>
						{ __( 'Remove Rule', 'pa-editorial-engine' ) }
					</Button>
				</PanelRow>
			</PanelBody>
		</Panel>
	);
}
