define('gapper/client/utils/require', ['jquery'], function($) {
    $('[data-require]').each(function() {
        var attr = $(this).attr('data-require');
        require([attr]);
    });
});
