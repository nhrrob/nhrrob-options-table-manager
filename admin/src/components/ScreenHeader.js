/**
 * Screen header — page title + one-line lede shown at the top of every section
 * (matches the mockup's screen__head). Purely presentational.
 *
 * @param {Object} root0       Props.
 * @param {string} root0.title Section title.
 * @param {string} root0.lede  Optional supporting sentence.
 * @return {Object} Header element.
 */
export default function ScreenHeader( { title, lede } ) {
	return (
		<div className="nhrotm-screen__head">
			<h1 className="nhrotm-screen__title">{ title }</h1>
			{ lede && <p className="nhrotm-screen__lede">{ lede }</p> }
		</div>
	);
}
