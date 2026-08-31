// Created by PhpStorm.
// Project name co_crmv5.
// User: Huang Gang
// Date: 2026/05/31
// Time: 22:56
/**
 * Layui 日期输入增强工具。
 * 为所有 .J_layDate 输入框绑定弹层日历，使 Naive 普通页和 Layui 页保持相同日期选择体验。
 */
var CrmDateRange = (function () {
    'use strict';

    // 保留多语言读取入口，后续扩展快捷按钮文案时可直接复用。
    function t(key) {
        return typeof CrmLang !== 'undefined' && CrmLang.t ? CrmLang.t(key) : key;
    }

    // 日期格式化辅助函数，保证月份和日期始终两位。
    function pad(value) {
        value = Number(value || 0);
        return value < 10 ? '0' + value : String(value);
    }

    // 输出 Layui 接受的 yyyy-MM-dd 字符串。
    function formatDate(date) {
        return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
    }

    // 在指定作用域内初始化日历，并用 data 标记避免重复绑定同一个输入框。
    function init(scope) {
        if (typeof layui === 'undefined') {
            return;
        }

        layui.use(['jquery', 'laydate'], function () {
            var $ = layui.jquery;
            var laydate = layui.laydate;
            var $scope = scope ? $(scope) : $(document);

            $scope.find('.J_layDate').each(function () {
                var $input = $(this);

                if ($input.data('laydate-ready')) {
                    return;
                }

                laydate.render({
                    elem: this,
                    type: 'date',
                    trigger: 'click',
                    btns: ['confirm']
                });
                $input.data('laydate-ready', true);
            });
        });
    }

    return {
        init: init
    };
})();
