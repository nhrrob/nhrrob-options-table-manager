/**
 * NHR Database Cleaner & Optimizer — React app entry.
 *
 * React and all @wordpress/* packages are externalized by @wordpress/scripts
 * (provided by WP core), so they never enter our bundle — keeping the shipped
 * footprint minimal.
 */
import { createRoot } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

import App from './app';
import Panel from './components/Panel';
import ScreenHeader from './components/ScreenHeader';
import DataTable from './components/DataTable';
import JumpNav from './components/JumpNav';
import Icon, { registerIcons } from './components/Icon';
import { useToast } from './components/ToastProvider';
import { useConfirm } from './components/ConfirmProvider';
import './style.scss';

/*
 * Public surface for add-ons. An add-on's bundle loads after this
 * one ('nhrotm-app' dependency) and before DOMContentLoaded, registers its
 * screens with addFilter( 'nhrotm.screens', … ) and reuses these shared
 * components so it renders with the same chrome instead of shipping copies.
 */
window.nhrotm = {
	components: { Panel, ScreenHeader, DataTable, JumpNav, Icon },
	useToast,
	useConfirm,
	registerIcons,
};

// Authenticate REST calls with the localized nonce.
const boot = window.nhrotmApp || {};
if ( boot.nonce ) {
	apiFetch.use( apiFetch.createNonceMiddleware( boot.nonce ) );
}
if ( boot.restRoot ) {
	apiFetch.use( apiFetch.createRootURLMiddleware( boot.restRoot ) );
}

document.addEventListener( 'DOMContentLoaded', () => {
	const el = document.getElementById( 'nhrotm-app' );
	if ( el ) {
		createRoot( el ).render( <App boot={ boot } /> );
	}
} );
