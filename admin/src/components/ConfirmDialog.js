/**
 * Confirmation modal — replaces window.confirm() with UI that matches the
 * rest of the app instead of the browser's native dialog chrome.
 */
import { useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * @param {Object}   root0                Props.
 * @param {string}   root0.message        Question shown as the dialog title.
 * @param {string}   [root0.description]  Supporting detail shown below the title.
 * @param {string}   [root0.confirmLabel] Confirm button label.
 * @param {string}   [root0.cancelLabel]  Cancel button label.
 * @param {boolean}  [root0.danger]       Whether the confirm button reads as destructive (default true).
 * @param {Function} root0.onConfirm      Called when the user confirms.
 * @param {Function} root0.onCancel       Called when the user cancels/dismisses.
 * @return {Object} Modal element.
 */
export default function ConfirmDialog( {
	message,
	description,
	confirmLabel,
	cancelLabel,
	danger = true,
	onConfirm,
	onCancel,
} ) {
	// Cancel is the safe default focus target — pressing Enter right after
	// the dialog opens (e.g. a stray keypress from the action that opened it)
	// should never land on the destructive action.
	const cancelRef = useRef( null );
	useEffect( () => {
		if ( cancelRef.current ) {
			cancelRef.current.focus();
		}
	}, [] );

	return (
		<div
			className="nhrotm-modal__overlay"
			role="presentation"
			onMouseDown={ ( e ) => {
				if ( e.target === e.currentTarget ) {
					onCancel();
				}
			} }
			onKeyDown={ ( e ) => {
				if ( e.key === 'Escape' ) {
					onCancel();
				}
			} }
		>
			<div
				className="nhrotm-modal nhrotm-modal--sm"
				role="alertdialog"
				aria-modal="true"
				aria-label={ message }
			>
				<header className="nhrotm-modal__head">
					<h2>{ message }</h2>
					<button
						type="button"
						className="nhrotm-modal__close"
						aria-label={ __(
							'Close',
							'nhrrob-options-table-manager'
						) }
						onClick={ onCancel }
					>
						×
					</button>
				</header>

				{ description && (
					<div className="nhrotm-modal__body">
						<p className="nhrotm-modal__confirm-text">
							{ description }
						</p>
					</div>
				) }

				<footer className="nhrotm-modal__foot">
					<button
						ref={ cancelRef }
						type="button"
						className="nhrotm-btn nhrotm-btn--soft"
						onClick={ onCancel }
					>
						{ cancelLabel ||
							__( 'Cancel', 'nhrrob-options-table-manager' ) }
					</button>
					<button
						type="button"
						className={
							'nhrotm-btn ' +
							( danger
								? 'nhrotm-btn--danger'
								: 'nhrotm-btn--primary' )
						}
						onClick={ onConfirm }
					>
						{ confirmLabel ||
							__( 'Confirm', 'nhrrob-options-table-manager' ) }
					</button>
				</footer>
			</div>
		</div>
	);
}
