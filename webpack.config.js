const VueLoaderPlugin = require('./node_modules/vue-loader/lib/plugin');

var HardSourceWebpackPlugin = require('hard-source-webpack-plugin');

const vueLoader = {
    test: /\.vue$/,
    use: [{loader: 'vue-loader'}]
};

module.exports = {
    mode: 'production',
    plugins: [
        new HardSourceWebpackPlugin(),
        new VueLoaderPlugin()
    ],
    module: {
        rules: [vueLoader],
    },
    entry: {
        auth: './app/impero/public/js/auth.js',
        backend: './app/impero/public/js/backend.js',
        services: './app/impero/public/js/services.js',
        generic: './app/impero/public/js/generic.js',
    },
    output: {
        filename: '[name].js',
        path: __dirname + '/build/js'
    }
};