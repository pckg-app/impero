const VueLoaderPlugin = require('./node_modules/vue-loader/lib/plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const TerserPlugin = require('terser-webpack-plugin');
const HardSourceWebpackPlugin = require('hard-source-webpack-plugin');
const UglifyJsPlugin = require('uglifyjs-webpack-plugin');

const vueLoader = {
    test: /\.vue$/,
    use: [{
        loader: 'vue-loader',
        options: {
            compilerOptions: {
                preserveWhitespace: false,
                whitespace: 'condense'
            }
        }
    }],
};

const esLoader = {
    test: /\.js$/,
    exclude: /(node_modules|bower_components)/,
    use: [{
        loader: 'babel-loader',
        options: {
            presets: ['@babel/preset-env', /*'es2015'*/]
        }
    }],
};

const lessLoader = {
    test: /\.less$/,
    use: [
        'vue-style-loader',
        {
            loader: MiniCssExtractPlugin.loader,
            options: {
                publicPath: '/build/js',
                hmr: process.env.NODE_ENV === 'development',
            },
        },
        'css-loader',
        'less-loader'
    ]
};

const cssLoader = {
    test: /\.css$/,
    use: ['style-loader',
        {
            loader: MiniCssExtractPlugin.loader,
            options: {
                // you can specify a publicPath here
                // by default it uses publicPath in webpackOptions.output
                /*publicPath: function(resourcePath, context){
                    // publicPath is the relative path of the resource to the context
                    // e.g. for ./css/admin/main.css the publicPath will be ../../
                    // while for ./css/main.css the publicPath will be ../
                    return path.relative(path.dirname(resourcePath), context) + '/';
                },*/
                publicPath: '/build/js',
                hmr: process.env.NODE_ENV === 'development',
            },
        }, 'css-loader'],
};

const urlLoader = {
    test: /\.(png|jpg|gif|svg)$/,
    loader: 'url-loader',
    options: {
        limit: 10000,
    },
};

const fontLoader = {
    test: /.(eot|ttf|otf|svg|woff(2)?)(\?[a-z0-9]+)?$/,
    use: [{
        loader: 'file-loader',
        options: {
            name: '[name].[ext]',
            outputPath: 'fonts/',    // where the fonts will go
            //publicPath: '../'       // override the default path
        }
    }]
};

module.exports = {mode: 'production',
    plugins: [
        new HardSourceWebpackPlugin(),
        new VueLoaderPlugin(),
        new MiniCssExtractPlugin({
            // Options similar to the same options in webpackOptions.output
            // both options are optional
            filename: '[name].css',
            chunkFilename: '[id].css',
        }),
    ],
    module: {
        rules: [vueLoader, cssLoader, urlLoader, esLoader, fontLoader, lessLoader],
    },
    entry: {
        libraries: './app/impero/public/js/libraries.js',
        auth: './app/impero/public/js/auth.js',
        backend: './app/impero/public/js/backend.js',
        services: './app/impero/public/js/services.js',
        generic: './app/impero/public/js/generic.js',
        footer: './app/impero/public/js/footer.js',
    },
    output: {
        filename: '[name].js',
        path: __dirname + '/build/js',
        chunkFilename: '[id].[hash].chunk.js',
        publicPath: '/build/js/'
    },
    optimization: {
        /*splitChunks: {
            chunks: 'all',
        },*/
        minimize: process.env.NODE_ENV !== 'development',
        minimizer: [
            new UglifyJsPlugin({
                test: /\.js(\?.*)?$/i,
                cache: true,
                parallel: true,
                sourceMap: process.env.NODE_ENV === 'development'
            })
            /*new TerserPlugin({
                cache: true,
                test: /\.js(\?.*)?$/i,
                parallel: true,
                sourceMap: process.env.NODE_ENV === 'development',
                terserOptions: {
                    warnings: true,
                    parse: {},
                    compress: false,
                    ecma: 6,
                    mangle: true,
                    toplevel: false,
                    nameCache: null,
                    ie8: false,
                    keep_fnames: false,
                    output: {
                        comments: false,
                    },
                },
            }),*/
        ],
    }
};