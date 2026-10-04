/**
 * Option History — every recorded version of one option, opened from a
 * Browse row. Wired to GET nhrotm/v1/option-history and the Activity
 * restore route. Reuses the EditModal shell and the data-grid styles.
 */
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

import Icon from './Icon';
import { useConfirm } from './ConfirmProvider';
import { useToast } from './ToastProvider';

export default function HistoryModal( { name, onClose, onRestored } ) {
	const [ rows, setRows ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const confirm = useConfirm();
	const toast = useToast();

	const load = useCallback( () => {
		apiFetch( {
			path: `nhrotm/v1/option-history?name=${ encodeURIComponent(
				name
			) }`,
		} )
			.then( ( res ) => setRows( res.data ) )
			.catch( () => setRows( [] ) );
	}, [ name ] );

	useEffect( () => {
		load();
	}, [ load ] );

	const restore = async ( row ) => {
		if (
			! ( await confirm(
				__(
					'Restore this option to this version?',
					'nhrrob-options-table-manager'
				),
				{
					description: __(
						'The current value is logged first, so this restore can itself be undone.',
						'nhrrob-options-table-manager'
					),
					confirmLabel: __(
						'Restore',
						'nhrrob-options-table-manager'
					),
				}
			) )
		) {
			return;
		}
		setBusy( true );
		apiFetch( {
			path: `nhrotm/v1/dashboard/activity/${ row.id }/restore`,
			method: 'POST',
		} )
			.then( () => {
				toast(
					__(
						'Option restored successfully.',
						'nhrrob-options-table-manager'
					)
				);
				load();
				onRestored();
			} )
			.catch( () =>
				toast(
					__(
						'This version could not be restored.',
						'nhrrob-options-table-manager'
					),
					'error'
				)
			)
			.finally( () => setBusy( false ) );
	};

	const title = sprintf(
		/* translators: %s: option name. */
		__( 'History: %s', 'nhrrob-options-table-manager' ),
		name
	);

	return (
		<div
			className="nhrotm-modal__overlay"
			role="presentation"
			onMouseDown={ ( e ) => {
				if ( e.target === e.currentTarget ) {
					onClose();
				}
			} }
			onKeyDown={ ( e ) => {
				if ( e.key === 'Escape' ) {
					onClose();
				}
			} }
		>
			<div
				className="nhrotm-modal nhrotm-modal--wide"
				role="dialog"
				aria-modal="true"
				aria-label={ title }
			>
				<header className="nhrotm-modal__head">
					<h2>{ title }</h2>
					<button
						type="button"
						className="nhrotm-modal__close"
						aria-label={ __(
							'Close',
							'nhrrob-options-table-manager'
						) }
						onClick={ onClose }
					>
						×
					</button>
				</header>
				<div className="nhrotm-modal__body">
					{ rows === null && (
						<p className="nhrotm-muted">
							{ __( 'Loading…', 'nhrrob-options-table-manager' ) }
						</p>
					) }
					{ rows && rows.length === 0 && (
						<p className="nhrotm-muted">
							{ __(
								'No recorded changes for this option yet.',
								'nhrrob-options-table-manager'
							) }
						</p>
					) }
					{ rows && rows.length > 0 && (
						<div className="nhrotm-grid__scroll">
							<table className="nhrotm-grid">
								<colgroup>
									<col className="nhrotm-col-badge-wide" />
									<col />
									<col className="nhrotm-col-owner" />
									<col className="nhrotm-col-date" />
									<col className="nhrotm-col-act-sm" />
								</colgroup>
								<thead>
									<tr>
										<th>
											{ __(
												'Change',
												'nhrrob-options-table-manager'
											) }
										</th>
										<th>
											{ __(
												'Previous value',
												'nhrrob-options-table-manager'
											) }
										</th>
										<th>
											{ __(
												'Who',
												'nhrrob-options-table-manager'
											) }
										</th>
										<th>
											{ __(
												'When',
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
									{ rows.map( ( r ) => (
										<tr key={ r.id }>
											<td>
												<span className="nhrotm-badge nhrotm-badge--muted">
													{ r.prefix }
												</span>
											</td>
											<td title={ r.value }>
												<div className="nhrotm-grid__preview">
													{ r.value || '—' }
												</div>
											</td>
											<td>{ r.who }</td>
											<td className="nhrotm-muted">
												{ r.when }
											</td>
											<td>
												{ r.restorable && (
													<div className="nhrotm-grid__actions">
														<button
															type="button"
															className="nhrotm-iconbtn"
															disabled={ busy }
															onClick={ () =>
																restore( r )
															}
														>
															<Icon
																name="refresh"
																size={ 14 }
															/>
															{ __(
																'Restore',
																'nhrrob-options-table-manager'
															) }
														</button>
													</div>
												) }
											</td>
										</tr>
									) ) }
								</tbody>
							</table>
						</div>
					) }
				</div>
				<footer className="nhrotm-modal__foot">
					<button
						type="button"
						className="nhrotm-btn nhrotm-btn--soft"
						onClick={ onClose }
					>
						{ __( 'Close', 'nhrrob-options-table-manager' ) }
					</button>
				</footer>
			</div>
		</div>
	);
}
