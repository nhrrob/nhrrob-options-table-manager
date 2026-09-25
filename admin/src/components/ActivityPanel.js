/**
 * Recent activity — the last few recorded changes shown on the Dashboard.
 * "View all" navigates to the dedicated Activity tab (search/filter there)
 * rather than expanding in place.
 */
import { __ } from '@wordpress/i18n';

import Panel from './Panel';
import Icon from './Icon';

/**
 * @param {Object}   root0           Props.
 * @param {Array}    root0.recent    Rows from the dashboard payload.
 * @param {Function} root0.onViewAll Navigates to the dedicated Activity tab.
 * @return {Object} Panel element.
 */
export default function ActivityPanel( { recent = [], onViewAll } ) {
	const headAction = recent.length > 0 && (
		<button type="button" className="nhrotm-linkbtn" onClick={ onViewAll }>
			{ __( 'View all', 'nhrrob-options-table-manager' ) }
			<Icon name="arrowRight" size={ 13 } />
		</button>
	);

	return (
		<Panel
			title={ __( 'Recent activity', 'nhrrob-options-table-manager' ) }
			actions={ headAction || undefined }
		>
			{ recent.length > 0 ? (
				<ul className="nhrotm-activity">
					{ recent.map( ( ev, i ) => (
						<li key={ i }>
							<span className="nhrotm-activity__dot" />
							<span className="nhrotm-activity__text">
								{ ev.prefix }
								{ ev.code && (
									<>
										{ ' ' }
										<code>{ ev.code }</code>
									</>
								) }
								{ ev.suffix && ' ' + ev.suffix }
							</span>
							<span className="nhrotm-activity__when">
								{ ev.when }
							</span>
						</li>
					) ) }
				</ul>
			) : (
				<p className="nhrotm-muted">
					{ __(
						'No recorded changes yet. Edits, deletes, cleanups and snapshots will appear here.',
						'nhrrob-options-table-manager'
					) }
				</p>
			) }
		</Panel>
	);
}
