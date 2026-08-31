{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/28
Time: 01:28
--}}
@extends('admin_layui::layouts.app')

@section('title', '客户资料')

@php
    $login = $customer->login;
    $auth = $customer->auth;
    $parentAgent = $customer->parent;
    $currentGroup = $customer->groupConfig;
    $phoneParts = explode('-', (string) $customer->phone, 2);
    $phoneCode = count($phoneParts) === 2 ? $phoneParts[0] : '86';
    $phoneNumber = count($phoneParts) === 2 ? $phoneParts[1] : $phoneParts[0];
    $createdTimestamp = (int) $customer->getRawOriginal('created_at');
    $createdText = $createdTimestamp > 0 ? date('Y-m-d H:i:s', $createdTimestamp) : '-';
    $idCardStatusLabels = [0 => '未提交', 1 => '审核中', 2 => '已通过', 4 => '已退回'];
    $bankStatusLabels = [0 => '未提交', 1 => '审核中', 2 => '已通过', 3 => '变更审核中', 4 => '已拒绝'];
    $idCardStatus = (int) optional($auth)->id_card_status;
    $bankStatus = (int) optional($auth)->bank_status;
    $currentGroupName = trim((string) $customer->mt4_group);
    $knownGroupNames = $customerGroups->pluck('name')->map(function ($name) { return (string) $name; })->all();
@endphp

@section('styles')
<style>
    .legacy-customer-detail {
        --detail-primary: var(--admin-blue);
        --detail-border: var(--admin-line);
        color: var(--admin-strong);
    }
    .legacy-customer-detail .detail-hero,
    .legacy-customer-detail .detail-panel,
    .legacy-customer-detail .detail-stat {
        border: 1px solid var(--detail-border);
        border-radius: 16px;
        background: var(--admin-panel);
        box-shadow: var(--crm-shadow);
    }
    .legacy-customer-detail .detail-hero {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 24px;
        padding: 24px 28px;
        background: linear-gradient(135deg, var(--admin-hover) 0%, var(--admin-panel) 70%, var(--crm-warning-soft) 100%);
    }
    .legacy-customer-detail .detail-kicker {
        margin: 0 0 6px;
        color: var(--detail-primary);
        font-size: 12px;
        font-weight: 700;
        letter-spacing: .12em;
        text-transform: uppercase;
    }
    .legacy-customer-detail .detail-title { margin: 0; font-size: 24px; font-weight: 700; }
    .legacy-customer-detail .detail-subtitle { margin: 8px 0 0; color: var(--admin-muted); line-height: 1.65; }
    .legacy-customer-detail .detail-status-row { display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end; }
    .legacy-customer-detail .detail-chip {
        border: 1px solid var(--detail-primary);
        border-radius: 999px;
        padding: 6px 10px;
        color: var(--detail-primary);
        background: var(--admin-hover);
        font-size: 12px;
        font-weight: 700;
        white-space: nowrap;
    }
    .legacy-customer-detail .detail-chip-warning { border-color: var(--admin-warning); color: var(--admin-warning); background: var(--crm-warning-soft); }
    .legacy-customer-detail .detail-stats {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
        margin: 18px 0;
    }
    .legacy-customer-detail .detail-stat { padding: 17px 18px; }
    .legacy-customer-detail .detail-stat-label { color: var(--admin-muted); font-size: 12px; font-weight: 700; }
    .legacy-customer-detail .detail-stat-value { margin-top: 8px; font-size: 20px; font-weight: 700; font-variant-numeric: tabular-nums; }
    .legacy-customer-detail .detail-panel { margin-top: 18px; padding: 24px 26px; }
    .legacy-customer-detail .detail-section + .detail-section { margin-top: 26px; padding-top: 24px; border-top: 1px solid var(--admin-line); }
    .legacy-customer-detail .detail-section-title { margin: 0 0 17px; font-size: 16px; font-weight: 700; }
    .legacy-customer-detail .detail-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 17px 20px; }
    .legacy-customer-detail .detail-field { min-width: 0; }
    .legacy-customer-detail .detail-field-wide { grid-column: span 2; }
    .legacy-customer-detail .detail-field-full { grid-column: 1 / -1; }
    .legacy-customer-detail .detail-label { display: block; margin-bottom: 8px; color: var(--admin-text); font-size: 13px; font-weight: 700; }
    .legacy-customer-detail .layui-input,
    .legacy-customer-detail .layui-select-title input { border-radius: 9px; }
    .legacy-customer-detail .detail-readonly { color: var(--admin-muted); background: var(--admin-hover); }
    .legacy-customer-detail .detail-help { margin-top: 7px; color: var(--admin-muted); font-size: 12px; line-height: 1.5; }
    .legacy-customer-detail .detail-switches { display: flex; flex-wrap: wrap; gap: 12px 18px; }
    .legacy-customer-detail .detail-actions { display: flex; gap: 10px; margin-top: 26px; padding-top: 20px; border-top: 1px solid var(--admin-line); }
    .legacy-customer-detail .detail-save { border-radius: 9px; background: var(--detail-primary); }
    .legacy-customer-detail :focus-visible { outline: 3px solid var(--crm-focus); outline-offset: 2px; }
    @media (max-width: 1024px) {
        .legacy-customer-detail .detail-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .legacy-customer-detail .detail-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 768px) {
        .legacy-customer-detail .detail-hero { flex-direction: column; padding: 20px; }
        .legacy-customer-detail .detail-status-row { justify-content: flex-start; }
        .legacy-customer-detail .detail-stats,
        .legacy-customer-detail .detail-grid { grid-template-columns: 1fr; }
        .legacy-customer-detail .detail-field-wide,
        .legacy-customer-detail .detail-field-full { grid-column: auto; }
        .legacy-customer-detail .detail-panel { padding: 20px; }
    }
    @media (prefers-reduced-motion: reduce) {
        .legacy-customer-detail *,
        .legacy-customer-detail *::before,
        .legacy-customer-detail *::after { scroll-behavior: auto !important; transition: none !important; }
    }
