const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		admin: './src/admin/js/index.js',
		frontend: './src/frontend/js/index.js',
	},
};
