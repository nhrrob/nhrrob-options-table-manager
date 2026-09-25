/**
 * Sticky quick-jump pill bar — scrolls a screen's own panels into view.
 * Reuses the `.nhrotm-segmented` pill styling from Browse/Integrations.
 */
import { __ } from '@wordpress/i18n';

import { scrollToPanel } from './usePanelFocus';

/**
 * @param {Object} root0       Props.
 * @param {Array}  root0.items `[{ id, label }]` — id matches a Panel's `anchor`.
 */
export default function JumpNav( { items } ) {
	return (
		<nav
			className="nhrotm-jumpnav"
			aria-label={ __(
				'Jump to section',
				'nhrrob-options-table-manager'
			) }
		>
			<div className="nhrotm-segmented">
				{ items.map( ( item ) => (
					<button
						key={ item.id }
						type="button"
						className="nhrotm-segmented__item"
						onClick={ () => scrollToPanel( item.id ) }
					>
						{ item.label }
					</button>
				) ) }
			</div>
		</nav>
	);
}
