/**
 * Tiny inline Tabler-style icon set (stroke SVGs, no icon font, tree-shaken).
 * Kept as raw path data so the bundle only carries the glyphs actually used.
 */
const PATHS = {
	dashboard: 'M3 3h7v9H3zM14 3h7v5h-7zM14 12h7v9h-7zM3 16h7v5H3z',
	browse: 'M3 4h18v16H3zM3 10h18M9 10v10',
	optimize: 'M13 2 3 14h7l-1 8 10-12h-7z',
	tools: 'M14.7 6.3a4 4 0 0 0-5 5L3 18l3 3 6.7-6.7a4 4 0 0 0 5-5l-2.8 2.8-2.1-2.1z',
	integrations: 'M7 8 3 12l4 4M17 8l4 4-4 4M14 4l-4 16',
	settings:
		'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-2.9 1.2 2 2 0 1 1-4 0 1.7 1.7 0 0 0-2.9-1.2l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1A1.7 1.7 0 0 0 4.6 9a1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1A1.7 1.7 0 0 0 10 4.6a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 2.9 1.2l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0 1.2 2.9H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z',
	upgrade: 'M12 3 4 9v12h16V9zM9 21v-6h6v6',
	edit: 'M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z',
	trash: 'M4 7h16M10 11v6M14 11v6M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2l1-12M9 7V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v3',
	search: 'M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14zM21 21l-4.3-4.3',
	close: 'M18 6 6 18M6 6l12 12',
	refresh: 'M21 12a9 9 0 1 1-3-6.7L21 8M21 3v5h-5',
	clock: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM12 7v5l3 2',
	collapse: 'M3 4h18v16H3zM9 4v16M14 9l2 3-2 3',
	sort: 'M8 9l4-4 4 4M16 15l-4 4-4-4',
	sortUp: 'M8 14l4-4 4 4',
	sortDown: 'M8 10l4 4 4-4',
	check: 'M20 6 9 17l-5-5',
	alert: 'M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0zM12 9v4M12 17h.01',
	database:
		'M12 5c4.97 0 9-1.34 9-3s-4.03-3-9-3-9 1.34-9 3 4.03 3 9 3zM3 5v14c0 1.66 4.03 3 9 3s9-1.34 9-3V5M3 12c0 1.66 4.03 3 9 3s9-1.34 9-3',
	lock: 'M5 13a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2zM11 16a1 1 0 1 0 2 0 1 1 0 0 0-2 0zM8 11V7a4 4 0 1 1 8 0v4',
};

export default function Icon( { name, size = 18, className = '' } ) {
	const d = PATHS[ name ];
	if ( ! d ) {
		return null;
	}
	return (
		<svg
			className={ className }
			width={ size }
			height={ size }
			viewBox="0 0 24 24"
			fill="none"
			stroke="currentColor"
			strokeWidth="2"
			strokeLinecap="round"
			strokeLinejoin="round"
			aria-hidden="true"
			focusable="false"
		>
			<path d={ d } />
		</svg>
	);
}
