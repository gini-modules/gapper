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


