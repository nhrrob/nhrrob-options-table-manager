/**
 * Root component — app shell + client-side section routing.
 *
 * Sections are data-driven from the module list the server localizes
 * (mirrors the PHP ModuleRegistry), so add-ons surface here without core edits.
 */
import { useState, useMemo } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';

import AppShell from './components/AppShell';
import { ConfirmProvider } from './components/ConfirmProvider';
import { ToastProvider } from './components/ToastProvider';
import DashboardScreen from './components/DashboardScreen';
import BrowseScreen from './components/BrowseScreen';
import CleanupScreen from './components/CleanupScreen';
import NetworkScreen from './components/NetworkScreen';
import OptimizeScreen from './components/OptimizeScreen';
import ToolsScreen from './components/ToolsScreen';
import IntegrationsScreen from './components/IntegrationsScreen';
import SettingsScreen from './components/SettingsScreen';
import ActivityScreen from './components/ActivityScreen';
import PlaceholderScreen from './components/PlaceholderScreen';
import usePanelFocus from './components/usePanelFocus';

const SCREENS = {
	dashboard: DashboardScreen,
	browse: BrowseScreen,
	cleanup: CleanupScreen,
	network: NetworkScreen,
	optimize: OptimizeScreen,
	tools: ToolsScreen,
	integrations: IntegrationsScreen,
	settings: SettingsScreen,
	// Not a listed module (no permanent sidebar item) — reached only via
	// Dashboard's "View all" activity link.
	activity: ActivityScreen,
};

export default function App( { boot } ) {
	const modules =
		boot.modules && boot.modules.length
			? boot.modules
			: [
					{
						id: 'dashboard',
						label: __(
							'Dashboard',
							'nhrrob-options-table-manager'
						),
					},
			  ];

	const [ active, setActive ] = useState( modules[ 0 ].id );
	const [ focus, setFocus ] = useState( null );

	/**
	 * Switch section, optionally landing on a specific panel and/or carrying
	 * an initial filter for the target screen. Recommendations and stat cards
	 * pass an anchor so "Scan orphans" arrives at the orphan scanner rather
	 * than the top of Optimize; Optimize's "N expired transients" link passes
	 * a browseFilter so it lands on Browse pre-filtered instead.
	 *
	 * @param {string} section      Target section id.
	 * @param {string} panel        Optional panel anchor within that section.
	 * @param {Object} browseFilter Optional initial filter for Browse ({ type, status }).
	 */
	const navigate = ( section, panel, browseFilter ) => {
		setActive( section );
		// seq makes repeat clicks on the same target re-run the scroll/filter.
		setFocus(
			panel || browseFilter
				? { panel, browseFilter, seq: Date.now() }
				: null
		);
	};

	usePanelFocus( active, focus );

	// Add-ons contribute screens for their own modules through this filter
	// (registered via window.nhrotm before mount — see index.js).
	const screens = useMemo(
		() => applyFilters( 'nhrotm.screens', { ...SCREENS } ),
		[]
	);

	const Screen = screens[ active ] || PlaceholderScreen;
	const current = modules.find( ( m ) => m.id === active ) || modules[ 0 ];

	return (
		<AppShell
			modules={ modules }
			active={ active }
			onNavigate={ navigate }
			title={
				boot.pluginName ||
				__( 'Database Cleaner', 'nhrrob-options-table-manager' )
			}
		>
			{ /* Nested inside AppShell (not wrapping it) so the confirm
			dialog's and toasts' DOM nodes stay within .nhrotm-app — that's
			the only scope the --nhrotm-* CSS custom properties are defined
			on, the modal's background included. */ }
			<ConfirmProvider>
				<ToastProvider>
					<Screen
						module={ current }
						boot={ boot }
						onNavigate={ navigate }
						focus={ focus }
					/>
				</ToastProvider>
			</ConfirmProvider>
		</AppShell>
	);
}
