/**
 * Cron — every scheduled event (WP-Cron) with its next run, recurrence and
 * owner; run one now or delete it. Wired to nhrotm/v1/tools/cron.
 *
 * "Possibly orphaned" is only a hint (nothing listens for the hook on this
 * request and no installed plugin matches its name): plenty of plugins attach
 * their cron callbacks only during cron requests.
 */
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

import DataTable from './DataTable';
import Icon from './Icon';
import Panel from './Panel';
import { useConfirm } from './ConfirmProvider';
import { useToast } from './ToastProvider';

export default function CronPanel() {
	const confirm = useConfirm();
	const toast = useToast();
	const [ rows, setRows ] = useState( null );
	const [ busy, setBusy ] = useState( false );

	const load = useCallback( () => {
		apiFetch( { path: 'nhrotm/v1/tools/cron' } )
			.then( ( res ) => setRows( res.data ) )
			.catch( () => setRows( [] ) );
	}, [] );

	useEffect( () => {
		load();
	}, [ load ] );

	const call = ( path, row, extra, message ) => {
		setBusy( true );
		apiFetch( {
			path: 'nhrotm/v1/tools/cron/' + path,
			method: 'POST',
			data: {
				hook: row.hook,
				timestamp: row.timestamp,
				sig: row.sig,
				...extra,
			},
		} )
			.then( ( res ) => {
				setRows( res.data );
				toast( message );
			} )
			.catch( ( e ) =>
				toast(
					( e && e.message ) ||
						__( 'Action failed.', 'nhrrob-options-table-manager' ),
					'error'
				)
			)
			.finally( () => setBusy( false ) );
	};

	const remove = async ( row ) => {
		if (
			await confirm(
				sprintf(
					/* translators: %s: cron hook name. */
					__(
						'Delete the scheduled event %s?',
						'nhrrob-options-table-manager'
					),
					row.hook
				),
				{
					description: __(
						'If its plugin is still active it may simply schedule the event again. This action cannot be undone.',
						'nhrrob-options-table-manager'
					),
					confirmLabel: __(
						'Delete',
						'nhrrob-options-table-manager'
					),
				}
			)
		) {
			call(
				'delete',
				row,
				{},
				__(
					'Scheduled event deleted successfully.',
					'nhrrob-options-table-manager'
				)
			);
		}
	};

	const columns = [
		{
			key: 'hook',
			label: __( 'Hook', 'nhrrob-options-table-manager' ),
			sortable: true,
			colClassName: 'nhrotm-col-name',
			render: ( r ) => (
				<span className="nhrotm-grid__name" title={ r.args || r.hook }>
					{ r.hook }
					{ r.orphaned && (
						<>
							{ ' ' }
							<span className="nhrotm-badge nhrotm-badge--warning">
								{ __(
									'Possibly orphaned',
									'nhrrob-options-table-manager'
								) }
							</span>
						</>
					) }
				</span>
			),
		},
		{
			key: 'owner',
			label: __( 'Owner', 'nhrrob-options-table-manager' ),
			sortable: true,
			colClassName: 'nhrotm-col-owner',
		},
		{
			key: 'next',
			label: __( 'Next run', 'nhrrob-options-table-manager' ),
			sortable: true,
			sortValue: ( r ) => r.timestamp,
			colClassName: 'nhrotm-col-date',
			cellClassName: 'nhrotm-muted',
		},
		{
			key: 'recurrence',
			label: __( 'Repeats', 'nhrrob-options-table-manager' ),
			sortable: true,
			colClassName: 'nhrotm-col-owner',
		},
		{
			key: 'actions',
			label: __( 'Actions', 'nhrrob-options-table-manager' ),
			align: 'right',
			colClassName: 'nhrotm-col-act',
			render: ( r ) => (
				<div className="nhrotm-grid__actions">
					<button
						type="button"
						className="nhrotm-iconbtn"
						disabled={ busy }
						onClick={ () =>
							call(
								'run',
								r,
								{},
								__(
									'Scheduled event run successfully.',
									'nhrrob-options-table-manager'
								)
							)
						}
					>
						<Icon name="optimize" size={ 14 } />
						{ __( 'Run now', 'nhrrob-options-table-manager' ) }
					</button>
					{ ! r.core && (
						<button
							type="button"
							className="nhrotm-iconbtn nhrotm-iconbtn--danger"
							disabled={ busy }
							onClick={ () => remove( r ) }
						>
							<Icon name="trash" size={ 14 } />
							{ __( 'Delete', 'nhrrob-options-table-manager' ) }
						</button>
					) }
				</div>
			),
		},
	];

	return (
		<Panel
			anchor="cron"
			title={ __( 'Scheduled events', 'nhrrob-options-table-manager' ) }
			meta={
				rows
					? sprintf(
							/* translators: %d: number of scheduled events. */
							__( '%d events', 'nhrrob-options-table-manager' ),
							rows.length
					  )
					: null
			}
		>
			<p className="nhrotm-muted">
				{ __(
					'Everything WordPress is scheduled to run in the background (WP-Cron). Events flagged "Possibly orphaned" have no code listening for them and no matching installed plugin — usually leftovers, but check before deleting.',
					'nhrrob-options-table-manager'
				) }
			</p>
			{ rows === null ? (
				<p className="nhrotm-muted">
					{ __( 'Loading…', 'nhrrob-options-table-manager' ) }
				</p>
			) : (
				<DataTable
					columns={ columns }
					rows={ rows }
					rowKey={ ( r ) => r.hook + r.timestamp + r.sig }
					defaultSort={ { key: 'next', dir: 'asc' } }
					searchKeys={ [ 'hook', 'owner' ] }
					searchPlaceholder={ __(
						'Search hook or owner…',
						'nhrrob-options-table-manager'
					) }
					pageSize={ 20 }
					emptyMessage={ __(
						'No scheduled events.',
						'nhrrob-options-table-manager'
					) }
				/>
			) }
		</Panel>
	);
}
