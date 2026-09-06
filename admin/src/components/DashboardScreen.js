/**
 * Dashboard — live health score + stat cards + recommendations + activity.
 * Wired to nhrotm/v1/dashboard (HealthService).
 */
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf, _n } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

import ScoreGauge from './ScoreGauge';
import Panel from './Panel';
import Icon from './Icon';
import ScreenHeader from './ScreenHeader';
import ActivityPanel from './ActivityPanel';

// Per-card chrome (icon, tint, accent, label) keyed by the stable card id the
// server sends. Metric, sub-line and the action label travel in the payload —
// they carry live counts, so they belong where the counts are.
const CARD_META = {
	autoload: {
		tint: 'nhrotm-card--autoload',
		icon: 'optimize',
		accent: 'var(--nhrotm-info)',
		label: __( 'Autoload', 'nhrrob-options-table-manager' ),
	},
	options: {
		tint: 'nhrotm-card--options',
		icon: 'database',
		accent: 'var(--nhrotm-primary)',
		label: __( 'Options', 'nhrrob-options-table-manager' ),
	},
	transients: {
		tint: 'nhrotm-card--transients',
		icon: 'clock',
		accent: 'var(--nhrotm-warning)',
		label: __( 'Transients', 'nhrrob-options-table-manager' ),
	},
	backup: {
		tint: 'nhrotm-card--backup',
		icon: 'refresh',
		accent: 'var(--nhrotm-success)',
		label: __( 'Last backup', 'nhrrob-options-table-manager' ),
	},
};

// A recommendation the user should act on now gets the solid button; the rest
// stay soft so the panel has exactly one visual priority.
const URGENT = [ 'danger', 'warning' ];

/**
 * Dashboard screen.
 *
 * @param {Object}   root0            Props.
 * @param {Function} root0.onNavigate Section navigation callback.
 * @return {Object} Dashboard element.
 */
export default function DashboardScreen( { onNavigate } ) {
	const [ summary, setSummary ] = useState( null );
	const [ status, setStatus ] = useState( 'loading' );

	useEffect( () => {
		apiFetch( { path: 'nhrotm/v1/dashboard' } )
			.then( ( res ) => {
				setSummary( res.data );
				setStatus( 'ready' );
			} )
			.catch( () => setStatus( 'error' ) );
	}, [] );

	if ( status === 'loading' ) {
		return (
			<Panel>
				<p className="nhrotm-muted">
					{ __( 'Loading health…', 'nhrrob-options-table-manager' ) }
				</p>
			</Panel>
		);
	}

	if ( status === 'error' || ! summary ) {
		return (
			<Panel>
				<p className="nhrotm-error">
					{ __(
						'Could not load the dashboard.',
						'nhrrob-options-table-manager'
					) }
				</p>
			</Panel>
		);
	}

	// focus is the panel anchor within the target section, so a recommendation
	// lands on the control that resolves it rather than the top of the screen.
	const go = ( section, focus ) =>
		onNavigate && section && onNavigate( section, focus );
	const recs = summary.recommendations || [];

	return (
		<div className="nhrotm-dashboard">
			<ScreenHeader
				title={ __( 'Dashboard', 'nhrrob-options-table-manager' ) }
				lede={ __(
					'Is your options table healthy — and what should you do next?',
					'nhrrob-options-table-manager'
				) }
			/>

			<Panel>
				<div className="nhrotm-health">
					<ScoreGauge score={ summary.score } size={ 132 } />
					<div className="nhrotm-health__copy">
						<h3>{ summary.headline }</h3>
						{ summary.description && (
							<p className="nhrotm-muted">
								{ summary.description }
							</p>
						) }
						{ summary.stats && summary.stats.length > 0 && (
							<div className="nhrotm-health__stats">
								{ summary.stats.map( ( stat, i ) => (
									<span key={ i }>
										{ stat.before ? (
											<>
												{ stat.label }{ ' ' }
												<b>{ stat.value }</b>
											</>
										) : (
											<>
												<b>{ stat.value }</b>{ ' ' }
												{ stat.label }
											</>
										) }
									</span>
								) ) }
							</div>
						) }
					</div>
				</div>
			</Panel>

			{ summary.cards && summary.cards.length > 0 && (
				<div className="nhrotm-cards">
					{ summary.cards.map( ( card ) => {
						const meta = CARD_META[ card.id ] || {};
						return (
							<div
								key={ card.id }
								className={
									'nhrotm-card ' + ( meta.tint || '' )
								}
							>
								<div className="nhrotm-card__top">
									<span
										className="nhrotm-card__ico"
										style={ { color: meta.accent } }
									>
										<Icon
											name={ meta.icon || 'database' }
											size={ 16 }
										/>
									</span>
									<span className="nhrotm-card__label">
										{ meta.label || card.id }
									</span>
								</div>
								<span className="nhrotm-card__metric">
									{ card.metric }
								</span>
								<span className="nhrotm-card__sub">
									{ card.sub }
								</span>
								{ card.section && (
									<div className="nhrotm-card__action">
										<button
											type="button"
											className="nhrotm-btn nhrotm-btn--soft nhrotm-btn--sm"
											onClick={ () =>
												go( card.section, card.focus )
											}
										>
											{ card.action ||
												__(
													'View →',
													'nhrrob-options-table-manager'
												) }
										</button>
									</div>
								) }
							</div>
						);
					} ) }
				</div>
			) }

			<Panel
				title={ __(
					'Recommendations',
					'nhrrob-options-table-manager'
				) }
				meta={
					recs.length > 0
						? sprintf(
								/* translators: %s: number of open recommendations. */
								_n(
									'%s item',
									'%s items',
									recs.length,
									'nhrrob-options-table-manager'
								),
								recs.length
						  )
						: undefined
				}
			>
				{ recs.length > 0 ? (
					<ul className="nhrotm-recs">
						{ recs.map( ( rec, i ) => (
							<li
								key={ i }
								className={
									'nhrotm-rec nhrotm-rec--' + rec.severity
								}
							>
								<span className="nhrotm-rec__text">
									<Icon
										name={ rec.icon || 'alert' }
										size={ 17 }
										className="nhrotm-rec__sev"
									/>
									{ rec.text }
								</span>
								{ rec.section && (
									<button
										type="button"
										className={
											'nhrotm-btn nhrotm-btn--sm ' +
											( URGENT.includes( rec.severity )
												? 'nhrotm-btn--primary'
												: 'nhrotm-btn--soft' )
										}
										onClick={ () =>
											go( rec.section, rec.focus )
										}
									>
										{ rec.action ||
											__(
												'Fix',
												'nhrrob-options-table-manager'
											) }
									</button>
								) }
							</li>
						) ) }
					</ul>
				) : (
					<p className="nhrotm-muted">
						{ __(
							'Nothing to fix — your options table is healthy. 🎉',
							'nhrrob-options-table-manager'
						) }
					</p>
				) }
			</Panel>

			<ActivityPanel recent={ summary.activity || [] } />
		</div>
	);
}
