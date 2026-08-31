// Created by PhpStorm.
// Project name co_crmv5.
// User: Huang Gang
// Date: 2026/08/16
// Time: 03:47
layui.use(['form', 'layer', 'jquery'], function () {
    var form = layui.form;
    var layer = layui.layer;
    var $ = layui.jquery;
    var $form = $('#legacyCustomerAddForm');

    function syncSelectedGroup() {
        var $selected = $('#legacyCustomerGroup option:selected');
        $('#legacyCustomerGroupName').val($selected.data('group-name') || '');
    }

    form.on('select(legacyCustomerGroup)', syncSelectedGroup);
    $('#legacyCustomerAddReset').on('click', function () {
        window.setTimeout(syncSelectedGroup, 0);
    });

    form.on('submit(legacyCustomerAddSubmit)', function (submission) {
        syncSelectedGroup();
        var fields = $.extend({}, submission.field, {
            usergrpName: $('#legacyCustomerGroupName').val()
        });

        if (!fields.usergrpId || !fields.usergrpName) {
            layer.msg('请选择有效客户组', {icon: 2});
            return false;
        }
        if (fields.password !== fields.againpassword) {
            layer.msg('两次输入的密码不一致', {icon: 2});
            return false;
        }

        CrmAjax.request({
            guard: 'admin',
            url: $form.data('create-endpoint'),
            method: 'POST',
            data: {data: fields, usergrpName: fields.usergrpName},
            success: function (response) {
                if ([1000, 1001, 1002].indexOf(Number(response && response.code)) !== -1) {
                    layer.msg(response.message || '客户创建成功', {icon: 1}, function () {
                        window.location.reload();
                    });
                    return;
                }
                layer.msg((response && response.message) || '客户创建失败', {icon: 2});
            }
        });

        return false;
    });

    form.render();
});
