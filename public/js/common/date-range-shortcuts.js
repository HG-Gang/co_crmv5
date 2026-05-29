var CrmDateRange = (function () {
    'use strict';

    function t(key) {
        return typeof CrmLang !== 'undefined' && CrmLang.t ? CrmLang.t(key) : key;
    }

    function pad(value) {
        value = Number(value || 0);
        return value < 10 ? '0' + value : String(value);
    }

    function formatDate(date) {
        return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
    }

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
