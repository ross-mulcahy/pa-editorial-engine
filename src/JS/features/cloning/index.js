/**
 * Cloning Engine — Gutenberg "Add New Lead" button.
 *
 * Registers a PluginPostStatusInfo slot with a button that triggers
 * the server-side clone action.
 */

import { registerPlugin } from '@wordpress/plugins';
import { PluginPostStatusInfo } from '@wordpress/editor';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

function AddNewLeadButton() {
	const config = window.paEditorialCloning;

	if ( ! config?.enabled || ! config?.cloneUrl ) {
		return null;
	}

	const handleClick = () => {
		// Navigate to the clone admin action URL (includes nonce).
		window.location.href = config.cloneUrl;
	};

	return (
		<PluginPostStatusInfo>
			<Button variant="secondary" onClick={ handleClick }>
				{ __( 'Add New Lead', 'pa-editorial-engine' ) }
			</Button>
		</PluginPostStatusInfo>
	);
}

export function initCloning() {
	if ( ! window.paEditorialCloning?.enabled ) {
		return;
	}

	registerPlugin( 'pa-editorial-cloning', {
		render: AddNewLeadButton,
	} );
}
