/**
 * Optimize — autoload health, usage tracker, orphan scanner, cleanup.
 * Wired to nhrotm/v1/optimize (+ action endpoints).
 */
/* eslint-disable no-alert -- native confirm/alert used intentionally for lightweight action UX. */
import { useEffect, useState, useCallback } from '@wordpress/element';
import { __, sprintf, _n } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

import Panel from './Panel';
import ProTag from './ProTag';
import ScreenHeader from './ScreenHeader';

export default function OptimizeScreen( { boot, onNavigate } ) {
	const hasPro = !! ( boot && boot.hasPro );
	const [ data, setData ] = useState( null );
	const [ status, setStatus ] = useState( 'loading' );
	const [ busy, setBusy ] = useState( false );

	const load = useCallback( () => {
		apiFetch( { path: 'nhrotm/v1/optimize' } )
			.then( ( res ) => {
				setData( res.data );
				setStatus( 'ready' );
			} )
			.catch( () => setStatus( 'error' ) );
	}, [] );

	useEffect( () => {
		load();
	}, [ load ] );

	const action = ( path, body ) => {
		setBusy( true );
		return apiFetch( {
			path: 'nhrotm/v1/optimize/' + path,
			method: 'POST',
			data: body || {},
		} )
			.then( () => load() )
			.catch( ( e ) =>
				window.alert(
					( e && e.message ) ||
						__( 'Action failed.', 'nhrrob-options-table-manager' )
				)
			)
			.finally( () => setBusy( false ) );
	};

	if ( status === 'loading' ) {
		return (
			<Panel title={ __( 'Optimize', 'nhrrob-options-table-manager' ) }>
				<p className="nhrotm-muted">
					{ __( 'Loading…', 'nhrrob-options-table-manager' ) }
				</p>
			</Panel>
		);
	}
	if ( status === 'error' || ! data ) {
		return (
			<Panel title={ __( 'Optimize', 'nhrrob-options-table-manager' ) }>
				<p className="nhrotm-error">
					{ __( 'Could not load.', 'nhrrob-options-table-manager' ) }
				</p>
			</Panel>
		);
	}

	return (
		<div className="nhrotm-optimize">
			<ScreenHeader
				title={ __( 'Optimize', 'nhrrob-options-table-manager' ) }
				lede={ __(
					'The single home for autoload, usage tracking, orphan scanning and cleanup.',
					'nhrrob-options-table-manager'
				) }
			/>
			<Panel
				anchor="autoload"
				title={ sprintf(
					/* translators: %s: total autoloaded size. */
					__(
						'Autoload health — %s',
						'nhrrob-options-table-manager'
					),
					data.autoload_total
				) }
			>
				{ data.heavy.length === 0 ? (
					<p className="nhrotm-muted">
						{ __(
							'No autoloaded options found.',
							'nhrrob-options-table-manager'
						) }
					</p>
				) : (
					<ul className="nhrotm-oplist">
						{ data.heavy.map( ( o ) => (
							<li key={ o.name } className="nhrotm-oprow">
								<span className="nhrotm-oprow__name">
									{ o.name }
								</span>
								<span className="nhrotm-oprow__size">
									{ o.size }
								</span>
								{ o.autoload === 'yes' && (
									<button
										type="button"
										className="nhrotm-btn nhrotm-btn--soft"
										disabled={ busy }
										onClick={ () =>
											action( 'disable-autoload', {
												option: o.name,
											} )
										}
									>
										{ __(
											'Disable autoload',
											'nhrrob-options-table-manager'
										) }
									</button>
								) }
							</li>
						) ) }
					</ul>
				) }
			</Panel>

			<Panel
				anchor="usage"
				title={ __( 'Usage Tracker', 'nhrrob-options-table-manager' ) }
			>
				<p className="nhrotm-muted">
					{ data.usage_tracking
						? sprintf(
								/* translators: 1: since date, 2: sampled count. */
								__(
									'Tracking since %1$s · %2$s options seen on real front-end loads.',
									'nhrrob-options-table-manager'
								),
								data.usage_since || '—',
								data.usage_seen
						  )
						: __(
								'Tracking is off. Enable it in Settings to find unused autoloaded options.',
								'nhrrob-options-table-manager'
						  ) }
				</p>
				{ data.unused.length > 0 && (
					<ul className="nhrotm-oplist">
						{ data.unused.map( ( o ) => (
							<li key={ o.name } className="nhrotm-oprow">
								<span className="nhrotm-oprow__name">
									{ o.name }
								</span>
								<span className="nhrotm-oprow__size">
									{ o.size }
								</span>
								<button
									type="button"
									className="nhrotm-btn nhrotm-btn--soft"
									disabled={ busy }
									onClick={ () =>
										action( 'disable-autoload', {
											option: o.name,
										} )
									}
								>
									{ __(
										'Disable autoload',
										'nhrrob-options-table-manager'
									) }
								</button>
							</li>
						) ) }
					</ul>
				) }
				<button
					type="button"
					className="nhrotm-linkbtn"
					disabled={ busy }
					onClick={ () => action( 'reset-usage' ) }
				>
					{ __(
						'Reset tracking data',
						'nhrrob-options-table-manager'
					) }
				</button>
			</Panel>

			<Panel
				anchor="orphans"
				title={ __( 'Orphan scanner', 'nhrrob-options-table-manager' ) }
			>
				{ data.orphans.length === 0 ? (
					<p className="nhrotm-muted">
						{ __(
							'No orphaned option groups detected.',
							'nhrrob-options-table-manager'
						) }
					</p>
				) : (
					<ul className="nhrotm-oplist">
						{ data.orphans.map( ( o ) => (
							<li key={ o.prefix } className="nhrotm-oprow">
								<span className="nhrotm-oprow__name">
									{ o.prefix }
									<span className="nhrotm-badge nhrotm-badge--muted">
										{ o.count }
									</span>
									{ o.source && (
										<em className="nhrotm-oprow__src">
											{ o.source }
										</em>
									) }
								</span>
								<button
									type="button"
									className="nhrotm-linkbtn nhrotm-linkbtn--danger"
									disabled={ busy }
									onClick={ () => {
										if (
											window.confirm(
												sprintf(
													/* translators: %s: option prefix. */
													__(
														'Delete all options starting with "%s"?',
														'nhrrob-options-table-manager'
													),
													o.prefix
												)
											)
										) {
											action( 'delete-orphans', {
												prefix: o.prefix,
											} );
										}
									} }
								>
									{ __(
										'Delete',
										'nhrrob-options-table-manager'
									) }
								</button>
							</li>
						) ) }
					</ul>
				) }
			</Panel>

			<Panel
				anchor="cleanup"
				title={ __( 'Cleanup', 'nhrrob-options-table-manager' ) }
			>
				<p className="nhrotm-muted">
					{ sprintf(
						/* translators: %s: expired transient count. */
						_n(
							'%s expired transient.',
							'%s expired transients.',
							data.expired_transients,
							'nhrrob-options-table-manager'
						),
						data.expired_transients
					) }
				</p>
				<div className="nhrotm-actions">
					<button
						type="button"
						className="nhrotm-btn nhrotm-btn--primary"
						disabled={ busy || data.expired_transients === 0 }
						onClick={ () =>
							action( 'clean-transients', { scope: 'expired' } )
						}
					>
						{ __(
							'Delete expired',
							'nhrrob-options-table-manager'
						) }
					</button>
					<button
						type="button"
						className="nhrotm-btn nhrotm-btn--soft"
						disabled={ busy }
						onClick={ () => {
							if (
								window.confirm(
									__(
										'Delete ALL transients (including active)?',
										'nhrrob-options-table-manager'
									)
								)
							) {
								action( 'clean-transients', { scope: 'all' } );
							}
						} }
					>
						{ __( 'Delete all', 'nhrrob-options-table-manager' ) }
					</button>
				</div>
				<div className="nhrotm-actions">
					<span className="nhrotm-hint">
						{ __(
							'Custom hourly→monthly scheduling',
							'nhrrob-options-table-manager'
						) }
					</span>
					<ProTag hasPro={ hasPro } onNavigate={ onNavigate } />
				</div>
			</Panel>
		</div>
	);
}
