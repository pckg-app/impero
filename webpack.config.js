const base = require('pckg-app-frontend/full.loader.js');

module.exports = base.exports({
    entry: {
        libraries: './app/impero/public/js/libraries.js',
        auth: './app/impero/public/js/auth.js',
        backend: './app/impero/public/js/backend.js',
        services: './app/impero/public/js/services.js',
        generic: './app/impero/public/js/generic.js',
        footer: './app/impero/public/js/footer.js',
    }
});