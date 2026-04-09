/**
 * PA Editorial Engine — Admin settings entry point.
 *
 * Mounts the React-based settings UI into the admin page.
 */

import { createRoot } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';
import { SettingsPage } from './SettingsPage';

domReady( () => {
	const container = document.getElementById( 'pa-editorial-engine-admin' );
	if ( ! container ) {
		return;
	}

	const root = createRoot( container );
	root.render( <SettingsPage /> );
} );
