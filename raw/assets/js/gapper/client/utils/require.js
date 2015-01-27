define('gapper/client/utils/require', ['jquery'], function($) {$(function() {
    var run = function() {
        $('[data-require]').each(function() {
            var attr = $(this).attr('data-require');
            require([attr]);
        });
    };

    if (document.implementation.hasFeature('MutationsEvents', '2.0')) {
        $('body').on('DOMNodeInserted', function(evt) {
            run();
        });
    }
    else {
        setInterval(run, 1000);
    }

    run();
});});
