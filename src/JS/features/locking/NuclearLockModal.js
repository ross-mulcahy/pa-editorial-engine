/**
 * Nuclear Lock Modal — non-dismissible overlay shown when the post is
 * locked by another user.
 */

import { Modal, Icon } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { lock as lockIcon } from '@wordpress/icons';

/**
 * @param {Object} props
 * @param {string} props.lockerName Display name of the user holding the lock.
 */
export function NuclearLockModal( { lockerName } ) {
	const displayName =
		lockerName || __( 'another user', 'pa-editorial-engine' );

	return (
		<Modal
			title={ __( 'Restricted Access', 'pa-editorial-engine' ) }
			isDismissible={ false }
			shouldCloseOnClickOutside={ false }
			shouldCloseOnEsc={ false }
			className="pa-nuclear-lock-modal"
		>
			<div className="pa-nuclear-lock-modal__content">
				<Icon icon={ lockIcon } size={ 48 } />
				<p>
					{ sprintf(
						/* translators: %s: display name of the lock holder */
						__(
							'%s is currently subbing this story. Your changes will not be saved.',
							'pa-editorial-engine'
						),
						displayName
					) }
				</p>
				<p className="pa-nuclear-lock-modal__hint">
					{ __(
						'Please wait until the editor has finished, or contact them directly.',
						'pa-editorial-engine'
					) }
				</p>
			</div>
		</Modal>
	);
}
