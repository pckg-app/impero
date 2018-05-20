// webpack-dev-server ./webpack.config.js --hot

const VueLoaderPlugin = require('./node_modules/vue-loader/lib/plugin');

const vueLoader = {
    test: /\.vue$/,
    use: [{loader: 'vue-loader'}]
};

module.exports = {
    plugins: [
        new VueLoaderPlugin()
    ],
    entry: './app/impero/public/js/backend.js',
    output: {
        filename: 'build/js/backend.js'
    },
    module: {
        rules: [vueLoader]
    }
};