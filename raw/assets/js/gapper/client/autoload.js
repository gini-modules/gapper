define('gapper/client/autologin', function() {
	var action = window.ACTION ? window.ACTION: 'login';
	var file;
	switch (action) {
		case 'signup':
			file = 'gapper/client/signup';
			break;
		case 'login':
		default:
			file = 'gapper/client/login';
	}
	require([file], function($) {
		$.show();
	});
});

