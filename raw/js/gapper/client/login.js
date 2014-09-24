define('gapper/client/login', ['jquery', 'bootbox'], function ($) {
    var dialog;
    var classDialog = 'gapper-client-dialog';
    var showLogin = function() {
        var url = 'ajax/gapper/client/getSources';
        $.get(url, function(data) {
            dialog = $(data);
            dialog.modal('show');
        });
    };

    var classBox = 'gapper-client-checkbox';
    var classLi = 'gapper-client-checkbox-li';
    $('body').on('click', '.'+classBox+' .'+classLi, function() {
        var $that = $(this);
        if ($that.attr('data-gapper-auth-source')) {
            var source = $that.attr('data-gapper-auth-source');
            var url = 'ajax/'+source+'/getForm';
            $.get(url, function(data) {
                dialog && dialog.modal && dialog.modal('hide');
                dialog = $(data);
                dialog.modal('show');
                dialog.on('hide.bs.modal', function() {
                    showLogin();
                });
            });
        }
        if ($that.attr('data-gapper-client-group')) {
            var url = 'ajax/gapper/client/choose';
            $.post(url, {
                id: $that.attr('data-gapper-client-group')
            }, function(data) {
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
    $('body').on('click', classForm+' input[type=submit]', function(evt) {
        evt.preventDefault();
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
        });
        return false;
    });

    var data = {
        showLogin: showLogin
    };
    return data;
});
