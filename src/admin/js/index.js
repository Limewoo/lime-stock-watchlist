/**
 * Admin React entry point.
 */
import { createRoot } from '@wordpress/element';
import App from './components/App';
import '../scss/index.scss';

const root = document.getElementById( 'lswl-admin-root' );

if ( root ) {
	createRoot( root ).render( <App /> );
}
