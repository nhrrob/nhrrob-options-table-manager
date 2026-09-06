/**
 * Fallback for sections not yet migrated to the 2.0 React shell.
 */
import { __, sprintf } from '@wordpress/i18n';

import Panel from './Panel';

export default function PlaceholderScreen( { module } ) {
	return (
		<Panel title={ module.label }>
			<p className="nhrotm-muted">
				{ sprintf(
					/* translators: %s: section name. */
					__(
						'The %s section is being migrated to the 2.0 interface.',
						'nhrrob-options-table-manager'
					),
					module.label
				) }
			</p>
		</Panel>
	);
}
