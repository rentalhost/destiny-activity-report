const { mix } = require('laravel-mix');

/**
 * Mix asset management.
 */
mix.js('resources/assets/modules/Bootstrap.js', 'public/assets/compileds/application.js').extract(['jquery', 'vue', 'lodash', 'moment', 'querystring']);
mix.sass('resources/assets/modules/Application.scss', 'public/assets/compileds/application.css');
mix.sass('resources/assets/modules/Vendor.scss', 'public/assets/compileds/vendor.css');

//noinspection JSUnresolvedVariable
if (mix.config.inProduction) {
    mix.version();
}
