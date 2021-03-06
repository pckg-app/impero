const base = require('pckg-app-frontend/full.loader.js');

module.exports = base.exports({
    entry: {
        libraries: './app/impero/public/js/libraries.js',
        footer: './app/impero/public/js/footer.js',
    }
});
