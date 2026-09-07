/**
 * Search-as-you-type combobox backing the Browse postmeta/usermeta "filter by
 * post/user" control. Resolves a typed name to an id via
 * `nhrotm/v1/browse/lookup` (post title / user display name+login search),
 * so the actual server-side filter stays a cheap `WHERE post_id = %d` /
 * `WHERE user_id = %d` — this component only exists to make picking that id
 * possible without memorizing it.
 */
import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

import Icon from './Icon';

const DEBOUNCE_MS = 300;

export default function IdLookupFilter( {
	target, // 'post' | 'user'
	value, // currently selected id, 0 = none
	label, // display label for the currently selected id
	placeholder,
	ariaLabel,
	onChange, // ( id, label ) => void
} ) {
	const [ open, setOpen ] = useState( false );
	const [ query, setQuery ] = useState( '' );
	const [ results, setResults ] = useState( [] );
	const [ loading, setLoading ] = useState( false );
	const [ activeIndex, setActiveIndex ] = useState( -1 );
	const debounceRef = useRef( null );
	const requestIdRef = useRef( 0 );

	const runSearch = ( term ) => {
		const requestId = ++requestIdRef.current;
		setLoading( true );
		apiFetch( {
			path: `nhrotm/v1/browse/lookup?target=${ target }&search=${ encodeURIComponent(
				term
			) }`,
		} )
			.then( ( res ) => {
				if ( requestId !== requestIdRef.current ) {
					return; // A newer request has already superseded this one.
				}
				setResults( res.data.items || [] );
				setActiveIndex( -1 );
				setLoading( false );
			} )
			.catch( () => {
				if ( requestId === requestIdRef.current ) {
					setResults( [] );
					setLoading( false );
				}
			} );
	};

	const openWithDefaults = () => {
		setOpen( true );
		setQuery( '' );
		runSearch( '' );
	};

	const handleInputChange = ( e ) => {
		const next = e.target.value;
		setQuery( next );
		setOpen( true );
		if ( value ) {
			onChange( 0, '' ); // Typing invalidates the previous selection.
		}
		clearTimeout( debounceRef.current );
		debounceRef.current = setTimeout(
			() => runSearch( next ),
			DEBOUNCE_MS
		);
	};

	const selectItem = ( item ) => {
		onChange( item.id, item.label );
		setQuery( item.label );
		setOpen( false );
	};

	const clear = () => {
		onChange( 0, '' );
		setQuery( '' );
		setResults( [] );
		setOpen( false );
	};

	const handleKeyDown = ( e ) => {
		if ( ! open ) {
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
			if ( results[ activeIndex ] ) {
				selectItem( results[ activeIndex ] );
			}
		} else if ( 'Escape' === e.key ) {
			setOpen( false );
			setQuery( '' );
		}
	};

	useEffect( () => () => clearTimeout( debounceRef.current ), [] );

	const displayValue = open ? query : label || '';

	return (
		<span className="nhrotm-lookup">
			<Icon name="search" size={ 15 } />
			<input
				type="text"
				className="nhrotm-lookup__input"
				placeholder={ placeholder }
				aria-label={ ariaLabel }
				value={ displayValue }
				onFocus={ () => {
					if ( ! open ) {
						openWithDefaults();
					}
				} }
				onChange={ handleInputChange }
				onKeyDown={ handleKeyDown }
				onBlur={ () => setOpen( false ) }
			/>
			{ ( value || query ) && (
				<button
					type="button"
					className="nhrotm-lookup__clear"
					aria-label={ __(
						'Clear filter',
						'nhrrob-options-table-manager'
					) }
					onMouseDown={ ( e ) => e.preventDefault() }
					onClick={ clear }
				>
					<Icon name="close" size={ 13 } />
				</button>
			) }
			{ open && (
				<div className="nhrotm-lookup__panel" role="listbox">
					{ loading && (
						<div className="nhrotm-lookup__status">
							{ __( 'Loading…', 'nhrrob-options-table-manager' ) }
						</div>
					) }
					{ ! loading && results.length === 0 && (
						<div className="nhrotm-lookup__status">
							{ __(
								'No matches.',
								'nhrrob-options-table-manager'
							) }
						</div>
					) }
					{ ! loading &&
						results.map( ( item, index ) => (
							<button
								key={ item.id }
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
								onClick={ () => selectItem( item ) }
							>
								{ item.label }
								<span className="nhrotm-lookup__id">
									#{ item.id }
								</span>
							</button>
						) ) }
				</div>
			) }
		</span>
	);
}
