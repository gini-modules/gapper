define('gapper/client/login', ['jquery', 'bootbox'], function ($, bootbox) {
    var dialog;
    var classDialog = 'gapper-client-dialog';
    var isWaitingLogin = false;
    var clearDialog = function() {
        if (!dialog || !dialog.length) return;
        dialog.prev('.modal-backdrop').remove();
        dialog.remove();
    };
    var showLogin = function() {
        if (isWaitingLogin) return false;
        isWaiting = true;
        var url = 'ajax/gapper/client/getSources';
        $.get(url, function(data) {
            clearDialog();
            dialog = $(data);
            dialog.modal({show:true, backdrop: 'static'});
            setTimeout(function() {
                isWaitingLogin = false;
            }, 2000);
        });
    };

    var classBox = 'gapper-client-checkbox';
    var classLi = 'gapper-client-checkbox-li';
    var isWaitingClick = false;
    $('body').on('click', '.'+classBox+' .'+classLi, function() {
        if (isWaitingClick) return false;
        isWaitingClick = true;
        var $that = $(this);
        if ($that.attr('data-gapper-auth-source')) {
            var source = $that.attr('data-gapper-auth-source');
            var url = 'ajax/'+source+'/getForm';
            $.get(url, function(data) {
                clearDialog();
                dialog = $(data);
                dialog.modal({show:true, backdrop: 'static'});
                dialog.on('hide.bs.modal', function() {
                    showLogin();
                });
                setTimeout(function() {
                    isWaitingClick = false;
                }, 2000);
            });
        }
        if ($that.attr('data-gapper-client-group')) {
            var url = 'ajax/gapper/client/choose';
            $.post(url, {
                id: $that.attr('data-gapper-client-group')
            }, function(data) {
                setTimeout(function() {
                    isWaitingClick = false;
                }, 2000);
                if (true===data) {
                    window.location.reload();
                }
                else {
                    bootbox.alert(data);
                }
            });
        }
    });

    var classForm = '.gapper-auth-login-form';
    var isWaitingSubmit = false;
    $('body').on('click', classForm+' input[type=submit]', function(evt) {
        evt.preventDefault();
        if (isWaitingSubmit) return false;
        isWaitingSubmit = true;
        var $that = $(this).parents(classForm);
        var url = $that.attr('action');
        var data = $that.serialize();
        $.post(url, data, function(data) {
            if (true===data) {
                window.location.reload();
            }
            else {
                bootbox.alert(data);
            }
            setTimeout(function() {
                isWaitingSubmit = false;
            }, 2000);
        });
        return false;
    });

    var data = {
        showLogin: showLogin
    };
    return data;
});
