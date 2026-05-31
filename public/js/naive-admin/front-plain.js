/**
 * Naive CRM 的前台/后台统一壳脚本。
 *
 * 这里集中处理页面路由、模拟数据兜底、表格渲染、详情弹窗、
 * 资料表单和皮肤切换，让单页壳层可以复用同一套代码路径。
 */
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
    var currentDepositPageData = null;
    var currentFlowType = 'deposit';
    var currentChain = [];
    var tableCache = {};
    var resizeBound = false;
    // 记录详情弹框的最新请求批次，避免旧异步详情返回后覆盖当前用户正在查看的弹框。
    var detailRequestSerial = 0;

    var skins = [
        { value: 'light', label: '月白蓝', icon: '○', en: 'Porcelain Blue' },
        { value: 'dark', label: '星岩黑', icon: '●', en: 'Graphite Night' },
        { value: 'sea', label: '潮汐青', icon: '◇', en: 'Tide Cyan' },
        { value: 'warm', label: '松林绿', icon: '◌', en: 'Pine Green' },
        { value: 'contrast', label: '银岩灰', icon: '◆', en: 'Silver Slate' }
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
        profile: { title: tr('front.profile', '个人资料', 'Profile'), desc: tr('front.profile_desc', '账户资料、认证、换绑和安全设置。', 'Profile, verification, binding changes and security settings.'), kind: 'profile', endpoint: '/profileInfo', detailFields: ['user_id', 'user_name', 'email_masked', 'phone_masked', 'account_type', 'auth_status', 'id_card_no_masked', 'last_login_at'] },
        account: { title: tr('menu.front_account_info', '账户综合 / 余额', 'Account Overview'), desc: tr('front.account_overview_desc', '资金、余额、净值、认证和账户状态。', 'Funds, balance, equity, verification and account status.'), kind: 'detail', endpoint: '/accountInfo', detailFields: ['user_id', 'user_name', 'total_funds', 'equity', 'total_deposit', 'total_rebate', 'total_withdraw', 'open_order_count', 'closed_order_count', 'profit_7d', 'profit_15d', 'profit_30d'] },
        vouchers: { title: tr('menu.front_voucher', '凭证审核', 'Vouchers'), desc: tr('front.voucher_desc', '凭证列表。', 'Voucher list.'), endpoint: '/voucherList', mockWhenEmpty: true, fields: ['id', 'user_id', 'review_status', 'images', 'amount', 'created_at'] },
        deposits: { title: tr('menu.front_deposit', '入金管理', 'Deposits'), desc: tr('front.deposit_desc', '入金记录。', 'Deposit records.'), endpoint: '/depositHistory', depositForm: true, fields: ['id', 'order_no', 'amount', 'payment_channel', 'status', 'created_at'] },
        withdrawals: { title: tr('menu.front_withdraw', '出金管理', 'Withdrawals'), desc: tr('front.withdraw_desc', '出金记录。', 'Withdrawal records.'), endpoint: '/withdrawHistory', fields: ['id', 'order_no', 'apply_amount', 'status', 'created_at'] },
        flow: { title: tr('menu.front_flow', '账户流水', 'Account Flow'), desc: tr('front.account_flow_desc', '资金变动流水。', 'Fund movement records.'), endpoint: '/accountFlow', flowTabs: true, mockWhenEmpty: true, fields: ['id', 'user_id', 'flow_type', 'type', 'amount', 'balance', 'created_at'] },
        'position-summary': { title: tr('front.position_summary', '仓位总结', 'Position Summary'), desc: tr('front.position_summary_desc', '交易品种和盈亏汇总。', 'Trading symbols and profit summary.'), endpoint: '/positionSummary', noMock: true, fields: ['user_id', 'agent_level_name', 'user_name', 'total_yuerj', 'total_yuecj', 'total_rebate', 'total_net_worth', 'total_comm', 'total_profit', 'total_noble_metal', 'total_for_exca', 'total_crud_oil', 'total_index', 'total_currency', 'total_stock', 'total_volume', 'total_swaps', 'open_count', 'floating_profit'] },
        'open-orders': { title: tr('front.open_orders', '持仓订单', 'Open Orders'), desc: tr('front.open_orders_desc', '当前持仓订单。', 'Current open orders.'), endpoint: '/openOrders', fields: ['ticket', 'login', 'symbol', 'cmd_text', 'volume_lots', 'open_price', 'profit', 'swaps', 'commission', 'open_time', 'comment'] },
        'closed-orders': { title: tr('menu.front_closed_orders', '历史订单', 'Closed Orders'), desc: tr('front.closed_orders_desc', '已平仓订单。', 'Closed orders.'), endpoint: '/closedOrders', fields: ['ticket', 'login', 'symbol', 'cmd_text', 'volume_lots', 'open_price', 'close_price', 'profit', 'swaps', 'commission', 'open_time', 'close_time', 'comment'] },
        'agent-sub': { title: tr('menu.front_agent_sub', '下级代理', 'Sub Agents'), desc: tr('front.agent_sub_desc', '代理网络。', 'Agent network.'), endpoint: '/agentSubList', mockWhenEmpty: true, fields: ['user_id', 'user_name', 'email', 'account_type', 'created_at'] },
        'agent-customers': { title: tr('menu.front_agent_customers', '直属客户', 'Direct Customers'), desc: tr('front.agent_customers_desc', '客户列表。', 'Customer list.'), endpoint: '/agentCustomerList', mockWhenEmpty: true, fields: ['user_id', 'user_name', 'email', 'account_type', 'total_funds'] },
        'agent-confirm': { title: tr('front.confirm_level', '代理级别确认', 'Agent Level Confirm'), desc: tr('front.confirm_level_desc', '查看当前代理等级、确认状态和返佣比例。', 'Current agent level, confirmation status and commission rate.'), endpoint: '/agentConfirmLevel', confirmLevel: true, mockWhenEmpty: true, fields: ['userId', 'userName', 'userEmail', 'userPhone', 'agent_level_name', 'userGroupId', 'rec_crt_date'] },
        'group-change': { title: tr('menu.front_group_change', '组别变更', 'Group Change'), desc: tr('front.group_change_desc', '组别变更记录。', 'Group change records.'), endpoint: '/agentGroupChangeList', groupChangeForm: true, fields: ['trans_uid', 'trans_type_gid', 'trans_apply_status', 'trans_apply_reason', 'rec_crt_date'] },
        'commission-realtime': { title: tr('front.realtime_commission', '实时返佣', 'Real-time Commission'), desc: tr('front.realtime_commission_desc', '实时返佣订单。', 'Real-time commission orders.'), endpoint: '/commissionRealTime', collapsibleSummary: true, fields: ['ticket', 'login', 'symbol', 'volume_lots', 'profit_gain', 'profit_loss', 'profit_net', 'modify_time'] },
        'commission-history': { title: tr('front.commission_history', '返佣历史', 'Commission History'), desc: tr('front.commission_history_desc', '历史返佣记录。', 'Historical commission records.'), endpoint: '/commissionHistory', fields: ['id', 'agent_id', 'commission_amount', 'status', 'created_at'] },
        'commission-transfer': { title: tr('front.commission_transfer', '佣金转账', 'Commission Transfer'), desc: tr('front.commission_transfer_desc', '佣金转账记录。', 'Commission transfer records.'), endpoint: '/commissionHistory', fields: ['id', 'agent_id', 'commission_amount', 'status', 'created_at'] },
        'gift-address': { title: tr('front.gift_address', '地址管理', 'Address'), desc: tr('front.gift_address_desc', '收货地址。', 'Delivery addresses.'), endpoint: '/giftAddressList', addressBook: true, fields: ['id', 'recipient_name', 'recipient_phone', 'recipient_address', 'is_default', 'updated_at'] },
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
        userId: '用户ID',
        mt4_login: 'MT4 登录号',
        user_name: '用户名',
        userName: '用户名',
        user_info: '用户信息',
        commission_details: '返佣明细',
        avatar_url: '头像',
        username: '管理员',
        userEmail: '邮箱',
        userPhone: '电话',
        userSex: '性别',
        email: '邮箱',
        account_type: '账户类型',
        account_type_text: '账户类型',
        auth_status: '认证状态',
        auth_status_text: '认证状态',
        userStatus: '认证状态',
        userGroupId: '组别',
        order_no: '订单号',
        local_order_no: '本地订单号',
        ticket: '订单号',
        login: '登录号',
        cmd: '方向',
        cmd_text: '方向',
        amount: '金额',
        actual_amount: '实际金额',
        apply_amount: '申请金额',
        payment_channel: '支付通道',
        pay_channel: '支付通道',
        passageway: '通道',
        channel_name: '通道名称',
        depositType: '入金类型',
        depositComment: '入金备注',
        depositActProfit: '实际入金',
        status: '状态',
        review_status: '审核状态',
        created_at: '创建时间',
        type: '类型',
        balance: '余额',
        symbol: '品种',
        volume: '手数',
        volume_lots: '手数',
        profit: '盈亏',
        profit_gain: '盈利',
        profit_loss: '亏损',
        profit_net: '净盈亏',
        commission: '佣金',
        commission_amount: '返佣金额',
        swaps: '库存费',
        comm_rate: '返佣比例',
        total: '总计',
        open_price: '开仓价',
        close_price: '平仓价',
        sl: '止损',
        tp: '止盈',
        stop_loss: '止损',
        take_profit: '止盈',
        open_time: '开仓时间',
        close_time: '平仓时间',
        agent_id: '代理ID',
        parent_id: '上级ID',
        agent_level_rank: '代理等级序号',
        group_id: '组别',
        target_user_id: '目标用户ID',
        new_group_id: '新组别ID',
        trans_uid: '用户ID',
        trans_type_gid: '组别',
        trans_apply_status: '变更状态',
        trans_apply_reason: '变更原因',
        rec_upd_date: '更新时间',
        group_name: '组别名称',
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
        recipient_name: '收货人',
        recipient_phone: '收货电话',
        recipient_address: '收货地址',
        phone: '电话',
        phone_masked: '电话',
        email_masked: '邮箱',
        address: '地址',
        is_default: '默认',
        title: '标题',
        author_name: '作者',
        last_login_ip: '最后登录IP',
        last_login_at: '最后登录时间',
        modify_time: '更新时间',
        login_history_label: '登录历史',
        parent_name: '上级名称',
        country: '国家',
        state: '州/省',
        city: '城市',
        gender: '性别',
        total_funds: '总资金',
        equity: '净值',
        mt4_balance: 'MT4 余额',
        user_money: '账户余额',
        cust_eqy: '客户净值',
        mt4MarginLevel: '风险率',
        effective_credit: '信用额度',
        total_volume: '总手数',
        total_profit: '已结盈亏',
        floating_profit: '浮动盈亏',
        open_count: '持仓数',
        agent_level_name: '代理等级',
        id_card_no_masked: '身份证号',
        id_card_status_text: '身份证状态',
        bank_status_text: '银行卡状态',
        bank_name: '开户银行',
        bank_no: '银行卡号',
        bank_no_masked: '银行卡号',
        bank_addr: '开户支行',
        total_deposit: '总入金',
        total_withdraw: '总出金',
        total_rebate: '总返佣',
        effective_credit: '信用额度',
        commission_rate: '返佣比例',
        open_order_count: '开仓订单数',
        closed_order_count: '平仓订单数',
        profit_7d: '近 7 天盈亏',
        profit_15d: '近 15 天盈亏',
        profit_30d: '近 30 天盈亏',
        direct_agents: '直属代理',
        direct_customers: '直属客户',
        indirect_customers: '间接客户',
        relation_amount: '相关金额',
        commprop: '返佣比例',
        depth: '层级',
        is_direct: '直属',
        descendant: '客户对象',
        comm_trans: '佣金转账',
        change_group: '组别变更',
        fy_money: '返佣金额',
        rj_money: '入金金额',
        qk_money: '出金金额',
        rec_crt_date: '注册时间',
        id_card_no: '身份证号',
        comment: '用户消息',
        user_message: '用户消息',
        user_msg: '用户消息',
        customer_message: '客户消息',
        remarks: '备注',
        remark: '备注',
        message: '消息',
        images: '图片',
        'summary.current_level': '当前等级',
        'summary.next_level': '下一等级',
        'summary.is_confirmed': '确认状态',
        'summary.confirmed_at': '确认时间'
    };

    var fieldLabelKeys = {
        user_id: 'front.user_id',
        userId: 'front.user_id',
        mt4_login: 'front.mt4_login',
        user_name: 'user.user_name',
        userName: 'user.user_name',
        user_info: 'front.user_info',
        commission_details: 'front.commission_detail',
        avatar_url: 'front.avatar',
        username: 'auth.username',
        userEmail: 'front.email',
        userPhone: 'front.phone',
        userSex: 'front.gender',
        email: 'front.email',
        email_masked: 'front.email',
        account_type: 'front.account_type',
        account_type_text: 'front.account_type',
        auth_status: 'front.auth_status',
        auth_status_text: 'front.auth_status',
        userStatus: 'front.auth_status',
        userGroupId: 'front.group_id',
        order_no: 'front.order_no',
        local_order_no: 'front.order_no',
        ticket: 'front.order_no',
        login: 'front.mt4_login',
        cmd: 'front.type',
        cmd_text: 'front.type',
        amount: 'front.amount',
        actual_amount: 'front.actual_amount',
        apply_amount: 'front.withdraw_amount',
        payment_channel: 'front.payment_channel',
        pay_channel: 'front.payment_channel',
        passageway: 'front.payment_channel',
        channel_name: 'front.payment_channel',
        depositType: 'front.payment_channel',
        depositComment: 'front.remarks',
        depositActProfit: 'front.actual_amount',
        status: 'common.status',
        review_status: 'front.review_status',
        created_at: 'common.created_at',
        type: 'front.type',
        balance: 'front.account_balance',
        symbol: 'front.symbol',
        volume: 'front.volume',
        volume_lots: 'front.volume',
        profit: 'front.profit',
        profit_gain: 'front.profit_gain',
        profit_loss: 'front.profit_loss',
        profit_net: 'front.profit_net',
        commission: 'front.commission',
        commission_amount: 'front.rebate_amount',
        swaps: 'front.swaps',
        comm_rate: 'front.commission_rate',
        total: 'front.total',
        open_price: 'front.open_price',
        close_price: 'front.close_price',
        sl: 'front.stop_loss',
        tp: 'front.take_profit',
        stop_loss: 'front.stop_loss',
        take_profit: 'front.take_profit',
        open_time: 'front.open_time',
        close_time: 'front.close_time',
        agent_id: 'front.agent_id',
        parent_id: 'front.parent_id',
        agent_level_rank: 'front.agent_level_rank',
        group_id: 'front.group_id',
        target_user_id: 'front.target_user_id',
        new_group_id: 'front.new_group_id',
        trans_uid: 'front.user_id',
        trans_type_gid: 'front.group_id',
        trans_apply_status: 'front.group_change_status',
        trans_apply_reason: 'front.group_change_reason',
        rec_upd_date: 'common.updated_at',
        group_name: 'front.group_name',
        description: 'front.description',
        name: 'front.name',
        real_name: 'front.receiver_name',
        recipient_name: 'front.receiver_name',
        recipient_phone: 'front.phone',
        recipient_address: 'front.address',
        phone: 'front.phone',
        phone_masked: 'front.phone',
        address: 'front.address',
        title: 'front.news_title',
        author_name: 'front.news_author',
        last_login_ip: 'front.last_login_ip',
        last_login_at: 'front.last_login_at',
        modify_time: 'front.modify_time',
        login_history_label: 'front.login_history',
        parent_name: 'front.parent_name',
        country: 'front.country',
        state: 'front.state',
        city: 'front.city',
        gender: 'front.gender',
        total_funds: 'front.total_funds',
        equity: 'front.equity',
        mt4_balance: 'front.mt4_balance',
        user_money: 'front.account_balance',
        cust_eqy: 'front.customer_equity',
        mt4MarginLevel: 'front.margin_level',
        effective_credit: 'front.effective_credit',
        commission_rate: 'front.commission_rate',
        agent_level_name: 'front.agent_level',
        id_card_no_masked: 'front.id_card_no',
        id_card_status_text: 'front.id_card_status',
        bank_status_text: 'front.bank_status',
        bank_name: 'front.bank_name',
        bank_no: 'front.bank_no',
        bank_no_masked: 'front.bank_no',
        bank_addr: 'front.bank_addr',
        total_deposit: 'front.total_deposit',
        total_withdraw: 'front.total_withdraw',
        total_rebate: 'front.total_rebate',
        effective_credit: 'front.effective_credit',
        commission_rate: 'front.commission_rate',
        image: 'front.images',
        avatar: 'front.images',
        voucher_images: 'front.voucher_images',
        open_order_count: 'front.open_order_count',
        closed_order_count: 'front.closed_order_count',
        profit_7d: 'front.profit_7d',
        profit_15d: 'front.profit_15d',
        profit_30d: 'front.profit_30d',
        direct_agents: 'front.direct_agents',
        direct_customers: 'front.direct_customers',
        indirect_customers: 'front.indirect_customers',
        relation_amount: 'front.relation_amount',
        commprop: 'front.commission_rate',
        depth: 'front.depth',
        is_direct: 'front.is_direct',
        descendant: 'front.customer_info',
        comm_trans: 'front.commission_transfer',
        change_group: 'front.group_change',
        fy_money: 'front.rebate_amount',
        rj_money: 'front.deposit_amount',
        qk_money: 'front.withdraw_amount',
        rec_crt_date: 'front.register_time',
        id_card_no: 'front.id_card_no',
        comment: 'front.user_message',
        user_message: 'front.user_message',
        user_msg: 'front.user_message',
        customer_message: 'front.customer_message',
        remarks: 'front.remarks',
        remark: 'front.remarks',
        message: 'front.message',
        images: 'front.images',
        'summary.current_level': 'front.current_level',
        'summary.next_level': 'front.next_level',
        'summary.is_confirmed': 'front.is_confirmed',
        'summary.confirmed_at': 'front.confirmed_at',
        total_yuerj: 'front.total_deposit',
        total_yuecj: 'front.total_withdraw',
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

    function apiMultipart(endpoint, formData) {
        if (!token()) {
            return Promise.reject(new Error('no token'));
        }
        return fetch(boot.apiBase + endpoint, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                Authorization: 'Bearer ' + token(),
                'X-Locale': boot.locale || 'zh-CN'
            },
            body: formData
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
        value = value && typeof value === 'object' && !Array.isArray(value) ? JSON.stringify(value) : value;
        if (value === null || value === undefined || value === '') {
            return '-';
        }
        if (typeof value === 'number') {
            return Number.isInteger(value) ? String(value) : String(Math.round(value * 100) / 100);
        }
        if (Array.isArray(value)) {
            return value.length ? value.join(', ') : '-';
        }
        return String(value);
    }

    function afterNextPaint(callback) {
        if (window.requestAnimationFrame) {
            window.requestAnimationFrame(function () {
                window.setTimeout(callback, 0);
            });
            return;
        }
        window.setTimeout(callback, 0);
    }

    function getFieldValue(row, key) {
        return String(key || '').split('.').reduce(function (value, part) {
            return value && typeof value === 'object' ? value[part] : undefined;
        }, row || {});
    }

    function listFromValue(value) {
        if (Array.isArray(value)) {
            return value;
        }
        if (!value || typeof value !== 'object') {
            return [];
        }
        var keys = Object.keys(value);
        if (!keys.length || keys.some(function (key) { return String(parseInt(key, 10)) !== key; })) {
            return [];
        }
        return keys.map(function (key) { return value[key]; });
    }

    function parseImages(value) {
        if (!value) return [];
        if (Array.isArray(value)) return value;
        if (typeof value === 'string') {
            try {
                var parsed = JSON.parse(value);
                if (Array.isArray(parsed)) return parsed;
            } catch (e) {}
            return value.split(',').map(function (item) { return item.trim(); }).filter(Boolean);
        }
        return [];
    }

    function imageUrl(value) {
        var url = String(value || '').replace(/\\\//g, '/').trim();
        if (!url) return '#';
        if (/^(https?:)?\/\//i.test(url) || url.charAt(0) === '/') return url;
        return '/' + url.replace(/^\/+/, '');
    }

    // 给代理级别确认页补齐可操作的等级范围。接口有数据时使用接口返回；
    // 模拟数据或旧接口缺字段时也能显示下拉，而不是空模板。
    function defaultAgentRange(row, index) {
        var baseRate = Number(getFieldValue(row, 'comm_rate') || 35 + (index % 4) * 5);
        return [
            { level_id: 1, level_name: 'Level 1', prop: baseRate + 10, extra_val: 0, choice_gid: 1, selected: index % 3 === 0 },
            { level_id: 2, level_name: 'Level 2', prop: baseRate + 5, extra_val: 0, choice_gid: 2, selected: index % 3 === 1 },
            { level_id: 3, level_name: 'Level 3', prop: baseRate, extra_val: 0, choice_gid: 3, selected: index % 3 === 2 }
        ];
    }

    function agentLevelSelectHtml(row, rowIndex) {
        var ranges = listFromValue(getFieldValue(row, 'range_list'));
        if (!ranges.length) {
            ranges = defaultAgentRange(row, rowIndex);
        }
        if (!ranges.length) {
            return esc(fmt(getFieldValue(row, 'comm_rate') || getFieldValue(row, 'userGroupId')));
        }

        return '<select class="crm-agent-level-select" data-agent-level-row="' + esc(rowIndex) + '">' + ranges.map(function (item) {
            item = item || {};
            var levelId = item.level_id || item.choice_gid || getFieldValue(row, 'level_id') || '';
            var prop = item.prop || getFieldValue(row, 'comm_rate') || 0;
            var label = (item.level_name ? item.level_name + ' / ' : '') + prop + '%';
            return '<option value="' + esc(levelId) + '" data-comm-prop="' + esc(prop) + '" data-extra-val="' + esc(item.extra_val || 0) + '"' + (item.selected ? ' selected' : '') + '>' + esc(label) + '</option>';
        }).join('') + '</select>';
    }

    function notify(message, type) {
        var old = document.querySelector('.crm-toast');
        if (old) old.remove();
        var node = document.createElement('div');
        node.className = 'crm-toast crm-toast-' + (type || 'success');
        node.textContent = message;
        document.body.appendChild(node);
        window.setTimeout(function () {
            node.classList.add('is-leaving');
            window.setTimeout(function () {
                if (node.parentNode) node.parentNode.removeChild(node);
            }, 180);
        }, 1600);
    }

    function numericValue(value) {
        if (value === null || value === undefined || value === '') return null;
        var number = Number(String(value).replace(/,/g, '').replace(/[^\d.-]/g, ''));
        return isFinite(number) ? number : null;
    }

    function isMoneyKey(key) {
        return /(amount|balance|equity|funds|profit|commission|rebate|withdraw|deposit|swaps|margin|credit|money|actdraw|rj_|qk_|fy_|net_worth|yuerj|yuecj|comm|price|stop_loss|take_profit|volume|lots|^sl$|^tp$)/i.test(key || '');
    }

    function moneyClass(key, value) {
        var name = String(key || '');
        var number = numericValue(value);
        if (number !== null && number < 0) return 'crm-money-negative';
        if (number !== null && number > 0 && /(profit|floating|rebate|fy_|total_rebate)/i.test(name)) return 'crm-money-positive';
        if (/(withdraw|apply_amount|total_withdraw|qk_|actdraw|loss|yuecj)/i.test(name)) return 'crm-money-negative';
        if (/(commission|swaps|fee)/i.test(name)) return 'crm-money-warning';
        if (/(open_price|close_price|price|stop_loss|take_profit|^sl$|^tp$)/i.test(name)) return 'crm-money-info';
        if (/(volume|lots)/i.test(name)) return 'crm-money-volume';
        if (/(deposit|amount|balance|equity|funds|credit|rj_|yuerj|net_worth|money)/i.test(name)) return 'crm-money-positive';
        return 'crm-money-neutral';
    }

    function isMessageKey(key) {
        return /(comment|remark|message|info|desc)$/i.test(key || '') || /(user_message|用户消息)/i.test(key || '');
    }

    function decodeMessageText(value) {
        var text = String(value || '').trim().replace(/\\r\\n|\\n|\\r/g, '\n');
        if (/%[0-9a-f]{2}/i.test(text)) {
            try {
                text = decodeURIComponent(text.replace(/\+/g, '%20'));
            } catch (e) {}
        }
        return text;
    }

    function normalizeMessageKey(key) {
        var clean = String(key || '').replace(/["'{\[\]]/g, '').trim();
        var map = {
            user_msg: 'user_message',
            userMessage: 'user_message',
            customer_msg: 'customer_message',
            remark: 'remarks',
            memo: 'remarks'
        };
        return map[clean] || clean;
    }

    function parsedMessageParts(value) {
        if (value === null || value === undefined || value === '') return [];
        if (typeof value === 'object') {
            return Object.keys(value).map(function (key) {
                return { key: normalizeMessageKey(key), value: value[key] };
            });
        }

        var text = decodeMessageText(value);
        try {
            var parsed = JSON.parse(text);
            if (parsed && typeof parsed === 'object') return parsedMessageParts(parsed);
        } catch (e) {}

        if (/[?&][^=&\s]+=[^&]+/.test(text) || /^[^=&\s]+=[\s\S]*&[^=&\s]+=/i.test(text)) {
            var query = text.charAt(0) === '?' ? text.slice(1) : text.replace(/^[^?]*\?/, '');
            try {
                var params = new URLSearchParams(query);
                var rows = [];
                params.forEach(function (paramValue, paramKey) {
                    rows.push({ key: normalizeMessageKey(paramKey), value: paramValue });
                });
                if (rows.length) return rows;
            } catch (e) {}
        }

        return text.split(/[;\n&|,，；]+/).map(function (part) {
            var clean = part.trim();
            if (!clean) return null;
            var index = clean.indexOf('=');
            if (index === -1) index = clean.indexOf('=>');
            if (index === -1) index = clean.indexOf(':');
            if (index === -1) index = clean.indexOf('：');
            if (index === -1) return { key: tr('front.message', '消息', 'Message'), value: clean };
            return {
                key: normalizeMessageKey(clean.slice(0, index)),
                value: clean.slice(index + (clean.charAt(index + 1) === '>' ? 2 : 1)).replace(/["'}\]]/g, '').trim()
            };
        }).filter(Boolean);
    }

    function messageHtml(value) {
        var parts = parsedMessageParts(value);
        if (!parts.length) return esc(fmt(value));
        return '<dl class="crm-message-kv">' + parts.map(function (part) {
            var content = isMoneyKey(part.key) && numericValue(part.value) !== null
                ? '<span class="crm-money ' + moneyClass(part.key, part.value) + '">' + esc(fmt(part.value)) + '</span>'
                : esc(fmt(part.value));
            return '<div><dt>' + esc(fieldLabel(part.key) || part.key) + '</dt><dd>' + content + '</dd></div>';
        }).join('') + '</dl>';
    }

    function messagePreviewHtml(value) {
        var parts = parsedMessageParts(value);
        var text = parts.length
            ? parts.map(function (part) { return (fieldLabel(part.key) || part.key) + ': ' + fmt(part.value); }).join(' / ')
            : fmt(value);
        if (text.length > 72) {
            text = text.slice(0, 72) + '...';
        }
        return '<span class="crm-message-preview" title="' + esc(fmt(value)) + '">' + esc(text) + '</span>';
    }

    function valueHtml(key, value, context) {
        if (key === 'is_default') {
            return Number(value) === 1 ? esc(tr('front.yes', '是', 'Yes')) : esc(tr('front.no', '否', 'No'));
        }
        if (isMessageKey(key)) {
            if (context === 'table') return messagePreviewHtml(value);
            return messageHtml(value);
        }
        var images = /(^|_)(image|images|avatar|photo|voucher|url)(_|$)/i.test(key) ? parseImages(value) : [];
        if (images.length) {
            return '<span class="crm-image-icons">' + images.map(function (src, index) {
                return '<a href="' + esc(imageUrl(src)) + '" target="_blank" rel="noopener" title="' + esc(fieldLabel(key) + ' ' + (index + 1)) + '">▧</a>';
            }).join('') + '</span>';
        }
        if (isMoneyKey(key) && numericValue(value) !== null) {
            return '<span class="crm-money ' + moneyClass(key, value) + '">' + esc(fmt(value)) + '</span>';
        }
        return esc(fmt(value));
    }

    // 根据行数据构造可见链路。如果后端已经返回祖先链路，就截断到
    // 当前点击的用户；否则就按点击顺序逐层追加用户ID。
    function chainIdsFromRow(row, clickedId) {
        var source = Array.isArray(row && row.chain) ? row.chain : [];
        var ids = source.map(function (item) {
            return String(item && typeof item === 'object' ? (item.user_id || item.userId || item.id || '') : item || '').trim();
        }).filter(Boolean);
        clickedId = String(clickedId || '').trim();
        if (clickedId) {
            var sourceIndex = ids.indexOf(clickedId);
            if (sourceIndex >= 0) {
                return ids.slice(0, sourceIndex + 1);
            }
            var currentIndex = currentChain.indexOf(clickedId);
            if (currentIndex >= 0) {
                return currentChain.slice(0, currentIndex + 1);
            }
            ids.push(clickedId);
        }
        return ids;
    }

    function tableCellHtml(key, row, rowIndex) {
        var value = getFieldValue(row, key);
        if (currentTableConfig && currentTableConfig.confirmLevel && /^(userGroupId|comm_rate)$/i.test(key || '')) {
            return agentLevelSelectHtml(row, rowIndex);
        }
        if (/^(user_id|userId|trans_uid|agent_id)$/i.test(key || '') && value !== null && value !== undefined && value !== '') {
            return '<button type="button" class="crm-user-id-link" data-chain-row="' + esc(rowIndex) + '" data-chain-user="' + esc(value) + '">' + esc(fmt(value)) + '</button>';
        }
        if (/^(user_name|userName|username)$/i.test(key || '') && value !== null && value !== undefined && value !== '') {
            var userId = getFieldValue(row, 'user_id') || getFieldValue(row, 'userId') || getFieldValue(row, 'mt4_login') || getFieldValue(row, 'login');
            if (userId) {
                return '<button type="button" class="crm-user-name-link" data-row-detail="' + esc(rowIndex) + '">' + esc(fmt(value)) + '</button>';
            }
        }
        return valueHtml(key, value, 'table');
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
        if (key === 'userId') return String(10010 + index);
        if (key === 'agent_id') return 800 + index;
        if (key === 'user_name') return 'customer_' + (1000 + index);
        if (key === 'userName') return 'agent_' + (1000 + index);
        if (key === 'userEmail') return 'agent' + index + '@example.com';
        if (key === 'userPhone') return '1380000' + String(1000 + index);
        if (key === 'userGroupId') return (index % 3) + 1;
        if (key === 'rec_crt_date') return date;
        if (key === 'username') return 'admin_' + (index + 1);
        if (key === 'email') return 'user' + index + '@example.com';
        if (key === 'order_no') return 'CO2026' + (100000 + index);
        if (key === 'amount' || key === 'apply_amount' || key === 'total_deposit' || key === 'total_withdraw' || key === 'total_rebate' || key === 'total_funds' || key === 'equity' || key === 'relation_amount') return Math.round((1200 + index * 83.7) * 100) / 100;
        if (key === 'commission' || key === 'commission_amount') return Math.round((68 + index * 9.35) * 100) / 100;
        if (key === 'balance') return Math.round((18000 - index * 217.4) * 100) / 100;
        if (key === 'total_funds' || key === 'equity' || key === 'total') return Math.round((52000 + index * 1330.5) * 100) / 100;
        if (key === 'profit' || key === 'profit_7d' || key === 'profit_15d' || key === 'profit_30d') return Math.round(((index % 2 ? -1 : 1) * (260 + index * 18.2)) * 100) / 100;
        if (key === 'open_order_count' || key === 'closed_order_count' || key === 'direct_agents' || key === 'direct_customers' || key === 'indirect_customers') return (index + 1) * 3;
        if (key === 'volume') return Math.round((0.1 + (index % 8) * 0.15) * 100) / 100;
        if (key === 'symbol') return ['XAUUSD', 'EURUSD', 'GBPUSD', 'USDJPY'][index % 4];
        if (key === 'status' || key === 'review_status') return ['pending', 'approved', 'processing', 'rejected'][index % 4];
        if (key === 'images' || key === 'voucher_images') return JSON.stringify(['uploads/voucher/demo_' + (index + 1) + '.jpg']);
        if (key === 'flow_type') return currentFlowType || ['deposit', 'withdraw', 'withdraw_apply', 'direct_deposit'][index % 4];
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
        if (key === 'current_level') return 'A' + ((index % 3) + 1);
        if (key === 'next_level') return 'A' + ((index % 3) + 2);
        if (key === 'is_confirmed') return index % 2 ? 'confirmed' : 'pending';
        if (key.indexOf('.') !== -1) return mockValue(key.split('.').pop(), index);
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
            if (config && config.confirmLevel) {
                row.userId = String(row.userId || 10010 + i);
                row.userName = row.userName || 'agent_' + (1000 + i);
                row.userEmail = row.userEmail || 'agent' + i + '@example.com';
                row.userPhone = row.userPhone || '1380000' + String(1000 + i);
                row.userGroupId = row.userGroupId || ((i % 3) + 1);
                row.comm_rate = 35 + (i % 4) * 5;
                row.level_id = (i % 3) + 1;
                row.agent_level_name = row.agent_level_name || 'Level ' + row.level_id;
                row.range_list = defaultAgentRange(row, i);
            }
            if (config && (config.endpoint === '/agentSubList' || config.endpoint === '/agentCustomerList')) {
                row.total_deposit = mockValue('total_deposit', i);
                row.total_withdraw = mockValue('total_withdraw', i);
                row.total_rebate = mockValue('total_rebate', i);
                row.commission_rate = 8 + (i % 5) * 2;
                row.open_order_count = mockValue('open_order_count', i);
                row.closed_order_count = mockValue('closed_order_count', i);
                row.profit_7d = mockValue('profit_7d', i);
                row.profit_15d = mockValue('profit_15d', i);
                row.profit_30d = mockValue('profit_30d', i);
            }
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

    function flowTabs() {
        return [
            { type: 'deposit', label: tr('front.flow_deposit', '入金流水', 'Deposit Flow') },
            { type: 'withdraw', label: tr('front.flow_withdraw', '出金流水', 'Withdrawal Flow') },
            { type: 'withdraw_apply', label: tr('front.flow_withdraw_apply', '出金申请', 'Withdrawal Requests') },
            { type: 'direct_deposit', label: tr('front.flow_direct_deposit', '直属客户入金流水', 'Direct Customer Deposit') },
            { type: 'direct_withdraw', label: tr('front.flow_direct_withdraw', '直属客户出金流水', 'Direct Customer Withdrawal') },
            { type: 'direct_agents_deposit', label: tr('front.flow_direct_agents_deposit', '直属代理入金流水', 'Direct Agent Deposit') },
            { type: 'direct_agents_withdraw', label: tr('front.flow_direct_agents_withdraw', '直属代理出金流水', 'Direct Agent Withdrawal') }
        ];
    }

    function flowTabsHtml() {
        return '<div class="crm-flow-tabs" role="tablist">' + flowTabs().map(function (item) {
            var active = item.type === currentFlowType;
            return '<button type="button" class="' + (active ? 'active' : '') + '" data-flow-tab="' + esc(item.type) + '" role="tab" aria-selected="' + (active ? 'true' : 'false') + '">' + esc(item.label) + '</button>';
        }).join('') + '</div>';
    }

    function tableFiltersHtml(config) {
        var filters = tableFilters(config).filter(function (filter) {
            return !(config && config.flowTabs && filter.name === 'flow_type');
        });
        return '<form class="crm-table-filters" data-table-filter>' + filters.map(function (filter) {
            var isDate = filter.type === 'date';
            var type = isDate ? 'text' : 'text';
            var value = currentTableFilters[filter.name] || '';
            var label = tr(filter.label, fieldLabel(filter.name));
            return '<label><span>' + esc(label) + '</span><input class="crm-plain-input' + (isDate ? ' crm-date-input' : '') + '" type="' + type + '" name="' + esc(filter.name) + '" value="' + esc(value) + '" placeholder="' + esc(label) + '" autocomplete="off"' + (isDate ? ' data-date-picker' : '') + '></label>';
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
        var payload = Object.assign({}, (config && config.defaultFilters) || {}, currentTableFilters, { page: 1, per_page: 15, limit: 15 });
        if (config && config.flowTabs) {
            payload.flow_type = currentFlowType;
        }
        return payload;
    }

    function tableCacheKey(config) {
        return [guard, config && config.endpoint, config && config.flowTabs ? currentFlowType : '', JSON.stringify(currentTableFilters || {})].join('|');
    }

    function mockDepositPage() {
        return {
            user: { user_id: '-', balance: 0 },
            is_allowed: true,
            disabled_message: '',
            exchange_rates: { USD: 1, CNY: 7 },
            deposit_limits: { min: 10, max: 500000 },
            channels: [
                { name: 'USDT-TRC20', code: 'USDT_TRC20', exchange_rate: 1, min_amount: 10, max_amount: 500000 },
                { name: 'Bank Card', code: 'BANK_CARD', exchange_rate: 7, min_amount: 10, max_amount: 500000 }
            ]
        };
    }

    function depositChannelCode(channel) {
        return String((channel && (channel.code || channel.channel_code || channel.id)) || '');
    }

    function depositFormHtml(data) {
        data = data || currentDepositPageData || mockDepositPage();
        var channels = Array.isArray(data.channels) && data.channels.length ? data.channels : mockDepositPage().channels;
        var limits = data.deposit_limits || {};
        var disabled = data.is_allowed === false;
        return '<section class="crm-section crm-deposit-form-shell' + (disabled ? ' is-disabled' : '') + '" id="crmDepositFormShell"><div class="crm-section-head"><div><h2 class="crm-section-title">' + esc(tr('front.deposit', '入金', 'Deposit')) + '</h2><p class="crm-section-subtitle">' + esc(disabled ? (data.disabled_message || tr('front.deposit_disabled', '当前暂不可入金', 'Deposit is currently unavailable')) : tr('front.deposit_form_desc', '选择通道并填写入金金额。', 'Choose a channel and enter the deposit amount.')) + '</p></div></div><form id="crmDepositForm" class="crm-deposit-form"><input type="hidden" name="channel" id="crmDepositChannel"><label><span>' + esc(tr('front.deposit_account', '入金账号', 'Deposit Account')) + '</span><input class="crm-plain-input" value="' + esc((data.user && data.user.user_id) || '-') + '" readonly></label><label><span>' + esc(tr('front.deposit_amount', '入金金额', 'Deposit Amount')) + '</span><input class="crm-plain-input" id="crmDepositAmount" name="amount" type="number" min="' + esc(limits.min || 0) + '" max="' + esc(limits.max || '') + '" step="0.01" autocomplete="off"></label><label><span>' + esc(tr('front.exchange_rate', '汇率', 'Exchange Rate')) + '</span><input class="crm-plain-input" id="crmDepositRate" readonly></label><label><span>' + esc(tr('front.actual_payment', '实际支付', 'Actual Payment')) + '</span><input class="crm-plain-input" id="crmDepositActual" readonly></label><div class="crm-deposit-tabs" role="tablist" aria-label="' + esc(tr('front.payment_channel', '支付通道', 'Payment Channel')) + '">' + channels.map(function (channel, index) {
            var code = depositChannelCode(channel);
            return '<button type="button" class="crm-deposit-channel" role="tab" aria-selected="false" data-channel="' + esc(code) + '" data-rate="' + esc(channel.exchange_rate || 1) + '"><strong>' + esc(channel.name || code || (tr('front.channel', '通道', 'Channel') + ' ' + (index + 1))) + '</strong><span>' + esc(tr('front.limit_range', '限额', 'Limit')) + ': ' + esc(fmt(channel.min_amount || limits.min || '-')) + ' - ' + esc(fmt(channel.max_amount || limits.max || '-')) + '</span></button>';
        }).join('') + '</div><div class="crm-deposit-actions"><button class="crm-plain-primary" type="submit"' + (disabled ? ' disabled' : '') + '>' + esc(tr('common.submit', '提交', 'Submit')) + '</button><button class="crm-plain-secondary" type="button" data-action="deposit-reset">' + esc(tr('front.reset_amount', '重置金额', 'Reset Amount')) + '</button></div></form></section>';
    }

    function updateDepositActual() {
        var amount = document.getElementById('crmDepositAmount');
        var rate = document.getElementById('crmDepositRate');
        var actual = document.getElementById('crmDepositActual');
        var value = numericValue(amount && amount.value);
        var rateValue = numericValue(rate && rate.value) || 1;
        if (!actual) return;
        actual.value = value === null ? '' : fmt(Math.round(value * rateValue * 100) / 100);
    }

    function resetDepositAmount() {
        var amount = document.getElementById('crmDepositAmount');
        var actual = document.getElementById('crmDepositActual');
        if (amount) amount.value = '';
        if (actual) actual.value = '';
    }

    function bindDepositForm() {
        var form = document.getElementById('crmDepositForm');
        if (!form || form.dataset.bound) return;
        form.dataset.bound = '1';

        form.querySelectorAll('.crm-deposit-channel').forEach(function (button) {
            button.addEventListener('click', function () {
                form.querySelectorAll('.crm-deposit-channel').forEach(function (item) { item.classList.remove('active'); });
                button.classList.add('active');
                form.querySelectorAll('.crm-deposit-channel').forEach(function (item) { item.setAttribute('aria-selected', item === button ? 'true' : 'false'); });
                document.getElementById('crmDepositChannel').value = button.getAttribute('data-channel') || '';
                document.getElementById('crmDepositRate').value = button.getAttribute('data-rate') || '1';
                resetDepositAmount();
            });
        });
        var firstChannel = form.querySelector('.crm-deposit-channel');
        if (firstChannel && !document.getElementById('crmDepositChannel').value) {
            firstChannel.click();
        }

        var amount = document.getElementById('crmDepositAmount');
        if (amount) amount.addEventListener('input', updateDepositActual);

        var reset = form.querySelector('[data-action="deposit-reset"]');
        if (reset) {
            reset.addEventListener('click', function () {
                resetDepositAmount();
            });
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            var channel = document.getElementById('crmDepositChannel').value;
            var value = numericValue(document.getElementById('crmDepositAmount').value);
            if (!channel) {
                notify(tr('front.payment_channel_required', '请选择支付通道', 'Please choose a payment channel'), 'warning');
                return;
            }
            if (!value || value <= 0) {
                notify(tr('front.deposit_amount_required', '请输入入金金额', 'Please enter deposit amount'), 'warning');
                return;
            }
            api('/depositApply', { amount: value, deposit_amt_usd: value, channel: channel, pay_channel: channel }).then(function (body) {
                if (!success(body)) {
                    notify(body.message || tr('response.operation_failed', '操作失败', 'Operation failed'), 'warning');
                    return;
                }
                notify(tr('front.deposit_submit_success', '入金申请已提交', 'Deposit request submitted'), 'success');
                if (body.data && body.data.payment_url) {
                    window.open(body.data.payment_url, '_blank', 'noopener');
                }
                resetDepositAmount();
                loadTableData(currentTableConfig);
            }).catch(function () {
                notify(tr('common.network_error', '网络异常', 'Network error'), 'warning');
            });
        });
    }

    function loadDepositPageData() {
        if (!currentTableConfig || !currentTableConfig.depositForm) return;
        api('/depositPage', {}).then(function (body) {
            if (!success(body) || !body.data) return;
            currentDepositPageData = body.data;
            var shell = document.getElementById('crmDepositFormShell');
            if (shell) {
                shell.outerHTML = depositFormHtml(currentDepositPageData);
                bindDepositForm();
            }
        }).catch(function () {});
    }

    function collectProfilePayload(form) {
        var payload = {};
        Array.prototype.slice.call(form.elements || []).forEach(function (field) {
            if (!field.name || field.type === 'file' || field.disabled) return;
            if ((field.type === 'checkbox' || field.type === 'radio') && !field.checked) return;
            payload[field.name] = (field.value || '').trim();
        });
        return payload;
    }

    function profileFormTitle(form) {
        var title = form && form.querySelector('h3');
        return title ? title.textContent.replace(/\s+/g, ' ').trim() : tr('front.profile', '个人资料', 'Profile');
    }

    function fieldTitle(field) {
        var label = field && field.closest ? field.closest('label') : null;
        var span = label && label.querySelector('span');
        return span ? span.textContent.replace(/\s+/g, ' ').trim() : (field && field.name ? fieldLabel(field.name) : '');
    }

    function validateRequiredForm(form) {
        var fields = Array.prototype.slice.call(form.querySelectorAll('[required]'));
        for (var index = 0; index < fields.length; index += 1) {
            var field = fields[index];
            var emptyFile = field.type === 'file' && (!field.files || !field.files.length);
            var emptyValue = field.type !== 'file' && !String(field.value || '').trim();
            if (!emptyFile && !emptyValue) continue;
            notify(tr('front.profile_required_message', '请填写【{form}】的【{field}】', 'Please complete {field} in {form}')
                .replace('{form}', profileFormTitle(form))
                .replace('{field}', fieldTitle(field)), 'warning');
            if (field.focus) field.focus();
            return false;
        }
        return true;
    }

    function uploadSizeText(file) {
        var size = file && file.size ? file.size : 0;
        if (size >= 1024 * 1024) return (size / 1024 / 1024).toFixed(1) + ' MB';
        if (size >= 1024) return Math.ceil(size / 1024) + ' KB';
        return size + ' B';
    }

    function clearProfileUpload(input) {
        var label = input && input.closest ? input.closest('.crm-upload-field') : null;
        var name = label && label.querySelector('[data-upload-name]');
        var status = label && label.querySelector('[data-upload-status]');
        var preview = label && label.querySelector('[data-upload-preview]');
        var clear = label && label.querySelector('[data-upload-clear]');

        if (!input || !label) return;
        if (input.dataset.previewUrl && window.URL && URL.revokeObjectURL) {
            URL.revokeObjectURL(input.dataset.previewUrl);
        }
        delete input.dataset.previewUrl;
        input.value = '';
        if (name) name.textContent = tr('front.choose_image', '选择图片', 'Choose image');
        if (status) status.textContent = tr('front.no_file_selected', '未选择文件', 'No file selected');
        if (preview) {
            preview.removeAttribute('src');
            preview.classList.remove('active');
        }
        if (clear) clear.classList.remove('is-visible');
    }

    function clearProfileUploads(root) {
        (root || app).querySelectorAll('.crm-upload-field input[type="file"]').forEach(function (input) {
            clearProfileUpload(input);
        });
    }

    function bindUploadFields(root) {
        (root || app).querySelectorAll('.crm-upload-field input[type="file"]').forEach(function (input) {
            if (input.dataset.uploadBound) return;
            input.dataset.uploadBound = '1';
            var label = input.closest('.crm-upload-field');
            var trigger = label && label.querySelector('[data-upload-trigger]');
            var clear = label && label.querySelector('[data-upload-clear]');

            if (trigger) {
                trigger.addEventListener('click', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    input.click();
                });
            }
            if (clear) {
                clear.addEventListener('click', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    clearProfileUpload(input);
                });
            }
            input.addEventListener('change', function () {
                var name = label && label.querySelector('[data-upload-name]');
                var status = label && label.querySelector('[data-upload-status]');
                var preview = label && label.querySelector('[data-upload-preview]');
                var file = input.files && input.files[0];
                if (input.dataset.previewUrl && window.URL && URL.revokeObjectURL) {
                    URL.revokeObjectURL(input.dataset.previewUrl);
                    delete input.dataset.previewUrl;
                }
                if (name) {
                    name.textContent = file ? file.name : tr('front.choose_image', '选择图片', 'Choose image');
                }
                if (status) {
                    status.textContent = file ? uploadSizeText(file) : tr('front.no_file_selected', '未选择文件', 'No file selected');
                }
                if (clear) {
                    clear.classList.toggle('is-visible', !!file);
                }
                if (!preview) return;
                if (!file || !/^image\//.test(file.type)) {
                    preview.removeAttribute('src');
                    preview.classList.remove('active');
                    return;
                }
                if (!window.URL || !URL.createObjectURL) return;
                input.dataset.previewUrl = URL.createObjectURL(file);
                preview.src = input.dataset.previewUrl;
                preview.classList.add('active');
            });
        });
    }

    function reloadProfile() {
        var config = modules.profile;
        api('/profileInfo', {}).then(function (body) {
            if (success(body) && body.data && currentPage === 'profile') {
                renderPageWithData(config, body.data);
            }
        }).catch(function () {});
    }

    function bindProfileForms() {
        app.querySelectorAll('[data-profile-form]').forEach(function (form) {
            if (form.dataset.bound) return;
            form.dataset.bound = '1';
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                if (!validateRequiredForm(form)) return;
                var endpoint = form.getAttribute('data-endpoint');
                var mode = form.getAttribute('data-profile-form');
                var button = form.querySelector('button[type="submit"]');
                var oldText = button ? button.textContent : '';
                if (button) {
                    button.disabled = true;
                    button.textContent = tr('common.submitting', '提交中...', 'Submitting...');
                }
                var request = mode === 'multipart'
                    ? apiMultipart(endpoint, new FormData(form))
                    : api(endpoint, collectProfilePayload(form));

                request.then(function (body) {
                    if (!success(body)) {
                        notify(body.message || tr('response.operation_failed', '操作失败', 'Operation failed'), 'warning');
                        return;
                    }
                    notify(body.message || tr('response.updated', '已更新', 'Updated'), 'success');
                    if (form.getAttribute('data-reset-success') === '1') {
                        form.reset();
                        clearProfileUploads(form);
                    }
                    if (endpoint === '/changePassword') {
                        removeToken();
                        window.setTimeout(function () {
                            window.location.href = boot.loginPath || (basePath + '/login');
                        }, 900);
                        return;
                    }
                    reloadProfile();
                }).catch(function () {
                    notify(tr('common.network_error', '网络异常', 'Network error'), 'warning');
                }).then(function () {
                    if (button) {
                        button.disabled = false;
                        button.textContent = oldText;
                    }
                });
            });
        });
    }

    function addressFormHtml() {
        return '<section class="crm-section crm-address-form-shell"><div class="crm-section-head"><div><h2 class="crm-section-title">' + esc(tr('front.gift_address', '地址管理', 'Address')) + '</h2><p class="crm-section-subtitle">' + esc(tr('front.gift_address_desc', '维护收货地址，支持设为默认。', 'Manage delivery addresses and default address.')) + '</p></div></div><form class="crm-address-form" id="crmAddressForm"><input type="hidden" name="id" id="crmAddressId"><label><span>' + esc(tr('front.receiver_name', '收货人', 'Receiver')) + '</span><input class="crm-plain-input" name="recipient_name" required autocomplete="off"></label><label><span>' + esc(tr('front.phone', '电话', 'Phone')) + '</span><input class="crm-plain-input" name="recipient_phone" required autocomplete="off"></label><label class="crm-address-wide"><span>' + esc(tr('front.address', '地址', 'Address')) + '</span><textarea class="crm-plain-input" name="recipient_address" required></textarea></label><label class="crm-address-check"><input type="checkbox" name="is_default" value="1"><span>' + esc(tr('front.default_address', '默认地址', 'Default Address')) + '</span></label><div class="crm-address-actions"><button class="crm-plain-primary" type="submit">' + esc(tr('common.save', '保存', 'Save')) + '</button><button class="crm-plain-secondary" type="button" data-action="address-reset">' + esc(tr('common.reset', '重置', 'Reset')) + '</button></div></form></section>';
    }

    function resetAddressForm() {
        var form = document.getElementById('crmAddressForm');
        if (!form) return;
        form.reset();
        form.querySelector('[name="id"]').value = '';
    }

    function fillAddressForm(row) {
        var form = document.getElementById('crmAddressForm');
        if (!form || !row) return;
        form.querySelector('[name="id"]').value = getFieldValue(row, 'id') || '';
        form.querySelector('[name="recipient_name"]').value = getFieldValue(row, 'recipient_name') || getFieldValue(row, 'receiver_name') || '';
        form.querySelector('[name="recipient_phone"]').value = getFieldValue(row, 'recipient_phone') || getFieldValue(row, 'phone') || '';
        form.querySelector('[name="recipient_address"]').value = getFieldValue(row, 'recipient_address') || getFieldValue(row, 'address') || '';
        form.querySelector('[name="is_default"]').checked = Number(getFieldValue(row, 'is_default')) === 1;
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function submitAddress(payload) {
        var endpoint = payload.id ? '/giftUpdateAddress' : '/giftAddAddress';
        return api(endpoint, payload).then(function (body) {
            if (!success(body)) {
                notify(body.message || tr('response.operation_failed', '操作失败', 'Operation failed'), 'warning');
                return;
            }
            notify(body.message || tr('response.updated', '已保存', 'Saved'), 'success');
            resetAddressForm();
            loadTableData(currentTableConfig);
        }).catch(function () {
            notify(tr('common.network_error', '网络异常', 'Network error'), 'warning');
        });
    }

    function bindAddressBook() {
        var form = document.getElementById('crmAddressForm');
        if (form && !form.dataset.bound) {
            form.dataset.bound = '1';
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                var payload = collectProfilePayload(form);
                payload.is_default = form.querySelector('[name="is_default"]').checked ? 1 : 0;
                submitAddress(payload);
            });
            var reset = form.querySelector('[data-action="address-reset"]');
            if (reset) reset.addEventListener('click', resetAddressForm);
        }

        app.querySelectorAll('[data-address-action]').forEach(function (button) {
            if (button.dataset.bound) return;
            button.dataset.bound = '1';
            button.addEventListener('click', function () {
                var index = Number(button.getAttribute('data-row-detail'));
                var row = currentRows[index];
                if (!row) return;
                var action = button.getAttribute('data-address-action');
                var id = getFieldValue(row, 'id');
                if (action === 'edit') {
                    fillAddressForm(row);
                    return;
                }
                if (action === 'default') {
                    submitAddress({ id: id, is_default: 1 });
                    return;
                }
                if (action === 'delete') {
                    if (!window.confirm(tr('common.confirm_delete', '确认删除？', 'Confirm delete?'))) return;
                    api('/giftDeleteAddress', { id: id }).then(function (body) {
                        if (!success(body)) {
                            notify(body.message || tr('response.operation_failed', '操作失败', 'Operation failed'), 'warning');
                            return;
                        }
                        notify(body.message || tr('response.deleted', '已删除', 'Deleted'), 'success');
                        loadTableData(currentTableConfig);
                    }).catch(function () {
                        notify(tr('common.network_error', '网络异常', 'Network error'), 'warning');
                    });
                }
            });
        });
    }

    function groupChangeFormHtml() {
        return '<section class="crm-section crm-group-change-form-shell"><div class="crm-section-head"><div><h2 class="crm-section-title">' + esc(tr('front.group_change', '组别变更', 'Group Change')) + '</h2><p class="crm-section-subtitle">' + esc(tr('front.group_change_desc', '提交直属客户组别变更申请。', 'Submit customer group change request.')) + '</p></div></div><form class="crm-group-change-form" id="crmGroupChangeForm"><label><span>' + esc(tr('front.target_user_id', '目标用户ID', 'Target User ID')) + '</span><input class="crm-plain-input" name="target_user_id" type="number" required autocomplete="off"></label><label><span>' + esc(tr('front.new_group_id', '新组别ID', 'New Group ID')) + '</span><input class="crm-plain-input" name="new_group_id" type="number" required autocomplete="off"></label><label class="crm-group-change-wide"><span>' + esc(tr('front.apply_reason', '申请原因', 'Application Reason')) + '</span><textarea class="crm-plain-input" name="reason"></textarea></label><div class="crm-group-change-actions"><button class="crm-plain-primary" type="submit">' + esc(tr('common.submit', '提交', 'Submit')) + '</button><button class="crm-plain-secondary" type="button" data-action="group-change-reset">' + esc(tr('common.reset', '重置', 'Reset')) + '</button></div></form></section>';
    }

    function bindGroupChangeForm() {
        var form = document.getElementById('crmGroupChangeForm');
        if (!form || form.dataset.bound) return;
        form.dataset.bound = '1';
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            api('/agentGroupChange', collectProfilePayload(form)).then(function (body) {
                if (!success(body)) {
                    notify(body.message || tr('response.operation_failed', '操作失败', 'Operation failed'), 'warning');
                    return;
                }
                notify(body.message || tr('response.success', '提交成功', 'Submitted'), 'success');
                form.reset();
                loadTableData(currentTableConfig);
            }).catch(function () {
                notify(tr('common.network_error', '网络异常', 'Network error'), 'warning');
            });
        });
        var reset = form.querySelector('[data-action="group-change-reset"]');
        if (reset) reset.addEventListener('click', function () { form.reset(); });
    }

    function selectedAgentLevel(row) {
        var ranges = listFromValue(getFieldValue(row, 'range_list'));
        var selected = ranges.filter(function (item) { return item && item.selected; })[0];
        if (!selected && ranges.length) selected = ranges[0];
        return selected || {
            level_id: getFieldValue(row, 'level_id'),
            prop: getFieldValue(row, 'comm_rate'),
            extra_val: 0
        };
    }

    function bindAgentConfirmActions() {
        app.querySelectorAll('[data-agent-confirm]').forEach(function (button) {
            if (button.dataset.bound) return;
            button.dataset.bound = '1';
            button.addEventListener('click', function () {
                var rowIndex = Number(button.getAttribute('data-row-detail'));
                var row = currentRows[rowIndex];
                if (!row) return;
                var level = selectedAgentLevel(row);
                var select = app.querySelector('.crm-agent-level-select[data-agent-level-row="' + rowIndex + '"]');
                if (select && select.options[select.selectedIndex]) {
                    var option = select.options[select.selectedIndex];
                    level = {
                        level_id: option.value,
                        prop: option.getAttribute('data-comm-prop'),
                        extra_val: option.getAttribute('data-extra-val') || 0
                    };
                }
                var payload = {
                    userId: getFieldValue(row, 'userId') || getFieldValue(row, 'user_id'),
                    comm_prop: level.prop || getFieldValue(row, 'comm_rate') || 0,
                    agent_gId: level.level_id || level.choice_gid || getFieldValue(row, 'level_id'),
                    extra_val: level.extra_val || 0
                };
                if (!payload.userId || !payload.agent_gId) {
                    notify(tr('response.validation_failed', '参数不完整', 'Invalid parameters'), 'warning');
                    return;
                }
                if (!window.confirm(tr('front.confirm_level_desc', '确认该代理等级？', 'Confirm this agent level?'))) return;
                api('/agentConfirmLevelChange', payload).then(function (body) {
                    if (!success(body)) {
                        notify(body.message || tr('response.operation_failed', '操作失败', 'Operation failed'), 'warning');
                        return;
                    }
                    notify(body.message || tr('response.success', '确认成功', 'Confirmed'), 'success');
                    loadTableData(currentTableConfig);
                }).catch(function () {
                    notify(tr('common.network_error', '网络异常', 'Network error'), 'warning');
                });
            });
        });
    }

    function bindFlowTabs() {
        app.querySelectorAll('[data-flow-tab]').forEach(function (button) {
            if (button.dataset.bound) return;
            button.dataset.bound = '1';
            button.addEventListener('click', function () {
                var type = button.getAttribute('data-flow-tab') || 'deposit';
                if (type === currentFlowType) return;
                currentFlowType = type;
                currentTableFilters = {};
                loadTableData(currentTableConfig);
            });
        });
    }

    function loadTableData(config) {
        if (!config || !config.endpoint) return;
        var cachedRows = tableCache[tableCacheKey(config)];
        var initialRows = cachedRows || (config.mockWhenEmpty ? mockRows(config, 6) : []);
        renderTable(config, initialRows, { loading: !initialRows.length });
        bindPageContent();
        var requestPage = currentPage;
        afterNextPaint(function () {
            api(config.endpoint, tablePayload(config)).then(function (body) {
                if (currentPage !== requestPage || currentTableConfig !== config) return;
                if (success(body)) {
                    renderPageWithData(config, normalizeRows(body.data, config));
                }
            }).catch(function () {
                if (currentPage !== requestPage || currentTableConfig !== config) return;
                renderPageWithData(config, []);
            });
        });
    }

    function skinOptions() {
        return skins.map(function (item) {
            var current = item.value === skin;
            return '<option value="' + esc(item.value) + '"' + (current ? ' selected' : '') + '>' + esc(skinLabel(item)) + '</option>';
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
            '<p class="crm-login-desc">' + esc(guard === 'admin' ? tr('auth.login_desc_admin', '使用管理员账号登录后台工作台。', 'Sign in with an administrator account.') : tr('auth.login_desc_front', '输入邮箱或用户 ID，系统自动识别账号类型后登录。', 'Enter email or user ID; the system detects the account type automatically.')) + '</p>',
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

    function bindMobileMenu() {
        var shell = app.querySelector('.crm-shell');
        if (!shell) return;

        app.querySelectorAll('[data-action="toggle-menu"]').forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.stopPropagation();
                shell.classList.toggle('is-menu-open');
            });
        });

        shell.addEventListener('click', function (event) {
            if (!shell.classList.contains('is-menu-open')) return;
            if (event.target.closest && event.target.closest('.crm-sidebar')) return;
            if (event.target.closest && event.target.closest('[data-action="toggle-menu"]')) return;
            shell.classList.remove('is-menu-open');
        });
    }

    function bindSearch() {
        app.querySelectorAll('[data-action="toggle-summary"]').forEach(function (button) {
            if (button.dataset.summaryBound) return;
            button.dataset.summaryBound = '1';
            button.addEventListener('click', function () {
                var summary = app.querySelector('.crm-table-summary');
                var icon = button.querySelector('span');
                if (!summary) return;
                summary.classList.toggle('is-collapsed');
                if (icon) icon.textContent = summary.classList.contains('is-collapsed') ? '》' : '∨';
            });
        });
        app.querySelectorAll('[data-table-filter]').forEach(function (form) {
            if (form.dataset.filterBound) return;
            form.dataset.filterBound = '1';
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                currentTableFilters = collectTableFilters();
                loadTableData(currentTableConfig);
            });
        });
        app.querySelectorAll('[data-action="table-reset"]').forEach(function (button) {
            if (button.dataset.resetBound) return;
            button.dataset.resetBound = '1';
            button.addEventListener('click', function () {
                currentTableFilters = {};
                loadTableData(currentTableConfig);
            });
        });
        app.querySelectorAll('[data-action="search"]').forEach(function (button) {
            if (button.dataset.searchBound) return;
            button.dataset.searchBound = '1';
            button.addEventListener('click', filterCurrentTable);
        });
        bindDateInputs();
    }

    function bindDateInputs() {
        app.querySelectorAll('[data-date-picker]').forEach(function (input) {
            if (input.dataset.dateBound) return;
            input.dataset.dateBound = '1';
            var open = function () {
                showDatePopover(input);
            };
            input.addEventListener('focus', open);
            input.addEventListener('click', open);
        });
    }

    function closeDatePopover() {
        var old = document.getElementById('crmDatePopover');
        if (old) old.remove();
    }

    function padDate(value) {
        value = Number(value || 0);
        return value < 10 ? '0' + value : String(value);
    }

    function formatDateValue(date) {
        return date.getFullYear() + '-' + padDate(date.getMonth() + 1) + '-' + padDate(date.getDate());
    }

    function parseDateValue(value) {
        var parts = String(value || '').split('-').map(function (part) { return Number(part); });
        if (parts.length === 3 && parts[0] && parts[1] && parts[2]) {
            return new Date(parts[0], parts[1] - 1, parts[2]);
        }
        return new Date();
    }

    // Naive 页面不用浏览器原生 date 输入，而是渲染一个和 Layui 行为接近的
    // 弹层日历：点击输入框弹出、可切换月份、点日期立即回填。
    function dateGridHtml(viewDate, selectedValue) {
        var year = viewDate.getFullYear();
        var month = viewDate.getMonth();
        var first = new Date(year, month, 1);
        var start = new Date(year, month, 1 - first.getDay());
        var today = formatDateValue(new Date());
        var weeks = locale === 'en' ? ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] : ['日', '一', '二', '三', '四', '五', '六'];
        var html = '<div class="crm-date-head"><button type="button" data-date-prev>&lt;</button><strong>' + year + '-' + padDate(month + 1) + '</strong><button type="button" data-date-next>&gt;</button></div>';
        html += '<div class="crm-date-week">' + weeks.map(function (week) { return '<span>' + esc(week) + '</span>'; }).join('') + '</div><div class="crm-date-grid">';
        for (var i = 0; i < 42; i += 1) {
            var day = new Date(start.getFullYear(), start.getMonth(), start.getDate() + i);
            var value = formatDateValue(day);
            var className = (day.getMonth() === month ? '' : ' is-muted') + (value === selectedValue ? ' is-selected' : '') + (value === today ? ' is-today' : '');
            html += '<button type="button" class="' + className + '" data-date-value="' + value + '">' + day.getDate() + '</button>';
        }
        html += '</div><div class="crm-date-actions"><button type="button" data-date-today>' + esc(tr('common.today', '今天', 'Today')) + '</button><button type="button" data-date-clear>' + esc(tr('common.clear', '清空', 'Clear')) + '</button></div>';
        return html;
    }

    function showDatePopover(input) {
        closeDatePopover();
        var rect = input.getBoundingClientRect();
        var pop = document.createElement('div');
        var viewDate = parseDateValue(input.value);
        pop.id = 'crmDatePopover';
        pop.className = 'crm-date-popover';
        pop.innerHTML = dateGridHtml(viewDate, input.value || '');
        document.body.appendChild(pop);
        pop.style.left = Math.max(12, rect.left + window.scrollX) + 'px';
        pop.style.top = (rect.bottom + window.scrollY + 6) + 'px';
        pop.addEventListener('click', function (event) {
            var target = event.target;
            if (target.matches('[data-date-prev]')) {
                viewDate = new Date(viewDate.getFullYear(), viewDate.getMonth() - 1, 1);
                pop.innerHTML = dateGridHtml(viewDate, input.value || '');
                return;
            }
            if (target.matches('[data-date-next]')) {
                viewDate = new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 1);
                pop.innerHTML = dateGridHtml(viewDate, input.value || '');
                return;
            }
            if (target.matches('[data-date-today]')) {
                input.value = formatDateValue(new Date());
                closeDatePopover();
                return;
            }
            if (target.matches('[data-date-clear]')) {
                input.value = '';
                closeDatePopover();
                return;
            }
            if (target.matches('[data-date-value]')) {
                input.value = target.getAttribute('data-date-value');
                closeDatePopover();
            }
        });
        window.setTimeout(function () {
            document.addEventListener('mousedown', function handler(event) {
                if (event.target !== input && !pop.contains(event.target)) {
                    closeDatePopover();
                    document.removeEventListener('mousedown', handler);
                }
            });
        }, 0);
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
            if (button.dataset.copyBound) return;
            button.dataset.copyBound = '1';
            button.addEventListener('click', function () {
                var value = button.getAttribute('data-copy');
                var done = function () {
                    notify(tr('front.copy_success', '复制成功', 'Copied'), 'success');
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(value).then(done).catch(done);
                } else {
                    var input = document.createElement('textarea');
                    input.value = value || '';
                    input.setAttribute('readonly', 'readonly');
                    input.style.position = 'fixed';
                    input.style.left = '-9999px';
                    document.body.appendChild(input);
                    input.select();
                    try { document.execCommand('copy'); } catch (e) {}
                    document.body.removeChild(input);
                    done();
                }
            });
        });
    }

    function bindRowDetail() {
        app.querySelectorAll('[data-row-detail]:not([data-address-action]):not([data-agent-confirm])').forEach(function (button) {
            if (button.dataset.detailBound) return;
            button.dataset.detailBound = '1';
            button.addEventListener('click', function () {
                showRowDetail(button.getAttribute('data-row-detail'));
            });
        });
        app.querySelectorAll('.crm-plain-table tbody tr[data-row-index]').forEach(function (row) {
            if (row.dataset.detailBound) return;
            row.dataset.detailBound = '1';
            row.addEventListener('dblclick', function () {
                showRowDetail(row.getAttribute('data-row-index'));
            });
        });
        bindDetailModal(document.getElementById('plainDetailModal'));
    }

    function syncChainBar() {
        var panel = app.querySelector('.crm-data-panel');
        if (!panel) return;
        var current = panel.querySelector('.crm-chain-bar');
        if (!currentChain.length) {
            if (current) current.remove();
            return;
        }
        var html = chainHtml();
        if (current) {
            current.outerHTML = html;
            return;
        }
        var summary = panel.querySelector('.crm-table-summary');
        if (summary) {
            summary.insertAdjacentHTML('beforebegin', html);
        }
    }

    // 点击用户ID时只展开链路，不直接打开详情弹窗，避免链路展示和
    // 行详情逻辑互相干扰。
    function bindUserChain() {
        app.querySelectorAll('.crm-user-id-link').forEach(function (button) {
            if (button.dataset.chainBound) return;
            button.dataset.chainBound = '1';
            button.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                var rowIndex = Number(button.getAttribute('data-chain-row'));
                var userId = button.getAttribute('data-chain-user');
                var row = currentRows[rowIndex] || {};
                currentChain = chainIdsFromRow(row, userId);
                syncChainBar();
            });
        });
    }

    function bindNavigation() {
        app.querySelectorAll('[data-page]').forEach(function (button) {
            if (button.dataset.navBound) return;
            button.dataset.navBound = '1';
            button.addEventListener('click', function () {
                var shell = app.querySelector('.crm-shell');
                if (shell) shell.classList.remove('is-menu-open');
                navigate(button.getAttribute('data-page'));
            });
        });
    }

    function bindPageContent() {
        bindNavigation();
        bindSearch();
        bindCopy();
        bindRowDetail();
        bindUserChain();
        bindDepositForm();
        bindUploadFields(app);
        bindProfileForms();
        bindAddressBook();
        bindGroupChangeForm();
        bindAgentConfirmActions();
        bindFlowTabs();
    }

    function bindShell() {
        bindStyle();
        bindSkin();
        bindLocale();
        bindNavigation();
        app.querySelectorAll('[data-go]').forEach(function (button) {
            button.addEventListener('click', function () {
                window.location.href = button.getAttribute('data-go');
            });
        });
        bindRefresh();
        bindMobileMenu();
        bindLegacyStyle();
        bindSearch();
        bindLogout();
        bindCopy();
        bindRowDetail();
        bindUserChain();
        bindDepositForm();
        bindUploadFields(app);
        bindProfileForms();
        bindAddressBook();
        bindGroupChangeForm();
        bindAgentConfirmActions();
        bindFlowTabs();
        if (!resizeBound) {
            window.addEventListener('resize', renderCharts);
            resizeBound = true;
        }
    }

    function navigate(page) {
        if (!page || page === currentPage) return;
        currentPage = page;
        currentTableFilters = {};
        currentChain = [];
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
            renderDetail(config, mockRows({ fields: (config.detailFields || ['user_id', 'user_name', 'email', 'account_type', 'total_funds', 'equity', 'effective_credit', 'commission_rate']) }, 1)[0]);
            api(config.endpoint, {}).then(function (body) {
                if (success(body) && body.data) renderPageWithData(config, normalizeDetailData(config, body.data));
            }).catch(function () {});
        } else if (config.kind === 'profile') {
            renderProfile(config, normalizeProfileData(mockRows({ fields: ['user_id', 'user_name', 'email', 'phone', 'account_type', 'auth_status'] }, 1)[0]));
            api(config.endpoint, {}).then(function (body) {
                if (success(body) && body.data) renderPageWithData(config, body.data);
            }).catch(function () {});
        } else {
            var cachedRows = tableCache[tableCacheKey(config)];
            var initialRows = cachedRows || (config.mockWhenEmpty ? mockRows(config, 6) : []);
            renderTable(config, initialRows, { loading: !initialRows.length });
            var requestPage = currentPage;
            afterNextPaint(function () {
                api(config.endpoint, tablePayload(config)).then(function (body) {
                    if (currentPage !== requestPage || currentTableConfig !== config) return;
                    if (success(body)) renderPageWithData(config, normalizeRows(body.data, config));
                }).catch(function () {
                    if (currentPage !== requestPage || currentTableConfig !== config) return;
                    renderPageWithData(config, tableCache[tableCacheKey(config)] || []);
                });
            });
            if (config.depositForm) {
                loadDepositPageData();
            }
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
        var hadContent = !!document.getElementById('crmPlainContent');
        if (!hadContent) {
            shellStart(config);
        }
        if (config.kind === 'dashboard') {
            renderDashboard(data);
        } else if (config.kind === 'detail') {
            renderDetail(config, normalizeDetailData(config, data));
        } else if (config.kind === 'profile') {
            renderProfile(config, normalizeProfileData(data));
        } else {
            data = Array.isArray(data) ? data : [];
            if (!data.length && config.mockWhenEmpty) {
                data = mockRows(config);
            }
            tableCache[tableCacheKey(config)] = data;
            renderTable(config, data, { loading: false });
        }
        if (hadContent) {
            bindPageContent();
        } else {
            shellEnd();
        }
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
        var dashboardDesc = guard === 'admin' ? tr('front.naive_admin_desc', '平台指标、审核队列、用户增长和系统公告集中展示。', 'Platform metrics, review queues, user growth and announcements.') : tr('front.naive_front_desc', '账户资金、注册链接、交易概览和近期公告集中展示。', 'Funds, register links, trading overview and announcements.');
        var authGuide = guard === 'front' && Number(profile.auth_status || profile.id_card_status || 0) !== 1
            ? '<button type="button" class="crm-auth-guide" data-page="profile">▧ ' + esc(tr('front.identity_upload_guide', '去上传身份证完成实名认证', 'Upload ID card to verify')) + '</button>'
            : '';
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
            '<section class="crm-overview-band"><div class="crm-overview-copy"><span class="crm-kicker">CoCRM v5</span><h2>' + esc(dashboardTitle) + '</h2><p>' + esc(dashboardDesc) + '</p>' + authGuide + '</div><div class="crm-quick-panel"><p>' + esc(tr('front.quick_entry', '快捷入口', 'Quick Entry')) + '</p><div>' + menus.slice(1, 9).map(function (item) {
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

    function normalizeProfileData(data) {
        data = data || {};
        if (data.login || data.info || data.auth) {
            return Object.assign({}, data.login || {}, data.info || {}, data.auth || {});
        }
        return data;
    }

    function profileInput(label, name, value, type, required) {
        return '<label><span>' + esc(label) + '</span><input class="crm-plain-input" type="' + esc(type || 'text') + '" name="' + esc(name) + '" value="' + esc(value || '') + '"' + (required ? ' required' : '') + ' autocomplete="off"></label>';
    }

    function profileFile(label, name, required) {
        return '<label class="crm-upload-field"><span>' + esc(label) + '</span><input class="crm-file-input" type="file" name="' + esc(name) + '" accept="image/*"' + (required ? ' required' : '') + '><div class="crm-upload-toolbar"><button class="crm-upload-card" type="button" data-upload-trigger><b>▧</b><em data-upload-name>' + esc(tr('front.choose_image', '选择图片', 'Choose image')) + '</em></button><button class="crm-upload-clear" type="button" data-upload-clear title="' + esc(tr('common.reset', '重置', 'Reset')) + '">×</button></div><em class="crm-upload-status" data-upload-status>' + esc(tr('front.no_file_selected', '未选择文件', 'No file selected')) + '</em><img alt="" data-upload-preview></label>';
    }

    function profileForm(title, endpoint, mode, fields, submitText, resetAfterSuccess) {
        return '<form class="crm-profile-form" data-profile-form="' + esc(mode || 'json') + '" data-endpoint="' + esc(endpoint) + '"' + (resetAfterSuccess ? ' data-reset-success="1"' : '') + '><h3>' + title + '</h3><div class="crm-profile-fields">' + fields.join('') + '</div><div class="crm-profile-actions"><button class="crm-plain-primary" type="submit">' + esc(submitText || tr('common.submit', '提交', 'Submit')) + '</button></div></form>';
    }

    function profileStatus(text) {
        return text ? '<span class="crm-profile-status">' + esc(text) + '</span>' : '';
    }

    function renderProfile(config, data) {
        var content = document.getElementById('crmPlainContent');
        data = data || {};
        var avatar = data.avatar_url || '/images/default-avatar.svg';
        var gender = String(data.gender || '1');
        var genderOptions = '<select class="crm-plain-input" name="gender"><option value="1"' + (gender === '1' ? ' selected' : '') + '>' + esc(tr('register.male', '男', 'Male')) + '</option><option value="2"' + (gender === '2' ? ' selected' : '') + '>' + esc(tr('register.female', '女', 'Female')) + '</option></select>';
        var overviewKeys = ['user_id', 'user_name', 'email_masked', 'phone_masked', 'account_type', 'auth_status', 'id_card_no_masked', 'bank_no_masked', 'last_login_at'];
        var overview = '<section class="crm-section"><div class="crm-profile-head"><img src="' + esc(avatar) + '" alt="" class="crm-profile-avatar"><div><h2 class="crm-section-title">' + esc(data.user_name || data.email || config.title) + '</h2><p class="crm-section-subtitle">' + esc(tr('front.profile_desc', '账户资料、认证、换绑和安全设置。', 'Profile, verification, binding changes and security settings.')) + '</p></div></div><div class="crm-detail-grid">' + overviewKeys.map(function (key) {
            return '<div class="crm-detail-item"><p class="crm-detail-label">' + esc(fieldLabel(key)) + '</p><p class="crm-detail-value">' + valueHtml(key, getFieldValue(data, key)) + '</p></div>';
        }).join('') + '</div></section>';

        var profileFields = [
            profileInput(fieldLabel('user_name'), 'user_name', data.user_name, 'text', true),
            '<label><span>' + esc(tr('register.gender', '性别', 'Gender')) + '</span>' + genderOptions + '</label>',
            '<label class="crm-profile-wide"><span>' + esc(tr('auth.address', '地址', 'Address')) + '</span><textarea class="crm-plain-input" name="address">' + esc(data.address || '') + '</textarea></label>'
        ];
        var avatarFields = [profileFile(tr('front.avatar', '头像', 'Avatar'), 'avatar', true)];
        var passwordFields = [
            profileInput(tr('auth.old_password', '原密码', 'Old Password'), 'old_password', '', 'password', true),
            profileInput(tr('auth.new_password', '新密码', 'New Password'), 'password', '', 'password', true),
            profileInput(tr('auth.confirm_password', '确认密码', 'Confirm Password'), 'password_confirmation', '', 'password', true)
        ];
        var emailFields = [
            profileInput(tr('front.full_phone', '完整手机号', 'Full Phone'), 'verify_phone', '', 'text', true),
            profileInput(tr('front.current_email', '当前邮箱', 'Current Email'), 'current_email', data.email || '', 'email', true),
            profileInput(tr('front.new_email', '新邮箱', 'New Email'), 'new_email', '', 'email', true)
        ];
        var phoneFields = [
            profileInput(tr('front.full_phone', '完整手机号', 'Full Phone'), 'verify_phone', '', 'text', true),
            profileInput(tr('front.current_email', '当前邮箱', 'Current Email'), 'verify_email', data.email || '', 'email', true),
            profileInput(tr('front.new_phone', '新手机号', 'New Phone'), 'new_phone', '', 'text', true)
        ];
        var identityFields = [
            profileInput(fieldLabel('id_card_no'), 'id_card_no', '', 'text', true),
            profileFile(tr('front.id_card_front', '身份证正面', 'ID Card Front'), 'id_card_front', true),
            profileFile(tr('front.id_card_back', '身份证反面', 'ID Card Back'), 'id_card_back', true)
        ];
        var bankFields = [
            profileInput(fieldLabel('bank_name'), 'bank_name', data.bank_name || '', 'text', true),
            profileInput(fieldLabel('bank_no'), 'bank_no', '', 'text', true),
            profileInput(fieldLabel('bank_addr'), 'bank_addr', data.bank_addr || '', 'text', true),
            profileFile(tr('front.bank_card_image', '银行卡正面', 'Bank Card Front'), 'bank_card_img', true),
            profileFile(tr('front.bank_card_back_image', '银行卡反面', 'Bank Card Back'), 'bank_card_back_img', true)
        ];
        var bankChangeFields = [
            profileInput(tr('front.full_phone', '完整手机号', 'Full Phone'), 'verify_phone', '', 'text', true),
            profileInput(tr('front.current_email', '当前邮箱', 'Current Email'), 'verify_email', data.email || '', 'email', true),
            profileInput(fieldLabel('bank_name'), 'bank_name', '', 'text', true),
            profileInput(fieldLabel('bank_no'), 'bank_no', '', 'text', true),
            profileInput(fieldLabel('bank_addr'), 'bank_addr', '', 'text', true),
            profileFile(tr('front.bank_card_image', '银行卡正面', 'Bank Card Front'), 'bank_card_img', true),
            profileFile(tr('front.bank_card_back_image', '银行卡反面', 'Bank Card Back'), 'bank_card_back_img', true)
        ];
        var cancelFields = [
            profileInput(tr('front.full_phone', '完整手机号', 'Full Phone'), 'verify_phone', '', 'text', true),
            profileInput(tr('front.current_email', '当前邮箱', 'Current Email'), 'verify_email', data.email || '', 'email', true),
            '<label class="crm-profile-wide"><span>' + esc(tr('front.cancel_reason', '销户原因', 'Cancel Reason')) + '</span><textarea class="crm-plain-input" name="reason" required></textarea></label>'
        ];

        content.innerHTML = overview + '<section class="crm-profile-board">' + [
            profileForm(tr('front.edit_profile', '编辑资料', 'Edit Profile'), '/updateProfile', 'json', profileFields, tr('common.save', '保存', 'Save')),
            profileForm(tr('front.avatar_upload', '头像上传', 'Avatar Upload'), '/uploadAvatar', 'multipart', avatarFields),
            profileForm(tr('front.change_password', '修改密码', 'Change Password'), '/changePassword', 'json', passwordFields, tr('common.save', '保存', 'Save'), true),
            profileForm(tr('front.change_email', '修改邮箱', 'Change Email'), '/changeEmail', 'json', emailFields, tr('common.save', '保存', 'Save'), true),
            profileForm(tr('front.change_phone', '修改手机', 'Change Phone'), '/changePhone', 'json', phoneFields, tr('common.save', '保存', 'Save'), true),
            profileForm(tr('front.identity_audit', '身份认证', 'Identity Verification') + profileStatus(data.id_card_status_text), '/submitIdentity', 'multipart', identityFields),
            profileForm(tr('front.bank_audit', '银行卡认证', 'Bank Verification') + profileStatus(data.bank_status_text), '/submitBankCard', 'multipart', bankFields),
            profileForm(tr('front.change_bank', '变更银行卡', 'Change Bank Card'), '/submitBankChange', 'multipart', bankChangeFields, tr('common.submit', '提交', 'Submit'), true),
            profileForm(tr('front.cancel_account', '销户申请', 'Cancel Account'), '/cancelApply', 'json', cancelFields, tr('common.submit', '提交', 'Submit'), true)
        ].join('') + '</section>';
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
            return '<div class="crm-detail-item"><p class="crm-detail-label">' + esc(fieldLabel(key)) + '</p><p class="crm-detail-value">' + valueHtml(key, getFieldValue(data, key)) + '</p></div>';
        }).join('') + '</div></section>' + charts;
        if (charts) renderCharts(currentStats);
    }

    function chainHtml() {
        if (!currentChain.length) return '';
        return '<div class="crm-chain-bar"><span>' + esc(tr('front.current_chain', '当前链路', 'Current Chain')) + '</span><strong>' + currentChain.map(function (id) { return esc(id); }).join(' / ') + '</strong></div>';
    }

    function renderTable(config, rows, options) {
        var content = document.getElementById('crmPlainContent');
        options = options || {};
        rows = rows || [];
        var fields = config.fields || Object.keys(rows[0] || {});
        currentChain = [];
        currentTableConfig = config;
        currentRows = rows || [];
        var summary = tableSummary(fields, currentRows);
        var leadingForm = config.depositForm && guard === 'front' ? depositFormHtml(currentDepositPageData) : '';
        if (config.addressBook && guard === 'front') {
            leadingForm = addressFormHtml();
        }
        if (config.groupChangeForm && guard === 'front') {
            leadingForm = groupChangeFormHtml();
        }
        if (config.flowTabs && guard === 'front') {
            leadingForm = flowTabsHtml();
        }
        var bodyHtml = '';
        if (options.loading && !rows.length) {
            bodyHtml = loadingRowsHtml(fields.length + 1);
        } else if (!rows.length) {
            bodyHtml = '<tr class="crm-table-state"><td colspan="' + (fields.length + 1) + '">' + esc(tr('common.no_data', '暂无数据', 'No data')) + '</td></tr>';
        } else {
            bodyHtml = rows.map(function (row, rowIndex) {
                var actions = config.addressBook
                    ? '<button class="crm-table-action" type="button" data-address-action="edit" data-row-detail="' + rowIndex + '">' + esc(tr('common.edit', '编辑', 'Edit')) + '</button><button class="crm-table-action" type="button" data-address-action="default" data-row-detail="' + rowIndex + '">' + esc(tr('front.set_default', '设为默认', 'Set Default')) + '</button><button class="crm-table-action danger" type="button" data-address-action="delete" data-row-detail="' + rowIndex + '">' + esc(tr('common.delete', '删除', 'Delete')) + '</button>'
                    : '<button class="crm-table-action" type="button" data-row-detail="' + rowIndex + '">' + esc(tr('common.detail', '详情', 'Detail')) + '</button>';
                if (config.confirmLevel) {
                    actions = '<button class="crm-table-action" type="button" data-agent-confirm="1" data-row-detail="' + rowIndex + '">' + esc(tr('front.confirm_level', '确认等级', 'Confirm Level')) + '</button>' + actions;
                }
                return '<tr data-row-index="' + rowIndex + '">' + fields.map(function (key) { var raw = getFieldValue(row, key); return '<td title="' + esc(fmt(raw)) + '">' + tableCellHtml(key, row, rowIndex) + '</td>'; }).join('') + '<td><div class="crm-row-actions">' + actions + '</div></td></tr>';
            }).join('');
        }
        content.innerHTML = [
            leadingForm,
            '<section class="crm-data-panel' + (options.loading ? ' is-loading' : '') + '"><h2 class="crm-data-title">' + esc(config.title) + '</h2><div class="crm-table-filters"><input class="crm-plain-input" id="plainSearch" placeholder="' + esc(tr('common.search_placeholder', '输入关键词', 'Search keyword')) + '"><button class="crm-plain-secondary" data-action="search" type="button">' + esc(tr('common.search', '搜索', 'Search')) + '</button></div>',
            config.collapsibleSummary ? '<button type="button" class="crm-summary-toggle" data-action="toggle-summary" title="' + esc(tr('front.summary', '汇总', 'Summary')) + '"><span>》</span></button>' : '',
            chainHtml(),
            '<div class="crm-table-summary' + (config.collapsibleSummary ? ' is-collapsed' : '') + '">' + summaryText(summary, currentRows.length) + '</div><div class="crm-table-wrap"><table class="crm-plain-table"><thead><tr>' + fields.map(function (key) { return '<th>' + esc(fieldLabel(key)) + '</th>'; }).join('') + '<th>' + esc(tr('common.operation', '操作', 'Action')) + '</th></tr></thead><tbody>' + bodyHtml + '</tbody></table></div></section>'
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
            var normalizedKey = String(key || '').toLowerCase();
            rows.forEach(function (row) {
                var value = Number(getFieldValue(row, key));
                if (isFinite(value) && !/(_id$|^id$|id$|status|sort|level$|phone|mobile|tel|recipient_phone|is_default)/.test(normalizedKey)) {
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
        if (/(amount|balance|equity|credit|margin|profit|commission|rebate|fee|swaps|funds|rate|total|open_order_count|closed_order_count)/i.test(key)) return 'finance';
        if (/(_at|_time|date|created|updated|modify)/i.test(key)) return 'time';
        return 'other';
    }

    function nestedDetailKeys(value) {
        if (!value || typeof value !== 'object') return [];
        if (Array.isArray(value)) {
            if (!value.length || typeof value[0] !== 'object') return [];
            return Object.keys(value[0]);
        }
        return Object.keys(value);
    }

    function nestedDetailValue(value, key, index) {
        if (Array.isArray(value)) {
            return value[index] && typeof value[index] === 'object' ? value[index][key] : undefined;
        }
        return value ? value[key] : undefined;
    }

    function nestedDetailSection(key, value) {
        var keys = nestedDetailKeys(value);
        if (!keys.length) return '';
        var rows = Array.isArray(value) ? value : [value];
        return rows.map(function (item, index) {
            var title = fieldLabel(key) + (rows.length > 1 ? ' #' + (index + 1) : '');
            return '<section class="crm-modal-section crm-modal-section-nested"><h4>' + esc(title) + '</h4><dl>' + keys.map(function (nestedKey) {
                var nestedValue = nestedDetailValue(item, nestedKey, 0);
                return '<div><dt>' + esc(fieldLabel(nestedKey)) + '</dt><dd>' + valueHtml(nestedKey, nestedValue) + '</dd></div>';
            }).join('') + '</dl></section>';
        }).join('');
    }

    // 从多个后端别名里取出第一个可用指标，兼容代理和客户两种
    // 返回结构，保证详情图表都能正常显示。
    function firstMetric(row, keys) {
        var result = { raw: null, number: null, key: '' };
        keys.some(function (key) {
            var raw = getFieldValue(row, key);
            var number = numericValue(raw);
            if (raw !== null && raw !== undefined && raw !== '' && number !== null) {
                result = { raw: raw, number: number, key: key };
                return true;
            }
            return false;
        });
        return result;
    }

    // 每个指标组渲染一个紧凑的柱状块。这里用原生 DOM/CSS 实现，
    // 即使没有 ECharts 也能保持弹窗轻量。
    function userDetailChartGroup(title, items) {
        var available = items.filter(function (item) { return item.metric.number !== null; });
        if (!available.length) return '';
        var max = Math.max.apply(null, available.map(function (item) { return Math.abs(item.metric.number); }).concat([1]));
        return '<article class="crm-user-detail-chart-card"><h5>' + esc(title) + '</h5>' + available.map(function (item) {
            var width = Math.max(6, Math.min(100, Math.round(Math.abs(item.metric.number) / max * 100)));
            var negative = item.metric.number < 0 ? ' is-negative' : '';
            return '<div class="crm-user-detail-bar-row"><div class="crm-user-detail-bar-meta"><span>' + esc(item.label) + '</span><strong>' + valueHtml(item.metric.key, item.metric.raw) + '</strong></div><div class="crm-user-detail-track"><i class="crm-user-detail-bar' + negative + '" style="width:' + width + '%"></i></div></div>';
        }).join('') + '</article>';
    }

    // 组合用户要求的资金/订单/盈亏三组图表；如果数据缺失，就直接
    // 返回空字符串，不额外塞占位内容。
    function userDetailChartsHtml(row) {
        var finance = userDetailChartGroup(tr('front.funds_profile', '资金画像', 'Funds Profile'), [
            { label: fieldLabel('total_deposit'), metric: firstMetric(row, ['total_deposit', 'rj_money', 'total_yuerj']) },
            { label: fieldLabel('total_withdraw'), metric: firstMetric(row, ['total_withdraw', 'qk_money', 'total_yuecj']) },
            { label: fieldLabel('total_rebate'), metric: firstMetric(row, ['total_rebate', 'fy_money', 'total_comm']) },
            { label: fieldLabel('commission_rate'), metric: firstMetric(row, ['commission_rate', 'commprop', 'comm_rate']) }
        ]);
        var orders = userDetailChartGroup(tr('front.order_profile', '订单画像', 'Order Profile'), [
            { label: fieldLabel('open_order_count'), metric: firstMetric(row, ['open_order_count', 'open_count', 'open_orders_count']) },
            { label: fieldLabel('closed_order_count'), metric: firstMetric(row, ['closed_order_count', 'closed_count', 'close_count']) }
        ]);
        var profit = userDetailChartGroup(tr('front.profit_profile', '盈亏画像', 'Profit Profile'), [
            { label: fieldLabel('profit_7d'), metric: firstMetric(row, ['profit_7d']) },
            { label: fieldLabel('profit_15d'), metric: firstMetric(row, ['profit_15d']) },
            { label: fieldLabel('profit_30d'), metric: firstMetric(row, ['profit_30d', 'total_profit']) }
        ]);
        var groups = [finance, orders, profit].filter(Boolean);
        if (!groups.length) return '';
        return '<section class="crm-modal-section crm-user-detail-charts"><h4>' + esc(tr('front.account_chart_title', '账户图表', 'Account Charts')) + '</h4><div class="crm-user-detail-chart-grid">' + groups.join('') + '</div></section>';
    }

    function isHiddenDetailKey(key) {
        // 代理管理详情不展示登录历史类字段；个人中心仍保留自己的最后登录时间。
        if (currentPage === 'agent-customers' || currentPage === 'agent-sub' || currentPage === 'agents') {
            return /^(login_history|login_history_label|last_login_ip|last_login_at)$/i.test(key);
        }
        return key === 'login_history_label';
    }

    function loadingRowsHtml(colspan) {
        var rows = [];
        for (var rowIndex = 0; rowIndex < 6; rowIndex += 1) {
            var cells = [];
            for (var cellIndex = 0; cellIndex < colspan; cellIndex += 1) {
                cells.push('<td><span class="crm-skeleton-line" style="width:' + (42 + ((rowIndex + cellIndex) % 4) * 12) + '%"></span></td>');
            }
            rows.push('<tr class="crm-table-skeleton">' + cells.join('') + '</tr>');
        }
        return rows.join('');
    }

    function detailModalHtml(row) {
        var groups = { identity: [], trade: [], finance: [], time: [], other: [] };
        var nested = [];
        Object.keys(row || {}).forEach(function (key) {
            if (isHiddenDetailKey(key)) return;
            if (row[key] && typeof row[key] === 'object' && nestedDetailKeys(row[key]).length) {
                nested.push(key);
                return;
            }
            groups[detailGroupForKey(key)].push(key);
        });
        return '<div class="crm-modal-mask" id="plainDetailModal"><div class="crm-modal-card" role="dialog" aria-modal="true"><div class="crm-modal-head"><h3>' + esc(tr(currentPage === 'agent-customers' || currentPage === 'agent-sub' ? 'front.user_detail' : 'common.detail', '详情', 'Detail')) + '</h3><button type="button" class="crm-modal-close" data-close-detail>&times;</button></div><div class="crm-modal-body">' + userDetailChartsHtml(row) + Object.keys(groups).map(function (group) {
            if (!groups[group].length) return '';
            return '<section class="crm-modal-section"><h4>' + esc(detailGroupTitle(group)) + '</h4><dl>' + groups[group].map(function (key) {
                return '<div><dt>' + esc(fieldLabel(key)) + '</dt><dd>' + valueHtml(key, getFieldValue(row, key)) + '</dd></div>';
            }).join('') + '</dl></section>';
        }).join('') + nested.map(function (key) {
            if (isHiddenDetailKey(key)) return '';
            return nestedDetailSection(key, row[key]);
        }).join('') + '</div></div></div>';
    }

    function closeDetailModal() {
        // 关闭弹框时推进批次号，让仍在等待的详情请求自动失效。
        detailRequestSerial += 1;
        var modal = document.getElementById('plainDetailModal');
        if (modal) modal.remove();
    }

    function bindDetailModal(modal) {
        // 统一绑定详情弹框关闭事件，避免页面重渲染后对同一个弹框重复绑定监听器。
        if (!modal || modal.dataset.modalBound) return;
        modal.dataset.modalBound = '1';
        modal.querySelectorAll('[data-close-detail]').forEach(function (button) {
            button.addEventListener('click', closeDetailModal);
        });
        modal.addEventListener('click', function (event) {
            if (event.target === modal) closeDetailModal();
        });
    }

    function showRowDetail(index) {
        var row = currentRows[Number(index)];
        if (!row) return;
        closeDetailModal();
        var requestSerial = detailRequestSerial;
        document.body.insertAdjacentHTML('beforeend', detailModalHtml(row));
        bindDetailModal(document.getElementById('plainDetailModal'));
        var detailUserId = getFieldValue(row, 'user_id') || getFieldValue(row, 'userId') || getFieldValue(row, 'mt4_login');
        if ((currentPage === 'agent-customers' || currentPage === 'agent-sub') && detailUserId) {
            api('/userDetail', { user_id: detailUserId }).then(function (body) {
                if (requestSerial !== detailRequestSerial) return;
                if (!success(body) || !body.data) return;
                var modal = document.getElementById('plainDetailModal');
                if (modal) modal.remove();
                document.body.insertAdjacentHTML('beforeend', detailModalHtml(Object.assign({}, row, body.data)));
                bindDetailModal(document.getElementById('plainDetailModal'));
            }).catch(function () {});
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
