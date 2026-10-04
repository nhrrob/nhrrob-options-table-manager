/**
 * Cleanup — revisions, drafts, trash, spam, orphaned and duplicate meta,
 * caches and Action Scheduler leftovers. Wired to nhrotm/v1/cleanup.
 *
 * Every type shows a live count, a Preview of exactly what would be deleted,
 * a "keep the last N days" rule (dated types only) and an optional schedule.
 * The same N-days value drives both a manual Clean and the schedule.
 */
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

import Icon from './Icon';
import Panel from './Panel';
import ScreenHeader from './ScreenHeader';
import { useConfirm } from './ConfirmProvider';
import { useToast } from './ToastProvider';

const GROUPS = [
	{
		id: 'posts',
		title: __( 'Posts', 'nhrrob-options-table-manager' ),
	},
	{
		id: 'comments',
		title: __( 'Comments', 'nhrrob-options-table-manager' ),
	},
	{
		id: 'meta',
		title: __( 'Meta and relationships', 'nhrrob-options-table-manager' ),
	},
	{
		id: 'cache',
		title: __( 'Caches and logs', 'nhrrob-options-table-manager' ),
	},
];

const FREQUENCY_LABELS = {
	'': __( 'Off', 'nhrrob-options-table-manager' ),
	hourly: __( 'Hourly', 'nhrrob-options-table-manager' ),
	twicedaily: __( 'Twice daily', 'nhrrob-options-table-manager' ),
	daily: __( 'Daily', 'nhrrob-options-table-manager' ),
	weekly: __( 'Weekly', 'nhrrob-options-table-manager' ),
	nhrotm_monthly: __( 'Monthly', 'nhrrob-options-table-manager' ),
};

// One clean request runs for a bounded time server-side; keep calling until
// nothing is left, with a ceiling so a row that refuses to delete can't loop.
const MAX_ROUNDS = 40;

