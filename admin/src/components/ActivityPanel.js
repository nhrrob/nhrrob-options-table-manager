/**
 * Recent activity — the last few recorded changes, expandable into the full
 * paginated history via "View all". Collapsed state reuses the rows the
 * dashboard payload already carries, so opening the page costs no extra request.
 */
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

import Panel from './Panel';

const PER_PAGE = 20;

/**
 * @param {Object} root0        Props.
 * @param {Array}  root0.recent Rows from the dashboard payload.
 * @return {Object} Panel element.
 */
export default function ActivityPanel( { recent = [] } ) {
	const [ expanded, setExpanded ] = useState( false );
	const [ page, setPage ] = useState( 1 );
	const [ feed, setFeed ] = useState( null );
	const [ status, setStatus ] = useState( 'idle' );

	const load = ( next ) => {
		setStatus( 'loading' );
		apiFetch( {
			path: `nhrotm/v1/dashboard/activity?page=${ next }&per_page=${ PER_PAGE }`,
		} )
			.then( ( res ) => {
				setFeed( res.data );
				setPage( next );
				setExpanded( true );
				setStatus( 'ready' );
			} )
			.catch( () => setStatus( 'error' ) );
	};

	const collapse = () => {
		setExpanded( false );
		setPage( 1 );
	};

	const items = expanded && feed ? feed.items : recent;
	const total = feed ? feed.total : null;

	const headAction = expanded ? (
		<button type="button" className="nhrotm-linkbtn" onClick={ collapse }>
			{ __( 'Show less', 'nhrrob-options-table-manager' ) }
		</button>
	) : (
		recent.length > 0 && (
			<button
				type="button"
				className="nhrotm-linkbtn"
				onClick={ () => load( 1 ) }
			>
				{ __( 'View all →', 'nhrrob-options-table-manager' ) }
			</button>
		)
	);

	return (
		<Panel
			title={ __( 'Recent activity', 'nhrrob-options-table-manager' ) }
			actions={ headAction || undefined }
		>
			{ status === 'error' && (
				<p className="nhrotm-error">
					{ __(
						'Could not load the activity history.',
						'nhrrob-options-table-manager'
					) }
				</p>
			) }

			{ items.length > 0 ? (
				<ul className="nhrotm-activity">
					{ items.map( ( ev, i ) => (
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

			{ expanded && feed && (
				<div className="nhrotm-activity__foot">
					<span>
						{ sprintf(
							/* translators: 1: current page, 2: total pages, 3: total entries. */
							__(
								'Page %1$s of %2$s · %3$s entries',
								'nhrrob-options-table-manager'
							),
							page,
							Math.max( 1, feed.total_pages ),
							total
						) }
					</span>
					<span className="nhrotm-actions">
						<button
							type="button"
							className="nhrotm-btn nhrotm-btn--soft nhrotm-btn--sm"
							disabled={ page <= 1 || status === 'loading' }
							onClick={ () => load( page - 1 ) }
						>
							{ __( '← Prev', 'nhrrob-options-table-manager' ) }
						</button>
						<button
							type="button"
							className="nhrotm-btn nhrotm-btn--soft nhrotm-btn--sm"
							disabled={
								page >= feed.total_pages || status === 'loading'
							}
							onClick={ () => load( page + 1 ) }
						>
							{ __( 'Next →', 'nhrrob-options-table-manager' ) }
						</button>
					</span>
				</div>
			) }
		</Panel>
	);
}