</style>
@endsection

@section('content')
<div class="layui-fluid legacy-customer-detail" data-legacy-customer-detail data-customer-id="{{ $customer->user_id }}">
    <section class="detail-hero" aria-labelledby="legacyCustomerDetailTitle">
        <div>
            <p class="detail-kicker">Customer profile</p>
            <h2 class="detail-title" id="legacyCustomerDetailTitle">{{ $customer->user_name ?: '未命名客户' }}</h2>
            <p class="detail-subtitle">客户 ID {{ $customer->user_id }} · 上级代理 {{ $parentAgent ? $parentAgent->user_name . ' [' . $parentAgent->user_id . ']' : '平台直属' }}</p>
        </div>
        <div class="detail-status-row" aria-label="客户审核状态">
            <span class="detail-chip">身份证：{{ $idCardStatusLabels[$idCardStatus] ?? '未知' }}</span>
            <span class="detail-chip {{ $bankStatus === 2 ? '' : 'detail-chip-warning' }}">银行卡：{{ $bankStatusLabels[$bankStatus] ?? '未知' }}</span>
            <span class="detail-chip {{ optional($login)->is_enabled ? '' : 'detail-chip-warning' }}">登录：{{ optional($login)->is_enabled ? '启用' : '停用' }}</span>
        </div>
    </section>

    <section class="detail-stats" aria-label="客户资金快照">
        <div class="detail-stat">
            <div class="detail-stat-label">账户余额</div>
            <div class="detail-stat-value">{{ number_format((float) $customer->total_funds, 2, '.', '') }}</div>
        </div>
        <div class="detail-stat">
            <div class="detail-stat-label">账户净值</div>
            <div class="detail-stat-value">{{ number_format((float) $customer->equity, 2, '.', '') }}</div>
        </div>
        <div class="detail-stat">
            <div class="detail-stat-label">可用保证金</div>
            <div class="detail-stat-value">{{ number_format((float) $customer->avail_margin, 2, '.', '') }}</div>
        </div>
        <div class="detail-stat">
            <div class="detail-stat-label">风险率</div>
            <div class="detail-stat-value">{{ number_format((float) $customer->risk_ratio, 2, '.', '') }}</div>
        </div>
    </section>

    {{-- 需求 13：客户数据统计。出入金金额、返佣金额、返佣比例、开/关订单数与近 7/15/30 天盈亏
         全部来自 admin_api_customerStatistics 的真实 DB 查询（BCMath 十进制口径），没有任何前端造数；
         盈亏用 ECharts 图表呈现，图表类型切换按钮沿用前台控制台的 44px 触控目标与 crm-sr-only 约定。 --}}
    <section class="crm-admin-stats-block" id="customerStatisticsBlock"
             data-customer-statistics
             data-customer-statistics-endpoint="{{ route('admin_api_customerStatistics') }}"
             aria-labelledby="customerStatisticsTitle">
        <div class="crm-admin-stats-heading">
            <span class="crm-admin-stats-title" id="customerStatisticsTitle" data-translate="admin.customer_statistics">{{ __('admin.customer_statistics') }}</span>
            <span class="crm-admin-stats-desc" data-translate="admin.table_statistics_desc">{{ __('admin.table_statistics_desc') }}</span>
        </div>
        <div class="crm-table-summary">
            <div class="crm-table-summary-item"><span data-translate="admin.total_deposit">{{ __('admin.total_deposit') }}</span><strong data-customer-stat="total_deposit">0.00</strong></div>
            <div class="crm-table-summary-item"><span data-translate="admin.total_withdraw">{{ __('admin.total_withdraw') }}</span><strong data-customer-stat="total_withdraw">0.00</strong></div>
            <div class="crm-table-summary-item"><span data-translate="admin.net_flow">{{ __('admin.net_flow') }}</span><strong data-customer-stat="net_flow">0.00</strong></div>
            <div class="crm-table-summary-item"><span data-translate="admin.rebate_amount">{{ __('admin.rebate_amount') }}</span><strong data-customer-stat="total_rebate">0.00</strong></div>
            <div class="crm-table-summary-item"><span data-translate="admin.rebate_ratio">{{ __('admin.rebate_ratio') }}</span><strong data-customer-stat="rebate_ratio_percent">0.00</strong></div>
            <div class="crm-table-summary-item"><span data-translate="admin.open_order_count">{{ __('admin.open_order_count') }}</span><strong data-customer-stat="open_order_count">0</strong></div>
            <div class="crm-table-summary-item"><span data-translate="admin.closed_order_count">{{ __('admin.closed_order_count') }}</span><strong data-customer-stat="closed_order_count">0</strong></div>
            <div class="crm-table-summary-item"><span data-translate="admin.profit_7d">{{ __('admin.profit_7d') }}</span><strong data-customer-stat="profit_7d">0.00</strong></div>
            <div class="crm-table-summary-item"><span data-translate="admin.profit_15d">{{ __('admin.profit_15d') }}</span><strong data-customer-stat="profit_15d">0.00</strong></div>
            <div class="crm-table-summary-item"><span data-translate="admin.profit_30d">{{ __('admin.profit_30d') }}</span><strong data-customer-stat="profit_30d">0.00</strong></div>
        </div>

        <div class="crm-chart-grid">
            <div class="crm-chart-card">
                <div class="crm-chart-head">
                    <span data-translate="admin.profit_trend">{{ __('admin.profit_trend') }}</span>
                    <div class="crm-chart-controls" role="group" aria-label="{{ __('admin.profit_window') }}">
                        <button type="button" class="crm-chart-type" data-customer-profit-window="7" aria-pressed="false" title="{{ __('admin.profit_7d') }}" aria-label="{{ __('admin.profit_7d') }}">7<span class="crm-sr-only">{{ __('admin.profit_7d') }}</span></button>
                        <button type="button" class="crm-chart-type" data-customer-profit-window="15" aria-pressed="false" title="{{ __('admin.profit_15d') }}" aria-label="{{ __('admin.profit_15d') }}">15<span class="crm-sr-only">{{ __('admin.profit_15d') }}</span></button>
                        <button type="button" class="crm-chart-type is-active" data-customer-profit-window="30" aria-pressed="true" title="{{ __('admin.profit_30d') }}" aria-label="{{ __('admin.profit_30d') }}">30<span class="crm-sr-only">{{ __('admin.profit_30d') }}</span></button>
                    </div>
                    <div class="crm-chart-controls" role="group" aria-label="{{ __('admin.chart_view_mode') }}">
                        <button type="button" class="crm-chart-type" data-chart-target="customerProfitChart" data-chart-type="bar" title="{{ __('admin.chart_bar') }}" aria-label="{{ __('admin.chart_bar') }}" aria-pressed="false"><i data-lucide="chart-column" aria-hidden="true"></i><span class="crm-sr-only">{{ __('admin.chart_bar') }}</span></button>
                        <button type="button" class="crm-chart-type is-active" data-chart-target="customerProfitChart" data-chart-type="line" title="{{ __('admin.chart_line') }}" aria-label="{{ __('admin.chart_line') }}" aria-pressed="true"><i data-lucide="chart-line" aria-hidden="true"></i><span class="crm-sr-only">{{ __('admin.chart_line') }}</span></button>
                        <button type="button" class="crm-chart-type" data-chart-target="customerProfitChart" data-chart-type="area" title="{{ __('admin.chart_area') }}" aria-label="{{ __('admin.chart_area') }}" aria-pressed="false"><i data-lucide="chart-area" aria-hidden="true"></i><span class="crm-sr-only">{{ __('admin.chart_area') }}</span></button>
                        <button type="button" class="crm-chart-type" data-chart-target="customerProfitChart" data-chart-type="pie" title="{{ __('admin.chart_pie') }}" aria-label="{{ __('admin.chart_pie') }}" aria-pressed="false"><i data-lucide="chart-pie" aria-hidden="true"></i><span class="crm-sr-only">{{ __('admin.chart_pie') }}</span></button>
                    </div>
                </div>
                <div class="crm-chart-canvas" id="customerProfitChart"></div>
            </div>
            <div class="crm-chart-card">
                <div class="crm-chart-head">
                    <span data-translate="admin.deposit_withdraw_amount">{{ __('admin.deposit_withdraw_amount') }}</span>
                    <div class="crm-chart-controls" role="group" aria-label="{{ __('admin.chart_view_mode') }}">
                        <button type="button" class="crm-chart-type" data-chart-target="customerFundsChart" data-chart-type="bar" title="{{ __('admin.chart_bar') }}" aria-label="{{ __('admin.chart_bar') }}" aria-pressed="false"><i data-lucide="chart-column" aria-hidden="true"></i><span class="crm-sr-only">{{ __('admin.chart_bar') }}</span></button>
                        <button type="button" class="crm-chart-type" data-chart-target="customerFundsChart" data-chart-type="line" title="{{ __('admin.chart_line') }}" aria-label="{{ __('admin.chart_line') }}" aria-pressed="false"><i data-lucide="chart-line" aria-hidden="true"></i><span class="crm-sr-only">{{ __('admin.chart_line') }}</span></button>
                        <button type="button" class="crm-chart-type" data-chart-target="customerFundsChart" data-chart-type="area" title="{{ __('admin.chart_area') }}" aria-label="{{ __('admin.chart_area') }}" aria-pressed="false"><i data-lucide="chart-area" aria-hidden="true"></i><span class="crm-sr-only">{{ __('admin.chart_area') }}</span></button>
                        <button type="button" class="crm-chart-type is-active" data-chart-target="customerFundsChart" data-chart-type="pie" title="{{ __('admin.chart_pie') }}" aria-label="{{ __('admin.chart_pie') }}" aria-pressed="true"><i data-lucide="chart-pie" aria-hidden="true"></i><span class="crm-sr-only">{{ __('admin.chart_pie') }}</span></button>
                    </div>
                </div>
                <div class="crm-chart-canvas" id="customerFundsChart"></div>
            </div>
        </div>
    </section>

    <section class="detail-panel">
        <form class="layui-form" id="legacyCustomerDetailForm"
              data-save-endpoint="{{ url('/index/admin/cust/cust_save_info') }}"
              data-bank-status="{{ $bankStatus }}">
            <input type="hidden" name="userId" value="{{ $customer->user_id }}">
            <input type="hidden" name="modules" value="{{ $phoneCode }}">

            <div class="detail-section">
                <h3 class="detail-section-title">基础资料</h3>
                <div class="detail-grid">
                    <div class="detail-field">
                        <label class="detail-label" for="legacyCustomerId">客户 ID</label>
                        <input class="layui-input detail-readonly" id="legacyCustomerId" type="text" value="{{ $customer->user_id }}" readonly>
                    </div>
                    <div class="detail-field">
                        <label class="detail-label" for="legacyCustomerName">客户姓名</label>
                        <input class="layui-input" id="legacyCustomerName" name="username" type="text" maxlength="200"
                               value="{{ $customer->user_name }}" data-initial="{{ $customer->user_name }}" autocomplete="name">
                    </div>
                    <div class="detail-field">
                        <label class="detail-label" for="legacyCustomerPassword">新密码</label>
                        <input class="layui-input" id="legacyCustomerPassword" name="password" type="password"
                               value="********" data-initial="********" autocomplete="new-password">
                        <p class="detail-help">保留星号表示不修改密码。</p>
                    </div>
                    <div class="detail-field">
                        <label class="detail-label" for="legacyCustomerEmail">登录邮箱</label>
                        <input class="layui-input" id="legacyCustomerEmail" name="useremail" type="email" maxlength="191"
                               value="{{ optional($login)->email }}" data-initial="{{ optional($login)->email }}" autocomplete="email">
                    </div>
                    <div class="detail-field">
                        <label class="detail-label" for="legacyCustomerPhone">手机号码</label>
                        <input class="layui-input" id="legacyCustomerPhone" name="userphoneNo" type="text" maxlength="30"
                               value="{{ $phoneNumber }}" data-initial="{{ $phoneNumber }}" autocomplete="tel">
                    </div>
                    <div class="detail-field">
                        <label class="detail-label" for="legacyCustomerIdCard">身份证号</label>
                        <input class="layui-input" id="legacyCustomerIdCard" name="userIdcardNo" type="text" maxlength="50"
                               value="{{ optional($auth)->id_card_no }}" data-initial="{{ optional($auth)->id_card_no }}">
                    </div>
                    <div class="detail-field">
                        <span class="detail-label">性别</span>
                        <div data-initial-gender="{{ (int) $customer->gender }}">
                            <input type="radio" name="sex" value="1" title="男" {{ (int) $customer->gender === 1 ? 'checked' : '' }}>
                            <input type="radio" name="sex" value="2" title="女" {{ (int) $customer->gender === 2 ? 'checked' : '' }}>
                        </div>
                    </div>
                    <div class="detail-field">
                        <span class="detail-label">礼品资格</span>
                        <div data-initial-gift="{{ (int) $customer->is_gift_allowed }}">
                            <input type="radio" name="gift_allowed" value="1" title="允许" {{ (int) $customer->is_gift_allowed === 1 ? 'checked' : '' }}>
                            <input type="radio" name="gift_allowed" value="0" title="不允许" {{ (int) $customer->is_gift_allowed === 0 ? 'checked' : '' }}>
                        </div>
                    </div>
                    <div class="detail-field">
                        <label class="detail-label" for="legacyCustomerCreatedAt">开户时间</label>
                        <input class="layui-input detail-readonly" id="legacyCustomerCreatedAt" type="text" value="{{ $createdText }}" readonly>
                    </div>
                </div>
            </div>

            <div class="detail-section">
                <h3 class="detail-section-title">交易与层级</h3>
                <div class="detail-grid">
                    <div class="detail-field">
                        <label class="detail-label" for="legacyCustomerGroup">客户组</label>
                        <select id="legacyCustomerGroup" name="usergrpId" lay-filter="legacyCustomerDetailGroup"
                                data-initial-name="{{ $currentGroupName }}">
                            @if($currentGroupName !== '' && !in_array($currentGroupName, $knownGroupNames, true))
                                <option value="{{ (int) $customer->group_id }}" data-group-name="{{ $currentGroupName }}" data-is-ecn="{{ (int) $customer->is_ecn }}" selected>
                                    {{ $currentGroupName }}（当前组）
                                </option>
                            @endif
                            @foreach($customerGroups as $group)
                                <option value="{{ $group->id }}" data-group-name="{{ $group->name }}" data-is-ecn="{{ (int) $group->is_ecn }}"
                                        {{ $currentGroupName === (string) $group->name ? 'selected' : '' }}>
                                    {{ $group->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="detail-field">
                        <label class="detail-label" for="legacyCustomerLeverage">交易杠杆</label>
                        <input class="layui-input detail-readonly" id="legacyCustomerLeverage" type="text" value="{{ (int) $customer->leverage }}" readonly>
                    </div>
                    <div class="detail-field">
                        <label class="detail-label" for="legacyCustomerParent">上级代理 ID</label>
                        <input class="layui-input" id="legacyCustomerParent" name="userparentId" type="text" inputmode="numeric"
                               value="{{ (int) $customer->parent_id }}" data-initial="{{ (int) $customer->parent_id }}">
                    </div>
                    <div class="detail-field detail-field-full">
                        <label class="detail-label" for="legacyCustomerHierarchy">层级关系</label>
                        <input class="layui-input detail-readonly" id="legacyCustomerHierarchy" name="usercountry" type="text"
                               value="{{ $customer->family_tree }}" readonly>
                    </div>
                    <div class="detail-field detail-field-full">
                        <span class="detail-label">交易与资金限制</span>
                        <div class="detail-switches">
                            <input type="checkbox" name="enablereadonly" value="1" title="MT4 只读"
                                   data-initial="{{ (int) $customer->is_mt4_readonly }}" {{ (int) $customer->is_mt4_readonly === 1 ? 'checked' : '' }}>
                            <input type="checkbox" name="isoutmoney" value="1" title="禁止出金"
                                   data-initial="{{ (int) $customer->is_withdrawal_allowed }}" {{ (int) $customer->is_withdrawal_allowed === 1 ? 'checked' : '' }}>
                            <input type="checkbox" name="isallowmoney" value="1" title="禁止入金"
                                   data-initial="{{ (int) $customer->is_deposit_allowed }}" {{ (int) $customer->is_deposit_allowed === 1 ? 'checked' : '' }}>
                        </div>
                    </div>
                </div>
            </div>

            <div class="detail-section">
                <h3 class="detail-section-title">认证与银行卡</h3>
                <div class="detail-grid">
                    <div class="detail-field">
                        <label class="detail-label" for="legacyCustomerBankNo">银行卡号</label>
                        <input class="layui-input {{ $bankStatus === 2 ? '' : 'detail-readonly' }}" id="legacyCustomerBankNo" name="bank_no" type="text" maxlength="50"
                               value="{{ optional($auth)->bank_no }}" data-initial="{{ optional($auth)->bank_no }}" {{ $bankStatus === 2 ? '' : 'readonly' }}>
                    </div>
                    <div class="detail-field detail-field-wide">
                        <label class="detail-label" for="legacyCustomerBankName">开户银行</label>
                        <input class="layui-input {{ $bankStatus === 2 ? '' : 'detail-readonly' }}" id="legacyCustomerBankName" name="bank_class" type="text" maxlength="255"
                               value="{{ optional($auth)->bank_name }}" data-initial="{{ optional($auth)->bank_name }}" {{ $bankStatus === 2 ? '' : 'readonly' }}>
                    </div>
                    <div class="detail-field detail-field-full">
                        <label class="detail-label" for="legacyCustomerBankAddr">开户支行</label>
                        <input class="layui-input {{ $bankStatus === 2 ? '' : 'detail-readonly' }}" id="legacyCustomerBankAddr" name="bank_info" type="text" maxlength="500"
                               value="{{ optional($auth)->bank_addr }}" data-initial="{{ optional($auth)->bank_addr }}" {{ $bankStatus === 2 ? '' : 'readonly' }}>
                        @if($bankStatus !== 2)
                            <p class="detail-help">银行卡仅在审核通过后允许维护。</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="detail-section">
                <h3 class="detail-section-title">备注</h3>
                <div class="detail-grid">
                    <div class="detail-field detail-field-full">
                        <label class="detail-label" for="legacyCustomerRemark">客户备注</label>
                        <textarea class="layui-textarea" id="legacyCustomerRemark" name="userremark" maxlength="500"
                                  data-initial="{{ $customer->remark }}" placeholder="请输入客户备注">{{ $customer->remark }}</textarea>
                    </div>
                </div>
            </div>

            <div class="detail-actions">
                <button type="submit" class="layui-btn detail-save" lay-submit lay-filter="legacyCustomerDetailSubmit">保存修改</button>
                <button type="button" class="layui-btn layui-btn-primary" id="legacyCustomerDetailBack">返回</button>
            </div>
        </form>
    </section>
</div>
@endsection

@section('scripts')
<script src="{{ asset('/js/vendor/echarts/echarts.common.min.js') }}"></script>
<script src="{{ asset('/js/apps/admin/layui/users/customer-detail.js') }}"></script>
@endsection