export default function CleanupScreen() {
	const confirm = useConfirm();
	const toast = useToast();
	const [ items, setItems ] = useState( null );
	const [ frequencies, setFrequencies ] = useState( [] );
	const [ status, setStatus ] = useState( 'loading' );
	const [ busy, setBusy ] = useState( '' );
	const [ dirty, setDirty ] = useState( false );
	const [ preview, setPreview ] = useState( null );

	const load = useCallback( () => {
		apiFetch( { path: 'nhrotm/v1/cleanup' } )
			.then( ( res ) => {
				setItems( res.data.items );
				setFrequencies( res.data.frequencies );
				setStatus( 'ready' );
			} )
			.catch( () => setStatus( 'error' ) );
	}, [] );

	useEffect( () => {
		load();
	}, [ load ] );

	const patch = ( id, changes ) => {
		setItems(
			items.map( ( it ) => ( it.id === id ? { ...it, ...changes } : it ) )
		);
		setDirty( true );
	};

	const openPreview = ( item ) => {
		setPreview( { item, data: null } );
		apiFetch( {
			path: `nhrotm/v1/cleanup/preview?type=${ item.id }&days=${ item.days }`,
		} )
			.then( ( res ) => setPreview( { item, data: res.data } ) )
			.catch( () => setPreview( null ) );
	};

	const clean = async ( item ) => {
		const data = await apiFetch( {
			path: `nhrotm/v1/cleanup/preview?type=${ item.id }&days=${ item.days }`,
		} )
			.then( ( res ) => res.data )
			.catch( () => null );
		if ( ! data || data.total === 0 ) {
			toast(
				__(
					'Nothing matches this rule right now.',
					'nhrrob-options-table-manager'
				)
			);
			return;
		}
		if (
			! ( await confirm(
				sprintf(
					/* translators: 1: number of rows, 2: what is being cleaned, e.g. "Post revisions". */
					_n(
						'Delete %1$s row: %2$s?',
						'Delete %1$s rows: %2$s?',
						data.total,
						'nhrrob-options-table-manager'
					),
					data.total.toLocaleString(),
					item.label
				),
				{
					description: __(
						'Snapshots only cover the options table, so this cannot be undone from here. Use Preview first if you are unsure.',
						'nhrrob-options-table-manager'
					),
					confirmLabel: __(
						'Delete',
						'nhrrob-options-table-manager'
					),
				}
			) )
		) {
			return;
		}

		setBusy( item.id );
		let deleted = 0;
		let failed = false;
		for ( let round = 0; round < MAX_ROUNDS; round++ ) {
			// eslint-disable-next-line no-await-in-loop -- sequential by design: each call continues where the last stopped
			const res = await apiFetch( {
				path: 'nhrotm/v1/cleanup/run',
				method: 'POST',
				data: { type: item.id, days: item.days },
			} ).catch( () => null );
			if ( ! res ) {
				failed = true;
				break;
			}
			deleted += res.data.deleted;
			if ( res.data.remaining === 0 || res.data.deleted === 0 ) {
				break;
			}
		}
		setBusy( '' );
		load();
		if ( failed && deleted === 0 ) {
			toast(
				__( 'Cleanup failed.', 'nhrrob-options-table-manager' ),
				'error'
			);
			return;
		}
		toast(
			sprintf(
				/* translators: %s: number of rows deleted. */
				_n(
					'%s row cleaned successfully.',
					'%s rows cleaned successfully.',
					deleted,
					'nhrrob-options-table-manager'
				),
				deleted.toLocaleString()
			)
		);
	};

	const saveSchedules = () => {
		const schedules = {};
		items.forEach( ( it ) => {
			if ( it.schedulable && it.frequency ) {
				schedules[ it.id ] = {
					frequency: it.frequency,
					days: it.days,
				};
			}
		} );
		setBusy( 'schedules' );
		apiFetch( {
			path: 'nhrotm/v1/cleanup/schedules',
			method: 'POST',
			data: { schedules },
		} )
			.then( () => {
				setDirty( false );
				toast(
					__(
						'Schedules saved successfully.',
						'nhrrob-options-table-manager'
					)
				);
			} )
			.catch( () =>
				toast(
					__(
						'Could not save the schedules.',
						'nhrrob-options-table-manager'
					),
					'error'
				)
			)
			.finally( () => setBusy( '' ) );
	};

	if ( status !== 'ready' ) {
		return (
			<Panel title={ __( 'Cleanup', 'nhrrob-options-table-manager' ) }>
				<p
					className={
						status === 'error' ? 'nhrotm-error' : 'nhrotm-muted'
					}
				>
					{ status === 'error'
						? __(
								'Could not load the cleanup data.',
								'nhrrob-options-table-manager'
						  )
						: __( 'Loading…', 'nhrrob-options-table-manager' ) }
				</p>
			</Panel>
		);
	}

	const total = items.reduce( ( sum, it ) => sum + it.count, 0 );

	return (
		<div className="nhrotm-cleanup-screen">
			<ScreenHeader
				title={ __( 'Cleanup', 'nhrrob-options-table-manager' ) }
				lede={ sprintf(
					/* translators: %s: number of rows that can be cleaned. */
					_n(
						'Remove data your site no longer needs. %s row can be cleaned right now — preview anything before you delete it.',
						'Remove data your site no longer needs. %s rows can be cleaned right now — preview anything before you delete it.',
						total,
						'nhrrob-options-table-manager'
					),
					total.toLocaleString()
				) }
			/>
			{ GROUPS.map( ( group ) => {
				const rows = items.filter( ( it ) => it.group === group.id );
				if ( rows.length === 0 ) {
					return null;
				}
				return (
					<Panel
						key={ group.id }
						anchor={ group.id }
						title={ group.title }
					>
						<div className="nhrotm-grid__scroll">
							<table className="nhrotm-grid">
								<colgroup>
									<col />
									<col className="nhrotm-col-count" />
									<col className="nhrotm-col-owner" />
									<col className="nhrotm-col-owner" />
									<col className="nhrotm-col-act" />
								</colgroup>
								<thead>
									<tr>
										<th>
											{ __(
												'What',
												'nhrrob-options-table-manager'
											) }
										</th>
										<th>
											{ __(
												'Rows',
												'nhrrob-options-table-manager'
											) }
										</th>
										<th>
											{ __(
												'Keep the last',
												'nhrrob-options-table-manager'
											) }
										</th>
										<th>
											{ __(
												'Schedule',
												'nhrrob-options-table-manager'
											) }
										</th>
										<th style={ { textAlign: 'right' } }>
											{ __(
												'Actions',
												'nhrrob-options-table-manager'
											) }
										</th>
									</tr>
								</thead>
								<tbody>
									{ rows.map( ( it ) => (
										<tr key={ it.id }>
											<td>
												{ it.label }
												{ it.note && (
													<span className="nhrotm-cleanup__note">
														{ it.note }
													</span>
												) }
											</td>
											<td>
												<span
													className={
														'nhrotm-badge ' +
														( it.count > 0
															? 'nhrotm-badge--warning'
															: 'nhrotm-badge--muted' )
													}
												>
													{ it.count.toLocaleString() }
												</span>
											</td>
											<td>
												{ it.dated ? (
													<span className="nhrotm-cleanup__days">
														<input
															type="number"
															min="0"
															max="3650"
															className="nhrotm-pager__select"
															aria-label={ sprintf(
																/* translators: %s: cleanup type, e.g. "Post revisions". */
																__(
																	'Days to keep: %s',
																	'nhrrob-options-table-manager'
																),
																it.label
															) }
															value={ it.days }
															onChange={ ( e ) =>
																patch( it.id, {
																	days: Math.max(
																		0,
																		parseInt(
																			e
																				.target
																				.value,
																			10
																		) || 0
																	),
																} )
															}
														/>
														{ __(
															'days',
															'nhrrob-options-table-manager'
														) }
													</span>
												) : (
													<span className="nhrotm-muted">
														—
													</span>
												) }
											</td>
											<td>
												{ it.schedulable ? (
													<select
														className="nhrotm-pager__select"
														aria-label={ sprintf(
															/* translators: %s: cleanup type, e.g. "Post revisions". */
															__(
																'Schedule: %s',
																'nhrrob-options-table-manager'
															),
															it.label
														) }
														value={ it.frequency }
														onChange={ ( e ) =>
															patch( it.id, {
																frequency:
																	e.target
																		.value,
															} )
														}
													>
														{ [
															'',
															...frequencies,
														].map( ( f ) => (
															<option
																key={ f }
																value={ f }
															>
																{ FREQUENCY_LABELS[
																	f
																] || f }
															</option>
														) ) }
													</select>
												) : (
													<span className="nhrotm-muted">
														{ __(
															'Manual only',
															'nhrrob-options-table-manager'
														) }
													</span>
												) }
											</td>
											<td>
												<div className="nhrotm-grid__actions">
													<button
														type="button"
														className="nhrotm-iconbtn"
														disabled={
															it.count === 0
														}
														onClick={ () =>
															openPreview( it )
														}
													>
														<Icon
															name="search"
															size={ 14 }
														/>
														{ __(
															'Preview',
															'nhrrob-options-table-manager'
														) }
													</button>
													<button
														type="button"
														className="nhrotm-iconbtn nhrotm-iconbtn--danger"
														disabled={
															it.count === 0 ||
															busy !== ''
														}
														onClick={ () =>
															clean( it )
														}
													>
														<Icon
															name="trash"
															size={ 14 }
														/>
														{ busy === it.id
															? __(
																	'Cleaning…',
																	'nhrrob-options-table-manager'
															  )
															: __(
																	'Clean',
																	'nhrrob-options-table-manager'
															  ) }
													</button>
												</div>
											</td>
										</tr>
									) ) }
								</tbody>
							</table>
						</div>
					</Panel>
				);
			} ) }
			<div className="nhrotm-actions">
				<button
					type="button"
					className="nhrotm-btn nhrotm-btn--primary"
					disabled={ ! dirty || busy !== '' }
					onClick={ saveSchedules }
				>
					{ __( 'Save schedules', 'nhrrob-options-table-manager' ) }
				</button>
				<span className="nhrotm-hint">
					{ __(
						'A schedule deletes without asking, using the "keep the last" rule on its row. 0 days means everything that matches.',
						'nhrrob-options-table-manager'
					) }
				</span>
			</div>

			{ preview && (
				<div
					className="nhrotm-modal__overlay"
					role="presentation"
					onMouseDown={ ( e ) => {
						if ( e.target === e.currentTarget ) {
							setPreview( null );
						}
					} }
					onKeyDown={ ( e ) => {
						if ( e.key === 'Escape' ) {
							setPreview( null );
						}
					} }
				>
					<div
						className="nhrotm-modal nhrotm-modal--wide"
						role="dialog"
						aria-modal="true"
						aria-label={ preview.item.label }
					>
						<header className="nhrotm-modal__head">
							<h2>{ preview.item.label }</h2>
							<button
								type="button"
								className="nhrotm-modal__close"
								aria-label={ __(
									'Close',
									'nhrrob-options-table-manager'
								) }
								onClick={ () => setPreview( null ) }
							>
								×
							</button>
						</header>
						<div className="nhrotm-modal__body">
							{ ! preview.data && (
								<p className="nhrotm-muted">
									{ __(
										'Loading…',
										'nhrrob-options-table-manager'
									) }
								</p>
							) }
							{ preview.data && (
								<>
									<p className="nhrotm-muted">
										{ sprintf(
											/* translators: 1: rows shown, 2: total rows that would be deleted. */
											__(
												'Showing %1$s of %2$s rows that Clean would delete.',
												'nhrrob-options-table-manager'
											),
											preview.data.items.length.toLocaleString(),
											preview.data.total.toLocaleString()
										) }
									</p>
									{ preview.data.items.length > 0 && (
										<div className="nhrotm-grid__scroll">
											<table className="nhrotm-grid">
												<colgroup>
													<col className="nhrotm-col-count" />
													<col />
													<col className="nhrotm-col-date" />
												</colgroup>
												<thead>
													<tr>
														<th>
															{ __(
																'ID',
																'nhrrob-options-table-manager'
															) }
														</th>
														<th>
															{ __(
																'Item',
																'nhrrob-options-table-manager'
															) }
														</th>
														<th>
															{ __(
																'Date',
																'nhrrob-options-table-manager'
															) }
														</th>
													</tr>
												</thead>
												<tbody>
													{ preview.data.items.map(
														( row ) => (
															<tr key={ row.id }>
																<td>
																	{ row.id }
																</td>
																<td
																	title={
																		row.label
																	}
																>
																	<div className="nhrotm-grid__preview">
																		{ row.label ||
																			'—' }
																	</div>
																</td>
																<td className="nhrotm-muted">
																	{ row.date ||
																		'—' }
																</td>
															</tr>
														)
													) }
												</tbody>
											</table>
										</div>
									) }
								</>
							) }
						</div>
						<footer className="nhrotm-modal__foot">
							<button
								type="button"
								className="nhrotm-btn nhrotm-btn--soft"
								onClick={ () => setPreview( null ) }
							>
								{ __(
									'Close',
									'nhrrob-options-table-manager'
								) }
							</button>
						</footer>
					</div>
				</div>
			) }
		</div>
	);
}
