/**
 * Network — the multisite Network Admin view: every site at a glance (open
 * any site's own Database Cleaner from here) and the network options table
 * (wp_sitemeta), where WordPress keeps network settings and site transients.
 * Wired to nhrotm/v1/network.
 */
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

import DataTable from './DataTable';
import Icon from './Icon';
import Panel from './Panel';
import ScreenHeader from './ScreenHeader';
import formatBytes from './formatBytes';
import { useConfirm } from './ConfirmProvider';
import { useToast } from './ToastProvider';

const PER_PAGE = 50;

export default function NetworkScreen() {
	const confirm = useConfirm();
	const toast = useToast();
	const [ sites, setSites ] = useState( null );
	const [ options, setOptions ] = useState( { items: [], total: 0 } );
	const [ searchInput, setSearchInput ] = useState( '' );
	const [ search, setSearch ] = useState( '' );
	const [ kind, setKind ] = useState( '' );
	const [ page, setPage ] = useState( 1 );
	const [ busy, setBusy ] = useState( false );
	const debounceRef = useRef( null );

	useEffect( () => {
		apiFetch( { path: 'nhrotm/v1/network/sites' } )
			.then( ( res ) => setSites( res.data ) )
			.catch( () => setSites( [] ) );
		return () => clearTimeout( debounceRef.current );
	}, [] );

	const loadOptions = useCallback( () => {
		apiFetch( {
			path:
				`nhrotm/v1/network/options?page=${ page }&kind=${ kind }` +
				`&search=${ encodeURIComponent( search ) }`,
		} )
			.then( ( res ) => setOptions( res.data ) )
			.catch( () => {} );
	}, [ page, kind, search ] );

	useEffect( () => {
		loadOptions();
	}, [ loadOptions ] );

	const onSearch = ( value ) => {
		setSearchInput( value );
		clearTimeout( debounceRef.current );
		debounceRef.current = setTimeout( () => {
			setSearch( value );
			setPage( 1 );
		}, 300 );
	};

	const remove = async ( row ) => {
		if (
			! ( await confirm(
				sprintf(
					/* translators: %s: network option name. */
					__(
						'Delete the network option %s?',
						'nhrrob-options-table-manager'
					),
					row.name
				),
				{
					description: __(
						'It is removed for the whole network. This action cannot be undone.',
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
		setBusy( true );
		apiFetch( {
			path: `nhrotm/v1/network/options/${ row.id }`,
			method: 'DELETE',
		} )
			.then( () => {
				toast(
					__(
						'Network option deleted successfully.',
						'nhrrob-options-table-manager'
					)
				);
				loadOptions();
			} )
			.catch( ( e ) =>
				toast(
					( e && e.message ) ||
						__( 'Delete failed.', 'nhrrob-options-table-manager' ),
					'error'
				)
			)
			.finally( () => setBusy( false ) );
	};

	const cleanTransients = () => {
		setBusy( true );
		apiFetch( {
			path: 'nhrotm/v1/network/clean-transients',
			method: 'POST',
		} )
			.then( ( res ) => {
				toast(
					sprintf(
						/* translators: %s: number of site transients deleted. */
						_n(
							'%s expired site transient deleted successfully.',
							'%s expired site transients deleted successfully.',
							res.data.deleted,
							'nhrrob-options-table-manager'
						),
						res.data.deleted
					)
				);
				loadOptions();
			} )
			.catch( () =>
				toast(
					__( 'Cleanup failed.', 'nhrrob-options-table-manager' ),
					'error'
				)
			)
			.finally( () => setBusy( false ) );
	};

	const siteColumns = [
		{
			key: 'name',
			label: __( 'Site', 'nhrrob-options-table-manager' ),
			sortable: true,
			colClassName: 'nhrotm-col-name',
			render: ( r ) => (
				<span title={ r.url }>
					{ r.name || r.url }
					{ r.main && (
						<>
							{ ' ' }
							<span className="nhrotm-badge nhrotm-badge--muted">
								{ __(
									'Main site',
									'nhrrob-options-table-manager'
								) }
							</span>
						</>
					) }
					<span className="nhrotm-cleanup__note">{ r.url }</span>
				</span>
			),
		},
		{
			key: 'options',
			label: __( 'Options', 'nhrrob-options-table-manager' ),
			sortable: true,
			colClassName: 'nhrotm-col-count',
			render: ( r ) => r.options.toLocaleString(),
		},
		{
			key: 'autoload',
			label: __( 'Autoload', 'nhrrob-options-table-manager' ),
			sortable: true,
			colClassName: 'nhrotm-col-badge',
			render: ( r ) => formatBytes( r.autoload ),
		},
		{
			key: 'db_bytes',
			label: __( 'Database', 'nhrrob-options-table-manager' ),
			sortable: true,
			colClassName: 'nhrotm-col-badge',
			render: ( r ) => formatBytes( r.db_bytes ),
		},
		{
			key: 'actions',
			label: __( 'Actions', 'nhrrob-options-table-manager' ),
			align: 'right',
			colClassName: 'nhrotm-col-act-sm',
			render: ( r ) => (
				<div className="nhrotm-grid__actions">
					<a className="nhrotm-iconbtn" href={ r.open }>
						<Icon name="arrowRight" size={ 14 } />
						{ __( 'Open', 'nhrrob-options-table-manager' ) }
					</a>
				</div>
			),
		},
	];

	const totalPages = Math.max( 1, Math.ceil( options.total / PER_PAGE ) );

	return (
		<div className="nhrotm-network-screen">
			<ScreenHeader
				title={ __( 'Network', 'nhrrob-options-table-manager' ) }
				lede={ __(
					'Every site in this network at a glance, and the network-wide options WordPress stores outside each site.',
					'nhrrob-options-table-manager'
				) }
			/>
			<Panel
				anchor="sites"
				title={ __( 'Sites', 'nhrrob-options-table-manager' ) }
				meta={
					sites
						? sprintf(
								/* translators: %d: number of sites. */
								_n(
									'%d site',
									'%d sites',
									sites.length,
									'nhrrob-options-table-manager'
								),
								sites.length
						  )
						: null
				}
			>
				{ sites === null ? (
					<p className="nhrotm-muted">
						{ __( 'Loading…', 'nhrrob-options-table-manager' ) }
					</p>
				) : (
					<DataTable
						columns={ siteColumns }
						rows={ sites }
						rowKey={ ( r ) => r.id }
						defaultSort={ { key: 'db_bytes', dir: 'desc' } }
						searchKeys={ [ 'name', 'url' ] }
						searchPlaceholder={ __(
							'Search sites…',
							'nhrrob-options-table-manager'
						) }
						pageSize={ 20 }
						emptyMessage={ __(
							'No sites found.',
							'nhrrob-options-table-manager'
						) }
					/>
				) }
			</Panel>

			<Panel
				anchor="network-options"
				title={ __(
					'Network options',
					'nhrrob-options-table-manager'
				) }
				meta={ sprintf(
					/* translators: %s: number of rows. */
					__( '%s rows', 'nhrrob-options-table-manager' ),
					options.total.toLocaleString()
				) }
			>
				<p className="nhrotm-muted">
					{ __(
						'Network settings and site transients, largest first. WordPress core settings are protected and cannot be deleted.',
						'nhrrob-options-table-manager'
					) }
				</p>
				<div className="nhrotm-actions">
					<select
						className="nhrotm-pager__select"
						aria-label={ __(
							'Filter network options',
							'nhrrob-options-table-manager'
						) }
						value={ kind }
						onChange={ ( e ) => {
							setKind( e.target.value );
							setPage( 1 );
						} }
					>
						<option value="">
							{ __(
								'Everything',
								'nhrrob-options-table-manager'
							) }
						</option>
						<option value="options">
							{ __(
								'Options only',
								'nhrrob-options-table-manager'
							) }
						</option>
						<option value="transients">
							{ __(
								'Site transients only',
								'nhrrob-options-table-manager'
							) }
						</option>
					</select>
					<button
						type="button"
						className="nhrotm-btn nhrotm-btn--soft"
						disabled={ busy }
						onClick={ cleanTransients }
					>
						{ __(
							'Delete expired site transients',
							'nhrrob-options-table-manager'
						) }
					</button>
					<span className="nhrotm-search">
						<Icon name="search" size={ 15 } />
						<input
							type="search"
							className="nhrotm-browse__search"
							style={ { WebkitAppearance: 'textfield' } }
							placeholder={ __(
								'Search network options…',
								'nhrrob-options-table-manager'
							) }
							value={ searchInput }
							onChange={ ( e ) => onSearch( e.target.value ) }
						/>
					</span>
				</div>
				<div className="nhrotm-grid__scroll">
					<table className="nhrotm-grid">
						<colgroup>
							<col className="nhrotm-col-name" />
							<col />
							<col className="nhrotm-col-size" />
							<col className="nhrotm-col-act-sm" />
						</colgroup>
						<thead>
							<tr>
								<th>
									{ __(
										'Name',
										'nhrrob-options-table-manager'
									) }
								</th>
								<th>
									{ __(
										'Value',
										'nhrrob-options-table-manager'
									) }
								</th>
								<th>
									{ __(
										'Size',
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
							{ options.items.length === 0 && (
								<tr>
									<td colSpan={ 4 } className="nhrotm-muted">
										{ __(
											'No network options match.',
											'nhrrob-options-table-manager'
										) }
									</td>
								</tr>
							) }
							{ options.items.map( ( row ) => (
								<tr key={ row.id }>
									<td
										className="nhrotm-grid__name"
										title={ row.name }
									>
										{ row.name }
									</td>
									<td title={ row.preview }>
										<div className="nhrotm-grid__preview">
											{ row.preview }
										</div>
									</td>
									<td className="nhrotm-grid__size">
										{ formatBytes( row.size ) }
									</td>
									<td>
										<div className="nhrotm-grid__actions">
											{ row.protected ? (
												<span className="nhrotm-muted">
													<Icon
														name="lock"
														size={ 14 }
													/>{ ' ' }
													{ __(
														'Protected',
														'nhrrob-options-table-manager'
													) }
												</span>
											) : (
												<button
													type="button"
													className="nhrotm-iconbtn nhrotm-iconbtn--danger"
													disabled={ busy }
													onClick={ () =>
														remove( row )
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
											) }
										</div>
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
				</div>
				<div className="nhrotm-pager">
					<span className="nhrotm-muted">
						{ options.total.toLocaleString() }{ ' ' }
						{ __( 'rows', 'nhrrob-options-table-manager' ) }
					</span>
					<div className="nhrotm-pager__nav">
						<button
							type="button"
							className="nhrotm-btn nhrotm-btn--soft"
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
							className="nhrotm-btn nhrotm-btn--soft"
							disabled={ page >= totalPages }
							onClick={ () => setPage( page + 1 ) }
						>
							{ __( 'Next', 'nhrrob-options-table-manager' ) }
						</button>
					</div>
				</div>
			</Panel>
		</div>
	);
}
