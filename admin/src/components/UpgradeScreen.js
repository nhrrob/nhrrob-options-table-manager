/**
 * Upgrade — the free build's single, calm PRO-awareness surface (PRD §0.2).
 * A plain free-vs-PRO comparison and exactly one outbound CTA. No banners,
 * no timers, no second call to action.
 */
import { __ } from '@wordpress/i18n';

import ScreenHeader from './ScreenHeader';

const ROWS = [
	{
		cap: __(
			'Browse / edit options, usermeta, transients',
			'nhrrob-options-table-manager'
		),
		free: {
			t: __( 'Included', 'nhrrob-options-table-manager' ),
			cls: 'yes',
		},
		pro: {
			t: __( 'Included', 'nhrrob-options-table-manager' ),
			cls: 'yes',
		},
	},
	{
		cap: __(
			'Autoload health & manual toggle',
			'nhrrob-options-table-manager'
		),
		free: {
			t: __( 'Included', 'nhrrob-options-table-manager' ),
			cls: 'yes',
		},
		pro: {
			t: __( 'Included', 'nhrrob-options-table-manager' ),
			cls: 'yes',
		},
	},
	{
		cap: __( 'Autoload Usage Tracker', 'nhrrob-options-table-manager' ),
		free: { t: __( 'Full', 'nhrrob-options-table-manager' ), cls: 'yes' },
		pro: {
			t: __(
				'+ auto-disable · per-page breakdown',
				'nhrrob-options-table-manager'
			),
			cls: 'plus',
		},
	},
	{
		cap: __( 'Cleanup', 'nhrrob-options-table-manager' ),
		free: { t: __( 'Manual', 'nhrrob-options-table-manager' ), cls: '' },
		pro: {
			t: __( 'Scheduled hourly→monthly', 'nhrrob-options-table-manager' ),
			cls: 'plus',
		},
	},
	{
		cap: __( 'Search & Replace', 'nhrrob-options-table-manager' ),
		free: { t: __( 'Dry-run', 'nhrrob-options-table-manager' ), cls: '' },
		pro: {
			t: __(
				'+ Regex, all tables, scheduled',
				'nhrrob-options-table-manager'
			),
			cls: 'plus',
		},
	},
	{
		cap: __( 'Backups', 'nhrrob-options-table-manager' ),
		free: { t: __( '15, local', 'nhrrob-options-table-manager' ), cls: '' },
		pro: {
			t: __(
				'Unlimited + off-site + recovery',
				'nhrrob-options-table-manager'
			),
			cls: 'plus',
		},
	},
	{
		cap: __( 'Reports & alerts', 'nhrrob-options-table-manager' ),
		free: { t: '—', cls: 'no' },
		pro: {
			t: __(
				'Email health + threshold alerts',
				'nhrrob-options-table-manager'
			),
			cls: 'plus',
		},
	},
	{
		cap: __( 'Multisite / network', 'nhrrob-options-table-manager' ),
		free: { t: '—', cls: 'no' },
		pro: {
			t: __( 'Included', 'nhrrob-options-table-manager' ),
			cls: 'yes',
		},
	},
];

const PLANS_URL = 'https://wordpress.org/plugins/nhrrob-options-table-manager/';

export default function UpgradeScreen( { boot } ) {
	const url = ( boot && boot.upgradeUrl ) || PLANS_URL;

	return (
		<div className="nhrotm-upgrade">
			<ScreenHeader
				title={ __( 'Upgrade', 'nhrrob-options-table-manager' ) }
				lede={ __(
					'Everything you use today stays free. Pro adds automation, scale and off-site safety on top.',
					'nhrrob-options-table-manager'
				) }
			/>
			<div className="nhrotm-upgrade__hero">
				<div className="nhrotm-upgrade__eyebrow">
					{ __(
						'Options Table Manager Pro',
						'nhrrob-options-table-manager'
					) }
				</div>
				<h2 className="nhrotm-upgrade__title">
					{ __(
						'Set it, schedule it, and sleep easy.',
						'nhrrob-options-table-manager'
					) }
				</h2>
				<p className="nhrotm-upgrade__lede">
					{ __(
						'Everything you use today stays free. Pro automates the routine — scheduled cleanup, auto-disabling unused autoloads, and off-site backups with one-click recovery.',
						'nhrrob-options-table-manager'
					) }
				</p>
			</div>

			<div className="nhrotm-panel">
				<div
					className="nhrotm-panel__body"
					style={ { padding: '6px 8px' } }
				>
					<div className="nhrotm-grid__scroll">
						<table className="nhrotm-compare">
							<thead>
								<tr>
									<th>
										{ __(
											'Capability',
											'nhrrob-options-table-manager'
										) }
									</th>
									<th>
										{ __(
											'Free',
											'nhrrob-options-table-manager'
										) }
									</th>
									<th className="nhrotm-compare__pro">
										{ __(
											'Pro',
											'nhrrob-options-table-manager'
										) }
									</th>
								</tr>
							</thead>
							<tbody>
								{ ROWS.map( ( r ) => (
									<tr key={ r.cap }>
										<td className="nhrotm-compare__cap">
											{ r.cap }
										</td>
										<td className={ r.free.cls }>
											{ r.free.t }
										</td>
										<td
											className={
												'nhrotm-compare__pro ' +
												r.pro.cls
											}
										>
											{ r.pro.t }
										</td>
									</tr>
								) ) }
							</tbody>
						</table>
					</div>
				</div>
			</div>

			<div className="nhrotm-upgrade__cta">
				<a
					className="nhrotm-btn nhrotm-btn--primary"
					href={ url }
					target="_blank"
					rel="noopener noreferrer"
				>
					{ __( 'View plans →', 'nhrrob-options-table-manager' ) }
				</a>
				<span className="nhrotm-upgrade__fine">
					{ __(
						'One plugin, no bundle.',
						'nhrrob-options-table-manager'
					) }
				</span>
			</div>
		</div>
	);
}
