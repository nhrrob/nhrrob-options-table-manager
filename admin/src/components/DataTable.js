/**
 * Config-driven data table shared by the Optimize screen's three lists
 * (Autoload health, Usage Tracker, Orphan scanner) — same grid chrome
 * (zebra/hover/sort) as Browse's hand-rolled grid, but self-contained
 * (client-side search/sort/pagination/selection) since these lists are
 * already fully loaded in one API response, unlike Browse's server-paginated
 * one. Columns are config, not new markup, so a fourth list reuses this
 * instead of another copy-pasted <table>.
 */
import { useMemo, useState } from '@wordpress/element';
import { __, sprintf, _n } from '@wordpress/i18n';

import Icon from './Icon';

const PAGE_SIZE_OPTIONS = [ 20, 50, 100 ];

function sortIconName( isActive, dir ) {
	if ( ! isActive ) {
		return 'sort';
	}
	return dir === 'asc' ? 'sortUp' : 'sortDown';
}

function compareValues( a, b ) {
	if ( typeof a === 'number' && typeof b === 'number' ) {
		return a - b;
	}
	return String( a ).localeCompare( String( b ) );
}

export default function DataTable( {
	columns,
	rows,
	rowKey,
	searchKeys,
	searchPlaceholder,
	filters,
	defaultSort,
	pageSize,
	selectable,
	bulkActions,
	emptyMessage,
} ) {
	const [ search, setSearch ] = useState( '' );
	const [ filterValues, setFilterValues ] = useState( {} );
	const [ sort, setSort ] = useState( defaultSort || null );
	const [ page, setPage ] = useState( 1 );
	const [ perPage, setPerPage ] = useState( pageSize );
	const [ selected, setSelected ] = useState( [] );

	const filtered = useMemo( () => {
		let result = rows;
		if ( filters ) {
			filters.forEach( ( f ) => {
				const value = filterValues[ f.key ];
				if ( value ) {
					result = result.filter(
						( row ) => String( row[ f.key ] ) === value
					);
				}
			} );
		}
		if ( searchKeys && search ) {
			const needle = search.toLowerCase();
			result = result.filter( ( row ) =>
				searchKeys.some( ( key ) =>
					String( row[ key ] || '' )
						.toLowerCase()
						.includes( needle )
				)
			);
		}
		return result;
	}, [ rows, search, searchKeys, filters, filterValues ] );

	const sorted = useMemo( () => {
		if ( ! sort ) {
			return filtered;
		}
		const col = columns.find( ( c ) => c.key === sort.key );
		const valueOf =
			col && col.sortValue ? col.sortValue : ( r ) => r[ sort.key ];
		const dir = sort.dir === 'asc' ? 1 : -1;
		return [ ...filtered ].sort(
			( a, b ) => dir * compareValues( valueOf( a ), valueOf( b ) )
		);
	}, [ filtered, sort, columns ] );

	const totalPages = perPage
		? Math.max( 1, Math.ceil( sorted.length / perPage ) )
		: 1;
	// Clamped rather than stored — deleting the last row on the last page
	// (bulk-delete, e.g.) must not strand the view on a now-empty page.
	const currentPage = Math.min( page, totalPages );
	const pageRows = perPage
		? sorted.slice( ( currentPage - 1 ) * perPage, currentPage * perPage )
		: sorted;

	const sortBy = ( key ) => {
		setPage( 1 );
		setSort( ( prev ) =>
			prev && prev.key === key
				? { key, dir: prev.dir === 'asc' ? 'desc' : 'asc' }
				: { key, dir: 'desc' }
		);
	};

	const toggleRow = ( key ) =>
		setSelected( ( prev ) =>
			prev.includes( key )
				? prev.filter( ( k ) => k !== key )
				: [ ...prev, key ]
		);

	const pageKeys = pageRows.map( rowKey );
	const allPageSelected =
		pageKeys.length > 0 &&
		pageKeys.every( ( k ) => selected.includes( k ) );
	const toggleAllOnPage = () =>
		setSelected( ( prev ) =>
			allPageSelected
				? prev.filter( ( k ) => ! pageKeys.includes( k ) )
				: [ ...new Set( [ ...prev, ...pageKeys ] ) ]
		);

	const selectedRows = rows.filter( ( r ) =>
		selected.includes( rowKey( r ) )
	);

	const showToolbar =
		!! searchKeys || !! filters || ( selectable && !! bulkActions );

	return (
		<div className="nhrotm-datatable">
			{ showToolbar && (
				<div className="nhrotm-browse__toolbar">
					<div className="nhrotm-browse__tools">
						{ selectable &&
							selectedRows.length > 0 &&
							bulkActions &&
							bulkActions.map( ( action ) => (
								<button
									key={ action.label }
									type="button"
									className={
										'nhrotm-btn nhrotm-btn--' +
										( action.variant || 'soft' )
									}
									disabled={ !! action.disabled }
									onClick={ () =>
										action.onClick( selectedRows, () =>
											setSelected( [] )
										)
									}
								>
									{ action.label +
										' (' +
										selectedRows.length +
										')' }
								</button>
							) ) }
					</div>
					<div className="nhrotm-browse__tools">
						{ filters &&
							filters.map( ( f ) => (
								<select
									key={ f.key }
									className="nhrotm-browse__statusfilter"
									value={ filterValues[ f.key ] || '' }
									onChange={ ( e ) => {
										setPage( 1 );
										setFilterValues( ( prev ) => ( {
											...prev,
											[ f.key ]: e.target.value,
										} ) );
									} }
									aria-label={ f.label }
								>
									{ f.options.map( ( opt ) => (
										<option
											key={ opt.value }
											value={ opt.value }
										>
											{ opt.label }
										</option>
									) ) }
								</select>
							) ) }
						{ searchKeys && (
							<span className="nhrotm-search">
								<Icon name="search" size={ 15 } />
								<input
									type="search"
									className="nhrotm-browse__search"
									style={ { WebkitAppearance: 'textfield' } }
									placeholder={
										searchPlaceholder ||
										__(
											'Search…',
											'nhrrob-options-table-manager'
										)
									}
									value={ search }
									onChange={ ( e ) => {
										setPage( 1 );
										setSearch( e.target.value );
									} }
								/>
								{ search && (
									<button
										type="button"
										className="nhrotm-search__clear"
										aria-label={ __(
											'Clear search',
											'nhrrob-options-table-manager'
										) }
										onClick={ () => {
											setPage( 1 );
											setSearch( '' );
										} }
									>
										<Icon name="close" size={ 13 } />
									</button>
								) }
							</span>
						) }
					</div>
				</div>
			) }

			<div className="nhrotm-grid__wrap">
				<div className="nhrotm-grid__scroll">
					<table className="nhrotm-grid">
						<colgroup>
							{ selectable && (
								<col className="nhrotm-col-check" />
							) }
							{ columns.map( ( col ) => (
								<col
									key={ col.key }
									className={ col.colClassName || undefined }
								/>
							) ) }
						</colgroup>
						<thead>
							<tr>
								{ selectable && (
									<th>
										{ pageKeys.length > 0 && (
											<input
												type="checkbox"
												checked={ allPageSelected }
												onChange={ toggleAllOnPage }
												aria-label={ __(
													'Select all on this page',
													'nhrrob-options-table-manager'
												) }
											/>
										) }
									</th>
								) }
								{ columns.map( ( col ) => (
									<th
										key={ col.key }
										style={
											col.align === 'right'
												? { textAlign: 'right' }
												: undefined
										}
									>
										{ col.sortable ? (
											<button
												type="button"
												className={
													'nhrotm-grid__sort' +
													( sort &&
													sort.key === col.key
														? ' is-active'
														: '' )
												}
												onClick={ () =>
													sortBy( col.key )
												}
												aria-label={ col.label }
											>
												{ col.label }
												<Icon
													name={ sortIconName(
														!! (
															sort &&
															sort.key === col.key
														),
														sort && sort.dir
													) }
													size={ 13 }
												/>
											</button>
										) : (
											col.label
										) }
									</th>
								) ) }
							</tr>
						</thead>
						<tbody>
							{ pageRows.length === 0 ? (
								<tr>
									<td
										colSpan={
											columns.length +
											( selectable ? 1 : 0 )
										}
										className="nhrotm-muted"
									>
										{ emptyMessage ||
											__(
												'No records found.',
												'nhrrob-options-table-manager'
											) }
									</td>
								</tr>
							) : (
								pageRows.map( ( row ) => {
									const key = rowKey( row );
									return (
										<tr key={ key }>
											{ selectable && (
												<td>
													<input
														type="checkbox"
														checked={ selected.includes(
															key
														) }
														onChange={ () =>
															toggleRow( key )
														}
														aria-label={ __(
															'Select row',
															'nhrrob-options-table-manager'
														) }
													/>
												</td>
											) }
											{ columns.map( ( col ) => (
												<td
													key={ col.key }
													className={
														col.cellClassName
													}
													style={
														col.align === 'right'
															? {
																	textAlign:
																		'right',
															  }
															: undefined
													}
												>
													{ col.render
														? col.render( row )
														: row[ col.key ] }
												</td>
											) ) }
										</tr>
									);
								} )
							) }
						</tbody>
					</table>
				</div>
			</div>

			{ pageSize && (
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
							{ PAGE_SIZE_OPTIONS.map( ( n ) => (
								<option key={ n } value={ n }>
									{ n }
								</option>
							) ) }
						</select>
					</span>
					<div className="nhrotm-pager__nav">
						<span className="nhrotm-pager__pos">
							{ sprintf(
								/* translators: 1: total matching rows. */
								_n(
									'%1$s row',
									'%1$s rows',
									sorted.length,
									'nhrrob-options-table-manager'
								),
								sorted.length
							) }
						</span>
						<button
							type="button"
							className="nhrotm-btn nhrotm-btn--soft nhrotm-btn--sm"
							disabled={ currentPage <= 1 }
							onClick={ () => setPage( 1 ) }
						>
							{ __( 'First', 'nhrrob-options-table-manager' ) }
						</button>
						<button
							type="button"
							className="nhrotm-btn nhrotm-btn--soft nhrotm-btn--sm"
							disabled={ currentPage <= 1 }
							onClick={ () => setPage( currentPage - 1 ) }
						>
							{ __( 'Prev', 'nhrrob-options-table-manager' ) }
						</button>
						<span className="nhrotm-pager__pos">
							{ currentPage } / { totalPages }
						</span>
						<button
							type="button"
							className="nhrotm-btn nhrotm-btn--soft nhrotm-btn--sm"
							disabled={ currentPage >= totalPages }
							onClick={ () => setPage( currentPage + 1 ) }
						>
							{ __( 'Next', 'nhrrob-options-table-manager' ) }
						</button>
						<button
							type="button"
							className="nhrotm-btn nhrotm-btn--soft nhrotm-btn--sm"
							disabled={ currentPage >= totalPages }
							onClick={ () => setPage( totalPages ) }
						>
							{ __( 'Last', 'nhrrob-options-table-manager' ) }
						</button>
					</div>
				</div>
			) }
		</div>
	);
}
