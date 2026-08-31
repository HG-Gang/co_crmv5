// Created by PhpStorm.
// Project name co_crmv5.
// User: Huang Gang
// Date: 2026/08/29
// Time: 14:29
/**
 * Aggregated Layui page module.
 * Generated from individual page entry scripts so Blade pages load one maintainable module.
 */
layui.define(function (exports) {
    'use strict';

    var registry = {};

    function once(fn) {
        var initialized = false;

        return function () {
            if (initialized) {
                return;
            }
            initialized = true;
            fn();
        };
    }

    function run(page) {
        if (!registry[page]) {
            throw new Error('Unknown Layui page module: ' + page);
        }

        registry[page]();
    }

    function has(page) {
        return !!registry[page];
    }

    function onReady(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
            return;
        }
        callback();
    }

    function serializeForm($, selector) {
        var data = {};

        $(selector).serializeArray().forEach(function (item) {
            if (item.value !== '') {
                data[item.name] = item.value;
            }
        });

        return data;
    }

    function csvFileName(response, fallback) {
        var disposition = response.headers.get('content-disposition') || '';
        var match = disposition.match(/filename="?([^";]+)"?/i);

        return match ? match[1] : fallback;
    }

    function downloadAdminCsv($, layer, url, data, fallbackName) {
        var headers = {
            Accept: 'text/csv',
            'Content-Type': 'application/json',
            'X-Locale': window.CrmLang && CrmLang.getLocale ? CrmLang.getLocale() : 'zh-CN'
        };
        var token = window.CrmAjax && CrmAjax.getToken ? CrmAjax.getToken('admin') : '';

        if (token) {
            headers.Authorization = 'Bearer ' + token;
        }

        fetch(url, {
            method: 'POST',
            headers: headers,
            body: JSON.stringify(data || {}),
            credentials: 'same-origin'
        }).then(function (response) {
            var contentType = response.headers.get('content-type') || '';

            if (!response.ok || contentType.indexOf('text/csv') === -1) {
                return response.text().then(function () {
                    throw new Error('csv_download_failed');
                });
            }

            return response.blob().then(function (blob) {
                return {blob: blob, fileName: csvFileName(response, fallbackName)};
            });
        }).then(function (download) {
            var link = document.createElement('a');
            var objectUrl = URL.createObjectURL(download.blob);

            link.href = objectUrl;
            link.download = download.fileName;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(objectUrl);
            layer.msg(CrmLang.t('common.success'), {icon: 1});
        }).catch(function () {
            layer.msg(CrmLang.t('common.error'), {icon: 2});
        });
    }

    /**
     * 把后端返回的 summary 写进后台表格的独立统计区块（需求 9）。
     *
     * 逻辑说明：
     * - 所有后台表格的统计都放在表格卡片外的 .crm-admin-stats-block 里，默认靠左对齐。
     * - 选择器限定在传入的容器内，避免对整个文档跑 [data-summary-field] 扫描。
     * - summary 缺失或字段为空时保留原有占位值（0 / 0.00），保证统计区块不会出现空白格。
     *
     * @param {string} containerSelector 统计区块容器选择器，例如 '#depositSummaryCards'。
     * @param {Object} summary 后端返回的汇总对象，键名与 data-summary-field 一一对应。
     * @returns {void}
     */
    function renderAdminTableStatistics(containerSelector, summary) {
        var container = document.querySelector(containerSelector);
        var fields;

        if (!container) {
            return;
        }

        summary = summary || {};
        fields = container.querySelectorAll('[data-summary-field]');
        Array.prototype.forEach.call(fields, function (node) {
            var key = node.getAttribute('data-summary-field');
            var value = summary[key];

            if (value === undefined || value === null || value === '') {
                return;
            }

            node.textContent = String(value);
        });
    }

    function runMarkedPages() {
        var nodes = document.querySelectorAll('[data-layui-page]');

        Array.prototype.forEach.call(nodes, function (node) {
            var page = node.getAttribute('data-layui-page');

            if (page && has(page)) {
                run(page);
            }
        });
    }

    registry['admins/index'] = once(function () {
        // Source: admins/index.js
        layui.use(['table', 'form', 'layer', 'jquery'], function() {
            var table = layui.table;
            var form = layui.form;
            var layer = layui.layer;
            var $ = layui.jquery;
            var successCodes = {1000: true, 1001: true, 1002: true, 1003: true};

            CrmLang.switchUI();

            // 管理员账号列表读取 /api/admin/admins。
            // 新增、编辑、重置密码、删除分别调用 /api/admin/createAdmin、/api/admin/updateAdmin/{id}、/api/admin/resetAdminPassword/{id}、/api/admin/deleteAdmin/{id}。
            // admins 表中 username 表示管理员登录名，email 表示管理员邮箱。
            // password 留空表示编辑时保留原密码；表格重载后需要重新应用按钮权限，权限来源为 permissions.slug。

            // 管理员账号列表：数据来自 /api/admin/admins，读取 admins 表中的后台账号基础信息。
            // 管理员账号属于高敏后台资源，新增、编辑、删除入口必须继续按 permissions.slug 做前端显隐。
            table.render(CrmTable.layuiConfig('admin', {
                elem: '#adminTable',
                id: 'adminTable',
                url: '/api/admin/admins',
                cols: [[
                    {field: 'id', title: 'ID', width: 90, sort: true},
                    // username 表示管理员登录名，对应 admins.username，也是后台登录接口读取的账号字段。
                    {field: 'username', title: CrmLang.t('admin.username'), width: 180},
                    // email 表示管理员邮箱，对应 admins.email。
                    {field: 'email', title: CrmLang.t('user.email'), width: 240},
                    {field: 'status', title: CrmLang.t('admin.status'), width: 120},
                    {field: 'created_at', title: CrmLang.t('user.createdAt'), width: 180},
                    {fixed: 'right', title: CrmLang.t('common.action'), toolbar: '#adminActions', width: 230}
                ]],
                parseData: CrmTable.layuiParseData(),
                done: function() {
                    CrmLang.switchUI();
                    refreshPermissions();
                }
            }));

            document.getElementById('reloadAdmins').onclick = function() {
                table.reload('adminTable');
            };

            $('#addAdmin').on('click', function() {
                openAdminModal({
                    id: '',
                    username: '',
                    email: '',
                    mobile: '',
                    role_id: '',
                    status: 1,
                    password: ''
                });
            });

            table.on('tool(adminTable)', function(obj) {
                if (obj.event === 'edit') {
                    openAdminModal(obj.data);
                    return;
                }

                if (obj.event === 'resetPassword') {
                    layer.prompt({
                        formType: 1,
                        title: CrmLang.t('admin.reset_password'),
                        value: ''
                    }, function(value, index) {
                        if (!value || value.length < 6) {
                            layer.msg(CrmLang.t('register.passwordRule'), {icon: 2});
                            return;
                        }

                        CrmAjax.request({
                            guard: 'admin',
                            // /api/admin/resetAdminPassword/{id} 只重置路由目标管理员密码；id 参数来自当前表格行的 admins.id。
                            url: '/api/admin/resetAdminPassword/' + encodeURIComponent(obj.data.id),
                            data: {
                                id: obj.data.id,
                                password: value
                            },
                            success: function(res) {
                                if (successCodes[res.code]) {
                                    layer.close(index);
                                    layer.msg(res.message || CrmLang.t('common.success'), {icon: 1});
                                    return;
                                }
                                layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                            }
                        });
                    });
                    return;
                }

                if (obj.event === 'delete') {
                    layer.confirm(CrmLang.t('common.confirm_delete'), function(index) {
                        CrmAjax.request({
                            guard: 'admin',
                            // /api/admin/deleteAdmin/{id} 删除指定管理员账号；id 参数来自当前表格行的 admins.id。
                            url: '/api/admin/deleteAdmin/' + encodeURIComponent(obj.data.id),
                            data: {id: obj.data.id},
                            success: function(res) {
                                if (successCodes[res.code]) {
                                    obj.del();
                                    layer.close(index);
                                    layer.msg(res.message || CrmLang.t('common.success'), {icon: 1});
                                    return;
                                }
                                layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                            }
                        });
                    });
                }
            });

            form.on('submit(saveAdmin)', function(data) {
                var id = data.field.id;
                // id 为空表示新增管理员，调用 /api/admin/createAdmin；id 有值表示编辑，调用 /api/admin/updateAdmin/{id}。
                var apiUrl = id ? '/api/admin/updateAdmin/' + encodeURIComponent(id) : '/api/admin/createAdmin';

                // password 留空表示编辑时保留原密码；password 留空表示编辑时不修改旧密码。
                // 删除空字段可避免触发后端密码规则或覆盖旧密码。
                if (id && !data.field.password) {
                    delete data.field.password;
                }

                CrmAjax.request({
                    guard: 'admin',
                    url: apiUrl,
                    data: data.field,
                    success: function(res) {
                        if (successCodes[res.code]) {
                            layer.closeAll();
                            table.reload('adminTable');
                            layer.msg(res.message || CrmLang.t('common.success'), {icon: 1});
                            return;
                        }
                        layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                    }
                });

                return false;
            });

            /**
             * 打开管理员账号表单弹窗。
             *
             * 参数含义：
             * - row：管理员行数据，来自表格行或新增按钮构造的空对象。
             * - id 为空表示新增管理员；有值表示更新指定 admins.id。
             * - username 表示管理员登录名，必须与后台登录接口读取的 admins.username 保持一致。
             * - email 表示管理员邮箱。
             * - password 新增时表示初始登录密码；编辑时留空表示不修改旧密码。
             *
             * @param {Object} row 管理员行数据或新增表单默认值。
             * @returns {void}
             */
            function openAdminModal(row) {
                form.val('adminForm', {
                    id: row.id || '',
                    username: row.username || '',
                    email: row.email || '',
                    mobile: row.mobile || '',
                    role_id: row.role_id || '',
                    status: row.status || 1,
                    password: ''
                });

                layer.open({
                    type: 1,
                    title: row.id ? CrmLang.t('admin.edit_admin') : CrmLang.t('admin.create_admin'),
                    area: ['560px', '620px'],
                    content: $('#adminModal')
                });
                form.render();
            }

            /**
             * 重新应用按钮权限。
             *
             * 逻辑说明：
             * - Layui 表格操作列会在重载后重新渲染 DOM，必须再次调用 CrmAdminPermissions.refresh()。
             * - refresh() 根据按钮上的 data-permission 与当前管理员拥有的 permissions.slug 做显隐匹配。
             * - 这里仅负责前端体验控制，真实接口鉴权仍由后端 check.permission:admin 中间件完成。
             *
             * @returns {void}
             */
            function refreshPermissions() {
                if (window.CrmAdminPermissions && window.CrmAdminPermissions.refresh) {
                    window.CrmAdminPermissions.refresh();
                }
            }
        });
    });

    registry['agent-levels/index'] = once(function () {
        // Source: agent-levels/index.js
        layui.use(['table', 'form', 'layer', 'jquery'], function() {
            var table = layui.table;
            var form = layui.form;
            var layer = layui.layer;
            var $ = layui.jquery;
            var successCodes = {1000: true, 1001: true, 1002: true, 1003: true};

            CrmLang.switchUI();

            // 代理等级参数由后端模型定义；页面展示 level_code、name、max_commission、min_commission、user_commission 等真实表字段。
            table.render(CrmTable.layuiConfig('admin', {
                elem: '#agentLevelTable',
                id: 'agentLevelTable',
                url: '/api/admin/agent-levels',
                cols: [[
                    {field: 'id', title: 'ID', width: 90, sort: true},
                    {field: 'level_code', title: CrmLang.t('admin.level'), width: 120},
                    {field: 'name', title: CrmLang.t('admin.name'), width: 180},
                    {field: 'max_commission', title: CrmLang.t('admin.max_commission'), width: 150},
                    {field: 'min_commission', title: CrmLang.t('admin.min_commission'), width: 150},
                    {field: 'user_commission', title: CrmLang.t('admin.user_commission'), width: 150},
                    {fixed: 'right', title: CrmLang.t('common.action'), toolbar: '#agentLevelActions', width: 150}
                ]],
                parseData: CrmTable.layuiParseData(),
                done: function() {
                    CrmLang.switchUI();
                    refreshPermissions();
                }
            }));

            document.getElementById('reloadAgentLevels').onclick = function() {
                table.reload('agentLevelTable');
            };

            $('#addAgentLevel').on('click', function() {
                openAgentLevelModal({
                    id: '',
                    level_code: '',
                    name: '',
                    max_commission: 0,
                    min_commission: 0,
                    user_commission: 0
                });
            });

            table.on('tool(agentLevelTable)', function(obj) {
                if (obj.event === 'edit') {
                    openAgentLevelModal(obj.data);
                    return;
                }

                if (obj.event === 'delete') {
                    layer.confirm(CrmLang.t('common.confirm'), function(index) {
                        CrmAjax.request({
                            guard: 'admin',
                            url: '/api/admin/deleteAgentLevel/' + encodeURIComponent(obj.data.id),
                            data: {id: obj.data.id},
                            success: function(res) {
                                if (successCodes[res.code]) {
                                    obj.del();
                                    layer.close(index);
                                    layer.msg(res.message || CrmLang.t('common.success'), {icon: 1});
                                    return;
                                }
                                layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                            }
                        });
                    });
                }
            });

            form.on('submit(saveAgentLevel)', function(data) {
                var id = data.field.id;
                var apiUrl = id ? '/api/admin/updateAgentLevel2/' + encodeURIComponent(id) : '/api/admin/createAgentLevel';

                CrmAjax.request({
                    guard: 'admin',
                    url: apiUrl,
                    data: data.field,
                    success: function(res) {
                        if (successCodes[res.code]) {
                            layer.closeAll();
                            table.reload('agentLevelTable');
                            layer.msg(res.message || CrmLang.t('common.success'), {icon: 1});
                            return;
                        }
                        layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                    }
                });

                return false;
            });

            /**
             * 打开代理等级表单弹窗。
             *
             * 参数含义：
             * - row：代理等级行数据，来自表格行或新增按钮构造的空对象。
             * - id 为空表示新增代理等级；有值表示更新指定等级。
             * - level_code 表示等级编码，表单字段名兼容旧页面的 level。
             * - max_commission 表示该等级允许的最大佣金比例。
             * - min_commission 表示该等级允许的最小佣金比例。
             * - user_commission 表示客户侧佣金比例，旧数据可能以 commission_rate 返回。
             *
             * @param {Object} row 代理等级行数据或新增表单默认值。
             * @returns {void}
             */
            function openAgentLevelModal(row) {
                form.val('agentLevelForm', {
                    id: row.id || '',
                    level: row.level_code || row.level || '',
                    name: row.name || '',
                    max_commission: row.max_commission || 0,
                    min_commission: row.min_commission || 0,
                    user_commission: row.user_commission || row.commission_rate || 0
                });

                layer.open({
                    type: 1,
                    title: row.id ? CrmLang.t('admin.edit_agent_level') : CrmLang.t('admin.create_agent_level'),
                    area: ['560px', '520px'],
                    content: $('#agentLevelModal')
                });
                form.render();
            }

            /**
             * 重新应用按钮权限。
             *
             * 逻辑说明：
             * - Layui 表格操作列会在重载后重新渲染 DOM，必须再次调用 CrmAdminPermissions.refresh()。
             * - refresh() 根据按钮上的 data-permission 与当前管理员拥有的 permissions.slug 做显隐匹配。
             * - 这里只有前端体验控制；新增、更新、删除接口仍由后端 check.permission:admin 做二次鉴权。
             *
             * @returns {void}
             */
            function refreshPermissions() {
                if (window.CrmAdminPermissions && window.CrmAdminPermissions.refresh) {
                    window.CrmAdminPermissions.refresh();
                }
            }
        });
    });

    registry['agents/index'] = once(function () {
        // Source: agents/index.js
        layui.use(['table', 'form', 'layer', 'jquery', 'laydate'], function() {
            var table = layui.table;
            var form = layui.form;
            var layer = layui.layer;
            var $ = layui.$;
            var laydate = layui.laydate;
            var successCodes = {1000: true, 1001: true, 1002: true, 1003: true};

            CrmLang.switchUI();
            laydate.render({elem: '#agentStartDate', type: 'date'});
            laydate.render({elem: '#agentEndDate', type: 'date'});

            // agent_id：业务代理用户 ID；user_name：代理姓名模糊筛选；后端继续追加数据范围条件。
            table.render(CrmTable.layuiConfig('admin', {
                elem: '#agentTable',
                id: 'agentTable',
                url: '/api/admin/agents',
                cols: [[
                    {field: 'user_id', title: 'ID', width: 100, sort: true},
                    {field: 'user_name', title: CrmLang.t('user.userName'), width: 160},
                    {field: 'level_id', title: CrmLang.t('admin.agentLevel'), width: 120},
                    {field: 'comm_rate', title: CrmLang.t('admin.commissionRate'), width: 140},
                    {field: 'created_at', title: CrmLang.t('user.createdAt'), width: 180},
                    {fixed: 'right', title: CrmLang.t('common.action'), toolbar: '#agentActions', width: 480}
                ]],
                parseData: CrmTable.layuiParseData(),
                done: function() {
                    CrmLang.switchUI();
                    refreshPermissions();
                }
            }));

            form.on('submit(searchAgents)', function(data) {
                table.reload('agentTable', {where: data.field, page: {curr: 1}});
                return false;
            });

            $('#exportAgents').on('click', function() {
                downloadAdminCsv($, layer, '/api/admin/exportAgents', serializeForm($, '#agentSearchForm'), 'agents_export.csv');
            });

            $('#loadAgentStats').on('click', function() {
                var payload = serializeForm($, '#agentSearchForm');
                payload.form = 1;

                CrmAjax.request({
                    guard: 'admin',
                    url: '/api/admin/agentStatsList',
                    data: payload,
                    success: function(res) {
                        var data = res.data || {};
                        var totalRow = data.totalRow || {};

                        if (!successCodes[res.code]) {
                            layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                            return;
                        }

                        layer.alert(
                            CrmLang.t('admin.agent_stats') + ': ' +
                            'BALANCE ' + (totalRow.BALANCE || '0.00') +
                            ', EQUITY ' + (totalRow.EQUITY || '0.00') +
                            ', AGENTS ' + (totalRow.mun_s || 0),
                            {title: CrmLang.t('admin.agent_stats')}
                        );
                    }
                });
            });

            table.on('tool(agentTable)', function(obj) {
                if (obj.event === 'descendants') {
                    loadDescendants(obj.data.user_id);
                    return;
                }

                if (obj.event === 'confirmAgent') {
                    confirmAgentFromRow(obj.data);
                    return;
                }

                if (obj.event === 'rejectAgentConfirmation') {
                    rejectAgentConfirmationFromRow(obj.data);
                    return;
                }

                if (obj.event === 'updateLevel') {
                    openLevelModal(obj.data);
                    return;
                }

                if (obj.event === 'updateCommission') {
                    openCommissionModal(obj.data);
                }
            });

            form.on('submit(saveAgentLevelUpdate)', function(data) {
                CrmAjax.request({
                    guard: 'admin',
                    url: '/api/admin/updateAgentLevel',
                    data: {
                        agent_id: data.field.agent_id,
                        level: data.field.level
                    },
                    success: handleMutationSuccess
                });

                return false;
            });

            form.on('submit(saveAgentCommissionUpdate)', function(data) {
                CrmAjax.request({
                    guard: 'admin',
                    url: '/api/admin/updateAgentCommission',
                    data: {
                        agent_id: data.field.agent_id,
                        comm_rate: data.field.comm_rate
                    },
                    success: handleMutationSuccess
                });

                return false;
            });

            function confirmAgentFromRow(row) {
                layer.confirm(CrmLang.t('admin.approve') + '?', function(index) {
                    layer.close(index);
                    CrmAjax.request({
                        guard: 'admin',
                        url: '/api/admin/confirmAgent',
                        data: {agent_id: row.user_id},
                        success: handleMutationSuccess
                    });
                });
            }

            function rejectAgentConfirmationFromRow(row) {
                layer.prompt({
                    formType: 2,
                    title: CrmLang.t('admin.reject')
                }, function(value, index) {
                    layer.close(index);
                    CrmAjax.request({
                        guard: 'admin',
                        url: '/api/admin/rejectAgentConfirmation',
                        data: {
                            agent_id: row.user_id,
                            reason: value
                        },
                        success: handleMutationSuccess
                    });
                });
            }

            /**
             * 查询并展示指定代理的下级关系。
             *
             * @param {number|string} agentId 业务代理用户 ID，不是后台管理员 ID。
             * @returns {void}
             */
            function loadDescendants(agentId) {
                CrmAjax.request({
                    guard: 'admin',
                    url: '/api/admin/agentDescendants',
                    data: {agent_id: agentId},
                    success: function(res) {
                        if (!successCodes[res.code]) {
                            layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                            return;
                        }

                        var rows = res.data || [];
                        layer.alert(CrmLang.t('admin.descendants') + ': ' + rows.length, {
                            title: CrmLang.t('admin.descendants')
                        });
                    }
                });
            }

            /**
             * 打开代理等级调整弹窗。
             *
             * @param {Object} row 代理行数据；user_id 为业务代理 ID，agent_level 为当前等级。
             * @returns {void}
             */
            function openLevelModal(row) {
                form.val('agentLevelUpdateForm', {
                    agent_id: row.user_id || '',
                    agent_id_display: row.user_id || '',
                    level: row.level_id || row.agent_level || ''
                });

                layer.open({
                    type: 1,
                    title: CrmLang.t('admin.update_agent_level'),
                    area: ['520px', '360px'],
                    content: $('#agentLevelUpdateModal')
                });
                form.render();
            }

            /**
             * 打开代理佣金调整弹窗。
             *
             * @param {Object} row 代理行数据；comm_rate 为当前佣金比例。
             * @returns {void}
             */
            function openCommissionModal(row) {
                form.val('agentCommissionUpdateForm', {
                    agent_id: row.user_id || '',
                    agent_id_display: row.user_id || '',
                    comm_rate: row.comm_rate || 0
                });

                layer.open({
                    type: 1,
                    title: CrmLang.t('admin.update_agent_commission'),
                    area: ['520px', '360px'],
                    content: $('#agentCommissionUpdateModal')
                });
                form.render();
            }

            /**
             * 处理等级或佣金更新成功后的统一刷新逻辑。
             *
             * @param {Object} res 接口响应对象。
             * @returns {void}
             */
            function handleMutationSuccess(res) {
                if (successCodes[res.code]) {
                    layer.closeAll();
                    table.reload('agentTable');
                    layer.msg(res.message || CrmLang.t('common.success'), {icon: 1});
                    return;
                }

                layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
            }

            /**
             * 重新应用按钮权限，确保表格操作列刷新后仍按 permissions.slug 显隐。
             *
             * @returns {void}
             */
            function refreshPermissions() {
                if (window.CrmAdminPermissions && window.CrmAdminPermissions.refresh) {
                    window.CrmAdminPermissions.refresh();
                }
            }
        });
    });

    registry['auth/login'] = once(function () {
        // Source: auth/login.js
        layui.config({
            base: '/js/apps/admin/layui/'
        }).use(['form', 'jquery', 'layer', 'common'], function() {
            var form = layui.form;
            var $ = layui.jquery;
            var layer = layui.layer;
            var CRM = layui.common;

            // 后台 Blade 登录页脚本：email 表示管理员登录邮箱，password 表示管理员登录密码，remember 表示是否延长后台登录会话。
            // 登录请求交给 /api/admin/login 和 AuthController@login 对应的认证逻辑，并统一使用 CrmAjax 全局遮罩。
            form.on('submit(adminLogin)', function(formData) {
                var fields = formData.field || {};
                var isLegacy = $('[data-legacy-admin-login="1"]').length > 0;
                var email = fields.email || '';
                var password = fields.password || '';
                var legacyUid = fields.loginUid || '';
                var legacyPassword = fields.loginPassword || '';
                var captcha = fields.cptcode || '';
                var account = isLegacy ? legacyUid : email;
                var secret = isLegacy ? legacyPassword : password;

                if (!account || !secret || (isLegacy && !captcha)) {
                    layer.msg(CRM.t('login_failed'), {icon: 2});
                    return false;
                }

                var onLoginSuccess = function(res) {
                    if (res && res.code >= 1000 && res.code < 4000 && res.data && res.data.access_token) {
                        CrmAjax.setToken('admin', res.data.access_token);
                        layer.msg(CRM.t('login_success'), {icon: 1});
                        setTimeout(function() {
                            window.location.href = CRM.route('admin_page_dashboard', {}, '/admin/dashboard');
                        }, 250);
                        return;
                    }

                    layer.msg((res && res.message) || CRM.t('login_failed'), {icon: 2});
                };
                var onLoginError = function(res) {
                    layer.msg((res && res.message) || CRM.t('network_error'), {icon: 2});
                };

                if (isLegacy) {
                    // 旧入口必须携带旧字段和 CSRF，后端再校验 Session 中的一次性验证码。
                    CrmAjax.request({
                        guard: 'admin',
                        authRedirect: false,
                        method: 'POST',
                        url: '/index/admin/logon',
                        data: {
                            loginUid: legacyUid,
                            loginPassword: legacyPassword,
                            cptcode: captcha
                        },
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || ''
                        },
                        success: onLoginSuccess,
                        error: onLoginError
                    });
                } else {
                    // 现代入口固定使用 url: '/api/admin/login'，保持现有 username/password API 契约。
                    CrmAjax.request({
                        guard: 'admin',
                        authRedirect: false,
                        method: 'POST',
                        url: '/api/admin/login',
                        data: {
                            username: email,
                            password: password
                        },
                        success: onLoginSuccess,
                        error: onLoginError
                    });
                }

                return false;
            });

            $('#legacyAdminCaptcha').on('click', function() {
                this.src = '/index/admin/captcha?' + Date.now();
            });

            $('.lang-switch').on('click', function() {
                var lang = $(this).data('lang');
                // 切换后台登录页语言，CRM.switchLang 会同步本地语言状态并刷新页面文案。
                CRM.switchLang(lang);
            });
        });
    });

    registry['authentications/detail'] = once(function () {
        // Source: authentications/detail.js
        layui.use(['form', 'layer', 'jquery'], function () {
            var form = layui.form;
            var layer = layui.layer;
            var $ = layui.jquery;
            var $page = $('[data-auth-detail-page="1"]').first();
            var mode = String($page.attr('data-auth-detail-mode') || '');
            var pageUserId = String($page.attr('data-auth-detail-user-id') || '').trim();
            var successCodes = {1000: true, 1001: true, 1002: true, 1003: true};
            var currentDetail = null;
            var reviewSubmitting = false;

            if (!$page.length) {
                return;
            }

            CrmLang.switchUI();
            bindDetailEvents();

            if ((mode !== 'auth' && mode !== 'show') || !/^[1-9]\d*$/.test(pageUserId)) {
                showDetailError(CrmLang.t('response.validation_failed'));
                return;
            }

            loadAuthDetail();

            function bindDetailEvents() {
                $('#authDetailBack').on('click', function () {
                    if (window.history.length > 1) {
                        window.history.back();
                        return;
                    }

                    window.location.href = window.crmRoute
                        ? window.crmRoute('admin_page_authentications', {}, '/admin/authentications')
                        : '/admin/authentications';
                });

                $('#authDetailRetry').on('click', loadAuthDetail);
                $('.auth-detail-image-item a').on('click', function (event) {
                    if ($(this).attr('aria-disabled') === 'true') {
                        event.preventDefault();
                    }
                });

                form.on('submit(submitAuthDetailReview)', function (data) {
                    var payload;

                    if (reviewSubmitting || !currentDetail) {
                        return false;
                    }

                    clearDetailReviewErrors();
                    try {
                        payload = buildAuthDetailReviewPayload(data.field || {}, currentDetail);
                    } catch (error) {
                        layer.msg(error.message || CrmLang.t('response.validation_failed'), {icon: 2});
                        return false;
                    }

                    setDetailReviewSubmitting(true);
                    CrmAjax.request({
                        guard: 'admin',
                        url: '/api/admin/reviewAuth',
                        data: payload,
                        success: function (res) {
                            setDetailReviewSubmitting(false);
                            if (!res || !successCodes[res.code]) {
                                layer.msg((res && res.message) || CrmLang.t('common.error'), {icon: 2});
                                return;
                            }

                            layer.msg(res.message || CrmLang.t('common.success'), {icon: 1});
                            loadAuthDetail();
                        },
                        error: function (res) {
                            setDetailReviewSubmitting(false);
                            layer.msg((res && res.message) || CrmLang.t('common.error'), {icon: 2});
                        }
                    });

                    return false;
                });
            }

            function loadAuthDetail() {
                currentDetail = null;
                clearDetailReviewErrors();
                setDetailState('loading');

                CrmAjax.request({
                    guard: 'admin',
                    url: '/api/admin/authDetail',
                    data: {user_id: pageUserId},
                    success: function (res) {
                        var detail = res && res.data;
                        var responseUserId = String(detail && detail.user_id);

                        if (!res || !successCodes[res.code] || !detail || responseUserId !== pageUserId) {
                            showDetailError((res && res.message) || CrmLang.t('admin.auth_detail_load_failed'));
                            return;
                        }

                        currentDetail = detail;
                        renderAuthDetail(detail);
                        setDetailState('content');
                    },
                    error: function (res) {
                        showDetailError((res && res.message) || CrmLang.t('admin.auth_detail_load_failed'));
                    }
                });
            }

            function setDetailState(state) {
                $('#authDetailLoading').prop('hidden', state !== 'loading');
                $('#authDetailError').prop('hidden', state !== 'error');
                $('#authDetailContent').prop('hidden', state !== 'content');
            }

            function showDetailError(message) {
                $('#authDetailErrorMessage').text(message || CrmLang.t('admin.auth_detail_load_failed'));
                setDetailState('error');
                refreshDetailIcons();
            }

            function renderAuthDetail(detail) {
                setDetailText('#authDetailUserId', detail.user_id);
                setDetailText('#authDetailUserName', detail.user_name);
                setDetailText('#authDetailPhone', detail.phone);
                setDetailText('#authDetailEmail', detail.email);
                setDetailText('#authDetailAccountType', accountTypeText(detail.account_type));
                setDetailText('#authDetailCreatedAt', formatDetailTime(detail.created_at));
                setDetailText('#authDetailUpdatedAt', formatDetailTime(detail.updated_at));
                setDetailText('#authDetailIdCardNo', detail.id_card_no);
                setDetailText('#authDetailIdCardRemarks', detail.id_card_remarks);
                setDetailText('#authDetailBankNo', detail.bank_no);
                setDetailText('#authDetailBankName', detail.bank_name);
                setDetailText('#authDetailBankAddr', detail.bank_addr);
                setDetailText('#authDetailBankRemarks', detail.bank_remarks);

                renderDetailStatus('#authDetailIdCardStatus', 'id_card', detail.id_card_status);
                renderDetailStatus('#authDetailBankStatus', 'bank', detail.bank_status);
                renderDetailImage('#authDetailIdCardFront', '#authDetailIdCardFrontLink', detail.id_card_front_url);
                renderDetailImage('#authDetailIdCardBack', '#authDetailIdCardBackLink', detail.id_card_back_url);
                renderDetailImage('#authDetailBankCardFront', '#authDetailBankCardFrontLink', detail.bank_card_img_url);
                renderDetailImage('#authDetailBankCardBack', '#authDetailBankCardBackLink', detail.bank_card_back_img_url);

                if (mode === 'auth') {
                    configureDetailReview(detail);
                }
                refreshDetailIcons();
            }

            function setDetailText(selector, value) {
                var text = value === null || typeof value === 'undefined' || String(value).trim() === ''
                    ? '-'
                    : String(value);

                $(selector).text(text);
            }

            function renderDetailStatus(selector, component, value) {
                var status = String(value);
                var labels = {
                    '0': CrmLang.t('admin.auth_not_submitted'),
                    '1': CrmLang.t('admin.auth_reviewing'),
                    '2': CrmLang.t('admin.auth_passed'),
                    '3': CrmLang.t('admin.auth_changed'),
                    '4': CrmLang.t('admin.auth_rejected')
                };
                var componentLabel = CrmLang.t(component === 'id_card' ? 'admin.id_card_status' : 'admin.bank_status');

                $(selector)
                    .attr('data-status', labels[status] ? status : 'unknown')
                    .text(componentLabel + ': ' + (labels[status] || CrmLang.t('admin.account_type_unknown')));
            }

            function renderDetailImage(imageSelector, linkSelector, rawUrl) {
                var $image = $(imageSelector);
                var $link = $(linkSelector);
                var $empty = $link.find('.auth-detail-image-empty');
                var url = safeDetailImageUrl(rawUrl);

                $image.off('error.authDetail');
                if (!url) {
                    $image.removeAttr('src').prop('hidden', true);
                    $link.attr('href', 'javascript:;').attr('aria-disabled', 'true');
                    $empty.prop('hidden', false);
                    return;
                }

                $image.on('error.authDetail', function () {
                    $image.removeAttr('src').prop('hidden', true);
                    $link.attr('href', 'javascript:;').attr('aria-disabled', 'true');
                    $empty.prop('hidden', false);
                });
                $empty.prop('hidden', true);
                $link.attr('href', url).attr('aria-disabled', 'false');
                $image.attr('src', url).prop('hidden', false);
            }

            function safeDetailImageUrl(value) {
                var url = String(value || '').trim();

                if (/^https?:\/\//i.test(url)) {
                    return url;
                }
                if (url.charAt(0) === '/' && url.charAt(1) !== '/') {
                    return url;
                }

                return '';
            }

            function formatDetailTime(value) {
                var text = String(value || '').trim();
                var date;

                if (!text) {
                    return '-';
                }

                date = /^\d{10}$/.test(text) ? new Date(parseInt(text, 10) * 1000) : new Date(text);
                return Number.isNaN(date.getTime()) ? '-' : date.toLocaleString();
            }

            function accountTypeText(value) {
                if (String(value) === '1') {
                    return CrmLang.t('admin.account_type_agent');
                }
                if (String(value) === '2') {
                    return CrmLang.t('admin.account_type_customer');
                }

                return CrmLang.t('admin.account_type_unknown');
            }

            function configureDetailReview(detail) {
                var reviewableCount = 0;

                ['id_card', 'bank'].forEach(function (component) {
                    var reviewable = isAuthDetailComponentReviewable(detail, component);
                    var $component = $('[data-auth-detail-review-component="' + component + '"]');

                    $component.prop('hidden', !reviewable);
                    $component.find(':input').prop('disabled', !reviewable);
                    if (reviewable) {
                        reviewableCount += 1;
                    }
                });

                $('#authDetailNoReview').prop('hidden', reviewableCount !== 0);
                $('#authDetailReviewActions').prop('hidden', reviewableCount === 0);
                setDetailReviewSubmitting(false);
                form.val('authDetailReviewForm', {
                    id_card_decision: '1',
                    id_card_reason: '',
                    bank_decision: '1',
                    bank_reason: ''
                });
                form.render();

                if (window.CrmAdminPermissions && window.CrmAdminPermissions.refresh) {
                    window.CrmAdminPermissions.refresh();
                }
            }

            function isAuthDetailComponentReviewable(detail, component) {
                var field = component === 'id_card' ? 'id_card_status' : 'bank_status';
                var status = String(detail && detail[field]);

                if (component === 'id_card') {
                    return status === '1';
                }

                return component === 'bank' && (status === '1' || status === '3');
            }

            function buildAuthDetailReviewPayload(fields, detail) {
                var userId = String(detail && detail.user_id);
                var payload = {user_id: userId};
                var reviewableCount = 0;

                if (!/^[1-9]\d*$/.test(userId) || userId !== pageUserId) {
                    throw new Error(CrmLang.t('response.validation_failed'));
                }

                ['id_card', 'bank'].forEach(function (component) {
                    var decisionField;
                    var reasonField;
                    var decision;
                    var reason;

                    if (!isAuthDetailComponentReviewable(detail, component)) {
                        return;
                    }

                    reviewableCount += 1;
                    decisionField = component + '_decision';
                    reasonField = component + '_reason';
                    decision = String(fields[decisionField] || '');
                    reason = String(fields[reasonField] || '').trim();

                    if (decision !== '1' && decision !== '2') {
                        throw new Error(CrmLang.t('response.validation_failed'));
                    }
                    if (decision === '2' && !reason) {
                        setDetailReviewFieldError(reasonField, CrmLang.t('admin.reject_reason_required'));
                        throw new Error(CrmLang.t('admin.reject_reason_required'));
                    }

                    payload[decisionField] = decision;
                    payload[reasonField] = reason;
                });

                if (reviewableCount === 0) {
                    throw new Error(CrmLang.t('admin.auth_no_reviewable_component'));
                }

                return payload;
            }

            function clearDetailReviewErrors() {
                $('[data-auth-detail-error-for]').text('').prop('hidden', true);
                $('#authDetailReviewForm .layui-form-danger')
                    .removeClass('layui-form-danger')
                    .removeAttr('aria-invalid');
            }

            function setDetailReviewFieldError(field, message) {
                $('[data-auth-detail-error-for="' + field + '"]').text(message).prop('hidden', false);
                $('#authDetailReviewForm [name="' + field + '"]')
                    .addClass('layui-form-danger')
                    .attr('aria-invalid', 'true')
                    .trigger('focus');
            }

            function setDetailReviewSubmitting(submitting) {
                reviewSubmitting = submitting;
                $('#authDetailReviewActions [lay-filter="submitAuthDetailReview"]')
                    .prop('disabled', submitting)
                    .toggleClass('layui-btn-disabled', submitting)
                    .attr('aria-busy', submitting ? 'true' : 'false');
            }

            function refreshDetailIcons() {
                if (window.lucide && window.lucide.createIcons) {
                    window.lucide.createIcons();
                }
            }
        });
    });

    registry['authentications/index'] = once(function () {
        // Source: authentications/index.js
        layui.use(['table', 'form', 'layer', 'jquery', 'laydate'], function () {
            var table = layui.table;
            var form = layui.form;
            var layer = layui.layer;
            var $ = layui.jquery;
            var laydate = layui.laydate;
            var successCodes = {1000: true, 1001: true, 1002: true, 1003: true};
            var currentAuthReviewRow = null;
            var authReviewSubmitting = false;

            CrmLang.switchUI();
            laydate.render({elem: '#authPendingStartDate', type: 'date'});
            laydate.render({elem: '#authPendingEndDate', type: 'date'});
            laydate.render({elem: '#authCertifiedStartDate', type: 'date'});
            laydate.render({elem: '#authCertifiedEndDate', type: 'date'});

            // 待审核认证表格：读取 user_auths + user_infos，后端按 /api/admin/authPendingList 权限和数据范围过滤。
            table.render(CrmTable.layuiConfig('admin', {
                elem: '#authPendingTable',
                id: 'authPendingTable',
                url: '/api/admin/authPendingList',
                where: serializeForm($, '#authPendingSearchForm'),
                cols: [[
                    {field: 'user_id', title: CrmLang.t('admin.userId'), width: 110},
                    {field: 'user_name', title: CrmLang.t('admin.user_name'), width: 140},
                    {field: 'parent_id', title: CrmLang.t('admin.parent_id'), width: 110},
                    {field: 'id_card_no', title: CrmLang.t('admin.id_card_no'), width: 190},
                    {field: 'id_card_status', title: CrmLang.t('admin.id_card_status'), width: 130, templet: statusText('id_card_status')},
                    {field: 'review_bank_no', title: CrmLang.t('admin.bank_no'), width: 180},
                    {field: 'review_bank_name', title: CrmLang.t('admin.bank_name'), width: 160},
                    {field: 'review_bank_addr', title: CrmLang.t('admin.bank_addr'), width: 180},
                    {field: 'bank_status', title: CrmLang.t('admin.bank_status'), width: 130, templet: statusText('bank_status')},
                    {field: 'created_at', title: CrmLang.t('admin.created_at'), width: 170, templet: timeText('created_at')},
                    {title: CrmLang.t('common.actions'), width: 190, fixed: 'right', toolbar: '#authPendingToolbar'}
                ]],
                parseData: CrmTable.layuiParseData(),
                done: function () {
                    CrmLang.switchUI();
                    refreshPermissions();
                }
            }));

            // 已审核认证表格：旧项目 userCertifiedSearch 的新项目落点，用于查看身份证和银行卡均通过的用户资料。
            table.render(CrmTable.layuiConfig('admin', {
                elem: '#authCertifiedTable',
                id: 'authCertifiedTable',
                url: '/api/admin/authCertifiedList',
                where: serializeForm($, '#authCertifiedSearchForm'),
                cols: [[
                    {field: 'user_id', title: CrmLang.t('admin.userId'), width: 110},
                    {field: 'user_name', title: CrmLang.t('admin.user_name'), width: 140},
                    {field: 'account_type', title: CrmLang.t('admin.account_type'), width: 120, templet: accountTypeText},
                    {field: 'id_card_no', title: CrmLang.t('admin.id_card_no'), width: 190},
                    {field: 'bank_no', title: CrmLang.t('admin.bank_no'), width: 180},
                    {field: 'bank_name', title: CrmLang.t('admin.bank_name'), width: 160},
                    {field: 'id_card_status', title: CrmLang.t('admin.id_card_status'), width: 130, templet: statusText('id_card_status')},
                    {field: 'bank_status', title: CrmLang.t('admin.bank_status'), width: 130, templet: statusText('bank_status')},
                    {field: 'updated_at', title: CrmLang.t('admin.updated_at'), width: 170, templet: timeText('updated_at')},
                    {title: CrmLang.t('common.actions'), width: 100, fixed: 'right', toolbar: '#authCertifiedToolbar'}
                ]],
                parseData: CrmTable.layuiParseData(),
                done: function () {
                    CrmLang.switchUI();
                    refreshPermissions();
                }
            }));

            form.on('submit(searchAuthPending)', function (data) {
                // data.field：待审列表筛选参数，字段名与 AuthenticationController::pendingList 保持一致。
                table.reload('authPendingTable', {where: data.field, page: {curr: 1}});
                return false;
            });

            form.on('submit(searchAuthCertified)', function (data) {
                // data.field：已审列表筛选参数，字段名与 AuthenticationController::certifiedList 保持一致。
                table.reload('authCertifiedTable', {where: data.field, page: {curr: 1}});
                return false;
            });

            table.on('tool(authPendingTable)', function (obj) {
                if (obj.event === 'detail') {
                    openAuthDetail(obj.data, 'auth');
                    return;
                }

                if (obj.event !== 'review') {
                    return;
                }

                var row = obj.data || {};
                var canReviewIdCard = isAuthReviewComponentReviewable(row, 'id_card');
                var canReviewBank = isAuthReviewComponentReviewable(row, 'bank');

                if (!canReviewIdCard && !canReviewBank) {
                    layer.msg(CrmLang.t('response.validation_failed'), {icon: 2});
                    return;
                }

                currentAuthReviewRow = row;
                setAuthReviewSubmitting(false);
                setReviewComponentState('id_card', canReviewIdCard);
                setReviewComponentState('bank', canReviewBank);
                form.val('authReviewForm', {
                    user_id: row.user_id,
                    display_user_id: row.user_id,
                    id_card_decision: '1',
                    id_card_reason: '',
                    bank_decision: '1',
                    bank_reason: ''
                });

                layer.open({
                    type: 1,
                    title: CrmLang.t('admin.review_auth'),
                    area: ['min(680px, 92vw)', 'min(640px, 88vh)'],
                    content: layui.$('#authReviewModal')
                });
                form.render();
            });

            table.on('tool(authCertifiedTable)', function (obj) {
                if (obj.event === 'detail') {
                    openAuthDetail(obj.data, 'show');
                }
            });

            form.on('submit(submitAuthReview)', function (data) {
                var payload;

                if (authReviewSubmitting) {
                    return false;
                }

                try {
                    payload = buildAuthReviewPayload(data.field || {}, currentAuthReviewRow);
                } catch (error) {
                    layer.msg(error.message || CrmLang.t('response.validation_failed'), {icon: 2});
                    return false;
                }

                setAuthReviewSubmitting(true);
                CrmAjax.request({
                    guard: 'admin',
                    url: '/api/admin/reviewAuth',
                    data: payload,
                    success: function (res) {
                        setAuthReviewSubmitting(false);
                        if (!successCodes[res.code]) {
                            layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                            return;
                        }

                        layer.closeAll('page');
                        layer.msg(res.message || CrmLang.t('common.success'), {icon: 1});
                        table.reload('authPendingTable');
                        table.reload('authCertifiedTable');
                    },
                    error: function (res) {
                        setAuthReviewSubmitting(false);
                        layer.msg((res && res.message) || CrmLang.t('common.error'), {icon: 2});
                    }
                });

                return false;
            });

            function setAuthReviewSubmitting(submitting) {
                authReviewSubmitting = submitting;
                $('#authReviewForm [lay-filter="submitAuthReview"]')
                    .prop('disabled', submitting)
                    .toggleClass('layui-btn-disabled', submitting)
                    .attr('aria-busy', submitting ? 'true' : 'false');
            }

            /**
             * Show and enable a reviewable component, or exclude it from the form entirely.
             * @param {string} component id_card or bank.
             * @param {boolean} enabled Whether the component can be reviewed for the current row.
             * @returns {void}
             */
            function setReviewComponentState(component, enabled) {
                var $component = $('#authReviewForm [data-auth-review-component="' + component + '"]');

                $component.toggle(enabled);
                $component.find(':input').prop('disabled', !enabled);
            }

            /**
             * Build a component-scoped review request from the current row state.
             * @param {Object} fields Submitted Layui form fields.
             * @param {Object|null} row Current pending-authentication table row.
             * @returns {Object} Payload accepted by /api/admin/reviewAuth.
             * @throws {Error} When no component is reviewable or a component decision is invalid.
             */
            function buildAuthReviewPayload(fields, row) {
                var userId = String(row && row.user_id);
                if (!/^[1-9]\d*$/.test(userId)) {
                    throw new Error(CrmLang.t('response.validation_failed'));
                }

                var payload = {
                    user_id: userId
                };
                var reviewableCount = 0;

                ['id_card', 'bank'].forEach(function (component) {
                    if (!isAuthReviewComponentReviewable(row, component)) {
                        return;
                    }

                    reviewableCount += 1;
                    var decisionField = component + '_decision';
                    var reasonField = component + '_reason';
                    var decision = String(fields[decisionField] || '');
                    var reason = String(fields[reasonField] || '').trim();

                    if (decision !== '1' && decision !== '2') {
                        throw new Error(CrmLang.t('response.validation_failed'));
                    }
                    if (decision === '2' && !reason) {
                        throw new Error(
                            CrmLang.t(component === 'id_card' ? 'admin.id_card_status' : 'admin.bank_status')
                            + ': ' + CrmLang.t('admin.reject_reason')
                        );
                    }

                    payload[decisionField] = decision;
                    payload[reasonField] = reason;
                });

                if (reviewableCount === 0) {
                    throw new Error(CrmLang.t('response.validation_failed'));
                }

                return payload;
            }

            /**
             * Check the approved review transition entry states for one component.
             * @param {Object|null} row Current pending-authentication table row.
             * @param {string} component id_card or bank.
             * @returns {boolean} Whether the component may be reviewed.
             */
            function isAuthReviewComponentReviewable(row, component) {
                var statusField = component === 'id_card' ? 'id_card_status' : 'bank_status';
                var status = String(row && row[statusField]);

                if (component === 'id_card') {
                    return status === '1';
                }

                return component === 'bank' && (status === '1' || status === '3');
            }

            function openAuthDetail(row, mode) {
                var userId = String(row && row.user_id);

                if (!/^[1-9]\d*$/.test(userId)) {
                    layer.msg(CrmLang.t('response.validation_failed'), {icon: 2});
                    return;
                }

                window.location.href = buildAuthDetailPageUrl(userId, mode);
            }

            function buildAuthDetailPageUrl(userId, mode) {
                return '/admin/authentications/'
                    + encodeURIComponent(userId)
                    + '/detail/' + mode;
            }

            /**
             * 生成认证状态列渲染函数。
             *
             * @param {string} field 字段名，支持 id_card_status 或 bank_status。
             * @returns {Function} Layui 表格 templet 渲染函数。
             */
            function statusText(field) {
                return function (row) {
                    var value = parseInt(row[field], 10);
                    var map = {
                        0: CrmLang.t('admin.auth_not_submitted'),
                        1: CrmLang.t('admin.auth_reviewing'),
                        2: CrmLang.t('admin.auth_passed'),
                        3: CrmLang.t('admin.auth_changed'),
                        4: CrmLang.t('admin.auth_rejected')
                    };

                    return map[value] || CrmLang.t('admin.account_type_unknown');
                };
            }

            /**
             * 生成 10 位时间戳列渲染函数。
             *
             * @param {string} field 字段名，值来自 user_auths.created_at 或 user_auths.updated_at。
             * @returns {Function} Layui 表格 templet 渲染函数。
             */
            function timeText(field) {
                return function (row) {
                    var timestamp = parseInt(row[field], 10);
                    if (!timestamp) {
                        return '-';
                    }

                    return new Date(timestamp * 1000).toLocaleString();
                };
            }

            /**
             * 渲染账号类型。
             *
             * @param {Object} row 当前表格行，account_type=1 表示代理商，2 表示普通客户。
             * @returns {string} 本地化后的账号类型文本。
             */
            function accountTypeText(row) {
                if (parseInt(row.account_type, 10) === 1) {
                    return CrmLang.t('admin.account_type_agent');
                }

                if (parseInt(row.account_type, 10) === 2) {
                    return CrmLang.t('admin.account_type_customer');
                }

                return CrmLang.t('admin.account_type_unknown');
            }

            /**
             * 刷新页面按钮权限。
             *
             * @returns {void}
             */
            function refreshPermissions() {
                if (window.CrmAdminPermissions && window.CrmAdminPermissions.refresh) {
                    window.CrmAdminPermissions.refresh();
                }
                if (window.lucide && window.lucide.createIcons) {
                    window.lucide.createIcons();
                }
            }
        });
    });

    registry['big-agents/index'] = once(function () {
        // Source: big-agents/index.js
        layui.use(['table', 'form', 'layer', 'jquery'], function() {
            var table = layui.table;
            var form = layui.form;
            var layer = layui.layer;
            var $ = layui.jquery;
            var successCodes = {1000: true, 1001: true, 1002: true, 1003: true};

            CrmLang.switchUI();

            // 大代理列表读取 /api/admin/bigAgentList。
            // 新增、编辑、删除分别调用 /api/admin/createBigAgent、/api/admin/updateBigAgent/{id}、/api/admin/deleteBigAgent/{id}。
            // id 为空表示新增大代理，username 表示大代理登录名，password 表示大代理登录密码。
            // is_enabled 表示大代理账号是否启用；表格重载后重新应用按钮权限，权限来源为 permissions.slug。

            // 大代理列表：数据来自 /api/admin/bigAgentList，读取 big_agents 表中的账号基础信息。
            table.render(CrmTable.layuiConfig('admin', {
                elem: '#bigAgentTable',
                id: 'bigAgentTable',
                url: '/api/admin/bigAgentList',
                cols: [[
                    {field: 'id', title: 'ID', width: 90, sort: true},
                    // username 表示大代理登录名，对应 big_agents.username。
                    {field: 'username', title: CrmLang.t('admin.username'), width: 180},
                    {field: 'email', title: CrmLang.t('user.email'), width: 220},
                    {field: 'sub_agent_ids', title: CrmLang.t('front.sub_agents'), width: 180},
                    // is_enabled 表示大代理账号是否启用，对应 big_agents.is_enabled，前台大代理登录会读取该字段。
                    {field: 'is_enabled', title: CrmLang.t('admin.status'), width: 120, templet: function(row) {
                        return String(row.is_enabled) === '1' ? CrmLang.t('common.enabled') : CrmLang.t('common.disabled');
                    }},
                    {field: 'created_at', title: CrmLang.t('user.createdAt'), width: 180},
                    {fixed: 'right', title: CrmLang.t('common.action'), toolbar: '#bigAgentActions', width: 150}
                ]],
                parseData: CrmTable.layuiParseData(),
                done: function() {
                    CrmLang.switchUI();
                    refreshPermissions();
                }
            }));

            document.getElementById('reloadBigAgents').onclick = function() {
                table.reload('bigAgentTable');
            };

            $('#addBigAgent').on('click', function() {
                openBigAgentModal({
                    id: '',
                    username: '',
                    email: '',
                    sub_agent_ids: '',
                    password: '',
                    is_enabled: 1
                });
            });

            table.on('tool(bigAgentTable)', function(obj) {
                if (obj.event === 'edit') {
                    openBigAgentModal(obj.data);
                    return;
                }

                if (obj.event === 'delete') {
                    // /api/admin/deleteBigAgent/{id} 删除指定大代理账号；id 参数来自当前表格行的 big_agents.id。
                    layer.confirm(CrmLang.t('common.confirm'), function(index) {
                        CrmAjax.request({
                            guard: 'admin',
                            url: '/api/admin/deleteBigAgent/' + encodeURIComponent(obj.data.id),
                            data: {id: obj.data.id},
                            success: function(res) {
                                if (successCodes[res.code]) {
                                    obj.del();
                                    layer.close(index);
                                    layer.msg(res.message || CrmLang.t('common.success'), {icon: 1});
                                    return;
                                }
                                layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                            }
                        });
                    });
                }
            });

            form.on('submit(saveBigAgent)', function(data) {
                // id 为空表示新增大代理，调用 /api/admin/createBigAgent；id 有值表示编辑，调用 /api/admin/updateBigAgent/{id}。
                var id = data.field.id;
                var apiUrl = id ? '/api/admin/updateBigAgent/' + encodeURIComponent(id) : '/api/admin/createBigAgent';

                // password 表示大代理登录密码；编辑时留空由后端保留原密码。
                // is_enabled 表示大代理账号是否启用，提交前统一归一化为 1/0。
                data.field.is_enabled = data.field.is_enabled ? 1 : 0;
                data.field.sub_agent_ids = String(data.field.sub_agent_ids || '').trim().replace(/\s*,\s*/g, ',');
                CrmAjax.request({
                    guard: 'admin',
                    url: apiUrl,
                    data: data.field,
                    success: function(res) {
                        if (successCodes[res.code]) {
                            layer.closeAll();
                            table.reload('bigAgentTable');
                            layer.msg(res.message || CrmLang.t('common.success'), {icon: 1});
                            return;
                        }
                        layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                    }
                });

                return false;
            });

            /**
             * 打开大代理新增/编辑弹窗。
             *
             * @param {Object} row 大代理行数据；id 为空时创建账号，id 有值时更新账号。
             * @returns {void}
             */
            function openBigAgentModal(row) {
                form.val('bigAgentForm', {
                    id: row.id || '',
                    username: row.username || '',
                    email: row.email || '',
                    sub_agent_ids: row.sub_agent_ids || '',
                    password: '',
                    is_enabled: String(typeof row.is_enabled === 'undefined' ? 1 : row.is_enabled) === '1'
                });

                layer.open({
                    type: 1,
                    title: row.id ? CrmLang.t('admin.edit_big_agent') : CrmLang.t('admin.create_big_agent'),
                    area: ['560px', '380px'],
                    content: $('#bigAgentModal')
                });
                form.render();
            }

            /**
             * 重新应用按钮权限：表格重载后操作列会重新生成，需要按 permissions.slug 隐藏无权限按钮。
             *
             * @returns {void}
             */
            function refreshPermissions() {
                if (window.CrmAdminPermissions && window.CrmAdminPermissions.refresh) {
                    window.CrmAdminPermissions.refresh();
                }
            }
        });
    });

    registry['blacklist/index'] = once(function () {
        // Source: blacklist/index.js
        layui.use(['table', 'form', 'layer', 'jquery'], function() {
            var table = layui.table;
            var form = layui.form;
            var layer = layui.layer;
            var $ = layui.jquery;
            var successCodes = {1000: true, 1001: true, 1002: true, 1003: true};

            CrmLang.switchUI();

            // 黑名单列表读取 /api/admin/blacklistList。
            // 新增、编辑、删除分别调用 /api/admin/createBlacklist、/api/admin/updateBlacklist/{id}、/api/admin/deleteBlacklist/{id}。
            // keyword 表示统一搜索关键字，name 表示黑名单姓名，id_card 表示证件号码。
            // email 表示邮箱，phone 表示手机号，remark 表示备注，id 为空表示新增黑名单。
            // 表格重载后重新应用按钮权限，权限来源为 permissions.slug。

            // 黑名单列表：数据来自 /api/admin/blacklistList，读取 blacklists 表并按 keyword 统一搜索。
            // keyword 表示统一搜索关键字，可匹配姓名、证件号码、邮箱和手机号，具体 SQL 条件由 BlacklistController@index 控制。
            table.render(CrmTable.layuiConfig('admin', {
                elem: '#blacklistTable',
                id: 'blacklistTable',
                url: '/api/admin/blacklistList',
                cols: [[
                    {field: 'id', title: 'ID', width: 90, sort: true},
                    // name 表示黑名单姓名，对应 blacklists.name。
                    {field: 'name', title: CrmLang.t('admin.name'), width: 160},
                    // id_card 表示证件号码，对应 blacklists.id_card。
                    {field: 'id_card', title: CrmLang.t('admin.idCard'), width: 200},
                    // email 表示邮箱，对应 blacklists.email。
                    {field: 'email', title: CrmLang.t('user.email'), width: 220},
                    // phone 表示手机号，对应 blacklists.phone。
                    {field: 'phone', title: CrmLang.t('admin.phone'), width: 160},
                    {fixed: 'right', title: CrmLang.t('common.action'), toolbar: '#blacklistActions', width: 150}
                ]],
                parseData: CrmTable.layuiParseData(),
                done: function() {
                    CrmLang.switchUI();
                    refreshPermissions();
                }
            }));

            form.on('submit(searchBlacklist)', function(data) {
                table.reload('blacklistTable', {where: data.field, page: {curr: 1}});
                return false;
            });

            $('#addBlacklist').on('click', function() {
                openBlacklistModal({
                    id: '',
                    name: '',
                    id_card: '',
                    email: '',
                    phone: '',
                    remark: ''
                });
            });

            table.on('tool(blacklistTable)', function(obj) {
                if (obj.event === 'edit') {
                    openBlacklistModal(obj.data);
                    return;
                }

                if (obj.event === 'delete') {
                    // /api/admin/deleteBlacklist/{id} 删除指定黑名单记录，id 参数来自当前表格行的 blacklists.id。
                    layer.confirm(CrmLang.t('common.confirm'), function(index) {
                        CrmAjax.request({
                            guard: 'admin',
                            url: '/api/admin/deleteBlacklist/' + encodeURIComponent(obj.data.id),
                            data: {id: obj.data.id},
                            success: function(res) {
                                if (successCodes[res.code]) {
                                    obj.del();
                                    layer.close(index);
                                    layer.msg(res.message || CrmLang.t('common.success'), {icon: 1});
                                    return;
                                }
                                layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                            }
                        });
                    });
                }
            });

            form.on('submit(saveBlacklist)', function(data) {
                // id 为空表示新增黑名单，调用 /api/admin/createBlacklist；id 有值表示编辑，调用 /api/admin/updateBlacklist/{id}。
                var id = data.field.id;
                var apiUrl = id ? '/api/admin/updateBlacklist/' + encodeURIComponent(id) : '/api/admin/createBlacklist';

                // remark 表示备注，和 name、id_card、email、phone 一起提交给 BlacklistController 校验保存。
                CrmAjax.request({
                    guard: 'admin',
                    url: apiUrl,
                    data: data.field,
                    success: function(res) {
                        if (successCodes[res.code]) {
                            layer.closeAll();
                            table.reload('blacklistTable');
                            layer.msg(res.message || CrmLang.t('common.success'), {icon: 1});
                            return;
                        }
                        layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                    }
                });

                return false;
            });

            /**
             * 打开黑名单新增/编辑弹窗。
             *
             * @param {Object} row 黑名单行数据；id 为空表示新增，id 有值表示更新指定记录。
             * @returns {void}
             */
            function openBlacklistModal(row) {
                form.val('blacklistForm', {
                    id: row.id || '',
                    name: row.name || '',
                    id_card: row.id_card || '',
                    email: row.email || '',
                    phone: row.phone || '',
                    remark: row.remark || ''
                });

                layer.open({
                    type: 1,
                    title: row.id ? CrmLang.t('admin.edit_blacklist') : CrmLang.t('admin.create_blacklist'),
                    area: ['620px', '520px'],
                    content: $('#blacklistModal')
                });
                form.render();
            }

            /**
             * 重新应用按钮权限：表格重载后操作列会重新生成，需要按 permissions.slug 隐藏无权限按钮。
             *
             * @returns {void}
             */
            function refreshPermissions() {
                if (window.CrmAdminPermissions && window.CrmAdminPermissions.refresh) {
                    window.CrmAdminPermissions.refresh();
                }
            }
        });
    });

    registry['cancel-applies/index'] = once(function () {
        // Source: cancel-applies/index.js
        layui.use(['table', 'form', 'layer', 'jquery'], function() {
            var table = layui.table, form = layui.form, layer = layui.layer, $ = layui.jquery;
            var successCodes = {1000: true, 1001: true, 1002: true};
            var reviewRequestPending = false;

            CrmLang.switchUI();

            // 注销申请列表读取 /api/admin/cancelApplyList。
            // 审核通过和审核拒绝分别调用 /api/admin/cancelApplyApprove/{id}、/api/admin/cancelApplyReject/{id}。
            // status 表示注销申请状态：0=待处理，1=通过，-1=拒绝。
            // id 表示注销申请主键；approve 表示通过注销申请，reject 表示拒绝注销申请。
            // 表格重载后重新应用按钮权限，权限来源为 permissions.slug。

            function refreshPermissions() {
                // 重新应用按钮权限：Layui 表格重载后会重新生成操作列按钮，必须再次按 permissions.slug 隐藏无权限按钮。
                if (window.CrmAdminPermissions && window.CrmAdminPermissions.refresh) {
                    window.CrmAdminPermissions.refresh();
                }
            }

            function escapeCancelApplyText(value) {
                return $('<div>').text(value === undefined || value === null ? '' : String(value)).html();
            }

            function cancelApplyStatusBadge(row) {
                var status = Number(row.status);
                var statusClass = status === 1 ? 'is-approved' : (status === -1 ? 'is-rejected' : 'is-pending');
                var statusText = status === 1
                    ? CrmLang.t('admin.approved')
                    : (status === -1 ? CrmLang.t('admin.rejected') : CrmLang.t('admin.pending'));

                return '<span class="cancel-apply-status-badge ' + statusClass + '">' + escapeCancelApplyText(statusText) + '</span>';
            }

            function cancelApplyBalance(row) {
                var balance = String(row.balance === undefined || row.balance === null ? '0.00' : row.balance);
                var negativeClass = Number(balance) < 0 ? ' is-negative' : '';

                return '<span class="cancel-apply-balance' + negativeClass + '">' + escapeCancelApplyText(balance) + '</span>';
            }

            // 注销申请列表：数据来自 /api/admin/cancelApplyList，读取 cancel_applies 表中的客户注销申请。
            // status 表示注销申请状态：0=待处理，1=通过，-1=拒绝；空字符串表示不限制状态。
            table.render(CrmTable.layuiConfig('admin', {
                elem: '#cancelApplyTable',
                id: 'cancelApplyTable',
                url: '/api/admin/cancelApplyList',
                where: {status: 0},
                cols: [[
                    {field: 'user_id', title: CrmLang.t('admin.userId'), width: 120},
                    {field: 'user_name', title: CrmLang.t('admin.user_name'), minWidth: 150},
                    {field: 'balance', title: CrmLang.t('admin.balance'), width: 130, align: 'right', templet: cancelApplyBalance},
                    {field: 'open_positions', title: CrmLang.t('admin.open_positions'), width: 120, align: 'center'},
                    {field: 'cancel_remark', title: CrmLang.t('admin.application_reason'), minWidth: 220},
                    {field: 'reject_reason', title: CrmLang.t('admin.review_remark'), minWidth: 220},
                    {field: 'status', title: CrmLang.t('admin.status'), width: 120, templet: cancelApplyStatusBadge},
                    {field: 'created_at', title: CrmLang.t('user.createdAt'), width: 180},
                    {fixed: 'right', title: CrmLang.t('common.action'), toolbar: '#cancelApplyActions', width: 170}
                ]],
                parseData: CrmTable.layuiParseData(),
                done: function() {
                    CrmLang.switchUI();
                    refreshPermissions();
                }
            }));

            form.on('submit(searchCancelApplies)', function(data) {
                table.reload('cancelApplyTable', {where: data.field, page: {curr: 1}});
                return false;
            });

            $('#resetCancelApplySearch').on('click', function() {
                var searchForm = document.getElementById('cancelApplySearchForm');

                searchForm.reset();
                $(searchForm).find('[name="status"]').val('0');
                form.render('select');
                table.reload('cancelApplyTable', {
                    where: {user_id: '', status: 0, start_date: '', end_date: ''},
                    page: {curr: 1}
                });
            });

            function openCancelApplyReview(apiUrl, row, actionText) {
                if (reviewRequestPending || Number(row.status) !== 0) {
                    return;
                }

                layer.prompt({
                    formType: 2,
                    title: actionText + ' - ' + CrmLang.t('admin.review_remark'),
                    success: function(layerNode) {
                        layerNode.find('textarea')
                            .attr('maxlength', 500)
                            .attr('aria-label', CrmLang.t('admin.review_remark'))
                            .trigger('focus');
                    }
                }, function(value, index) {
                    var reviewRemark = String(value || '').trim();
                    var $promptLayer = $('#layui-layer' + index);
                    var $promptButton = $promptLayer.find('.layui-layer-btn0');

                    if (!reviewRemark) {
                        layer.msg(CrmLang.t('response.validation_failed'), {icon: 2});
                        return;
                    }
                    if (reviewRequestPending) {
                        return;
                    }

                    reviewRequestPending = true;
                    $promptButton.addClass('layui-btn-disabled').attr('aria-busy', 'true');
                    CrmAjax.request({
                        guard: 'admin',
                        url: apiUrl + '/' + encodeURIComponent(row.id),
                        data: {reason: reviewRemark},
                        success: function(res) {
                            if (successCodes[res.code]) {
                                layer.close(index);
                                table.reload('cancelApplyTable');
                                layer.msg(res.message || CrmLang.t('common.success'), {icon: 1});
                                return;
                            }
                            layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                        },
                        error: function(res) {
                            layer.msg((res && res.message) || CrmLang.t('common.error'), {icon: 2});
                        },
                        complete: function() {
                            reviewRequestPending = false;
                            $promptButton.removeClass('layui-btn-disabled').removeAttr('aria-busy');
                        }
                    });
                });
            }

            table.on('tool(cancelApplyTable)', function(obj) {
                // approve 表示通过注销申请，调用 /api/admin/cancelApplyApprove/{id}；reject 表示拒绝注销申请，调用 /api/admin/cancelApplyReject/{id}。
                if (obj.event === 'approve') openCancelApplyReview('/api/admin/cancelApplyApprove', obj.data, CrmLang.t('admin.approve'));
                if (obj.event === 'reject') openCancelApplyReview('/api/admin/cancelApplyReject', obj.data, CrmLang.t('admin.reject'));
            });
        });
    });

    registry['channels/index'] = once(function () {
        // Source: channels/index.js
        layui.use(['table', 'form', 'layer', 'element', 'jquery'], function() {
            var table = layui.table;
            var form = layui.form;
            var layer = layui.layer;
            var element = layui.element;
            var $ = layui.jquery;
            var successCodes = {1000: true, 1001: true, 1002: true, 1003: true};

            CrmLang.switchUI();

            // status 表示支付通道启用状态筛选；空值表示全部状态，真实列表数据仍以后端接口返回为准。
            table.render(CrmTable.layuiConfig('admin', {
                elem: '#channelTable',
                id: 'channelTable',
                url: '/api/admin/channels',
                // summaryElem：把当前页统计渲染进表格卡片之外的独立统计区块（需求 9）。
                summaryElem: '#channelStatistics',
                cols: [[
                    {field: 'id', title: 'ID', width: 90, sort: true},
                    {field: 'name', title: CrmLang.t('admin.name'), width: 180},
                    {field: 'channel_code', title: CrmLang.t('admin.code'), width: 180},
                    {field: 'exchange_rate', title: CrmLang.t('admin.exchange_rate'), width: 140},
                    {field: 'is_enabled', title: CrmLang.t('admin.status'), width: 120, templet: function(row) {
                        return Number(row.is_enabled) === 1 ? CrmLang.t('admin.enabled') : CrmLang.t('admin.disabled');
                    }},
                    {field: 'sort', title: CrmLang.t('admin.sort'), width: 100},
                    {field: 'updated_at', title: CrmLang.t('admin.updatedAt'), width: 180},
                    {fixed: 'right', title: CrmLang.t('common.action'), toolbar: '#channelActions', width: 210}
                ]],
                parseData: CrmTable.layuiParseData(),
                done: function() {
                    CrmLang.switchUI();
                    refreshPermissions();
                }
            }));

            document.getElementById('reloadChannels').onclick = function() {
                table.reload('channelTable', {where: currentChannelFilters()});
            };

            $('#addChannel').on('click', function() {
                openChannelModal({
                    id: '',
                    name: '',
                    channel_code: '',
                    exchange_rate: 1,
                    is_enabled: 1,
                    sort: 0,
                    config: ''
                });
            });

            table.on('tool(channelTable)', function(obj) {
                if (obj.event === 'edit') {
                    openChannelModal(obj.data);
                    return;
                }

                if (obj.event === 'delete') {
                    layer.confirm(CrmLang.t('common.confirm_delete'), function(index) {
                        CrmAjax.request({
                            guard: 'admin',
                            url: '/api/admin/deleteChannel/' + encodeURIComponent(obj.data.id),
                            data: {id: obj.data.id},
                            success: function(res) {
                                if (successCodes[res.code]) {
                                    obj.del();
                                    layer.close(index);
                                    layer.msg(res.message || CrmLang.t('common.success'), {icon: 1});
                                    return;
                                }
                                layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                            }
                        });
                    });
                }

                if (obj.event === 'toggle') {
                    layer.confirm(CrmLang.t('common.confirm_status_change') || CrmLang.t('common.confirm'), function(index) {
                        CrmAjax.request({
                            guard: 'admin',
                            url: '/api/admin/toggleChannel/' + encodeURIComponent(obj.data.id),
                            data: {id: obj.data.id},
                            success: function(res) {
                                if (successCodes[res.code]) {
                                    layer.close(index);
                                    table.reload('channelTable');
                                    layer.msg(res.message || CrmLang.t('common.success'), {icon: 1});
                                    return;
                                }
                                layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                            }
                        });
                    });
                }
            });

            form.on('submit(searchChannels)', function(data) {
                table.reload('channelTable', {where: currentChannelFilters(), page: {curr: 1}});
                return false;
            });

            // 需求 8：页签切换只收窄 status 筛选条件，其余 CRUD 行为完全不变。
            element.on('tab(channelTabs)', function() {
                var status = $(this).data('channel-status');

                $('#channelSearchForm [data-channel-status-input]').val(
                    status === undefined || status === null ? '' : String(status)
                );
                table.reload('channelTable', {where: currentChannelFilters(), page: {curr: 1}});
            });

            form.on('submit(saveChannel)', function(data) {
                var id = data.field.id;
                var apiUrl = id ? '/api/admin/updateChannel/' + encodeURIComponent(id) : '/api/admin/createChannel';

                CrmAjax.request({
                    guard: 'admin',
                    url: apiUrl,
                    data: data.field,
                    success: function(res) {
                        if (successCodes[res.code]) {
                            layer.closeAll();
                            table.reload('channelTable');
                            layer.msg(res.message || CrmLang.t('common.success'), {icon: 1});
                            return;
                        }
                        layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                    }
                });

                return false;
            });

            /**
             * 打开支付通道表单弹窗。
             *
             * 参数含义：
             * - row：支付通道行数据，来自表格行或新增按钮构造的空对象。
             * - id 为空表示新增支付通道；有值表示更新指定通道。
             * - channel_code 表示支付通道编码，用于后端识别具体支付网关或渠道实现。
             * - exchange_rate 表示该通道使用的汇率，入金/出金展示和换算时会读取该配置。
             * - is_enabled 表示通道是否启用，1=启用，0=禁用。
             * - config 表示通道扩展配置，可保存网关参数、商户号、回调配置等 JSON 文本。
             *
             * @param {Object} row 支付通道行数据或新增表单默认值。
             * @returns {void}
             */
            function openChannelModal(row) {
                form.val('channelForm', {
                    id: row.id || '',
                    name: row.name || row.channel_name || '',
                    channel_code: row.channel_code || '',
                    exchange_rate: row.exchange_rate || 1,
                    is_enabled: String(row.is_enabled !== undefined ? row.is_enabled : 1),
                    sort: row.sort || 0,
                    config: normalizeConfig(row.config)
                });

                layer.open({
                    type: 1,
                    title: row.id ? CrmLang.t('admin.edit_channel') : CrmLang.t('admin.create_channel'),
                    area: ['620px', '620px'],
                    content: $('#channelModal')
                });
                form.render();
            }

            /**
             * 读取支付通道当前筛选条件。
             *
             * 逻辑说明：
             * - status 由当前激活页签写入隐藏域，name/channel_code 来自搜索框。
             * - 页签、搜索和刷新共用同一份取值逻辑，避免三处各自拼参数导致条件不一致。
             *
             * @returns {Object} 提交给 /api/admin/channels 的筛选参数。
             */
            function currentChannelFilters() {
                return serializeForm($, '#channelSearchForm');
            }

            /**
             * normalizeConfig 将通道扩展配置转换为 textarea 文本。
             *
             * 参数含义：
             * - config：通道扩展配置，可为空、JSON 字符串或后端已解码的对象。
             * - 返回值：可直接放入 textarea 的配置文本，避免对象直接显示为 [object Object]。
             *
             * @param {string|Object|null} config 通道扩展配置。
             * @returns {string} 可放入 textarea 的配置文本。
             */
            function normalizeConfig(config) {
                if (!config) {
                    return '';
                }

                if (typeof config === 'string') {
                    return config;
                }

                return JSON.stringify(config, null, 2);
            }

            /**
             * 重新应用按钮权限。
             *
             * 逻辑说明：
             * - Layui 表格操作列会在重载后重新渲染 DOM，必须再次调用 CrmAdminPermissions.refresh()。
             * - refresh() 根据按钮上的 data-permission 与当前管理员拥有的 permissions.slug 做显隐匹配。
             * - 这里只负责前端体验控制；新增、更新、删除接口仍由后端 check.permission:admin 做二次鉴权。
             *
             * @returns {void}
             */
            function refreshPermissions() {
                if (window.CrmAdminPermissions && window.CrmAdminPermissions.refresh) {
                    window.CrmAdminPermissions.refresh();
                }
            }
        });
    });

    registry['commissions/index'] = once(function () {
        // Source: commissions/index.js
        layui.use(['table', 'form', 'layer'], function() {
            var table = layui.table, form = layui.form, layer = layui.layer;
            var commissionTransferReconciliationPermissions = {
                list: 'admin_commission_transfer_reconciliation_list',
                detail: 'admin_commission_transfer_reconciliation_detail',
                reconcile: 'admin_commission_transfer_reconcile'
            };
            var commissionTransferReconciliationEvidenceFields = [
                'decision', 'external_reference',
                'withdraw_status', 'withdraw_reference',
                'deposit_status', 'deposit_reference',
                'compensation_status', 'compensation_reference',
                'source_balance_after', 'target_balance_after'
            ];

            CrmLang.switchUI();

            // 返佣结算列表读取 /api/admin/commissions。
            // 结算操作调用 /api/admin/commissions/{id}/settle，后端继续通过 AdminDataScopeService 限制数据范围。
            // commission_records 中 agent_id 表示返佣归属代理，user_id 表示产生返佣的客户，amount 表示返佣金额。
            // settle_status 表示结算状态：1=待结算，2=已结算；id 表示返佣记录主键，settle 表示结算返佣记录。
            // 表格重载后重新应用按钮权限，权限来源为 permissions.slug。

            function refreshPermissions() {
                // 重新应用按钮权限：Layui 表格重载后会重新生成操作列按钮，必须再次按 permissions.slug 隐藏无权限按钮。
                if (window.CrmAdminPermissions && window.CrmAdminPermissions.refresh) {
                    window.CrmAdminPermissions.refresh();
                }
            }

            // 返佣结算列表：数据来自 /api/admin/commissions，读取 commission_records 并由 AdminDataScopeService 按代理范围裁剪。
            // agent_id 表示返佣归属代理；settle_status 表示结算状态：1=待结算，2=已结算。
            table.render(CrmTable.layuiConfig('admin', {
                elem: '#commissionTable',
                url: '/api/admin/commissions',
                cols: [[
                    {field: 'id', title: 'ID', width: 90, sort: true},
                    {field: 'agent_id', title: CrmLang.t('admin.agentId'), width: 120},
                    // user_id 表示产生返佣的客户，部分历史记录可能为空，真实归属仍以 agent_id 为准。
                    {field: 'user_id', title: CrmLang.t('admin.userId'), width: 120},
                    // amount 表示返佣金额，后端模型可能来自 commission_amount 或兼容字段映射。
                    {field: 'amount', title: CrmLang.t('admin.amount'), width: 140},
                    {field: 'settle_status', title: CrmLang.t('admin.status'), width: 120},
                    {fixed: 'right', title: CrmLang.t('common.action'), toolbar: '#commissionActions', width: 110}
                ]],
                parseData: CrmTable.layuiParseData(),
                done: function() {
                    CrmLang.switchUI();
                    refreshPermissions();
                }
            }));

            form.on('submit(searchCommissions)', function(data) {
                table.reload('commissionTable', {where: data.field, page: {curr: 1}});
                return false;
            });

            table.on('tool(commissionTable)', function(obj) {
                if (obj.event !== 'settle') return;
                // settle 表示结算返佣记录；id 表示返佣记录主键，/api/admin/commissionSettle/{id} 会拒绝已结算或越权记录。
                CrmAjax.request({
                    guard: 'admin',
                    url: '/api/admin/commissionSettle',
                    data: {id: obj.data.id},
                    success: function(res) {
                        if (res.code === 1000 || res.code === 1002) {
                            table.reload('commissionTable');
                            layer.msg(CrmLang.t('common.success'), {icon: 1});
                            return;
                        }
                        layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                    }
                });
            });

            // Transfer reconciliation cases only contain externally uncertain transfers.
            // The detail request is read-only; the decision request is the only mutation.
            table.render(CrmTable.layuiConfig('admin', {
                elem: '#commissionTransferReconciliationTable',
                id: 'commissionTransferReconciliationTable',
                url: '/api/admin/commission-transfers/reconciliation-cases',
                method: 'POST',
                cols: [[
                    {field: 'id', title: 'ID', width: 90, sort: true},
                    {field: 'local_order_no', title: 'Order', width: 190},
                    {field: 'source_user_id', title: 'Source user', width: 125},
                    {field: 'target_user_id', title: 'Target user', width: 125},
                    {field: 'amount', title: CrmLang.t('admin.amount'), width: 125},
                    {field: 'status', title: CrmLang.t('admin.status'), width: 180},
                    {field: 'last_error_code', title: 'Error code', width: 180},
                    {fixed: 'right', title: CrmLang.t('common.action'), toolbar: '#commissionTransferReconciliationActions', width: 180}
                ]],
                parseData: CrmTable.layuiParseData(),
                done: function() {
                    CrmLang.switchUI();
                    refreshPermissions();
                }
            }));

            function showReconciliationDetail(row) {
                if (window.CrmAdminPermissions
                    && window.CrmAdminPermissions.can
                    && !window.CrmAdminPermissions.can(commissionTransferReconciliationPermissions.detail)) {
                    return;
                }
                CrmAjax.request({
                    guard: 'admin',
                    method: 'GET',
                    url: '/api/admin/commission-transfers/reconciliation-cases/' + encodeURIComponent(row.id),
                    data: {},
                    success: function(res) {
                        if (!successCodes[res.code]) {
                            layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                            return;
                        }
                        var pre = document.createElement('pre');
                        pre.style.padding = '16px';
                        pre.style.maxHeight = '560px';
                        pre.style.overflow = 'auto';
                        pre.textContent = JSON.stringify(res.data || {}, null, 2);
                        layer.open({type: 1, title: CrmLang.t('common.detail'), area: ['720px', '640px'], content: pre.outerHTML});
                    }
                });
            }

            function openReconciliationForm(row) {
                if (window.CrmAdminPermissions
                    && window.CrmAdminPermissions.can
                    && !window.CrmAdminPermissions.can(commissionTransferReconciliationPermissions.reconcile)) {
                    return;
                }
                form.val('commissionTransferReconciliationForm', {
                    transfer_id: row.id,
                    decision: '',
                    external_reference: '',
                    withdraw_status: 'confirmed_not_processed',
                    withdraw_reference: '',
                    deposit_status: 'confirmed_not_processed',
                    deposit_reference: '',
                    compensation_status: 'confirmed_not_processed',
                    compensation_reference: '',
                    source_balance_after: '',
                    target_balance_after: ''
                });
                layer.open({
                    type: 1,
                    title: CrmLang.t('admin.review_status'),
                    area: ['560px', '360px'],
                    content: $('#commissionTransferReconciliationModal')
                });
                form.render();
            }

            table.on('tool(commissionTransferReconciliationTable)', function(obj) {
                if (obj.event === 'detail') {
                    showReconciliationDetail(obj.data);
                    return;
                }
                if (obj.event === 'reconcile') {
                    openReconciliationForm(obj.data);
                }
            });

            form.on('submit(saveCommissionTransferReconciliation)', function(data) {
                var transferId = data.field.transfer_id;
                var evidence = {};
                commissionTransferReconciliationEvidenceFields.forEach(function(field) {
                    if (field !== 'decision' && field !== 'external_reference') {
                        evidence[field] = data.field[field];
                    }
                });
                CrmAjax.request({
                    guard: 'admin',
                    url: '/api/admin/commission-transfers/reconciliation-cases/' + encodeURIComponent(transferId) + '/decisions',
                    data: {
                        decision: data.field.decision,
                        external_reference: data.field.external_reference,
                        withdraw_status: evidence.withdraw_status,
                        withdraw_reference: evidence.withdraw_reference,
                        deposit_status: evidence.deposit_status,
                        deposit_reference: evidence.deposit_reference,
                        compensation_status: evidence.compensation_status,
                        compensation_reference: evidence.compensation_reference,
                        source_balance_after: evidence.source_balance_after,
                        target_balance_after: evidence.target_balance_after
                    },
                    success: function(res) {
                        if (successCodes[res.code]) {
                            layer.closeAll();
                            table.reload('commissionTransferReconciliationTable');
                            layer.msg(res.message || CrmLang.t('common.success'), {icon: 1});
                            return;
                        }
                        layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                    }
                });
                return false;
            });
        });
    });

    registry['credit-imports/index'] = once(function () {
        // Source: credit-imports/index.js
        layui.use(['table', 'form', 'layer', 'jquery'], function() {
            var table = layui.table;
            var form = layui.form;
            var layer = layui.layer;
            var $ = layui.jquery;
            var successCodes = {1000: true, 1001: true, 1002: true};

            CrmLang.switchUI();

            // user_id：业务用户 ID；batch_no：导入批次号；credit_type：信用类型；is_synced：同步状态。
            table.render(CrmTable.layuiConfig('admin', {
                elem: '#creditImportTable',
                id: 'creditImportTable',
                url: '/api/admin/creditImportList',
                cols: [[
                    {field: 'id', title: 'ID', width: 90, sort: true},
                    {field: 'user_id', title: CrmLang.t('admin.userId'), width: 120},
                    {field: 'user_name', title: CrmLang.t('admin.user_name'), width: 160},
                    {field: 'credit_type', title: CrmLang.t('admin.credit_type'), width: 150, templet: creditTypeText},
                    {field: 'amount', title: CrmLang.t('admin.amount'), width: 130},
                    {field: 'batch_no', title: CrmLang.t('admin.batch_no'), width: 180},
                    {field: 'mt4_order_id', title: CrmLang.t('admin.mt4_order_id'), width: 140},
                    {field: 'is_synced', title: CrmLang.t('admin.sync_status'), width: 130, templet: syncStatusText},
                    {field: 'fail_reason', title: CrmLang.t('admin.fail_reason'), width: 220},
                    {field: 'created_at', title: CrmLang.t('user.createdAt'), width: 180},
                    {fixed: 'right', title: CrmLang.t('common.action'), toolbar: '#creditImportActions', width: 190}
                ]],
                parseData: CrmTable.layuiParseData(),
                done: function() {
                    CrmLang.switchUI();
                    refreshPermissions();
                }
            }));

            document.getElementById('reloadCreditImports').onclick = function() {
                table.reload('creditImportTable');
            };

            $('#downloadCreditImportTemplate').on('click', function() {
                downloadAdminCsv($, layer, '/api/admin/creditImportTemplate', {}, 'credit_import_template.csv');
            });

            $('#exportCreditImports').on('click', function() {
                downloadAdminCsv($, layer, '/api/admin/exportCreditImports', serializeForm($, '#creditImportSearchForm'), 'credit_imports_export.csv');
            });

            $('#addCreditImport').on('click', function() {
                form.val('creditImportForm', {
                    user_id: '',
                    user_name: '',
                    credit_type: '2',
                    amount: '',
                    batch_no: buildDefaultBatchNo(),
                    mt4_order_id: 0,
                    remarks: ''
                });

                layer.open({
                    type: 1,
                    title: CrmLang.t('admin.create_credit_import'),
                    area: ['620px', '620px'],
                    content: $('#creditImportModal')
                });
                form.render();
            });

            // CSV 批量导入弹窗：共享上传组件（deferred 模式缓存文件），提交走 CrmAjax.upload 携带管理员令牌。
            $('#importCreditImportFile').on('click', function() {
                layer.open({
                    type: 1,
                    title: CrmLang.t('admin.import_csv_file'),
                    area: ['560px', '420px'],
                    content: $('#creditImportUploadModal')
                });
                if (window.CrmUpload) { CrmUpload.init(document); }
                if (window.lucide && lucide.createIcons) { lucide.createIcons(); }
            });

            $('#submitCreditImportFile').on('click', function() {
                var file = window.CrmUpload ? CrmUpload.file('credit_import_csv') : null;
                if (!file) {
                    layer.msg(CrmLang.t('front.no_file_selected'), {icon: 0});
                    return;
                }
                var formData = new FormData();
                formData.append('file', file);
                CrmAjax.upload({
                    guard: 'admin',
                    url: '/api/admin/createCreditImport',
                    formData: formData,
                    success: function(res) {
                        if (successCodes[res.code]) {
                            var count = Array.isArray(res.data) ? res.data.length : 0;
                            layer.closeAll();
                            if (window.CrmUpload) { CrmUpload.reset('credit_import_csv', false); }
                            table.reload('creditImportTable');
                            layer.msg((CrmLang.t('admin.import_csv_result') || '').replace(':count', count), {icon: 1});
                            return;
                        }
                        layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                    },
                    error: function(res) {
                        layer.msg((res && res.message) || CrmLang.t('common.error'), {icon: 2});
                    }
                });
            });

            form.on('submit(searchCreditImports)', function(data) {
                table.reload('creditImportTable', {where: data.field, page: {curr: 1}});
                return false;
            });

            table.on('tool(creditImportTable)', function(obj) {
                if (obj.event === 'syncCreditImport') {
                    requestCreditImportAction(
                        obj,
                        '/api/admin/syncCreditImport/',
                        'admin.confirm_sync_import'
                    );
                    return;
                }

                if (obj.event !== 'retryCreditImport') {
                    return;
                }

                // obj.data.id：credit_imports 主键；后端会再次校验记录必须是失败状态且在管理员数据范围内。
                requestCreditImportAction(
                    obj,
                    '/api/admin/retryCreditImport/',
                    'admin.confirm_retry_import'
                );
            });

            /**
             * 执行单条信用导入记录动作，并在响应后刷新同步状态和失败原因。
             *
             * @param {Object} obj Layui 表格行事件对象。
             * @param {string} endpoint API 前缀，最后会拼接 credit_imports.id。
             * @param {string} confirmKey 确认弹窗多语言 key。
             * @returns {void}
             */
            function requestCreditImportAction(obj, endpoint, confirmKey) {
                layer.confirm(CrmLang.t(confirmKey), function(index) {
                    CrmAjax.request({
                        guard: 'admin',
                        url: endpoint + encodeURIComponent(obj.data.id),
                        data: {},
                        success: function(res) {
                            layer.close(index);
                            table.reload('creditImportTable');
                            if (successCodes[res.code]) {
                                layer.msg(res.message || CrmLang.t('common.success'), {icon: 1});
                                return;
                            }
                            layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                        }
                    });
                });
            }

            form.on('submit(saveCreditImport)', function(data) {
                CrmAjax.request({
                    guard: 'admin',
                    url: '/api/admin/createCreditImport',
                    data: data.field,
                    success: function(res) {
                        if (successCodes[res.code]) {
                            layer.closeAll();
                            table.reload('creditImportTable');
                            layer.msg(res.message || CrmLang.t('common.success'), {icon: 1});
                            return;
                        }
                        layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                    }
                });

                return false;
            });

            /**
             * 将信用类型枚举转换为页面文案。
             *
             * @param {Object} row 信用导入记录行；credit_type 为 1/2/3/4。
             * @returns {string} 信用类型文案。
             */
            function creditTypeText(row) {
                var value = Number(row.credit_type);
                if (value === 1) return CrmLang.t('admin.credit_type_temp');
                if (value === 3) return CrmLang.t('admin.credit_type_reward');
                if (value === 4) return CrmLang.t('admin.credit_type_other');
                return CrmLang.t('admin.credit_type_permanent');
            }

            /**
             * 将同步状态枚举转换为页面文案。
             *
             * @param {Object} row 信用导入记录行；is_synced 为 0/1/2。
             * @returns {string} 同步状态文案。
             */
            function syncStatusText(row) {
                var value = Number(row.is_synced);
                if (value === 1) return CrmLang.t('admin.import_synced');
                if (value === 2) return CrmLang.t('admin.import_failed');
                return CrmLang.t('admin.import_pending');
            }

            /**
             * 生成默认批次号，便于手工新增记录按批次追踪。
             *
             * @returns {string} 默认批次号，格式为 CRD-时间戳。
             */
            function buildDefaultBatchNo() {
                return 'CRD-' + Date.now();
            }

            /**
             * 表格刷新后重新执行按钮权限显示控制。
             *
             * @returns {void}
             */
            function refreshPermissions() {
                if (window.CrmAdminPermissions && window.CrmAdminPermissions.refresh) {
                    window.CrmAdminPermissions.refresh();
                }
            }
        });
    });

    registry['dashboard/index'] = once(function () {
        // Source: dashboard/index.js
        layui.use(['layer'], function() {
            var layer = layui.layer, $ = layui.jquery;

            function loadDashboardData() {
                CrmAjax.request({
                    guard: 'admin',
                    url: '/api/admin/dashboard',
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
    });

    registry['data-scopes/index'] = once(function () {
        // Source: data-scopes/index.js
        layui.use(['table', 'form', 'layer', 'jquery'], function () {
            var table = layui.table;
            var form = layui.form;
            var layer = layui.layer;
            var $ = layui.jquery;
            var text = {
                // 表格列名、状态徽标和弹窗标题都从运行时语言包读取，避免中文界面出现硬编码英文。
                id: CrmLang.t('common.id'),
                roleName: CrmLang.t('admin.role_data_scope_role_name'),
                scopeType: CrmLang.t('admin.scope_type'),
                agentIds: CrmLang.t('admin.agent_ids'),
                userIds: CrmLang.t('admin.user_ids'),
                status: CrmLang.t('common.status'),
                action: CrmLang.t('common.action'),
                enabled: CrmLang.t('admin.enabled'),
                disabled: CrmLang.t('admin.disabled'),
                adminId: CrmLang.t('admin.admin_id'),
                adminName: CrmLang.t('admin.admin_name'),
                agentId: CrmLang.t('admin.agent_id'),
                agentName: CrmLang.t('admin.agent_name'),
                bindingType: CrmLang.t('admin.binding_type'),
                primary: CrmLang.t('admin.binding_primary'),
                extra: CrmLang.t('admin.binding_extra'),
                saveSuccess: CrmLang.t('admin.data_scope_saved'),
                deleteConfirm: CrmLang.t('admin.admin_agent_binding_delete_confirm'),
                deleteSuccess: CrmLang.t('admin.admin_agent_binding_deleted'),
                roleScopeTitle: CrmLang.t('admin.role_data_scope_modal_title'),
                bindingTitle: CrmLang.t('admin.admin_agent_binding_modal_title')
            };

            var successCodes = {1000: true, 1001: true, 1002: true, 1003: true};

            CrmLang.switchUI();

            function refreshPermissions() {
                // Layui 表格重载会重新生成操作列按钮，必须重新按 permissions.slug 隐藏无权限按钮。
                if (window.CrmAdminPermissions && window.CrmAdminPermissions.refresh) {
                    window.CrmAdminPermissions.refresh();
                }
            }

            table.render(CrmTable.layuiConfig('admin', {
                elem: '#roleScopeTable',
                id: 'roleScopeTable',
                url: '/api/admin/roleDataScopeList',
                height: 520,
                cols: [[
                    {field: 'id', title: text.id, width: 76, sort: true},
                    {field: 'name', title: text.roleName, minWidth: 150},
                    {field: 'scope_type', title: text.scopeType, width: 140, templet: function (row) {
                        return scopeLabel(row.data_scope && row.data_scope.scope_type ? row.data_scope.scope_type : 'self');
                    }},
                    {field: 'agent_ids', title: text.agentIds, minWidth: 160, templet: function (row) {
                        return listText(row.data_scope && row.data_scope.agent_ids);
                    }},
                    {field: 'user_ids', title: text.userIds, minWidth: 160, templet: function (row) {
                        return listText(row.data_scope && row.data_scope.user_ids);
                    }},
                    {field: 'status', title: text.status, width: 90, templet: function (row) {
                        return statusBadge(row.data_scope ? row.data_scope.status : 1);
                    }},
                    {fixed: 'right', title: text.action, toolbar: '#roleScopeActions', width: 96}
                ]],
                parseData: CrmTable.layuiParseData(),
                done: function() {
                    CrmLang.switchUI();
                    refreshPermissions();
                }
            }));

            table.render(CrmTable.layuiConfig('admin', {
                elem: '#adminAgentBindingTable',
                id: 'adminAgentBindingTable',
                url: '/api/admin/adminAgentBindingList',
                height: 520,
                cols: [[
                    {field: 'id', title: text.id, width: 76, sort: true},
                    {field: 'admin_id', title: text.adminId, width: 110},
                    {field: 'admin', title: text.adminName, minWidth: 140, templet: function (row) {
                        return escapeText(row.admin && (row.admin.username || row.admin.name) ? (row.admin.username || row.admin.name) : '-');
                    }},
                    {field: 'agent_id', title: text.agentId, width: 110},
                    {field: 'agent', title: text.agentName, minWidth: 140, templet: function (row) {
                        return escapeText(row.agent && row.agent.user_name ? row.agent.user_name : '-');
                    }},
                    {field: 'binding_type', title: text.bindingType, width: 120, templet: function (row) {
                        return row.binding_type === 'extra' ? text.extra : text.primary;
                    }},
                    {field: 'status', title: text.status, width: 90, templet: function (row) {
                        return statusBadge(row.status);
                    }},
                    {fixed: 'right', title: text.action, toolbar: '#adminAgentBindingActions', width: 130}
                ]],
                parseData: CrmTable.layuiParseData(),
                done: function() {
                    CrmLang.switchUI();
                    refreshPermissions();
                }
            }));

            $('#reloadRoleScopes').on('click', function () {
                table.reload('roleScopeTable');
            });

            $('#addAdminAgentBinding').on('click', function () {
                openBindingModal({
                    admin_id: '',
                    agent_id: '',
                    binding_type: 'primary',
                    status: 1
                });
            });

            table.on('tool(roleScopeTable)', function (obj) {
                if (obj.event === 'edit') {
                    openRoleScopeModal(obj.data);
                }
            });

            table.on('tool(adminAgentBindingTable)', function (obj) {
                if (obj.event === 'edit') {
                    openBindingModal(obj.data);
                    return;
                }

                if (obj.event === 'delete') {
                    layer.confirm(text.deleteConfirm, function (index) {
                        CrmAjax.request({
                            guard: 'admin',
                            url: '/api/admin/deleteAdminAgentBinding',
                            data: {id: obj.data.id},
                            success: function (res) {
                                if (successCodes[res.code]) {
                                    obj.del();
                                    layer.close(index);
                                    layer.msg(res.message || text.deleteSuccess, {icon: 1});
                                    return;
                                }
                                layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                            }
                        });
                    });
                }
            });

            form.on('submit(saveRoleScope)', function (event) {
                var data = event.field;

                data.status = data.status ? 1 : 0;
                CrmAjax.request({
                    guard: 'admin',
                    url: '/api/admin/saveRoleDataScope',
                    data: data,
                    success: function (res) {
                        if (successCodes[res.code]) {
                            layer.closeAll();
                            table.reload('roleScopeTable');
                            layer.msg(res.message || text.saveSuccess, {icon: 1});
                            return;
                        }
                        layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                    }
                });

                return false;
            });

            form.on('submit(saveAdminAgentBinding)', function (event) {
                var data = event.field;

                data.status = data.status ? 1 : 0;
                CrmAjax.request({
                    guard: 'admin',
                    url: '/api/admin/saveAdminAgentBinding',
                    data: data,
                    success: function (res) {
                        if (successCodes[res.code]) {
                            layer.closeAll();
                            table.reload('adminAgentBindingTable');
                            layer.msg(res.message || text.saveSuccess, {icon: 1});
                            return;
                        }
                        layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                    }
                });

                return false;
            });

            /**
             * 打开角色数据范围弹窗。
             *
             * @param {Object} row 当前角色行；row.data_scope 是后端 role_data_scopes 关联配置。
             */
            function openRoleScopeModal(row) {
                var scope = row.data_scope || {};

                form.val('roleScopeForm', {
                    role_id: row.id,
                    role_name: row.name,
                    scope_type: scope.scope_type || 'self',
                    agent_ids: listText(scope.agent_ids, ''),
                    user_ids: listText(scope.user_ids, ''),
                    status: String(typeof scope.status === 'undefined' ? 1 : scope.status) === '1'
                });
                layer.open({
                    type: 1,
                    title: text.roleScopeTitle,
                    area: ['680px', '560px'],
                    content: $('#roleScopeModal')
                });
                form.render();
            }

            /**
             * 打开管理员代理绑定弹窗。
             *
             * @param {Object} row 绑定行；新增时传入空字段对象。
             */
            function openBindingModal(row) {
                form.val('adminAgentBindingForm', {
                    admin_id: row.admin_id || '',
                    agent_id: row.agent_id || '',
                    binding_type: row.binding_type || 'primary',
                    status: String(typeof row.status === 'undefined' ? 1 : row.status) === '1'
                });
                layer.open({
                    type: 1,
                    title: text.bindingTitle,
                    area: ['560px', '430px'],
                    content: $('#adminAgentBindingModal')
                });
                form.render();
            }
            function scopeLabel(value) {
                var map = {
                    // value 对应 role_data_scopes.scope_type，展示文案必须跟随当前语言切换。
                    all: CrmLang.t('admin.scope_all'),
                    self: CrmLang.t('admin.scope_self'),
                    created: CrmLang.t('admin.scope_created'),
                    agent_tree: CrmLang.t('admin.scope_agent_tree'),
                    custom_agents: CrmLang.t('admin.scope_custom_agents'),
                    custom_users: CrmLang.t('admin.scope_custom_users')
                };

                return map[value] || value || '-';
            }
            function listText(value, emptyText) {
                if (!value) {
                    return typeof emptyText === 'undefined' ? '-' : emptyText;
                }
                if ($.isArray(value)) {
                    return value.length ? value.join(',') : (typeof emptyText === 'undefined' ? '-' : emptyText);
                }
                return String(value);
            }

            function statusBadge(value) {
                var enabled = String(typeof value === 'undefined' ? 1 : value) === '1';
                var cls = enabled ? 'layui-bg-green' : 'layui-bg-gray';

                return '<span class="layui-badge ' + cls + '">' + (enabled ? text.enabled : text.disabled) + '</span>';
            }

            function escapeText(value) {
                return CrmTable.escapeHtml(value || '-');
            }
        });
    });

    registry['deposit-imports/index'] = once(function () {
        // Source: deposit-imports/index.js
        layui.use(['table', 'form', 'layer', 'jquery'], function() {
            var table = layui.table;
            var form = layui.form;
            var layer = layui.layer;
            var $ = layui.jquery;
            var successCodes = {1000: true, 1001: true, 1002: true};

            CrmLang.switchUI();

            // user_id：业务用户 ID；batch_no：导入批次号；is_synced：同步状态，三个参数都会原样传给后端筛选。
            table.render(CrmTable.layuiConfig('admin', {
                elem: '#depositImportTable',
                id: 'depositImportTable',
                url: '/api/admin/depositImportList',
                cols: [[
                    {field: 'id', title: 'ID', width: 90, sort: true},
                    {field: 'user_id', title: CrmLang.t('admin.userId'), width: 120},
                    {field: 'user_name', title: CrmLang.t('admin.user_name'), width: 160},
                    {field: 'amount', title: CrmLang.t('admin.amount'), width: 130},
                    {field: 'batch_no', title: CrmLang.t('admin.batch_no'), width: 180},
                    {field: 'mt4_order_id', title: CrmLang.t('admin.mt4_order_id'), width: 140},
                    {field: 'is_synced', title: CrmLang.t('admin.sync_status'), width: 130, templet: syncStatusText},
                    {field: 'fail_reason', title: CrmLang.t('admin.fail_reason'), width: 220},
                    {field: 'created_at', title: CrmLang.t('user.createdAt'), width: 180},
                    {fixed: 'right', title: CrmLang.t('common.action'), toolbar: '#depositImportActions', width: 190}
                ]],
                parseData: CrmTable.layuiParseData(),
                done: function() {
                    CrmLang.switchUI();
                    refreshPermissions();
                }
            }));

            document.getElementById('reloadDepositImports').onclick = function() {
                table.reload('depositImportTable');
            };

            $('#downloadDepositImportTemplate').on('click', function() {
                downloadAdminCsv($, layer, '/api/admin/depositImportTemplate', {}, 'deposit_import_template.csv');
            });

            $('#exportDepositImports').on('click', function() {
                downloadAdminCsv($, layer, '/api/admin/exportDepositImports', serializeForm($, '#depositImportSearchForm'), 'deposit_imports_export.csv');
            });

            $('#addDepositImport').on('click', function() {
                form.val('depositImportForm', {
                    user_id: '',
                    user_name: '',
                    amount: '',
                    batch_no: buildDefaultBatchNo(),
                    mt4_order_id: 0,
                    remarks: ''
                });

                layer.open({
                    type: 1,
                    title: CrmLang.t('admin.create_deposit_import'),
                    area: ['620px', '560px'],
                    content: $('#depositImportModal')
                });
                form.render();
            });

            // CSV 批量导入弹窗：共享上传组件（deferred 模式缓存文件），提交走 CrmAjax.upload 携带管理员令牌。
            $('#importDepositImportFile').on('click', function() {
                layer.open({
                    type: 1,
                    title: CrmLang.t('admin.import_csv_file'),
                    area: ['560px', '420px'],
                    content: $('#depositImportUploadModal')
                });
                if (window.CrmUpload) { CrmUpload.init(document); }
                if (window.lucide && lucide.createIcons) { lucide.createIcons(); }
            });

            $('#submitDepositImportFile').on('click', function() {
                var file = window.CrmUpload ? CrmUpload.file('deposit_import_csv') : null;
                if (!file) {
                    layer.msg(CrmLang.t('front.no_file_selected'), {icon: 0});
                    return;
                }
                var formData = new FormData();
                formData.append('file', file);
                CrmAjax.upload({
                    guard: 'admin',
                    url: '/api/admin/createDepositImport',
                    formData: formData,
                    success: function(res) {
                        if (successCodes[res.code]) {
                            var count = Array.isArray(res.data) ? res.data.length : 0;
                            layer.closeAll();
                            if (window.CrmUpload) { CrmUpload.reset('deposit_import_csv', false); }
                            table.reload('depositImportTable');
                            layer.msg((CrmLang.t('admin.import_csv_result') || '').replace(':count', count), {icon: 1});
                            return;
                        }
                        layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                    },
                    error: function(res) {
                        layer.msg((res && res.message) || CrmLang.t('common.error'), {icon: 2});
                    }
                });
            });

            form.on('submit(searchDepositImports)', function(data) {
                table.reload('depositImportTable', {where: data.field, page: {curr: 1}});
                return false;
            });

            table.on('tool(depositImportTable)', function(obj) {
                if (obj.event === 'syncDepositImport') {
                    requestDepositImportAction(
                        obj,
                        '/api/admin/syncDepositImport/',
                        'admin.confirm_sync_import'
                    );
                    return;
                }

                if (obj.event !== 'retryDepositImport') {
                    return;
                }

                // obj.data.id：deposit_imports 主键；后端会再次校验当前记录必须是失败状态且在管理员数据范围内。
                requestDepositImportAction(
                    obj,
                    '/api/admin/retryDepositImport/',
                    'admin.confirm_retry_import'
                );
            });

            form.on('submit(saveDepositImport)', function(data) {
                CrmAjax.request({
                    guard: 'admin',
                    url: '/api/admin/createDepositImport',
                    data: data.field,
                    success: function(res) {
                        if (successCodes[res.code]) {
                            layer.closeAll();
                            table.reload('depositImportTable');
                            layer.msg(res.message || CrmLang.t('common.success'), {icon: 1});
                            return;
                        }
                        layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                    }
                });

                return false;
            });

            /**
             * 将导入同步状态转换为页面可读文本。
             *
             * @param {Object} row 导入记录行；is_synced 为 0/1/2，分别表示待处理、成功和失败。
             * @returns {string} 可展示的状态文案。
             */
            function syncStatusText(row) {
                var value = Number(row.is_synced);
                if (value === 1) return CrmLang.t('admin.import_synced');
                if (value === 2) return CrmLang.t('admin.import_failed');
                return CrmLang.t('admin.import_pending');
            }

            /**
             * 执行单条入金导入记录动作，并在响应后刷新 fail_reason / 同步状态。
             *
             * @param {Object} obj Layui 表格行事件对象。
             * @param {string} endpoint API 前缀，最后会拼接导入记录 id。
             * @param {string} confirmKey 确认弹窗多语言 key。
             * @returns {void}
             */
            function requestDepositImportAction(obj, endpoint, confirmKey) {
                layer.confirm(CrmLang.t(confirmKey), function(index) {
                    CrmAjax.request({
                        guard: 'admin',
                        url: endpoint + encodeURIComponent(obj.data.id),
                        data: {},
                        success: function(res) {
                            layer.close(index);
                            table.reload('depositImportTable');
                            if (successCodes[res.code]) {
                                layer.msg(res.message || CrmLang.t('common.success'), {icon: 1});
                                return;
                            }
                            layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                        }
                    });
                });
            }

            /**
             * 生成默认批次号，方便手工新增单条导入记录时仍能按批次追踪。
             *
             * @returns {string} 默认批次号，格式为 DEP-时间戳。
             */
            function buildDefaultBatchNo() {
                return 'DEP-' + Date.now();
            }

            /**
             * 表格重绘后重新应用按钮权限。
             *
             * @returns {void}
             */
            function refreshPermissions() {
                if (window.CrmAdminPermissions && window.CrmAdminPermissions.refresh) {
                    window.CrmAdminPermissions.refresh();
                }
            }
        });
    });

    registry['deposits/index'] = once(function () {
        // Source: deposits/index.js
        layui.use(['table', 'form', 'layer'], function() {
            var table = layui.table, form = layui.form, layer = layui.layer;

            CrmLang.switchUI();

            function refreshPermissions() {
                // 重新应用按钮权限：Layui 表格重载后会重新生成操作列按钮，必须再次按 permissions.slug 隐藏无权限按钮。
                if (window.CrmAdminPermissions && window.CrmAdminPermissions.refresh) {
                    window.CrmAdminPermissions.refresh();
                }
            }

            // 入金审核列表：数据来自 /api/admin/deposits，后端会按管理员角色和数据范围过滤可见入金记录。
            // user_id 表示入金所属用户；status 表示入金审核状态，空字符串表示不限制状态。
            table.render(CrmTable.layuiConfig('admin', {
                elem: '#depositTable',
                url: '/api/admin/deposits',
                cols: [[
                    {field: 'id', title: 'ID', width: 90, sort: true},
                    {field: 'user_id', title: CrmLang.t('admin.userId'), width: 120},
                    // amount 表示入金申请金额，只做列表展示，真实金额审核仍由后端接口校验。
                    {field: 'amount', title: CrmLang.t('admin.amount'), width: 140},
                    {field: 'status', title: CrmLang.t('admin.status'), width: 120},
                    {field: 'created_at', title: CrmLang.t('user.createdAt'), width: 180},
                    {fixed: 'right', title: CrmLang.t('common.action'), toolbar: '#depositActions', width: 150}
                ]],
                parseData: function(res) {
                    // 需求 9：后端在 paginator 之外追加 summary，这里把它写进独立统计区块。
                    // 其余键（data/total/current_page）保持 paginator 原样，因此仍复用通用解析器。
                    renderAdminTableStatistics('#depositSummaryCards', res && res.data ? res.data.summary : {});

                    return CrmTable.layuiParseData()(res);
                },
                done: function() {
                    CrmLang.switchUI();
                    refreshPermissions();
                }
            }));

            form.on('submit(searchDeposits)', function(data) {
                table.reload('depositTable', {where: data.field, page: {curr: 1}});
                return false;
            });

            function changeDepositStatus(apiUrl, id) {
                // id 表示入金记录主键，后端据此读取记录并校验当前管理员是否可处理该用户数据。
                CrmAjax.request({
                    guard: 'admin',
                    url: apiUrl,
                    data: {id: id},
                    success: function(res) {
                        if (res.code === 1000 || res.code === 1002) {
                            table.reload('depositTable');
                            layer.msg(CrmLang.t('common.success'), {icon: 1});
                            return;
                        }
                        layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                    }
                });
            }

            table.on('tool(depositTable)', function(obj) {
                // approve 表示审核通过入金记录；reject 表示驳回入金记录，两个按钮都受 permissions.slug 控制显示。
                if (obj.event === 'approve') changeDepositStatus('/api/admin/depositApprove', obj.data.id);
                if (obj.event === 'reject') changeDepositStatus('/api/admin/depositReject', obj.data.id);
            });
        });
    });

    registry['exchange-rates/index'] = once(function () {
        // Source: exchange-rates/index.js
        layui.use(['form', 'layer', 'jquery'], function () {
            var form = layui.form;
            var layer = layui.layer;
            var $ = layui.jquery;
            var successCodes = {1000: true, 1001: true, 1002: true, 1003: true};

            CrmLang.switchUI();
            loadExchangeRate();

            $('#reloadExchangeRate').on('click', function () {
                loadExchangeRate();
            });

            form.on('submit(saveExchangeRate)', function (data) {
                // data.field 的字段名必须与 system_configs.key 一致，后端直接按 key 写入配置表。
                var payload = $.extend({}, data.field);

                // 未勾选的 checkbox 不会出现在 data.field 里，而后端用 has() 判断本次是否提交该字段。
                // 若不显式补 '0'，「关闭手续费」这个动作永远传不到后端 —— 开关会变成只能开不能关。
                // 这里统一归一为 '1'/'0' 字符串，与后端 normalizeSwitch() 的白名单口径对齐。
                payload.withdrawal_fee_enabled =
                    $('#exchangeRateForm input[name="withdrawal_fee_enabled"]').prop('checked') ? '1' : '0';

                // 开关关闭时金额输入被 disabled，浏览器不会提交它们，
                // 后端因此保留库内原值 —— 这正是「关闭后原配置值仍可恢复」所依赖的行为，
                // 不要在这里补上金额字段，否则会把禁用状态下的界面值写回配置。

                CrmAjax.request({
                    guard: 'admin',
                    url: '/api/admin/updateExchangeRate',
                    data: payload,
                    success: function (res) {
                        if (successCodes[res.code]) {
                            var next = res.data || payload;

                            form.val('exchangeRateForm', $.extend({}, next, {
                                // 后端回显的是字符串，'0' 在 JS 里是真值；必须显式比较才能正确渲染关闭态。
                                withdrawal_fee_enabled: String(next.withdrawal_fee_enabled) === '1'
                            }));
                            form.render();
                            syncFeeAmountState();
                            layer.msg(res.message || CrmLang.t('common.success'), {icon: 1});
                            return;
                        }

                        layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                    }
                });

                return false;
            });

            /**
             * 加载当前汇率配置并回填表单。
             *
             * @returns {void}
             */
            function loadExchangeRate() {
                CrmAjax.request({
                    guard: 'admin',
                    url: '/api/admin/exchangeRateInfo',
                    success: function (res) {
                        if (!successCodes[res.code]) {
                            layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                            return;
                        }

                        // res.data.sys_deposit_rate 表示入金汇率，res.data.sys_draw_rate 表示出金汇率。
                        // 手续费三项：withdrawal_fee_enabled 是总开关，另两项是金额。
                        // 开关用 === '1' 判定而非真值判定：后端回显的是字符串，'0' 在 JS 里是真值，
                        // 用 !! 会把「关闭」误渲染成开启。
                        form.val('exchangeRateForm', {
                            sys_deposit_rate: res.data.sys_deposit_rate || '',
                            sys_draw_rate: res.data.sys_draw_rate || '',
                            withdrawal_fee_enabled: String(res.data.withdrawal_fee_enabled) === '1',
                            withdrawal_fixed_fee_usd: res.data.withdrawal_fixed_fee_usd || '0',
                            withdrawal_fee_rate: res.data.withdrawal_fee_rate || '0'
                        });
                        form.render();
                        syncFeeAmountState();
                        refreshPermissions();
                    }
                });
            }

            /**
             * 按手续费总开关状态启用/禁用两个金额输入。
             *
             * 这只是交互提示：真正的「不扣费」由后端按开关判定，
             * 不依赖前端 disabled 状态，因此即使用户绕过前端也不会错扣。
             * 禁用的目的是避免管理员在开关关闭时填了金额、却以为已经生效。
             *
             * @returns {void}
             */
            function syncFeeAmountState() {
                var enabled = $('#exchangeRateForm input[name="withdrawal_fee_enabled"]').prop('checked');

                $('#exchangeRateForm [data-withdrawal-fee-amounts]')
                    .find('input')
                    .prop('disabled', !enabled)
                    .toggleClass('layui-disabled', !enabled);
            }

            // 开关切换时立即联动金额输入的可用状态。
            form.on('switch(withdrawalFeeEnabled)', function () {
                syncFeeAmountState();
            });

            /**
             * 刷新按钮权限状态。
             *
             * @returns {void}
             */
            function refreshPermissions() {
                if (window.CrmAdminPermissions && window.CrmAdminPermissions.refresh) {
                    window.CrmAdminPermissions.refresh();
                }
            }
        });
    });

    registry['gifts/index'] = once(function () {
        // Source: gifts/index.js
        layui.use(['table', 'form', 'layer', 'jquery'], function () {
            var table = layui.table;
            var form = layui.form;
            var layer = layui.layer;
            var $ = layui.jquery;
            var pageMode = $('[data-gift-page-mode]').attr('data-gift-page-mode') || 'all';
            var openSendGiftButton = document.getElementById('openSendGift');
            var openGiftItemButton = document.getElementById('openGiftItemForm');
            var sendGiftPending = false;
            var successCodes = {1000: true, 1001: true, 1002: true, 1003: true};

            CrmLang.switchUI();

            // 礼品配置表：读取 gift_items，供前台 available_gifts 使用，停用或无库存由前台接口再过滤。
            if (document.getElementById('giftItemTable')) {
                table.render(CrmTable.layuiConfig('admin', {
                elem: '#giftItemTable',
                id: 'giftItemTable',
                url: '/api/admin/giftItemList',
                cols: [[
                    {field: 'id', title: 'ID', width: 80},
                    {field: 'name', title: CrmLang.t('admin.gift_name'), minWidth: 180},
                    {field: 'points_cost', title: CrmLang.t('admin.points_cost'), width: 120},
                    {field: 'stock_quantity', title: CrmLang.t('admin.stock_quantity'), width: 130},
                    {
                        field: 'status',
                        title: CrmLang.t('admin.status'),
                        width: 100,
                        templet: function (row) {
                            return parseInt(row.status, 10) === 1 ? CrmLang.t('common.enabled') : CrmLang.t('common.disabled');
                        }
                    },
                    {field: 'image_url', title: CrmLang.t('admin.image_url'), minWidth: 180},
                    {field: 'updated_at', title: CrmLang.t('admin.updatedAt'), width: 170},
                    {fixed: 'right', title: CrmLang.t('common.action'), toolbar: '#giftItemActions', width: 150}
                ]],
                parseData: function (response) {
                    return CrmTable.layuiParseData(response);
                },
                done: function () {
                    CrmLang.switchUI();
                    refreshPermissions();
                }
                }));
            }

            // 发货记录表：读取 gift_shipments，后端通过 /api/admin/giftShipmentList 权限控制可访问性。
            if (document.getElementById('giftShipmentTable')) {
                table.render(CrmTable.layuiConfig('admin', {
                elem: '#giftShipmentTable',
                id: 'giftShipmentTable',
                url: '/api/admin/giftShipmentList',
                cols: [[
                    {field: 'id', title: 'ID', width: 80},
                    {field: 'user_id', title: CrmLang.t('admin.userId'), width: 110},
                    {field: 'gift_name', title: CrmLang.t('admin.gift_name'), width: 160},
                    {field: 'gift_quantity', title: CrmLang.t('admin.gift_quantity'), width: 110},
                    {field: 'recipient_name', title: CrmLang.t('admin.recipient_name'), width: 130},
                    {field: 'recipient_phone', title: CrmLang.t('admin.recipient_phone'), width: 140},
                    {field: 'recipient_address', title: CrmLang.t('admin.recipient_address'), minWidth: 220},
                    {field: 'status', title: CrmLang.t('admin.status'), width: 100},
                    {field: 'tracking_number', title: CrmLang.t('admin.tracking_number'), width: 160},
                    {field: 'sender_name', title: CrmLang.t('admin.sender_name'), width: 120},
                    {field: 'admin_name', title: CrmLang.t('admin.admin_name'), width: 120},
                    {field: 'shipped_at', title: CrmLang.t('admin.shipped_at'), width: 170},
                    {fixed: 'right', title: CrmLang.t('common.action'), toolbar: '#giftShipmentActions', width: 130}
                ]],
                parseData: function (response) {
                    return CrmTable.layuiParseData(response);
                },
                done: function () {
                    CrmLang.switchUI();
                    refreshPermissions();
                }
                }));
            }

            // 可发放地址表：读取 user_addresses，并由后端限制 user_infos.is_gift_allowed=1。
            if (document.getElementById('giftAddressTable')) {
                table.render(CrmTable.layuiConfig('admin', {
                elem: '#giftAddressTable',
                id: 'giftAddressTable',
                url: '/api/admin/giftAddressList',
                where: pageMode === 'send' ? {is_default: 1} : {},
                cols: [[
                    {type: 'checkbox', fixed: 'left'},
                    {field: 'id', title: 'ID', width: 80},
                    {field: 'user_id', title: CrmLang.t('admin.userId'), width: 110},
                    {field: 'user_name', title: CrmLang.t('admin.user_name'), width: 130},
                    {field: 'recipient_name', title: CrmLang.t('admin.recipient_name'), width: 130},
                    {field: 'recipient_phone', title: CrmLang.t('admin.recipient_phone'), width: 140},
                    {field: 'recipient_address', title: CrmLang.t('admin.recipient_address'), minWidth: 260},
                    {
                        field: 'is_default',
                        title: CrmLang.t('admin.default_address'),
                        width: 120,
                        templet: function (row) {
                            return parseInt(row.is_default, 10) === 1 ? CrmLang.t('common.yes') : CrmLang.t('common.no');
                        }
                    }
                ]],
                parseData: function (response) {
                    return CrmTable.layuiParseData(response);
                },
                done: function () {
                    CrmLang.switchUI();
                    refreshPermissions();
                }
                }));
            }

            if (openSendGiftButton) {
                openSendGiftButton.onclick = function () {
                    var selected = table.checkStatus('giftAddressTable').data || [];
                    if (selected.length === 0) {
                        layer.msg(CrmLang.t('admin.select_gift_recipients'), {icon: 0});
                        return;
                    }

                    form.val('sendGiftForm', {
                        address_payload: JSON.stringify(selected.map(addressToRecipient)),
                        gift_quantity: 1
                    });

                    layer.open({
                        type: 1,
                        title: CrmLang.t('admin.send_gift'),
                        area: ['min(560px, calc(100vw - 32px))', 'min(520px, calc(100vh - 32px))'],
                        content: $('#sendGiftModal')
                    });
                };
            }

            if (openGiftItemButton) {
                openGiftItemButton.onclick = function () {
                    openGiftItemModal({
                        id: '',
                        name: '',
                        description: '',
                        points_cost: 0,
                        stock_quantity: 0,
                        status: 1,
                        image_url: ''
                    });
                };
            }

            form.on('submit(searchGiftItem)', function (data) {
                table.reload('giftItemTable', {where: data.field, page: {curr: 1}});
                return false;
            });

            form.on('submit(searchGiftShipment)', function (data) {
                // data.field：发货列表筛选参数，字段名与 GiftController::shipmentList 保持一致。
                table.reload('giftShipmentTable', {where: data.field, page: {curr: 1}});
                return false;
            });

            $('#exportGiftShipments').on('click', function () {
                downloadAdminCsv($, layer, '/api/admin/exportGiftShipments', serializeForm($, '#giftShipmentSearchForm'), 'gift_shipments_export.csv');
            });

            table.on('tool(giftShipmentTable)', function (obj) {
                if (obj.event === 'updateGiftShipment') {
                    openUpdateGiftShipmentModal(obj.data || {});
                }
            });

            table.on('tool(giftItemTable)', function (obj) {
                if (obj.event === 'editGiftItem') {
                    openGiftItemModal(obj.data || {});
                    return;
                }

                if (obj.event === 'deleteGiftItem') {
                    layer.confirm(CrmLang.t('common.confirm'), function (index) {
                        CrmAjax.request({
                            guard: 'admin',
                            url: '/api/admin/deleteGiftItem/' + encodeURIComponent(obj.data.id),
                            success: function (res) {
                                if (!successCodes[res.code]) {
                                    layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                                    return;
                                }
                                obj.del();
                                layer.close(index);
                                layer.msg(res.message || CrmLang.t('common.success'), {icon: 1});
                            }
                        });
                    });
                }
            });

            form.on('submit(searchGiftAddress)', function (data) {
                // data.field：地址列表筛选参数，字段名与 GiftController::addressList 保持一致。
                table.reload('giftAddressTable', {where: data.field, page: {curr: 1}});
                return false;
            });

            form.on('submit(submitSendGift)', function (data) {
                var recipients = JSON.parse(data.field.address_payload || '[]');
                var $submit = $(data.elem);

                if (sendGiftPending) {
                    return false;
                }
                if (recipients.length === 0) {
                    layer.msg(CrmLang.t('admin.select_gift_recipients'), {icon: 0});
                    return false;
                }

                sendGiftPending = true;
                $submit.prop('disabled', true).attr('aria-busy', 'true');

                CrmAjax.request({
                    guard: 'admin',
                    url: '/api/admin/sendGift',
                    data: {
                        sender_name: data.field.sender_name,
                        gift_name: data.field.gift_name,
                        gift_quantity: data.field.gift_quantity,
                        tracking_number: data.field.tracking_number,
                        remark: data.field.remark,
                        recipients: recipients
                    },
                    success: function (res) {
                        if (!successCodes[res.code]) {
                            layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                            return;
                        }

                        layer.closeAll('page');
                        layer.msg(res.message || CrmLang.t('common.success'), {icon: 1});
                        if (document.getElementById('giftShipmentTable')) {
                            table.reload('giftShipmentTable');
                        }
                    },
                    complete: function () {
                        sendGiftPending = false;
                        $submit.prop('disabled', false).removeAttr('aria-busy');
                    }
                });

                return false;
            });

            form.on('submit(submitGiftItemForm)', function (data) {
                var id = data.field.id;
                var apiUrl = id ? '/api/admin/updateGiftItem/' + encodeURIComponent(id) : '/api/admin/createGiftItem';

                CrmAjax.request({
                    guard: 'admin',
                    url: apiUrl,
                    data: {
                        name: data.field.name,
                        description: data.field.description,
                        points_cost: data.field.points_cost,
                        stock_quantity: data.field.stock_quantity,
                        status: data.field['status'],
                        image_url: data.field.image_url
                    },
                    success: function (res) {
                        if (!successCodes[res.code]) {
                            layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                            return;
                        }

                        layer.closeAll('page');
                        layer.msg(res.message || CrmLang.t('common.success'), {icon: 1});
                        table.reload('giftItemTable');
                    }
                });

                return false;
            });

            form.on('submit(submitUpdateGiftShipment)', function (data) {
                var id = data.field.id;

                if (!id) {
                    layer.msg(CrmLang.t('response.validation_failed'), {icon: 2});
                    return false;
                }

                CrmAjax.request({
                    guard: 'admin',
                    url: '/api/admin/updateGiftShipment/' + encodeURIComponent(id),
                    data: {
                        status: data.field['status'],
                        tracking_number: data.field.tracking_number,
                        remark: data.field.remark
                    },
                    success: function (res) {
                        if (!successCodes[res.code]) {
                            layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                            return;
                        }

                        layer.closeAll('page');
                        layer.msg(res.message || CrmLang.t('common.success'), {icon: 1});
                        table.reload('giftShipmentTable');
                    }
                });

                return false;
            });

            /**
             * 将地址表格行转换为发放接口 recipients 参数。
             *
             * @param {Object} row 地址表格当前行，字段来自 user_addresses 查询结果。
             * @returns {Object} 发放接口需要的单个收件人参数。
             */
            function addressToRecipient(row) {
                return {
                    user_id: row.user_id,
                    address_id: row.id,
                    recipient_name: row.recipient_name,
                    recipient_phone: row.recipient_phone,
                    recipient_address: row.recipient_address
                };
            }

            function openGiftItemModal(row) {
                form.val('giftItemForm', {
                    id: row.id || '',
                    name: row.name || '',
                    description: row.description || '',
                    points_cost: typeof row.points_cost === 'undefined' ? 0 : row.points_cost,
                    stock_quantity: typeof row.stock_quantity === 'undefined' ? 0 : row.stock_quantity,
                    status: String(typeof row.status === 'undefined' ? 1 : row.status),
                    image_url: row.image_url || ''
                });

                layer.open({
                    type: 1,
                    title: CrmLang.t('admin.gift_items'),
                    area: ['min(620px, calc(100vw - 32px))', 'min(560px, calc(100vh - 32px))'],
                    content: $('#giftItemModal')
                });
                form.render();
            }

            function openUpdateGiftShipmentModal(row) {
                form.val('updateGiftShipmentForm', {
                    id: row.id || '',
                    status: String(typeof row.status === 'undefined' ? 0 : row.status),
                    tracking_number: row.tracking_number || '',
                    remark: row.remark || ''
                });

                layer.open({
                    type: 1,
                    title: CrmLang.t('admin.update_gift_shipment'),
                    area: ['min(560px, calc(100vw - 32px))', 'min(420px, calc(100vh - 32px))'],
                    content: $('#updateGiftShipmentModal')
                });
                form.render();
            }

            /**
             * 刷新页面按钮权限。
             *
             * @returns {void}
             */
            function refreshPermissions() {
                if (window.CrmAdminPermissions && window.CrmAdminPermissions.refresh) {
                    window.CrmAdminPermissions.refresh();
                }
            }
        });
    });

    registry['group-configs/index'] = once(function () {
        // Source: group-configs/index.js
        layui.use(['table', 'form', 'layer', 'jquery'], function() {
            var table = layui.table;
            var form = layui.form;
            var layer = layui.layer;
            var $ = layui.jquery;
            var successCodes = {1000: true, 1001: true, 1002: true, 1003: true};

            CrmLang.switchUI();

            // 组别配置列表读取 /api/admin/group-configs。
            // 新增、编辑、删除分别调用 /api/admin/createGroupConfig、/api/admin/updateGroupConfig/{id}、/api/admin/deleteGroupConfig/{id}。
            // group_configs 中 keyword 表示组别名称搜索关键字，name 表示组别名称，group_name 表示页面表单提交的组别名称。
            // radix 表示交易组别基数，category 表示组别分类：1=代理组，2=用户组。
            // has_commission 表示是否参与返佣，is_enabled 表示是否启用，is_ecn 表示是否 ECN 组，is_default 表示是否默认组。
            // id 表示组别配置主键；表格重载后重新应用按钮权限，权限来源为 permissions.slug。

            // 组别配置列表：读取 /api/admin/group-configs，数据来自真实 group_configs 表。
            // keyword 表示组别名称搜索关键字，提交给后端用于按 name 或兼容字段过滤组别配置。
            table.render(CrmTable.layuiConfig('admin', {
                elem: '#groupConfigTable',
                id: 'groupConfigTable',
                url: '/api/admin/group-configs',
                cols: [[
                    // id 表示组别配置主键，对应 group_configs.id，编辑和删除接口都会把它作为路由参数。
                    {field: 'id', title: 'ID', width: 90, sort: true},
                    // name 表示组别名称，对应 group_configs.name；页面表单使用 group_name，再由后端映射到 name。
                    {field: 'name', title: CrmLang.t('admin.name'), width: 180},
                    // radix 表示交易组别基数，对应 group_configs.radix，影响交易组或业务组相关计算。
                    {field: 'radix', title: CrmLang.t('admin.radix'), width: 120},
                    // category 表示组别分类：1=代理组，2=用户组，必须与 GroupConfigController 校验规则保持一致。
                    {field: 'category', title: CrmLang.t('admin.category'), width: 120, templet: function(row) {
                        return String(row.category) === '1' ? CrmLang.t('admin.agent_group') : CrmLang.t('admin.user_group');
                    }},
                    // is_enabled 表示是否启用：1=启用，0=禁用；这里只负责展示，最终状态以接口返回为准。
                    {field: 'is_enabled', title: CrmLang.t('admin.status'), width: 120, templet: function(row) {
                        return String(row.is_enabled) === '1' ? CrmLang.t('common.enabled') : CrmLang.t('common.disabled');
                    }},
                    {field: 'updated_at', title: CrmLang.t('admin.updatedAt'), width: 180},
                    {fixed: 'right', title: CrmLang.t('common.action'), toolbar: '#groupConfigActions', width: 150}
                ]],
                parseData: CrmTable.layuiParseData(),
                done: function() {
                    CrmLang.switchUI();
                    refreshPermissions();
                }
            }));

            form.on('submit(searchGroupConfigs)', function(data) {
                table.reload('groupConfigTable', {where: data.field, page: {curr: 1}});
                return false;
            });

            $('#addGroupConfig').on('click', function() {
                openGroupConfigModal({
                    id: '',
                    name: '',
                    radix: 50,
                    category: 2,
                    has_commission: 0,
                    is_enabled: 1,
                    is_ecn: 0,
                    is_default: 0
                });
            });

            table.on('tool(groupConfigTable)', function(obj) {
                if (obj.event === 'edit') {
                    openGroupConfigModal(obj.data);
                    return;
                }

                if (obj.event === 'delete') {
                    layer.confirm(CrmLang.t('common.confirm'), function(index) {
                        CrmAjax.request({
                            guard: 'admin',
                            // /api/admin/deleteGroupConfig/{id}：id 表示组别配置主键，后端 check.permission:admin 会按权限表再次鉴权。
                            url: '/api/admin/deleteGroupConfig/' + encodeURIComponent(obj.data.id),
                            data: {id: obj.data.id},
                            success: function(res) {
                                if (successCodes[res.code]) {
                                    obj.del();
                                    layer.close(index);
                                    layer.msg(res.message || CrmLang.t('common.success'), {icon: 1});
                                    return;
                                }
                                layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                            }
                        });
                    });
                }
            });

            form.on('submit(saveGroupConfig)', function(data) {
                var id = data.field.id;
                // /api/admin/createGroupConfig 用于新增；/api/admin/updateGroupConfig/{id} 用于编辑，id 表示组别配置主键。
                var apiUrl = id ? '/api/admin/updateGroupConfig/' + encodeURIComponent(id) : '/api/admin/createGroupConfig';

                // group_name 表示页面表单提交的组别名称，后端 normalizePayload 会映射为 group_configs.name。
                // has_commission 表示是否参与返佣；is_enabled 表示是否启用；is_ecn 表示是否 ECN 组；is_default 表示是否默认组。
                // Layui 未勾选复选框时不会提交字段，这里统一转成 1/0，避免编辑时旧值残留。
                data.field.has_commission = data.field.has_commission ? 1 : 0;
                data.field.is_enabled = data.field.is_enabled ? 1 : 0;
                data.field.is_ecn = data.field.is_ecn ? 1 : 0;
                data.field.is_default = data.field.is_default ? 1 : 0;

                CrmAjax.request({
                    guard: 'admin',
                    url: apiUrl,
                    data: data.field,
                    success: function(res) {
                        if (successCodes[res.code]) {
                            layer.closeAll();
                            table.reload('groupConfigTable');
                            layer.msg(res.message || CrmLang.t('common.success'), {icon: 1});
                            return;
                        }
                        layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                    }
                });

                return false;
            });

            /**
             * 打开组别配置弹窗。
             *
             * 参数逻辑说明：
             * - row：表格当前行数据；id 为空表示新增，有值表示编辑指定 group_configs.id。
             * - name/group_name：name 来自接口返回，group_name 是表单字段，两者都表示组别名称。
             * - radix：交易组别基数，默认 50。
             * - category：组别分类，1=代理组，2=用户组。
             *
             * @param {Object} row 组别配置行数据。
             * @returns {void}
             */
            function openGroupConfigModal(row) {
                form.val('groupConfigForm', {
                    id: row.id || '',
                    group_name: row.name || row.group_name || '',
                    radix: row.radix || 50,
                    category: String(row.category || 2),
                    has_commission: String(row.has_commission || 0) === '1',
                    is_enabled: String(typeof row.is_enabled === 'undefined' ? 1 : row.is_enabled) === '1',
                    is_ecn: String(row.is_ecn || 0) === '1',
                    is_default: String(row.is_default || 0) === '1'
                });

                layer.open({
                    type: 1,
                    title: row.id ? CrmLang.t('admin.edit_group_config') : CrmLang.t('admin.create_group_config'),
                    area: ['620px', '460px'],
                    content: $('#groupConfigModal')
                });
                form.render();
            }

            /**
             * 重新应用按钮权限。
             *
             * 逻辑说明：
             * - 按钮 data-permission 来自 permissions.slug，例如 admin_group_config_create、admin_group_config_update、admin_group_config_delete。
             * - 表格每次重载都会重新渲染操作列，所以必须再次调用 CrmAdminPermissions.refresh() 隐藏无权限按钮。
             * - 前端隐藏只改善体验，最终安全边界仍由后端 check.permission:admin 和权限表配置决定。
             *
             * @returns {void}
             */
            function refreshPermissions() {
                if (window.CrmAdminPermissions && window.CrmAdminPermissions.refresh) {
                    window.CrmAdminPermissions.refresh();
                }
            }
        });
    });

    registry['menus/index'] = once(function () {
        // Source: menus/index.js
        layui.use(['tree', 'form', 'layer', 'jquery'], function() {
            var tree = layui.tree, form = layui.form, layer = layui.layer, $ = layui.jquery;

            CrmLang.switchUI();

            function loadMenus() {
                CrmAjax.request({
                    guard: 'admin',
                    url: '/api/admin/menus/tree',
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
                // tree 节点来自 permissions 表；弹窗表单只暴露常用菜单字段，并回写 route、icon、name。
                // guard_type 用于区分 admin/front 菜单命名空间，避免前后台菜单权限混用。
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
                var apiUrl = data.field.id ? '/api/admin/updateMenu' : '/api/admin/createMenu';
                if (!data.field.guard_type) data.field.guard_type = 'admin';
                CrmAjax.request({
                    guard: 'admin',
                    url: apiUrl,
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
    });

    registry['news/index'] = once(function () {
        // Source: news/index.js
        layui.use(['table', 'form', 'laydate', 'layer', 'jquery'], function() {
            var table = layui.table;
            var form = layui.form;
            var laydate = layui.laydate;
            var layer = layui.layer;
            var $ = layui.jquery;
            var successCodes = {1000: true, 1001: true, 1002: true, 1003: true};
            var $newsPageContext = $('[data-news-mode]').first();

            CrmLang.switchUI();

            laydate.render({elem: '#newsStartDate', type: 'date'});
            laydate.render({elem: '#newsEndDate', type: 'date'});

            // 新闻公告列表读取 /api/admin/news。
            // 新增、编辑、删除、发布状态切换分别调用对应新闻公告 API。
            // news 中 title 表示新闻标题，content 表示新闻正文，is_published 表示发布状态：1=已发布，0=未发布。
            // id 表示新闻公告主键；表格重载后重新应用按钮权限，权限来源为 permissions.slug。

            // 新闻公告列表：读取 /api/admin/news，数据来自真实 news 表。
            // title 表示新闻标题搜索参数，必须与 NewsController@index 读取的入参保持一致。
            table.render(CrmTable.layuiConfig('admin', {
                elem: '#newsTable',
                id: 'newsTable',
                url: '/api/admin/news',
                cols: [[
                    // id 表示新闻公告主键，对应 news.id，编辑和删除接口都会把它作为路由参数。
                    {field: 'id', title: 'ID', width: 90, sort: true},
                    // title 表示新闻标题，对应 news.title，是后台搜索和前台公告展示的主要标题字段。
                    {field: 'title', title: CrmLang.t('admin.title'), minWidth: 260},
                    // is_published 表示发布状态：1=已发布，0=未发布；前台只应展示已发布新闻公告。
                    {field: 'is_published', title: CrmLang.t('admin.publishStatus'), width: 140, templet: function(row) {
                        return Number(row.is_published) === 1 ? CrmLang.t('admin.published') : CrmLang.t('admin.unpublished');
                    }},
                    {field: 'created_at', title: CrmLang.t('user.createdAt'), width: 180},
                    {fixed: 'right', title: CrmLang.t('common.action'), toolbar: '#newsActions', width: 210}
                ]],
                parseData: CrmTable.layuiParseData(),
                done: function() {
                    CrmLang.switchUI();
                    refreshPermissions();
                }
            }));

            document.getElementById('reloadNews').onclick = function() {
                table.reload('newsTable', {where: newsSearchFilters(), page: {curr: 1}});
            };

            $('#addNews').on('click', function() {
                openNewsModal({
                    id: '',
                    title: '',
                    content: '',
                    is_published: 1
                });
            });

            table.on('tool(newsTable)', function(obj) {
                if (obj.event === 'edit') {
                    openNewsModal(obj.data);
                    return;
                }

                if (obj.event === 'delete') {
                    layer.confirm(CrmLang.t('common.confirm_delete'), function(index) {
                        CrmAjax.request({
                            guard: 'admin',
                            // /api/admin/deleteNews/{id}：id 表示新闻公告主键，后端 check.permission:admin 会按权限表再次鉴权。
                            url: '/api/admin/deleteNews/' + encodeURIComponent(obj.data.id),
                            data: {id: obj.data.id},
                            success: function(res) {
                                if (successCodes[res.code]) {
                                    obj.del();
                                    layer.close(index);
                                    layer.msg(res.message || CrmLang.t('common.success'), {icon: 1});
                                    return;
                                }
                                layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                            }
                        });
                    });
                }

                if (obj.event === 'toggle') {
                    layer.confirm(CrmLang.t('common.confirm_status_change') || CrmLang.t('common.confirm'), function(index) {
                        CrmAjax.request({
                            guard: 'admin',
                            url: '/api/admin/toggleNews/' + encodeURIComponent(obj.data.id),
                            data: {id: obj.data.id},
                            success: function(res) {
                                if (successCodes[res.code]) {
                                    layer.close(index);
                                    table.reload('newsTable');
                                    layer.msg(res.message || CrmLang.t('common.success'), {icon: 1});
                                    return;
                                }
                                layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                            }
                        });
                    });
                }
            });

            form.on('submit(searchNews)', function() {
                table.reload('newsTable', {where: newsSearchFilters(), page: {curr: 1}});
                return false;
            });

            $('#newsSearchForm').on('reset', function() {
                window.setTimeout(function() {
                    table.reload('newsTable', {where: clearedNewsSearchFilters(), page: {curr: 1}});
                    form.render('select');
                }, 0);
            });

            form.on('submit(saveNews)', function(data) {
                var id = data.field.id;
                var $submit = $(data.elem);
                // /api/admin/createNews 用于新增；/api/admin/updateNews/{id} 用于编辑，id 表示新闻公告主键。
                var apiUrl = id ? '/api/admin/updateNews/' + encodeURIComponent(id) : '/api/admin/createNews';

                if ($submit.prop('disabled')) {
                    return false;
                }
                setNewsSubmitting($submit, true);

                // content 表示新闻正文；is_published 表示发布状态，1=已发布，0=未发布。
                CrmAjax.request({
                    guard: 'admin',
                    url: apiUrl,
                    data: data.field,
                    success: function(res) {
                        if (successCodes[res.code]) {
                            layer.closeAll();
                            table.reload('newsTable');
                            layer.msg(res.message || CrmLang.t('common.success'), {icon: 1});
                            return;
                        }
                        layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                    },
                    error: function(res) {
                        layer.msg((res && res.message) || CrmLang.t('common.error'), {icon: 2});
                    },
                    complete: function() {
                        setNewsSubmitting($submit, false);
                    }
                });

                return false;
            });

            /**
             * 打开新闻公告表单弹窗。
             *
             * 参数逻辑说明：
             * - row：表格当前行数据；id 为空表示新增，有值表示编辑指定 news.id。
             * - title：新闻标题，对应 news.title。
             * - content：新闻正文，对应 news.content。
             * - is_published：发布状态，1=已发布，0=未发布，决定前台是否可见。
             *
             * @param {Object} row 新闻公告行数据。
             * @returns {void}
             */
            function openNewsModal(row) {
                var savePermission = row.id ? 'admin_news_update' : 'admin_news_create';

                form.val('newsForm', {
                    id: row.id || '',
                    title: row.title || '',
                    content: row.content || '',
                    is_published: String(row.is_published !== undefined ? row.is_published : 1)
                });

                $('#saveNewsButton')
                    .attr('data-permission', savePermission)
                    .data('permission', savePermission);
                refreshPermissions();

                layer.open({
                    type: 1,
                    title: row.id ? CrmLang.t('admin.edit_news') : CrmLang.t('admin.create_news'),
                    area: newsModalArea(),
                    content: $('#newsModal')
                });
                form.render();
            }

            function newsSearchFilters() {
                var filters = {};

                $('#newsSearchForm').serializeArray().forEach(function(field) {
                    if (field.value !== '') {
                        filters[field.name] = field.value;
                    }
                });

                return filters;
            }

            function clearedNewsSearchFilters() {
                return {
                    title: null,
                    start_date: null,
                    end_date: null,
                    is_published: null
                };
            }

            function newsModalArea() {
                var width = Math.min(680, window.innerWidth - 32);
                var height = Math.min(600, window.innerHeight - 32);

                return [width + 'px', height + 'px'];
            }

            function setNewsSubmitting($button, submitting) {
                $button
                    .prop('disabled', submitting)
                    .toggleClass('layui-btn-disabled', submitting)
                    .attr('aria-busy', submitting ? 'true' : 'false');
            }

            function initializeNewsPageMode() {
                var mode = String($newsPageContext.attr('data-news-mode') || 'list');
                var row = newsPageContext();

                if (mode === 'create') {
                    openNewsModal({id: '', title: '', content: '', is_published: 1});
                    return;
                }
                if (mode === 'edit') {
                    openNewsModal(row);
                }
            }

            function newsPageContext() {
                var raw = String($newsPageContext.attr('data-news-info') || '{}');
                var parsed;

                try {
                    parsed = JSON.parse(raw);
                } catch (error) {
                    return {};
                }

                return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
            }

            /**
             * 重新应用按钮权限。
             *
             * 逻辑说明：
             * - 按钮 data-permission 来自 permissions.slug，例如 admin_news_create、admin_news_update、admin_news_delete、admin_news_toggle。
             * - 表格每次重载都会重新渲染操作列，所以必须再次调用 CrmAdminPermissions.refresh() 隐藏无权限按钮。
             * - 前端隐藏只改善体验，最终安全边界仍由后端 check.permission:admin 和权限表配置决定。
             *
             * @returns {void}
             */
            function refreshPermissions() {
                if (window.CrmAdminPermissions && window.CrmAdminPermissions.refresh) {
                    window.CrmAdminPermissions.refresh();
                }
            }

            initializeNewsPageMode();
        });
    });

    registry['online-users/index'] = once(function () {
        // Source: online-users/index.js
        layui.use(['table', 'form', 'laydate', 'layer'], function () {
            var table = layui.table;
            var form = layui.form;
            var laydate = layui.laydate;
            var layer = layui.layer;
            var successCodes = {1000: true, 1001: true, 1002: true, 1003: true};

            CrmLang.switchUI();

            // 日期筛选：start_date/end_date 会由后端转换为 last_activity 时间戳范围。
            laydate.render({elem: '#onlineUserStartDate', type: 'date'});
            laydate.render({elem: '#onlineUserEndDate', type: 'date'});

            // 在线用户表格：数据来自 /api/admin/onlineUserList，后端继续通过 permissions.api_route 做接口鉴权。
            table.render(CrmTable.layuiConfig('admin', {
                elem: '#onlineUserTable',
                id: 'onlineUserTable',
                url: '/api/admin/onlineUserList',
                cols: [[
                    {field: 'user_id', title: CrmLang.t('admin.userId'), width: 120},
                    {field: 'user_name', title: CrmLang.t('admin.user_name'), width: 160},
                    {
                        field: 'account_type',
                        title: CrmLang.t('admin.account_type'),
                        width: 120,
                        templet: function (row) {
                            return accountTypeText(row.account_type);
                        }
                    },
                    {
                        field: 'last_activity',
                        title: CrmLang.t('admin.last_activity'),
                        width: 180,
                        templet: function (row) {
                            return formatTimestamp(row.last_activity);
                        }
                    },
                    {field: 'ip_address', title: CrmLang.t('admin.ip_address'), width: 150},
                    {field: 'user_agent', title: CrmLang.t('admin.user_agent'), minWidth: 260},
                    {field: 'updated_at', title: CrmLang.t('admin.updatedAt'), width: 170},
                    {fixed: 'right', title: CrmLang.t('common.action'), toolbar: '#onlineUserActions', width: 140}
                ]],
                parseData: function (response) {
                    return CrmTable.layuiParseData(response);
                },
                done: function () {
                    CrmLang.switchUI();
                    refreshPermissions();
                }
            }));

            document.getElementById('reloadOnlineUser').onclick = function () {
                table.reload('onlineUserTable');
            };

            form.on('submit(searchOnlineUser)', function (data) {
                // data.field：筛选字段集合，字段名与 OnlineUserController::onlineUserList 参数保持一致。
                table.reload('onlineUserTable', {where: data.field, page: {curr: 1}});
                return false;
            });

            table.on('tool(onlineUserTable)', function (obj) {
                if (obj.event !== 'forceOffline') {
                    return;
                }

                layer.confirm(CrmLang.t('admin.confirm_force_offline'), function (index) {
                    layer.close(index);
                    CrmAjax.request({
                        guard: 'admin',
                        url: '/api/admin/forceOfflineUser/' + encodeURIComponent(obj.data.id),
                        success: function (res) {
                            if (successCodes[res.code]) {
                                table.reload('onlineUserTable');
                                layer.msg(res.message || CrmLang.t('common.success'), {icon: 1});
                                return;
                            }

                            layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                        }
                    });
                });
            });

            /**
             * 转换账号类型显示文案。
             *
             * @param {number|string|null} accountType 账号类型；1 表示代理商，2 表示普通客户，其他值按未知显示。
             * @returns {string} 多语言后的账号类型文案。
             */
            function accountTypeText(accountType) {
                if (parseInt(accountType, 10) === 1) {
                    return CrmLang.t('admin.account_type_agent');
                }

                if (parseInt(accountType, 10) === 2) {
                    return CrmLang.t('admin.account_type_customer');
                }

                return CrmLang.t('admin.account_type_unknown');
            }

            /**
             * 格式化 10 位秒级时间戳。
             *
             * @param {number|string|null} timestamp user_onlines.last_activity 秒级时间戳。
             * @returns {string} yyyy-MM-dd HH:mm:ss 格式时间；无有效值时返回空字符串。
             */
            function formatTimestamp(timestamp) {
                var value = parseInt(timestamp, 10);
                if (!value) {
                    return '';
                }

                var date = new Date(value * 1000);
                var pad = function (number) {
                    return number < 10 ? '0' + number : String(number);
                };

                return [
                    date.getFullYear(),
                    pad(date.getMonth() + 1),
                    pad(date.getDate())
                ].join('-') + ' ' + [
                    pad(date.getHours()),
                    pad(date.getMinutes()),
                    pad(date.getSeconds())
                ].join(':');
            }

            function refreshPermissions() {
                if (window.CrmAdminPermissions && window.CrmAdminPermissions.refresh) {
                    window.CrmAdminPermissions.refresh();
                }
            }
        });
    });

    registry['permissions/index'] = once(function () {
        // Source: permissions/index.js
        layui.use(['tree', 'layer', 'jquery'], function() {
            var tree = layui.tree, layer = layui.layer, $ = layui.jquery;

            // 加载后台权限树：读取 permissions 表中的后台菜单、按钮和接口权限字典，用于核对 DB 权限配置是否完整。
            CrmAjax.request({
                guard: 'admin',
                url: '/api/admin/permissions/tree',
                // guard_type 表示权限所属守卫；admin 只返回后台权限，避免混入前台 agent/customer 菜单权限。
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
                // 当前页面只做权限树预览；角色授权在角色模块通过 assignPermissions 完成，避免在两个页面维护授权状态。
                layer.msg(CrmLang.t('common.success'), {icon: 1});
            });

            /**
             * normalizeTree 将后端权限节点转换为 Layui tree 需要的字段。
             *
             * 参数含义：
             * - nodes：后端 /api/admin/permissions/tree 返回的权限节点数组，每个节点通常来自 permissions 表。
             * - node.id：权限主键，用作 Layui tree 节点 id。
             * - node.name/node.slug：优先显示权限名称，缺少名称时用 slug 或 id 兜底，方便定位权限字典配置。
             * - node.children：当前权限节点的子权限，递归转换后继续交给 Layui tree 渲染。
             *
             * @param {Array<Object>} nodes 后端权限树节点数组。
             * @returns {Array<Object>} Layui tree 可渲染的节点数组。
             */
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
    });

    registry['position-summary/index'] = once(function () {
        // Source: position-summary/index.js
        layui.use(['table', 'form', 'layer', 'jquery'], function() {
            var table = layui.table;
            var form = layui.form;
            var layer = layui.layer;
            var $ = layui.jquery;

            CrmLang.switchUI();
            var $positionSummaryPath = $('#positionSummaryPath');
            var $positionSummaryPathLabel = $positionSummaryPath.find('[data-position-drilldown-label]');
            // 需求 12：链路按点击顺序渐进展开，clickedChain 只保存用户ID。
            var $positionSummaryChain = $('#positionSummaryChain');
            var $positionSummaryChainNodes = $positionSummaryChain.find('[data-position-chain-nodes]');
            var clickedChain = [];

            /**
             * 渲染后台持仓汇总表格。
             *
             * 字段逻辑说明：
             * - user_id：CRM 业务用户 ID；交易数据通过 user_infos.mt4_code = mt4_trades.login 关联。
             * - account_type：账户类型，1=代理，2=普通客户。
             * - total_volume/total_profit/total_comm/total_swaps：由后端按 mt4_trades 聚合得到。
             * - total_noble_metal 等字段：由 symbol_prices.group_id 对交易品种进行第一阶段归类。
             */
            table.render(CrmTable.layuiConfig('admin', {
                elem: '#positionSummaryTable',
                id: 'positionSummaryTable',
                url: '/api/admin/positionSummaryList',
                cols: [[
                    {field: 'user_id', title: CrmLang.t('admin.userId'), width: 150, sort: true, templet: userIdDrilldownText},
                    {field: 'user_name', title: CrmLang.t('admin.user_name'), width: 160},
                    {field: 'parent_id', title: CrmLang.t('admin.parent_id'), width: 120},
                    {field: 'account_type', title: CrmLang.t('admin.account_type'), width: 120, templet: accountTypeText},
                    {field: 'mt4_group', title: CrmLang.t('admin.mt4_group'), width: 150},
                    // MT4 快照列来自 user_infos.mt4_code = mt4_users.login，用于核对真实交易账号资金状态。
                    {field: 'mt4_login', title: CrmLang.t('admin.mt4_login'), width: 130},
                    {field: 'mt4_name', title: CrmLang.t('admin.mt4_name'), width: 150},
                    {field: 'mt4_account_group', title: CrmLang.t('admin.mt4_account_group'), width: 150},
                    {field: 'mt4_balance', title: CrmLang.t('admin.mt4_balance'), width: 130, templet: numberText},
                    {field: 'mt4_equity', title: CrmLang.t('admin.mt4_equity'), width: 130, templet: numberText},
                    {field: 'mt4_margin', title: CrmLang.t('admin.mt4_margin'), width: 130, templet: numberText},
                    {field: 'mt4_margin_free', title: CrmLang.t('admin.mt4_margin_free'), width: 150, templet: numberText},
                    {field: 'mt4_leverage', title: CrmLang.t('admin.mt4_leverage'), width: 120},
                    {field: 'mt4_registered_at', title: CrmLang.t('admin.mt4_registered_at'), width: 170, templet: timestampText},
                    {field: 'mt4_snapshot_at', title: CrmLang.t('admin.mt4_snapshot_at'), width: 170, templet: timestampText},
                    {field: 'total_orders', title: CrmLang.t('admin.total_orders'), width: 120},
                    {field: 'total_volume', title: CrmLang.t('admin.total_volume'), width: 120, templet: numberText},
                    {field: 'total_profit', title: CrmLang.t('admin.total_profit'), width: 120, templet: numberText},
                    {field: 'total_comm', title: CrmLang.t('admin.total_trade_commission'), width: 140, templet: numberText},
                    {field: 'total_swaps', title: CrmLang.t('admin.total_swaps'), width: 120, templet: numberText},
                    {field: 'total_noble_metal', title: CrmLang.t('admin.total_noble_metal'), width: 140, templet: numberText},
                    {field: 'total_for_exca', title: CrmLang.t('admin.total_for_exca'), width: 130, templet: numberText},
                    {field: 'total_crud_oil', title: CrmLang.t('admin.total_crud_oil'), width: 130, templet: numberText},
                    {field: 'total_index', title: CrmLang.t('admin.total_index'), width: 120, templet: numberText},
                    {field: 'total_currency', title: CrmLang.t('admin.total_currency'), width: 120, templet: numberText},
                    {field: 'total_stock', title: CrmLang.t('admin.total_stock'), width: 120, templet: numberText},
                    {fixed: 'right', title: CrmLang.t('common.action'), width: 280, templet: positionSummaryActionsText}
                ]],
                parseData: function(response) {
                    var wrapper = response && response.data ? response.data : {};
                    var records = wrapper.records || {};
                    updateSummaryCards(wrapper.summary || {});

                    return {
                        code: response && response.code === 1000 ? 0 : (response ? response.code : 500),
                        msg: response ? response.message : '',
                        count: records.total || 0,
                        data: records.data || []
                    };
                },
                done: function() {
                    CrmLang.switchUI();
                    renderPositionSummaryIcons();
                }
            }));

            document.getElementById('reloadPositionSummary').onclick = function() {
                table.reload('positionSummaryTable', {where: currentPositionSummaryFilters()});
            };

            $('#exportPositionSummary').on('click', function() {
                downloadAdminCsv($, layer, '/api/admin/exportPositionSummary', currentPositionSummaryFilters(), 'position_summary_export.csv');
            });

            form.on('submit(searchPositionSummary)', function(data) {
                // data.field：筛选表单参数，字段名与 PositionSummaryController::positionSummaryList 保持一致。
                table.reload('positionSummaryTable', {where: currentPositionSummaryFilters(), page: {curr: 1}});
                return false;
            });

            $('#resetPositionSummarySearch').on('click', function() {
                clearPositionSummaryDrilldown();
                window.setTimeout(function() {
                    table.reload('positionSummaryTable', {where: currentPositionSummaryFilters(), page: {curr: 1}});
                }, 0);
            });

            $('#resetPositionSummaryDrilldown').on('click', function() {
                clearPositionSummaryDrilldown();
                table.reload('positionSummaryTable', {where: currentPositionSummaryFilters(), page: {curr: 1}});
            });

            $positionSummaryChain.on('click', '[data-position-chain-reset]', function() {
                clickedChain = [];
                renderPositionSummaryChain();
            });

            table.on('tool(positionSummaryTable)', function(obj) {
                if (obj.event === 'positionSummaryDrilldown') {
                    // 用户ID 列被点击：先按点击层级更新链路，再执行原有的下级代理钻取。
                    updateClickedChain(obj.data || {});
                    positionSummaryDrilldown(obj.data || {});
                    return;
                }

                if (obj.event === 'positionSummaryChain') {
                    // 普通客户行的用户ID 只展开链路，不进入代理钻取模式。
                    updateClickedChain(obj.data || {});
                    return;
                }

                if (obj.event === 'positionSummaryTradeDetail') {
                    positionSummaryTradeDetail(obj.data || {});
                    return;
                }

                if (obj.event === 'positionSummaryRiskDetail') {
                    positionSummaryRiskDetail(obj.data || {});
                }
            });

            renderPositionSummaryIcons();

            /**
             * 读取持仓汇总当前筛选条件。
             *
             * @returns {Object} 返回要传给列表或导出接口的参数；searchtype/userPId 为空时不会出现在请求中。
             */
            function currentPositionSummaryFilters() {
                return serializeForm($, '#positionSummarySearchForm');
            }

            /**
             * 点击代理行后进入旧后台下级代理持仓汇总模式。
             *
             * @param {Object} row Layui 当前行，row.user_id 是被点击代理的业务用户 ID。
             * @returns {void} 成功时重载表格；缺少 user_id 时提示并终止，避免向后端提交空父代理。
             */
            function positionSummaryDrilldown(row) {
                if (!row.user_id) {
                    layer.msg(CrmLang.t('admin.user_id') + '不能为空', {icon: 0});
                    return;
                }

                var filters = $.extend({}, currentPositionSummaryFilters(), {
                    searchtype: 'subAgentsSearch',
                    userPId: row.user_id
                });

                $('#positionSummarySearchForm [name="searchtype"]').val(filters.searchtype);
                $('#positionSummarySearchForm [name="userPId"]').val(filters.userPId);
                updatePositionSummaryPath(row);
                table.reload('positionSummaryTable', {where: filters, page: {curr: 1}});
            }

            /**
             * 跳转到当前用户的交易明细页。
             *
             * @param {Object} row Layui 当前行，row.user_id 会作为交易订单页 user_id 默认筛选。
             * @returns {void} 成功时跳转到后台交易订单页；缺少用户 ID 时提示并终止。
             */
            function positionSummaryTradeDetail(row) {
                if (!row.user_id) {
                    layer.msg(CrmLang.t('admin.user_id') + '不能为空', {icon: 0});
                    return;
                }

                var filters = currentPositionSummaryFilters();
                var positionSummaryTradeDetailUrl = (window.crmRoute ? crmRoute('admin_page_trades') : '/admin/trades') + '?' + $.param({
                    user_id: row.user_id,
                    start_date: filters.start_date,
                    end_date: filters.end_date,
                    mode: 'all'
                });

                window.location.href = positionSummaryTradeDetailUrl;
            }

            /**
             * 跳转到当前业务用户的持仓风险视图。
             *
             * @param {Object} row Layui 当前行，row.user_id 是 CRM 业务用户 ID，不允许回退使用 MT4 登录号。
             * @returns {void} 成功时携带日期筛选进入风控中心；缺少业务用户 ID 时提示并终止。
             */
            function positionSummaryRiskDetail(row) {
                if (!row.user_id) {
                    layer.msg(CrmLang.t('admin.user_id') + '不能为空', {icon: 0});
                    return;
                }

                var filters = currentPositionSummaryFilters();
                var positionSummaryRiskDetailUrl = (window.crmRoute ? crmRoute('admin_page_risk') : '/admin/risk') + '?' + $.param({
                    user_id: row.user_id,
                    start_date: filters.start_date,
                    end_date: filters.end_date,
                    mode: 'positions'
                });

                window.location.href = positionSummaryRiskDetailUrl;
            }

            /**
             * 清空旧后台下级代理钻取参数。
             *
             * @returns {void} 隐藏路径提示，并恢复普通持仓汇总筛选模式。
             */
            function clearPositionSummaryDrilldown() {
                $('#positionSummarySearchForm [name="searchtype"]').val('');
                $('#positionSummarySearchForm [name="userPId"]').val('');
                $positionSummaryPath.attr('hidden', true);
                // 回到根级时链路一并收起，重新回到"默认不显示"的状态。
                clickedChain = [];
                renderPositionSummaryChain();
            }

            /**
             * 展示当前持仓汇总钻取路径。
             *
             * @param {Object} row 被点击的代理行，user_id 与 user_name 用于让后台知道当前父级是谁。
             * @returns {void} 更新路径文本并显示返回根级按钮。
             */
            function updatePositionSummaryPath(row) {
                var label = CrmLang.t('admin.position_summary') + ' / ' + escapePositionSummaryHtml(row.user_name || row.user_id);
                $positionSummaryPathLabel.html(label);
                $positionSummaryPath.removeAttr('hidden');
                renderPositionSummaryIcons();
            }

            /**
             * 更新汇总卡片。
             *
             * @param {Object} summary 后端返回的 summary 对象。
             * @returns {void}
             */
            function updateSummaryCards(summary) {
                summary = summary || {};
                Object.keys(summary).forEach(function(key) {
                    var element = document.querySelector('[data-summary-field="' + key + '"]');
                    if (!element) {
                        return;
                    }

                    element.innerText = key === 'total_accounts' || key === 'total_mt4_accounts' || key === 'total_orders'
                        ? (summary[key] || 0)
                        : formatNumber(summary[key]);
                });
            }

            /**
             * 渲染代理行用户 ID。
             *
             * 需求 12 说明：
             * - 每一行的用户ID 都是可点击入口；点击后链路才出现，并只展开到被点击的这一层。
             * - 代理账号继续触发 positionSummaryDrilldown（链路 + 下级代理钻取）。
             * - 普通客户触发 positionSummaryChain，只更新链路，不改变列表筛选模式。
             *
             * @param {Object} row Layui 当前行。
             * @returns {string} 可点击的用户ID 单元格 HTML；缺少用户ID 时返回占位符。
             */
            function userIdDrilldownText(row) {
                var userId = row.user_id || '';
                if (!userId) {
                    return '-';
                }

                if (parseInt(row.account_type, 10) !== 1) {
                    return [
                        '<button type="button" class="crm-chain-trigger" lay-event="positionSummaryChain">',
                        escapePositionSummaryHtml(userId),
                        '</button>'
                    ].join('');
                }

                return [
                    '<button type="button" class="layui-btn layui-btn-primary layui-btn-xs" lay-event="positionSummaryDrilldown">',
                    '<span data-lucide="git-branch"></span>',
                    escapePositionSummaryHtml(userId),
                    '</button>'
                ].join('');
            }

            /**
             * 按被点击的用户ID 更新链路。
             *
             * 渐进展开规则（需求 12）：
             * - 首次点击：链路只有被点击的这一个用户ID，即"只显示到当前点击的这一层"。
             * - 点击一个尚未在链路中的用户ID：追加一层，等于继续向下展开。
             * - 再次点击链路中已有的用户ID：截断到该位置，链路收回到那一层。
             * - 全过程只记录用户ID，不记录用户名，也不记录代理等级。
             *
             * @param {Object} row Layui 当前行，取 row.user_id 作为链路节点。
             * @returns {void}
             */
            function updateClickedChain(row) {
                var userId = String((row && row.user_id) || '').trim();
                var existingIndex;

                if (!userId) {
                    return;
                }

                existingIndex = clickedChain.indexOf(userId);
                if (existingIndex >= 0) {
                    clickedChain = clickedChain.slice(0, existingIndex + 1);
                } else {
                    clickedChain.push(userId);
                }

                renderPositionSummaryChain();
            }

            /**
             * 渲染链路区块。
             *
             * @returns {void} 链路为空时保持隐藏；有节点时显示，最后一个节点标记为当前层。
             */
            function renderPositionSummaryChain() {
                var html = '';
                var index;

                if (!$positionSummaryChain.length) {
                    return;
                }

                if (!clickedChain.length) {
                    $positionSummaryChainNodes.empty();
                    $positionSummaryChain.removeClass('is-visible').attr('hidden', 'hidden');
                    return;
                }

                for (index = 0; index < clickedChain.length; index++) {
                    if (index > 0) {
                        html += '<span class="crm-chain-arrow" aria-hidden="true">&gt;</span>';
                    }
                    html += '<span class="crm-chain-node' + (index === clickedChain.length - 1 ? ' is-current' : '') + '">'
                        + escapePositionSummaryHtml(clickedChain[index])
                        + '</span>';
                }

                $positionSummaryChainNodes.html(html);
                $positionSummaryChain.removeAttr('hidden').addClass('is-visible');
            }

            /**
             * 渲染持仓汇总行操作按钮。
             *
             * @param {Object} row 当前持仓汇总行；只要有业务 user_id 就能查看交易明细和持仓风险。
             * @returns {string} 两个 Lucide 行操作按钮；缺少业务用户 ID 时返回占位文本。
             */
            function positionSummaryActionsText(row) {
                if (!row.user_id) {
                    return '-';
                }

                return [
                    '<button type="button" class="layui-btn layui-btn-primary layui-btn-xs" lay-event="positionSummaryTradeDetail">',
                    '<span data-lucide="search"></span>',
                    escapePositionSummaryHtml(CrmLang.t('admin.trades')),
                    '</button>',
                    '<button type="button" class="layui-btn layui-btn-primary layui-btn-xs" lay-event="positionSummaryRiskDetail">',
                    '<span data-lucide="shield-alert"></span>',
                    escapePositionSummaryHtml(CrmLang.t('admin.risk_control')),
                    '</button>'
                ].join('');
            }

            /**
             * 数字字段格式化。
             *
             * @param {Object} row Layui 当前行对象。
             * @returns {string} 保留两位小数的数字文本。
             */
            function numberText(row) {
                return formatNumber(row[this.field]);
            }

            /**
             * MT4 快照时间格式化。
             *
             * @param {Object} row Layui 当前行对象。
             * @returns {string} 返回本地化时间文本；空值返回 -，表示该业务用户未关联 MT4 快照。
             */
            function timestampText(row) {
                var value = row[this.field];
                var date;

                if (!value) {
                    return '-';
                }

                date = new Date(parseInt(value, 10) * 1000);
                if (Number.isNaN(date.getTime())) {
                    return '-';
                }

                return date.toLocaleString();
            }

            /**
             * 账户类型展示文本。
             *
             * @param {Object} row Layui 当前行对象。
             * @returns {string} 代理商、普通客户或未知。
             */
            function accountTypeText(row) {
                if (parseInt(row.account_type, 10) === 1) {
                    return CrmLang.t('admin.account_type_agent');
                }
                if (parseInt(row.account_type, 10) === 2) {
                    return CrmLang.t('admin.account_type_customer');
                }
                return CrmLang.t('admin.account_type_unknown');
            }

            /**
             * 格式化普通数字。
             *
             * @param {number|string} value 后端返回的数字或数字字符串。
             * @returns {string} 保留两位小数的展示文本。
             */
            function formatNumber(value) {
                var numberValue = parseFloat(value || 0);
                if (Number.isNaN(numberValue)) {
                    numberValue = 0;
                }
                return numberValue.toFixed(2);
            }

            /**
             * 转义持仓汇总行内 HTML。
             *
             * @param {number|string} value 后端返回的用户 ID、用户名或其它短文本。
             * @returns {string} 安全展示用文本，防止行内按钮被异常字符破坏。
             */
            function escapePositionSummaryHtml(value) {
                return String(value === undefined || value === null ? '' : value).replace(/[&<>"']/g, function(char) {
                    return {
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#39;'
                    }[char];
                });
            }

            /**
             * 统一渲染 Lucide 图标。
             *
             * @returns {void} 如果布局未加载 Lucide，则保持原文本按钮可用。
             */
            function renderPositionSummaryIcons() {
                if (window.lucide && window.lucide.createIcons) {
                    window.lucide.createIcons();
                }
            }
        });
    });

    registry['productions/index'] = once(function () {
        // Source: productions/index.js
        layui.use(['table', 'form', 'layer', 'jquery'], function () {
            var table = layui.table;
            var form = layui.form;
            var layer = layui.layer;
            var $ = layui.jquery;
            var successCodes = {1000: true, 1001: true, 1002: true, 1003: true};

            CrmLang.switchUI();

            // 产品/交易品种表格：数据来自 /api/admin/productionList，后端按 permissions.api_route 做接口鉴权。
            table.render(CrmTable.layuiConfig('admin', {
                elem: '#productionTable',
                id: 'productionTable',
                url: '/api/admin/productionList',
                cols: [[
                    {field: 'symbol', title: CrmLang.t('admin.symbol'), width: 130},
                    {field: 'group_id', title: CrmLang.t('admin.group_id'), width: 100},
                    {field: 'bid', title: CrmLang.t('admin.bid'), width: 110},
                    {field: 'ask', title: CrmLang.t('admin.ask'), width: 110},
                    {field: 'spread', title: CrmLang.t('admin.spread'), width: 110},
                    {field: 'digits', title: CrmLang.t('admin.digits'), width: 100},
                    // 买入/卖出均价：对齐旧产量报表的 avg_buy_price / avg_sell_price 列。
                    // 均价与同方向手数成对展示，与旧后台的阅读顺序一致。
                    // 用 moneyText 而非 volumeText：均价是价格而非手数，不做 /100 手数换算。
                    {field: 'avg_buy_price', title: CrmLang.t('admin.avg_buy_price'), width: 140, templet: moneyText},
                    {field: 'total_buy_volume', title: CrmLang.t('admin.total_buy_volume'), width: 150, templet: volumeText},
                    {field: 'avg_sell_price', title: CrmLang.t('admin.avg_sell_price'), width: 140, templet: moneyText},
                    {field: 'total_sell_volume', title: CrmLang.t('admin.total_sell_volume'), width: 150, templet: volumeText},
                    {field: 'net_volume', title: CrmLang.t('admin.net_volume'), width: 130, templet: volumeText},
                    {field: 'float_profit_loss', title: CrmLang.t('admin.float_profit_loss'), width: 150, templet: moneyText},
                    {
                        field: 'status',
                        title: CrmLang.t('admin.status'),
                        width: 110,
                        templet: function (row) {
                            return parseInt(row.status, 10) === 1 ? CrmLang.t('admin.enabled') : CrmLang.t('admin.disabled');
                        }
                    },
                    {field: 'modify_time', title: CrmLang.t('admin.modify_time'), width: 170},
                    {fixed: 'right', title: CrmLang.t('common.action'), toolbar: '#productionActions', width: 150}
                ]],
                parseData: function (response) {
                    var data = response && response.data ? response.data : {};
                    var records = data.records || {};
                    updateSummaryCards(data.summary || {});

                    return {
                        code: response && response.code === 1000 ? 0 : (response ? response.code : 500),
                        msg: response ? response.message : '',
                        count: records.total || 0,
                        data: records.data || []
                    };
                },
                done: function () {
                    CrmLang.switchUI();
                }
            }));

            document.getElementById('reloadProduction').onclick = function () {
                table.reload('productionTable');
            };

            $('#exportProductions').on('click', function () {
                downloadAdminCsv($, layer, '/api/admin/exportProductions', serializeForm($, '#productionSearchForm'), 'productions_export.csv');
            });

            form.on('submit(searchProduction)', function (data) {
                // data.field：筛选字段集合，字段名与 ProductionController::productionList 参数保持一致。
                table.reload('productionTable', {where: data.field, page: {curr: 1}});
                return false;
            });

            document.getElementById('openProductionCreate').onclick = function () {
                form.val('productionForm', {
                    id: '',
                    symbol: '',
                    bid: '',
                    ask: '',
                    low: '',
                    high: '',
                    digits: 2,
                    spread: '',
                    group_id: 0,
                    status: 1
                });
                openProductionForm(CrmLang.t('common.create'));
            };

            table.on('tool(productionTable)', function (obj) {
                if (obj.event === 'edit') {
                    form.val('productionForm', {
                        id: obj.data.id,
                        symbol: obj.data.symbol,
                        bid: obj.data.bid,
                        ask: obj.data.ask,
                        low: obj.data.low,
                        high: obj.data.high,
                        digits: obj.data.digits,
                        spread: obj.data.spread,
                        group_id: obj.data.group_id,
                        status: obj.data.status
                    });
                    openProductionForm(CrmLang.t('common.edit'));
                    return;
                }

                if (obj.event === 'delete') {
                    layer.confirm(CrmLang.t('common.delete'), function (index) {
                        layer.close(index);
                        CrmAjax.request({
                            guard: 'admin',
                            url: '/api/admin/deleteProduction/' + encodeURIComponent(obj.data.id),
                            success: function (res) {
                                if (successCodes[res.code]) {
                                    table.reload('productionTable');
                                    layer.msg(res.message || CrmLang.t('common.success'), {icon: 1});
                                    return;
                                }
                                layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                            }
                        });
                    });
                }
            });

            form.on('submit(submitProduction)', function (data) {
                var id = data.field.id;
                CrmAjax.request({
                    guard: 'admin',
                    url: id ? '/api/admin/updateProduction/' + encodeURIComponent(id) : '/api/admin/createProduction',
                    data: data.field,
                    success: function (res) {
                        if (successCodes[res.code]) {
                            layer.closeAll();
                            table.reload('productionTable');
                            layer.msg(res.message || CrmLang.t('common.success'), {icon: 1});
                            return;
                        }
                        layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                    }
                });
                return false;
            });

            function openProductionForm(title) {
                layer.open({
                    type: 1,
                    title: title,
                    area: ['720px', '560px'],
                    content: $('#productionFormModal')
                });
                form.render();
            }

            /**
             * 刷新页面顶部产品汇总卡片。
             *
             * @param {Object} summary 后端返回的汇总对象，包含 total_symbols、total_net_volume、total_float_profit_loss。
             * @returns {void}
             */
            function updateSummaryCards(summary) {
                summary = summary || {};
                Object.keys(summary).forEach(function (key) {
                    var element = document.querySelector('[data-summary-field="' + key + '"]');
                    if (!element) {
                        return;
                    }

                    element.innerText = key === 'total_symbols' ? (summary[key] || 0) : Number(summary[key] || 0).toFixed(2);
                });
            }

            /**
             * 格式化手数字段。
             *
             * @param {Object} row 当前表格行数据。
             * @returns {string} 保留两位小数的手数字符串。
             */
            function volumeText(row) {
                return Number(row[this.field] || 0).toFixed(2);
            }

            /**
             * 格式化金额字段。
             *
             * @param {Object} row 当前表格行数据。
             * @returns {string} 保留两位小数的金额字符串。
             */
            function moneyText(row) {
                return Number(row[this.field] || 0).toFixed(2);
            }
        });
    });

    registry['profile/change-password'] = once(function () {
        // Source: profile/change-password.js
        layui.use(['form', 'layer'], function() {
            var form = layui.form, layer = layui.layer, $ = layui.jquery;

            form.on('submit(changePassword)', function(data) {
                if (data.field.new_password !== data.field.confirm_password) {
                    layer.msg(CrmLang.t('register.passwordMismatch'), {icon: 2});
                    return false;
                }

                CrmAjax.request({
                    guard: 'admin',
                    url: '/api/admin/changePassword',
                    data: data.field,
                    success: function(res) {
                        if (res.code === 1000) {
                            layer.msg(CrmLang.t('profile.passwordChanged'), {icon: 1});
                            CrmAjax.removeToken('admin');
                            setTimeout(function() {
                                window.location.href = crmRoute('admin_page_login');
                            }, 1000);
                        } else {
                            layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                        }
                    }
                });
                return false;
            });
        });
    });

    registry['profile/edit'] = once(function () {
        // Source: profile/edit.js
        layui.use(['form', 'layer'], function() {
            var form = layui.form, layer = layui.layer, $ = layui.jquery;

            // 当前管理员资料读取 /api/admin/profileInfo，保存提交到 /api/admin/updateProfile。
            // 当前登录管理员由 admin guard 的 JWT 决定；username 表示管理员登录名，只读展示。
            // email 表示管理员邮箱，mobile 表示管理员手机号，本页面只允许更新 email 和 mobile。
            // 当前管理员资料：/api/admin/profileInfo 读取当前登录管理员，返回 username、email、mobile 等 admins 表字段。
            CrmAjax.request({
                guard: 'admin',
                url: '/api/admin/profileInfo',
                success: function(res) {
                    if (res.code === 1000) {
                        form.val('profileForm', res.data);
                    }
                }
            });

            // /api/admin/updateProfile 只允许更新 email 和 mobile；username 表示管理员登录名，页面只读不提交修改。
            form.on('submit(updateProfile)', function(data) {
                // email 表示管理员邮箱；mobile 表示管理员手机号，后端会再次校验格式和长度。
                CrmAjax.request({
                    guard: 'admin',
                    url: '/api/admin/updateProfile',
                    data: data.field,
                    success: function(res) {
                        if (res.code === 1000) {
                            layer.msg(CrmLang.t('common.success'), {icon: 1});
                        } else {
                            layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                        }
                    }
                });
                return false;
            });
        });
    });

    registry['realtime-commissions/index'] = once(function () {
        // Source: realtime-commissions/index.js
        layui.use(['table', 'form', 'laydate', 'layer', 'jquery'], function() {
            var table = layui.table;
            var form = layui.form;
            var laydate = layui.laydate;
            var layer = layui.layer;
            var $ = layui.jquery;

            // 需求 15 的前端侧优化点：
            // - 每页条数固定在受控档位（最大 100），Layui 不再有机会一次渲染上千个 DOM 行。
            // - 搜索走防抖，连续输入或连点搜索只发最后一次请求。
            // - 统计图表请求可被 abort，切换筛选条件时旧请求立刻取消，避免过期响应覆盖新数据。
            // - 汇总与多语言刷新都限定在自己的容器内，不再对整个文档做选择器扫描。
            var SEARCH_DEBOUNCE_MS = 300;
            var $summaryBlock = $('#realtimeCommissionSummary');
            var $chartPanel = $('#realtimeCommissionCharts');
            var searchDebounceTimer = null;
            var statisticsRequest = null;
            var chartInstances = {};
            var chartTypes = {};
            var chartData = {labels: [], records: [], profit: [], sources: []};
            var chartsLoaded = false;

            CrmLang.switchUI();

            // start_date/end_date：按返佣确认时间过滤，后端优先使用 mt4_trades.modify_time，缺失时回退到 close_time。
            laydate.render({elem: '#realtimeStartDate', type: 'date'});
            laydate.render({elem: '#realtimeEndDate', type: 'date'});

            table.render(CrmTable.layuiConfig('admin', {
                elem: '#realtimeCommissionTable',
                id: 'realtimeCommissionTable',
                url: '/api/admin/realtimeCommissionList',
                // limit/limits 与后端 MAX_PER_PAGE 对齐：服务端会把超限值收敛到 100，
                // 这里同步收敛可选档位，防止用户在分页条上选出一个会被截断的值。
                limit: 15,
                limits: [15, 30, 50, 100],
                cols: [[
                    {field: 'id', title: 'ID', width: 90, sort: true},
                    {field: 'ticket', title: CrmLang.t('admin.ticket'), width: 130},
                    {field: 'login', title: CrmLang.t('admin.mt4_login'), width: 130},
                    {field: 'symbol', title: CrmLang.t('admin.symbol'), width: 130},
                    {field: 'cmd', title: CrmLang.t('admin.trade_cmd'), width: 110},
                    {field: 'volume', title: CrmLang.t('admin.volume'), width: 110},
                    {field: 'profit', title: CrmLang.t('admin.profit'), width: 130},
                    {field: 'commission', title: CrmLang.t('admin.total_trade_commission'), width: 150},
                    {field: 'swaps', title: CrmLang.t('admin.swaps'), width: 120},
                    {field: 'rebate_source_name', title: CrmLang.t('admin.rebate_source_name'), width: 130},
                    {field: 'comment', title: CrmLang.t('admin.comment'), minWidth: 220},
                    {
                        field: 'modify_time',
                        title: CrmLang.t('admin.modify_time'),
                        width: 170,
                        templet: function(row) {
                            return formatTimestamp(row.modify_time || row.close_time);
                        }
                    }
                ]],
                parseData: function(res) {
                    var payload = res.data || {};
                    var records = payload.records || {};

                    // summary：后端基于同一筛选条件返回的汇总信息，用于独立统计区块展示。
                    renderSummary(payload.summary || {});

                    return {
                        code: res.code === 1000 ? 0 : res.code,
                        msg: res.message || '',
                        count: records.total || 0,
                        data: records.data || []
                    };
                },
                done: function() {
                    // 只刷新统计区块内的文案，不再对整个文档跑 [data-translate] 选择器。
                    refreshSummaryTranslations();
                    // 图表未展开时不请求统计接口；展开后筛选条件变化才重新拉取。
                    if (chartsLoaded) {
                        loadStatistics();
                    }
                }
            }));

            form.on('submit(searchRealtimeCommissions)', function() {
                scheduleSearch();
                return false;
            });

            $('#realtimeCommissionSearchForm').on('reset', function() {
                // reset 后表单值要等浏览器清空完成才能读取，所以延后一帧再触发查询。
                window.setTimeout(scheduleSearch, 0);
            });

            $('#exportRealtimeCommissions').on('click', function() {
                downloadAdminCsv($, layer, '/api/admin/exportRealtimeCommissions', currentRealtimeFilters(), 'realtime_commissions_export.csv');
            });

            bindChartPanel();

            /**
             * 读取实时返佣当前筛选条件。
             *
             * @returns {Object} 列表、导出和统计图表共用的筛选参数。
             */
            function currentRealtimeFilters() {
                return serializeForm($, '#realtimeCommissionSearchForm');
            }

            /**
             * 防抖触发表格重载。
             *
             * 逻辑说明：
             * - 连续提交（快速回车、连点搜索按钮、reset 后的自动查询）只会保留最后一次。
             * - 未防抖时每次按键或点击都会打一次全表查询，是页面卡死的主要外因之一。
             *
             * @returns {void}
             */
            function scheduleSearch() {
                if (searchDebounceTimer) {
                    window.clearTimeout(searchDebounceTimer);
                }

                searchDebounceTimer = window.setTimeout(function() {
                    searchDebounceTimer = null;
                    table.reload('realtimeCommissionTable', {where: currentRealtimeFilters(), page: {curr: 1}});
                }, SEARCH_DEBOUNCE_MS);
            }

            /**
             * 渲染实时返佣汇总指标。
             *
             * 逻辑说明：
             * - 选择器限定在 #realtimeCommissionSummary 区块内，避免全文档扫描造成的额外布局开销。
             *
             * @param {Object} summary 后端返回的汇总对象，包含 total_records、total_profit、total_commission。
             * @returns {void}
             */
            function renderSummary(summary) {
                $summaryBlock.find('[data-summary-field]').each(function() {
                    var field = $(this).data('summary-field');
                    var value = summary[field];
                    $(this).text(value === undefined || value === null ? '0' : value);
                });
            }

            /**
             * 只刷新统计区块内的多语言文案。
             *
             * @returns {void}
             */
            function refreshSummaryTranslations() {
                $summaryBlock.find('[data-translate]').each(function() {
                    $(this).text(CrmLang.t($(this).attr('data-translate')));
                });
            }

            /**
             * 绑定统计图表折叠面板与图表类型切换。
             *
             * 无障碍说明：
             * - 折叠开关是原生 button，Enter/Space 由浏览器默认行为提供，无需手写键盘事件。
             * - aria-expanded 与视觉状态同步；展开动画完全由 CSS 过渡负责。
             *
             * @returns {void}
             */
            function bindChartPanel() {
                if (!$chartPanel.length) {
                    return;
                }

                $chartPanel.on('click', '[data-realtime-chart-toggle]', function() {
                    var expanded = $(this).attr('aria-expanded') === 'true';

                    setChartPanelExpanded(!expanded);
                });

                $chartPanel.on('click', '.crm-chart-type', function() {
                    var $button = $(this);
                    var target = $button.data('chart-target');

                    $button.closest('.crm-chart-controls').find('.crm-chart-type')
                        .removeClass('is-active').attr('aria-pressed', 'false');
                    $button.addClass('is-active').attr('aria-pressed', 'true');

                    chartTypes[target] = String($button.data('chart-type') || 'bar');
                    renderChart(target);
                });

                $chartPanel.find('.crm-chart-type.is-active').each(function() {
                    chartTypes[$(this).data('chart-target')] = String($(this).data('chart-type') || 'bar');
                });

                window.addEventListener('resize', scheduleChartResize);
                window.addEventListener('crm:theme-change', scheduleChartResize);
            }

            /**
             * 切换统计图表容器展开状态。
             *
             * @param {boolean} expanded true 表示展开。
             * @returns {void}
             */
            function setChartPanelExpanded(expanded) {
                var $toggle = $chartPanel.find('[data-realtime-chart-toggle]');
                var labelKey = expanded ? 'admin.collapse_statistics' : 'admin.expand_statistics';

                $chartPanel.toggleClass('is-open', expanded);
                $toggle.attr('aria-expanded', expanded ? 'true' : 'false')
                    .attr('title', CrmLang.t(labelKey));
                $toggle.find('[data-realtime-chart-toggle-label]')
                    .attr('data-translate', labelKey)
                    .text(CrmLang.t(labelKey));

                if (!expanded) {
                    return;
                }

                // 首次展开才初始化 ECharts 并请求数据：折叠状态下不产生任何图表开销。
                if (!chartsLoaded) {
                    chartsLoaded = true;
                    loadStatistics();
                    return;
                }

                scheduleChartResize();
            }

            /**
             * 拉取实时返佣统计数据。
             *
             * 逻辑说明：
             * - 每次请求前 abort 掉未完成的上一次请求，保证只有最新筛选条件的响应会被渲染。
             *
             * @returns {void}
             */
            function loadStatistics() {
                if (statisticsRequest && statisticsRequest.abort) {
                    statisticsRequest.abort();
                }

                statisticsRequest = CrmAjax.request({
                    guard: 'admin',
                    url: '/api/admin/realtimeCommissionStatistics',
                    data: currentRealtimeFilters(),
                    success: function(res) {
                        var payload = (res && res.data) || {};

                        chartData = {
                            labels: payload.labels || [],
                            records: payload.records || [],
                            profit: payload.profit || [],
                            sources: payload.sources || []
                        };
                        renderChart('rebateRecordsChart');
                        renderChart('rebateProfitChart');
                        renderChart('rebateSourceChart');
                    }
                });
            }

            /**
             * 渲染指定统计图表。
             *
             * @param {string} target 图表容器 DOM id。
             * @returns {void}
             */
            function renderChart(target) {
                var container = document.getElementById(target);
                var type = chartTypes[target] || 'bar';
                var instance;

                if (!container || typeof echarts === 'undefined') {
                    return;
                }
                // 折叠状态下容器宽高为 0，ECharts 无法正确计算尺寸，等展开后再渲染。
                if (!container.offsetWidth) {
                    return;
                }

                instance = chartInstances[target] || echarts.init(container);
                chartInstances[target] = instance;
                instance.setOption(chartOption(target, type), true);
            }

            /**
             * 生成 ECharts 配置。
             *
             * @param {string} target 图表容器 DOM id。
             * @param {string} type 图表类型，支持 bar/line/area/pie。
             * @returns {Object} ECharts option。
             */
            function chartOption(target, type) {
                var seriesName;
                var labels;
                var values;

                if (target === 'rebateSourceChart') {
                    seriesName = CrmLang.t('admin.rebate_source_distribution');
                    labels = chartData.sources.map(function(item) {
                        return String((item && item.name) || '');
                    });
                    values = chartData.sources.map(function(item) {
                        return Number((item && item.profit) || 0);
                    });
                } else if (target === 'rebateProfitChart') {
                    seriesName = CrmLang.t('admin.rebate_daily_profit');
                    labels = chartData.labels;
                    values = chartData.profit;
                } else {
                    seriesName = CrmLang.t('admin.rebate_daily_records');
                    labels = chartData.labels;
                    values = chartData.records;
                }

                if (type === 'pie') {
                    return {
                        tooltip: {trigger: 'item'},
                        legend: {bottom: 0, type: 'scroll'},
                        series: [{
                            name: seriesName,
                            type: 'pie',
                            radius: ['38%', '66%'],
                            data: labels.map(function(label, index) {
                                return {name: label, value: Number(values[index] || 0)};
                            })
                        }]
                    };
                }

                return {
                    tooltip: {trigger: 'axis'},
                    grid: {left: 48, right: 16, top: 24, bottom: 32},
                    xAxis: {type: 'category', data: labels, boundaryGap: type === 'bar'},
                    yAxis: {type: 'value'},
                    series: [{
                        name: seriesName,
                        type: type === 'bar' ? 'bar' : 'line',
                        smooth: type !== 'bar',
                        areaStyle: type === 'area' ? {} : null,
                        data: values
                    }]
                };
            }

            /**
             * 合并窗口尺寸与皮肤变化引起的图表重绘。
             *
             * 逻辑说明：
             * - resize 事件会高频触发，直接同步 resize 三个图表会造成明显的布局抖动，
             *   这里用 requestAnimationFrame 合并到下一帧只做一次。
             *
             * @returns {void}
             */
            function scheduleChartResize() {
                if (scheduleChartResize.pending) {
                    return;
                }

                scheduleChartResize.pending = true;
                window.requestAnimationFrame(function() {
                    scheduleChartResize.pending = false;
                    Object.keys(chartInstances).forEach(function(key) {
                        if (chartInstances[key]) {
                            chartInstances[key].resize();
                        }
                    });
                    renderChart('rebateRecordsChart');
                    renderChart('rebateProfitChart');
                    renderChart('rebateSourceChart');
                });
            }

            /**
             * 将 10 位时间戳格式化为可读时间。
             *
             * @param {number|string} value mt4_trades.close_time 字段值，单位为秒。
             * @returns {string} 格式化后的本地时间字符串，空值返回横线。
             */
            function formatTimestamp(value) {
                var timestamp = parseInt(value, 10);
                if (!timestamp) {
                    return '-';
                }

                var date = new Date(timestamp * 1000);
                var pad = function(num) {
                    return num < 10 ? '0' + num : String(num);
                };

                return date.getFullYear() + '-' +
                    pad(date.getMonth() + 1) + '-' +
                    pad(date.getDate()) + ' ' +
                    pad(date.getHours()) + ':' +
                    pad(date.getMinutes()) + ':' +
                    pad(date.getSeconds());
            }
        });
    });

    registry['rights-summary/index'] = once(function () {
        // Source: rights-summary/index.js
        layui.use(['table', 'form', 'layer', 'jquery'], function() {
            var table = layui.table;
            var form = layui.form;
            var layer = layui.layer;
            var $ = layui.jquery;
            var successCodes = {1000: true, 1001: true, 1002: true};

            CrmLang.switchUI();

            // 表格数据来自 /api/admin/rightsSummaryList；后端会按 permissions.api_route 鉴权并按 AdminDataScopeService 过滤可见用户。
            table.render(CrmTable.layuiConfig('admin', {
                elem: '#rightsSummaryTable',
                id: 'rightsSummaryTable',
                url: '/api/admin/rightsSummaryList',
                cols: [[
                    {field: 'user_id', title: CrmLang.t('admin.userId'), width: 120},
                    {field: 'user_name', title: CrmLang.t('admin.user_name'), width: 160},
                    {field: 'login', title: CrmLang.t('admin.mt4_login'), width: 130},
                    {field: 'name', title: CrmLang.t('admin.mt4_name'), width: 150},
                    {field: 'group', title: CrmLang.t('admin.mt4_group'), width: 150},
                    {field: 'balance', title: CrmLang.t('admin.balance'), width: 130},
                    {field: 'equity', title: CrmLang.t('admin.equity'), width: 130},
                    {field: 'margin', title: CrmLang.t('admin.margin'), width: 130},
                    {field: 'margin_free', title: CrmLang.t('admin.margin_free'), width: 150},
                    {field: 'leverage', title: CrmLang.t('admin.leverage'), width: 100},
                    {field: 'settlement_amount', title: CrmLang.t('admin.settlement_amount'), width: 140, templet: settlementAmountText},
                    {field: 'settlement_status', title: CrmLang.t('admin.settlement_status'), width: 130, templet: settlementStatusText},
                    {field: 'updated_at', title: CrmLang.t('admin.updatedAt'), width: 170},
                    {fixed: 'right', title: CrmLang.t('common.action'), toolbar: '#rightsSummaryActions', width: 120}
                ]],
                parseData: function(response) {
                    // response.data.summary：当前筛选条件下的聚合结果，用于页面顶部统计卡片。
                    updateSummaryCards(response && response.data ? response.data.summary : {});

                    // response.data.records：Laravel paginator，CrmTable 默认解析器无法处理额外 summary 包装，所以这里显式展开。
                    var records = response && response.data && response.data.records ? response.data.records : {};
                    return {
                        code: response && response.code === 1000 ? 0 : (response ? response.code : 500),
                        msg: response ? response.message : '',
                        count: records.total || 0,
                        data: records.data || []
                    };
                },
                done: function() {
                    CrmLang.switchUI();
                    refreshPermissions();
                }
            }));

            document.getElementById('reloadRightsSummary').onclick = function() {
                table.reload('rightsSummaryTable');
            };

            $('#exportRightsSummary').on('click', function() {
                downloadAdminCsv($, layer, '/api/admin/exportRightsSummary', serializeForm($, '#rightsSummarySearchForm'), 'rights_summary_export.csv');
            });

            form.on('submit(searchRightsSummary)', function(data) {
                // data.field：筛选表单字段，字段名与后端 rightsSummaryList 参数保持一致。
                table.reload('rightsSummaryTable', {where: data.field, page: {curr: 1}});
                return false;
            });

            table.on('tool(rightsSummaryTable)', function(obj) {
                if (obj.event !== 'manualConfirmRightsSettlement') {
                    return;
                }

                // obj.data.settlement_id：rights_settlements 主键；为空说明当前 MT4 账号尚无可确认的权益结算记录。
                if (!obj.data.settlement_id) {
                    layer.msg(CrmLang.t('admin.rights_settlement_not_found'), {icon: 2});
                    return;
                }

                form.val('rightsManualConfirmForm', {
                    settlement_id: obj.data.settlement_id,
                    manual_confirm_reason: obj.data.settlement_remark || ''
                });

                layer.open({
                    type: 1,
                    title: CrmLang.t('admin.manual_confirm_rights_settlement'),
                    area: ['560px', '360px'],
                    content: $('#rightsManualConfirmModal')
                });
                form.render();
            });

            form.on('submit(submitRightsManualConfirm)', function(data) {
                // data.field.settlement_id：待确认权益结算记录 ID；manual_confirm_reason：人工确认原因。
                CrmAjax.request({
                    guard: 'admin',
                    url: '/api/admin/manualConfirmRightsSettlement/' + encodeURIComponent(data.field.settlement_id),
                    data: {
                        manual_confirm_reason: data.field.manual_confirm_reason
                    },
                    success: function(res) {
                        if (successCodes[res.code]) {
                            layer.closeAll();
                            table.reload('rightsSummaryTable');
                            layer.msg(res.message || CrmLang.t('common.success'), {icon: 1});
                            return;
                        }
                        layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                    }
                });

                return false;
            });

            /**
             * 格式化权益结算金额。
             *
             * @param {Object} row 表格行数据；settlement_amount 来源于 rights_settlements.amount。
             * @returns {string} 金额文本；无结算记录时显示占位符。
             */
            function settlementAmountText(row) {
                if (row.settlement_amount === null || row.settlement_amount === undefined || row.settlement_amount === '') {
                    return '-';
                }
                return formatMoney(row.settlement_amount);
            }

            /**
             * 格式化权益结算状态。
             *
             * @param {Object} row 表格行数据；settlement_status 为 0=待处理、1=已确认。
             * @returns {string} 多语言状态文本。
             */
            function settlementStatusText(row) {
                var value = Number(row.settlement_status);
                if (!row.settlement_id) {
                    return CrmLang.t('admin.no_settlement_record');
                }
                if (value === 1) {
                    return CrmLang.t('admin.settlement_confirmed');
                }
                return CrmLang.t('admin.settlement_pending');
            }

            /**
             * 刷新权益汇总卡片。
             *
             * @param {Object} summary 后端返回的聚合数据；键名包括账户权益字段和在线入金、出金、返佣、净结算金额。
             * @returns {void}
             */
            function updateSummaryCards(summary) {
                summary = summary || {};
                Object.keys(summary).forEach(function(key) {
                    var element = document.querySelector('[data-summary-field="' + key + '"]');
                    if (!element) {
                        return;
                    }
                    element.innerText = key === 'total_accounts' ? (summary[key] || 0) : formatMoney(summary[key]);
                });
            }

            /**
             * 格式化金额字段。
             *
             * @param {number|string} value 金额数值；为空时按 0 处理。
             * @returns {string} 保留两位小数的金额字符串。
             */
            function formatMoney(value) {
                var numberValue = parseFloat(value || 0);
                if (Number.isNaN(numberValue)) {
                    numberValue = 0;
                }
                return numberValue.toFixed(2);
            }

            /**
             * 表格刷新后重新执行按钮权限显示控制。
             *
             * @returns {void}
             */
            function refreshPermissions() {
                if (window.CrmAdminPermissions && window.CrmAdminPermissions.refresh) {
                    window.CrmAdminPermissions.refresh();
                }
            }
        });
    });

    registry['risk/index'] = once(function () {
        // Source: risk/index.js
        layui.use(['table', 'form', 'layer', 'jquery'], function() {
            var table = layui.table;
            var form = layui.form;
            var layer = layui.layer;
            var $ = layui.jquery;
            var currentMode = 'positions';
            var currentRiskIp = '';
            var renderedRiskTables = {};
            var $riskPageMarker = $('[data-layui-page="risk/index"]').first();
            var fixedMode = currentFixedRiskMode();

            CrmLang.switchUI();
            applyDefaultRiskQueryFilters();
            var defaultMode = currentRiskMode();
            renderRiskIcons();

            function refreshPermissions() {
                // Layui 表格重载会重新生成操作列按钮，必须重新按 permissions.slug 隐藏无权限按钮。
                if (window.CrmAdminPermissions && window.CrmAdminPermissions.refresh) {
                    window.CrmAdminPermissions.refresh();
                }
            }

            /**
             * 数字格式化。
             *
             * @param {number|string} value 后端返回的数字或数字字符串。
             * @returns {string} 保留两位小数的展示文本。
             */
            function formatNumber(value) {
                var numberValue = parseFloat(value || 0);
                if (Number.isNaN(numberValue)) {
                    numberValue = 0;
                }
                return numberValue.toFixed(2);
            }

            /**
             * 更新风控汇总卡片。
             *
             * @param {Object} summary 后端返回的 summary 对象，包含 total_records、total_profit、total_volume、total_margin、total_risk_value。
             * @returns {void}
             */
            function updateRiskSummaryCards(summary) {
                summary = summary || {};
                Object.keys(summary).forEach(function(key) {
                    var element = document.querySelector('[data-summary-field="' + key + '"]');
                    if (!element) {
                        return;
                    }

                    element.innerText = key === 'total_records' ? (summary[key] || 0) : formatNumber(summary[key]);
                });
            }

            function clearRiskSummaryCards() {
                updateRiskSummaryCards({
                    total_records: 0,
                    total_profit: 0,
                    total_volume: 0,
                    total_risk_value: 0,
                    total_margin: 0
                });
            }

            /**
             * 解析风控接口统一响应。
             *
             * response.data.records：Laravel 分页数据，用于 Layui 表格。
             * response.data.summary：当前筛选条件下的汇总数据，用于顶部卡片。
             *
             * @param {Object} response 后端统一响应对象。
             * @returns {Object} Layui table 需要的标准结构。
             */
            function parseRiskResponse(response, responseMode) {
                var wrapper = response && response.data ? response.data : {};
                var records = wrapper.records || {};
                if (responseMode && responseMode === currentMode) {
                    updateRiskSummaryCards(wrapper.summary || {});
                }

                return {
                    code: response && response.code === 1000 ? 0 : (response ? response.code : 5000),
                    msg: response ? response.message : '',
                    count: records.total || 0,
                    data: records.data || []
                };
            }

            /**
             * 渲染按业务用户聚合的盈利风险表。
             *
             * @param {Object=} where 当前共享筛选表单参数。
             * @returns {void}
             */
            function renderProfitRiskTable(where) {
                table.render(CrmTable.layuiConfig('admin', {
                    elem: '#profitRiskTable',
                    id: 'profitRiskTable',
                    url: '/api/admin/riskProfitUsers',
                    where: where || {},
                    cols: [[
                        {field: 'user_id', title: CrmLang.t('admin.userId'), width: 120},
                        {field: 'user_name', title: CrmLang.t('admin.user_name'), minWidth: 160},
                        {field: 'mt4_login', title: CrmLang.t('admin.mt4_login'), width: 120},
                        {field: 'mt4_name', title: CrmLang.t('admin.mt4_name'), minWidth: 150},
                        {field: 'mt4_balance', title: CrmLang.t('admin.mt4_balance'), width: 130, templet: numberText},
                        {field: 'mt4_equity', title: CrmLang.t('admin.mt4_equity'), width: 130, templet: numberText},
                        {field: 'total_comm', title: CrmLang.t('admin.total_commission'), width: 130, templet: numberText},
                        {field: 'total_volume', title: CrmLang.t('admin.total_volume'), width: 120, templet: numberText},
                        {field: 'total_swaps', title: CrmLang.t('admin.total_swaps'), width: 120, templet: numberText},
                        {field: 'total_profit', title: CrmLang.t('admin.total_profit'), width: 130, templet: numberText},
                        {field: 'total_net_worth', title: CrmLang.t('admin.total_net_worth'), width: 130, templet: numberText},
                        {field: 'feng_xian_val', title: CrmLang.t('admin.feng_xian_val'), width: 130, templet: numberText},
                        {field: 'mt4_regdate', title: CrmLang.t('admin.mt4_regdate'), width: 160}
                    ]],
                    parseData: function(response) {
                        return parseRiskResponse(response, 'profit');
                    },
                    done: function() {
                        CrmLang.switchUI();
                        renderRiskIcons();
                    }
                }));
            }

            /**
             * 渲染当前持仓风险表。
             *
             * 字段逻辑说明：
             * - user_id：CRM 业务用户 ID，用于权限范围和业务筛选。
             * - login：由 user_infos.mt4_code 映射出的真实 MT4 登录账号。
             * - ticket：MT4 订单号。
             * - risk_value：第一阶段按 profit - abs(commission) 计算的风险收益值。
             */
            function renderPositionsTable(where) {
                table.render(CrmTable.layuiConfig('admin', {
                elem: '#riskTable',
                id: 'riskTable',
                url: '/api/admin/riskPositions',
                where: where || {},
                cols: [[
                    {field: 'id', title: 'ID', width: 90, sort: true},
                    {field: 'user_id', title: CrmLang.t('admin.userId'), width: 120},
                    {field: 'login', title: CrmLang.t('admin.mt4_login'), width: 120},
                    {field: 'user_name', title: CrmLang.t('admin.user_name'), width: 140},
                    {field: 'ticket', title: CrmLang.t('admin.ticket'), width: 140},
                    {field: 'symbol', title: CrmLang.t('admin.symbol'), width: 120},
                    {field: 'volume', title: CrmLang.t('admin.volume'), width: 110, templet: numberText},
                    {field: 'commission', title: CrmLang.t('admin.commission'), width: 120, templet: numberText},
                    {field: 'profit', title: CrmLang.t('admin.profit'), width: 120, templet: numberText},
                    {field: 'risk_value', title: CrmLang.t('admin.risk_value'), width: 130, templet: numberText},
                    {field: 'open_time', title: CrmLang.t('admin.openTime'), width: 150},
                    {fixed: 'right', title: CrmLang.t('common.action'), toolbar: '#riskActions', width: 120}
                ]],
                parseData: function(response) {
                    return parseRiskResponse(response, 'positions');
                },
                done: function() {
                    CrmLang.switchUI();
                    refreshPermissions();
                    renderRiskIcons();
                }
                }));
            }

            /**
             * 渲染追保预警表。
             *
             * 字段逻辑说明：
             * - margin_level：由后端按 mt4_users.equity / mt4_users.margin * 100 计算。
             * - max_margin_level：筛选表单中的阈值，默认 100。
             */
            function renderMarginCallTable(where) {
                table.render(CrmTable.layuiConfig('admin', {
                elem: '#marginCallTable',
                id: 'marginCallTable',
                url: '/api/admin/riskMarginCalls',
                where: where || {},
                cols: [[
                    {field: 'login', title: CrmLang.t('admin.mt4_login'), width: 120},
                    {field: 'user_id', title: CrmLang.t('admin.userId'), width: 120},
                    {field: 'user_name', title: CrmLang.t('admin.user_name'), width: 140},
                    {field: 'group', title: CrmLang.t('admin.mt4_group'), width: 140},
                    {field: 'balance', title: CrmLang.t('admin.balance'), width: 120, templet: numberText},
                    {field: 'equity', title: CrmLang.t('admin.equity'), width: 120, templet: numberText},
                    {field: 'margin', title: CrmLang.t('admin.margin'), width: 120, templet: numberText},
                    {field: 'margin_free', title: CrmLang.t('admin.margin_free'), width: 140, templet: numberText},
                    {field: 'margin_level', title: CrmLang.t('admin.margin_level'), width: 140, templet: numberText},
                    {field: 'leverage', title: CrmLang.t('admin.leverage'), width: 100}
                ]],
                parseData: function(response) {
                    return parseRiskResponse(response, 'marginCalls');
                },
                done: function() {
                    CrmLang.switchUI();
                    refreshPermissions();
                    renderRiskIcons();
                }
                }));
            }

            /**
             * 渲染异常 IP 风险表。
             *
             * 字段逻辑说明：
             * - login_ip：同一个登录 IP，是异常 IP 聚合维度。
             * - distinct_user_count：同一 IP 下出现过的不同业务用户数量。
             * - login_count：同一 IP 的总登录次数。
             */
            function renderRiskIpTable(where) {
                table.render(CrmTable.layuiConfig('admin', {
                elem: '#riskIpTable',
                id: 'riskIpTable',
                url: '/api/admin/riskIpList',
                where: where || {},
                cols: [[
                    {field: 'login_ip', title: CrmLang.t('admin.login_ip'), width: 160},
                    {field: 'distinct_user_count', title: CrmLang.t('admin.distinct_user_count'), width: 150},
                    {field: 'login_count', title: CrmLang.t('admin.login_count'), width: 120},
                    {field: 'latest_login_at', title: CrmLang.t('admin.latest_login_at'), width: 160},
                    {field: 'sample_user_name', title: CrmLang.t('admin.sample_user_name'), minWidth: 160},
                    {fixed: 'right', title: CrmLang.t('common.action'), toolbar: '#riskIpActions', width: 100}
                ]],
                parseData: function(response) {
                    return parseRiskResponse(response, 'ipRisk');
                },
                done: function() {
                    CrmLang.switchUI();
                    refreshPermissions();
                    renderRiskIcons();
                }
                }));
            }

            /**
             * 渲染异常 IP 详情表。
             *
             * 字段逻辑说明：
             * - login_ip：当前展开的登录 IP，由异常 IP 聚合行传入。
             * - user_id：该 IP 下登录过的业务用户 ID。
             * - open_order_count / closed_order_count：后端按 mt4_trades 聚合出的开平仓订单数量。
             * - total_deposit / total_withdraw：后端按真实入金、出金业务表聚合的资金数据。
             */
            function renderRiskIpDetailTable() {
                table.render(CrmTable.layuiConfig('admin', {
                elem: '#riskIpDetailTable',
                id: 'riskIpDetailTable',
                url: '/api/admin/riskIpDetail',
                where: {login_ip: currentRiskIp},
                cols: [[
                    {field: 'login_ip', title: CrmLang.t('admin.login_ip'), width: 150},
                    {field: 'user_id', title: CrmLang.t('admin.userId'), width: 120},
                    {field: 'user_name', title: CrmLang.t('admin.user_name'), width: 140},
                    {field: 'parent_id', title: CrmLang.t('admin.parent_id'), width: 120},
                    {field: 'login_count', title: CrmLang.t('admin.login_count'), width: 110},
                    {field: 'latest_login_at', title: CrmLang.t('admin.latest_login_at'), width: 160},
                    {field: 'registered_at', title: CrmLang.t('admin.register_time'), width: 150},
                    {field: 'open_order_count', title: CrmLang.t('admin.open_order_count'), width: 140, templet: integerText},
                    {field: 'closed_order_count', title: CrmLang.t('admin.closed_order_count'), width: 140, templet: integerText},
                    {field: 'total_deposit', title: CrmLang.t('admin.total_deposit'), width: 130, templet: numberText},
                    {field: 'total_withdraw', title: CrmLang.t('admin.total_withdraw'), width: 130, templet: numberText}
                ]],
                parseData: function(response) {
                    return parseRiskResponse(response, '');
                },
                done: function() {
                    CrmLang.switchUI();
                    refreshPermissions();
                    renderRiskIcons();
                }
                }));
            }

            setMode(defaultMode);
            reloadCurrentTable(currentRiskFilters());

            document.getElementById('reloadRisk').onclick = function() {
                reloadCurrentTable(currentRiskFilters());
            };

            $('.risk-mode').on('click', function() {
                if (!setMode(this.getAttribute('data-mode'))) {
                    return;
                }
                reloadCurrentTable(currentRiskFilters());
            });

            form.on('submit(searchRisk)', function(data) {
                reloadCurrentTable(currentRiskFilters());
                return false;
            });

            $('#resetRiskSearch').on('click', function() {
                window.setTimeout(function() {
                    reloadCurrentTable(currentRiskFilters());
                }, 0);
            });

            table.on('tool(riskTable)', function(obj) {
                if (obj.event !== 'forceClose') {
                    return;
                }

                if (!obj.data.force_close_id) {
                    return;
                }

                // force_close_id：由业务用户映射账号与 ticket 精确关联出的 mt4_trades 主键。
                CrmAjax.request({
                    guard: 'admin',
                    url: '/api/admin/riskForceClose/' + encodeURIComponent(obj.data.force_close_id),
                    data: {},
                    success: function(res) {
                        layer.msg(res.message || CrmLang.t('common.success'));
                        reloadCurrentTable(currentRiskFilters());
                    }
                });
            });

            table.on('tool(riskIpTable)', function(obj) {
                if (obj.event !== 'ipDetail') {
                    return;
                }

                // obj.data.login_ip：异常 IP 聚合行的登录 IP，详情接口会按该 IP 精确筛选并再次套用管理员数据范围。
                currentRiskIp = obj.data.login_ip || '';
                layer.open({
                    type: 1,
                    title: CrmLang.t('admin.risk_ip_detail') + ' - ' + escapeRiskDialogTitle(currentRiskIp),
                    area: ['92%', '78%'],
                    content: $('#riskIpDetailDialog'),
                    success: function() {
                        if (!renderedRiskTables.riskIpDetail) {
                            renderRiskIpDetailTable();
                            renderedRiskTables.riskIpDetail = true;
                        } else {
                            table.reload('riskIpDetailTable', {
                                where: {login_ip: currentRiskIp},
                                page: {curr: 1}
                            });
                        }
                    }
                });
            });

            function escapeRiskDialogTitle(value) {
                return String(value === undefined || value === null ? '' : value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            /**
             * 切换风控视图。
             *
             * @param {string} mode 视图模式：profit=盈利风险，positions=当前持仓风险，marginCalls=追保预警，ipRisk=异常 IP。
             * @returns {boolean} 允许切换时返回 true；固定专页收到其他模式时返回 false。
             */
            function setMode(mode) {
                var requestedMode = ['profit', 'positions', 'marginCalls', 'ipRisk'].indexOf(mode) !== -1
                    ? mode
                    : 'positions';

                if (fixedMode && requestedMode !== fixedMode) {
                    return false;
                }

                currentMode = fixedMode || requestedMode;
                setRiskPanelVisibility('profitRiskTable', currentMode === 'profit');
                setRiskPanelVisibility('riskTable', currentMode === 'positions');
                setRiskPanelVisibility('marginCallTable', currentMode === 'marginCalls');
                setRiskPanelVisibility('riskIpTable', currentMode === 'ipRisk');
                $('.risk-mode').each(function() {
                    var isActive = this.getAttribute('data-mode') === currentMode;
                    $(this)
                        .toggleClass('layui-btn-normal', isActive)
                        .toggleClass('layui-btn-primary', !isActive)
                        .attr('aria-selected', isActive ? 'true' : 'false');
                });
                if (currentMode === 'profit') {
                    clearRiskSummaryCards();
                }
                renderRiskIcons();

                return true;
            }

            function setRiskPanelVisibility(tableId, visible) {
                var panel = document.getElementById(tableId).parentNode;

                panel.style.display = visible ? '' : 'none';
                if (panel.setAttribute) {
                    panel.setAttribute('aria-hidden', visible ? 'false' : 'true');
                }
            }

            /**
             * 首次进入模式时只渲染对应表格，后续搜索或刷新才重载当前表格。
             *
             * @param {Object=} where 筛选表单参数。
             * @returns {void}
             */
            function reloadCurrentTable(where) {
                var tableIdMap = {
                    profit: 'profitRiskTable',
                    positions: 'riskTable',
                    marginCalls: 'marginCallTable',
                    ipRisk: 'riskIpTable'
                };
                var renderers = {
                    profit: renderProfitRiskTable,
                    positions: renderPositionsTable,
                    marginCalls: renderMarginCallTable,
                    ipRisk: renderRiskIpTable
                };
                var tableId = tableIdMap[currentMode];

                if (!renderedRiskTables[currentMode]) {
                    renderers[currentMode](where || {});
                    renderedRiskTables[currentMode] = true;
                    return;
                }

                table.reload(tableId, {where: where || {}, page: {curr: 1}});
            }

            /**
             * 将持仓汇总下钻 URL 中的默认筛选写入风控表单。
             *
             * @returns {void} 只写入 Blade 已转义的数据属性；空值保持表单为空，供管理员继续手动筛选。
             */
            function applyDefaultRiskQueryFilters() {
                var defaults = {
                    user_id: $riskPageMarker.attr('data-default-risk-user-id') || '',
                    start_date: $riskPageMarker.attr('data-default-risk-start-date') || '',
                    end_date: $riskPageMarker.attr('data-default-risk-end-date') || ''
                };

                Object.keys(defaults).forEach(function(name) {
                    $('#riskSearchForm [name="' + name + '"]').val(defaults[name]);
                });
                form.render();
            }

            /**
             * 读取风控页当前表单筛选。
             *
             * @returns {Object} 返回业务 user_id、订单、日期、追保阈值和 IP 条件，供当前视图请求复用。
             */
            function currentRiskFilters() {
                return serializeForm($, '#riskSearchForm');
            }

            /**
             * 解析下钻入口指定的默认风险视图。
             *
             * @returns {string} 仅返回 profit、positions、marginCalls 或 ipRisk；非法值统一回退到 positions。
             */
            function currentRiskMode() {
                var mode = $riskPageMarker.attr('data-default-risk-mode') || 'positions';

                return ['profit', 'positions', 'marginCalls', 'ipRisk'].indexOf(mode) !== -1
                    ? mode
                    : 'positions';
            }

            function currentFixedRiskMode() {
                var mode = $riskPageMarker.attr('data-fixed-risk-mode') || '';

                return ['profit', 'positions', 'ipRisk'].indexOf(mode) !== -1 ? mode : '';
            }

            /**
             * 渲染风控筛选、视图切换和行操作中的 Lucide 图标。
             *
             * @returns {void} Lucide 未加载时保留按钮文字，业务操作仍可正常执行。
             */
            function renderRiskIcons() {
                if (window.lucide && window.lucide.createIcons) {
                    window.lucide.createIcons();
                }
            }

            /**
             * Layui 表格数字列模板。
             *
             * @param {Object} row 当前行数据。
             * @returns {string} 格式化数字。
             */
            function numberText(row) {
                return formatNumber(row[this.field]);
            }

            /**
             * Layui 表格整数列模板。
             *
             * @param {Object} row 当前行数据。
             * @returns {number} 整数展示值。
             */
            function integerText(row) {
                return parseInt(row[this.field] || 0, 10);
            }
        });
    });

    registry['roles/index'] = once(function () {
        // Source: roles/index.js
        layui.use(['table', 'form', 'layer', 'tree', 'jquery'], function() {
            var table = layui.table;
            var form = layui.form;
            var layer = layui.layer;
            var tree = layui.tree;
            var $ = layui.jquery;
            var successCodes = {1000: true, 1001: true, 1002: true, 1003: true};
            var currentPermissionRole = null;

            CrmLang.switchUI();

            // 角色列表：数据来自 roles 表，permission_ids 来自 role_permissions 表，用于授权弹窗回显。
            table.render(CrmTable.layuiConfig('admin', {
                elem: '#roleTable',
                id: 'roleTable',
                url: '/api/admin/roles',
                cols: [[
                    {field: 'id', title: 'ID', width: 80, sort: true},
                    // name 表示角色稳定名称，后台管理员通过 admins.role_id 绑定该角色。
                    {field: 'name', title: CrmLang.t('role.name'), width: 200},
                    // guard_type 表示角色守卫，admin=后台角色，front=前台代理商或普通客户角色。
                    {field: 'guard_type', title: CrmLang.t('role.guardType'), width: 120},
                    {field: 'description', title: CrmLang.t('role.description')},
                    {fixed: 'right', title: CrmLang.t('common.action'), toolbar: '#roleActions', width: 230}
                ]],
                parseData: CrmTable.layuiParseData(),
                done: function() {
                    CrmLang.switchUI();
                    refreshPermissions();
                }
            }));

            $('#addRole').on('click', function() {
                form.val('roleForm', {id: '', name: '', guard_type: 'admin', description: ''});
                layer.open({
                    type: 1,
                    title: CrmLang.t('role.createRole'),
                    area: ['600px', '400px'],
                    content: $('#roleModal')
                });
            });

            form.on('submit(saveRole)', function(data) {
                var apiUrl = data.field.id ? '/api/admin/updateRole' : '/api/admin/createRole';
                if (!data.field.guard_type) {
                    data.field.guard_type = 'admin';
                }

                CrmAjax.request({
                    guard: 'admin',
                    url: apiUrl,
                    data: data.field,
                    success: function(res) {
                        if (successCodes[res.code]) {
                            layer.closeAll();
                            table.reload('roleTable');
                            layer.msg(res.message || CrmLang.t('common.success'), {icon: 1});
                            return;
                        }
                        layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
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
                    return;
                }

                if (obj.event === 'assignPermissions') {
                    openPermissionModal(data);
                    return;
                }

                if (obj.event === 'delete') {
                    layer.confirm(CrmLang.t('common.confirm'), function(index) {
                        CrmAjax.request({
                            guard: 'admin',
                            // deleteRole 删除 roles.id；接口仍由 permissions.api_route 与 check.permission:admin 校验。
                            url: '/api/admin/deleteRole',
                            data: {id: data.id},
                            success: function(res) {
                                if (successCodes[res.code]) {
                                    obj.del();
                                    layer.close(index);
                                    layer.msg(res.message || CrmLang.t('common.success'), {icon: 1});
                                    return;
                                }
                                layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                            }
                        });
                    });
                }
            });

            $('#saveRolePermissions').on('click', function() {
                if (!currentPermissionRole) {
                    return;
                }

                // permissions 表中的勾选节点会被展开为 permissions.id 数组，提交后由后端写入 role_permissions 表。
                var checkedNodes = tree.getChecked('rolePermissionTree');
                var permissionIds = collectCheckedIds(checkedNodes);

                CrmAjax.request({
                    guard: 'admin',
                    url: '/api/admin/assignPermissions',
                    data: {
                        role_id: currentPermissionRole.id,
                        permissions: permissionIds
                    },
                    success: function(res) {
                        if (successCodes[res.code]) {
                            layer.closeAll();
                            table.reload('roleTable');
                            layer.msg(res.message || CrmLang.t('common.success'), {icon: 1});
                            return;
                        }
                        layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                    }
                });
            });

            /**
             * 打开角色权限分配弹窗。
             *
             * 参数含义：
             * - role：角色表格当前行，来自 roles 表；role.id 是待授权的 roles.id。
             * - role.guard_type 表示角色守卫，决定本次只同步当前角色 guard_type 下的权限。
             * - role.permission_ids 表示 role_permissions 表中已经授权的 permissions.id 数组，用于权限树默认勾选。
             *
             * @param {Object} role 当前角色行数据。
             * @returns {void}
             */
            function openPermissionModal(role) {
                currentPermissionRole = role;
                $('#rolePermissionRoleId').val(role.id);
                $('#rolePermissionGuardType').val(role.guard_type || 'admin');
                $('#rolePermissionHint').text(
                    CrmLang.t('role.assignPermissionHint') + ': ' + (role.name || role.id)
                );
                $('#permissionTreeBox').html('<div class="layui-text">' + CrmLang.t('common.loading') + '</div>');

                layer.open({
                    type: 1,
                    title: CrmLang.t('role.assignPermissions'),
                    area: ['680px', '620px'],
                    content: $('#rolePermissionModal'),
                    success: function() {
                        loadPermissionTree(role);
                    }
                });
            }

            /**
             * 加载权限树并回显当前角色已有授权。
             *
             * 参数含义：
             * - role.id：待授权角色 ID。
             * - role.guard_type：权限树筛选守卫，防止后台角色混入前台 agent/customer 菜单权限。
             * - role.permission_ids：已授权 permissions.id 数组。
             *
             * @param {Object} role 当前角色行数据。
             * @returns {void}
             */
            function loadPermissionTree(role) {
                CrmAjax.request({
                    guard: 'admin',
                    url: '/api/admin/permissions/tree',
                    data: {guard_type: role.guard_type || 'admin'},
                    success: function(res) {
                        if (!successCodes[res.code]) {
                            layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                            return;
                        }

                        tree.render({
                            elem: '#permissionTreeBox',
                            id: 'rolePermissionTree',
                            data: normalizePermissionTree(res.data || [], role.permission_ids || []),
                            showCheckbox: true,
                            onlyIconControl: false
                        });
                    }
                });
            }

            /**
             * 转换权限树字段并设置勾选状态。
             *
             * 参数含义：
             * - nodes：/api/admin/permissions/tree 返回的 permissions 表节点数组。
             * - selectedIds：当前角色已授权的 permissions.id 数组，来自 role_permissions 表。
             *
             * @param {Array<Object>} nodes 权限树节点数组。
             * @param {Array<number|string>} selectedIds 已授权权限 ID 数组。
             * @returns {Array<Object>} Layui tree 可渲染的节点数组。
             */
            function normalizePermissionTree(nodes, selectedIds) {
                var selectedMap = {};
                $.each(selectedIds || [], function(_, id) {
                    selectedMap[String(id)] = true;
                });

                return $.map(nodes || [], function(node) {
                    var children = normalizePermissionTree(node.children || [], selectedIds);
                    var item = {
                        id: node.id,
                        title: node.name || node.slug || String(node.id),
                        spread: true,
                        children: children
                    };

                    if (selectedMap[String(node.id)]) {
                        item.checked = true;
                    }

                    return item;
                });
            }

            /**
             * 收集 Layui tree 已勾选节点 ID。
             *
             * 参数含义：
             * - checkedNodes：tree.getChecked 返回的嵌套节点数组，包含父子权限节点。
             *
             * @param {Array<Object>} checkedNodes 已勾选权限节点。
             * @returns {Array<number>} permissions.id 数组。
             */
            function collectCheckedIds(checkedNodes) {
                var ids = [];

                $.each(checkedNodes || [], function(_, node) {
                    ids.push(Number(node.id));
                    ids = ids.concat(collectCheckedIds(node.children || []));
                });

                return ids;
            }

            /**
             * 重新应用按钮权限。
             *
             * 逻辑说明：
             * - Layui 表格重载会重新生成操作列按钮，必须重新按 permissions.slug 隐藏无权限按钮。
             * - 分配权限按钮使用 admin_role_assign_permissions，真实保存接口仍由后端二次鉴权。
             *
             * @returns {void}
             */
            function refreshPermissions() {
                if (window.CrmAdminPermissions && window.CrmAdminPermissions.refresh) {
                    window.CrmAdminPermissions.refresh();
                }
            }
        });
    });

    registry['system-configs/index'] = once(function () {
        // Source: system-configs/index.js
        layui.use(['table', 'form', 'layer', 'jquery'], function() {
            var table = layui.table;
            var form = layui.form;
            var layer = layui.layer;
            var $ = layui.jquery;
            var successCodes = {1000: true, 1001: true, 1002: true, 1003: true};

            CrmLang.switchUI();

            // system_configs 真实字段为 key/value/group/description；页面不再使用旧的 config_key/config_value 虚拟列。
            table.render(CrmTable.layuiConfig('admin', {
                elem: '#systemConfigTable',
                id: 'systemConfigTable',
                url: '/api/admin/system-configs',
                cols: [[
                    {field: 'id', title: 'ID', width: 90, sort: true},
                    {field: 'key', title: CrmLang.t('admin.configKey'), width: 220},
                    {field: 'value', title: CrmLang.t('admin.configValue'), minWidth: 260},
                    {field: 'group', title: CrmLang.t('admin.group'), width: 140},
                    {field: 'description', title: CrmLang.t('admin.description'), minWidth: 220},
                    {field: 'updated_at', title: CrmLang.t('admin.updatedAt'), width: 180},
                    {fixed: 'right', title: CrmLang.t('common.action'), toolbar: '#systemConfigActions', width: 100}
                ]],
                parseData: CrmTable.layuiParseData(),
                done: function() {
                    CrmLang.switchUI();
                    refreshPermissions();
                }
            }));

            document.getElementById('reloadSystemConfigs').onclick = function() {
                table.reload('systemConfigTable');
            };

            table.on('tool(systemConfigTable)', function(obj) {
                if (obj.event === 'edit') {
                    openSystemConfigModal(obj.data);
                }
            });

            form.on('submit(saveSystemConfig)', function(data) {
                CrmAjax.request({
                    guard: 'admin',
                    url: '/api/admin/updateSystemConfig',
                    data: data.field,
                    success: function(res) {
                        if (successCodes[res.code]) {
                            layer.closeAll();
                            table.reload('systemConfigTable');
                            layer.msg(res.message || CrmLang.t('common.success'), {icon: 1});
                            return;
                        }
                        layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                    }
                });

                return false;
            });

            /**
             * 打开系统配置编辑弹窗。
             *
             * @param {Object} row system_configs 行数据；id/key 用于定位配置，value/group/description 为可编辑字段。
             * @returns {void}
             */
            function openSystemConfigModal(row) {
                form.val('systemConfigForm', {
                    id: row.id || '',
                    key: row.key || '',
                    value: row.value || '',
                    group: row.group || 'general',
                    description: row.description || ''
                });

                layer.open({
                    type: 1,
                    title: CrmLang.t('admin.edit_system_config'),
                    area: ['680px', '600px'],
                    content: $('#systemConfigModal')
                });
                form.render();
            }

            /**
             * 重新应用按钮权限，确保表格操作列刷新后仍按 permissions.slug 显隐。
             *
             * @returns {void}
             */
            function refreshPermissions() {
                if (window.CrmAdminPermissions && window.CrmAdminPermissions.refresh) {
                    window.CrmAdminPermissions.refresh();
                }
            }
        });
    });

    registry['trades/index'] = once(function () {
        // Source: trades/index.js
        layui.use(['table', 'form', 'laydate', 'jquery'], function() {
            var table = layui.table;
            var form = layui.form;
            var laydate = layui.laydate;
            var $ = layui.jquery;
            var currentApiUrl = '/api/admin/tradeList';
            var tradeModeUrls = {
                all: '/api/admin/tradeList',
                open: '/api/admin/openPositions',
                closed: '/api/admin/closedPositions'
            };
            var tradeFilterFields = [
                {name: 'user_id'},
                {name: 'ticket'},
                {name: 'symbol'},
                {name: 'start_date'},
                {name: 'end_date'},
                {name: 'is_coercion'},
                {name: 'orderType'}
            ];
            var $tradePageMarker = $('[data-layui-page="trades/index"]');
            var defaultMode = currentTradeMode();
            currentApiUrl = tradeModeUrls[defaultMode] || tradeModeUrls.all;

            CrmLang.switchUI();
            laydate.render({elem: '#tradeStartDate', type: 'date'});
            laydate.render({elem: '#tradeEndDate', type: 'date'});
            applyDefaultTradeQueryFilters();
            activateTradeMode(defaultMode);

            /**
             * 读取交易筛选条件。
             *
             * tradeFilterFields 明确列出新项目字段名；后端同时兼容旧项目 userId/orderId/sym_symbol/startdate/enddate。
             *
             * @returns {Object} 去掉空值后的筛选对象。
             */
            function currentTradeFilters() {
                return tradeFilterFields.reduce(function(filters, field) {
                    var value = $('[name="' + field.name + '"]', '#tradeSearchForm').val();
                    if (value !== undefined && value !== null && value !== '') {
                        filters[field.name] = value;
                    }
                    return filters;
                }, {});
            }

            /**
             * 把 URL 默认筛选写入交易订单表单。
             *
             * @returns {void} 仅写入非空参数，避免覆盖用户手动保留的其它筛选项。
             */
            function applyDefaultTradeQueryFilters() {
                var defaults = {
                    user_id: $tradePageMarker.attr('data-default-trade-user-id') || '',
                    start_date: $tradePageMarker.attr('data-default-trade-start-date') || '',
                    end_date: $tradePageMarker.attr('data-default-trade-end-date') || ''
                };

                Object.keys(defaults).forEach(function(name) {
                    if (defaults[name] !== '') {
                        $('[name="' + name + '"]', '#tradeSearchForm').val(defaults[name]);
                    }
                });
                form.render('select');
            }

            /**
             * 返回当前交易模式。
             *
             * @returns {string} all、open 或 closed；未知值回退 all，避免请求不存在的接口。
             */
            function currentTradeMode() {
                var activeMode = $('.trade-mode.layui-btn-normal').attr('data-mode') || '';
                var markerMode = String($tradePageMarker.attr('data-default-trade-mode') || 'all');
                var mode = activeMode || markerMode || 'all';

                return tradeModeUrls[mode] ? mode : 'all';
            }

            /**
             * 高亮当前交易模式按钮。
             *
             * @param {string} mode 交易模式，all=全部，open=当前持仓，closed=历史平仓。
             * @returns {void}
             */
            function activateTradeMode(mode) {
                $('.trade-mode').removeClass('layui-btn-normal').addClass('layui-btn-primary');
                $('.trade-mode[data-mode="' + mode + '"]').removeClass('layui-btn-primary').addClass('layui-btn-normal');
            }

            /**
             * 格式化金额/手数类汇总值。
             *
             * @param {number|string} value 后端返回的数字或数字字符串。
             * @returns {string} 保留两位小数后的展示文本。
             */
            function formatNumber(value) {
                var number = parseFloat(value || 0);
                if (Number.isNaN(number)) {
                    number = 0;
                }
                return number.toFixed(2);
            }

            /**
             * 格式化 MT4 10 位时间戳。
             *
             * @param {number|string} value 后端返回的 open_time、close_time 或 modify_time。
             * @returns {string} 本地时间展示文本；无时间时返回占位横线。
             */
            function formatTimestamp(value) {
                var timestamp = parseInt(value || 0, 10);
                if (!timestamp) {
                    return '-';
                }
                return new Date(timestamp * 1000).toLocaleString();
            }

            /**
             * 转义表格文本，避免 MT4 COMMENT 中的特殊字符被当成 HTML。
             *
             * @param {number|string|null} value 原始字段值。
             * @returns {string} 可安全放入 Layui 表格单元格的文本。
             */
            function escapeHtml(value) {
                return String(value === undefined || value === null ? '' : value).replace(/[&<>"']/g, function(char) {
                    return {
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#039;'
                    }[char];
                });
            }

            /**
             * 更新交易汇总卡片。
             *
             * @param {Object} summary 后端返回的聚合数据；包含 total_orders、total_volume、total_profit、total_swaps、total_commission。
             */
            function updateSummaryCards(summary) {
                summary = summary || {};
                Object.keys(summary).forEach(function(key) {
                    var element = document.querySelector('[data-summary-field="' + key + '"]');
                    if (!element) {
                        return;
                    }
                    element.innerText = key === 'total_orders' ? (summary[key] || 0) : formatNumber(summary[key]);
                });
            }

            /**
             * 解析后台交易接口返回值。
             *
             * response.data.records：Laravel paginator，用于 Layui table 的 rows/total。
             * response.data.summary：当前筛选条件下的聚合汇总，用于顶部统计卡片。
             *
             * @param {Object} response 后端统一响应结构。
             * @returns {Object} Layui table 需要的 code/msg/count/data。
             */
            function parseTradeResponse(response) {
                var wrapper = response && response.data ? response.data : {};
                var records = wrapper.records || {};
                updateSummaryCards(wrapper.summary || {});

                return {
                    code: response && response.code === 1000 ? 0 : (response ? response.code : 5000),
                    msg: response ? response.message : '',
                    count: records.total || 0,
                    data: records.data || []
                };
            }

            /**
             * 渲染 MT4 交易表格。
             *
             * 字段说明：
             * - login：MT4 登录账号/业务用户 ID。
             * - ticket：MT4 订单号。
             * - cmd：MT4 交易类型，0 到 5 为交易/挂单类。
             * - comment/ordercomment：MT4 COMMENT，历史平仓兼容旧项目 ordercomment 字段名。
             * - open_time/close_time/modify_time：10 位时间戳，历史平仓按 modify_time 倒序返回。
             */
            table.render(CrmTable.layuiConfig('admin', {
                elem: '#tradeTable',
                id: 'tradeTable',
                url: currentApiUrl,
                where: currentTradeFilters(),
                cols: [[
                    {field: 'id', title: 'ID', width: 90, sort: true},
                    {field: 'login', title: CrmLang.t('admin.userId'), width: 120},
                    {field: 'ticket', title: CrmLang.t('admin.ticket'), width: 140},
                    {field: 'symbol', title: CrmLang.t('admin.symbol'), width: 120},
                    {field: 'cmd', title: CrmLang.t('admin.trade_cmd'), width: 100},
                    {field: 'volume', title: CrmLang.t('admin.volume'), width: 120},
                    {field: 'commission', title: CrmLang.t('admin.commission'), width: 120},
                    {field: 'swaps', title: CrmLang.t('admin.swaps'), width: 120},
                    {field: 'profit', title: CrmLang.t('admin.profit'), width: 120},
                    {field: 'comment', title: CrmLang.t('admin.comment'), minWidth: 220, templet: function(row) {
                        return escapeHtml(row.comment || row.ordercomment || '-');
                    }},
                    {field: 'open_time', title: CrmLang.t('admin.openTime'), width: 170, templet: function(row) {
                        return formatTimestamp(row.open_time);
                    }},
                    {field: 'close_time', title: CrmLang.t('admin.close_time'), width: 170, templet: function(row) {
                        return formatTimestamp(row.close_time);
                    }},
                    {field: 'modify_time', title: CrmLang.t('admin.modify_time'), width: 170, templet: function(row) {
                        return formatTimestamp(row.modify_time || row.close_time);
                    }}
                ]],
                parseData: parseTradeResponse,
                done: function(response) {
                    updateSummaryCards(response && response.data ? response.data.summary : {});
                    CrmLang.switchUI();
                    if (window.lucide && window.lucide.createIcons) {
                        window.lucide.createIcons();
                    }
                }
            }));

            form.on('submit(searchTrades)', function(data) {
                table.reload('tradeTable', {where: currentTradeFilters(), page: {curr: 1}});
                return false;
            });

            $('#exportClosedPositions').on('click', function() {
                downloadAdminCsv($, layer, '/api/admin/exportClosedPositions', currentTradeFilters(), 'closed_positions_export.csv');
            });

            layui.jquery('.trade-mode').on('click', function() {
                var mode = this.getAttribute('data-mode');
                defaultMode = mode;
                activateTradeMode(mode);
                currentApiUrl = tradeModeUrls[mode] || tradeModeUrls.all;
                table.reload('tradeTable', {url: currentApiUrl, where: currentTradeFilters(), page: {curr: 1}});
            });
        });
    });

    registry['undeposit-flows/index'] = once(function () {
        // Source: undeposit-flows/index.js
        layui.use(['table', 'form', 'layer', 'jquery', 'laydate'], function() {
            var table = layui.table;
            var form = layui.form;
            var layer = layui.layer;
            var $ = layui.jquery;
            var laydate = layui.laydate;

            CrmLang.switchUI();

            // start_date/end_date：未入金流水时间范围，对应后端按 deposit_records.created_at 过滤的日期参数。
            laydate.render({elem: '#undepositFlowStartDate', type: 'date'});
            laydate.render({elem: '#undepositFlowEndDate', type: 'date'});
            laydate.render({elem: '#neverDepositStartDate', type: 'date'});
            laydate.render({elem: '#neverDepositEndDate', type: 'date'});

            // 表格数据来自 /api/admin/undepositFlowList，后端会按 permissions.api_route 和 AdminDataScopeService 二次校验。
            table.render(CrmTable.layuiConfig('admin', {
                elem: '#undepositFlowTable',
                id: 'undepositFlowTable',
                url: '/api/admin/undepositFlowList',
                cols: [[
                    {field: 'id', title: 'ID', width: 90, sort: true},
                    {field: 'user_id', title: CrmLang.t('admin.userId'), width: 120},
                    {field: 'user_name', title: CrmLang.t('admin.user_name'), width: 160},
                    {field: 'mt4_ticket', title: CrmLang.t('admin.mt4_ticket'), width: 130},
                    {field: 'amount', title: CrmLang.t('admin.amount'), width: 130},
                    {field: 'actual_amount', title: CrmLang.t('admin.actual_amount'), width: 140},
                    {field: 'exchange_rate', title: CrmLang.t('admin.exchange_rate'), width: 130},
                    {field: 'channel_name', title: CrmLang.t('admin.channel_name'), width: 160},
                    {field: 'local_order_no', title: CrmLang.t('admin.local_order_no'), width: 190},
                    {field: 'channel_order_no', title: CrmLang.t('admin.channel_order_no'), width: 190},
                    {field: 'status', title: CrmLang.t('admin.status'), width: 110},
                    {field: 'follow_status_name', title: CrmLang.t('admin.follow_status_name'), width: 130},
                    {field: 'pending_days', title: CrmLang.t('admin.pending_days'), width: 120},
                    {field: 'created_at', title: CrmLang.t('user.createdAt'), width: 170}
                ]],
                totalRow: true,
                parseData: CrmTable.layuiParseData(),
                done: function() {
                    CrmLang.switchUI();
                }
            }));

            document.getElementById('reloadUndepositFlows').onclick = function() {
                table.reload('undepositFlowTable');
            };

            $('#exportUndepositFlows').on('click', function() {
                downloadAdminCsv($, layer, '/api/admin/exportUndepositFlows', serializeForm($, '#undepositFlowSearchForm'), 'undeposit_flows_export.csv');
            });

            form.on('submit(searchUndepositFlows)', function(data) {
                table.reload('undepositFlowTable', {where: data.field, page: {curr: 1}});
                return false;
            });

            // 从未入金用户列表来自 /api/admin/neverDepositUserList，用于跟进注册后没有成功入金记录的普通客户。
            table.render(CrmTable.layuiConfig('admin', {
                elem: '#neverDepositUserTable',
                id: 'neverDepositUserTable',
                url: '/api/admin/neverDepositUserList',
                cols: [[
                    {field: 'user_id', title: CrmLang.t('admin.userId'), width: 120},
                    {field: 'user_name', title: CrmLang.t('admin.user_name'), width: 160},
                    {field: 'phone', title: CrmLang.t('admin.phone'), width: 150},
                    {field: 'email', title: 'Email', width: 190},
                    {field: 'parent_id', title: CrmLang.t('admin.parent_id'), width: 120},
                    {field: 'register_date', title: CrmLang.t('user.createdAt'), width: 170},
                    {field: 'never_deposit_days', title: CrmLang.t('admin.never_deposit_days'), width: 140}
                ]],
                parseData: CrmTable.layuiParseData(),
                done: function() {
                    CrmLang.switchUI();
                }
            }));

            document.getElementById('reloadNeverDepositUsers').onclick = function() {
                table.reload('neverDepositUserTable');
            };

            form.on('submit(searchNeverDepositUsers)', function(data) {
                table.reload('neverDepositUserTable', {where: data.field, page: {curr: 1}});
                return false;
            });
        });
    });

    registry['users/detail'] = once(function () {
        // Source: users/detail.js
        layui.use(['form', 'layer', 'jquery'], function() {
            var form = layui.form, layer = layui.layer, $ = layui.jquery;

            // 初始化当前页面的多语言文案，确保 Blade 初始文案和 JS 动态回填后的文案保持一致。
            CrmLang.switchUI();

            // 用户详情读取 /api/admin/users/{user_id}，保存基础资料提交 PATCH /api/admin/users/{user_id}，启停登录账号提交 PATCH /api/admin/users/{user_id}/status。
            // user_id 表示业务用户 ID，user_infos 保存用户基础资料，user_logins 保存登录账号状态。
            // user_name 表示用户姓名，phone 表示用户手机号，status 表示页面选择的启停状态。
            // is_enabled 表示登录账号是否启用。
            // 用户详情：user_id 表示业务用户 ID，来自 Blade 隐藏字段，读取详情时传给 /api/admin/users/{user_id}。
            var userId = $('#user-id').val();
            if (userId) {
                CrmAjax.request({
                    guard: 'admin',
                    url: '/api/admin/users/' + encodeURIComponent(userId),
                    method: 'POST',
                    data: {user_id: userId},
                    success: function(res) {
                        if (res.code === 1000 || res.code === 1002) {
                            // /api/admin/users/{user_id} 返回 user_infos，并带 user_logins/login 与 auth 关系；表单字段按真实表结构映射。
                            var user = res.data || {};
                            var login = user.login || {};
                            form.val('user-form', {
                                user_id: user.user_id,
                                user_name: user.user_name,
                                email: login.email || '',
                                phone: user.phone || '',
                                status: login.is_enabled == 1 ? '1' : '0'
                            });
                            form.render();
                            CrmLang.switchUI();
                        }
                    }
                });
            }

            form.on('submit(user-save)', function(data) {
                var fields = data.field;
                // status 表示页面选择的启停状态，后续会转换为 user_logins.is_enabled 写入。
                var status = fields.status;
                CrmAjax.request({
                    guard: 'admin',
                    url: '/api/admin/users/' + encodeURIComponent(fields.user_id),
                    method: 'PATCH',
                    data: {
                        // user_id 表示业务用户 ID；user_name 表示用户姓名；phone 表示用户手机号，三者由 /api/admin/users/{user_id} 更新 user_infos。
                        user_id: fields.user_id,
                        user_name: fields.user_name,
                        phone: fields.phone
                    },
                    success: function(res) {
                        if (res.code === 1000) {
                            // /api/admin/users/{user_id}/status 单独更新 user_logins；is_enabled 表示登录账号是否启用。
                            CrmAjax.request({
                                guard: 'admin',
                                url: '/api/admin/users/' + encodeURIComponent(fields.user_id) + '/status',
                                method: 'PATCH',
                                data: {user_id: fields.user_id, is_enabled: status},
                                success: function() {
                                    layer.msg(CrmLang.t('common.success'), {icon: 1}, function() {
                                        window.location.href = crmRoute('admin_page_users');
                                    });
                                }
                            });
                        } else {
                            layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                        }
                    }
                });
                return false;
            });

            $('#cancel-btn').on('click', function() {
                window.history.back();
            });
        });
    });

    registry['users/index'] = once(function () {
        // Source: users/index.js
        layui.use(['table', 'form', 'layer', 'laydate'], function() {
            var table = layui.table, form = layui.form, layer = layui.layer, laydate = layui.laydate, $ = layui.jquery;

            // 初始化当前页面的多语言文案，确保 Blade 初始文案和 JS 动态渲染文案保持一致。
            CrmLang.switchUI();
            laydate.render({elem: '#userStartDate', type: 'date'});
            laydate.render({elem: '#userEndDate', type: 'date'});

            function refreshPermissions() {
                // 重新应用按钮权限：Layui 表格重载后会重新生成操作列按钮，必须再次按 permissions.slug 隐藏无权限按钮。
                if (window.CrmAdminPermissions && window.CrmAdminPermissions.refresh) {
                    window.CrmAdminPermissions.refresh();
                }
            }

            // 用户列表：数据来自 /api/admin/users，后端会按管理员角色和数据范围过滤可见代理与客户。
            table.render(CrmTable.layuiConfig('admin', {
                elem: '#userTable',
                url: '/api/admin/users',
                cols: [[
                    // user_id 表示业务用户 ID，是打开详情页和修改登录状态时传给后端的主键。
                    {field: 'user_id', title: 'ID', width: 100, sort: true},
                    {field: 'user_name', title: CrmLang.t('user.userName'), width: 140},
                    // email 表示登录邮箱，真实字段来自 user_logins 关联对象 login.email。
                    {field: 'email', title: CrmLang.t('user.email'), width: 220, templet: function(d) {
                        return d.login && d.login.email ? d.login.email : '-';
                    }},
                    // account_type 表示账号类型：1=代理，2=客户，用于区分后台用户列表中的代理商和普通客户。
                    {field: 'account_type', title: CrmLang.t('user.accountType'), width: 120, templet: function(d) {
                        return d.account_type == 1 ? CrmLang.t('user.agentType') : CrmLang.t('user.customerType');
                    }},
                    {field: 'total_yuerj', title: CrmLang.t('front.total_deposit'), width: 130},
                    {field: 'total_yuecj', title: CrmLang.t('front.total_withdraw'), width: 130},
                    {field: 'total_net_worth', title: CrmLang.t('front.net_worth'), width: 130},
                    {field: 'total_comm', title: CrmLang.t('front.total_commission'), width: 130},
                    {field: 'total_profit', title: CrmLang.t('front.total_profit'), width: 130},
                    {field: 'total_volume', title: CrmLang.t('front.total_volume'), width: 130},
                    {field: 'total_swaps', title: CrmLang.t('front.total_swaps'), width: 130},
                    // auth_status 表示认证状态：0=未认证，1=已认证，其它值按审核中展示。
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
                    refreshPermissions();
                }
            }));

            form.on('submit(searchUsers)', function(data) {
                // 搜索参数来自 Blade 表单：user_id 表示业务用户 ID，email 表示登录邮箱，account_type 表示账号类型。
                table.reload('userTable', {
                    where: data.field,
                    page: {curr: 1}
                });
                return false;
            });

            $('#exportUsers').on('click', function() {
                // 导出沿用当前筛选表单，后端 /api/admin/exportUsers 会继续套用管理员数据范围，避免 CSV 绕过可见范围。
                var fields = {};
                $('#userSearchForm').serializeArray().forEach(function(item) {
                    fields[item.name] = item.value;
                });
                submitCsvDownload('/api/admin/exportUsers', fields);
            });

            table.on('tool(userTable)', function(obj) {
                var data = obj.data;
                if (obj.event === 'detail') {
                    // detail 表示打开用户详情，详情页继续由 /admin/users/{id} Blade 页面渲染。
                    layer.open({
                        type: 2,
                        title: CrmLang.t('common.view'),
                        area: ['800px', '600px'],
                        content: crmRoute('admin_page_users_detail', {id: data.user_id})
                    });
                } else if (obj.event === 'status') {
                    // status 表示切换用户启停状态；is_enabled 表示登录账号是否启用，最终以后端 /api/admin/users/{user_id}/status 校验为准。
                    layer.confirm(CrmLang.t('common.confirm'), {title: CrmLang.t('common.status')}, function(index) {
                        var login = data.login || {};
                        var enabled = login.is_enabled == 1 ? 0 : 1;
                        CrmAjax.request({
                            guard: 'admin',
                            url: '/api/admin/users/' + encodeURIComponent(data.user_id) + '/status',
                            method: 'PATCH',
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

            function submitCsvDownload(url, fields) {
                var $form = $('<form method="POST" style="display:none;"></form>').attr('action', url);
                Object.keys(fields || {}).forEach(function(key) {
                    $('<input type="hidden">').attr('name', key).val(fields[key]).appendTo($form);
                });
                $('<input type="hidden">').attr('name', '_token').val(document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : '').appendTo($form);
                $form.appendTo(document.body).trigger('submit').remove();
            }
        });
    });

    registry['vouchers/index'] = once(function () {
        // Source: vouchers/index.js
        layui.use(['table', 'form', 'layer', 'jquery'], function() {
            var table = layui.table, form = layui.form, layer = layui.layer, $ = layui.jquery;

            CrmLang.switchUI();

            function refreshPermissions() {
                // Layui 表格重载会重新生成操作列按钮，必须重新按 permissions.slug 隐藏无权限按钮。
                if (window.CrmAdminPermissions && window.CrmAdminPermissions.refresh) {
                    window.CrmAdminPermissions.refresh();
                }
            }

            function escapeHtml(value) {
                return String(value == null ? '' : value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }

            function parseVoucherImages(value) {
                if (value == null || value === '') {
                    return [];
                }

                if (Array.isArray(value)) {
                    return value.map(function(item) {
                        return String(item || '').trim();
                    }).filter(Boolean);
                }

                var text = String(value).trim();
                if (!text) {
                    return [];
                }

                if ((text[0] === '[' && text[text.length - 1] === ']') || (text[0] === '{' && text[text.length - 1] === '}')) {
                    try {
                        var parsed = JSON.parse(text);
                        if (Array.isArray(parsed)) {
                            return parseVoucherImages(parsed);
                        }
                        if (parsed && typeof parsed === 'object') {
                            return Object.keys(parsed).map(function(key) {
                                return String(parsed[key] || '').trim();
                            }).filter(Boolean);
                        }
                    } catch (error) {
                        // Continue with delimiter parsing below.
                    }
                }

                return text.split(/[\n,]+/).map(function(item) {
                    return item.trim();
                }).filter(Boolean);
            }

            function voucherImageUrl(src) {
                var url = String(src || '').trim();
                if (!url) {
                    return '';
                }
                if (/^(https?:)?\/\//i.test(url) || /^data:image\//i.test(url) || url[0] === '/') {
                    return url;
                }
                if (/^storage\//i.test(url)) {
                    return '/' + url.replace(/^\/+/, '');
                }
                return '/storage/' + url.replace(/^\/+/, '');
            }

            function voucherImagesHtml(row) {
                var images = parseVoucherImages(row.images);
                if (!images.length) {
                    return '<span class="layui-font-gray">-</span>';
                }

                // 图片链接文案来自 common.view，index 只表示同一条凭证记录中的第几张图片。
                return '<div class="admin-voucher-image-links">' + images.map(function(src, index) {
                    var url = voucherImageUrl(src);
                    var label = CrmLang.t('common.view') + (index + 1);
                    return '<a href="' + escapeHtml(url) + '" role="button" tabindex="0" data-admin-voucher-preview="' + escapeHtml(url) + '">' + escapeHtml(label) + '</a>';
                }).join('') + '</div>';
            }

            function openVoucherImagePreview(url) {
                if (!url) {
                    return;
                }

                layer.open({
                    type: 1,
                    title: CrmLang.t('front.voucher_images'),
                    area: ['min(920px, 92vw)', 'min(720px, 88vh)'],
                    shadeClose: true,
                    content: [
                        '<div class="admin-voucher-preview-layer">',
                        '<img class="admin-voucher-preview-image" src="', escapeHtml(url), '" alt="">',
                        '</div>'
                    ].join('')
                });
            }

            function openVoucherPreviewFromLink($link) {
                openVoucherImagePreview($link.attr('data-admin-voucher-preview'));
            }

            $(document).on('click', '[data-admin-voucher-preview]', function(event) {
                event.preventDefault();
                event.stopPropagation();
                openVoucherPreviewFromLink($(this));
            });

            $(document).on('keydown', '[data-admin-voucher-preview]', function(event) {
                if (event.key !== 'Enter' && event.key !== ' ') {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();
                openVoucherPreviewFromLink($(this));
            });

            table.render(CrmTable.layuiConfig('admin', {
                elem: '#voucherTable',
                url: '/api/admin/vouchers',
                cols: [[
                    {field: 'id', title: 'ID', width: 90, sort: true},
                    {field: 'user_id', title: CrmLang.t('admin.userId'), width: 120},
                    {field: 'images', title: CrmLang.t('front.voucher_images'), width: 220, templet: voucherImagesHtml},
                    {field: 'review_status', title: CrmLang.t('admin.reviewStatus'), width: 140},
                    {field: 'created_at', title: CrmLang.t('user.createdAt'), width: 180},
                    {fixed: 'right', title: CrmLang.t('common.action'), toolbar: '#voucherActions', width: 150}
                ]],
                parseData: CrmTable.layuiParseData(),
                done: function() {
                    CrmLang.switchUI();
                    refreshPermissions();
                }
            }));

            form.on('submit(searchVouchers)', function(data) {
                table.reload('voucherTable', {where: data.field, page: {curr: 1}});
                return false;
            });

            function reviewVoucher(apiUrl, id) {
                CrmAjax.request({
                    guard: 'admin',
                    url: apiUrl + '/' + encodeURIComponent(id),
                    data: {},
                    success: function(res) {
                        if (res.code === 1000 || res.code === 1002) {
                            table.reload('voucherTable');
                            layer.msg(CrmLang.t('common.success'), {icon: 1});
                            return;
                        }
                        layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                    }
                });
            }

            table.on('tool(voucherTable)', function(obj) {
                if (obj.event === 'approve') reviewVoucher('/api/admin/voucherApprove', obj.data.id);
                if (obj.event === 'reject') reviewVoucher('/api/admin/voucherReject', obj.data.id);
            });
        });
    });

    registry['vouchers/detail'] = once(function () {
        layui.use(['form', 'layer', 'jquery'], function() {
            var form = layui.form;
            var layer = layui.layer;
            var $ = layui.jquery;
            var page = document.querySelector('[data-voucher-detail-page="1"]');
            var voucherId = page ? page.getAttribute('data-voucher-id') : '';
            var backUrl = '/index/admin/auth/voucher_info_browse';

            if (!page) {
                return;
            }

            $('#voucherDetailBack').on('click', function() {
                window.location.href = backUrl;
            });

            form.on('submit(voucherDetailReview)', function(data) {
                var fields = data.field || {};
                var status = String(fields.reviewstatus || '');
                var reason = String(fields.reviewmsg || '').trim();
                var url = status === '2'
                    ? '/api/admin/voucherReject/' + encodeURIComponent(voucherId)
                    : '/api/admin/voucherApprove/' + encodeURIComponent(voucherId);

                if (status === '2' && !reason) {
                    layer.msg(CrmLang.t('admin.reject_reason_required'), {icon: 2});
                    return false;
                }

                $('#voucherDetailSubmit').prop('disabled', true).addClass('layui-btn-disabled');
                CrmAjax.request({
                    guard: 'admin',
                    url: url,
                    data: status === '2' ? {reason: reason} : {},
                    success: function(res) {
                        if (res && (res.code === 1000 || res.code === 1002)) {
                            layer.msg(CrmLang.t('common.success'), {icon: 1});
                            window.setTimeout(function() { window.location.href = backUrl; }, 350);
                            return;
                        }
                        $('#voucherDetailSubmit').prop('disabled', false).removeClass('layui-btn-disabled');
                        layer.msg((res && res.message) || CrmLang.t('common.error'), {icon: 2});
                    },
                    error: function(res) {
                        $('#voucherDetailSubmit').prop('disabled', false).removeClass('layui-btn-disabled');
                        layer.msg((res && res.message) || CrmLang.t('common.error'), {icon: 2});
                    }
                });

                return false;
            });

            // 页面使用普通 layui form 时仍需显式绑定提交过滤器。
            $('#voucherDetailReviewForm').attr('lay-filter', 'voucherDetailReview');
            form.render();
        });
    });

    registry['whs-exp-zero/index'] = once(function () {
        // Source: whs-exp-zero/index.js
        layui.use(['table', 'form', 'layer', 'jquery', 'element'], function() {
            var table = layui.table;
            var form = layui.form;
            var layer = layui.layer;
            var $ = layui.jquery;
            var element = layui.element;
            var successCodes = {1000: true, 1001: true, 1002: true, 1003: true};
            var currentView = 'zero_candidates';
            var recordTableRendered = false;

            CrmLang.switchUI();

            renderWhsExpZeroCandidates();
            form.render();
            renderIcons();

            document.getElementById('reloadWhsExpZero').onclick = function() {
                if (currentView === 'zero_records') {
                    reloadWhsExpZeroRecords();
                    return;
                }

                table.reload('whsExpZeroTable');
            };

            form.on('submit(searchWhsExpZeroCandidates)', function(data) {
                table.reload('whsExpZeroTable', {where: data.field, page: {curr: 1}});
                return false;
            });

            form.on('submit(searchWhsExpZeroRecords)', function(data) {
                reloadWhsExpZeroRecords(data.field);
                return false;
            });

            $('#resetWhsExpZeroCandidates').on('click', function() {
                setTimeout(function() {
                    table.reload('whsExpZeroTable', {
                        where: {user_id: '', user_name: ''},
                        page: {curr: 1}
                    });
                }, 0);
            });

            $('#resetWhsExpZeroRecords').on('click', function() {
                setTimeout(function() {
                    reloadWhsExpZeroRecords({
                        user_id: '',
                        user_name: '',
                        status: '',
                        start_date: '',
                        end_date: ''
                    });
                }, 0);
            });

            element.on('tab(whsExpZeroTabs)', function() {
                currentView = $(this).attr('lay-id') === 'zero_records' ? 'zero_records' : 'zero_candidates';
                syncTabAccessibility();

                if (currentView === 'zero_records') {
                    ensureWhsExpZeroRecords();
                    return;
                }

                table.resize('whsExpZeroTable');
            });

            table.on('tool(whsExpZeroTable)', function(obj) {
                if (obj.event !== 'oneKeyZero') {
                    return;
                }

                layer.confirm(CrmLang.t('admin.confirm_whs_exp_zero'), function(index) {
                    CrmAjax.request({
                        guard: 'admin',
                        url: '/api/admin/whsExpZero',
                        data: {user_id: obj.data.userId},
                        success: function(res) {
                            layer.close(index);
                            if (successCodes[res.code]) {
                                table.reload('whsExpZeroTable');
                                if (recordTableRendered) {
                                    table.reload('whsExpZeroRecordTable');
                                }
                                layer.msg(res.message || CrmLang.t('common.success'), {icon: 1});
                                return;
                            }
                            layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                        }
                    });
                });
            });

            function renderWhsExpZeroCandidates() {
                table.render(CrmTable.layuiConfig('admin', {
                    elem: '#whsExpZeroTable',
                    id: 'whsExpZeroTable',
                    url: '/api/admin/whsExpZeroList',
                    cols: [[
                        {field: 'userId', title: CrmLang.t('admin.userId'), width: 120, sort: true},
                        {field: 'userName', title: CrmLang.t('admin.user_name'), minWidth: 160},
                        {field: 'userBalance', title: CrmLang.t('admin.user_balance'), width: 140, sort: true},
                        {field: 'userCredit', title: CrmLang.t('admin.user_credit'), width: 140},
                        {field: 'needZeroAmount', title: CrmLang.t('admin.need_zero_amount'), width: 160},
                        {fixed: 'right', title: CrmLang.t('common.action'), toolbar: '#whsExpZeroActions', width: 140}
                    ]],
                    parseData: CrmTable.layuiParseData(),
                    done: afterTableRender
                }));
            }

            function ensureWhsExpZeroRecords() {
                if (recordTableRendered) {
                    table.resize('whsExpZeroRecordTable');
                    return;
                }

                renderWhsExpZeroRecords({});
            }

            function reloadWhsExpZeroRecords(where) {
                if (!recordTableRendered) {
                    renderWhsExpZeroRecords(where || {});
                    return;
                }

                if (where) {
                    table.reload('whsExpZeroRecordTable', {where: where, page: {curr: 1}});
                    return;
                }

                table.reload('whsExpZeroRecordTable');
            }

            function renderWhsExpZeroRecords(where) {
                recordTableRendered = true;
                table.render(CrmTable.layuiConfig('admin', {
                    elem: '#whsExpZeroRecordTable',
                    id: 'whsExpZeroRecordTable',
                    url: '/api/admin/whsExpZeroRecords',
                    where: where || {},
                    cols: [[
                        {field: 'id', title: 'ID', width: 90, sort: true},
                        {field: 'user_id', title: CrmLang.t('admin.user_id'), width: 120, sort: true},
                        {field: 'user_name', title: CrmLang.t('admin.user_name'), minWidth: 160},
                        {field: 'balance_before', title: CrmLang.t('admin.user_balance'), width: 140, sort: true},
                        {field: 'credit_amount', title: CrmLang.t('admin.user_credit'), width: 130},
                        {field: 'zero_amount', title: CrmLang.t('admin.need_zero_amount'), width: 145},
                        {field: 'status_name', title: CrmLang.t('admin.status'), width: 110},
                        {field: 'created_at', title: CrmLang.t('common.created_at'), width: 175, sort: true},
                        {field: 'processed_at', title: CrmLang.t('admin.processed_at'), width: 175}
                    ]],
                    parseData: CrmTable.layuiParseData(),
                    done: afterTableRender
                }));
            }

            function afterTableRender() {
                CrmLang.switchUI();
                refreshPermissions();
                renderIcons();
            }

            function syncTabAccessibility() {
                $('[lay-filter="whsExpZeroTabs"] .layui-tab-title [lay-id]').each(function() {
                    $(this).attr('aria-selected', $(this).attr('lay-id') === currentView ? 'true' : 'false');
                });
            }

            function refreshPermissions() {
                if (window.CrmAdminPermissions && window.CrmAdminPermissions.refresh) {
                    window.CrmAdminPermissions.refresh();
                }
            }

            function renderIcons() {
                if (window.lucide && window.lucide.createIcons) {
                    window.lucide.createIcons();
                }
            }
        });
    });

    registry['withdraw-flows/index'] = once(function () {
        // Source: withdraw-flows/index.js
        layui.use(['table', 'form', 'layer', 'jquery', 'laydate'], function() {
            var table = layui.table;
            var form = layui.form;
            var layer = layui.layer;
            var $ = layui.jquery;
            var laydate = layui.laydate;

            CrmLang.switchUI();

            // start_date/end_date：出金流水时间范围，对应后端按 mt4_trades.close_time 过滤的日期参数。
            laydate.render({elem: '#withdrawFlowStartDate', type: 'date'});
            laydate.render({elem: '#withdrawFlowEndDate', type: 'date'});

            // 表格数据来自 /api/admin/withdrawFlowList，后端会按 permissions.api_route 和 AdminDataScopeService 二次校验。
            table.render(CrmTable.layuiConfig('admin', {
                elem: '#withdrawFlowTable',
                id: 'withdrawFlowTable',
                url: '/api/admin/withdrawFlowList',
                cols: [[
                    {field: 'id', title: 'ID', width: 90, sort: true},
                    {field: 'ticket', title: CrmLang.t('admin.ticket'), width: 130},
                    {field: 'login', title: CrmLang.t('admin.userId'), width: 120},
                    {field: 'user_name', title: CrmLang.t('admin.user_name'), width: 160, templet: userNameText},
                    {field: 'symbol', title: CrmLang.t('admin.symbol'), width: 120},
                    {field: 'cmd', title: CrmLang.t('admin.trade_cmd'), width: 90},
                    {field: 'profit', title: CrmLang.t('admin.profit'), width: 130},
                    {field: 'flow_source_name', title: CrmLang.t('admin.flow_source_name'), width: 150},
                    {field: 'comment', title: CrmLang.t('admin.comment'), minWidth: 220},
                    {field: 'commission', title: CrmLang.t('admin.commission'), width: 130},
                    {field: 'swaps', title: CrmLang.t('admin.swaps'), width: 120},
                    {field: 'open_time', title: CrmLang.t('admin.open_time'), width: 170},
                    {field: 'close_time', title: CrmLang.t('admin.close_time'), width: 170}
                ]],
                totalRow: true,
                parseData: CrmTable.layuiParseData(),
                done: function() {
                    CrmLang.switchUI();
                }
            }));

            document.getElementById('reloadWithdrawFlows').onclick = function() {
                table.reload('withdrawFlowTable');
            };

            $('#exportWithdrawFlows').on('click', function() {
                downloadAdminCsv($, layer, '/api/admin/exportWithdrawFlows', serializeForm($, '#withdrawFlowSearchForm'), 'withdraw_flows_export.csv');
            });

            form.on('submit(searchWithdrawFlows)', function(data) {
                table.reload('withdrawFlowTable', {where: data.field, page: {curr: 1}});
                return false;
            });

            /**
             * 返回交易所属用户名称。
             *
             * @param {Object} row 出金流水行；user 为后端 with('user') 返回的业务用户对象。
             * @returns {string} 用户名，缺失时返回空字符串。
             */
            function userNameText(row) {
                return row.user_name || (row.user && row.user.user_name ? row.user.user_name : '');
            }
        });
    });

    registry['withdraw-imports/index'] = once(function () {
        // Source: withdraw-imports/index.js
        layui.use(['table', 'form', 'layer', 'jquery'], function() {
            var table = layui.table;
            var form = layui.form;
            var layer = layui.layer;
            var $ = layui.jquery;
            var successCodes = {1000: true, 1001: true, 1002: true};

            CrmLang.switchUI();

            // user_id：业务用户 ID；batch_no：导入批次号；is_synced：同步状态，三个筛选参数都会传给后端查询。
            table.render(CrmTable.layuiConfig('admin', {
                elem: '#withdrawImportTable',
                id: 'withdrawImportTable',
                url: '/api/admin/withdrawImportList',
                cols: [[
                    {field: 'id', title: 'ID', width: 90, sort: true},
                    {field: 'user_id', title: CrmLang.t('admin.userId'), width: 120},
                    {field: 'user_name', title: CrmLang.t('admin.user_name'), width: 160},
                    {field: 'amount', title: CrmLang.t('admin.amount'), width: 130},
                    {field: 'batch_no', title: CrmLang.t('admin.batch_no'), width: 180},
                    {field: 'mt4_order_id', title: CrmLang.t('admin.mt4_order_id'), width: 140},
                    {field: 'is_synced', title: CrmLang.t('admin.sync_status'), width: 130, templet: syncStatusText},
                    {field: 'fail_reason', title: CrmLang.t('admin.fail_reason'), width: 220},
                    {field: 'created_at', title: CrmLang.t('user.createdAt'), width: 180},
                    {fixed: 'right', title: CrmLang.t('common.action'), toolbar: '#withdrawImportActions', width: 190}
                ]],
                parseData: CrmTable.layuiParseData(),
                done: function() {
                    CrmLang.switchUI();
                    refreshPermissions();
                }
            }));

            document.getElementById('reloadWithdrawImports').onclick = function() {
                table.reload('withdrawImportTable');
            };

            $('#downloadWithdrawImportTemplate').on('click', function() {
                downloadAdminCsv($, layer, '/api/admin/withdrawImportTemplate', {}, 'withdraw_import_template.csv');
            });

            $('#exportWithdrawImports').on('click', function() {
                downloadAdminCsv($, layer, '/api/admin/exportWithdrawImports', serializeForm($, '#withdrawImportSearchForm'), 'withdraw_imports_export.csv');
            });

            $('#addWithdrawImport').on('click', function() {
                form.val('withdrawImportForm', {
                    user_id: '',
                    user_name: '',
                    amount: '',
                    batch_no: buildDefaultBatchNo(),
                    mt4_order_id: 0,
                    remarks: ''
                });

                layer.open({
                    type: 1,
                    title: CrmLang.t('admin.create_withdraw_import'),
                    area: ['620px', '560px'],
                    content: $('#withdrawImportModal')
                });
                form.render();
            });

            // CSV 批量导入弹窗：共享上传组件（deferred 模式缓存文件），提交走 CrmAjax.upload 携带管理员令牌。
            $('#importWithdrawImportFile').on('click', function() {
                layer.open({
                    type: 1,
                    title: CrmLang.t('admin.import_csv_file'),
                    area: ['560px', '420px'],
                    content: $('#withdrawImportUploadModal')
                });
                if (window.CrmUpload) { CrmUpload.init(document); }
                if (window.lucide && lucide.createIcons) { lucide.createIcons(); }
            });

            $('#submitWithdrawImportFile').on('click', function() {
                var file = window.CrmUpload ? CrmUpload.file('withdraw_import_csv') : null;
                if (!file) {
                    layer.msg(CrmLang.t('front.no_file_selected'), {icon: 0});
                    return;
                }
                var formData = new FormData();
                formData.append('file', file);
                CrmAjax.upload({
                    guard: 'admin',
                    url: '/api/admin/createWithdrawImport',
                    formData: formData,
                    success: function(res) {
                        if (successCodes[res.code]) {
                            var count = Array.isArray(res.data) ? res.data.length : 0;
                            layer.closeAll();
                            if (window.CrmUpload) { CrmUpload.reset('withdraw_import_csv', false); }
                            table.reload('withdrawImportTable');
                            layer.msg((CrmLang.t('admin.import_csv_result') || '').replace(':count', count), {icon: 1});
                            return;
                        }
                        layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                    },
                    error: function(res) {
                        layer.msg((res && res.message) || CrmLang.t('common.error'), {icon: 2});
                    }
                });
            });

            form.on('submit(searchWithdrawImports)', function(data) {
                table.reload('withdrawImportTable', {where: data.field, page: {curr: 1}});
                return false;
            });

            table.on('tool(withdrawImportTable)', function(obj) {
                if (obj.event === 'syncWithdrawImport') {
                    requestWithdrawImportAction(
                        obj,
                        '/api/admin/syncWithdrawImport/',
                        'admin.confirm_sync_import'
                    );
                    return;
                }

                if (obj.event !== 'retryWithdrawImport') {
                    return;
                }

                // obj.data.id：withdraw_imports 主键；后端只允许失败状态记录重试，防止成功出金重复进入队列。
                requestWithdrawImportAction(
                    obj,
                    '/api/admin/retryWithdrawImport/',
                    'admin.confirm_retry_import'
                );
            });

            form.on('submit(saveWithdrawImport)', function(data) {
                CrmAjax.request({
                    guard: 'admin',
                    url: '/api/admin/createWithdrawImport',
                    data: data.field,
                    success: function(res) {
                        if (successCodes[res.code]) {
                            layer.closeAll();
                            table.reload('withdrawImportTable');
                            layer.msg(res.message || CrmLang.t('common.success'), {icon: 1});
                            return;
                        }
                        layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                    }
                });

                return false;
            });

            /**
             * 将出金导入同步状态转换为页面文案。
             *
             * @param {Object} row 出金导入记录行；is_synced 为 0/1/2。
             * @returns {string} 同步状态文案。
             */
            function syncStatusText(row) {
                var value = Number(row.is_synced);
                if (value === 1) return CrmLang.t('admin.import_synced');
                if (value === 2) return CrmLang.t('admin.import_failed');
                return CrmLang.t('admin.import_pending');
            }

            /**
             * 执行单条出金导入记录动作，并在响应后刷新 fail_reason / 同步状态。
             *
             * @param {Object} obj Layui 表格行事件对象。
             * @param {string} endpoint API 前缀，最后会拼接导入记录 id。
             * @param {string} confirmKey 确认弹窗多语言 key。
             * @returns {void}
             */
            function requestWithdrawImportAction(obj, endpoint, confirmKey) {
                layer.confirm(CrmLang.t(confirmKey), function(index) {
                    CrmAjax.request({
                        guard: 'admin',
                        url: endpoint + encodeURIComponent(obj.data.id),
                        data: {},
                        success: function(res) {
                            layer.close(index);
                            table.reload('withdrawImportTable');
                            if (successCodes[res.code]) {
                                layer.msg(res.message || CrmLang.t('common.success'), {icon: 1});
                                return;
                            }
                            layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                        }
                    });
                });
            }

            /**
             * 生成默认批次号，保证手工新增记录也有可追踪批次。
             *
             * @returns {string} 默认批次号，格式为 WDR-时间戳。
             */
            function buildDefaultBatchNo() {
                return 'WDR-' + Date.now();
            }

            /**
             * 表格刷新后重新执行按钮权限显示控制。
             *
             * @returns {void}
             */
            function refreshPermissions() {
                if (window.CrmAdminPermissions && window.CrmAdminPermissions.refresh) {
                    window.CrmAdminPermissions.refresh();
                }
            }
        });
    });

    registry['withdrawals/index'] = once(function () {
        // Source: withdrawals/index.js
        layui.use(['table', 'form', 'layer', 'laydate', 'jquery'], function() {
            var table = layui.table, form = layui.form, layer = layui.layer, laydate = layui.laydate, $ = layui.$;

            CrmLang.switchUI();
            laydate.render({elem: '#withdrawStartDate', type: 'date'});
            laydate.render({elem: '#withdrawEndDate', type: 'date'});

            function refreshPermissions() {
                // 重新应用按钮权限：Layui 表格重载后会重新生成操作列按钮，必须再次按 permissions.slug 隐藏无权限按钮。
                if (window.CrmAdminPermissions && window.CrmAdminPermissions.refresh) {
                    window.CrmAdminPermissions.refresh();
                }
            }

            function currentWithdrawFilters() {
                return serializeForm($, '#withdrawSearchForm');
            }

            // 出金审核列表：数据来自 /api/admin/withdrawals，后端会按管理员角色和数据范围过滤可见出金申请。
            // user_id 表示出金申请人；apply_amount 表示出金申请金额；status 表示出金处理状态，所有动作最终以后端权限和状态流转校验结果为准。
            table.render(CrmTable.layuiConfig('admin', {
                elem: '#withdrawTable',
                id: 'withdrawTable',
                url: '/api/admin/withdrawals',
                where: currentWithdrawFilters(),
                cols: [[
                    // 批量审核勾选列：对齐旧四个出金状态页的 checkbox 列。
                    // 终态记录（status=2 已完成 / 3 已拒绝）在 done 回调里禁用勾选，避免提交注定失败的行。
                    {type: 'checkbox', fixed: 'left'},
                    {field: 'id', title: 'ID', width: 90, sort: true},
                    {field: 'local_order_no', title: CrmLang.t('admin.local_order_no'), minWidth: 190},
                    {field: 'mt4_ticket', title: CrmLang.t('admin.mt4_ticket'), width: 150},
                    {field: 'user_id', title: CrmLang.t('admin.userId'), width: 120},
                    {field: 'user_name', title: CrmLang.t('admin.user_name'), minWidth: 150},
                    {field: 'apply_amount', title: CrmLang.t('admin.apply_amount'), width: 140},
                    {field: 'actual_amount', title: CrmLang.t('admin.actual_amount'), width: 140},
                    {field: 'fee', title: CrmLang.t('admin.fee'), width: 110},
                    {field: 'status', title: CrmLang.t('admin.status'), width: 120},
                    {field: 'created_at', title: CrmLang.t('user.createdAt'), width: 180},
                    {fixed: 'right', title: CrmLang.t('common.action'), toolbar: '#withdrawActions', width: 300}
                ]],
                parseData: function(res) {
                    // 需求 9：后端在 paginator 之外追加 summary，这里把它写进独立统计区块。
                    renderAdminTableStatistics('#withdrawSummaryCards', res && res.data ? res.data.summary : {});

                    return CrmTable.layuiParseData()(res);
                },
                done: function(res) {
                    CrmLang.switchUI();
                    refreshPermissions();
                    disableTerminalWithdrawCheckboxes(res);
                }
            }));

            // 旧四个状态页在 done 里按 applystatus 禁用终态行的勾选框；新表格字段为 status，语义一致。
            // status=2（已完成）与 3（已拒绝）都是终态，后端状态机必然拒绝，因此在 UI 侧先行禁用，
            // 避免管理员误勾后收到一批注定失败的结果。
            function disableTerminalWithdrawCheckboxes(res) {
                var rows = (res && res.data) || [];

                // Layui 把渲染后的表格容器标记为 .layui-table-view[lay-id=<表格id>]，
                // 按 lay-id 定位比依赖 DOM 相邻关系稳健（分页器/工具条会插在中间）。
                $('.layui-table-view[lay-id="withdrawTable"]')
                    .find('.layui-table-body tr').each(function() {
                        var rowIndex = $(this).data('index');
                        var row = rows[rowIndex];
                        var status;

                        if (!row) {
                            return;
                        }

                        status = String(row.status);
                        if (status === '2' || status === '3') {
                            $(this).find('input[type="checkbox"]')
                                .prop('disabled', true)
                                .closest('.layui-form-checkbox')
                                .addClass('layui-checkbox-disabled layui-disabled');
                        }
                    });
            }

            form.on('submit(searchWithdraws)', function() {
                table.reload('withdrawTable', {where: currentWithdrawFilters(), page: {curr: 1}});
                return false;
            });

            $('#withdrawSearchForm').on('reset', function() {
                window.setTimeout(function() {
                    table.reload('withdrawTable', {where: currentWithdrawFilters(), page: {curr: 1}});
                }, 0);
            });

            $('#exportWithdrawals').on('click', function() {
                downloadAdminCsv($, layer, '/api/admin/exportWithdrawals', currentWithdrawFilters(), 'withdrawals_export.csv');
            });

            // ===== 出金批量审核 =====
            // 旧后台在待处理/处理中/已出款/失败四个状态页各有一份「批量操作」入口，
            // 新后台合并为单页 + status 筛选，因此这里只实现一套，语义与旧逻辑逐条对齐：
            // 1) 必须先勾选记录；2) 只允许来源状态 0（待处理）或 1（处理中）；
            // 3) 勾选行的来源状态必须一致（否则同一目标状态对部分行非法）；
            // 4) 跃迁约束 0→{1,2,3}、1→{2,3}；5) 目标状态为 3（拒绝）时备注必填。
            // 前端校验只用于减少无效往返，后端 batchWithdrawApply 会按 payload.status 改判权限并逐条复校状态机。

            // 各来源状态允许的目标状态白名单，与旧 updateRadioButtons 的禁用规则等价。
            var WITHDRAW_BATCH_TRANSITIONS = {
                '0': ['1', '2', '3'],
                '1': ['2', '3']
            };

            var batchWithdrawLayerIndex = null;

            // 读取当前勾选行并按旧规则校验，返回 null 表示校验未通过（已弹提示）。
            function collectBatchWithdrawRows() {
                var checked = table.checkStatus('withdrawTable').data || [];
                var processable;
                var sourceStatus;
                var uniform;

                if (checked.length === 0) {
                    layer.msg(CrmLang.t('admin.batch_select_records_first'), {icon: 0});
                    return null;
                }

                // 只保留来源状态在白名单内的行；终态行虽已在 done 里禁用勾选，此处仍复校一次。
                processable = checked.filter(function(row) {
                    return Object.prototype.hasOwnProperty.call(WITHDRAW_BATCH_TRANSITIONS, String(row.status));
                });
                if (processable.length === 0) {
                    layer.msg(CrmLang.t('admin.batch_select_processable_only'), {icon: 2});
                    return null;
                }

                // 来源状态必须一致：旧逻辑用首行状态推导可选目标，混合状态会让同一目标对部分行非法。
                sourceStatus = String(processable[0].status);
                uniform = processable.every(function(row) {
                    return String(row.status) === sourceStatus;
                });
                if (!uniform) {
                    layer.msg(CrmLang.t('admin.batch_select_same_status'), {icon: 2});
                    return null;
                }

                return {rows: processable, sourceStatus: sourceStatus};
            }

            // 按来源状态禁用非法目标项，并清空上一次的选择与备注，避免残留值被误提交。
            function prepareBatchWithdrawForm(sourceStatus) {
                var allowed = WITHDRAW_BATCH_TRANSITIONS[sourceStatus] || [];

                $('#batchWithdrawForm input[name="target_status"]').each(function() {
                    var value = String($(this).val());
                    var isAllowed = allowed.indexOf(value) !== -1;

                    $(this).prop('checked', false).prop('disabled', !isAllowed);
                });
                $('#batchWithdrawRemark').val('');
                form.render('radio', 'batchWithdrawForm');
            }

            // 提交批量审核：走旧 URI batchWithdrawApply，它按 payload.status 动态改判权限并逐条复用现代状态机。
            // 该路由位于 web 中间件组内，因此除 JWT 外必须补 X-CSRF-TOKEN（与登录入口同一惯例）。
            function submitBatchWithdraw(payloadRows, sourceStatus) {
                var targetStatus = String($('#batchWithdrawForm input[name="target_status"]:checked').val() || '');
                var remark = String($('#batchWithdrawRemark').val() || '').trim();
                var allowed = WITHDRAW_BATCH_TRANSITIONS[sourceStatus] || [];

                if (targetStatus === '') {
                    layer.msg(CrmLang.t('admin.batch_target_status_required'), {icon: 0});
                    return;
                }
                if (allowed.indexOf(targetStatus) === -1) {
                    layer.msg(CrmLang.t('admin.batch_target_status_invalid'), {icon: 2});
                    return;
                }
                // 目标为拒绝时备注必填：后端 reject() 要求非空 reason 且不超过 500 字，空备注会整批失败。
                if (targetStatus === '3' && remark === '') {
                    layer.msg(CrmLang.t('admin.reject_reason_required'), {icon: 0});
                    return;
                }

                CrmAjax.request({
                    guard: 'admin',
                    url: '/index/admin/amount/batchWithdrawApply',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || ''
                    },
                    data: {
                        payload: {
                            status: targetStatus,
                            remark: remark,
                            orderList: payloadRows.map(function(row) {
                                // recordId 必须是 withdraw_records 主键；userId 与 t4Ticket 仅供后端留痕，不作为定位依据。
                                return {
                                    recordId: row.id,
                                    userId: row.user_id,
                                    t4Ticket: row.mt4_ticket
                                };
                            })
                        }
                    },
                    success: function(res) {
                        var data = (res && res.data) || {};
                        var succeeded;
                        var failed;
                        var summary;

                        // 载荷整体被拒时后端走 error() 分支，data 为空对象且没有逐条结果，
                        // 此时不能按 0/0 渲染「成功 0 条」误导管理员，直接展示后端错误消息并保留弹窗待修正。
                        if (typeof data.total === 'undefined') {
                            layer.msg((res && res.message) || CrmLang.t('common.error'), {icon: 2});
                            return;
                        }

                        succeeded = Number(data.success || 0);
                        failed = Number(data.failed || 0);
                        summary = CrmLang.t('admin.batch_withdraw_result')
                            .replace(':success', String(succeeded))
                            .replace(':failed', String(failed));

                        if (batchWithdrawLayerIndex !== null) {
                            layer.close(batchWithdrawLayerIndex);
                            batchWithdrawLayerIndex = null;
                        }

                        // 全成功用成功图标，部分或全部失败用警告图标，并保留后端逐条结果的聚合数字。
                        layer.msg(summary, {icon: failed === 0 ? 1 : 2, time: 3000});
                        table.reload('withdrawTable', {where: currentWithdrawFilters()});
                    },
                    error: function(res) {
                        layer.msg((res && res.message) || CrmLang.t('common.error'), {icon: 2});
                    }
                });
            }

            $('#batchWithdrawButton').on('click', function() {
                var selection = collectBatchWithdrawRows();

                if (!selection) {
                    return;
                }

                prepareBatchWithdrawForm(selection.sourceStatus);
                batchWithdrawLayerIndex = layer.open({
                    type: 1,
                    title: CrmLang.t('admin.batch_withdraw_title'),
                    area: ['min(520px, calc(100vw - 32px))', 'min(420px, calc(100vh - 32px))'],
                    content: $('#batchWithdrawModal'),
                    success: function() {
                        CrmLang.switchUI();
                        form.render(null, 'batchWithdrawForm');
                    },
                    end: function() {
                        batchWithdrawLayerIndex = null;
                    }
                });

                // 提交与取消绑定在弹窗内按钮上：用 off 先解绑，避免多次打开弹窗后回调累积重复提交。
                $('#batchWithdrawSubmit').off('click').on('click', function() {
                    submitBatchWithdraw(selection.rows, selection.sourceStatus);
                });
                $('#batchWithdrawCancel').off('click').on('click', function() {
                    if (batchWithdrawLayerIndex !== null) {
                        layer.close(batchWithdrawLayerIndex);
                        batchWithdrawLayerIndex = null;
                    }
                });
            });

            function updateWithdraw(apiUrl, id, extraData) {
                // id 表示出金申请主键，后端会据此读取记录、校验数据范围并判断状态流转是否合法。
                CrmAjax.request({
                    guard: 'admin',
                    url: apiUrl,
                    data: $.extend({id: id}, extraData || {}),
                    success: function(res) {
                        if (res.code === 1000 || res.code === 1002) {
                            table.reload('withdrawTable');
                            layer.msg(CrmLang.t('common.success'), {icon: 1});
                            return;
                        }
                        layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                    }
                });
            }

            function showWithdrawDetail(data) {
                var statusLabels = [
                    CrmLang.t('admin.pending'),
                    CrmLang.t('admin.processing'),
                    CrmLang.t('admin.completed'),
                    CrmLang.t('admin.rejected')
                ];
                var fields = [
                    ['local_order_no', CrmLang.t('admin.local_order_no')],
                    ['mt4_ticket', CrmLang.t('admin.mt4_ticket')],
                    ['user_id', CrmLang.t('admin.userId')],
                    ['user_name', CrmLang.t('admin.user_name')],
                    ['apply_amount', CrmLang.t('admin.apply_amount')],
                    ['actual_amount', CrmLang.t('admin.actual_amount')],
                    ['fee', CrmLang.t('admin.fee')],
                    ['exchange_rate', CrmLang.t('admin.exchange_rate')],
                    ['bank_no', CrmLang.t('admin.bank_no')],
                    ['bank_name', CrmLang.t('admin.bank_name')],
                    ['bank_addr', CrmLang.t('admin.bank_addr')],
                    ['reject_reason', CrmLang.t('admin.reject_reason')],
                    ['created_at', CrmLang.t('user.createdAt')],
                    ['updated_at', CrmLang.t('user.updatedAt')]
                ];
                var $content = $('<div class="withdraw-detail-page crm-withdraw-detail" role="document"><section class="withdraw-detail-section"><dl class="withdraw-detail-facts"></dl></section></div>');
                var $list = $content.find('dl');

                fields.forEach(function(field) {
                    $('<dt></dt>').text(field[1]).appendTo($list);
                    $('<dd></dd>').text(data[field[0]] === null || data[field[0]] === undefined || data[field[0]] === '' ? '-' : data[field[0]]).appendTo($list);
                });
                $('<dt></dt>').text(CrmLang.t('admin.status')).appendTo($list);
                $('<dd></dd>').text(statusLabels[Number(data.status)] || String(data.status || '-')).appendTo($list);

                layer.open({
                    type: 1,
                    title: CrmLang.t('common.detail'),
                    area: window.innerWidth < 768 ? ['92vw', '80vh'] : ['720px', 'auto'],
                    content: $content.prop('outerHTML')
                });
            }

            table.on('tool(withdrawTable)', function(obj) {
                // process 表示标记出金处理中；complete 表示完成出金记录；reject 表示拒绝出金记录，三个按钮都受 permissions.slug 控制显示。
                if (obj.event === 'detail') showWithdrawDetail(obj.data);
                if (obj.event === 'process') updateWithdraw('/api/admin/withdrawProcess', obj.data.id);
                if (obj.event === 'complete') updateWithdraw('/api/admin/withdrawComplete', obj.data.id);
                if (obj.event === 'reject') {
                    layer.prompt({formType: 2, title: CrmLang.t('admin.reject_reason')}, function(reason, index) {
                        reason = String(reason || '').trim();
                        if (!reason) {
                            layer.msg(CrmLang.t('admin.reject_reason_required'), {icon: 2});
                            return;
                        }
                        layer.close(index);
                        updateWithdraw('/api/admin/withdrawReject', obj.data.id, {reason: reason});
                    });
                }
            });
        });
    });

    registry['legacy/users/index'] = once(function () {
        layui.use(['table', 'form'], function () {
            var table = layui.table;
            var tableNode = document.getElementById('userTable');
            var config;

            if (!tableNode) {
                return;
            }

            config = {
                url: tableNode.getAttribute('data-url') || '',
                accountType: tableNode.getAttribute('data-account-type') || '',
                keyword: tableNode.getAttribute('data-keyword') || '',
                detailRoute: tableNode.getAttribute('data-detail-route') || 'admin_page_users_detail',
                labels: {
                    id: tableNode.getAttribute('data-label-id') || 'ID',
                    userName: tableNode.getAttribute('data-label-user-name') || 'User name',
                    email: tableNode.getAttribute('data-label-email') || 'Email',
                    phone: tableNode.getAttribute('data-label-phone') || 'Phone',
                    accountType: tableNode.getAttribute('data-label-account-type') || 'Account type',
                    parentAgent: tableNode.getAttribute('data-label-parent-agent') || 'Parent agent',
                    familyTree: tableNode.getAttribute('data-label-family-tree') || 'Family tree',
                    createdAt: tableNode.getAttribute('data-label-created-at') || 'Created at',
                    operation: tableNode.getAttribute('data-label-operation') || 'Operation',
                    view: tableNode.getAttribute('data-label-view') || 'View',
                    noData: tableNode.getAttribute('data-label-no-data') || 'No data'
                }
            };

            table.render({
                elem: '#userTable',
                url: config.url,
                where: {
                    account_type: config.accountType,
                    keyword: config.keyword
                },
                page: true,
                cols: [[
                    {field: 'user_id', title: config.labels.id, width: 100, sort: true},
                    {field: 'user_name', title: config.labels.userName, width: 150},
                    {field: 'email', title: config.labels.email, width: 200},
                    {field: 'phone', title: config.labels.phone, width: 130},
                    {field: 'account_type', title: config.labels.accountType, width: 120},
                    {field: 'total_yuerj', title: 'Total deposit', width: 130},
                    {field: 'total_yuecj', title: 'Total withdrawal', width: 130},
                    {field: 'total_net_worth', title: 'Net worth', width: 130},
                    {field: 'total_comm', title: 'Commission', width: 130},
                    {field: 'total_profit', title: 'Profit', width: 130},
                    {field: 'total_volume', title: 'Volume', width: 130},
                    {field: 'total_swaps', title: 'Swaps', width: 130},
                    {field: 'parent_id', title: config.labels.parentAgent, width: 100},
                    {field: 'family_tree', title: config.labels.familyTree, minWidth: 200},
                    {field: 'created_at', title: config.labels.createdAt, width: 170},
                    {fixed: 'right', title: config.labels.operation, width: 100, templet: function (row) {
                        var detailUrl = crmRoute(config.detailRoute, {id: row.user_id}, '#');
                        return '<a class="layui-btn layui-btn-xs" href="' + detailUrl + '">' + config.labels.view + '</a>';
                    }}
                ]],
                text: {none: config.labels.noData}
            });
        });
    });

    onReady(runMarkedPages);

    exports('adminPages', {
        run: run,
        has: has,
        registry: registry
    });
});
