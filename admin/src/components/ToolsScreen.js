/**
 * Tools — backups, search & replace, export.
 * Wired to nhrotm/v1/tools/*.
 */
/* eslint-disable no-alert -- native alert used intentionally for lightweight error UX. */
import { useEffect, useState, useCallback, useRef } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

import JumpNav from './JumpNav';
import Panel from './Panel';
import ProTag from './ProTag';
import ScreenHeader from './ScreenHeader';
import Icon from './Icon';
import { useConfirm } from './ConfirmProvider';
import { useToast } from './ToastProvider';

export default function ToolsScreen( { boot, onNavigate } ) {
	const confirm = useConfirm();
	const toast = useToast();
	const hasPro = !! ( boot && boot.hasPro );
	const proAvailable = !! ( boot && boot.proAvailable );
	const [ backups, setBackups ] = useState( [] );
	const [ busy, setBusy ] = useState( false );
	const [ label, setLabel ] = useState( '' );

	const [ search, setSearch ] = useState( '' );
	const [ replace, setReplace ] = useState( '' );
	const [ srResult, setSrResult ] = useState( null );

	const [ importJson, setImportJson ] = useState( '' );
	const [ importFileName, setImportFileName ] = useState( '' );
	const [ isDropzoneActive, setIsDropzoneActive ] = useState( false );
	const fileInputRef = useRef( null );
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
				toast(
					__(
						'Snapshot created successfully.',
						'nhrrob-options-table-manager'
					)
				);
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
				toast(
					sprintf(
						/* translators: %s: number of options restored. */
						__(
							'%s options restored successfully.',
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
			.then( () => {
				loadBackups();
				toast(
					__(
						'Snapshot deleted successfully.',
						'nhrrob-options-table-manager'
					)
				);
			} )
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
				setImportFileName( '' );
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

	const readFile = ( file ) => {
		if ( ! file ) {
			return;
		}
		const reader = new window.FileReader();
		reader.onload = ( ev ) => {
			setImportJson( String( ev.target.result ) );
			setImportFileName( file.name );
		};
		reader.readAsText( file );
	};

	const onFile = ( e ) => readFile( e.target.files && e.target.files[ 0 ] );

	const clearImportFile = () => {
		setImportFileName( '' );
		setImportJson( '' );
		if ( fileInputRef.current ) {
			fileInputRef.current.value = '';
		}
	};

	const onDropzoneDrop = ( e ) => {
		e.preventDefault();
		setIsDropzoneActive( false );
		readFile( e.dataTransfer.files && e.dataTransfer.files[ 0 ] );
	};

	const openFilePicker = () => {
		if ( fileInputRef.current ) {
			fileInputRef.current.click();
		}
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
				<p className="nhrotm-muted">
					{ __(
						'A full snapshot of the entire options table, taken before anything risky — restore it in one click if something goes wrong.',
						'nhrrob-options-table-manager'
					) }
				</p>
				<div className="nhrotm-actions">
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
				</div>
				{ backups.length === 0 ? (
					<p className="nhrotm-muted">
						{ __(
							'No snapshots yet.',
							'nhrrob-options-table-manager'
						) }
					</p>
				) : (
					<div className="nhrotm-grid__scroll">
						<table className="nhrotm-grid">
							<colgroup>
								<col className="nhrotm-col-name" />
								<col className="nhrotm-col-badge" />
								<col className="nhrotm-col-count" />
								<col className="nhrotm-col-size" />
								<col className="nhrotm-col-date" />
								<col className="nhrotm-col-act" />
							</colgroup>
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
									<th style={ { textAlign: 'right' } }>
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
									<th style={ { textAlign: 'right' } }>
										{ __(
											'Actions',
											'nhrrob-options-table-manager'
										) }
									</th>
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
										<td className="nhrotm-grid__size">
											{ b.size_formatted }
										</td>
										<td>{ b.created_at }</td>
										<td>
											<div className="nhrotm-grid__actions">
												<button
													type="button"
													className="nhrotm-iconbtn"
													disabled={ busy }
													onClick={ () =>
														restoreBackup( b.id )
													}
												>
													<Icon
														name="refresh"
														size={ 14 }
													/>
													{ __(
														'Restore',
														'nhrrob-options-table-manager'
													) }
												</button>
												<button
													type="button"
													className="nhrotm-iconbtn nhrotm-iconbtn--danger"
													onClick={ () =>
														deleteBackup( b.id )
													}
												>
													<Icon
														name="trash"
														size={ 14 }
													/>
													{ __(
														'Delete',
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
				<p className="nhrotm-muted">
					{ __(
						"Find and replace a string across every option's value — a safety snapshot is taken automatically before anything is written.",
						'nhrrob-options-table-manager'
					) }
				</p>
				<div className="nhrotm-fieldrow">
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
					<>
						<p
							className={
								srResult.dry_run
									? 'nhrotm-muted'
									: 'nhrotm-notice'
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
						{ srResult.details && srResult.details.length > 0 && (
							<div className="nhrotm-grid__wrap">
								<div className="nhrotm-grid__scroll">
									<table className="nhrotm-grid">
										<thead>
											<tr>
												<th>
													{ __(
														'Option',
														'nhrrob-options-table-manager'
													) }
												</th>
												<th
													style={ {
														textAlign: 'right',
													} }
												>
													{ __(
														'Occurrences',
														'nhrrob-options-table-manager'
													) }
												</th>
											</tr>
										</thead>
										<tbody>
											{ srResult.details.map( ( row ) => (
												<tr key={ row.option_name }>
													<td className="nhrotm-grid__name">
														{ row.option_name }
													</td>
													<td className="nhrotm-grid__size">
														{ row.occurrences }
													</td>
												</tr>
											) ) }
										</tbody>
									</table>
								</div>
							</div>
						) }
					</>
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
				<p className="nhrotm-muted">
					{ __(
						'Restore or merge options from a previously exported JSON file, matched by option name.',
						'nhrrob-options-table-manager'
					) }
				</p>
				<div className="nhrotm-field">
					<div
						className={
							'nhrotm-dropzone' +
							( isDropzoneActive ? ' is-active' : '' )
						}
						role="button"
						tabIndex={ 0 }
						onClick={ openFilePicker }
						onKeyDown={ ( e ) => {
							if ( 'Enter' === e.key || ' ' === e.key ) {
								e.preventDefault();
								openFilePicker();
							}
						} }
						onDragOver={ ( e ) => {
							e.preventDefault();
							setIsDropzoneActive( true );
						} }
						onDragLeave={ () => setIsDropzoneActive( false ) }
						onDrop={ onDropzoneDrop }
					>
						<input
							ref={ fileInputRef }
							type="file"
							accept="application/json,.json"
							className="nhrotm-dropzone__input"
							onChange={ onFile }
							tabIndex={ -1 }
						/>
						{ importFileName ? (
							<div className="nhrotm-dropzone__file">
								<Icon name="upload" size={ 18 } />
								<span>{ importFileName }</span>
								<button
									type="button"
									className="nhrotm-dropzone__clear"
									aria-label={ __(
										'Remove file',
										'nhrrob-options-table-manager'
									) }
									onClick={ ( e ) => {
										e.stopPropagation();
										clearImportFile();
									} }
								>
									<Icon name="close" size={ 14 } />
								</button>
							</div>
						) : (
							<div className="nhrotm-dropzone__empty">
								<Icon name="upload" size={ 20 } />
								{ __(
									'Click to upload or drag a JSON file here',
									'nhrrob-options-table-manager'
								) }
							</div>
						) }
					</div>
				</div>
				<div className="nhrotm-dropzone__divider">
					{ __(
						'— or paste JSON —',
						'nhrrob-options-table-manager'
					) }
				</div>
				<div className="nhrotm-field">
					<textarea
						className="nhrotm-modal__textarea"
						rows="6"
						placeholder={ __(
							'Paste exported JSON here',
							'nhrrob-options-table-manager'
						) }
						value={ importJson }
						onChange={ ( e ) => {
							setImportJson( e.target.value );
							setImportFileName( '' );
						} }
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
