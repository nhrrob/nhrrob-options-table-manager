/**
 * Settings screen — first section wired live to nhrotm/v1/settings.
 * Proves the React → REST → SettingsService round-trip.
 */
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

import Panel from './Panel';
import ScreenHeader from './ScreenHeader';
import { useToast } from './ToastProvider';

export default function SettingsScreen() {
	const toast = useToast();
	const [ settings, setSettings ] = useState( null );
	const [ status, setStatus ] = useState( 'loading' ); // loading | ready | saving | error

	useEffect( () => {
		apiFetch( { path: 'nhrotm/v1/settings' } )
			.then( ( res ) => {
				setSettings( res.data );
				setStatus( 'ready' );
			} )
			.catch( () => setStatus( 'error' ) );
	}, [] );

	const update = ( key, value ) =>
		setSettings( ( prev ) => ( { ...prev, [ key ]: value } ) );

	const save = () => {
		setStatus( 'saving' );
		apiFetch( {
			path: 'nhrotm/v1/settings',
			method: 'POST',
			data: settings,
		} )
			.then( ( res ) => {
				setSettings( res.data );
				setStatus( 'ready' );
				toast(
					__(
						'Settings saved successfully.',
						'nhrrob-options-table-manager'
					)
				);
			} )
			.catch( () => setStatus( 'error' ) );
	};

	if ( status === 'loading' ) {
		return (
			<Panel title={ __( 'Settings', 'nhrrob-options-table-manager' ) }>
				<p className="nhrotm-muted">
					{ __( 'Loading…', 'nhrrob-options-table-manager' ) }
				</p>
			</Panel>
		);
	}

	if ( status === 'error' && ! settings ) {
		return (
			<Panel title={ __( 'Settings', 'nhrrob-options-table-manager' ) }>
				<p className="nhrotm-error">
					{ __(
						'Could not load settings.',
						'nhrrob-options-table-manager'
					) }
				</p>
			</Panel>
		);
	}

	return (
		<div className="nhrotm-settings">
			<ScreenHeader
				title={ __( 'Settings', 'nhrrob-options-table-manager' ) }
				lede={ __(
					'Every preference in one place — history retention, cleanup and backup schedules.',
					'nhrrob-options-table-manager'
				) }
			/>
			<Panel title={ __( 'Settings', 'nhrrob-options-table-manager' ) }>
				<p className="nhrotm-muted">
					{ __(
						'Every preference below is applied immediately on save.',
						'nhrrob-options-table-manager'
					) }
				</p>
				<div className="nhrotm-field">
					<label htmlFor="nhrotm-allow-html">
						<input
							id="nhrotm-allow-html"
							type="checkbox"
							checked={ !! settings.allow_html_in_values }
							onChange={ ( e ) =>
								update(
									'allow_html_in_values',
									e.target.checked
								)
							}
						/>
						{ __(
							'Allow HTML in option values',
							'nhrrob-options-table-manager'
						) }
					</label>
					<p className="nhrotm-field__hint">
						{ __(
							'Skips sanitization on save — only enable this if you trust every user who can edit options here.',
							'nhrrob-options-table-manager'
						) }
					</p>
				</div>

				<div className="nhrotm-field">
					<label htmlFor="nhrotm-usage-tracking">
						<input
							id="nhrotm-usage-tracking"
							type="checkbox"
							checked={ !! settings.usage_tracking_enabled }
							onChange={ ( e ) =>
								update(
									'usage_tracking_enabled',
									e.target.checked
								)
							}
						/>
						{ __(
							'Enable autoload usage tracking',
							'nhrrob-options-table-manager'
						) }
					</label>
					<p className="nhrotm-field__hint">
						{ __(
							'Required for Optimize\'s autoload Usage column and its "Unused" bulk-disable filter — leave off and those stay empty.',
							'nhrrob-options-table-manager'
						) }
					</p>
				</div>

				<div className="nhrotm-field">
					<label htmlFor="nhrotm-auto-cleanup">
						<input
							id="nhrotm-auto-cleanup"
							type="checkbox"
							checked={ !! settings.auto_cleanup_enabled }
							onChange={ ( e ) =>
								update(
									'auto_cleanup_enabled',
									e.target.checked
								)
							}
						/>
						{ __(
							'Enable automated daily cleanup',
							'nhrrob-options-table-manager'
						) }
					</label>
					<p className="nhrotm-field__hint">
						{ __(
							'Automatically clears expired transients every day — the automated counterpart to Optimize Cleanup\'s manual "Delete expired" button.',
							'nhrrob-options-table-manager'
						) }
					</p>
				</div>

				<div className="nhrotm-field">
					<label htmlFor="nhrotm-backup-frequency">
						{ __(
							'Scheduled backups',
							'nhrrob-options-table-manager'
						) }
					</label>
					<select
						id="nhrotm-backup-frequency"
						value={ settings.backup_frequency }
						onChange={ ( e ) =>
							update( 'backup_frequency', e.target.value )
						}
					>
						<option value="off">
							{ __( 'Off', 'nhrrob-options-table-manager' ) }
						</option>
						<option value="daily">
							{ __( 'Daily', 'nhrrob-options-table-manager' ) }
						</option>
						<option value="weekly">
							{ __( 'Weekly', 'nhrrob-options-table-manager' ) }
						</option>
					</select>
					<p className="nhrotm-field__hint">
						{ __(
							'Only the last 15 snapshots are ever kept (Tools → Backups) — "Daily" quietly exhausts that cap in two weeks.',
							'nhrrob-options-table-manager'
						) }
					</p>
				</div>

				<div className="nhrotm-field">
					<label htmlFor="nhrotm-retention">
						{ __(
							'History retention (days)',
							'nhrrob-options-table-manager'
						) }
					</label>
					<input
						id="nhrotm-retention"
						type="number"
						min="1"
						value={ settings.history_retention_days }
						onChange={ ( e ) =>
							update(
								'history_retention_days',
								parseInt( e.target.value, 10 ) || 0
							)
						}
					/>
					<p className="nhrotm-field__hint">
						{ __(
							"How long the option-change audit log (Dashboard's Recent Activity) keeps entries — rows past this many days are purged.",
							'nhrrob-options-table-manager'
						) }
					</p>
				</div>

				<div className="nhrotm-actions">
					<button
						type="button"
						className="nhrotm-btn nhrotm-btn--primary"
						disabled={ status === 'saving' }
						onClick={ save }
					>
						{ status === 'saving'
							? __( 'Saving…', 'nhrrob-options-table-manager' )
							: __(
									'Save changes',
									'nhrrob-options-table-manager'
							  ) }
					</button>
					{ status === 'error' && (
						<span className="nhrotm-error">
							{ __(
								'Save failed.',
								'nhrrob-options-table-manager'
							) }
						</span>
					) }
				</div>
			</Panel>
		</div>
	);
}
