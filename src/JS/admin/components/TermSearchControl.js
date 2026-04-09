/**
 * Term Search Control — async search against the REST API for taxonomy terms.
 *
 * Handles PA's large taxonomy trees by searching via `/wp/v2/{taxonomy}?search=`.
 */

import { useState, useEffect } from '@wordpress/element';
import { ComboboxControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

// eslint-disable-next-line jsdoc/check-line-alignment -- Fixer cannot resolve mixed-length type/name alignment.
/**
 * @param {Object}   props              Component props.
 * @param {string}   props.taxonomySlug Taxonomy REST base (e.g. 'topic').
 * @param {number}   props.value        Currently selected term ID.
 * @param {Function} props.onChange     Callback when selection changes.
 */
export function TermSearchControl( { taxonomySlug, value, onChange } ) {
	const [ options, setOptions ] = useState( [] );
	const [ , setIsLoading ] = useState( false );

	// Load initial label for the current value.
	useEffect( () => {
		if ( ! value || ! taxonomySlug ) {
			return;
		}

		apiFetch( {
			path: `/wp/v2/${ taxonomySlug }?include=${ value }`,
		} )
			.then( ( terms ) => {
				if ( terms?.length ) {
					setOptions( [
						{
							value: terms[ 0 ].id,
							label: terms[ 0 ].name,
						},
					] );
				}
			} )
			.catch( () => {} );
	}, [ value, taxonomySlug ] );

	const handleSearch = ( input ) => {
		if ( ! taxonomySlug || input.length < 2 ) {
			return;
		}

		setIsLoading( true );

		apiFetch( {
			path: `/wp/v2/${ taxonomySlug }?search=${ encodeURIComponent(
				input
			) }&per_page=20`,
		} )
			.then( ( terms ) => {
				setOptions(
					( terms || [] ).map( ( term ) => ( {
						value: term.id,
						label: term.name,
					} ) )
				);
			} )
			.catch( () => setOptions( [] ) )
			.finally( () => setIsLoading( false ) );
	};

	return (
		<ComboboxControl
			label={ __( 'Term', 'pa-editorial-engine' ) }
			value={ value || null }
			options={ options }
			onChange={ ( val ) => onChange( val ? Number( val ) : 0 ) }
			onFilterValueChange={ handleSearch }
			__experimentalShowAllOnFocus
		/>
	);
}
