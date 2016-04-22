define('gapper/client/login', ['jquery', 'bootbox', 'css!../../../css/gapper-choose-group'], function($, bootbox) {
	var dialog;
	var isWaitingLogin = false;
	var clearDialog = function() {
		if (!dialog || ! dialog.length) return;
		dialog.prev('.modal-backdrop').remove();
		dialog.remove();
	};
	var showLogin = function() {
		if (isWaitingLogin) return false;
		isWaitingLogin = true;
		var url = 'ajax/gapper/client/sources';
		$.get(url, function(data) {
			if (data === true) {
				return window.location.reload();
			}
			if ($.isPlainObject(data)) {
				var redirectURL = data.redirect;
				window.location.href = redirectURL;
			}
			clearDialog();
			dialog = $(data);
			dialog.modal({
				show: true
				,backdrop: 'static'
			});
			setTimeout(function() {
				isWaitingLogin = false;
			}, 2000);
		});
	};

	var classBox = 'gapper-client-checkbox';
	var classLi = 'gapper-client-checkbox-li';
	var isWaitingClick = false;
	$('body').on('click', '.' + classBox + ' .' + classLi, function() {
		if (isWaitingClick) return false;
		isWaitingClick = true;
		var $that = $(this);
		if ($that.attr('data-gapper-auth-source')) {
			var source = $that.attr('data-gapper-auth-source');
			var url = 'ajax/gapper/auth/' + source + '/getForm';
			$.get(url, function(data) {
				if ($.isPlainObject(data)) {
					window.location.href = data.redirect;
					return;
				}
				clearDialog();
				dialog = $(data);
				dialog.modal({
					show: true
					,backdrop: 'static'
				});
				dialog.on('hide.bs.modal', function() {
					showLogin();
				});
				setTimeout(function() {
					isWaitingClick = false;
				}, 2000);
			});
		}
		if ($that.attr('data-gapper-client-group')) {
			$.post('ajax/gapper/auth/gapper/choose', {
				id: $that.attr('data-gapper-client-group')
			}, function(data) {
				setTimeout(function() {
					isWaitingClick = false;
				}, 2000);
				if (true === data) {
					window.location.reload();
				}
				else {
					bootbox.alert(data);
				}
			});
		}
	});

	var classForm = '.gapper-auth-form';
	var isWaitingSubmit = false;
	$('body').on('click', classForm + ' input[type=submit]', function(evt) {
		evt.preventDefault();
		if (isWaitingSubmit) return false;
		isWaitingSubmit = true;
		var $that = $(this).parents(classForm);
		var url = $that.attr('action');
		var data = $that.serialize();
		$.post(url, data, function(pData) {
			if (true === pData) {
				window.location.reload();
			}
			else {
				var tData = $.isPlainObject(pData) ? pData: {
					type: 'alert'
					,message: pData
				};
				switch (tData.type) {
					case 'modal':
						clearDialog();
						dialog = $(tData.message);
						dialog.modal({
							show: true
							,backdrop: 'static'
						});
						dialog.on('hide.bs.modal', function() {
							showLogin();
						});
						break;
					default:
						bootbox.alert(pData);
				}
			}
			setTimeout(function() {
				isWaitingSubmit = false;
			}, 2000);
		});
		return false;
	});

	var data = {
		show: showLogin
	};
	return data;
});

