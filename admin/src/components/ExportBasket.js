/**
 * Export basket — search options by name and collect the ones to export.
 * Wired to GET nhrotm/v1/tools/export/search. Reuses the IdLookupFilter
 * combobox styles (.nhrotm-lookup*), so it adds no CSS of its own.
 */
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

import Icon from './Icon';

export default function ExportBasket( { basket, setBasket } ) {
	const [ query, setQuery ] = useState( '' );
	const [ results, setResults ] = useState( [] );
	const [ open, setOpen ] = useState( false );
	const [ activeIndex, setActiveIndex ] = useState( 0 );
	const debounceRef = useRef( null );

	useEffect( () => () => clearTimeout( debounceRef.current ), [] );

	const search = ( term ) => {
		setQuery( term );
		clearTimeout( debounceRef.current );
		if ( ! term.trim() ) {
			setResults( [] );
			setOpen( false );
			return;
		}
		debounceRef.current = setTimeout( () => {
			apiFetch( {
				path: `nhrotm/v1/tools/export/search?search=${ encodeURIComponent(
					term
				) }`,
			} )
				.then( ( res ) => {
					setResults( res.data );
					setActiveIndex( 0 );
					setOpen( true );
				} )
				.catch( () => setResults( [] ) );
		}, 300 );
	};

	const add = ( name ) => {
		if ( ! basket.includes( name ) ) {
			setBasket( [ ...basket, name ] );
		}
	};

	const onKeyDown = ( e ) => {
		if ( ! open || results.length === 0 ) {
			return;
		}
		if ( 'ArrowDown' === e.key ) {
			e.preventDefault();
			setActiveIndex( ( i ) => Math.min( i + 1, results.length - 1 ) );
		} else if ( 'ArrowUp' === e.key ) {
			e.preventDefault();
			setActiveIndex( ( i ) => Math.max( i - 1, 0 ) );
		} else if ( 'Enter' === e.key ) {
			e.preventDefault();
			add( results[ activeIndex ] );
		} else if ( 'Escape' === e.key ) {
			setOpen( false );
		}
	};

	return (
		<>
			<div className="nhrotm-field">
				<span className="nhrotm-lookup">
					<Icon name="search" size={ 15 } />
					<input
						type="text"
						className="nhrotm-lookup__input"
						placeholder={ __(
							'Search option names to add…',
							'nhrrob-options-table-manager'
						) }
						aria-label={ __(
							'Search option names to add to the export',
							'nhrrob-options-table-manager'
						) }
						value={ query }
						onChange={ ( e ) => search( e.target.value ) }
						onFocus={ () => results.length && setOpen( true ) }
						onKeyDown={ onKeyDown }
						onBlur={ () => setOpen( false ) }
					/>
					{ open && (
						<div className="nhrotm-lookup__panel" role="listbox">
							{ results.length === 0 && (
								<div className="nhrotm-lookup__status">
									{ __(
										'No matches.',
										'nhrrob-options-table-manager'
									) }
								</div>
							) }
							{ results.map( ( name, index ) => (
								<button
									key={ name }
									type="button"
									role="option"
									aria-selected={ index === activeIndex }
									className={
										'nhrotm-lookup__option' +
										( index === activeIndex
											? ' is-active'
											: '' )
									}
									onMouseDown={ ( e ) => e.preventDefault() }
									onClick={ () => add( name ) }
								>
									{ name }
									{ basket.includes( name ) && (
										<span className="nhrotm-lookup__id">
											{ __(
												'added',
												'nhrrob-options-table-manager'
											) }
										</span>
									) }
								</button>
							) ) }
						</div>
					) }
				</span>
			</div>
			{ basket.length > 0 && (
				<div className="nhrotm-grid__scroll">
					<table className="nhrotm-grid">
						<colgroup>
							<col className="nhrotm-col-name" />
							<col className="nhrotm-col-act-sm" />
						</colgroup>
						<thead>
							<tr>
								<th>
									{ sprintf(
										/* translators: %d: number of options selected for export. */
										__(
											'Selected options (%d)',
											'nhrrob-options-table-manager'
										),
										basket.length
									) }
								</th>
								<th style={ { textAlign: 'right' } }>
									<button
										type="button"
										className="nhrotm-iconbtn"
										onClick={ () => setBasket( [] ) }
									>
										{ __(
											'Clear',
											'nhrrob-options-table-manager'
										) }
									</button>
								</th>
							</tr>
						</thead>
						<tbody>
							{ basket.map( ( name ) => (
								<tr key={ name }>
									<td className="nhrotm-grid__name">
										{ name }
									</td>
									<td>
										<div className="nhrotm-grid__actions">
											<button
												type="button"
												className="nhrotm-iconbtn nhrotm-iconbtn--danger"
												onClick={ () =>
													setBasket(
														basket.filter(
															( n ) => n !== name
														)
													)
												}
											>
												<Icon
													name="close"
													size={ 14 }
												/>
												{ __(
													'Remove',
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
		</>
	);
}
