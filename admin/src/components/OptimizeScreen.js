/**
 * Optimize — autoload health, usage tracker, orphan scanner, cleanup.
 * Wired to nhrotm/v1/optimize (+ action endpoints).
 */
/* eslint-disable no-alert -- native alert used intentionally for lightweight error UX. */
import { useEffect, useState, useCallback } from '@wordpress/element';
import { __, sprintf, _n } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

import DataTable from './DataTable';
import Icon from './Icon';
import JumpNav from './JumpNav';
import Panel from './Panel';
import ProTag from './ProTag';
import ScreenHeader from './ScreenHeader';
import { useConfirm } from './ConfirmProvider';
import { useToast } from './ToastProvider';

// One shared badge for every "what would fixing this whole section be worth"
// number — Autoload health, Orphan scanner, and Cleanup each pass their own
// joint gain in here so the three panels render this identically instead of
// three hand-copied variants drifting apart (own wording, own placement).
function ScoreGainMeta( { gain, title } ) {
	if ( ! ( gain > 0 ) ) {
		return null;
	}
	return (
		<span className="nhrotm-grid__gain" title={ title }>
			{ sprintf(
				/* translators: %s: potential health-score points. */
				__(
					'up to +%s health-score points',
					'nhrrob-options-table-manager'
				),
				gain
			) }
		</span>
	);
}

