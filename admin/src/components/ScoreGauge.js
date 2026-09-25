/**
 * Health score ring — inline SVG, no chart library (protects the bundle budget).
 *
 * @param {number} score Health score, 0–100.
 * @return {string} CSS color for the score band.
 */
function band( score ) {
	if ( score >= 80 ) {
		return 'var(--nhrotm-success)';
	}
	if ( score >= 50 ) {
		return 'var(--nhrotm-warning)';
	}
	return 'var(--nhrotm-danger)';
}

export default function ScoreGauge( { score = 0, size = 120, stroke = 12 } ) {
	const clamped = Math.max( 0, Math.min( 100, score ) );
	const r = ( size - stroke ) / 2;
	const c = 2 * Math.PI * r;
	const offset = c * ( 1 - clamped / 100 );

	return (
		<div className="nhrotm-gauge" style={ { width: size, height: size } }>
			<svg
				width={ size }
				height={ size }
				viewBox={ `0 0 ${ size } ${ size }` }
			>
				<circle
					cx={ size / 2 }
					cy={ size / 2 }
					r={ r }
					fill="none"
					stroke="var(--nhrotm-border)"
					strokeWidth={ stroke }
				/>
				<circle
					cx={ size / 2 }
					cy={ size / 2 }
					r={ r }
					fill="none"
					stroke={ band( clamped ) }
					strokeWidth={ stroke }
					strokeLinecap="round"
					strokeDasharray={ c }
					strokeDashoffset={ offset }
					transform={ `rotate(-90 ${ size / 2 } ${ size / 2 })` }
				/>
			</svg>
			<div className="nhrotm-gauge__value">
				<strong>{ clamped }</strong>
				<span>/ 100</span>
			</div>
		</div>
	);
}
