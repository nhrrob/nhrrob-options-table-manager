/**
 * Tools — backups, search & replace, export.
 * Wired to nhrotm/v1/tools/*.
 */
/* eslint-disable no-alert -- native alert used intentionally for lightweight error UX. */
import { useEffect, useState, useCallback, useRef } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

import CronPanel from './CronPanel';
import ExportBasket from './ExportBasket';
import formatBytes from './formatBytes';
import JumpNav from './JumpNav';
import Panel from './Panel';
import ScreenHeader from './ScreenHeader';
import Icon from './Icon';
import { useConfirm } from './ConfirmProvider';
import { useToast } from './ToastProvider';

const IMPORT_STATUS = {
	new: {
		label: __( 'New', 'nhrrob-options-table-manager' ),
		tone: 'nhrotm-badge--success',
	},
	modified: {
		label: __( 'Modified', 'nhrrob-options-table-manager' ),
		tone: 'nhrotm-badge--warning',
	},
	unchanged: {
		label: __( 'Unchanged', 'nhrrob-options-table-manager' ),
		tone: 'nhrotm-badge--muted',
	},
	protected: {
		label: __( 'Protected', 'nhrrob-options-table-manager' ),
		tone: 'nhrotm-badge--danger',
	},
};

export default function ToolsScreen( { boot } ) {
	const confirm = useConfirm();
	const toast = useToast();
	const [ backups, setBackups ] = useState( [] );
	const [ busy, setBusy ] = useState( false );
	const [ label, setLabel ] = useState( '' );

	const [ search, setSearch ] = useState( '' );
	const [ replace, setReplace ] = useState( '' );
	const [ srResult, setSrResult ] = useState( null );
	// Live "N options, M occurrences" for the current search string.
	const [ srCount, setSrCount ] = useState( null );

	useEffect( () => {
		if ( ! search ) {
			setSrCount( null );
			return undefined;
		}
		const timer = setTimeout( () => {
			apiFetch( {
				path: 'nhrotm/v1/tools/search-replace/count',
				method: 'POST',
				data: { search },
			} )
				.then( ( res ) => setSrCount( res.data ) )
				.catch( () => setSrCount( null ) );
		}, 400 );
		return () => clearTimeout( timer );
	}, [ search ] );

	const [ importJson, setImportJson ] = useState( '' );
	const [ importFileName, setImportFileName ] = useState( '' );
	const [ isDropzoneActive, setIsDropzoneActive ] = useState( false );
	const fileInputRef = useRef( null );
	const [ importResult, setImportResult ] = useState( null );
	const [ exportBasket, setExportBasket ] = useState( [] );
	// Import preview rows ({ name, status, current, autoload }) and the
	// names ticked for import. Changing the JSON discards a stale preview.
	const [ preview, setPreview ] = useState( null );
	const [ picked, setPicked ] = useState( [] );

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

	const exportJson = ( names = [] ) => {
		const query = names
			.map( ( n ) => `names[]=${ encodeURIComponent( n ) }` )
			.join( '&' );
		apiFetch( {
			path: 'nhrotm/v1/tools/export' + ( query ? `?${ query }` : '' ),
		} )
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

	const setJson = ( json, fileName = '' ) => {
		setImportJson( json );
		setImportFileName( fileName );
		setPreview( null );
		setPicked( [] );
	};

	const runPreview = () => {
		setBusy( true );
		setImportResult( null );
		apiFetch( {
			path: 'nhrotm/v1/tools/import/preview',
			method: 'POST',
			data: { json: importJson },
		} )
			.then( ( res ) => {
				setPreview( res.data );
				// Pre-tick what would actually change; unchanged rows are a
				// no-op and protected ones can never be imported.
				setPicked(
					res.data
						.filter( ( r ) =>
							[ 'new', 'modified' ].includes( r.status )
						)
						.map( ( r ) => r.name )
				);
			} )
			.catch( ( e ) =>
				toast(
					( e && e.message ) ||
						__( 'Preview failed.', 'nhrrob-options-table-manager' ),
					'error'
				)
			)
			.finally( () => setBusy( false ) );
	};

	const togglePicked = ( name ) =>
		setPicked(
			picked.includes( name )
				? picked.filter( ( n ) => n !== name )
				: [ ...picked, name ]
		);

	const runImport = async () => {
		if ( ! importJson.trim() || picked.length === 0 ) {
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
			data: { json: importJson, selected: picked },
		} )
			.then( ( res ) => {
				setImportResult( res.data );
				setJson( '' );
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
		reader.onload = ( ev ) =>
			setJson( String( ev.target.result ), file.name );
		reader.readAsText( file );
	};

	const onFile = ( e ) => readFile( e.target.files && e.target.files[ 0 ] );

	const clearImportFile = () => {
		setJson( '' );
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
					{
						id: 'cron',
						label: __( 'Cron', 'nhrrob-options-table-manager' ),
					},
				] }
			/>
			<Panel
				anchor="backups"
				title={ __( 'Backups', 'nhrrob-options-table-manager' ) }
			>
				<p className="nhrotm-muted">
					{ __(
						'A compressed snapshot of the options table (transients excluded — they are cache), taken before anything risky. Restore it in one click if something goes wrong.',
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
				{ backups.length > 0 && (
					<p className="nhrotm-muted">
						{ sprintf(
							/* translators: 1: number of snapshots, 2: maximum kept, 3: total size, e.g. "120 KB". */
							__(
								'%1$d of %2$d snapshots, using %3$s of database space. The oldest is removed automatically when a new one is added.',
								'nhrrob-options-table-manager'
							),
							backups.length,
							Number( ( boot && boot.maxSnapshots ) || 15 ),
							formatBytes(
								backups.reduce(
									( sum, b ) =>
										sum + Number( b.size_bytes || 0 ),
									0
								)
							)
						) }
					</p>
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
				</div>
				{ srCount && ! srResult && (
					<p className="nhrotm-muted">
						{ sprintf(
							/* translators: 1: number of options, 2: number of occurrences. */
							__(
								'Found in %1$s options, %2$s occurrences.',
								'nhrrob-options-table-manager'
							),
							srCount.options.toLocaleString(),
							srCount.occurrences.toLocaleString()
						) }
					</p>
				) }
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
						'Download every option (excluding transients) as JSON, or search and pick just the options you need.',
						'nhrrob-options-table-manager'
					) }
				</p>
				<ExportBasket
					basket={ exportBasket }
					setBasket={ setExportBasket }
				/>
				<div className="nhrotm-actions">
					<button
						type="button"
						className="nhrotm-btn nhrotm-btn--primary"
						disabled={ exportBasket.length === 0 }
						onClick={ () => exportJson( exportBasket ) }
					>
						{ sprintf(
							/* translators: %d: number of selected options. */
							__(
								'Export selected (%d)',
								'nhrrob-options-table-manager'
							),
							exportBasket.length
						) }
					</button>
					<button
						type="button"
						className="nhrotm-btn"
						onClick={ () => exportJson() }
					>
						{ __(
							'Export all options',
							'nhrrob-options-table-manager'
						) }
					</button>
				</div>
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
						onChange={ ( e ) => setJson( e.target.value ) }
					/>
				</div>
				{ ! preview && (
					<button
						type="button"
						className="nhrotm-btn nhrotm-btn--primary"
						disabled={ busy || ! importJson.trim() }
						onClick={ runPreview }
					>
						{ __(
							'Preview import',
							'nhrrob-options-table-manager'
						) }
					</button>
				) }
				{ preview && (
					<>
						<div className="nhrotm-grid__scroll">
							<table className="nhrotm-grid">
								<colgroup>
									<col className="nhrotm-col-check" />
									<col className="nhrotm-col-name" />
									<col className="nhrotm-col-badge" />
									<col />
								</colgroup>
								<thead>
									<tr>
										<th>
											<input
												type="checkbox"
												aria-label={ __(
													'Select all importable options',
													'nhrrob-options-table-manager'
												) }
												checked={
													picked.length > 0 &&
													picked.length ===
														preview.filter(
															( r ) =>
																r.status !==
																'protected'
														).length
												}
												onChange={ ( e ) =>
													setPicked(
														e.target.checked
															? preview
																	.filter(
																		( r ) =>
																			r.status !==
																			'protected'
																	)
																	.map(
																		( r ) =>
																			r.name
																	)
															: []
													)
												}
											/>
										</th>
										<th>
											{ __(
												'Option',
												'nhrrob-options-table-manager'
											) }
										</th>
										<th>
											{ __(
												'Status',
												'nhrrob-options-table-manager'
											) }
										</th>
										<th>
											{ __(
												'Current value',
												'nhrrob-options-table-manager'
											) }
										</th>
									</tr>
								</thead>
								<tbody>
									{ preview.map( ( r ) => (
										<tr key={ r.name }>
											<td>
												<input
													type="checkbox"
													aria-label={ r.name }
													disabled={
														r.status === 'protected'
													}
													checked={ picked.includes(
														r.name
													) }
													onChange={ () =>
														togglePicked( r.name )
													}
												/>
											</td>
											<td className="nhrotm-grid__name">
												{ r.name }
											</td>
											<td>
												<span
													className={
														'nhrotm-badge ' +
														( IMPORT_STATUS[
															r.status
														]?.tone ||
															'nhrotm-badge--muted' )
													}
												>
													{ IMPORT_STATUS[ r.status ]
														?.label || r.status }
												</span>
											</td>
											<td title={ r.current || '' }>
												<div className="nhrotm-grid__preview">
													{ r.current === null
														? '—'
														: r.current }
												</div>
											</td>
										</tr>
									) ) }
								</tbody>
							</table>
						</div>
						<div className="nhrotm-actions">
							<button
								type="button"
								className="nhrotm-btn nhrotm-btn--primary"
								disabled={ busy || picked.length === 0 }
								onClick={ runImport }
							>
								{ sprintf(
									/* translators: %d: number of options ticked for import. */
									__(
										'Import selected (%d)',
										'nhrrob-options-table-manager'
									),
									picked.length
								) }
							</button>
							<button
								type="button"
								className="nhrotm-btn"
								disabled={ busy }
								onClick={ () => setPreview( null ) }
							>
								{ __(
									'Cancel',
									'nhrrob-options-table-manager'
								) }
							</button>
						</div>
					</>
				) }
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

			<CronPanel />
		</div>
	);
}
