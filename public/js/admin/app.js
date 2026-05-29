CRM.use(['jquery'], function() {
    var $ = CRM.$;
    console.log('Admin App Initialized');

    // Sidebar toggle
    $('.sidebar-toggle').on('click', function() {
        $('.layui-side').toggleClass('collapsed');
    });
});
