layui.use(['tree', 'form', 'layer', 'jquery'], function() {
    var tree = layui.tree, form = layui.form, layer = layui.layer, $ = layui.jquery;

    CrmLang.switchUI();

    function loadMenus() {
        CrmAjax.request({
            guard: 'admin',
            url: '/api/admin/menuTree',
            data: {guard_type: 'admin'},
            success: function(res) {
                if (res.code === 1000) {
                    tree.render({
                        elem: '#menuTree',
                        data: res.data || [],
                        edit: ['add', 'update', 'del'],
                        operate: function(obj) {
                            var type = obj.type, data = obj.data, elem = obj.elem;
                            if (type === 'add') {
                                showModal({ id: '', parent_id: data.id, title: '', url: '', icon: '', guard_type: 'admin' });
                            } else if (type === 'update') {
                                showModal(data);
                            } else if (type === 'del') {
                                layer.confirm(CrmLang.t('common.confirm'), function(index) {
                                    CrmAjax.request({
                                        guard: 'admin',
                                        url: '/api/admin/deleteMenu',
                                        data: {id: data.id},
                                        success: function(res) {
                                            if (res.code === 1000 || res.code === 1003) {
                                                layer.msg(CrmLang.t('common.success'), {icon: 1});
                                                loadMenus();
                                            } else {
                                                layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                                            }
                                        }
                                    });
                                    layer.close(index);
                                });
                            }
                        }
                    });
                }
            }
        });
    }

    function showModal(data) {
        // tree 节点来自 permissions 表，弹窗表单只暴露常用菜单字段并回写到 route/icon/name。
        form.val('menuForm', {
            id: data.id || '',
            parent_id: data.parent_id || 0,
            guard_type: data.guard_type || 'admin',
            title: data.name || data.title || '',
            url: data.path || data.url || '',
            icon: data.icon || ''
        });
        layer.open({
            type: 1,
            title: data.id ? CrmLang.t('menuMgmt.editMenu') : CrmLang.t('menuMgmt.createMenu'),
            area: ['600px', '400px'],
            content: $('#menuModal')
        });
    }

    $('#addMenu').on('click', function() {
        showModal({ id: '', parent_id: 0, title: '', url: '', icon: '', guard_type: 'admin' });
    });

    form.on('submit(saveMenu)', function(data) {
        var url = data.field.id ? '/api/admin/updateMenu' : '/api/admin/createMenu';
        if (!data.field.guard_type) data.field.guard_type = 'admin';
        CrmAjax.request({
            guard: 'admin',
            url: url,
            data: data.field,
            success: function(res) {
                if (res.code === 1000 || res.code === 1001 || res.code === 1002) {
                    layer.closeAll();
                    loadMenus();
                    layer.msg(CrmLang.t('common.success'), {icon: 1});
                } else {
                    layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                }
            }
        });
        return false;
    });

    loadMenus();
});
