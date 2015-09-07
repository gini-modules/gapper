define('gapper/client/user_account', ['jquery', 'bootbox'], function($, bootbox) {
	function showNoAccount() {
        $url='ajax/gapper/auth/gapper/get-user-account';
        $.get($url,function(data){
            $(data).modal({
                show:true,
                backdrop:'static'
            });
        });

	};

	return {
		show: showNoAccount
	};

});

