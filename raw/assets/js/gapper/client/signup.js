define('gapper/client/signup', ['jquery', 'bootbox'], function($, bootbox) {
	function showSignup() {
        $url='ajax/gapper/auth/gapper/get-signup';
        $.get($url,function(data){
            $(data).modal({
                show:true,
                backdrop:'static'
            });
        });

	};

	return {
		show: showSignup
	};

});

