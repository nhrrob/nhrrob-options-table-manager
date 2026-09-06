/**
 * Browse — unified options / usermeta / transients data grid.
 * Hand-rolled grid (no table library) to protect the bundle budget.
 * Server-side paginated, searchable and sortable (DataTables-style behaviour)
 * via nhrotm/v1/browse (list, get, save, delete, bulk-delete).
 */
/* eslint-disable no-alert -- native confirm/alert used intentionally for lightweight UX. */
import { useEffect, useState, useCallback, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

import EditModal from './EditModal';
import Icon from './Icon';
import ScreenHeader from './ScreenHeader';

const TYPES = [
	{ id: 'options', label: __( 'Options', 'nhrrob-options-table-manager' ) },
	{ id: 'usermeta', label: __( 'Usermeta', 'nhrrob-options-table-manager' ) },
	{
		id: 'transients',
		label: __( 'Transients', 'nhrrob-options-table-manager' ),
	},
];

const PER_PAGE_OPTIONS = [ 20, 50, 100 ];

const STATUS_BADGE = {
	expired: 'danger',
	persistent: 'muted',
	active: 'success',
};

export default function BrowseScreen() {
	const [ type, setType ] = useState( 'options' );
	const [ search, setSearch ] = useState( '' );
	const [ page, setPage ] = useState( 1 );
	const [ perPage, setPerPage ] = useState( 20 );
	const [ orderby, setOrderby ] = useState( 'size' );
	const [ order, setOrder ] = useState( 'desc' );
	const [ data, setData ] = useState( { items: [], total: 0 } );
	const [ status, setStatus ] = useState( 'loading' );
	const [ selected, setSelected ] = useState( [] );
	const [ modal, setModal ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ reloadTick, setReloadTick ] = useState( 0 );
	// When true, the next load refreshes rows in place (no "Loading…" flash) —
	// used after save/delete so the grid doesn't blink behind the closing modal.
	const silentRef = useRef( false );

	const load = useCallback( () => {
		if ( ! silentRef.current ) {
			setStatus( 'loading' );
		}
		silentRef.current = false;
		setSelected( [] );
		apiFetch( {
			path:
				`nhrotm/v1/browse?type=${ type }&page=${ page }` +
				`&per_page=${ perPage }&orderby=${ orderby }&order=${ order }` +
				`&search=${ encodeURIComponent( search ) }`,
		} )
			.then( ( res ) => {
				setData( res.data );
				setStatus( 'ready' );
			} )
			.catch( () => setStatus( 'error' ) );
		// reloadTick lets callers force a refresh even when no query param changed.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ type, page, perPage, orderby, order, search, reloadTick ] );

	useEffect( () => {
		load();
	}, [ load ] );

	// Refresh rows in place without the loading flash.
	const silentReload = () => {
		silentRef.current = true;
		setReloadTick( ( t ) => t + 1 );
	};

	const remove = ( item ) => {
		if (
			! window.confirm(
				__( 'Delete this record?', 'nhrrob-options-table-manager' )
			)
		) {
			return;
		}
		apiFetch( {
			path: `nhrotm/v1/browse/${ encodeURIComponent(
				item.id
			) }?type=${ type }`,
			method: 'DELETE',
		} )
			.then( () => silentReload() )
			.catch( () =>
				window.alert(
					__(
						'Delete failed — the record may be protected.',
						'nhrrob-options-table-manager'
					)
				)
			);
	};

	const bulkDelete = () => {
		if (
			selected.length === 0 ||
			! window.confirm(
				__(
					'Delete the selected records?',
					'nhrrob-options-table-manager'
				)
			)
		) {
			return;
		}
		apiFetch( {
			path: 'nhrotm/v1/browse/bulk-delete',
			method: 'POST',
			data: { type, ids: selected.map( String ) },
		} )
			.then( () => silentReload() )
			.catch( () =>
				window.alert(
					__( 'Bulk delete failed.', 'nhrrob-options-table-manager' )
				)
			);
	};

	const openEdit = ( item ) => {
		apiFetch( {
			path: `nhrotm/v1/browse/${ encodeURIComponent(
				item.id
			) }?type=${ type }`,
		} )
			.then( ( res ) => setModal( { record: res.data } ) )
			.catch( () =>
				window.alert(
					__(
						'Could not load record.',
						'nhrrob-options-table-manager'
					)
				)
			);
	};

	const saveRecord = ( payload ) => {
		const creating = ! payload.id;
		setBusy( true );
		apiFetch( {
			path: 'nhrotm/v1/browse/save',
			method: 'POST',
			data: { type, ...payload },
		} )
			.then( () => {
				setModal( null );
				if ( creating ) {
					// Surface the just-created record: newest first, page 1.
					setSearch( '' );
					setPage( 1 );
					setOrderby( 'id' );
					setOrder( 'desc' );
					setReloadTick( ( t ) => t + 1 );
				} else {
					silentReload();
				}
			} )
			.catch( ( e ) =>
				window.alert(
					( e && e.message ) ||
						__( 'Save failed.', 'nhrrob-options-table-manager' )
				)
			)
			.finally( () => setBusy( false ) );
	};

	const toggleRow = ( id ) =>
		setSelected( ( prev ) =>
			prev.includes( id )
				? prev.filter( ( x ) => x !== id )
				: [ ...prev, id ]
		);

	const totalPages = Math.max( 1, Math.ceil( data.total / perPage ) );

	const switchType = ( id ) => {
		setType( id );
		setPage( 1 );
		setSearch( '' );
		setOrderby( 'size' );
		setOrder( 'desc' );
	};

	// Click a sortable header: toggle direction if same column, else default desc.
	const sortBy = ( key ) => {
		setPage( 1 );
		if ( orderby === key ) {
			setOrder( order === 'asc' ? 'desc' : 'asc' );
		} else {
			setOrderby( key );
			setOrder( 'desc' );
		}
	};

	const SortHeader = ( { label, sortKey } ) => {
		const isActive = orderby === sortKey;
		let iconName = 'sort';
		if ( isActive ) {
			iconName = order === 'asc' ? 'sortUp' : 'sortDown';
		}
		return (
			<button
				type="button"
				className={
					'nhrotm-grid__sort' + ( isActive ? ' is-active' : '' )
				}
				onClick={ () => sortBy( sortKey ) }
				aria-label={ label }
			>
				{ label }
				<Icon name={ iconName } size={ 13 } />
			</button>
		);
	};

	const messageRow = ( text ) => (
		<tr>
			<td colSpan={ 6 } className="nhrotm-muted">
				{ text }
			</td>
		</tr>
	);

	const renderRows = () => {
		if ( status === 'loading' ) {
			return messageRow(
				__( 'Loading…', 'nhrrob-options-table-manager' )
			);
		}
		if ( data.items.length === 0 ) {
			return messageRow(
				__( 'No records found.', 'nhrrob-options-table-manager' )
			);
		}
		return data.items.map( ( item ) => (
			<tr key={ item.id }>
				<td>
					{ ! item.protected && type !== 'transients' && (
						<input
							type="checkbox"
							checked={ selected.includes( item.id ) }
							onChange={ () => toggleRow( item.id ) }
							aria-label={ __(
								'Select row',
								'nhrrob-options-table-manager'
							) }
						/>
					) }
				</td>
				<td className="nhrotm-grid__name" title={ item.name }>
					{ item.name }
					{ item.protected && (
						<span className="nhrotm-badge nhrotm-badge--muted nhrotm-grid__tag">
							{ __(
								'protected',
								'nhrrob-options-table-manager'
							) }
						</span>
					) }
				</td>
				<td className="nhrotm-grid__preview" title={ item.preview }>
					{ item.preview }
				</td>
				<td className="nhrotm-grid__size">{ item.size }</td>
				<td>
					{ type === 'options' && (
						<span
							className={
								'nhrotm-badge nhrotm-badge--dot ' +
								( item.autoload === 'yes'
									? 'nhrotm-badge--success'
									: 'nhrotm-badge--muted' )
							}
						>
							{ item.autoload === 'yes'
								? __( 'On', 'nhrrob-options-table-manager' )
								: __( 'Off', 'nhrrob-options-table-manager' ) }
						</span>
					) }
					{ type === 'transients' && (
						<span
							className={
								'nhrotm-badge nhrotm-badge--dot nhrotm-badge--' +
								( STATUS_BADGE[ item.status ] || 'muted' )
							}
						>
							{ item.status }
						</span>
					) }
				</td>
				<td>
					<div className="nhrotm-grid__actions">
						{ type !== 'transients' && ! item.protected && (
							<button
								type="button"
								className="nhrotm-iconbtn"
								title={ __(
									'Edit',
									'nhrrob-options-table-manager'
								) }
								aria-label={ __(
									'Edit',
									'nhrrob-options-table-manager'
								) }
								onClick={ () => openEdit( item ) }
							>
								<Icon name="edit" size={ 14 } />
							</button>
						) }
						{ ! item.protected && (
							<button
								type="button"
								className="nhrotm-iconbtn nhrotm-iconbtn--danger"
								title={ __(
									'Delete',
									'nhrrob-options-table-manager'
								) }
								aria-label={ __(
									'Delete',
									'nhrrob-options-table-manager'
								) }
								onClick={ () => remove( item ) }
							>
								<Icon name="trash" size={ 14 } />
							</button>
						) }
					</div>
				</td>
			</tr>
		) );
	};

	const from = data.total === 0 ? 0 : ( page - 1 ) * perPage + 1;
	const to = Math.min( page * perPage, data.total );

	return (
		<div className="nhrotm-browse">
			<ScreenHeader
				title={ __( 'Browse', 'nhrrob-options-table-manager' ) }
				lede={ __(
					'One data browser for options, user meta and transients. Autoload is managed in Optimize.',
					'nhrrob-options-table-manager'
				) }
			/>

			<div className="nhrotm-browse__toolbar">
				<div className="nhrotm-segmented">
					{ TYPES.map( ( t ) => (
						<button
							key={ t.id }
							type="button"
							className={
								'nhrotm-segmented__item' +
								( t.id === type ? ' is-active' : '' )
							}
							onClick={ () => switchType( t.id ) }
						>
							{ t.label }
						</button>
					) ) }
				</div>
				<div className="nhrotm-browse__tools">
					{ selected.length > 0 && (
						<button
							type="button"
							className="nhrotm-btn nhrotm-btn--soft"
							onClick={ bulkDelete }
						>
							{ __(
								'Delete selected',
								'nhrrob-options-table-manager'
							) }
							{ ' (' + selected.length + ')' }
						</button>
					) }
					{ type !== 'transients' && (
						<button
							type="button"
							className="nhrotm-btn nhrotm-btn--primary"
							onClick={ () => setModal( { record: null } ) }
						>
							{ type === 'usermeta'
								? __(
										'Add usermeta',
										'nhrrob-options-table-manager'
								  )
								: __(
										'Add option',
										'nhrrob-options-table-manager'
								  ) }
						</button>
					) }
					<input
						type="search"
						className="nhrotm-browse__search"
						placeholder={ __(
							'Search…',
							'nhrrob-options-table-manager'
						) }
						value={ search }
						onChange={ ( e ) => {
							setPage( 1 );
							setSearch( e.target.value );
						} }
					/>
				</div>
			</div>

			{ status === 'error' ? (
				<p className="nhrotm-error">
					{ __(
						'Could not load records.',
						'nhrrob-options-table-manager'
					) }
				</p>
			) : (
				<div className="nhrotm-grid__wrap">
					<div className="nhrotm-grid__scroll">
						<table className="nhrotm-grid">
							<colgroup>
								<col className="nhrotm-col-check" />
								<col className="nhrotm-col-name" />
								<col />
								<col className="nhrotm-col-size" />
								<col className="nhrotm-col-badge" />
								<col className="nhrotm-col-act" />
							</colgroup>
							<thead>
								<tr>
									<th />
									<th>
										<SortHeader
											label={ __(
												'Name',
												'nhrrob-options-table-manager'
											) }
											sortKey="name"
										/>
									</th>
									<th>
										{ __(
											'Value',
											'nhrrob-options-table-manager'
										) }
									</th>
									<th style={ { textAlign: 'right' } }>
										<SortHeader
											label={ __(
												'Size',
												'nhrrob-options-table-manager'
											) }
											sortKey="size"
										/>
									</th>
									<th>
										{ type === 'options' && (
											<SortHeader
												label={ __(
													'Autoload',
													'nhrrob-options-table-manager'
												) }
												sortKey="autoload"
											/>
										) }
										{ type === 'transients' &&
											__(
												'Status',
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
							<tbody>{ renderRows() }</tbody>
						</table>
					</div>
				</div>
			) }

			<div className="nhrotm-pager">
				<span className="nhrotm-pager__perpage">
					{ __( 'Rows:', 'nhrrob-options-table-manager' ) }
					<select
						value={ perPage }
						onChange={ ( e ) => {
							setPage( 1 );
							setPerPage( Number( e.target.value ) );
						} }
						aria-label={ __(
							'Rows per page',
							'nhrrob-options-table-manager'
						) }
					>
						{ PER_PAGE_OPTIONS.map( ( n ) => (
							<option key={ n } value={ n }>
								{ n }
							</option>
						) ) }
					</select>
				</span>

				<div className="nhrotm-pager__nav">
					<span className="nhrotm-pager__pos">
						{ from }–{ to }{ ' ' }
						{ __( 'of', 'nhrrob-options-table-manager' ) }{ ' ' }
						{ data.total }
					</span>
					<button
						type="button"
						className="nhrotm-btn nhrotm-btn--soft nhrotm-btn--sm"
						disabled={ page <= 1 }
						onClick={ () => setPage( page - 1 ) }
					>
						{ __( 'Prev', 'nhrrob-options-table-manager' ) }
					</button>
					<span className="nhrotm-pager__pos">
						{ page } / { totalPages }
					</span>
					<button
						type="button"
						className="nhrotm-btn nhrotm-btn--soft nhrotm-btn--sm"
						disabled={ page >= totalPages }
						onClick={ () => setPage( page + 1 ) }
					>
						{ __( 'Next', 'nhrrob-options-table-manager' ) }
					</button>
				</div>
			</div>

			{ modal && (
				<EditModal
					type={ type }
					record={ modal.record }
					busy={ busy }
					onSave={ saveRecord }
					onClose={ () => setModal( null ) }
				/>
			) }
		</div>
	);
}
