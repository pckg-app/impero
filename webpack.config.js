const webpack = require("webpack");
const {merge} = require('webpack-merge');
const base = require('pckg-app-frontend/webpack.base.js');

module.exports = merge(base, {
    entry: {
        libraries: './app/impero/public/js/libraries.js',
        auth: './app/impero/public/js/auth.js',
        backend: './app/impero/public/js/backend.js',
        services: './app/impero/public/js/services.js',
        generic: './app/impero/public/js/generic.js',
        footer: './app/impero/public/js/footer.js',
    },
    output: {
        path: __dirname + '/build'
    }
});