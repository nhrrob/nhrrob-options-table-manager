/**
 * Activity — the full change feed, reached via Dashboard's "View all".
 * Wired to nhrotm/v1/dashboard/activity (ActivityService).
 *
 * Fetches one large page rather than following the server's own pagination —
 * DataTable is self-contained (client-side search/sort/pagination) once a
 * list is fully loaded, same as Optimize's three lists, and the history
 * table is bounded by Settings' retention-window prune, not open-ended.
 */
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

import DataTable from './DataTable';
import Panel from './Panel';
import ScreenHeader from './ScreenHeader';

const FETCH_LIMIT = 100;

const ACTION_LABELS = {
	create: __( 'Added', 'nhrrob-options-table-manager' ),
	update: __( 'Updated', 'nhrrob-options-table-manager' ),
	delete: __( 'Deleted', 'nhrrob-options-table-manager' ),
	delete_usermeta: __( 'Deleted (usermeta)', 'nhrrob-options-table-manager' ),
	delete_postmeta: __( 'Deleted (postmeta)', 'nhrrob-options-table-manager' ),
	delete_commentmeta: __(
		'Deleted (commentmeta)',
		'nhrrob-options-table-manager'
	),
	delete_termmeta: __( 'Deleted (termmeta)', 'nhrrob-options-table-manager' ),
	delete_transient: __( 'Deleted transient', 'nhrrob-options-table-manager' ),
	create_transient: __( 'Added transient', 'nhrrob-options-table-manager' ),
	update_transient: __( 'Updated transient', 'nhrrob-options-table-manager' ),
	disable_autoload: __( 'Disabled autoload', 'nhrrob-options-table-manager' ),
	delete_orphans: __(
		'Deleted orphan group',
		'nhrrob-options-table-manager'
	),
	clean_transients: __(
		'Cleaned transients',
		'nhrrob-options-table-manager'
	),
	clean_transients_all: __(
		'Cleaned transients',
		'nhrrob-options-table-manager'
	),
	snapshot: __( 'Snapshot', 'nhrrob-options-table-manager' ),
	restore: __( 'Restored', 'nhrrob-options-table-manager' ),
	restore_backup: __( 'Restored', 'nhrrob-options-table-manager' ),
};

const columns = [
	{
		key: 'message',
		label: __( 'Change', 'nhrrob-options-table-manager' ),
		colClassName: 'nhrotm-col-name',
		render: ( r ) => (
			<span className="nhrotm-activity__text">
				{ r.prefix }
				{ r.code && (
					<>
						{ ' ' }
						<code>{ r.code }</code>
					</>
				) }
				{ r.suffix && ' ' + r.suffix }
			</span>
		),
	},
	{
		key: 'action',
		label: __( 'Type', 'nhrrob-options-table-manager' ),
		colClassName: 'nhrotm-col-badge-wide',
		render: ( r ) => (
			<span className="nhrotm-badge nhrotm-badge--muted">
				{ ACTION_LABELS[ r.action ] || r.action }
			</span>
		),
	},
	{
		key: 'who',
		label: __( 'Who', 'nhrrob-options-table-manager' ),
		sortable: true,
		colClassName: 'nhrotm-col-owner',
	},
	{
		key: 'when',
		label: __( 'When', 'nhrrob-options-table-manager' ),
		align: 'right',
		sortable: true,
		sortValue: ( r ) => r.performed_at_ts,
		colClassName: 'nhrotm-col-date',
		cellClassName: 'nhrotm-muted',
	},
];

export default function ActivityScreen() {
	const [ rows, setRows ] = useState( [] );
	const [ total, setTotal ] = useState( 0 );
	const [ status, setStatus ] = useState( 'loading' );

	useEffect( () => {
		apiFetch( {
			path: `nhrotm/v1/dashboard/activity?page=1&per_page=${ FETCH_LIMIT }`,
		} )
			.then( ( res ) => {
				setRows( res.data.items );
				setTotal( res.data.total );
				setStatus( 'ready' );
			} )
			.catch( () => setStatus( 'error' ) );
	}, [] );

	return (
		<div className="nhrotm-activity-screen">
			<ScreenHeader
				title={ __( 'Activity', 'nhrrob-options-table-manager' ) }
				lede={ __(
					'Every recorded change made through the plugin — edits, deletes, cleanups, and snapshots.',
					'nhrrob-options-table-manager'
				) }
			/>
			<Panel title={ __( 'Activity', 'nhrrob-options-table-manager' ) }>
				{ status === 'loading' && (
					<p className="nhrotm-muted">
						{ __( 'Loading…', 'nhrrob-options-table-manager' ) }
					</p>
				) }
				{ status === 'error' && (
					<p className="nhrotm-error">
						{ __(
							'Could not load the activity history.',
							'nhrrob-options-table-manager'
						) }
					</p>
				) }
				{ status === 'ready' && (
					<>
						{ total > FETCH_LIMIT && (
							<p className="nhrotm-muted">
								{ __(
									'Showing the most recent 100 entries.',
									'nhrrob-options-table-manager'
								) }
							</p>
						) }
						<DataTable
							columns={ columns }
							rows={ rows }
							rowKey={ ( r ) => r.id }
							defaultSort={ { key: 'when', dir: 'desc' } }
							searchKeys={ [ 'option_name', 'who' ] }
							searchPlaceholder={ __(
								'Search option or user…',
								'nhrrob-options-table-manager'
							) }
							filters={ [
								{
									key: 'action',
									label: __(
										'Filter by type',
										'nhrrob-options-table-manager'
									),
									options: [
										{
											value: '',
											label: __(
												'All types',
												'nhrrob-options-table-manager'
											),
										},
										...Object.keys( ACTION_LABELS ).map(
											( key ) => ( {
												value: key,
												label: ACTION_LABELS[ key ],
											} )
										),
									],
								},
							] }
							pageSize={ 20 }
							emptyMessage={ __(
								'No recorded changes yet.',
								'nhrrob-options-table-manager'
							) }
						/>
					</>
				) }
			</Panel>
		</div>
	);
}
