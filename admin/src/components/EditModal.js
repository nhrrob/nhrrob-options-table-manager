/**
 * Edit/Add modal for options & usermeta records.
 *
 * Structured values (serialized arrays / JSON) are edited as pretty JSON and
 * converted back on save; scalars stay raw. Supports adding options and
 * usermeta (usermeta add needs a user ID).
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const FORMAT_LABEL = {
	serialized: __(
		'serialized (edit as JSON)',
		'nhrrob-options-table-manager'
	),
	json: __( 'JSON', 'nhrrob-options-table-manager' ),
	plain: __( 'text', 'nhrrob-options-table-manager' ),
};

/**
 * @param {Object}   root0         Props.
 * @param {string}   root0.type    Record type.
 * @param {Object}   root0.record  Existing record, or null for a new one.
 * @param {Function} root0.onSave  Save callback.
 * @param {Function} root0.onClose Close callback.
 * @param {boolean}  root0.busy    Whether a save is in flight.
 * @return {Object} Modal element.
 */
export default function EditModal( { type, record, onSave, onClose, busy } ) {
	const isNew = ! record || ! record.id;
	const [ name, setName ] = useState( record ? record.name : '' );
	const [ value, setValue ] = useState( record ? record.value : '' );
	const [ format ] = useState(
		record && record.format ? record.format : 'plain'
	);
	const [ userId, setUserId ] = useState( '' );
	const [ autoload, setAutoload ] = useState(
		record && record.autoload ? record.autoload : 'yes'
	);

	const isUsermetaAdd = isNew && type === 'usermeta';

	let title;
	if ( ! isNew ) {
		title = __( 'Edit record', 'nhrrob-options-table-manager' );
	} else if ( type === 'usermeta' ) {
		title = __( 'Add usermeta', 'nhrrob-options-table-manager' );
	} else {
		title = __( 'Add option', 'nhrrob-options-table-manager' );
	}

	const canSave =
		! busy &&
		( ! isNew || name ) &&
		( ! isUsermetaAdd || ( name && userId ) );

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
				className="nhrotm-modal"
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
					{ isUsermetaAdd && (
						<div className="nhrotm-field">
							<label htmlFor="nhrotm-modal-userid">
								{ __(
									'User ID',
									'nhrrob-options-table-manager'
								) }
							</label>
							<input
								id="nhrotm-modal-userid"
								type="number"
								min="1"
								className="nhrotm-browse__search"
								value={ userId }
								onChange={ ( e ) =>
									setUserId( e.target.value )
								}
							/>
						</div>
					) }

					<div className="nhrotm-field">
						<label htmlFor="nhrotm-modal-name">
							{ type === 'usermeta'
								? __(
										'Meta key',
										'nhrrob-options-table-manager'
								  )
								: __(
										'Option name',
										'nhrrob-options-table-manager'
								  ) }
						</label>
						<input
							id="nhrotm-modal-name"
							type="text"
							className="nhrotm-browse__search"
							value={ name }
							disabled={ ! isNew }
							onChange={ ( e ) => setName( e.target.value ) }
						/>
					</div>

					<div className="nhrotm-field">
						<label htmlFor="nhrotm-modal-value">
							{ __( 'Value', 'nhrrob-options-table-manager' ) }
							{ ! isNew && format !== 'plain' && (
								<span className="nhrotm-badge nhrotm-badge--info">
									{ FORMAT_LABEL[ format ] }
								</span>
							) }
						</label>
						<textarea
							id="nhrotm-modal-value"
							className="nhrotm-modal__textarea"
							rows="10"
							value={ value }
							onChange={ ( e ) => setValue( e.target.value ) }
						/>
					</div>

					{ type === 'options' && (
						<div className="nhrotm-field">
							<label htmlFor="nhrotm-modal-autoload">
								<input
									id="nhrotm-modal-autoload"
									type="checkbox"
									checked={ autoload === 'yes' }
									onChange={ ( e ) =>
										setAutoload(
											e.target.checked ? 'yes' : 'no'
										)
									}
								/>
								{ __(
									'Autoload',
									'nhrrob-options-table-manager'
								) }
							</label>
						</div>
					) }
				</div>

				<footer className="nhrotm-modal__foot">
					<button
						type="button"
						className="nhrotm-btn nhrotm-btn--soft"
						onClick={ onClose }
					>
						{ __( 'Cancel', 'nhrrob-options-table-manager' ) }
					</button>
					<button
						type="button"
						className="nhrotm-btn nhrotm-btn--primary"
						disabled={ ! canSave }
						onClick={ () =>
							onSave( {
								id: record ? record.id : 0,
								name,
								value,
								format,
								autoload,
								user_id: userId ? parseInt( userId, 10 ) : 0,
							} )
						}
					>
						{ __( 'Save', 'nhrrob-options-table-manager' ) }
					</button>
				</footer>
			</div>
		</div>
	);
}
