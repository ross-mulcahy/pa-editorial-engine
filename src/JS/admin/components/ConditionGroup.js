/**
 * Condition Group — AND/OR toggle + list of condition rows.
 *
 * Each condition can match a taxonomy term or a post meta value.
 */

import { Button, SelectControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { TermSearchControl } from './TermSearchControl';

/**
 * @param {Object}   props
 * @param {Object}   props.conditions { operator: 'AND'|'OR', rules: [] }
 * @param {Function} props.onChange   Callback when conditions change.
 */
export function ConditionGroup( { conditions, onChange } ) {
	const { operator = 'AND', rules = [] } = conditions;

	const updateOperator = ( val ) => {
		onChange( { ...conditions, operator: val } );
	};

	const updateRule = ( index, updated ) => {
		const next = [ ...rules ];
		next[ index ] = updated;
		onChange( { ...conditions, rules: next } );
	};

	const addRule = () => {
		onChange( {
			...conditions,
			rules: [ ...rules, { type: 'taxonomy', slug: '', term_id: 0 } ],
		} );
	};

	const removeRule = ( index ) => {
		onChange( {
			...conditions,
			rules: rules.filter( ( _, i ) => i !== index ),
		} );
	};

	return (
		<div className="pa-condition-group">
			<SelectControl
				label={ __( 'Match', 'pa-editorial-engine' ) }
				value={ operator }
				options={ [
					{
						label: __(
							'ALL conditions (AND)',
							'pa-editorial-engine'
						),
						value: 'AND',
					},
					{
						label: __(
							'ANY condition (OR)',
							'pa-editorial-engine'
						),
						value: 'OR',
					},
				] }
				onChange={ updateOperator }
			/>

			{ rules.map( ( rule, index ) => (
				<ConditionRow
					key={ index }
					condition={ rule }
					onChange={ ( val ) => updateRule( index, val ) }
					onRemove={ () => removeRule( index ) }
				/>
			) ) }

			<Button variant="secondary" isSmall onClick={ addRule }>
				{ __( '+ Add Condition', 'pa-editorial-engine' ) }
			</Button>
		</div>
	);
}

// eslint-disable-next-line jsdoc/check-line-alignment -- Fixer cannot resolve mixed-length type/name alignment.
/**
 * Single condition row — taxonomy term or meta value match.
 *
 * @param {Object}   root0           Component props.
 * @param {Object}   root0.condition Condition data.
 * @param {Function} root0.onChange  Callback when condition changes.
 * @param {Function} root0.onRemove Callback to remove condition.
 */
function ConditionRow( { condition, onChange, onRemove } ) {
	const update = ( key, value ) => {
		onChange( { ...condition, [ key ]: value } );
	};

	return (
		<div className="pa-condition-row">
			<SelectControl
				label={ __( 'Type', 'pa-editorial-engine' ) }
				value={ condition.type || 'taxonomy' }
				options={ [
					{
						label: __( 'Taxonomy', 'pa-editorial-engine' ),
						value: 'taxonomy',
					},
					{
						label: __( 'Meta', 'pa-editorial-engine' ),
						value: 'meta',
					},
				] }
				onChange={ ( val ) => update( 'type', val ) }
			/>

			{ condition.type === 'taxonomy' ? (
				<>
					<TextControl
						label={ __( 'Taxonomy slug', 'pa-editorial-engine' ) }
						value={ condition.slug || '' }
						onChange={ ( val ) => update( 'slug', val ) }
						placeholder="topic"
					/>
					<TermSearchControl
						taxonomySlug={ condition.slug || '' }
						value={ condition.term_id || 0 }
						onChange={ ( val ) => update( 'term_id', val ) }
					/>
				</>
			) : (
				<>
					<TextControl
						label={ __( 'Meta key', 'pa-editorial-engine' ) }
						value={ condition.key || '' }
						onChange={ ( val ) => update( 'key', val ) }
						placeholder="_pa_format"
					/>
					<TextControl
						label={ __( 'Value', 'pa-editorial-engine' ) }
						value={ condition.value ?? '' }
						onChange={ ( val ) => update( 'value', val ) }
					/>
				</>
			) }

			<Button
				variant="tertiary"
				isDestructive
				isSmall
				onClick={ onRemove }
			>
				{ __( 'Remove', 'pa-editorial-engine' ) }
			</Button>
		</div>
	);
}
