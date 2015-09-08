define('gapper/client/autoload', function() {
    var action = window.ACTION ? window.ACTION: 'login';
    var file;
    switch (action) {
        case 'group_account':
            file = 'gapper/client/group_account';
            break;
        case 'user_account':
            file = 'gapper/client/user_account';
            break;
        case 'login':
        default:
            file = 'gapper/client/login';
    }
    require([file], function($) {
        $.show();
    });
});
