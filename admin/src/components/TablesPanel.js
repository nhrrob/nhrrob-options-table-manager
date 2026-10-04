/**
 * Tables — every table of this site with size, overhead, engine (shown under
 * the name) and owner,
 * plus optimize / repair / convert / empty / drop. Wired to
 * nhrotm/v1/optimize/tables. Emptying or dropping needs the table name typed
 * exactly; the server refuses both for WordPress core tables.
 */
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

import DataTable from './DataTable';
import Icon from './Icon';
import Panel from './Panel';
import formatBytes from './formatBytes';
import { useConfirm } from './ConfirmProvider';
import { useToast } from './ToastProvider';

const KIND = {
	core: { tone: 'muted', label: null },
	plugin: { tone: 'muted', label: null },
	leftover: {
		tone: 'warning',
		label: __( 'Plugin removed', 'nhrrob-options-table-manager' ),
	},
	unknown: {
		tone: 'info',
		label: __( 'Unknown owner', 'nhrrob-options-table-manager' ),
	},
};

const DONE = {
	optimize: __(
		'Table optimized successfully.',
		'nhrrob-options-table-manager'
	),
	repair: __(
		'Table repaired successfully.',
		'nhrrob-options-table-manager'
	),
	convert: __(
		'Table converted to InnoDB successfully.',
		'nhrrob-options-table-manager'
	),
	empty: __( 'Table emptied successfully.', 'nhrrob-options-table-manager' ),
	drop: __( 'Table dropped successfully.', 'nhrrob-options-table-manager' ),
};