export default function OptimizeScreen( { boot, onNavigate } ) {
	const confirm = useConfirm();
	const toast = useToast();
	const hasPro = !! ( boot && boot.hasPro );
	const proAvailable = !! ( boot && boot.proAvailable );
	const [ data, setData ] = useState( null );
	const [ status, setStatus ] = useState( 'loading' );
	const [ busy, setBusy ] = useState( false );

	const load = useCallback( () => {
		apiFetch( { path: 'nhrotm/v1/optimize' } )
			.then( ( res ) => {
				setData( res.data );
				setStatus( 'ready' );
			} )
			.catch( () => setStatus( 'error' ) );
	}, [] );

	useEffect( () => {
		load();
	}, [ load ] );

	const action = ( path, body, successMessage ) => {
		setBusy( true );
		return apiFetch( {
			path: 'nhrotm/v1/optimize/' + path,
			method: 'POST',
			data: body || {},
		} )
			.then( () => {
				load();
				if ( successMessage ) {
					toast( successMessage );
				}
			} )
			.catch( ( e ) =>
				window.alert(
					( e && e.message ) ||
						__( 'Action failed.', 'nhrrob-options-table-manager' )
				)
			)
			.finally( () => setBusy( false ) );
	};

	if ( status === 'loading' ) {
		return (
			<Panel title={ __( 'Optimize', 'nhrrob-options-table-manager' ) }>
				<p className="nhrotm-muted">
					{ __( 'Loading…', 'nhrrob-options-table-manager' ) }
				</p>
			</Panel>
		);
	}
	if ( status === 'error' || ! data ) {
		return (
			<Panel title={ __( 'Optimize', 'nhrrob-options-table-manager' ) }>
				<p className="nhrotm-error">
					{ __( 'Could not load.', 'nhrrob-options-table-manager' ) }
				</p>
			</Panel>
		);
	}

	const USAGE_BADGE = {
		used: 'success',
		unused: 'danger',
		untracked: 'muted',
	};
	// "Used" carries the real per-option hit count (e.g. "12 usages") instead
	// of a flat label — a boolean "Used" told you an option wasn't safe to
	// disable but not by how much, which made two very different options
	// (read once vs. read on every load) look identical.
	const usageLabel = ( r ) => {
		if ( r.used === 'used' ) {
			return sprintf(
				/* translators: %s: number of tracked front-end loads the option was read on. */
				_n(
					'%s usage',
					'%s usages',
					r.used_count,
					'nhrrob-options-table-manager'
				),
				r.used_count
			);
		}
		if ( r.used === 'unused' ) {
			return __( 'Unused', 'nhrrob-options-table-manager' );
		}
		return __( 'Untracked', 'nhrrob-options-table-manager' );
	};

	// Every autoloaded option, one row each — this used to be split across
	// "Autoload health" (top 10 by size) and "Usage Tracker" (unused ones) as
	// two separately-scrolled tables, but they're the same underlying data
	// (an autoloaded option, its size) with different filters on top, so a
	// large-and-unused option would show up in both. One table, sortable by
	// either dimension, replaces both.
	const autoloadColumns = [
		{
			key: 'name',
			label: __( 'Name', 'nhrrob-options-table-manager' ),
			sortable: true,
			colClassName: 'nhrotm-col-name nhrotm-col-name--capped',
			cellClassName: 'nhrotm-grid__name',
		},
		{
			key: 'owner',
			label: __( 'Owner', 'nhrrob-options-table-manager' ),
			sortable: true,
			colClassName: 'nhrotm-col-owner',
			render: ( r ) =>
				r.owner === 'Unknown' ? (
					<span className="nhrotm-muted">{ r.owner }</span>
				) : (
					r.owner
				),
		},
		{
			key: 'used',
			label: __( 'Usage', 'nhrrob-options-table-manager' ),
			sortable: true,
			// Plain `r.used` sorts alphabetically on the status string
			// ("unused" < "untracked" < "used"), which isn't a usage
			// ordering at all, and it leaves every "used" row tied on that
			// string so they never sort by how much they're actually used.
			// Rank untracked (unknown) below unused (confirmed zero), then
			// order "used" rows by their real hit count.
			sortValue: ( r ) => {
				if ( r.used === 'used' ) {
					return r.used_count;
				}
				return r.used === 'unused' ? -1 : -2;
			},
			colClassName: 'nhrotm-col-usage',
			render: ( r ) => (
				<span
					className={
						'nhrotm-badge nhrotm-badge--dot nhrotm-badge--' +
						USAGE_BADGE[ r.used ]
					}
				>
					{ usageLabel( r ) }
				</span>
			),
		},
		{
			// Right-aligned like Actions, so a numeric column no longer sits
			// hard against a left-aligned text column (Owner) — the two
			// right-aligned columns now read as one contiguous block instead
			// of the numbers looking jammed into whatever followed them.
			key: 'size',
			label: __( 'Size', 'nhrrob-options-table-manager' ),
			align: 'right',
			sortable: true,
			sortValue: ( r ) => r.size_bytes,
			colClassName: 'nhrotm-col-size',
			cellClassName: 'nhrotm-grid__size',
		},
		{
			key: 'actions',
			label: __( 'Actions', 'nhrrob-options-table-manager' ),
			align: 'right',
			colClassName: 'nhrotm-col-act-sm',
			render: ( r ) =>
				r.protected ? (
					<span
						className="nhrotm-muted nhrotm-grid__protected"
						title={ __(
							'WordPress core relies on this option being autoloaded — it cannot be changed here.',
							'nhrrob-options-table-manager'
						) }
					>
						<Icon name="lock" size={ 14 } />
						{ __( 'Protected', 'nhrrob-options-table-manager' ) }
					</span>
				) : (
					<div className="nhrotm-grid__actions">
						<button
							type="button"
							className="nhrotm-iconbtn"
							disabled={ busy }
							title={ __(
								'Safe and reversible: the option still works exactly the same, it just stops loading on every page request and is fetched only when something actually asks for it. Re-enable any time from Browse → edit the option.',
								'nhrrob-options-table-manager'
							) }
							onClick={ () =>
								action(
									'disable-autoload',
									{ option: r.name },
									sprintf(
										/* translators: %s: option name. */
										__(
											'Autoload disabled for "%s" successfully.',
											'nhrrob-options-table-manager'
										),
										r.name
									)
								)
							}
						>
							<Icon name="optimize" size={ 14 } />
							{ __(
								'Disable autoload',
								'nhrrob-options-table-manager'
							) }
						</button>
					</div>
				),
		},
	];

	const bulkDisableAutoload = ( rows, clearSelection ) => {
		const eligible = rows.filter( ( r ) => ! r.protected );
		const skipped = rows.length - eligible.length;
		setBusy( true );
		Promise.allSettled(
			eligible.map( ( r ) =>
				apiFetch( {
					path: 'nhrotm/v1/optimize/disable-autoload',
					method: 'POST',
					data: { option: r.name },
				} )
			)
		)
			.then( ( results ) => {
				const succeeded = results.filter(
					( res ) => res.status === 'fulfilled'
				).length;
				clearSelection();
				load();
				toast(
					sprintf(
						/* translators: 1: number disabled, 2: number skipped as protected. */
						__(
							'Autoload disabled for %1$s option(s) successfully.%2$s',
							'nhrrob-options-table-manager'
						),
						succeeded,
						skipped > 0
							? ' ' +
									sprintf(
										/* translators: %s: number of protected options skipped. */
										_n(
											'%s protected option was skipped.',
											'%s protected options were skipped.',
											skipped,
											'nhrrob-options-table-manager'
										),
										skipped
									)
							: ''
					)
				);
			} )
			.finally( () => setBusy( false ) );
	};

	const orphanColumns = [
		{
			key: 'prefix',
			label: __( 'Prefix', 'nhrrob-options-table-manager' ),
			sortable: true,
			colClassName: 'nhrotm-col-name nhrotm-col-name--capped',
			cellClassName: 'nhrotm-grid__name',
		},
		{
			key: 'source',
			label: __( 'Source', 'nhrrob-options-table-manager' ),
			colClassName: 'nhrotm-col-badge-wide',
			render: ( r ) =>
				r.source ? (
					<span className="nhrotm-badge nhrotm-badge--muted">
						{ r.source }
					</span>
				) : (
					<span className="nhrotm-muted">—</span>
				),
		},
		{
			key: 'risk',
			label: __( 'Risk', 'nhrrob-options-table-manager' ),
			sortable: true,
			colClassName: 'nhrotm-col-badge',
			render: ( r ) =>
				r.risk ? (
					<span
						className={
							'nhrotm-badge ' +
							( r.risk === 'High'
								? 'nhrotm-badge--danger'
								: 'nhrotm-badge--warning' )
						}
						title={
							r.risk === 'High'
								? __(
										"Source unrecognized — this prefix doesn't match any plugin or theme we know of, so we can't confirm it's genuinely unused. Check the Source column and search the prefix before deleting.",
										'nhrrob-options-table-manager'
								  )
								: __(
										"Recognized as belonging to a known plugin that isn't currently installed — a more confident leftover, but still worth a quick check.",
										'nhrrob-options-table-manager'
								  )
						}
					>
						{ r.risk }
					</span>
				) : (
					<span className="nhrotm-muted">—</span>
				),
		},
		{
			// Right-aligned like Actions, so the numeric column sits next to
			// its fellow right-aligned column instead of jammed against
			// Source's left-aligned badge.
			key: 'count',
			label: __( 'Options', 'nhrrob-options-table-manager' ),
			align: 'right',
			sortable: true,
			colClassName: 'nhrotm-col-size',
			cellClassName: 'nhrotm-grid__size',
		},
		{
			key: 'actions',
			label: __( 'Actions', 'nhrrob-options-table-manager' ),
			align: 'right',
			colClassName: 'nhrotm-col-act-sm',
			render: ( r ) => (
				<div className="nhrotm-grid__actions">
					<button
						type="button"
						className="nhrotm-iconbtn nhrotm-iconbtn--danger"
						disabled={ busy }
						onClick={ async () => {
							const ok = await confirm(
								sprintf(
									/* translators: %s: option prefix. */
									__(
										'Delete all options starting with "%s"?',
										'nhrrob-options-table-manager'
									),
									r.prefix
								),
								{
									description: __(
										'This action cannot be undone.',
										'nhrrob-options-table-manager'
									),
									confirmLabel: __(
										'Delete',
										'nhrrob-options-table-manager'
									),
								}
							);
							if ( ok ) {
								action(
									'delete-orphans',
									{ prefix: r.prefix },
									sprintf(
										/* translators: %s: option prefix. */
										__(
											'Options starting with "%s" deleted successfully.',
											'nhrrob-options-table-manager'
										),
										r.prefix
									)
								);
							}
						} }
					>
						<Icon name="trash" size={ 14 } />
						{ __( 'Delete', 'nhrrob-options-table-manager' ) }
					</button>
				</div>
			),
		},
	];

	const bulkDeleteOrphans = ( rows, clearSelection ) => {
		confirm(
			sprintf(
				/* translators: %s: number of selected orphan groups. */
				_n(
					'Delete %s selected group?',
					'Delete %s selected groups?',
					rows.length,
					'nhrrob-options-table-manager'
				),
				rows.length
			),
			{
				description: __(
					'This action cannot be undone.',
					'nhrrob-options-table-manager'
				),
				confirmLabel: __( 'Delete', 'nhrrob-options-table-manager' ),
			}
		).then( ( ok ) => {
			if ( ! ok ) {
				return;
			}
			setBusy( true );
			Promise.all(
				rows.map( ( r ) =>
					apiFetch( {
						path: 'nhrotm/v1/optimize/delete-orphans',
						method: 'POST',
						data: { prefix: r.prefix },
					} )
				)
			)
				.then( () => {
					clearSelection();
					load();
					toast(
						sprintf(
							/* translators: %s: number of deleted orphan groups. */
							_n(
								'%s group deleted successfully.',
								'%s groups deleted successfully.',
								rows.length,
								'nhrrob-options-table-manager'
							),
							rows.length
						)
					);
				} )
				.catch( () =>
					window.alert(
						__(
							'Bulk delete failed.',
							'nhrrob-options-table-manager'
						)
					)
				)
				.finally( () => setBusy( false ) );
		} );
	};

	return (
		<div className="nhrotm-optimize">
			<ScreenHeader
				title={ __( 'Optimize', 'nhrrob-options-table-manager' ) }
				lede={ sprintf(
					/* translators: %s: current health score, 0-100. */
					__(
						'The single home for autoload, usage tracking, orphan scanning and cleanup. Current health score: %s/100 — this updates live as you clean things up below.',
						'nhrrob-options-table-manager'
					),
					data.score
				) }
			/>
			<JumpNav
				items={ [
					{
						id: 'autoload',
						label: __( 'Autoload', 'nhrrob-options-table-manager' ),
					},
					{
						id: 'orphans',
						label: __( 'Orphans', 'nhrrob-options-table-manager' ),
					},
					{
						id: 'cleanup',
						label: __( 'Cleanup', 'nhrrob-options-table-manager' ),
					},
				] }
			/>
			<Panel
				anchor="autoload"
				title={ sprintf(
					/* translators: %s: total autoloaded size. */
					__(
						'Autoload health — %s',
						'nhrrob-options-table-manager'
					),
					data.autoload_total
				) }
				meta={
					<ScoreGainMeta
						gain={ data.autoload_total_gain }
						title={ __(
							'Health-score points available if every actionable row here is disabled',
							'nhrrob-options-table-manager'
						) }
					/>
				}
			>
				<p className="nhrotm-muted">
					{ __(
						'Options marked to autoload load into memory on every single page request, whether that page needs them or not. Keep this list as small as possible.',
						'nhrrob-options-table-manager'
					) }
				</p>
				<p className="nhrotm-muted">
					{ data.usage_tracking
						? sprintf(
								/* translators: 1: since date, 2: sampled options, 3: sampled front-end loads. */
								__(
									'Tracking since %1$s · %2$s options seen across %3$s front-end loads — the Usage column below shows how many of those loads actually read each option.',
									'nhrrob-options-table-manager'
								),
								data.usage_since || '—',
								data.usage_seen,
								data.usage_loads
						  )
						: __(
								'Usage tracking is off, so every row shows "Untracked" below. Enable it in Settings to find genuinely unused options.',
								'nhrrob-options-table-manager'
						  ) }
				</p>
				<p className="nhrotm-muted">
					{ data.usage_tracking
						? __(
								'Safe to disable: options marked "Unused" — they still work exactly the same, just fetched on demand instead of preloaded. Leave "Used" options alone, especially high-usage ones; hold off on "Untracked" ones until tracking has run a while, since there’s no evidence yet either way. Filter to "Unused" below to select and disable them in bulk.',
								'nhrrob-options-table-manager'
						  )
						: __(
								'Once tracking is on, options confirmed "Unused" are the safe bulk-disable candidates — use the Usage filter below to select them all at once.',
								'nhrrob-options-table-manager'
						  ) }
				</p>
				<DataTable
					columns={ autoloadColumns }
					rows={ data.autoload }
					rowKey={ ( r ) => r.name }
					defaultSort={ { key: 'size', dir: 'desc' } }
					searchKeys={ [ 'name', 'owner' ] }
					searchPlaceholder={ __(
						'Search name or owner…',
						'nhrrob-options-table-manager'
					) }
					filters={ [
						{
							key: 'used',
							label: __(
								'Filter by usage',
								'nhrrob-options-table-manager'
							),
							options: [
								{
									value: '',
									label: __(
										'All usage',
										'nhrrob-options-table-manager'
									),
								},
								{
									value: 'unused',
									label: __(
										'Unused',
										'nhrrob-options-table-manager'
									),
								},
								{
									value: 'untracked',
									label: __(
										'Untracked',
										'nhrrob-options-table-manager'
									),
								},
								{
									value: 'used',
									label: __(
										'Used',
										'nhrrob-options-table-manager'
									),
								},
							],
						},
					] }
					pageSize={ 20 }
					selectable
					bulkActions={ [
						{
							label: __(
								'Disable autoload for selected',
								'nhrrob-options-table-manager'
							),
							variant: 'soft',
							disabled: busy,
							onClick: bulkDisableAutoload,
						},
					] }
					emptyMessage={ __(
						'No autoloaded options found.',
						'nhrrob-options-table-manager'
					) }
				/>
				{ data.usage_tracking && (
					<div className="nhrotm-actions">
						<button
							type="button"
							className="nhrotm-btn nhrotm-btn--soft nhrotm-btn--sm"
							disabled={ busy }
							onClick={ () =>
								action(
									'reset-usage',
									undefined,
									__(
										'Tracking data reset successfully.',
										'nhrrob-options-table-manager'
									)
								)
							}
						>
							<Icon name="refresh" size={ 13 } />
							{ __(
								'Reset tracking data',
								'nhrrob-options-table-manager'
							) }
						</button>
					</div>
				) }
			</Panel>

			<Panel
				anchor="orphans"
				title={ __( 'Orphan scanner', 'nhrrob-options-table-manager' ) }
				meta={
					<ScoreGainMeta
						gain={ data.orphans_total_gain }
						title={ __(
							'Health-score points available if every orphan group here is deleted',
							'nhrrob-options-table-manager'
						) }
					/>
				}
			>
				<p className="nhrotm-muted">
					{ __(
						"Flags option-name prefixes that don't match any installed plugin or theme folder — usually leftovers from something you've removed. Occasional false positives are possible; check a prefix before deleting it.",
						'nhrrob-options-table-manager'
					) }
				</p>
				<DataTable
					columns={ orphanColumns }
					rows={ data.orphans }
					rowKey={ ( r ) => r.prefix }
					defaultSort={ { key: 'count', dir: 'desc' } }
					searchKeys={ [ 'prefix', 'source' ] }
					searchPlaceholder={ __(
						'Search prefix or source…',
						'nhrrob-options-table-manager'
					) }
					pageSize={ 20 }
					selectable
					bulkActions={ [
						{
							label: __(
								'Delete selected',
								'nhrrob-options-table-manager'
							),
							variant: 'danger',
							disabled: busy,
							onClick: bulkDeleteOrphans,
						},
					] }
					emptyMessage={ __(
						'No orphaned option groups detected.',
						'nhrrob-options-table-manager'
					) }
				/>
			</Panel>

			<Panel
				anchor="cleanup"
				title={ __( 'Cleanup', 'nhrrob-options-table-manager' ) }
				meta={
					<ScoreGainMeta
						gain={ data.cleanup_score_gain }
						title={ __(
							'Health-score points available if all expired transients are deleted',
							'nhrrob-options-table-manager'
						) }
					/>
				}
			>
				<p className="nhrotm-muted">
					{ __(
						'Transients are a temporary cache. WordPress does not delete expired ones on its own — this does.',
						'nhrrob-options-table-manager'
					) }
				</p>
				<p className="nhrotm-muted">
					{ data.expired_transients > 0 ? (
						<button
							type="button"
							className="nhrotm-linkbtn"
							onClick={ () =>
								onNavigate( 'browse', undefined, {
									type: 'transients',
									status: 'expired',
								} )
							}
						>
							{ sprintf(
								/* translators: %s: expired transient count. */
								_n(
									'%s expired transient.',
									'%s expired transients.',
									data.expired_transients,
									'nhrrob-options-table-manager'
								),
								data.expired_transients
							) }
						</button>
					) : (
						__(
							'0 expired transients.',
							'nhrrob-options-table-manager'
						)
					) }
				</p>
				<div className="nhrotm-actions">
					<button
						type="button"
						className="nhrotm-btn nhrotm-btn--primary"
						disabled={ busy || data.expired_transients === 0 }
						onClick={ () =>
							action(
								'clean-transients',
								{ scope: 'expired' },
								__(
									'Expired transients deleted successfully.',
									'nhrrob-options-table-manager'
								)
							)
						}
					>
						{ __(
							'Delete expired',
							'nhrrob-options-table-manager'
						) }
					</button>
				</div>
				{ proAvailable && ! hasPro && (
					<div className="nhrotm-actions">
						<span className="nhrotm-hint">
							{ __(
								'Custom hourly→monthly scheduling',
								'nhrrob-options-table-manager'
							) }
						</span>
						<ProTag
							hasPro={ hasPro }
							proAvailable={ proAvailable }
							onNavigate={ onNavigate }
						/>
					</div>
				) }
			</Panel>
		</div>
	);
}
