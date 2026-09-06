/**
 * App shell — gradient hero app bar + collapsible left nav + content slot.
 * Nav is registry-driven; a pinned, de-emphasized Upgrade item is the single
 * PRO-awareness surface (hidden when the PRO add-on is active). See DESIGN.md §1/§12.
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import Icon from './Icon';

const THEME_KEY = 'nhrotm-theme';

export default function AppShell( {
	modules,
	active,
	onNavigate,
	title,
	showUpgrade,
	classicUrl,
	children,
} ) {
	const [ collapsed, setCollapsed ] = useState( false );
	const [ theme, setTheme ] = useState( '' ); // '' = follow OS

	useEffect( () => {
		try {
			const saved = window.localStorage.getItem( THEME_KEY );
			if ( saved === 'dark' || saved === 'light' ) {
				setTheme( saved );
			}
		} catch ( e ) {
			/* localStorage unavailable — fall back to OS preference. */
		}
	}, [] );

	const toggleTheme = () => {
		const prefersDark =
			window.matchMedia &&
			window.matchMedia( '(prefers-color-scheme: dark)' ).matches;
		const isDark = theme ? theme === 'dark' : prefersDark;
		const next = isDark ? 'light' : 'dark';
		setTheme( next );
		try {
			window.localStorage.setItem( THEME_KEY, next );
		} catch ( e ) {
			/* ignore persistence failure */
		}
	};

	const appClass = 'nhrotm-app' + ( collapsed ? ' is-collapsed' : '' );
	const appProps = theme ? { 'data-theme': theme } : {};

	const navItem = ( id, label, extraClass = '' ) => (
		<button
			key={ id }
			type="button"
			className={
				'nhrotm-nav__item' +
				extraClass +
				( id === active ? ' is-active' : '' )
			}
			aria-current={ id === active ? 'page' : undefined }
			title={ label }
			onClick={ () => onNavigate( id ) }
		>
			<Icon name={ id } className="nhrotm-nav__icon" />
			<span className="nhrotm-nav__label">{ label }</span>
		</button>
	);

	return (
		<div className={ appClass } { ...appProps }>
			<header className="nhrotm-appbar">
				<button
					type="button"
					className="nhrotm-appbar__btn nhrotm-appbar__icobtn"
					aria-label={
						collapsed
							? __(
									'Expand sidebar',
									'nhrrob-options-table-manager'
							  )
							: __(
									'Collapse sidebar',
									'nhrrob-options-table-manager'
							  )
					}
					onClick={ () => setCollapsed( ( c ) => ! c ) }
				>
					<Icon name="collapse" size={ 16 } />
				</button>
				<span className="nhrotm-appbar__mark" aria-hidden="true">
					<Icon name="database" size={ 17 } />
				</span>
				<span className="nhrotm-appbar__title">{ title }</span>
				<span className="nhrotm-appbar__spacer"></span>
				{ classicUrl && (
					<a
						className="nhrotm-appbar__btn nhrotm-appbar__link"
						href={ classicUrl }
					>
						{ __( 'Classic view', 'nhrrob-options-table-manager' ) }
					</a>
				) }
				<button
					type="button"
					className="nhrotm-appbar__btn nhrotm-appbar__icobtn"
					aria-label={ __(
						'Toggle dark theme',
						'nhrrob-options-table-manager'
					) }
					onClick={ toggleTheme }
				>
					<span aria-hidden="true">
						{ theme === 'dark' ? '☀' : '☾' }
					</span>
				</button>
			</header>

			<div className="nhrotm-body">
				<nav className="nhrotm-nav" aria-label={ title }>
					{ modules.map( ( m ) => navItem( m.id, m.label ) ) }

					{ showUpgrade && (
						<>
							<span className="nhrotm-nav__spacer"></span>
							<span
								className="nhrotm-nav__sep"
								role="separator"
							></span>
							{ navItem(
								'upgrade',
								__( 'Upgrade', 'nhrrob-options-table-manager' ),
								' nhrotm-nav__item--upgrade'
							) }
						</>
					) }
				</nav>

				<main className="nhrotm-content">
					<div className="nhrotm-screen" key={ active }>
						{ children }
					</div>
				</main>
			</div>
		</div>
	);
}
