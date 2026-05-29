layui.use(['tree', 'layer', 'jquery'], function() {
    var tree = layui.tree, layer = layui.layer, $ = layui.jquery;
    
    // Load permissions
    CrmAjax.request({
        guard: 'admin',
        url: '/api/admin/permissionTree',
        data: {guard_type: 'admin'},
        success: function(res) {
            if (res.code === 1000) {
                tree.render({
                    elem: '#permissionTree',
                    data: normalizeTree(res.data || []),
                    showCheckbox: false,
                    id: 'permissionId'
                });
            }
        }
    });

    $('#savePermissions').on('click', function() {
        // 当前页面是权限树预览；角色授权在角色模块通过 assignPermissions 完成。
        layer.msg(CrmLang.t('common.success'), {icon: 1});
    });

    function normalizeTree(nodes) {
        return $.map(nodes, function(node) {
            return {
                id: node.id,
                title: node.name || node.slug || String(node.id),
                spread: true,
                children: normalizeTree(node.children || [])
            };
        });
    }
});
