/**
 * Action Group — defines what happens when rule conditions are met.
 *
 * Supports:
 * - select_taxonomies: auto-check taxonomy terms
 * - force_meta: set post meta values
 * - ui_notices: display Gutenberg snackbar messages
 */

import { Button, TextControl, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * @param {Object}   props
 * @param {Object}   props.actions  The actions object from the rule.
 * @param {Function} props.onChange Callback when actions change.
 */
export function ActionGroup( { actions, onChange } ) {
	const {
		select_taxonomies: selectTaxonomies = {},
		force_meta: forceMeta = {},
		ui_notices: uiNotices = [],
	} = actions;

	const update = ( key, value ) => {
		onChange( { ...actions, [ key ]: value } );
	};

	return (
		<div className="pa-action-group">
			<TaxonomyActions
				value={ selectTaxonomies }
				onChange={ ( val ) => update( 'select_taxonomies', val ) }
			/>
			<MetaActions
				value={ forceMeta }
				onChange={ ( val ) => update( 'force_meta', val ) }
			/>
			<NoticeActions
				value={ uiNotices }
				onChange={ ( val ) => update( 'ui_notices', val ) }
			/>
		</div>
	);
}

/**
 * Taxonomy selection actions — taxonomy slug → comma-separated term IDs.
 * @param {Object}   root0          Component props.
 * @param {Object}   root0.value    Current value.
 * @param {Function} root0.onChange Callback when value changes.
 */
function TaxonomyActions( { value, onChange } ) {
	const entries = Object.entries( value );

	const addEntry = () => {
		onChange( { ...value, '': [] } );
	};

	const updateEntry = ( oldKey, newKey, termIds ) => {
		const next = { ...value };
		if ( oldKey !== newKey ) {
			delete next[ oldKey ];
		}
		next[ newKey ] = termIds;
		onChange( next );
	};

	const removeEntry = ( key ) => {
		const next = { ...value };
		delete next[ key ];
		onChange( next );
	};

	return (
		<div className="pa-action-taxonomy">
			<h4>
				{ __( 'Auto-select Taxonomy Terms', 'pa-editorial-engine' ) }
			</h4>
			{ entries.map( ( [ slug, termIds ], index ) => (
				<div key={ index } className="pa-action-taxonomy__row">
					<TextControl
						label={ __( 'Taxonomy slug', 'pa-editorial-engine' ) }
						value={ slug }
						onChange={ ( val ) =>
							updateEntry( slug, val, termIds )
						}
						placeholder="service"
					/>
					<TextControl
						label={ __(
							'Term IDs (comma-separated)',
							'pa-editorial-engine'
						) }
						value={ ( termIds || [] ).join( ', ' ) }
						onChange={ ( val ) => {
							const ids = val
								.split( ',' )
								.map( ( s ) => parseInt( s.trim(), 10 ) )
								.filter( ( n ) => ! isNaN( n ) );
							updateEntry( slug, slug, ids );
						} }
						placeholder="12, 34, 56"
					/>
					<Button
						variant="tertiary"
						isDestructive
						isSmall
						onClick={ () => removeEntry( slug ) }
					>
						{ __( 'Remove', 'pa-editorial-engine' ) }
					</Button>
				</div>
			) ) }
			<Button variant="secondary" isSmall onClick={ addEntry }>
				{ __( '+ Add Taxonomy', 'pa-editorial-engine' ) }
			</Button>
		</div>
	);
}

/**
 * Force meta actions — key/value pairs set on the post.
 * @param {Object}   root0          Component props.
 * @param {Object}   root0.value    Current value.
 * @param {Function} root0.onChange Callback when value changes.
 */
function MetaActions( { value, onChange } ) {
	const entries = Object.entries( value );

	const addEntry = () => {
		onChange( { ...value, '': '' } );
	};

	const updateEntry = ( oldKey, newKey, newValue ) => {
		const next = { ...value };
		if ( oldKey !== newKey ) {
			delete next[ oldKey ];
		}
		next[ newKey ] = newValue;
		onChange( next );
	};

	const removeEntry = ( key ) => {
		const next = { ...value };
		delete next[ key ];
		onChange( next );
	};

	return (
		<div className="pa-action-meta">
			<h4>{ __( 'Force Meta Values', 'pa-editorial-engine' ) }</h4>
			{ entries.map( ( [ key, val ], index ) => (
				<div key={ index } className="pa-action-meta__row">
					<TextControl
						label={ __( 'Meta key', 'pa-editorial-engine' ) }
						value={ key }
						onChange={ ( k ) => updateEntry( key, k, val ) }
					/>
					<TextControl
						label={ __( 'Value', 'pa-editorial-engine' ) }
						value={ String( val ) }
						onChange={ ( v ) => updateEntry( key, key, v ) }
					/>
					<Button
						variant="tertiary"
						isDestructive
						isSmall
						onClick={ () => removeEntry( key ) }
					>
						{ __( 'Remove', 'pa-editorial-engine' ) }
					</Button>
				</div>
			) ) }
			<Button variant="secondary" isSmall onClick={ addEntry }>
				{ __( '+ Add Meta', 'pa-editorial-engine' ) }
			</Button>
		</div>
	);
}

/**
 * UI Notice actions — snackbar messages shown in Gutenberg when the rule fires.
 * @param {Object}   root0          Component props.
 * @param {Object}   root0.value    Current value.
 * @param {Function} root0.onChange Callback when value changes.
 */
function NoticeActions( { value, onChange } ) {
	const addNotice = () => {
		onChange( [ ...value, { type: 'info', message: '' } ] );
	};

	const updateNotice = ( index, updated ) => {
		const next = [ ...value ];
		next[ index ] = updated;
		onChange( next );
	};

	const removeNotice = ( index ) => {
		onChange( value.filter( ( _, i ) => i !== index ) );
	};

	return (
		<div className="pa-action-notices">
			<h4>{ __( 'UI Notices', 'pa-editorial-engine' ) }</h4>
			{ value.map( ( notice, index ) => (
				<div key={ index } className="pa-action-notices__row">
					<SelectControl
						label={ __( 'Type', 'pa-editorial-engine' ) }
						value={ notice.type || 'info' }
						options={ [
							{
								label: __( 'Info', 'pa-editorial-engine' ),
								value: 'info',
							},
							{
								label: __( 'Warning', 'pa-editorial-engine' ),
								value: 'warning',
							},
							{
								label: __( 'Error', 'pa-editorial-engine' ),
								value: 'error',
							},
						] }
						onChange={ ( val ) =>
							updateNotice( index, { ...notice, type: val } )
						}
					/>
					<TextControl
						label={ __( 'Message', 'pa-editorial-engine' ) }
						value={ notice.message || '' }
						onChange={ ( val ) =>
							updateNotice( index, { ...notice, message: val } )
						}
					/>
					<Button
						variant="tertiary"
						isDestructive
						isSmall
						onClick={ () => removeNotice( index ) }
					>
						{ __( 'Remove', 'pa-editorial-engine' ) }
					</Button>
				</div>
			) ) }
			<Button variant="secondary" isSmall onClick={ addNotice }>
				{ __( '+ Add Notice', 'pa-editorial-engine' ) }
			</Button>
		</div>
	);
}
