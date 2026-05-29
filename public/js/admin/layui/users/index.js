layui.use(['table', 'form', 'layer'], function() {
    var table = layui.table, form = layui.form, layer = layui.layer, $ = layui.jquery;

    // Initial UI i18n
    CrmLang.switchUI();

    table.render(CrmTable.layuiConfig('admin', {
        elem: '#userTable',
        url: '/api/admin/userList',
        cols: [[
            {field: 'user_id', title: 'ID', width: 100, sort: true},
            {field: 'user_name', title: CrmLang.t('user.userName'), width: 140},
            {field: 'email', title: CrmLang.t('user.email'), width: 220, templet: function(d) {
                return d.login && d.login.email ? d.login.email : '-';
            }},
            {field: 'account_type', title: CrmLang.t('user.accountType'), width: 120, templet: function(d) {
                return d.account_type == 1 ? CrmLang.t('user.agentType') : CrmLang.t('user.customerType');
            }},
            {field: 'auth_status', title: CrmLang.t('user.authStatus'), width: 120, templet: function(d) {
                if (d.auth_status == 0) return CrmLang.t('user.unverified');
                if (d.auth_status == 1) return CrmLang.t('user.verified');
                return CrmLang.t('user.reviewing');
            }},
            {field: 'created_at', title: CrmLang.t('user.createdAt'), width: 180},
            {fixed: 'right', title: CrmLang.t('common.action'), toolbar: '#userActions', width: 150}
        ]],
        parseData: CrmTable.layuiParseData(),
        done: function() {
            CrmLang.switchUI();
        }
    }));

    form.on('submit(searchUsers)', function(data) {
        table.reload('userTable', {
            where: data.field,
            page: {curr: 1}
        });
        return false;
    });

    table.on('tool(userTable)', function(obj) {
        var data = obj.data;
        if (obj.event === 'detail') {
            layer.open({
                type: 2,
                title: CrmLang.t('common.view'),
                area: ['800px', '600px'],
                content: '/admin/users/' + data.user_id
            });
        } else if (obj.event === 'status') {
            layer.confirm(CrmLang.t('common.confirm'), {title: CrmLang.t('common.status')}, function(index) {
                var login = data.login || {};
                var enabled = login.is_enabled == 1 ? 0 : 1;
                CrmAjax.request({
                    guard: 'admin',
                    url: '/api/admin/changeUserStatus',
                    data: {user_id: data.user_id, is_enabled: enabled},
                    success: function(res) {
                        if (res.code === 1000) {
                            layer.msg(CrmLang.t('common.success'), {icon: 1});
                            table.reload('userTable');
                        } else {
                            layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                        }
                    }
                });
                layer.close(index);
            });
        }
    });
});
