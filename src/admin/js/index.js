/**
 * Admin React entry point.
 */
import { createRoot } from '@wordpress/element';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import App from './components/App';
import '../scss/index.scss';

const queryClient = new QueryClient( {
	defaultOptions: {
		queries: {
			staleTime: 30_000,
			retry: 1,
		},
	},
} );

const root = document.getElementById( 'lswl-admin-root' );

if ( root ) {
	createRoot( root ).render(
		<QueryClientProvider client={ queryClient }>
			<App />
		</QueryClientProvider>
	);
}
