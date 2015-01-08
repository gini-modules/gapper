require.config({
    baseUrl: 'assets/js',
    urlArgs: '_t=' + TIMESTAMP,
    paths: {
        jquery: (document.all && !window.atob) ? 'jquery-1.11.1' : 'jquery'
    }
});

require([
    'jquery'
    ,'bootstrap'
    ,'gapper/client/utils/retina'
    ,'gapper/client/utils/require'
    ,'gapper/client/utils/data'
]);

// 
require(['jquery', 'bootbox'], function($, bootbox) {
    var defaults = {};
    var $meta = $('meta[name=gini-locale]');
    if ($meta.length && $meta.attr('content')) {
        defaults['locale'] = $meta.attr('content');
    }
    if ($.isEmptyObject(defaults)) {
        return;
    }
    bootbox.setDefaults(defaults);
});

