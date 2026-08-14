/**
 * The CryptX settings screen.
 *
 * Everything this file knows about the options comes from the schema the REST
 * route hands over. Adding an option means adding it to SettingsSchema.php --
 * there is deliberately no second list of fields here that could fall behind.
 */

import { createRoot, StrictMode } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import App from './components/App';
import './index.css';

document.addEventListener( 'DOMContentLoaded', () => {
	const container = document.getElementById( 'cryptx-settings-root' );

	if ( ! container ) {
		return;
	}

	// The container carries the "loading" fallback that PHP printed. Clearing
	// it here rather than in PHP means the fallback stays visible for people
	// whose browser never gets this far.
	container.textContent = '';
	container.setAttribute( 'aria-busy', 'false' );
	container.setAttribute( 'aria-label', __( 'CryptX settings', 'cryptx' ) );

	createRoot( container ).render(
		<StrictMode>
			<App />
		</StrictMode>
	);
} );
