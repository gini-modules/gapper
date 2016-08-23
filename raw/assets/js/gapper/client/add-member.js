define('gapper/client/add-member', ['jquery', 'bootbox', 'css!../../../css/add-member'], function($, bootbox) {
    var strLoading = '<div class="add-member-loading loading text-center"> <i class="fa fa-spinner fa-spin fa-2x"></i> </div>';

    var selector = {
        appHandler: '.app-add-member-handler'
        ,handler: '.add-member-handler'
        ,matched: '.add-member-matched'
        ,confirm: '.add-member-confirm'
        ,info: '.add-member-info'
        ,loading: '.add-member-loading'
        ,second: '.add-member-close-in'
        ,refreshHandler: '.app-add-member-refresh-handler'
    };

    var url = {
        types: 'ajax/gapper/client/get-add-member-types'
        ,addMember: 'ajax/gapper/client/get-add-modal'
        ,search: 'ajax/gapper/client/search'
        ,add: 'ajax/gapper/client/post-add'
    };

    var is = {
        ajaxing: false
    };

    var clock = {
        searchResult: undefined
    };

    var xhr = false;

    var resetXHR = function() {
        if (xhr) {
            xhr.abort();
            xhr = undefined;
        }
    };

    var getSearchResult = function(pValue) {
        resetXHR();
        var iModal = $(this);
        xhr = $.get(url.search, {
            value: pValue
            ,type: iModal.attr('data-type')
        }, function(pResult) {
            iModal.find(selector.matched).remove();
            iModal.find(selector.info).remove();
            iModal.find(selector.loading).remove();
            if (pResult) {
                iModal.find('.modal-body').append(pResult);
            }
        });
    };

    // 确认添加
    $(document).on('click', selector.confirm, function(pEvt) {
        pEvt.preventDefault();
        var iContainer = $(this).parents('.modal-body').find(selector.matched);
        var iModal = $(this).parents('.modal');
        var iCallback = iModal.attr('data-callback');
        resetXHR();
        var iURL = [url.add, '?type=', iModal.attr('data-type')].join('');
        xhr = $.post(iURL, iContainer.serializeArray(), function(pResult) {
            if (!pResult) return;
            var iData = pResult;
            if (!$.isPlainObject(pResult)) {
                iData = {
                    type: 'subcontent'
                    ,message: iData
                };
            }

            switch (iData.type) {
                case 'alert':
                    bootbox.alert(iData.message);
                    break;
                case 'replace':
                    iModal.find('.modal-body').html(iData.message);
                    // 添加成功之后才调用各个app的回调
                    if (iData.replace) {
                        var iSecondClose = function() {
                            var tHM = function() {
                                iModal.off('click', selector.appHandler, tHM);
                                tInterval && clearInterval(tInterval);
                                tInterval=undefined;
                                iModal.length && iModal.modal('hide');
                            };
                            var tInterval = setInterval(function() {
                                var tSecond = parseInt(iModal.find(selector.second).text(), 10);
                                if (tSecond > 1) {
                                    iModal.find(selector.second).text(tSecond - 1);
                                    return;
                                }
                                tHM();
                            }, 1000);
                            iModal.on('click', selector.appHandler, tHM);
                        };
                        if (iCallback) {
                            require([iCallback], function(pCallback) {
                                pCallback && pCallback.call(iModal, iData.replace);
                                iSecondClose();
                            });
                        }
                        else {
                            var iRH = $(selector.refreshHandler);
                            if (iRH.length) iRH.trigger('click');
                            iSecondClose();
                        }
                    }
                    break;
                case 'subcontent':
                    iModal.find(selector.matched).remove();
                    if (iData.message) {
                        iModal.find('.modal-body').append(iData.message);
                    }
                    break;
            }
        });
    });

    $(document).on('click', selector.handler, function(pEvt) {
        if (is.ajaxing) return;
        pEvt.preventDefault();
        is.ajaxing = true;
        var iType = $(this).attr('data-type');
        var iGid = $(this).parents('.modal-body').attr('data-gid');
        var iModal = $(this).parents('.modal');
        var iCallback = iModal.attr('data-callback');
        $.post(url.addMember, {
            type: iType
            ,gid: iGid
        }, function(pResult) {
            showDialog(pResult, {
                'data-callback': iCallback
                ,'data-type': iType
            });
        }).always(function() {
            is.ajaxing = false;
            iModal.modal('hide');
        });
    });

    var showDialog = function(pResult, pAttrs) {
        if (!pResult) {
            return;
        }
        var iDialog = $(pResult);
        for (var iAttr in pAttrs) {
            iDialog.attr(iAttr, pAttrs[iAttr]);
        }
        iDialog.modal({
            show: true
            ,backdrop: 'static'
        });
        iDialog.on('submit', 'form', function(pEvt) {
            pEvt.preventDefault();
        });
        var iCallback = function(pEvt) {
            pEvt.preventDefault();
            if (clock.searchResult) {
                clearTimeout(clock.searchResult);
                clock.searchResult = undefined;
            }
            var $that = $(this);
            iDialog.find(selector.matched).remove();
            iDialog.find(selector.info).remove();
            iDialog.find(selector.loading).remove();
            if ($that.val()) {
                iDialog.find('.modal-body').append(strLoading);
            }

            clock.searchResult = setTimeout(function() {
                getSearchResult.call(iDialog, $that.val());
            }, 1000);
        };

        iDialog.on('focus', '.add-member-username input[name=username]', iCallback);
        iDialog.on('keyup', '.add-member-username input[name=username]', iCallback);
    };

    $(document).on('click', selector.appHandler, function(pEvt) {
        if (is.ajaxing) return;
        is.ajaxing = true;
        pEvt.preventDefault();
        var iCallback = $(this).attr('data-callback') || '';
        // 获取登录方式，并展示
        $.get(url.types, function(pResult) {
            showDialog(pResult, {
                'data-callback': iCallback
            });
        }).always(function() {
            is.ajaxing = false;
        });
    });
});

