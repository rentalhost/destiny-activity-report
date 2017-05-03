const { mix } = require('laravel-mix');

/**
 * Mix asset management.
 */
mix.js('resources/assets/js/app.js', 'public/assets')
    .sass('resources/assets/sass/app.scss', 'public/assets');
