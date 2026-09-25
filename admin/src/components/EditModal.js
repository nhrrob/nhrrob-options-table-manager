/**
 * Edit/Add modal for options, usermeta, postmeta, commentmeta & termmeta
 * records.
 *
 * Structured values (serialized arrays / JSON) are edited as pretty JSON and
 * converted back on save; scalars stay raw. Supports adding options, usermeta
 * (needs a user ID), postmeta (needs a post ID), commentmeta (needs a comment
 * ID) and termmeta (needs a term ID).
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
	const [ postId, setPostId ] = useState( '' );
	const [ commentId, setCommentId ] = useState( '' );
	const [ termId, setTermId ] = useState( '' );
	const [ autoload, setAutoload ] = useState(
		record && record.autoload ? record.autoload : 'yes'
	);
	const [ expiration, setExpiration ] = useState(
		record && record.expiration !== undefined && record.expiration !== null
			? record.expiration
			: 3600
	);

	const isUsermetaAdd = isNew && type === 'usermeta';
	const isPostmetaAdd = isNew && type === 'postmeta';
	const isCommentmetaAdd = isNew && type === 'commentmeta';
	const isTermmetaAdd = isNew && type === 'termmeta';
	const isTransient = type === 'transients';
	const isMetaType = [
		'usermeta',
		'postmeta',
		'commentmeta',
		'termmeta',
	].includes( type );

	let title;
	if ( ! isNew ) {
		title = __( 'Edit record', 'nhrrob-options-table-manager' );
	} else if ( type === 'usermeta' ) {
		title = __( 'Add usermeta', 'nhrrob-options-table-manager' );
	} else if ( type === 'postmeta' ) {
		title = __( 'Add postmeta', 'nhrrob-options-table-manager' );
	} else if ( type === 'commentmeta' ) {
		title = __( 'Add commentmeta', 'nhrrob-options-table-manager' );
	} else if ( type === 'termmeta' ) {
		title = __( 'Add termmeta', 'nhrrob-options-table-manager' );
	} else if ( isTransient ) {
		title = __( 'Add transient', 'nhrrob-options-table-manager' );
	} else {
		title = __( 'Add option', 'nhrrob-options-table-manager' );
	}

	const canSave =
		! busy &&
		( ! isNew || name ) &&
		( ! isUsermetaAdd || ( name && userId ) ) &&
		( ! isPostmetaAdd || ( name && postId ) ) &&
		( ! isCommentmetaAdd || ( name && commentId ) ) &&
		( ! isTermmetaAdd || ( name && termId ) );

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
								value={ userId }
								onChange={ ( e ) =>
									setUserId( e.target.value )
								}
							/>
						</div>
					) }

					{ isPostmetaAdd && (
						<div className="nhrotm-field">
							<label htmlFor="nhrotm-modal-postid">
								{ __(
									'Post ID',
									'nhrrob-options-table-manager'
								) }
							</label>
							<input
								id="nhrotm-modal-postid"
								type="number"
								min="1"
								value={ postId }
								onChange={ ( e ) =>
									setPostId( e.target.value )
								}
							/>
						</div>
					) }

					{ isCommentmetaAdd && (
						<div className="nhrotm-field">
							<label htmlFor="nhrotm-modal-commentid">
								{ __(
									'Comment ID',
									'nhrrob-options-table-manager'
								) }
							</label>
							<input
								id="nhrotm-modal-commentid"
								type="number"
								min="1"
								value={ commentId }
								onChange={ ( e ) =>
									setCommentId( e.target.value )
								}
							/>
						</div>
					) }

					{ isTermmetaAdd && (
						<div className="nhrotm-field">
							<label htmlFor="nhrotm-modal-termid">
								{ __(
									'Term ID',
									'nhrrob-options-table-manager'
								) }
							</label>
							<input
								id="nhrotm-modal-termid"
								type="number"
								min="1"
								value={ termId }
								onChange={ ( e ) =>
									setTermId( e.target.value )
								}
							/>
						</div>
					) }

					<div className="nhrotm-field">
						<label htmlFor="nhrotm-modal-name">
							{ isMetaType
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

					{ isTransient && (
						<div className="nhrotm-field">
							<label htmlFor="nhrotm-modal-expiration">
								{ __(
									'Expires in (seconds)',
									'nhrrob-options-table-manager'
								) }
							</label>
							<input
								id="nhrotm-modal-expiration"
								type="number"
								min="0"
								value={ expiration }
								onChange={ ( e ) =>
									setExpiration( e.target.value )
								}
							/>
							<span className="nhrotm-hint">
								{ __(
									'0 means the transient never expires.',
									'nhrrob-options-table-manager'
								) }
							</span>
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
								post_id: postId ? parseInt( postId, 10 ) : 0,
								comment_id: commentId
									? parseInt( commentId, 10 )
									: 0,
								term_id: termId ? parseInt( termId, 10 ) : 0,
								expiration: isTransient
									? parseInt( expiration, 10 ) || 0
									: 0,
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
