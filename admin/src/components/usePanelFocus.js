/**
 * Scrolls a deep-linked panel into view after a cross-section jump.
 *
 * Target screens fetch their data before rendering panels, so the anchor does
 * not exist at navigation time — we poll for it on animation frames until it
 * appears, then give up rather than spin forever.
 */
import { useEffect } from '@wordpress/element';

// Clears the sticky WP admin bar and leaves the panel a little breathing room.
const SCROLL_OFFSET = 60;
const GIVE_UP_MS = 3000;
const FLASH_MS = 1600;

/**
 * @param {string} active Currently rendered section id.
 * @param {Object} focus  { panel, seq } — seq re-triggers the same target.
 */
export default function usePanelFocus( active, focus ) {
	const panel = focus && focus.panel;
	const seq = focus && focus.seq;

	useEffect( () => {
		if ( ! panel ) {
			return undefined;
		}

		let frame;
		let flash;
		let cancelled = false;
		const deadline = Date.now() + GIVE_UP_MS;

		const tick = () => {
			if ( cancelled ) {
				return;
			}

			const el = document.getElementById( 'nhrotm-panel-' + panel );
			if ( el ) {
				window.scrollTo( {
					top:
						el.getBoundingClientRect().top +
						window.pageYOffset -
						SCROLL_OFFSET,
					behavior: 'smooth',
				} );
				el.classList.add( 'is-focused' );
				flash = window.setTimeout(
					() => el.classList.remove( 'is-focused' ),
					FLASH_MS
				);
				return;
			}

			if ( Date.now() < deadline ) {
				frame = window.requestAnimationFrame( tick );
			}
		};

		frame = window.requestAnimationFrame( tick );

		return () => {
			cancelled = true;
			window.cancelAnimationFrame( frame );
			window.clearTimeout( flash );
		};
	}, [ active, panel, seq ] );
}
