/**
 * Tools — backups, search & replace, export.
 * Wired to nhrotm/v1/tools/*.
 */
/* eslint-disable no-alert -- native alert used intentionally for lightweight error UX. */
import { useEffect, useState, useCallback } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

import JumpNav from './JumpNav';
import Panel from './Panel';
import ProTag from './ProTag';
import ScreenHeader from './ScreenHeader';
import { useConfirm } from './ConfirmProvider';

export default function ToolsScreen( { boot, onNavigate } ) {
	const confirm = useConfirm();
	const hasPro = !! ( boot && boot.hasPro );
	const proAvailable = !! ( boot && boot.proAvailable );
	const [ backups, setBackups ] = useState( [] );
	const [ busy, setBusy ] = useState( false );
	const [ label, setLabel ] = useState( '' );

	const [ search, setSearch ] = useState( '' );
	const [ replace, setReplace ] = useState( '' );
	const [ srResult, setSrResult ] = useState( null );

	const [ importJson, setImportJson ] = useState( '' );
	const [ overwrite, setOverwrite ] = useState( false );
	const [ importResult, setImportResult ] = useState( null );

	const loadBackups = useCallback( () => {
		apiFetch( { path: 'nhrotm/v1/tools/backups' } )
			.then( ( res ) => setBackups( res.data ) )
			.catch( () => {} );
	}, [] );

	useEffect( () => {
		loadBackups();
	}, [ loadBackups ] );

	const createBackup = () => {
		setBusy( true );
		apiFetch( {
			path: 'nhrotm/v1/tools/backups',
			method: 'POST',
			data: { label },
		} )
			.then( ( res ) => {
				setBackups( res.data.backups );
				setLabel( '' );
			} )
			.catch( () =>
				window.alert(
					__( 'Backup failed.', 'nhrrob-options-table-manager' )
				)
			)
			.finally( () => setBusy( false ) );
	};

	const restoreBackup = async ( id ) => {
		if (
			! ( await confirm(
				__( 'Restore this snapshot?', 'nhrrob-options-table-manager' ),
				{
					description: __(
						'Current option values will be overwritten.',
						'nhrrob-options-table-manager'
					),
					confirmLabel: __(
						'Restore',
						'nhrrob-options-table-manager'
					),
				}
			) )
		) {
			return;
		}
		setBusy( true );
		apiFetch( {
			path: `nhrotm/v1/tools/backups/${ id }/restore`,
			method: 'POST',
		} )
			.then( ( res ) =>
				window.alert(
					sprintf(
						/* translators: %s: number of options restored. */
						__(
							'%s options restored.',
							'nhrrob-options-table-manager'
						),
						res.data.restored
					)
				)
			)
			.catch( () =>
				window.alert(
					__( 'Restore failed.', 'nhrrob-options-table-manager' )
				)
			)
			.finally( () => setBusy( false ) );
	};

	const deleteBackup = async ( id ) => {
		if (
			! ( await confirm(
				__( 'Delete this snapshot?', 'nhrrob-options-table-manager' ),
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
			) )
		) {
			return;
		}
		apiFetch( {
			path: `nhrotm/v1/tools/backups/${ id }`,
			method: 'DELETE',
		} )
			.then( () => loadBackups() )
			.catch( () => {} );
	};

	const runSearchReplace = async ( dryRun ) => {
		if ( ! search ) {
			return;
		}
		if (
			! dryRun &&
			! ( await confirm(
				__(
					'Run replace on the database?',
					'nhrrob-options-table-manager'
				),
				{
					description: __(
						'A safety snapshot is taken first.',
						'nhrrob-options-table-manager'
					),
					confirmLabel: __(
						'Replace',
						'nhrrob-options-table-manager'
					),
				}
			) )
		) {
			return;
		}
		setBusy( true );
		apiFetch( {
			path: 'nhrotm/v1/tools/search-replace',
			method: 'POST',
			data: { search, replace, dry_run: dryRun },
		} )
			.then( ( res ) => {
				setSrResult( res.data );
				if ( ! dryRun ) {
					loadBackups();
				}
			} )
			.catch( ( e ) =>
				window.alert(
					( e && e.message ) ||
						__( 'Failed.', 'nhrrob-options-table-manager' )
				)
			)
			.finally( () => setBusy( false ) );
	};

	const exportJson = () => {
		apiFetch( { path: 'nhrotm/v1/tools/export' } )
			.then( ( res ) => {
				const blob = new Blob(
					[ JSON.stringify( res.data, null, 2 ) ],
					{
						type: 'application/json',
					}
				);
				const url = URL.createObjectURL( blob );
				const a = document.createElement( 'a' );
				a.href = url;
				a.download = 'nhrotm-options-export.json';
				a.click();
				URL.revokeObjectURL( url );
			} )
			.catch( () =>
				window.alert(
					__( 'Export failed.', 'nhrrob-options-table-manager' )
				)
			);
	};

	const runImport = async () => {
		if ( ! importJson.trim() ) {
			return;
		}
		if (
			! ( await confirm(
				__( 'Import options?', 'nhrrob-options-table-manager' ),
				{
					description: __(
						'A safety snapshot is taken first.',
						'nhrrob-options-table-manager'
					),
					confirmLabel: __(
						'Import',
						'nhrrob-options-table-manager'
					),
				}
			) )
		) {
			return;
		}
		setBusy( true );
		setImportResult( null );
		apiFetch( {
			path: 'nhrotm/v1/tools/import',
			method: 'POST',
			data: { json: importJson, overwrite },
		} )
			.then( ( res ) => {
				setImportResult( res.data );
				setImportJson( '' );
				loadBackups();
			} )
			.catch( ( e ) =>
				window.alert(
					( e && e.message ) ||
						__( 'Import failed.', 'nhrrob-options-table-manager' )
				)
			)
			.finally( () => setBusy( false ) );
	};

	const onFile = ( e ) => {
		const file = e.target.files && e.target.files[ 0 ];
		if ( ! file ) {
			return;
		}
		const reader = new window.FileReader();
		reader.onload = ( ev ) => setImportJson( String( ev.target.result ) );
		reader.readAsText( file );
	};

	return (
		<div className="nhrotm-tools">
			<ScreenHeader
				title={ __( 'Tools', 'nhrrob-options-table-manager' ) }
				lede={ __(
					'Search & replace, import / export, and backups — each takes a snapshot before it changes anything.',
					'nhrrob-options-table-manager'
				) }
			/>
			<JumpNav
				items={ [
					{
						id: 'backups',
						label: __( 'Backups', 'nhrrob-options-table-manager' ),
					},
					{
						id: 'search-replace',
						label: __(
							'Search & Replace',
							'nhrrob-options-table-manager'
						),
					},
					{
						id: 'export',
						label: __( 'Export', 'nhrrob-options-table-manager' ),
					},
					{
						id: 'import',
						label: __( 'Import', 'nhrrob-options-table-manager' ),
					},
				] }
			/>
			<Panel
				anchor="backups"
				title={ __( 'Backups', 'nhrrob-options-table-manager' ) }
			>
				<div className="nhrotm-actions">
					<input
						type="text"
						className="nhrotm-browse__search"
						placeholder={ __(
							'Snapshot label (optional)',
							'nhrrob-options-table-manager'
						) }
						value={ label }
						onChange={ ( e ) => setLabel( e.target.value ) }
					/>
					<button
						type="button"
						className="nhrotm-btn nhrotm-btn--primary"
						disabled={ busy }
						onClick={ createBackup }
					>
						{ __(
							'Create snapshot',
							'nhrrob-options-table-manager'
						) }
					</button>
				</div>
				{ backups.length === 0 ? (
					<p className="nhrotm-muted">
						{ __(
							'No snapshots yet.',
							'nhrrob-options-table-manager'
						) }
					</p>
				) : (
					<table className="nhrotm-grid">
						<thead>
							<tr>
								<th>
									{ __(
										'Label',
										'nhrrob-options-table-manager'
									) }
								</th>
								<th>
									{ __(
										'Type',
										'nhrrob-options-table-manager'
									) }
								</th>
								<th>
									{ __(
										'Options',
										'nhrrob-options-table-manager'
									) }
								</th>
								<th>
									{ __(
										'Size',
										'nhrrob-options-table-manager'
									) }
								</th>
								<th>
									{ __(
										'Created',
										'nhrrob-options-table-manager'
									) }
								</th>
								<th />
							</tr>
						</thead>
						<tbody>
							{ backups.map( ( b ) => (
								<tr key={ b.id }>
									<td className="nhrotm-grid__name">
										{ b.label }
									</td>
									<td>
										<span className="nhrotm-badge nhrotm-badge--muted">
											{ b.type }
										</span>
									</td>
									<td>{ b.option_count }</td>
									<td>{ b.size_formatted }</td>
									<td>{ b.created_at }</td>
									<td>
										<button
											type="button"
											className="nhrotm-linkbtn"
											disabled={ busy }
											onClick={ () =>
												restoreBackup( b.id )
											}
										>
											{ __(
												'Restore',
												'nhrrob-options-table-manager'
											) }
										</button>{ ' ' }
										<button
											type="button"
											className="nhrotm-linkbtn nhrotm-linkbtn--danger"
											onClick={ () =>
												deleteBackup( b.id )
											}
										>
											{ __(
												'Delete',
												'nhrrob-options-table-manager'
											) }
										</button>
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
				) }
				{ proAvailable && ! hasPro && (
					<div className="nhrotm-actions">
						<span className="nhrotm-hint">
							{ __(
								'Local, last 15 kept. Unlimited + off-site backups & emergency recovery',
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

			<Panel
				anchor="search-replace"
				title={ __(
					'Search & Replace',
					'nhrrob-options-table-manager'
				) }
			>
				<div className="nhrotm-field">
					<input
						type="text"
						className="nhrotm-browse__search"
						placeholder={ __(
							'Search for…',
							'nhrrob-options-table-manager'
						) }
						value={ search }
						onChange={ ( e ) => setSearch( e.target.value ) }
					/>
				</div>
				<div className="nhrotm-field">
					<input
						type="text"
						className="nhrotm-browse__search"
						placeholder={ __(
							'Replace with…',
							'nhrrob-options-table-manager'
						) }
						value={ replace }
						onChange={ ( e ) => setReplace( e.target.value ) }
					/>
				</div>
				<div className="nhrotm-actions">
					<button
						type="button"
						className="nhrotm-btn nhrotm-btn--soft"
						disabled={ busy || ! search }
						onClick={ () => runSearchReplace( true ) }
					>
						{ __( 'Dry run', 'nhrrob-options-table-manager' ) }
					</button>
					<button
						type="button"
						className="nhrotm-btn nhrotm-btn--primary"
						disabled={ busy || ! search }
						onClick={ () => runSearchReplace( false ) }
					>
						{ __( 'Replace', 'nhrrob-options-table-manager' ) }
					</button>
					{ proAvailable && ! hasPro && (
						<>
							<span className="nhrotm-hint">
								{ __(
									'Regex & all-table replacement',
									'nhrrob-options-table-manager'
								) }
							</span>
							<ProTag
								hasPro={ hasPro }
								proAvailable={ proAvailable }
								onNavigate={ onNavigate }
							/>
						</>
					) }
				</div>
				{ srResult && (
					<p
						className={
							srResult.dry_run ? 'nhrotm-notice' : 'nhrotm-notice'
						}
					>
						{ sprintf(
							/* translators: 1: dry-run/applied, 2: options count, 3: occurrences. */
							__(
								'%1$s — %2$s options, %3$s occurrences.',
								'nhrrob-options-table-manager'
							),
							srResult.dry_run
								? __(
										'Dry run',
										'nhrrob-options-table-manager'
								  )
								: __(
										'Applied',
										'nhrrob-options-table-manager'
								  ),
							srResult.total_updated,
							srResult.total_occurrences
						) }
					</p>
				) }
			</Panel>

			<Panel
				anchor="export"
				title={ __( 'Export', 'nhrrob-options-table-manager' ) }
			>
				<p className="nhrotm-muted">
					{ __(
						'Download all options (excluding transients) as JSON.',
						'nhrrob-options-table-manager'
					) }
				</p>
				<button
					type="button"
					className="nhrotm-btn nhrotm-btn--primary"
					onClick={ exportJson }
				>
					{ __( 'Export JSON', 'nhrrob-options-table-manager' ) }
				</button>
			</Panel>

			<Panel
				anchor="import"
				title={ __( 'Import', 'nhrrob-options-table-manager' ) }
			>
				<div className="nhrotm-field">
					<input
						type="file"
						accept="application/json,.json"
						onChange={ onFile }
					/>
				</div>
				<div className="nhrotm-field">
					<textarea
						className="nhrotm-modal__textarea"
						rows="6"
						placeholder={ __(
							'…or paste exported JSON here',
							'nhrrob-options-table-manager'
						) }
						value={ importJson }
						onChange={ ( e ) => setImportJson( e.target.value ) }
					/>
				</div>
				<div className="nhrotm-field">
					<label htmlFor="nhrotm-import-overwrite">
						<input
							id="nhrotm-import-overwrite"
							type="checkbox"
							checked={ overwrite }
							onChange={ ( e ) =>
								setOverwrite( e.target.checked )
							}
						/>
						{ __(
							'Overwrite existing options',
							'nhrrob-options-table-manager'
						) }
					</label>
				</div>
				<button
					type="button"
					className="nhrotm-btn nhrotm-btn--primary"
					disabled={ busy || ! importJson.trim() }
					onClick={ runImport }
				>
					{ __( 'Import', 'nhrrob-options-table-manager' ) }
				</button>
				{ importResult && (
					<p className="nhrotm-notice">
						{ sprintf(
							/* translators: 1: imported count, 2: skipped count. */
							__(
								'Imported %1$s · skipped %2$s.',
								'nhrrob-options-table-manager'
							),
							importResult.imported,
							importResult.skipped
						) }
					</p>
				) }
			</Panel>
		</div>
	);
}
