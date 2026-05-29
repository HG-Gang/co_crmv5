(function () {
    'use strict';

    if (window.__CRM_PLAIN_NAIVE_BOOTED__) {
        return;
    }
    window.__CRM_PLAIN_NAIVE_BOOTED__ = true;

    var boot = window.CrmNaiveBoot || {};
    var app = document.getElementById('naive-crm-app');
    var ECharts = window.echarts;
    var guard = boot.guard === 'admin' ? 'admin' : 'front';
    var tokenKeys = guard === 'admin' ? ['admin_token', 'admin_jwt_token'] : ['front_token', 'front_jwt_token'];
    var basePath = guard === 'admin' ? '/admin-naive' : '/front-naive';
    var legacyBasePath = guard === 'admin' ? '/admin' : '/front';
    var currentPage = boot.page || 'dashboard';
    var legacySkinMap = {
        emerald: 'light',
        blue: 'sea',
        amber: 'warm',
        violet: 'contrast'
    };
    var skin = normalizeSkin(window.CrmTheme ? CrmTheme.get() : (localStorage.getItem('front_theme') || localStorage.getItem('crm_theme') || localStorage.getItem('crm_naive_skin') || 'light'));
    var uiStyle = localStorage.getItem('crm_ui_style') || localStorage.getItem(guard + '_ui_style') || 'naive';
    var locale = normalizeLocale((window.CrmLang && CrmLang.getLocale && CrmLang.getLocale()) || boot.locale || localStorage.getItem('crm_locale') || localStorage.getItem('front_lang') || 'zh-CN');
    var chartInstances = [];
    var currentRows = [];
    var currentStats = [];
    var currentTableFilters = {};
    var currentTableConfig = null;
    var resizeBound = false;

    var skins = [
        { value: 'light', label: '浅色', icon: '☀', en: 'Light' },
        { value: 'dark', label: '深色', icon: '☾', en: 'Dark' },
        { value: 'sea', label: '海蓝', icon: '≋', en: 'Sea Blue' },
        { value: 'warm', label: '暖色', icon: '◐', en: 'Warm' },
        { value: 'contrast', label: '高对比', icon: '▣', en: 'High Contrast' }
    ];

    var frontMenus = [
        { key: 'dashboard', label: tr('menu.front_dashboard', '控制台', 'Dashboard'), icon: 'D' },
        { key: 'profile', label: tr('menu.front_profile_info', '个人资料', 'Profile'), icon: 'P' },
        { key: 'account', label: tr('menu.front_account_info', '账户综合 / 余额', 'Account Overview'), icon: 'A' },
        { key: 'vouchers', label: tr('menu.front_voucher', '凭证审核', 'Vouchers'), icon: 'V' },
        { key: 'deposits', label: tr('menu.front_deposit', '入金管理', 'Deposit'), icon: 'I' },
        { key: 'withdrawals', label: tr('menu.front_withdraw', '出金管理', 'Withdraw'), icon: 'O' },
        { key: 'flow', label: tr('menu.front_flow', '账户流水', 'Account Flow'), icon: 'F' },
        { key: 'position-summary', label: tr('menu.front_position_summary', '仓位总结', 'Position Summary'), icon: 'S' },
        { key: 'open-orders', label: tr('menu.front_open_orders', '持仓订单', 'Open Orders'), icon: 'O' },
        { key: 'closed-orders', label: tr('menu.front_closed_orders', '历史订单', 'Closed Orders'), icon: 'H' },
        { key: 'agent-sub', label: tr('menu.front_agent_sub', '下级代理', 'Sub Agents'), icon: 'G' },
        { key: 'agent-customers', label: tr('menu.front_agent_customers', '直属客户', 'Customers'), icon: 'C' },
        { key: 'agent-confirm', label: tr('menu.front_agent_confirm', '代理级别确认', 'Agent Level Confirm'), icon: 'L' },
        { key: 'group-change', label: tr('menu.front_group_change', '组别变更', 'Group Change'), icon: 'M' },
        { key: 'commission-realtime', label: tr('menu.front_commission_rt', '实时返佣', 'Real-time Commission'), icon: 'R' },
        { key: 'commission-history', label: tr('menu.front_commission_hist', '返佣历史', 'Commission History'), icon: 'H' },
        { key: 'commission-transfer', label: tr('menu.front_commission_transfer', '佣金转账', 'Commission Transfer'), icon: 'T' },
        { key: 'gift-address', label: tr('menu.front_gift_address', '地址管理', 'Address'), icon: 'A' },
        { key: 'gift-list', label: tr('menu.front_gift_list', '礼品列表', 'Gift List'), icon: 'G' },
        { key: 'news', label: tr('menu.front_news', '新闻公告', 'News'), icon: 'N' }
    ];

    var adminMenus = [
        { key: 'dashboard', label: tr('common.dashboard', '控制台', 'Dashboard'), icon: 'D' },
        { key: 'users', label: tr('user.title', '用户管理', 'Users'), icon: 'U' },
        { key: 'agents', label: tr('front.agent_level', '代理管理', 'Agents'), icon: 'A' },
        { key: 'deposits', label: tr('front.deposit', '入金审核', 'Deposits'), icon: 'I' },
        { key: 'withdrawals', label: tr('front.withdraw', '出金审核', 'Withdrawals'), icon: 'O' },
        { key: 'commissions', label: tr('front.commission', '佣金管理', 'Commissions'), icon: 'C' },
        { key: 'vouchers', label: tr('menu.front_voucher', '凭证记录', 'Vouchers'), icon: 'V' },
        { key: 'roles', label: tr('common.role', '角色管理', 'Roles'), icon: 'R' },
        { key: 'permissions', label: tr('common.permission', '权限管理', 'Permissions'), icon: 'P' },
        { key: 'menus', label: tr('common.menu', '菜单管理', 'Menus'), icon: 'M' },
        { key: 'agent-levels', label: tr('front.agent_level', '代理等级', 'Agent Levels'), icon: 'L' },
        { key: 'group-configs', label: tr('front.group_id', '组别配置', 'Groups'), icon: 'G' },
        { key: 'system-configs', label: tr('common.config', '系统配置', 'System Config'), icon: 'S' },
        { key: 'operation-logs', label: tr('common.operation', '操作日志', 'Operation Logs'), icon: 'O' },
        { key: 'channels', label: tr('front.payment_channel', '支付通道', 'Channels'), icon: 'P' },
        { key: 'admins', label: tr('common.admin', '管理员', 'Admins'), icon: 'A' },
        { key: 'news', label: tr('front.news_list', '新闻公告', 'News'), icon: 'N' }
    ];

    var frontModules = {
        dashboard: { title: tr('front.dashboard', '控制台', 'Dashboard'), desc: tr('front.dashboard_desc', '关键指标、注册链接、账户概况和近期公告。', 'Key metrics, register links, account overview and news.'), kind: 'dashboard', endpoint: '/dashboardData' },
        profile: { title: tr('front.profile', '个人资料', 'Profile'), desc: tr('front.profile_desc', '账户基本资料。', 'Basic account profile.'), kind: 'detail', endpoint: '/profileInfo', detailFields: ['user_id', 'user_name', 'email_masked', 'phone_masked', 'account_type', 'auth_status', 'id_card_no_masked', 'last_login_at'] },
        account: { title: tr('menu.front_account_info', '账户综合 / 余额', 'Account Overview'), desc: tr('front.account_overview_desc', '资金、余额、净值、认证和账户状态。', 'Funds, balance, equity, verification and account status.'), kind: 'detail', endpoint: '/accountInfo', detailFields: ['user_id', 'user_name', 'total_funds', 'equity', 'total_deposit', 'total_rebate', 'total_withdraw', 'open_order_count', 'closed_order_count', 'profit_7d', 'profit_15d', 'profit_30d'] },
        vouchers: { title: tr('menu.front_voucher', '凭证审核', 'Vouchers'), desc: tr('front.voucher_desc', '凭证列表。', 'Voucher list.'), endpoint: '/voucherList', fields: ['id', 'user_id', 'review_status', 'amount', 'created_at'] },
        deposits: { title: tr('menu.front_deposit', '入金管理', 'Deposits'), desc: tr('front.deposit_desc', '入金记录。', 'Deposit records.'), endpoint: '/depositHistory', fields: ['id', 'order_no', 'amount', 'payment_channel', 'status', 'created_at'] },
        withdrawals: { title: tr('menu.front_withdraw', '出金管理', 'Withdrawals'), desc: tr('front.withdraw_desc', '出金记录。', 'Withdrawal records.'), endpoint: '/withdrawHistory', fields: ['id', 'order_no', 'apply_amount', 'status', 'created_at'] },
        flow: { title: tr('menu.front_flow', '账户流水', 'Account Flow'), desc: tr('front.account_flow_desc', '资金变动流水。', 'Fund movement records.'), endpoint: '/accountFlow', fields: ['id', 'user_id', 'type', 'amount', 'balance', 'created_at'] },
        'position-summary': { title: tr('front.position_summary', '仓位总结', 'Position Summary'), desc: tr('front.position_summary_desc', '交易品种和盈亏汇总。', 'Trading symbols and profit summary.'), endpoint: '/positionSummary', noMock: true, fields: ['user_id', 'agent_level_name', 'user_name', 'total_yuerj', 'total_yuecj', 'total_rebate', 'total_net_worth', 'total_comm', 'total_profit', 'total_noble_metal', 'total_for_exca', 'total_crud_oil', 'total_index', 'total_currency', 'total_stock', 'total_volume', 'total_swaps', 'open_count', 'floating_profit'] },
        'open-orders': { title: tr('front.open_orders', '持仓订单', 'Open Orders'), desc: tr('front.open_orders_desc', '当前持仓订单。', 'Current open orders.'), endpoint: '/openOrders', fields: ['order_no', 'user_id', 'symbol', 'volume', 'open_time', 'profit'] },
        'closed-orders': { title: tr('menu.front_closed_orders', '历史订单', 'Closed Orders'), desc: tr('front.closed_orders_desc', '已平仓订单。', 'Closed orders.'), endpoint: '/closedOrders', fields: ['order_no', 'user_id', 'symbol', 'volume', 'open_time', 'close_time', 'profit'] },
        'agent-sub': { title: tr('menu.front_agent_sub', '下级代理', 'Sub Agents'), desc: tr('front.agent_sub_desc', '代理网络。', 'Agent network.'), endpoint: '/agentSubList', fields: ['user_id', 'user_name', 'email', 'account_type', 'created_at'] },
        'agent-customers': { title: tr('menu.front_agent_customers', '直属客户', 'Direct Customers'), desc: tr('front.agent_customers_desc', '客户列表。', 'Customer list.'), endpoint: '/agentCustomerList', fields: ['user_id', 'user_name', 'email', 'account_type', 'total_funds'] },
        'agent-confirm': { title: tr('menu.front_agent_confirm', '代理级别确认', 'Agent Level Confirm'), desc: tr('front.agent_confirm_desc', '级别确认状态。', 'Level confirmation status.'), kind: 'detail', endpoint: '/agentConfirmLevel' },
        'group-change': { title: tr('menu.front_group_change', '组别变更', 'Group Change'), desc: tr('front.group_change_desc', '组别变更记录。', 'Group change records.'), endpoint: '/agentGroupChangeList', fields: ['id', 'user_id', 'group_id', 'status', 'created_at'] },
        'commission-realtime': { title: tr('front.realtime_commission', '实时返佣', 'Real-time Commission'), desc: tr('front.realtime_commission_desc', '实时返佣订单。', 'Real-time commission orders.'), endpoint: '/commissionRealTime', collapsibleSummary: true, fields: ['ticket', 'login', 'symbol', 'volume_lots', 'profit_gain', 'profit_loss', 'profit_net', 'modify_time'] },
        'commission-history': { title: tr('front.commission_history', '返佣历史', 'Commission History'), desc: tr('front.commission_history_desc', '历史返佣记录。', 'Historical commission records.'), endpoint: '/commissionHistory', fields: ['id', 'agent_id', 'commission_amount', 'status', 'created_at'] },
        'commission-transfer': { title: tr('front.commission_transfer', '佣金转账', 'Commission Transfer'), desc: tr('front.commission_transfer_desc', '佣金转账记录。', 'Commission transfer records.'), endpoint: '/commissionHistory', fields: ['id', 'agent_id', 'commission_amount', 'status', 'created_at'] },
        'gift-address': { title: tr('front.gift_address', '地址管理', 'Address'), desc: tr('front.gift_address_desc', '收货地址。', 'Delivery addresses.'), endpoint: '/giftAddressList', fields: ['id', 'real_name', 'phone', 'address', 'is_default'] },
        'gift-list': { title: tr('front.gift_list', '礼品列表', 'Gift List'), desc: tr('front.gift_list_desc', '礼品兑换列表。', 'Gift exchange list.'), endpoint: '/giftList', fields: ['id', 'title', 'status', 'created_at'] },
        news: { title: tr('front.news_list', '新闻公告', 'News'), desc: tr('front.news_list_desc', '公告列表。', 'News list.'), endpoint: '/newsList', fields: ['id', 'title', 'author_name', 'created_at'] }
    };

    var adminModules = {
        dashboard: { title: '控制台', desc: '平台指标、审核队列、用户增长和系统公告。', kind: 'dashboard', endpoint: '/dashboardData' },
        users: { title: '用户管理', desc: '客户与代理账户列表。', endpoint: '/userList', fields: ['user_id', 'user_name', 'email', 'account_type', 'total_funds', 'auth_status'] },
        agents: { title: '代理管理', desc: '代理账户、等级和返佣配置。', endpoint: '/agentList', fields: ['user_id', 'user_name', 'email', 'level', 'commission', 'created_at'] },
        deposits: { title: '入金审核', desc: '入金订单和审核状态。', endpoint: '/depositList', fields: ['id', 'user_id', 'order_no', 'amount', 'status', 'created_at'] },
        withdrawals: { title: '出金审核', desc: '出金申请和处理状态。', endpoint: '/withdrawList', fields: ['id', 'user_id', 'order_no', 'apply_amount', 'status', 'created_at'] },
        commissions: { title: '佣金管理', desc: '代理佣金与结算记录。', endpoint: '/commissionList', fields: ['id', 'agent_id', 'user_id', 'commission_amount', 'status', 'created_at'] },
        vouchers: { title: '凭证记录', desc: '客户凭证上传与审核记录。', endpoint: '/voucherRecords', fields: ['id', 'user_id', 'review_status', 'created_at'] },
        roles: { title: '角色管理', desc: '后台角色和权限分配。', endpoint: '/roleList', fields: ['id', 'name', 'guard_type', 'description', 'status'] },
        permissions: { title: '权限管理', desc: '后台权限树与资源配置。', endpoint: '/permissionTree', fields: ['id', 'name', 'slug', 'guard_type', 'type', 'parent_id', 'sort', 'status'] },
        menus: { title: '菜单管理', desc: '后台菜单结构与排序。', endpoint: '/menuTree', fields: ['id', 'title', 'name', 'slug', 'route', 'guard_type', 'parent_id', 'sort', 'status'] },
        'agent-levels': { title: '代理等级', desc: '代理等级、编码和佣金比例。', endpoint: '/agentLevelList', fields: ['id', 'level', 'level_code', 'name', 'commission', 'status'] },
        'group-configs': { title: '组别配置', desc: '交易组别和业务配置。', endpoint: '/groupConfigList', fields: ['id', 'group_id', 'name', 'description', 'status'] },
        'system-configs': { title: '系统配置', desc: '系统参数和业务开关。', endpoint: '/systemConfigList', fields: ['group', 'key', 'value', 'description'] },
        'operation-logs': { title: '操作日志', desc: '后台操作轨迹和审计记录。', endpoint: '/operationLogs', fields: ['id', 'admin_name', 'action', 'description', 'created_at'] },
        channels: { title: '支付通道', desc: '入出金通道配置。', endpoint: '/channelList', fields: ['id', 'name', 'channel_code', 'is_enabled', 'sort'] },
        admins: { title: '管理员', desc: '后台管理员账户。', endpoint: '/adminList', fields: ['id', 'username', 'email', 'role_id', 'status', 'created_at'] },
        news: { title: '新闻公告', desc: '系统公告和前台展示内容。', endpoint: '/newsList', fields: ['id', 'title', 'status', 'created_at'] }
    };

    var menus = guard === 'admin' ? adminMenus : frontMenus;
    var modules = guard === 'admin' ? adminModules : frontModules;

    window.addEventListener('crm:theme-change', function (event) {
        var nextSkin = normalizeSkin(event.detail && event.detail.theme);
        if (!nextSkin || nextSkin === skin) {
            return;
        }
        skin = nextSkin;
        syncSkinState(true);
    });

    var fieldLabels = {
        id: 'ID',
        user_id: '用户ID',
        user_name: '用户名',
        username: '管理员',
        email: '邮箱',
        account_type: '账户类型',
        auth_status: '认证状态',
        order_no: '订单号',
        amount: '金额',
        apply_amount: '申请金额',
        payment_channel: '支付通道',
        status: '状态',
        review_status: '审核状态',
        created_at: '创建时间',
        type: '类型',
        balance: '余额',
        symbol: '品种',
        volume: '手数',
        profit: '盈亏',
        commission: '佣金',
        commission_amount: '返佣金额',
        total: '总计',
        open_time: '开仓时间',
        close_time: '平仓时间',
        agent_id: '代理ID',
        group_id: '组别',
        guard_type: '守卫',
        description: '描述',
        slug: '标识',
        route: '路由',
        parent_id: '父级',
        sort: '排序',
        level: '等级',
        level_code: '等级编码',
        name: '名称',
        key: '配置键',
        value: '配置值',
        admin_name: '管理员',
        action: '动作',
        channel_code: '通道编码',
        is_enabled: '启用',
        role_id: '角色',
        real_name: '姓名',
        phone: '电话',
        phone_masked: '电话',
        email_masked: '邮箱',
        address: '地址',
        is_default: '默认',
        title: '标题',
        author_name: '作者',
        total_funds: '总资金',
        equity: '净值',
        effective_credit: '信用额度',
        total_volume: '总手数',
        total_profit: '已结盈亏',
        floating_profit: '浮动盈亏',
        open_count: '持仓数',
        agent_level_name: '代理等级',
        id_card_no_masked: '身份证号',
    };

    var fieldLabelKeys = {
        user_id: 'front.user_id',
        user_name: 'user.user_name',
        username: 'auth.username',
        email: 'front.email',
        email_masked: 'front.email',
        account_type: 'front.account_type',
        auth_status: 'front.auth_status',
        order_no: 'front.order_no',
        amount: 'front.amount',
        apply_amount: 'front.withdraw_amount',
        payment_channel: 'front.payment_channel',
        status: 'common.status',
        review_status: 'front.review_status',
        created_at: 'common.created_at',
        type: 'front.type',
        balance: 'front.account_balance',
        symbol: 'front.symbol',
        volume: 'front.volume',
        profit: 'front.profit',
        commission: 'front.commission',
        commission_amount: 'front.rebate_amount',
        total: 'front.total',
        open_time: 'front.open_time',
        close_time: 'front.close_time',
        agent_id: 'front.agent_id',
        group_id: 'front.group_id',
        description: 'front.description',
        name: 'front.name',
        real_name: 'front.receiver_name',
        phone: 'front.phone',
        phone_masked: 'front.phone',
        address: 'front.address',
        title: 'front.news_title',
        author_name: 'front.news_author',
        total_funds: 'front.total_funds',
        equity: 'front.equity',
        effective_credit: 'front.effective_credit',
        commission_rate: 'front.commission_rate',
        agent_level_name: 'front.agent_level',
        id_card_no_masked: 'front.id_card_no',
        total_yuerj: 'front.total_deposit',
        total_yuecj: 'front.total_withdraw',
        total_rebate: 'front.total_rebate',
        total_net_worth: 'front.net_worth',
        total_comm: 'front.commission',
        total_noble_metal: 'front.noble_metal',
        total_for_exca: 'front.forex',
        total_crud_oil: 'front.crude_oil',
        total_index: 'front.index_products',
        total_currency: 'front.currency_products',
        total_stock: 'front.stock_products',
        total_swaps: 'front.swaps',
        floating_profit: 'front.floating_profit',
        open_count: 'front.open_count'
    };

    function esc(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function normalizeLocale(value) {
        value = String(value || 'zh-CN');
        if (value === 'zh' || value === 'zh-cn' || value === 'zh_CN') return 'zh-CN';
        if (value === 'en-US' || value === 'en_US') return 'en';
        return value;
    }

    function tr(key, fallback, enFallback) {
        var translated = window.CrmLang && CrmLang.t ? CrmLang.t(key) : key;
        if (translated && translated !== key && translated !== humanizeKey(key)) return translated;
        return locale === 'en' && enFallback ? enFallback : fallback;
    }

    function humanizeKey(key) {
        var last = String(key || '').split('.').pop() || '';
        return last.replace(/_/g, ' ').replace(/\b\w/g, function (letter) {
            return letter.toUpperCase();
        });
    }

    function normalizeSkin(value) {
        value = legacySkinMap[value] || value || 'light';
        return ['light', 'dark', 'sea', 'warm', 'contrast'].indexOf(value) === -1 ? 'light' : value;
    }

    function persistSkin(value) {
        skin = window.CrmTheme ? CrmTheme.set(value) : normalizeSkin(value);
        if (!window.CrmTheme) {
            localStorage.setItem('crm_naive_skin', skin);
            localStorage.setItem('crm_theme', skin);
            localStorage.setItem('front_theme', skin);
            document.documentElement.setAttribute('data-front-theme', skin);
        }
    }

    function skinLabel(item) {
        return item.icon + ' ' + (locale === 'en' ? item.en : item.label);
    }

    function styleLabel(value) {
        if (value === 'layui') return locale === 'en' ? 'Layui Style' : 'Layui 风格';
        return locale === 'en' ? 'Naive Style' : 'Naive 风格';
    }

    function token() {
        return localStorage.getItem(tokenKeys[0]) || localStorage.getItem(tokenKeys[1]) || '';
    }

    function setToken(value) {
        tokenKeys.forEach(function (key) {
            localStorage.setItem(key, value);
        });
    }

    function removeToken() {
        tokenKeys.forEach(function (key) {
            localStorage.removeItem(key);
        });
    }

    function api(endpoint, payload) {
        if (!token()) {
            return Promise.reject(new Error('no token'));
        }
        return fetch(boot.apiBase + endpoint, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                Authorization: 'Bearer ' + token(),
                'X-Locale': boot.locale || 'zh-CN'
            },
            body: JSON.stringify(payload || {})
        }).then(function (res) {
            if (res.status === 401 || res.status === 419) {
                removeToken();
                throw new Error('expired');
            }
            return res.json();
        });
    }

    function success(body) {
        return body && [1000, 1001, 1002, 1003, 1004, 2000, 3000].indexOf(Number(body.code)) !== -1;
    }

    function fieldLabel(key) {
        return tr(fieldLabelKeys[key] || ('front.' + key), fieldLabels[key] || String(key).replace(/_/g, ' '));
    }

    function fmt(value) {
        if (value === null || value === undefined || value === '') {
            return '-';
        }
        if (typeof value === 'number') {
            return Number.isInteger(value) ? String(value) : String(Math.round(value * 100) / 100);
        }
        return String(value);
    }

    function mockDashboard() {
        if (guard === 'admin') {
            return {
                stats: {
                    total_users: 128430,
                    total_agents: 1688,
                    total_customers: 64320,
                    today_new_users: 386,
                    pending_deposits: 42,
                    pending_withdrawals: 27,
                    monthly_deposit: 3820000,
                    monthly_withdraw: 2690000
                },
                news: [
                    { id: 1, title: '高额出金风控规则已更新', created_at: '2026-05-28 09:30:00' },
                    { id: 2, title: '代理结算队列改为每 30 分钟执行', created_at: '2026-05-27 18:20:00' },
                    { id: 3, title: '支付通道健康监控已上线', created_at: '2026-05-26 11:05:00' }
                ]
            };
        }
        return {
            stats: {
                account_balance: 186420.75,
                total_commission: 82460.5,
                monthly_deposit: 238000,
                monthly_withdraw: 126800,
                direct_agents: 38,
                direct_customers: 612,
                open_orders_count: 74,
                monthly_commission: 15880.35
            },
            user: {
                user_id: 10086,
                user_name: 'demo_agent',
                email: 'agent@example.com',
                account_type: 'agent'
            },
            profile: {
                total_funds: 322400.5,
                equity: 298880.3,
                effective_credit: 50000,
                commission_rate: '12%'
            },
            downloads: {
                pc: '/downloads/CoCRM-PC.exe',
                mobile: '/downloads/CoCRM-Mobile.apk'
            },
            share_urls: [
                { label: '代理开户链接', url: '/front/register/10086?type=agent' },
                { label: '客户开户链接', url: '/front/register/10086?type=customer' },
                { label: '开户链接 A', url: '/front/register/10086?channel=a' },
                { label: '开户链接 B', url: '/front/register/10086?channel=b' }
            ],
            news: [
                { id: 1, title: 'Monthly commission preview has been generated', created_at: '2026-05-28 10:15:00' },
                { id: 2, title: 'Deposit review SLA adjusted to 15 minutes', created_at: '2026-05-27 16:45:00' },
                { id: 3, title: 'Customer position report supports export', created_at: '2026-05-26 08:50:00' }
            ]
        };
    }

    function mockValue(key, index) {
        var date = '2026-05-' + String(28 - (index % 9)).padStart(2, '0') + ' ' + String(9 + (index % 10)).padStart(2, '0') + ':00:00';
        if (key === 'id') return index + 1;
        if (key === 'user_id') return 10000 + index;
        if (key === 'agent_id') return 800 + index;
        if (key === 'user_name') return 'customer_' + (1000 + index);
        if (key === 'username') return 'admin_' + (index + 1);
        if (key === 'email') return 'user' + index + '@example.com';
        if (key === 'order_no') return 'CO2026' + (100000 + index);
        if (key === 'amount' || key === 'apply_amount') return Math.round((1200 + index * 83.7) * 100) / 100;
        if (key === 'commission' || key === 'commission_amount') return Math.round((68 + index * 9.35) * 100) / 100;
        if (key === 'balance') return Math.round((18000 - index * 217.4) * 100) / 100;
        if (key === 'total_funds' || key === 'equity' || key === 'total') return Math.round((52000 + index * 1330.5) * 100) / 100;
        if (key === 'profit') return Math.round(((index % 2 ? -1 : 1) * (260 + index * 18.2)) * 100) / 100;
        if (key === 'volume') return Math.round((0.1 + (index % 8) * 0.15) * 100) / 100;
        if (key === 'symbol') return ['XAUUSD', 'EURUSD', 'GBPUSD', 'USDJPY'][index % 4];
        if (key === 'status' || key === 'review_status') return ['pending', 'approved', 'processing', 'rejected'][index % 4];
        if (key === 'payment_channel') return ['USDT-TRC20', 'Bank Card', 'Wire Transfer'][index % 3];
        if (key === 'account_type') return ['standard', 'agent', 'vip'][index % 3];
        if (key === 'group_id') return (index % 4) + 1;
        if (key === 'guard_type') return index % 2 ? 'admin' : 'user';
        if (key === 'auth_status') return ['pending', 'verified', 'rejected'][index % 3];
        if (key === 'description') return '配置说明 ' + (index + 1);
        if (key === 'slug') return 'resource.' + (index + 1);
        if (key === 'route') return '/' + (guard === 'admin' ? 'admin' : 'front') + '/demo/' + (index + 1);
        if (key === 'parent_id') return index % 4 === 0 ? 0 : index;
        if (key === 'sort') return index + 1;
        if (key === 'level') return (index % 5) + 1;
        if (key === 'level_code') return 'L' + ((index % 5) + 1);
        if (key === 'name') return 'Demo Name ' + (index + 1);
        if (key === 'key') return 'demo_key_' + (index + 1);
        if (key === 'value') return 'demo_value_' + (index + 1);
        if (key === 'admin_name') return 'admin_' + (index + 1);
        if (key === 'action') return ['create', 'update', 'review', 'delete'][index % 4];
        if (key === 'channel_code') return ['USDT', 'BANK', 'WIRE'][index % 3];
        if (key === 'is_enabled') return index % 2 ? 1 : 0;
        if (key === 'role_id') return (index % 4) + 1;
        if (key === 'real_name') return 'Demo User ' + (index + 1);
        if (key === 'phone') return '1380000' + String(1000 + index);
        if (key === 'address') return 'Demo address ' + (index + 1);
        if (key === 'is_default') return index % 3 === 0 ? 1 : 0;
        if (key === 'title') return 'Demo notice ' + (index + 1);
        if (/_at$|_time$|date/.test(key)) return date;
        return 'Demo ' + (index + 1);
    }

    function mockRows(config, count) {
        var fields = (config && config.fields) || ['id', 'user_id', 'status', 'created_at'];
        var rows = [];
        for (var i = 0; i < (count || 18); i += 1) {
            var row = {};
            fields.forEach(function (key) {
                row[key] = mockValue(key, i);
            });
            rows.push(row);
        }
        return rows;
    }

    function normalizeRows(data, config) {
        var payload = data || {};
        if (Array.isArray(payload)) return payload;
        if (Array.isArray(payload.data)) return payload.data;
        if (payload.data && Array.isArray(payload.data.data)) return payload.data.data;
        if (payload.list && Array.isArray(payload.list.data)) return payload.list.data;
        if (payload.list && payload.list.data && Array.isArray(payload.list.data.data)) return payload.list.data.data;
        if (Array.isArray(payload.list)) return payload.list;
        if (Array.isArray(payload.items)) return payload.items;
        var keys = Object.keys(payload).filter(function (key) { return Array.isArray(payload[key]); });
        if (keys.length) return payload[keys[0]];
        return config && config.noMock ? [] : mockRows(config);
    }

    function tableFilters(config) {
        var endpoint = (config && config.endpoint) || '';
        if (config && Array.isArray(config.filters)) return config.filters;
        if (endpoint === '/openOrders' || endpoint === '/closedOrders') {
            return [
                { name: 'userId', label: 'front.user_id' },
                { name: 'orderId', label: 'front.order_no' },
                { name: 'symbol', label: 'front.symbol' },
                { name: 'date_from', label: 'front.date_from', type: 'date' },
                { name: 'date_to', label: 'front.date_to', type: 'date' }
            ];
        }
        if (endpoint === '/positionSummary') {
            return [
                { name: 'userId', label: 'front.user_id' },
                { name: 'userName', label: 'front.user_name' },
                { name: 'symbol', label: 'front.symbol' },
                { name: 'startdate', label: 'front.date_from', type: 'date' },
                { name: 'enddate', label: 'front.date_to', type: 'date' }
            ];
        }
        if (endpoint === '/commissionRealTime') {
            return [
                { name: 'userId', label: 'front.user_id' },
                { name: 'orderId', label: 'front.order_no' },
                { name: 'date_from', label: 'front.date_from', type: 'date' },
                { name: 'date_to', label: 'front.date_to', type: 'date' }
            ];
        }
        if (endpoint === '/commissionHistory') {
            return [
                { name: 'orderId', label: 'front.order_no' },
                { name: 'date_from', label: 'front.date_from', type: 'date' },
                { name: 'date_to', label: 'front.date_to', type: 'date' }
            ];
        }
        if (endpoint === '/agentSubList' || endpoint === '/agentCustomerList' || endpoint === '/agentConfirmLevel') {
            return [
                { name: 'userId', label: 'front.user_id' },
                { name: 'username', label: 'front.user_name' },
                { name: 'userstatus', label: 'front.auth_status' }
            ];
        }
        if (endpoint === '/agentGroupChangeList') {
            return [
                { name: 'userId', label: 'front.user_id' },
                { name: 'groupId', label: 'front.group_id' },
                { name: 'date_from', label: 'front.date_from', type: 'date' },
                { name: 'date_to', label: 'front.date_to', type: 'date' }
            ];
        }
        if (endpoint === '/giftAddressList') {
            return [
                { name: 'receiver_name', label: 'front.receiver_name' },
                { name: 'phone', label: 'front.phone' },
                { name: 'is_default', label: 'front.default_address' }
            ];
        }
        if (endpoint === '/giftList') {
            return [
                { name: 'keyword', label: 'front.gift_name' },
                { name: 'points_cost', label: 'front.points_cost' }
            ];
        }
        if (endpoint === '/newsList') {
            return [
                { name: 'title', label: 'front.news_title' },
                { name: 'author_name', label: 'front.news_author' }
            ];
        }
        if (endpoint === '/voucherList') {
            return [
                { name: 'review_status', label: 'front.review_status' },
                { name: 'date_from', label: 'front.date_from', type: 'date' },
                { name: 'date_to', label: 'front.date_to', type: 'date' }
            ];
        }
        if (endpoint === '/depositHistory' || endpoint === '/withdrawHistory') {
            return [
                { name: 'status', label: 'common.status' },
                { name: 'date_from', label: 'front.date_from', type: 'date' },
                { name: 'date_to', label: 'front.date_to', type: 'date' }
            ];
        }
        if (endpoint === '/accountFlow') {
            return [
                { name: 'flow_type', label: 'front.flow_type' },
                { name: 'date_from', label: 'front.date_from', type: 'date' },
                { name: 'date_to', label: 'front.date_to', type: 'date' }
            ];
        }
        return [
            { name: 'keyword', label: 'common.search' }
        ];
    }

    function tableFiltersHtml(config) {
        var filters = tableFilters(config);
        return '<form class="crm-table-filters" data-table-filter>' + filters.map(function (filter) {
            var type = filter.type === 'date' ? 'date' : 'text';
            var value = currentTableFilters[filter.name] || '';
            var label = tr(filter.label, fieldLabel(filter.name));
            return '<label><span>' + esc(label) + '</span><input class="crm-plain-input" type="' + type + '" name="' + esc(filter.name) + '" value="' + esc(value) + '" placeholder="' + esc(label) + '"></label>';
        }).join('') + '<div class="crm-table-filter-actions"><button class="crm-plain-primary" type="submit" data-action="table-search">' + esc(tr('common.search', '搜索', 'Search')) + '</button><button class="crm-plain-secondary" type="button" data-action="table-reset">' + esc(tr('common.reset', '重置', 'Reset')) + '</button></div></form>';
    }

    function collectTableFilters() {
        var filters = {};
        app.querySelectorAll('[data-table-filter] input, [data-table-filter] select').forEach(function (field) {
            var value = (field.value || '').trim();
            if (field.name && value) filters[field.name] = value;
        });
        return filters;
    }

    function tablePayload(config) {
        return Object.assign({}, (config && config.defaultFilters) || {}, currentTableFilters, { page: 1, per_page: 15, limit: 15 });
    }

    function loadTableData(config) {
        if (!config || !config.endpoint) return;
        api(config.endpoint, tablePayload(config)).then(function (body) {
            if (success(body)) {
                renderPageWithData(config, normalizeRows(body.data, config));
            }
        }).catch(function () {
            renderPageWithData(config, config.noMock ? [] : mockRows(config));
        });
    }

    function skinOptions() {
        return skins.map(function (item) {
            var current = item.value === skin;
            return '<option value="' + esc(item.value) + '"' + (current ? ' selected' : '') + '>' + esc(item.icon + ' ' + skinLabel(item)) + '</option>';
        }).join('');
    }

    function styleOptions() {
        return [
            { value: 'naive', label: '▣ ' + styleLabel('naive') },
            { value: 'layui', label: '□ ' + styleLabel('layui') }
        ].map(function (item) {
            var current = item.value === uiStyle;
            return '<option value="' + esc(item.value) + '"' + (current ? ' selected' : '') + '>' + esc(item.label) + '</option>';
        }).join('');
    }

    function localeOptions() {
        return [
            { value: 'zh-CN', label: '中' },
            { value: 'en', label: 'EN' }
        ].map(function (item) {
            var current = item.value === locale;
            return '<option value="' + esc(item.value) + '"' + (current ? ' selected' : '') + '>' + esc(item.label) + '</option>';
        }).join('');
    }

    function writeStyle(value) {
        localStorage.setItem('crm_ui_style', value);
        localStorage.setItem(guard + '_ui_style', value);
    }

    function shellStart(config) {
        disposeCharts();
        app.innerHTML = [
            '<div class="crm-root crm-root-app crm-skin-' + esc(skin) + '">',
            '<div class="crm-shell">',
            '<aside class="crm-sidebar">',
            '<div class="crm-sidebar-head"><div class="crm-logo">' + (guard === 'admin' ? 'A' : 'F') + '</div><div><p class="crm-sidebar-title">CoCRM v5</p><p class="crm-sidebar-meta">' + (guard === 'admin' ? tr('common.admin', '后台工作台', 'Admin workspace') : tr('front.dashboard', '前台工作台', 'Front workspace')) + '</p></div></div>',
            '<div class="crm-menu-wrap"><nav class="crm-plain-menu">' + menus.map(function (item) {
                return '<button type="button" data-page="' + esc(item.key) + '" class="' + (item.key === currentPage ? 'active' : '') + '"><span>' + esc(item.icon) + '</span>' + esc(item.label) + '</button>';
            }).join('') + '</nav></div>',
            '</aside>',
            '<main class="crm-main">',
            '<header class="crm-topbar">',
            '<div class="crm-page-title"><button type="button" class="crm-mobile-menu crm-plain-secondary" data-action="toggle-menu">' + esc(tr('common.menu', '菜单', 'Menu')) + '</button><div><h1>' + esc(config.title) + '</h1><p>' + esc(config.desc) + '</p></div></div>',
            '<div class="crm-top-actions"><label class="crm-skin-select" title="' + esc(tr('front.ui_style', '界面', 'UI')) + '"><span class="crm-style-select-icon" aria-hidden="true"></span><select id="crmStyleSelect" aria-label="' + esc(tr('front.ui_style', '界面', 'UI')) + '">' + styleOptions() + '</select></label><label class="crm-skin-select" title="' + esc(tr('front.skin_mode', '皮肤', 'Theme')) + '"><span class="crm-skin-select-icon" aria-hidden="true"></span><select id="crmSkinSelect" aria-label="' + esc(tr('front.skin_mode', '皮肤', 'Theme')) + '">' + skinOptions() + '</select></label><label class="crm-skin-select" title="' + esc(tr('common.language', '语言', 'Language')) + '"><span class="crm-locale-select-icon" aria-hidden="true"></span><select id="crmLocaleSelect" aria-label="' + esc(tr('common.language', '语言', 'Language')) + '">' + localeOptions() + '</select></label><button type="button" class="crm-plain-secondary" data-action="legacy">□ ' + esc(styleLabel('layui')) + '</button><button type="button" class="crm-plain-secondary" data-action="refresh">' + esc(tr('common.refresh', '刷新', 'Refresh')) + '</button><button type="button" class="crm-plain-secondary" data-action="logout">' + esc(tr('common.logout', '退出', 'Logout')) + '</button></div>',
            '</header>',
            '<section class="crm-content"><div class="crm-content-inner" id="crmPlainContent"></div></section>',
            '</main>',
            '</div>',
            '</div>'
        ].join('');
    }

    function shellEnd() {
        bindShell();
    }

    function renderLogin() {
        disposeCharts();
        app.innerHTML = [
            '<div class="crm-root crm-root-login crm-skin-' + esc(skin) + '">',
            '<div class="crm-login-page">',
            '<section class="crm-login-panel">',
            '<div class="crm-login-brand"><div class="crm-logo">' + (guard === 'admin' ? 'A' : 'F') + '</div><div class="crm-brand-copy"><p class="crm-brand-title">CoCRM v5</p><p class="crm-brand-subtitle">' + (guard === 'admin' ? esc(tr('common.admin', '后台管理工作台', 'Admin workspace')) : esc(tr('front.dashboard', '代理与客户工作台', 'Front workspace'))) + '</p></div></div>',
            '<h1 class="crm-login-title">' + esc(guard === 'admin' ? tr('auth.login', '后台登录', 'Admin Login') : tr('auth.login', '前台登录', 'Front Login')) + '</h1>',
            '<p class="crm-login-desc">' + esc(guard === 'admin' ? tr('auth.login_desc_admin', '使用管理员账号登录原生 JS 工作台。', 'Sign in with an administrator account.') : tr('auth.login_desc_front', '输入邮箱或用户 ID，系统自动识别账号类型后进入 Naive 风格工作台。', 'Enter email or user ID; the system detects the account type automatically.')) + '</p>',
            '<form class="crm-login-form" id="plainLoginForm">',
            '<label class="crm-plain-field">' + esc(tr('auth.account', '账号', 'Account')) + '<input name="account" placeholder="' + esc(guard === 'admin' ? tr('auth.username', '管理员账号', 'Admin username') : tr('auth.account_or_email', '邮箱 / 用户 ID', 'Email / User ID')) + '" autocomplete="username"></label>',
            '<label class="crm-plain-field">' + esc(tr('auth.password', '密码', 'Password')) + '<input name="password" type="password" placeholder="' + esc(tr('auth.password', '密码', 'Password')) + '" autocomplete="current-password"></label>',
            '<div class="crm-login-actions"><label class="crm-skin-select"><span class="crm-style-select-icon" aria-hidden="true"></span>' + esc(tr('front.ui_style', '界面', 'UI')) + '<select id="crmStyleSelect">' + styleOptions() + '</select></label><label class="crm-skin-select"><span class="crm-skin-select-icon" aria-hidden="true"></span>' + esc(tr('front.skin_mode', '皮肤', 'Theme')) + '<select id="crmSkinSelect">' + skinOptions() + '</select></label><label class="crm-skin-select"><span class="crm-locale-select-icon" aria-hidden="true"></span>' + esc(tr('common.language', '语言', 'Language')) + '<select id="crmLocaleSelect">' + localeOptions() + '</select></label><button class="crm-plain-primary" type="submit">' + esc(tr('auth.login', '登录', 'Login')) + '</button></div>',
            guard === 'front' ? '<div class="crm-login-links"><a href="/front/register">' + esc(tr('auth.go_register', '注册账号', 'Register')) + '</a><a href="/front/forgot-password">' + esc(tr('auth.forgot_password', '忘记密码', 'Forgot Password')) + '</a><a href="/front/big-number/login">' + esc(tr('auth.userid_login', '用户ID登录', 'User ID Login')) + '</a></div>' : '',
            '<p class="crm-login-error" id="plainLoginError"></p>',
            '</form>',
            '</section>',
            '<section class="crm-login-visual" aria-hidden="true"><div class="crm-visual-board"><div class="crm-visual-tile wide"><p class="crm-visual-label">' + esc(tr('front.funds_chart', '资金概览', 'Funds Overview')) + '</p><p class="crm-visual-value">98.74%</p><div class="crm-bars"><div class="crm-bar"><span style="width:82%"></span></div><div class="crm-bar"><span style="width:58%;background:var(--crm-blue)"></span></div></div></div><div class="crm-visual-tile"><p class="crm-visual-label">' + esc(tr('front.network_chart', '团队结构', 'Network')) + '</p><p class="crm-visual-value">17,952</p></div><div class="crm-visual-tile"><p class="crm-visual-label">' + esc(tr('front.order_chart', '订单监控', 'Orders')) + '</p><p class="crm-visual-value">286k</p></div><div class="crm-visual-tile wide"><p class="crm-visual-label">' + esc(tr('front.commission_chart', '结算返佣', 'Settlement')) + '</p><p class="crm-visual-value">$12.8M</p></div></div></section>',
            '</div></div>'
        ].join('');
        bindStyle();
        bindSkin();
        bindLocale();
        document.getElementById('plainLoginForm').addEventListener('submit', submitLogin);
    }

    function submitLogin(event) {
        event.preventDefault();
        var form = event.currentTarget;
        var error = document.getElementById('plainLoginError');
        var account = (form.account.value || '').trim();
        var loginType = 'auto';
        var payload = {
            account: account,
            password: form.password.value
        };
        if (guard === 'admin') {
            payload.username = account;
        } else if (loginType === 'email') {
            payload.email = account;
            delete payload.user_id;
        } else if (loginType === 'user_id') {
            payload.user_id = account;
            delete payload.email;
        } else if (/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(account)) {
            payload.email = account;
        } else if (account) {
            payload.user_id = account;
        }
        error.textContent = '';
        fetch(boot.apiBase + '/login', {
            method: 'POST',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Locale': locale },
            body: JSON.stringify(payload)
        }).then(function (res) {
            return res.json();
        }).then(function (body) {
            if (!success(body) || !body.data || !body.data.access_token) {
                error.textContent = body.message || tr('auth.login_failed', '登录失败', 'Login failed');
                return;
            }
            setToken(body.data.access_token);
            localStorage.setItem('crm_locale', locale);
            localStorage.setItem('front_lang', locale);
            window.location.href = boot.homePath || (basePath + '/dashboard');
        }).catch(function () {
            error.textContent = tr('common.network_error', '网络异常', 'Network error');
        });
    }

    function bindStyle() {
        var selects = app.querySelectorAll('#crmStyleSelect, [data-crm-style-select]');
        if (!selects.length) return;
        selects.forEach(function (select) {
            select.value = uiStyle;
            select.addEventListener('change', function () {
                uiStyle = select.value;
                writeStyle(uiStyle);
                if (uiStyle === 'layui') {
                    window.location.href = boot.legacyPath || (legacyBasePath + '/dashboard');
                } else {
                    window.location.href = boot.homePath || (basePath + '/dashboard');
                }
            });
        });
    }

    function bindSkin() {
        var selects = app.querySelectorAll('#crmSkinSelect, [data-crm-skin-select]');
        if (!selects.length) return;
        selects.forEach(function (select) {
            select.value = skin;
            select.addEventListener('change', function () {
                persistSkin(select.value);
                syncSkinState(true);
            });
        });
    }

    function syncSkinState(redrawCharts) {
        var root = app.querySelector('.crm-root');

        app.querySelectorAll('#crmSkinSelect, [data-crm-skin-select]').forEach(function (item) {
            item.innerHTML = skinOptions();
            item.value = skin;
        });

        if (root) {
            root.className = root.className.replace(/crm-skin-\w+/g, '').trim() + ' crm-skin-' + skin;
        }

        document.documentElement.setAttribute('data-front-theme', skin);
        if (redrawCharts) {
            renderCharts(currentStats);
        }
    }

    function bindLocale() {
        var selects = app.querySelectorAll('#crmLocaleSelect, [data-crm-locale-select]');
        if (!selects.length) return;
        selects.forEach(function (select) {
            select.value = locale;
            select.addEventListener('change', function () {
                locale = normalizeLocale(select.value);
                boot.locale = locale;
                localStorage.setItem('crm_locale', locale);
                localStorage.setItem('front_lang', locale);
                document.documentElement.setAttribute('lang', locale);
                if (window.CrmLang && CrmLang.loadLanguage) {
                    CrmLang.loadLanguage(locale).then(function () { window.location.reload(); });
                    return;
                }
                window.location.reload();
            });
        });
    }

    function bindLegacyStyle() {
        app.querySelectorAll('[data-action="legacy"]').forEach(function (button) {
            button.addEventListener('click', function () {
                writeStyle('layui');
                window.location.href = boot.legacyPath || (legacyBasePath + '/dashboard');
            });
        });
    }

    function bindRefresh() {
        app.querySelectorAll('[data-action="refresh"]').forEach(function (button) {
            button.addEventListener('click', function () { renderPage(currentPage, true); });
        });
    }

    function bindSearch() {
        app.querySelectorAll('[data-action="toggle-summary"]').forEach(function (button) {
            button.addEventListener('click', function () {
                var summary = app.querySelector('.crm-table-summary');
                var icon = button.querySelector('span');
                if (!summary) return;
                summary.classList.toggle('is-collapsed');
                if (icon) icon.textContent = summary.classList.contains('is-collapsed') ? '>' : '∨';
            });
        });
        app.querySelectorAll('[data-table-filter]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                currentTableFilters = collectTableFilters();
                loadTableData(currentTableConfig);
            });
        });
        app.querySelectorAll('[data-action="table-reset"]').forEach(function (button) {
            button.addEventListener('click', function () {
                currentTableFilters = {};
                loadTableData(currentTableConfig);
            });
        });
        app.querySelectorAll('[data-action="search"]').forEach(function (button) {
            button.addEventListener('click', filterCurrentTable);
        });
    }

    function bindLogout() {
        app.querySelectorAll('[data-action="logout"]').forEach(function (button) {
            button.addEventListener('click', function () {
                removeToken();
                window.location.href = boot.loginPath || (basePath + '/login');
            });
        });
    }

    function bindCopy() {
        app.querySelectorAll('[data-copy]').forEach(function (button) {
            button.addEventListener('click', function () {
                var value = button.getAttribute('data-copy');
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(value);
                } else {
                    window.prompt('Copy', value);
                }
            });
        });
    }

    function bindRowDetail() {
        app.querySelectorAll('[data-row-detail]').forEach(function (button) {
            button.addEventListener('click', function () {
                showRowDetail(button.getAttribute('data-row-detail'));
            });
        });
        app.querySelectorAll('.crm-plain-table tbody tr[data-row-index]').forEach(function (row) {
            row.addEventListener('dblclick', function () {
                showRowDetail(row.getAttribute('data-row-index'));
            });
        });
        document.querySelectorAll('[data-close-detail]').forEach(function (button) {
            button.addEventListener('click', closeDetailModal);
        });
        var modal = document.getElementById('plainDetailModal');
        if (modal) {
            modal.addEventListener('click', function (event) {
                if (event.target === modal) closeDetailModal();
            });
        }
    }

    function bindShell() {
        bindStyle();
        bindSkin();
        bindLocale();
        app.querySelectorAll('[data-page]').forEach(function (button) {
            button.addEventListener('click', function () {
                navigate(button.getAttribute('data-page'));
            });
        });
        app.querySelectorAll('[data-go]').forEach(function (button) {
            button.addEventListener('click', function () {
                window.location.href = button.getAttribute('data-go');
            });
        });
        bindRefresh();
        bindLegacyStyle();
        bindSearch();
        bindLogout();
        bindCopy();
        bindRowDetail();
        if (!resizeBound) {
            window.addEventListener('resize', renderCharts);
            resizeBound = true;
        }
    }

    function navigate(page) {
        if (!page || page === currentPage) return;
        currentPage = page;
        currentTableFilters = {};
        window.history.pushState({}, '', basePath + '/' + page);
        renderPage(page, false);
    }

    function renderPage(page, force) {
        currentPage = page || 'dashboard';
        var config = modules[currentPage] || modules.dashboard;
        if (currentPage === 'login') {
            renderLogin();
            return;
        }
        shellStart(config);
        if (config.kind === 'dashboard') {
            renderDashboard(mockDashboard());
            api(config.endpoint, {}).then(function (body) {
                if (success(body) && body.data) {
                    renderPageWithData(config, body.data);
                }
            }).catch(function () {});
        } else if (config.kind === 'detail') {
            renderDetail(config, mockRows({ fields: ['user_id', 'user_name', 'email', 'account_type', 'total_funds', 'equity', 'effective_credit', 'commission_rate'] }, 1)[0]);
            api(config.endpoint, {}).then(function (body) {
                if (success(body) && body.data) renderPageWithData(config, normalizeDetailData(config, body.data));
            }).catch(function () {});
        } else {
            renderTable(config, config.noMock ? [] : mockRows(config));
            api(config.endpoint, tablePayload(config)).then(function (body) {
                if (success(body)) renderPageWithData(config, normalizeRows(body.data, config));
            }).catch(function () {
                if (config.noMock) renderPageWithData(config, []);
            });
        }
        shellEnd();
    }

    function normalizeDetailData(config, data) {
        var endpoint = (config && config.endpoint) || '';
        var result = {};
        var section;
        var key;

        if (endpoint !== '/profileInfo') {
            return data || {};
        }

        data = data || {};
        ['login', 'info', 'auth'].forEach(function (name) {
            section = data[name] || {};
            for (key in section) {
                if (Object.prototype.hasOwnProperty.call(section, key) && typeof result[key] === 'undefined') {
                    result[key] = section[key];
                }
            }
        });

        return result;
    }

    function renderPageWithData(config, data) {
        shellStart(config);
        if (config.kind === 'dashboard') {
            renderDashboard(data);
        } else if (config.kind === 'detail') {
            renderDetail(config, normalizeDetailData(config, data));
        } else {
            renderTable(config, Array.isArray(data) && data.length ? data : (config.noMock ? [] : mockRows(config)));
        }
        shellEnd();
    }

    function statItems(data) {
        var stats = data.stats || data || {};
        if (guard === 'admin') {
            return [
                { key: 'total_users', label: tr('front.total_users', '总用户数', 'Total Users'), value: stats.total_users || data.total_users || 0, note: tr('user.title', '用户管理', 'Users'), icon: 'U' },
                { key: 'total_agents', label: tr('front.total_agents', '代理总数', 'Total Agents'), value: stats.total_agents || data.total_agents || 0, note: tr('front.agent_level', '代理管理', 'Agents'), icon: 'A' },
                { key: 'total_customers', label: tr('front.total_customers', '客户总数', 'Total Customers'), value: stats.total_customers || data.total_customers || 0, note: tr('front.direct_customers', '客户增长', 'Customers'), icon: 'C' },
                { key: 'today_new_users', label: tr('front.today_new_users', '今日新增', 'New Today'), value: stats.today_new_users || data.today_new_users || 0, note: tr('front.new_users', '新增用户', 'New Users'), icon: 'N' },
                { key: 'pending_deposits', label: tr('front.pending_deposits', '待审入金', 'Pending Deposits'), value: stats.pending_deposits || data.pending_deposits || 0, note: tr('front.deposit', '入金审核', 'Deposits'), icon: 'I' },
                { key: 'pending_withdrawals', label: tr('front.pending_withdrawals', '待审出金', 'Pending Withdrawals'), value: stats.pending_withdrawals || data.pending_withdrawals || 0, note: tr('front.withdraw', '出金审核', 'Withdrawals'), icon: 'O' },
                { key: 'monthly_deposit', label: tr('front.monthly_deposit', '本月入金', 'Monthly Deposit'), value: stats.monthly_deposit || data.monthly_deposit || 0, note: tr('front.total_deposit', '资金流入', 'Deposit'), icon: 'D' },
                { key: 'monthly_withdraw', label: tr('front.monthly_withdraw', '本月出金', 'Monthly Withdraw'), value: stats.monthly_withdraw || data.monthly_withdraw || 0, note: tr('front.total_withdraw', '资金流出', 'Withdraw'), icon: 'W' }
            ];
        }
        return [
            { key: 'account_balance', label: tr('front.account_balance', '账户余额', 'Account Balance'), value: stats.account_balance || 0, note: tr('menu.front_account_info', '账户综合', 'Account Overview'), icon: 'B' },
            { key: 'total_commission', label: tr('front.total_commission', '累计返佣', 'Total Commission'), value: stats.total_commission || 0, note: tr('menu.front_commission', '返佣管理', 'Commission'), icon: 'C' },
            { key: 'monthly_deposit', label: tr('front.monthly_deposit', '本月入金', 'Monthly Deposit'), value: stats.monthly_deposit || 0, note: tr('menu.front_deposit', '入金管理', 'Deposit'), icon: 'D' },
            { key: 'monthly_withdraw', label: tr('front.monthly_withdraw', '本月出金', 'Monthly Withdraw'), value: stats.monthly_withdraw || 0, note: tr('menu.front_withdraw', '出金管理', 'Withdraw'), icon: 'W' },
            { key: 'direct_agents', label: tr('front.direct_agents', '直属代理', 'Direct Agents'), value: stats.direct_agents || 0, note: tr('menu.front_agent_sub', '下级代理', 'Sub Agents'), icon: 'A' },
            { key: 'direct_customers', label: tr('front.direct_customers', '直属客户', 'Direct Customers'), value: stats.direct_customers || 0, note: tr('menu.front_agent_customers', '直属客户', 'Customers'), icon: 'K' },
            { key: 'open_orders_count', label: tr('front.open_orders', '当前持仓', 'Open Orders'), value: stats.open_orders_count || 0, note: tr('menu.front_open_orders', '持仓订单', 'Open Orders'), icon: 'O' },
            { key: 'monthly_commission', label: tr('front.monthly_commission', '本月返佣', 'Monthly Commission'), value: stats.monthly_commission || 0, note: tr('menu.front_commission_hist', '返佣历史', 'Commission History'), icon: 'M' }
        ];
    }

    function downloadUrl(config) {
        var url = typeof config === 'string' ? config : (config && config.url ? config.url : '#');
        url = String(url || '#').trim();
        if (!url || url === '#' || isObsoleteVersionProbe(url)) return '#';
        return url;
    }

    function isObsoleteVersionProbe(url) {
        var normalized = String(url || '').toLowerCase().trim();
        return normalized.indexOf('xapi.yhchj.com/version') !== -1 || /\/version([/?#].*)?$/.test(normalized);
    }

    function shareLinkLabel(item, index) {
        var fallback = tr('front.share_url', '注册链接', 'Register Link') + ' ' + (index + 1);
        if (!item) return fallback;
        if (item.label_key) return tr(item.label_key, item.label || fallback);
        return item.label || fallback;
    }

    function dashboardControlPanel() {
        return '<section class="crm-dashboard-controls"><label class="crm-skin-select" title="' + esc(tr('front.ui_style', '界面', 'UI')) + '"><span class="crm-style-select-icon" aria-hidden="true"></span><select data-crm-style-select aria-label="' + esc(tr('front.ui_style', '界面', 'UI')) + '">' + styleOptions() + '</select></label><label class="crm-skin-select" title="' + esc(tr('front.skin_mode', '皮肤', 'Theme')) + '"><span class="crm-skin-select-icon" aria-hidden="true"></span><select data-crm-skin-select aria-label="' + esc(tr('front.skin_mode', '皮肤', 'Theme')) + '">' + skinOptions() + '</select></label><label class="crm-skin-select" title="' + esc(tr('common.language', '语言', 'Language')) + '"><span class="crm-locale-select-icon" aria-hidden="true"></span><select data-crm-locale-select aria-label="' + esc(tr('common.language', '语言', 'Language')) + '">' + localeOptions() + '</select></label></section>';
    }

    function renderDashboard(data) {
        var content = document.getElementById('crmPlainContent');
        var profile = Object.assign({}, data.user || {}, data.profile || {});
        var links = (data.share_urls || data.profile && data.profile.share_urls || []).slice(0, 4);
        if (!links.length) links = mockDashboard().share_urls;
        var stats = statItems(data);
        stats.forEach(function (item) {
            item.breakdownLabels = locale === 'en' ? ['Deposit', 'Rebate', 'Withdraw', 'Orders', 'Agents', 'Clients'] : ['入金', '返佣', '出金', '订单', '代理', '客户'];
            item.breakdownValues = [
                Number((data.stats || {}).monthly_deposit || 0),
                Number((data.stats || {}).monthly_commission || (data.stats || {}).total_commission || 0),
                Number((data.stats || {}).monthly_withdraw || 0),
                Number((data.stats || {}).open_orders_count || (data.stats || {}).monthly_open_orders || 0),
                Number((data.stats || {}).direct_agents || 0) + Number((data.stats || {}).indirect_agents || 0),
                Number((data.stats || {}).direct_customers || 0) + Number((data.stats || {}).indirect_customers || 0)
            ];
        });
        currentStats = stats;
        var dashboardTitle = guard === 'admin' ? tr('common.dashboard', '后台管理控制台', 'Admin Dashboard') : tr('front.dashboard', '代理与客户工作台', 'Front Dashboard');
        var dashboardDesc = guard === 'admin' ? tr('front.naive_admin_desc', '原生 JS 渲染后台模块，保留 naive-admin 的侧栏、密度和图表体验。', 'Plain JavaScript admin workspace with Naive-style layout.') : tr('front.naive_front_desc', '无 Vue 运行时，页面切换稳定，保留 naive-admin 的信息密度和侧栏布局。', 'Plain JavaScript rendering without Vue runtime, keeping the Naive-style density and sidebar.');
        var downloads = Object.assign({}, mockDashboard().downloads || {}, data.downloads || data.profile && data.profile.downloads || {});
        var pcDownload = downloadUrl(downloads.pc);
        var mobileDownload = downloadUrl(downloads.mobile);
        var downloadPanel = guard === 'front' ? '<section class="crm-download-panel"><div><h2 class="crm-section-title">' + esc(tr('front.download_center', '下载中心', 'Download Center')) + '</h2><p class="crm-section-subtitle">' + esc(tr('front.download_center_desc', 'PC 客户端和移动端安装包。', 'PC client and mobile package.')) + '</p></div><div class="crm-download-actions"><a class="crm-plain-primary' + (pcDownload === '#' ? ' disabled' : '') + '" href="' + esc(pcDownload) + '" target="_blank" rel="noopener">▣ ' + esc(tr('front.pc_download', 'PC 下载', 'PC Download')) + '</a><a class="crm-plain-secondary' + (mobileDownload === '#' ? ' disabled' : '') + '" href="' + esc(mobileDownload) + '" target="_blank" rel="noopener">□ ' + esc(tr('front.mobile_download', '移动端下载', 'Mobile Download')) + '</a></div></section>' : '';
        var registerPanel = guard === 'front' ? '<section class="crm-register-panel"><div class="crm-section-head"><div><h2 class="crm-section-title">' + esc(tr('front.share_url', '注册链接', 'Register Links')) + '</h2><p class="crm-section-subtitle">' + esc(tr('front.share_url_desc', '4 个常用开户链接，支持复制。', 'Four common register links with copy action.')) + '</p></div></div><div class="crm-register-grid">' + links.map(function (item, index) {
            return '<article class="crm-register-card"><div class="crm-register-icon">' + esc(['R1', 'R2', 'A', 'B'][index]) + '</div><div><p class="crm-register-label">' + esc(shareLinkLabel(item, index)) + '</p><a href="' + esc(item.url) + '" target="_blank" rel="noopener">' + esc(item.url) + '</a></div><button type="button" data-copy="' + esc(item.url) + '">' + esc(tr('common.copy', '复制', 'Copy')) + '</button></article>';
        }).join('') + '</div></section>' : '';
        var detailPanel = guard === 'front'
            ? '<section class="crm-section"><h2 class="crm-section-title">' + esc(tr('menu.front_account_info', '账户综合', 'Account Overview')) + '</h2><div class="crm-detail-grid">' + ['user_id', 'user_name', 'email', 'account_type', 'total_funds', 'equity', 'effective_credit', 'commission_rate'].map(function (key) {
                return '<div class="crm-detail-item"><p class="crm-detail-label">' + esc(fieldLabel(key)) + '</p><p class="crm-detail-value">' + esc(fmt(profile[key])) + '</p></div>';
            }).join('') + '</div></section>'
            : '<section class="crm-section"><h2 class="crm-section-title">' + esc(tr('front.review_queue', '审核队列', 'Review Queue')) + '</h2><div class="crm-detail-grid">' + ['pending_deposits', 'pending_withdrawals', 'total_agents', 'today_new_users'].map(function (key) {
                var item = stats.filter(function (stat) { return stat.key === key; })[0] || { label: fieldLabel(key), value: 0 };
                return '<div class="crm-detail-item"><p class="crm-detail-label">' + esc(item.label) + '</p><p class="crm-detail-value">' + esc(fmt(item.value)) + '</p></div>';
            }).join('') + '</div></section>';
        content.innerHTML = [
            '<section class="crm-overview-band"><div class="crm-overview-copy"><span class="crm-kicker">Plain Naive Style</span><h2>' + esc(dashboardTitle) + '</h2><p>' + esc(dashboardDesc) + '</p></div><div class="crm-quick-panel"><p>' + esc(tr('front.quick_entry', '快捷入口', 'Quick Entry')) + '</p><div>' + menus.slice(1, 9).map(function (item) {
                return '<button class="crm-action-chip" data-page="' + esc(item.key) + '"><span class="crm-action-dot"></span><span>' + esc(item.label) + '</span></button>';
            }).join('') + '</div></div></section>',
            dashboardControlPanel(),
            downloadPanel,
            registerPanel,
            '<div class="crm-grid stats">' + stats.map(function (item) {
                return '<article class="crm-stat"><span class="crm-stat-icon">' + esc(item.icon) + '</span><p class="crm-stat-label">' + esc(item.label) + '</p><p class="crm-stat-value">' + esc(fmt(item.value)) + '</p><p class="crm-stat-note">' + esc(item.note) + '</p></article>';
            }).join('') + '</div>',
            '<section class="crm-chart-board"><div class="crm-section-head"><div><h2 class="crm-section-title">ECharts</h2><p class="crm-section-subtitle">' + esc(tr('front.chart_board_desc', '每个指标保留独立图形视图。', 'Each metric keeps an independent chart view.')) + '</p></div></div><div class="crm-chart-grid">' + stats.map(function (item, index) {
                return '<article class="crm-chart-card"><div class="crm-chart-head"><div><p class="crm-chart-title">' + esc(item.label) + '</p><p class="crm-chart-meta">' + esc(fmt(item.value)) + '</p></div><select class="crm-chart-type" data-chart-type="' + index + '">' + chartTypeOptions(index) + '</select></div><div class="crm-chart-canvas" id="plainChart' + index + '"></div></article>';
            }).join('') + '</div></section>',
            '<div class="crm-grid two">' + detailPanel + '<section class="crm-section"><h2 class="crm-section-title">' + esc(tr('front.news_list', '新闻公告', 'News')) + '</h2><div class="crm-news-list">' + (data.news || []).map(function (item) {
                return '<article class="crm-news-item"><p class="crm-news-title">' + esc(item.title) + '</p><p class="crm-news-meta">' + esc(fmt(item.created_at)) + '</p></article>';
            }).join('') + '</div></section></div>'
        ].join('');
        renderCharts(stats);
    }

    function renderDetail(config, data) {
        var content = document.getElementById('crmPlainContent');
        var keys = (config && config.detailFields) || Object.keys(data || {});
        var charts = '';

        if (!keys.length) keys = ['user_id', 'user_name', 'email', 'account_type', 'total_funds', 'equity'];
        if (config && config.endpoint === '/accountInfo') {
            currentStats = [
                {key: 'funds_profile', label: tr('front.funds_profile', '资金画像', 'Funds Profile'), value: Number(data.total_deposit || data.total_funds || 0), breakdownLabels: [tr('front.total_deposit', '入金', 'Deposit'), tr('front.total_rebate', '返佣', 'Rebate'), tr('front.total_withdraw', '出金', 'Withdraw'), tr('front.total_funds', '余额', 'Funds')], breakdownValues: [Number(data.total_deposit || 0), Number(data.total_rebate || 0), Number(data.total_withdraw || 0), Number(data.total_funds || 0)]},
                {key: 'order_profile', label: tr('front.order_profile', '订单画像', 'Order Profile'), value: Number(data.closed_order_count || 0), breakdownLabels: [tr('front.open_order_count', '开仓订单数', 'Open Orders'), tr('front.closed_order_count', '平仓订单数', 'Closed Orders'), tr('front.profit_7d', '近 7 天盈亏', '7-Day P/L'), tr('front.profit_15d', '近 15 天盈亏', '15-Day P/L'), tr('front.profit_30d', '近 30 天盈亏', '30-Day P/L')], breakdownValues: [Number(data.open_order_count || 0), Number(data.closed_order_count || 0), Number(data.profit_7d || 0), Number(data.profit_15d || 0), Number(data.profit_30d || 0)]},
                {key: 'client_profile', label: tr('front.client_profile', '客户画像', 'Client Profile'), value: Number(data.relation_amount || 0), breakdownLabels: [tr('front.direct_agents', '直属代理', 'Direct Agents'), tr('front.direct_customers', '直属客户', 'Direct Customers'), tr('front.indirect_customers', '间接客户', 'Indirect Customers'), tr('front.relation_amount', '相关金额', 'Related Amount')], breakdownValues: [Number(data.direct_agents || 0), Number(data.direct_customers || 0), Number(data.indirect_customers || 0), Number(data.relation_amount || 0)]}
            ];
            charts = '<section class="crm-chart-board"><div class="crm-section-head"><div><h2 class="crm-section-title">' + esc(tr('front.account_chart_title', '账户综合图表', 'Account Overview Charts')) + '</h2></div></div><div class="crm-chart-grid">' + currentStats.map(function (item, index) {
                return '<article class="crm-chart-card"><div class="crm-chart-head"><div><p class="crm-chart-title">' + esc(item.label) + '</p><p class="crm-chart-meta">' + esc(fmt(item.value)) + '</p></div><select class="crm-chart-type" data-chart-type="' + index + '">' + chartTypeOptions(index) + '</select></div><div class="crm-chart-canvas" id="plainChart' + index + '"></div></article>';
            }).join('') + '</div></section>';
        }
        content.innerHTML = '<section class="crm-section"><h2 class="crm-section-title">' + esc(config.title) + '</h2><div class="crm-detail-grid">' + keys.map(function (key) {
            return '<div class="crm-detail-item"><p class="crm-detail-label">' + esc(fieldLabel(key)) + '</p><p class="crm-detail-value">' + esc(fmt(data[key])) + '</p></div>';
        }).join('') + '</div></section>' + charts;
        if (charts) renderCharts(currentStats);
    }

    function renderTable(config, rows) {
        var content = document.getElementById('crmPlainContent');
        rows = rows || [];
        var fields = config.fields || Object.keys(rows[0] || {});
        currentTableConfig = config;
        currentRows = rows || [];
        var summary = tableSummary(fields, currentRows);
        content.innerHTML = [
            '<section class="crm-data-panel"><div class="crm-data-head"><h2>' + esc(config.title) + '</h2></div><div class="crm-table-filters"><input class="crm-plain-input" id="plainSearch" placeholder="' + esc(tr('common.search_placeholder', '输入关键词', 'Search keyword')) + '"><button class="crm-plain-secondary" data-action="search">' + esc(tr('common.search', '搜索', 'Search')) + '</button></div>',
            config.collapsibleSummary ? '<button type="button" class="crm-summary-toggle" data-action="toggle-summary"><span>&gt;</span>' + esc(tr('front.summary', '汇总', 'Summary')) + '</button>' : '',
            '<div class="crm-table-wrap"><table class="crm-plain-table"><thead><tr>' + fields.map(function (key) { return '<th>' + esc(fieldLabel(key)) + '</th>'; }).join('') + '<th>' + esc(tr('common.operation', '操作', 'Action')) + '</th></tr></thead><tbody>' + rows.map(function (row) {
                var rowIndex = currentRows.indexOf(row);
                return '<tr data-row-index="' + rowIndex + '">' + fields.map(function (key) { return '<td title="' + esc(fmt(row[key])) + '">' + esc(fmt(row[key])) + '</td>'; }).join('') + '<td><button class="crm-table-action" type="button" data-row-detail="' + rowIndex + '">' + esc(tr('common.detail', '详情', 'Detail')) + '</button></td></tr>';
            }).join('') + '</tbody><tfoot><tr><td>' + esc(tr('front.summary', '汇总', 'Summary')) + '</td>' + fields.slice(1).map(function (key) { return '<td>' + esc(summary[key] || '-') + '</td>'; }).join('') + '<td>' + currentRows.length + ' ' + esc(tr('front.rows_unit', '条', 'rows')) + '</td></tr></tfoot></table></div><div class="crm-table-summary' + (config.collapsibleSummary ? ' is-collapsed' : '') + '">' + summaryText(summary, currentRows.length) + '</div><div class="crm-row-detail" id="plainRowDetail">' + esc(tr('front.click_detail_hint', '点击详情查看单行完整数据。', 'Click detail to view the complete row.')) + '</div></section>'
        ].join('');
        var filterNode = content.querySelector('.crm-table-filters');
        if (filterNode) {
            filterNode.outerHTML = tableFiltersHtml(config);
        }
    }

    function tableSummary(fields, rows) {
        var result = {};
        fields.forEach(function (key) {
            var sum = 0;
            var count = 0;
            rows.forEach(function (row) {
                var value = Number(row[key]);
                if (isFinite(value) && !/_id$|^id$|status|sort|level$/.test(key)) {
                    sum += value;
                    count += 1;
                }
            });
            if (count) result[key] = fmt(sum);
        });
        return result;
    }

    function summaryText(summary, count) {
        var parts = [tr('front.total_count', '总数', 'Total') + ': ' + count];
        Object.keys(summary).slice(0, 4).forEach(function (key) {
            parts.push(fieldLabel(key) + ': ' + summary[key]);
        });
        return parts.map(function (part) { return '<span>' + esc(part) + '</span>'; }).join('');
    }

    function filterCurrentTable() {
        var input = document.getElementById('plainSearch');
        var keyword = input ? input.value.toLowerCase().trim() : '';
        var visible = 0;
        app.querySelectorAll('.crm-plain-table tbody tr').forEach(function (row) {
            var matched = !keyword || row.textContent.toLowerCase().indexOf(keyword) !== -1;
            row.style.display = matched ? '' : 'none';
            if (matched) visible += 1;
        });
        var detail = document.getElementById('plainRowDetail');
        if (detail) detail.textContent = keyword ? (tr('front.filter_result', '筛选结果', 'Filtered') + ': ' + visible + ' ' + tr('front.rows_unit', '条', 'rows')) : tr('front.click_detail_hint', '点击详情查看单行完整数据。', 'Click detail to view the complete row.');
    }

    function detailGroupTitle(group) {
        var map = {
            identity: tr('front.basic_info', '基本信息', 'Basic Info'),
            trade: tr('front.trade_info', '交易信息', 'Trade Info'),
            finance: tr('front.finance_info', '资金信息', 'Finance Info'),
            time: tr('front.time_info', '时间信息', 'Time Info'),
            other: tr('front.other_info', '其他信息', 'Other Info')
        };
        return map[group] || map.other;
    }

    function detailGroupForKey(key) {
        if (/^(id|user|login|email|phone|account|agent|group|level|auth|parent|real_name|username)/i.test(key)) return 'identity';
        if (/^(ticket|order|symbol|cmd|volume|open_|close_|sl|tp|stop_|take_|reason|comment)/i.test(key)) return 'trade';
        if (/(amount|balance|equity|credit|margin|profit|commission|rebate|fee|swaps|funds|rate|total)/i.test(key)) return 'finance';
        if (/(_at|_time|date|created|updated|modify)/i.test(key)) return 'time';
        return 'other';
    }

    function detailModalHtml(row) {
        var groups = { identity: [], trade: [], finance: [], time: [], other: [] };
        Object.keys(row || {}).forEach(function (key) {
            groups[detailGroupForKey(key)].push(key);
        });
        return '<div class="crm-modal-mask" id="plainDetailModal"><div class="crm-modal-card" role="dialog" aria-modal="true"><div class="crm-modal-head"><h3>' + esc(tr('common.detail', '详情', 'Detail')) + '</h3><button type="button" class="crm-modal-close" data-close-detail>&times;</button></div><div class="crm-modal-body">' + Object.keys(groups).map(function (group) {
            if (!groups[group].length) return '';
            return '<section class="crm-modal-section"><h4>' + esc(detailGroupTitle(group)) + '</h4><dl>' + groups[group].map(function (key) {
                return '<div><dt>' + esc(fieldLabel(key)) + '</dt><dd>' + esc(fmt(row[key])) + '</dd></div>';
            }).join('') + '</dl></section>';
        }).join('') + '</div></div></div>';
    }

    function closeDetailModal() {
        var modal = document.getElementById('plainDetailModal');
        if (modal) modal.remove();
    }

    function showRowDetail(index) {
        var row = currentRows[Number(index)];
        var detail = document.getElementById('plainRowDetail');
        if (!row) return;
        if (detail) {
            detail.innerHTML = Object.keys(row).slice(0, 6).map(function (key) {
                return '<span><strong>' + esc(fieldLabel(key)) + '</strong>' + esc(fmt(row[key])) + '</span>';
            }).join('');
        }
        closeDetailModal();
        document.body.insertAdjacentHTML('beforeend', detailModalHtml(row));
        var modal = document.getElementById('plainDetailModal');
        if (modal) {
            modal.querySelectorAll('[data-close-detail]').forEach(function (button) { button.addEventListener('click', closeDetailModal); });
            modal.addEventListener('click', function (event) { if (event.target === modal) closeDetailModal(); });
        }
    }

    function chartTypeOptions(index) {
        var options = [
            { value: 'bar', label: tr('front.chart_bar', '柱状图', 'Bar') },
            { value: 'line', label: tr('front.chart_line', '折线图', 'Line') },
            { value: 'area', label: tr('front.chart_area', '面积图', 'Area') },
            { value: 'pie', label: tr('front.chart_pie', '饼图', 'Pie') },
            { value: 'radar', label: tr('front.chart_radar', '雷达图', 'Radar') }
        ];
        var defaults = ['bar', 'line', 'area', 'pie', 'radar'];
        var selected = defaults[index % defaults.length];
        return options.map(function (item) {
            return '<option value="' + esc(item.value) + '"' + (item.value === selected ? ' selected' : '') + '>' + esc(item.label) + '</option>';
        }).join('');
    }

    function chartOption(item, type) {
        var base = Math.max(Number(item.value) || 10, 10);
        var labels = item.breakdownLabels || (locale === 'en' ? ['Deposit', 'Rebate', 'Withdraw', 'Orders', 'Agents', 'Clients'] : ['入金', '返佣', '出金', '订单', '代理', '客户']);
        var points = item.breakdownValues || [0.62, 0.78, 0.71, 0.88, 0.95, 1].map(function (rate, index) {
            return Math.round((base * rate + index * 4) * 100) / 100;
        });
        var colors = ['#18a058', '#2080f0', '#f0a020', '#d03050', '#0e7a83', '#7c3aed'];
        if (type === 'pie') {
            return { color: colors, tooltip: { trigger: 'item' }, series: [{ type: 'pie', radius: ['42%', '72%'], data: labels.map(function (label, index) { return { name: label, value: points[index] }; }) }] };
        }
        var seriesType = type === 'area' || type === 'radar' ? 'line' : type;
        return { color: colors, grid: { left: 34, right: 14, top: 24, bottom: 28 }, tooltip: { trigger: 'axis' }, xAxis: { type: 'category', data: labels, axisTick: { show: false } }, yAxis: { type: 'value' }, series: [{ type: seriesType, smooth: true, barWidth: 18, areaStyle: type === 'line' || type === 'area' ? { opacity: type === 'area' ? 0.24 : 0.12 } : undefined, data: points }] };
    }

    function renderCharts(stats) {
        if (!ECharts) return;
        stats = stats && stats.length ? stats : (currentStats && currentStats.length ? currentStats : statItems(mockDashboard()));
        chartInstances.forEach(function (chart) { if (chart && chart.resize) chart.resize(); });
        stats.forEach(function (item, index) {
            var el = document.getElementById('plainChart' + index);
            if (!el) return;
            var select = document.querySelector('[data-chart-type="' + index + '"]');
            var type = select ? select.value : ['bar', 'line', 'area', 'pie', 'radar'][index % 5];
            var chart = chartInstances[index] || ECharts.init(el);
            chartInstances[index] = chart;
            chart.setOption(chartOption(item, type), true);
            if (select && !select.dataset.bound) {
                select.dataset.bound = '1';
                select.addEventListener('change', function () { chart.setOption(chartOption(item, select.value), true); });
            }
        });
    }

    function disposeCharts() {
        chartInstances.forEach(function (chart) {
            if (chart && chart.dispose) chart.dispose();
        });
        chartInstances = [];
    }

    window.addEventListener('popstate', function () {
        var path = window.location.pathname.replace(basePath, '').replace(/^\/+/, '');
        renderPage(path || 'dashboard', false);
    });

    persistSkin(skin);
    document.documentElement.setAttribute('lang', locale);
    uiStyle = 'naive';
    writeStyle('naive');
    renderPage(currentPage, false);
})();
