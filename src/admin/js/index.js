/**
 * Admin React entry point.
 */
import { render } from '@wordpress/element';
import App from './components/App';
import '../scss/index.scss';

const root = document.getElementById( 'lswl-admin-root' );

if ( root ) {
	render( <App />, root );
}