export default function TablesPanel() {
	const confirm = useConfirm();
	const toast = useToast();
	const [ rows, setRows ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	// Table awaiting a typed confirmation: { row, action, typed }.
	const [ danger, setDanger ] = useState( null );

	const load = useCallback( () => {
		apiFetch( { path: 'nhrotm/v1/optimize/tables' } )
			.then( ( res ) => setRows( res.data ) )
			.catch( () => setRows( [] ) );
	}, [] );

	useEffect( () => {
		load();
	}, [ load ] );

	const run = ( row, action, typed = '' ) => {
		setBusy( true );
		return apiFetch( {
			path: 'nhrotm/v1/optimize/tables/action',
			method: 'POST',
			data: { table: row.name, action, confirm: typed },
		} )
			.then( ( res ) => {
				setRows( res.data );
				toast( DONE[ action ] );
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

	const convert = async ( row ) => {
		if (
			await confirm(
				sprintf(
					/* translators: %s: table name. */
					__(
						'Convert %s to InnoDB?',
						'nhrrob-options-table-manager'
					),
					row.name
				),
				{
					description: __(
						'This rewrites the whole table and can take a while on a large one. Take a full database backup first.',
						'nhrrob-options-table-manager'
					),
					confirmLabel: __(
						'Convert',
						'nhrrob-options-table-manager'
					),
				}
			)
		) {
			run( row, 'convert' );
		}
	};

	const columns = [
		{
			key: 'name',
			label: __( 'Table', 'nhrrob-options-table-manager' ),
			sortable: true,
			colClassName: 'nhrotm-col-name',
			cellClassName: 'nhrotm-grid__name',
			// The storage engine sits under the name rather than in its own
			// column: seven columns did not fit the panel.
			render: ( r ) => (
				<>
					{ r.name }
					{ r.engine && (
						<span className="nhrotm-tables__engine">
							{ r.engine }
						</span>
					) }
				</>
			),
		},
		{
			key: 'owner',
			label: __( 'Owner', 'nhrrob-options-table-manager' ),
			sortable: true,
			colClassName: 'nhrotm-col-owner',
			render: ( r ) =>
				KIND[ r.kind ] && KIND[ r.kind ].label ? (
					<span
						className={
							'nhrotm-badge nhrotm-badge--' + KIND[ r.kind ].tone
						}
						title={ r.owner }
					>
						{ KIND[ r.kind ].label }
					</span>
				) : (
					r.owner
				),
		},
		{
			key: 'rows',
			label: __( 'Rows', 'nhrrob-options-table-manager' ),
			sortable: true,
			colClassName: 'nhrotm-col-count',
			render: ( r ) => r.rows.toLocaleString(),
		},
		{
			key: 'size',
			label: __( 'Size', 'nhrrob-options-table-manager' ),
			sortable: true,
			colClassName: 'nhrotm-col-size',
			render: ( r ) => formatBytes( r.size ),
		},
		{
			key: 'overhead',
			label: __( 'Overhead', 'nhrrob-options-table-manager' ),
			sortable: true,
			colClassName: 'nhrotm-col-badge',
			render: ( r ) =>
				r.overhead > 0 ? (
					<span className="nhrotm-badge nhrotm-badge--warning">
						{ formatBytes( r.overhead ) }
					</span>
				) : (
					<span className="nhrotm-muted">—</span>
				),
		},
		{
			key: 'actions',
			label: __( 'Actions', 'nhrrob-options-table-manager' ),
			align: 'right',
			colClassName: 'nhrotm-col-act',
			render: ( r ) => (
				<div className="nhrotm-grid__actions">
					{ ! r.removable &&
						r.overhead <= 0 &&
						r.engine === 'InnoDB' && (
							<span className="nhrotm-muted">
								{ __(
									'Nothing to do',
									'nhrrob-options-table-manager'
								) }
							</span>
						) }
					{ ( r.overhead > 0 || r.engine !== 'InnoDB' ) && (
						<button
							type="button"
							className="nhrotm-iconbtn"
							disabled={ busy }
							onClick={ () => run( r, 'optimize' ) }
						>
							<Icon name="optimize" size={ 14 } />
							{ __( 'Optimize', 'nhrrob-options-table-manager' ) }
						</button>
					) }
					{ r.engine === 'MyISAM' && (
						<>
							<button
								type="button"
								className="nhrotm-iconbtn"
								disabled={ busy }
								onClick={ () => run( r, 'repair' ) }
							>
								<Icon name="tools" size={ 14 } />
								{ __(
									'Repair',
									'nhrrob-options-table-manager'
								) }
							</button>
							<button
								type="button"
								className="nhrotm-iconbtn"
								disabled={ busy }
								onClick={ () => convert( r ) }
							>
								<Icon name="refresh" size={ 14 } />
								{ __(
									'To InnoDB',
									'nhrrob-options-table-manager'
								) }
							</button>
						</>
					) }
					{ r.removable && (
						<>
							<button
								type="button"
								className="nhrotm-iconbtn nhrotm-iconbtn--danger"
								disabled={ busy }
								onClick={ () =>
									setDanger( {
										row: r,
										action: 'empty',
										typed: '',
									} )
								}
							>
								<Icon name="close" size={ 14 } />
								{ __(
									'Empty',
									'nhrrob-options-table-manager'
								) }
							</button>
							<button
								type="button"
								className="nhrotm-iconbtn nhrotm-iconbtn--danger"
								disabled={ busy }
								onClick={ () =>
									setDanger( {
										row: r,
										action: 'drop',
										typed: '',
									} )
								}
							>
								<Icon name="trash" size={ 14 } />
								{ __( 'Drop', 'nhrrob-options-table-manager' ) }
							</button>
						</>
					) }
				</div>
			),
		},
	];

	return (
		<Panel
			anchor="tables"
			title={ __( 'Tables', 'nhrrob-options-table-manager' ) }
			meta={
				rows && rows.length > 0
					? formatBytes(
							rows.reduce( ( sum, r ) => sum + r.size, 0 )
					  )
					: null
			}
		>
			<p className="nhrotm-muted">
				{ __(
					'Every database table of this site. Optimize reclaims overhead. Tables that no installed plugin claims are flagged, and only those can be emptied or dropped.',
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
					rowKey={ ( r ) => r.name }
					defaultSort={ { key: 'size', dir: 'desc' } }
					searchKeys={ [ 'name', 'owner', 'engine' ] }
					searchPlaceholder={ __(
						'Search table, owner or engine…',
						'nhrrob-options-table-manager'
					) }
					filters={ [
						{
							key: 'kind',
							label: __(
								'Filter by owner type',
								'nhrrob-options-table-manager'
							),
							options: [
								{
									value: '',
									label: __(
										'All tables',
										'nhrrob-options-table-manager'
									),
								},
								{
									value: 'core',
									label: __(
										'WordPress core',
										'nhrrob-options-table-manager'
									),
								},
								{
									value: 'plugin',
									label: __(
										'Installed plugins',
										'nhrrob-options-table-manager'
									),
								},
								{
									value: 'leftover',
									label: __(
										'Plugin removed',
										'nhrrob-options-table-manager'
									),
								},
								{
									value: 'unknown',
									label: __(
										'Unknown owner',
										'nhrrob-options-table-manager'
									),
								},
							],
						},
					] }
					tableClassName="nhrotm-grid--tables"
					pageSize={ 20 }
					emptyMessage={ __(
						'No tables found.',
						'nhrrob-options-table-manager'
					) }
				/>
			) }

			{ danger && (
				<div
					className="nhrotm-modal__overlay"
					role="presentation"
					onMouseDown={ ( e ) => {
						if ( e.target === e.currentTarget ) {
							setDanger( null );
						}
					} }
					onKeyDown={ ( e ) => {
						if ( e.key === 'Escape' ) {
							setDanger( null );
						}
					} }
				>
					<div
						className="nhrotm-modal nhrotm-modal--sm"
						role="dialog"
						aria-modal="true"
					>
						<header className="nhrotm-modal__head">
							<h2>
								{ danger.action === 'drop'
									? __(
											'Drop this table?',
											'nhrrob-options-table-manager'
									  )
									: __(
											'Empty this table?',
											'nhrrob-options-table-manager'
									  ) }
							</h2>
						</header>
						<div className="nhrotm-modal__body">
							<p>
								{ danger.action === 'drop'
									? __(
											'The table and everything in it are deleted permanently. Snapshots do not cover tables, so take a full database backup first.',
											'nhrrob-options-table-manager'
									  )
									: __(
											'Every row is deleted permanently; the empty table stays. Snapshots do not cover tables, so take a full database backup first.',
											'nhrrob-options-table-manager'
									  ) }
							</p>
							<div className="nhrotm-field">
								<label htmlFor="nhrotm-table-confirm">
									{ sprintf(
										/* translators: %s: table name the user must type. */
										__(
											'Type %s to confirm',
											'nhrrob-options-table-manager'
										),
										danger.row.name
									) }
								</label>
								<input
									id="nhrotm-table-confirm"
									type="text"
									autoComplete="off"
									value={ danger.typed }
									onChange={ ( e ) =>
										setDanger( {
											...danger,
											typed: e.target.value,
										} )
									}
								/>
							</div>
						</div>
						<footer className="nhrotm-modal__foot">
							<button
								type="button"
								className="nhrotm-btn nhrotm-btn--soft"
								onClick={ () => setDanger( null ) }
							>
								{ __(
									'Cancel',
									'nhrrob-options-table-manager'
								) }
							</button>
							<button
								type="button"
								className="nhrotm-btn nhrotm-btn--primary"
								disabled={
									busy || danger.typed !== danger.row.name
								}
								onClick={ () => {
									const { row, action, typed } = danger;
									setDanger( null );
									run( row, action, typed );
								} }
							>
								{ danger.action === 'drop'
									? __(
											'Drop table',
											'nhrrob-options-table-manager'
									  )
									: __(
											'Empty table',
											'nhrrob-options-table-manager'
									  ) }
							</button>
						</footer>
					</div>
				</div>
			) }
		</Panel>
	);
}
