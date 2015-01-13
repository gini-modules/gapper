/**
 * @file add-memeber.js
 * @brief 允许APP通过调用这个js实现直接在APP界面实现成员添加的功能
 * @author Hongjie Zhu
 * @version 0.1.0
 * @date 2015-01-09
 */
define('gapper/client/add-member', ['jquery', 'bootstrap', 'bootbox', 'css'], function($, Bootstrap, bootbox, css) {
    var selector = {
        handler: '.gapper-add-member-handler'
    };

    var url = {
        // 获取登录方式
        types: 'ajax/gapper/client/get-add-member-types'
    };

    var is = {
        ajaxing: false
    };

    var showTypesDialog = function(pResult, pCallback) {
        // 各种登录方式自己实现接下来的添加成员的流程
        if (!pResult) {
            return;
        }
        var iDialog = $(pResult);
        if (pCallback) {
            iDialog.find('.modal-body').data('callback', pCallback);
        }
        iDialog.modal('show');
    };

    $(function() {
        css.load('gapper-client-add-member', {
            toUrl: function(pFile) {
                return '/assets/css/' + pFile;
            }
        }, function() {});
    });

    $(document).on('click', selector.handler, function(pEvt) {
        if (is.ajaxing) return;
        is.ajaxing = true;
        pEvt.preventDefault();
        var iCallback = $(this).data('callback');
        // 获取登录方式，并展示
        $.get(url.types, function(pResult) {
            showTypesDialog(pResult, iCallback);
        }).always(function() {
            is.ajaxing = false;
        });
    });
});
