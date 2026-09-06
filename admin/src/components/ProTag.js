/**
 * Inert "Pro" tag — the free build's quiet, in-context PRO signal (PRD §0.2).
 * Routes to the Upgrade screen; renders nothing once the PRO add-on is active.
 */
import { __ } from '@wordpress/i18n';

export default function ProTag( { onNavigate, hasPro } ) {
	if ( hasPro ) {
		return null;
	}
	return (
		<button
			type="button"
			className="nhrotm-pro-tag"
			title={ __( 'Available in Pro', 'nhrrob-options-table-manager' ) }
			onClick={ () => onNavigate && onNavigate( 'upgrade' ) }
		>
			{ __( 'Pro', 'nhrrob-options-table-manager' ) }
		</button>
	);
}
