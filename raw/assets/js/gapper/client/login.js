define('gapper/client/login', ['jquery', 'bootbox', 'css!../../../css/gapper-choose-group'], function($, bootbox) {
    var dialog, loadingDialog;
    var isWaitingLogin = false;
    var clearDialog = function() {
        if (!dialog || ! dialog.length) return;
        dialog.prev('.modal-backdrop').remove();
        dialog.remove();
        dialog = undefined;
    };
    var clearLoadingDialog = function() {
        if (!loadingDialog || ! loadingDialog.length) return;
        loadingDialog.prev('.modal-backdrop').remove();
        loadingDialog.remove();
    };
    var showLoadingDialog = function() {
        clearLoadingDialog();
        loadingDialog = $('<div class="modal"><div class="modal-dialog"><div class="modal-content"><h2 class="text-center"><span class="fa fa-spinner fa-spin fa-2x"></span></h2></div></div></div>');
        loadingDialog.modal({
            show: true
            ,backdrop: 'static'
        });
    };
    var showDialog = function(html, callback) {
        clearDialog();
        try {
            dialog = $(html);
        } catch(err) {}
        if (!dialog || ! dialog.hasClass('modal')) {
            dialog = $(['<div class="modal fade"><div class="modal-dialog"><div class="modal-content"><div class="modal-body"><button class="close" data-dismiss="modal"><span aria-hidden="true">×</span><span class="sr-only">Close</span></button><h4 class="modal-title">', html, '</h4></div></div></div></div>'].join(''));
        }
        if (!dialog.hasClass('gapper-login-modal')) {
            dialog.addClass('gapper-login-modal');
        }
        dialog.modal({
            show: true
            ,backdrop: 'static'
        });
        callback && callback();
    };

    var showLogin = function() {
        if (isWaitingLogin) return false;
        isWaitingLogin = true;
        var url = 'ajax/gapper/client/sources';
        showLoadingDialog();
        $.get(url, {
            _t: (new Date()).getTime()
        }, function(data) {
            clearLoadingDialog();
            if (data === true) {
                return window.location.reload();
            }
            if ($.isPlainObject(data) && data.redirect && data.message) {
                var redirectURL = data.redirect;
                var message = data.message;
                var callback = function() {
                    setTimeout(function() {
                        window.location.href = redirectURL;
                    }, 10);
                    setTimeout(function() {
                        isWaitingLogin = false;
                    }, 2000);
                };

                if (message) {
                    showDialog(message, callback);
                }
                else {
                    callback();
                }
                return;
            }
            if ($.isPlainObject(data) && data.type == 'modal' && data.message) {
                data = data.message;
            }
            showDialog(data, function() {
                setTimeout(function() {
                    isWaitingLogin = false;
                }, 2000);
            });
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
            var url = 'ajax/gapper/auth/get-form/' + source;
            showLoadingDialog();
            $.get(url, {
                _t: (new Date()).getTime()
            }, function(data) {
                clearLoadingDialog();
                if ($.isPlainObject(data) && data.redirect) {
                    window.location.href = data.redirect;
                    return;
                }
                if ($.isPlainObject(data) && data.type == 'modal' && data.message) {
                    data = data.message;
                }
                clearDialog();
                showDialog(data, function() {
                    dialog.on('hide.bs.modal', function() {
                        setTimeout(showLogin, 1);
                    });
                    setTimeout(function() {
                        isWaitingClick = false;
                    }, 2000);
                });
            });
        }
        if ($that.attr('data-gapper-client-group')) {
            showLoadingDialog();
            $.post('ajax/gapper/auth/gapper/choose', {
                id: $that.attr('data-gapper-client-group')
            }, function(data) {
                setTimeout(function() {
                    isWaitingClick = false;
                }, 2000);
                if (true === data) {
                    window.location.reload();
                }
                if ($.isPlainObject(data) && data.redirect) {
                    window.location.href = data.redirect;
                    return;
                }
                clearLoadingDialog();
                if ($.isPlainObject(data) && data.type == 'modal' && data.message) {
                    data = data.message;
                }
                showDialog(data);
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

        var values, index;

        dataArr = $that.serializeArray();

        for (index = 0; index < dataArr.length; ++index) {
            if (dataArr[index].name == "password") {
                dataArr[index].value = window.btoa(dataArr[index].value);
            }
        }

        data = jQuery.param(dataArr);


        showLoadingDialog();
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
                        clearLoadingDialog();
                        clearDialog();
                        showDialog(tData.message, function() {
                            dialog.on('hide.bs.modal', function() {
                                setTimeout(showLogin, 1);
                            });
                        });
                        break;
                    case 'alert':
                        clearLoadingDialog();
                        showDialog(pData);
                        break;
                    default:
                        if (pData.redirect) {
                            var callback = function() {
                                setTimeout(function() {
                                    window.location.href = pData.redirect;
                                }, 10);
                            };
                            if (pData.message) {
                                clearLoadingDialog();
                                showDialog(pData.message, callback);
                            }
                            else {
                                callback();
                            }
                        }
                }
            }
            setTimeout(function() {
                isWaitingSubmit = false;
            }, 2000);
        });
        return false;
    });

    $(document).on('click', '.gapper-login-modal .close', function() {
        isWaitingLogin = true;
        var needShowlogin = ! $(this).attr('data-just-close');
        $.get('ajax/gapper/client/logout', {
            _t: (new Date()).getTime()
        }, function(data) {
            if ($.isPlainObject(data) && data.redirect) {
                window.location.href = data.redirect;
                return;
            }
            isWaitingLogin = false;
            if (needShowlogin) {
                setTimeout(showLogin, 1);
            }
        });
    });

    var data = {
        show: showLogin
    };
    return data;
});

