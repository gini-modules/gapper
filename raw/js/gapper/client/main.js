require.config({
    baseUrl: 'assets/js',
    urlArgs: '_t=' + TIMESTAMP,
    shim: {
        'bootstrap': ['jquery'],
        'bootbox': ['bootstrap']
    }
});

require([
    'gapper/client/utils/global'
    ,'jquery'
    ,'bootstrap'
    ,'gapper/client/utils/retina'
    ,'gapper/client/utils/require'
]);


// 
require(['jquery', 'bootbox'], function($) {
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
