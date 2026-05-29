layui.use(['table', 'form', 'layer', 'jquery'], function() {
    var table = layui.table, form = layui.form, layer = layui.layer;
    var $ = layui.jquery;

    CrmLang.switchUI();

    table.render(CrmTable.layuiConfig('admin', {
        elem: '#roleTable',
        url: '/api/admin/roleList',
        cols: [[
            {field: 'id', title: 'ID', width: 80, sort: true},
            {field: 'name', title: CrmLang.t('role.name'), width: 200},
            {field: 'guard_type', title: CrmLang.t('role.guardType'), width: 120},
            {field: 'description', title: CrmLang.t('role.description')},
            {fixed: 'right', title: CrmLang.t('common.action'), toolbar: '#roleActions', width: 150}
        ]],
        parseData: CrmTable.layuiParseData(),
        done: function() {
            CrmLang.switchUI();
        }
    }));

    $('#addRole').on('click', function() {
        form.val('roleForm', { id: '', name: '', guard_type: 'admin', description: '' });
        layer.open({
            type: 1,
            title: CrmLang.t('role.createRole'),
            area: ['600px', '400px'],
            content: $('#roleModal')
        });
    });

    form.on('submit(saveRole)', function(data) {
        var url = data.field.id ? '/api/admin/updateRole' : '/api/admin/createRole';
        if (!data.field.guard_type) data.field.guard_type = 'admin';
        CrmAjax.request({
            guard: 'admin',
            url: url,
            data: data.field,
            success: function(res) {
                if (res.code === 1000 || res.code === 1001 || res.code === 1002) {
                    layer.closeAll();
                    table.reload('roleTable');
                    layer.msg(CrmLang.t('common.success'), {icon: 1});
                } else {
                    layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                }
            }
        });
        return false;
    });

    table.on('tool(roleTable)', function(obj) {
        var data = obj.data;
        if (obj.event === 'edit') {
            form.val('roleForm', data);
            layer.open({
                type: 1,
                title: CrmLang.t('role.editRole'),
                area: ['600px', '400px'],
                content: $('#roleModal')
            });
        } else if (obj.event === 'delete') {
            layer.confirm(CrmLang.t('common.confirm'), function(index) {
                CrmAjax.request({
                    guard: 'admin',
                    url: '/api/admin/deleteRole',
                    data: {id: data.id},
                    success: function(res) {
                        if (res.code === 1000 || res.code === 1003) {
                            obj.del();
                            layer.close(index);
                            layer.msg(CrmLang.t('common.success'), {icon: 1});
                        } else {
                            layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                        }
                    }
                });
            });
        }
    });
});
