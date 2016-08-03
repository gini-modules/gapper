define('gapper/client/utils/require', ['jquery'], function($) {
    var run = function() {
        $('[data-require]').each(function() {
            var attr = $(this).attr('data-require');
            attr = attr.split(',');
            require(attr, function() {
                var args = Array.prototype.slice.call(arguments);
                for (var i = 0, l = args.length; i < l; i++) {
                    if (args[i] && args[i].loopMe) {
                        args[i].loopMe();
                    }
                }
            });
        });
    };

    function init() {
        if (document.implementation.hasFeature('MutationsEvents', '2.0')) {
            $('body').on('DOMNodeInserted', function(evt) {
                run();
            });
        }
        else {
            setInterval(run, 1000);
        }
    }

    var clock = setTimeout(function() {
        init();
    }, 2000);

	$(function() {
        clearTimeout(clock);
        init();
	});
});
