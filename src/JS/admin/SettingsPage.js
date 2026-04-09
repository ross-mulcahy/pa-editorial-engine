/**
 * PA Editorial Engine — Settings page component.
 *
 * Uses wp.apiFetch to read/write settings via the WordPress REST API.
 * This avoids issues with @wordpress/core-data entity resolution that
 * can fail in environments like WordPress Playground.
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Panel,
	PanelBody,
	PanelRow,
	ToggleControl,
	TextControl,
	Spinner,
	Notice,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { RuleBuilder } from './components/RuleBuilder';

const DEFAULTS = {
	locking_enabled: true,
	cloning_enabled: true,
	syndication_enabled: true,
	global_priority_offset: 10,
};

export function SettingsPage() {
	const [ formData, setFormData ] = useState( null );
	const [ taxonomyMap, setTaxonomyMap ] = useState( null );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ loadError, setLoadError ] = useState( null );

	// Load settings from REST API on mount.
	useEffect( () => {
		apiFetch( { path: '/wp/v2/settings' } )
			.then( ( settings ) => {
				setFormData( {
					...DEFAULTS,
					...( settings.pa_engine_settings || {} ),
				} );
				setTaxonomyMap(
					Array.isArray( settings.pa_taxonomy_map )
						? settings.pa_taxonomy_map
						: []
				);
			} )
			.catch( ( err ) => {
				setLoadError(
					err.message ||
						__( 'Failed to load settings.', 'pa-editorial-engine' )
				);
			} );
	}, [] );

	const updateField = useCallback( ( key, value ) => {
		setFormData( ( prev ) => ( { ...prev, [ key ]: value } ) );
	}, [] );

	const handleSave = async () => {
		setIsSaving( true );
		setNotice( null );

		try {
			const payload = { pa_engine_settings: formData };
			if ( taxonomyMap !== null ) {
				payload.pa_taxonomy_map = taxonomyMap;
			}
			await apiFetch( {
				path: '/wp/v2/settings',
				method: 'POST',
				data: payload,
			} );
			setNotice( {
				status: 'success',
				message: __( 'Settings saved.', 'pa-editorial-engine' ),
			} );
		} catch ( error ) {
			setNotice( {
				status: 'error',
				message:
					error.message ||
					__( 'Failed to save settings.', 'pa-editorial-engine' ),
			} );
		} finally {
			setIsSaving( false );
		}
	};

	if ( loadError ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ loadError }
			</Notice>
		);
	}

	if ( ! formData ) {
		return <Spinner />;
	}

	const apiCredentials =
		window.paEditorialEngine?.apiCredentialsConfigured || {};

	return (
		<div className="pa-editorial-engine-settings">
			<h1>{ __( 'PA Editorial Engine', 'pa-editorial-engine' ) }</h1>

			{ notice && (
				<Notice
					status={ notice.status }
					isDismissible
					onDismiss={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }

			<Panel>
				<PanelBody
					title={ __( 'Feature Toggles', 'pa-editorial-engine' ) }
					initialOpen
				>
					<PanelRow>
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __(
								'Enable Nuclear Locking',
								'pa-editorial-engine'
							) }
							help={ __(
								'Hard lock that prevents non-lock-holders from editing or saving.',
								'pa-editorial-engine'
							) }
							checked={ formData.locking_enabled }
							onChange={ ( val ) =>
								updateField( 'locking_enabled', val )
							}
						/>
					</PanelRow>
					<PanelRow>
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __(
								'Enable Cloning Engine',
								'pa-editorial-engine'
							) }
							help={ __(
								'Allow editors to create "New Lead" copies of stories.',
								'pa-editorial-engine'
							) }
							checked={ formData.cloning_enabled }
							onChange={ ( val ) =>
								updateField( 'cloning_enabled', val )
							}
						/>
					</PanelRow>
					<PanelRow>
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __(
								'Enable Syndication Hooks',
								'pa-editorial-engine'
							) }
							help={ __(
								'Editorial Stop and Correction Flag workflows.',
								'pa-editorial-engine'
							) }
							checked={ formData.syndication_enabled }
							onChange={ ( val ) =>
								updateField( 'syndication_enabled', val )
							}
						/>
					</PanelRow>
				</PanelBody>

				<PanelBody
					title={ __(
						'Global Configuration',
						'pa-editorial-engine'
					) }
				>
					<PanelRow>
						<TextControl
							label={ __(
								'Global Priority Offset',
								'pa-editorial-engine'
							) }
							help={ __(
								'Baseline priority for all taxonomy mapping rules.',
								'pa-editorial-engine'
							) }
							type="number"
							value={ String( formData.global_priority_offset ) }
							onChange={ ( val ) =>
								updateField(
									'global_priority_offset',
									Number( val )
								)
							}
							min={ 1 }
							max={ 100 }
						/>
					</PanelRow>
				</PanelBody>

				<PanelBody
					title={ __( 'API Credentials', 'pa-editorial-engine' ) }
				>
					<PanelRow>
						<p>
							{ apiCredentials.paWire
								? __(
										'PA Wire API key: Configured via environment variable.',
										'pa-editorial-engine'
								  )
								: __(
										'PA Wire API key: Not configured. Set PA_WIRE_API_KEY in vip-config.php.',
										'pa-editorial-engine'
								  ) }
						</p>
					</PanelRow>
					<PanelRow>
						<p>
							{ apiCredentials.digital
								? __(
										'Digital API key: Configured via environment variable.',
										'pa-editorial-engine'
								  )
								: __(
										'Digital API key: Not configured. Set PA_DIGITAL_API_KEY in vip-config.php.',
										'pa-editorial-engine'
								  ) }
						</p>
					</PanelRow>
				</PanelBody>
				<PanelBody
					title={ __(
						'Taxonomy Dependency Map',
						'pa-editorial-engine'
					) }
					initialOpen={ false }
				>
					{ taxonomyMap !== null ? (
						<RuleBuilder
							rules={ taxonomyMap }
							onChange={ setTaxonomyMap }
						/>
					) : (
						<Spinner />
					) }
				</PanelBody>
			</Panel>

			<div style={ { marginTop: '16px' } }>
				<Button
					variant="primary"
					isBusy={ isSaving }
					disabled={ isSaving }
					onClick={ handleSave }
				>
					{ __( 'Save Settings', 'pa-editorial-engine' ) }
				</Button>
			</div>
		</div>
	);
}
