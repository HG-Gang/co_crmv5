layui.use(['layer'], function() {
    var layer = layui.layer, $ = layui.jquery;

    function loadDashboardData() {
        CrmAjax.request({
            guard: 'admin',
            url: '/api/admin/dashboardData',
            success: function(res) {
                if (res.code === 1000) {
                    $('#totalUsers').text(res.data.totalUsers || 0);
                    $('#totalAgents').text(res.data.totalAgents || 0);
                    $('#totalCustomers').text(res.data.totalCustomers || 0);
                    $('#pendingDeposits').text(res.data.pendingDeposits || 0);
                    $('#pendingWithdraws').text(res.data.pendingWithdraws || 0);
                    $('#todayNew').text(res.data.todayNew || 0);
                } else {
                    layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                }
            },
            error: function() {
                layer.msg(CrmLang.t('common.error'), {icon: 2});
            }
        });
    }

    loadDashboardData();
});
