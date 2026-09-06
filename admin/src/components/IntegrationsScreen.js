/**
 * Integrations — read-only browser for third-party tables (Better Payment, WPRM).
 * Wired to nhrotm/v1/integrations.
 */
import { useEffect, useState, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

import Panel from './Panel';
import ScreenHeader from './ScreenHeader';

const PER_PAGE = 20;

export default function IntegrationsScreen() {
	const [ list, setList ] = useState( [] );
	const [ active, setActive ] = useState( null );
	const [ rows, setRows ] = useState( { columns: [], items: [], total: 0 } );
	const [ page, setPage ] = useState( 1 );
	const [ status, setStatus ] = useState( 'loading' );

	useEffect( () => {
		apiFetch( { path: 'nhrotm/v1/integrations' } )
			.then( ( res ) => {
				setList( res.data );
				if ( res.data.length ) {
					setActive( res.data[ 0 ].slug );
				}
				setStatus( 'ready' );
			} )
			.catch( () => setStatus( 'error' ) );
	}, [] );

	const loadRows = useCallback( () => {
		if ( ! active ) {
			return;
		}
		apiFetch( {
			path: `nhrotm/v1/integrations/${ active }?page=${ page }&per_page=${ PER_PAGE }`,
		} )
			.then( ( res ) => setRows( res.data ) )
			.catch( () => {} );
	}, [ active, page ] );

	useEffect( () => {
		loadRows();
	}, [ loadRows ] );

	if ( status === 'loading' ) {
		return (
			<Panel
				title={ __( 'Integrations', 'nhrrob-options-table-manager' ) }
			>
				<p className="nhrotm-muted">
					{ __( 'Loading…', 'nhrrob-options-table-manager' ) }
				</p>
			</Panel>
		);
	}

	if ( status === 'error' || list.length === 0 ) {
		return (
			<Panel
				title={ __( 'Integrations', 'nhrrob-options-table-manager' ) }
			>
				<p className="nhrotm-muted">
					{ __(
						'No supported integration tables were found.',
						'nhrrob-options-table-manager'
					) }
				</p>
			</Panel>
		);
	}

	const totalPages = Math.max( 1, Math.ceil( rows.total / PER_PAGE ) );

	return (
		<div className="nhrotm-integrations">
			<ScreenHeader
				title={ __( 'Integrations', 'nhrrob-options-table-manager' ) }
				lede={ __(
					'Third-party tables, kept here so they never clutter the main navigation.',
					'nhrrob-options-table-manager'
				) }
			/>
			<Panel
				title={ __( 'Integrations', 'nhrrob-options-table-manager' ) }
			>
				<div className="nhrotm-segmented">
					{ list.map( ( it ) => (
						<button
							key={ it.slug }
							type="button"
							className={
								'nhrotm-segmented__item' +
								( it.slug === active ? ' is-active' : '' )
							}
							onClick={ () => {
								setActive( it.slug );
								setPage( 1 );
							} }
						>
							{ it.label } ({ it.count })
						</button>
					) ) }
				</div>

				<div className="nhrotm-grid__scroll">
					<table className="nhrotm-grid">
						<thead>
							<tr>
								{ rows.columns.map( ( c ) => (
									<th key={ c }>{ c }</th>
								) ) }
							</tr>
						</thead>
						<tbody>
							{ rows.items.length === 0 ? (
								<tr>
									<td
										colSpan={ rows.columns.length || 1 }
										className="nhrotm-muted"
									>
										{ __(
											'No rows.',
											'nhrrob-options-table-manager'
										) }
									</td>
								</tr>
							) : (
								rows.items.map( ( row, i ) => (
									<tr key={ i }>
										{ rows.columns.map( ( c ) => (
											<td
												key={ c }
												className="nhrotm-grid__preview"
											>
												{ String( row[ c ] ).slice(
													0,
													60
												) }
											</td>
										) ) }
									</tr>
								) )
							) }
						</tbody>
					</table>
				</div>

				<div className="nhrotm-pager">
					<span className="nhrotm-muted">
						{ rows.total }{ ' ' }
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
