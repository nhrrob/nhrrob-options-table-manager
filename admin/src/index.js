/**
 * NHR Options Manager 2.0 — React app entry.
 *
 * React and all @wordpress/* packages are externalized by @wordpress/scripts
 * (provided by WP core), so they never enter our bundle — keeping the shipped
 * footprint minimal.
 */
import { createRoot } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

import App from './app';
import './style.scss';

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
