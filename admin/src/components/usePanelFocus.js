/**
 * Scrolls a deep-linked panel into view — used both for the cross-section
 * jump from Dashboard and for in-page JumpNav clicks.
 *
 * Target screens fetch their data before rendering panels, so on a
 * cross-section jump the anchor does not exist at navigation time — the hook
 * below polls for it on animation frames until it appears, then gives up
 * rather than spin forever. An in-page click already has the element on
 * screen, so it calls `scrollToPanel` directly with no polling.
 */
import { useEffect } from '@wordpress/element';

// Clears the (fixed) WP admin bar. JumpNav is not sticky, so it never
// overlaps a scrolled-to panel and needs no offset of its own.
const SCROLL_OFFSET = 60;
const GIVE_UP_MS = 3000;
const FLASH_MS = 1600;

/**
 * Scrolls the given panel into view and flashes it.
 *
 * @param {string} anchor Panel anchor id (matches `Panel`'s `anchor` prop).
 * @return {boolean} Whether the panel element was found.
 */
export function scrollToPanel( anchor ) {
	const el = document.getElementById( 'nhrotm-panel-' + anchor );
	if ( ! el ) {
		return false;
	}

	window.scrollTo( {
		top:
			el.getBoundingClientRect().top + window.pageYOffset - SCROLL_OFFSET,
		behavior: 'smooth',
	} );
	el.classList.add( 'is-focused' );
	window.setTimeout( () => el.classList.remove( 'is-focused' ), FLASH_MS );
	return true;
}

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
		let cancelled = false;
		const deadline = Date.now() + GIVE_UP_MS;

		const tick = () => {
			if ( cancelled ) {
				return;
			}

			if ( scrollToPanel( panel ) ) {
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
		};
	}, [ active, panel, seq ] );
}
