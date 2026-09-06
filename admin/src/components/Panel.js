/**
 * Titled card container — the base surface for section content.
 *
 * @param {Object} root0          Props.
 * @param {string} root0.title    Panel heading.
 * @param {string} root0.anchor   Optional deep-link target; becomes the DOM id
 *                                so cross-section links can scroll to it.
 * @param {Object} root0.meta     Optional right-aligned header note (e.g. "3 items").
 * @param {Object} root0.actions  Optional header actions.
 * @param {Object} root0.children Panel body.
 * @return {Object} Panel element.
 */
export default function Panel( { title, anchor, meta, actions, children } ) {
	return (
		<section
			className="nhrotm-panel"
			id={ anchor ? 'nhrotm-panel-' + anchor : undefined }
		>
			{ ( title || meta || actions ) && (
				<header className="nhrotm-panel__head">
					{ title && (
						<h2 className="nhrotm-panel__title">{ title }</h2>
					) }
					{ meta && (
						<span className="nhrotm-panel__meta">{ meta }</span>
					) }
					{ actions && (
						<div className="nhrotm-panel__actions">{ actions }</div>
					) }
				</header>
			) }
			<div className="nhrotm-panel__body">{ children }</div>
		</section>
	);
}
