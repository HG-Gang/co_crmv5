{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/28
Time: 01:14
--}}
@extends('admin_layui::layouts.app')

@section('title', __('admin.productions'))

@section('content')
{{-- 产品/交易品种页面：只渲染筛选表单、汇总卡片和 Layui 表格，真实数据由 admin_api_productionList 按 permissions.api_route 鉴权后返回。 --}}
<div class="crm-admin-workbench">
    <div class="crm-page-head">
        <div>
            <h1>{{ __('admin.productions') }}</h1>
            <p>{{ __('admin.productions_desc') }}</p>
        </div>
        <button class="layui-btn layui-btn-primary" id="reloadProduction">
            <i data-lucide="refresh-cw"></i>{{ __('common.refresh') }}
        </button>
        <button class="layui-btn layui-btn-normal" id="exportProductions" data-permission="admin_production_export">
            <i data-lucide="file-down"></i>{{ __('common.export') }}
        </button>
        <button class="layui-btn" id="openProductionCreate">
            <i data-lucide="plus"></i>{{ __('common.create') }}
        </button>
    </div>

    {{-- 需求 9：统计独立成块，默认靠左对齐，并与下方表格卡片形成视觉区分。 --}}
    <section class="crm-admin-stats-block" id="productionSummaryCards" aria-labelledby="productionStatsTitle">
        <div class="crm-admin-stats-heading">
            <span class="crm-admin-stats-title" id="productionStatsTitle" data-translate="admin.table_statistics">{{ __('admin.table_statistics') }}</span>
            <span class="crm-admin-stats-desc" data-translate="admin.table_statistics_desc">{{ __('admin.table_statistics_desc') }}</span>
        </div>
        <div class="crm-table-summary">
            <div class="crm-table-summary-item"><span data-translate="admin.total_symbols">{{ __('admin.total_symbols') }}</span><strong data-summary-field="total_symbols">0</strong></div>
            <div class="crm-table-summary-item"><span data-translate="admin.total_net_volume">{{ __('admin.total_net_volume') }}</span><strong data-summary-field="total_net_volume">0.00</strong></div>
            <div class="crm-table-summary-item"><span data-translate="admin.total_float_profit_loss">{{ __('admin.total_float_profit_loss') }}</span><strong data-summary-field="total_float_profit_loss">0.00</strong></div>
        </div>
    </section>

    <div class="layui-card crm-admin-panel">
        <div class="layui-card-header">{{ __('admin.productions') }}</div>
        <div class="layui-card-body">
            <form class="layui-form layui-form-pane" id="productionSearchForm">
                <div class="layui-form-item">
                    <div class="layui-inline"><div class="layui-input-inline">
                            {{-- symbol：交易品种编码，对应 symbol_prices.symbol，后端按 LIKE 模糊筛选。 --}}
                            <input type="text" name="symbol" autocomplete="off" class="layui-input" placeholder="{{ __('admin.symbol') }}">
                        </div>
                    </div>
                    <div class="layui-inline"><div class="layui-input-inline">
                            {{-- group_id：品种分组 ID，对应 symbol_prices.group_id，用于按产品类别筛选。 --}}
                            <input type="number" name="group_id" autocomplete="off" class="layui-input" placeholder="{{ __('admin.group_id') }}">
                        </div>
                    </div>
                    <div class="layui-inline"><div class="layui-input-inline">
                            {{-- status：品种状态，对应 symbol_prices.status；留空表示全部状态。 --}}
                            <select name="status">
                                <option value="">{{ __('admin.status') }}</option>
                                <option value="1">{{ __('admin.enabled') }}</option>
                                <option value="0">{{ __('admin.disabled') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="layui-inline">
                        <button class="layui-btn" lay-submit lay-filter="searchProduction">
                            <i data-lucide="search"></i>{{ __('common.search') }}
                        </button>
                        <button type="reset" class="layui-btn layui-btn-primary">{{ __('common.reset') }}</button>
                    </div>
                </div>
            </form>

            <table class="layui-hide" id="productionTable" lay-filter="productionTable"></table>
        </div>
    </div>
</div>

<script type="text/html" id="productionActions">
    <a class="layui-btn layui-btn-xs" lay-event="edit">{{ __('common.edit') }}</a>
    <a class="layui-btn layui-btn-danger layui-btn-xs" lay-event="delete">{{ __('common.delete') }}</a>
</script>

<div id="productionFormModal" style="display:none;padding:16px 20px 0;">
    <form class="layui-form layui-form-pane" id="productionForm" lay-filter="productionForm">
        <input type="hidden" name="id">
        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.symbol') }}</label>
            <div class="layui-input-block"><input type="text" name="symbol" required lay-verify="required" class="layui-input"></div>
        </div>
        <div class="layui-form-item">
            <div class="layui-inline"><label class="layui-form-label">{{ __('admin.bid') }}</label><div class="layui-input-inline"><input type="number" step="0.00001" name="bid" required lay-verify="required" class="layui-input"></div></div>
            <div class="layui-inline"><label class="layui-form-label">{{ __('admin.ask') }}</label><div class="layui-input-inline"><input type="number" step="0.00001" name="ask" required lay-verify="required" class="layui-input"></div></div>
        </div>
        <div class="layui-form-item">
            <div class="layui-inline"><label class="layui-form-label">{{ __('admin.low') }}</label><div class="layui-input-inline"><input type="number" step="0.00001" name="low" class="layui-input"></div></div>
            <div class="layui-inline"><label class="layui-form-label">{{ __('admin.high') }}</label><div class="layui-input-inline"><input type="number" step="0.00001" name="high" class="layui-input"></div></div>
        </div>
        <div class="layui-form-item">
            <div class="layui-inline"><label class="layui-form-label">{{ __('admin.digits') }}</label><div class="layui-input-inline"><input type="number" name="digits" class="layui-input" value="2"></div></div>
            <div class="layui-inline"><label class="layui-form-label">{{ __('admin.spread') }}</label><div class="layui-input-inline"><input type="number" step="0.00001" name="spread" class="layui-input"></div></div>
        </div>
        <div class="layui-form-item">
            <div class="layui-inline"><label class="layui-form-label">{{ __('admin.group_id') }}</label><div class="layui-input-inline"><input type="number" name="group_id" required lay-verify="required" class="layui-input" value="0"></div></div>
            <div class="layui-inline"><label class="layui-form-label">{{ __('admin.status') }}</label><div class="layui-input-inline"><select name="status"><option value="1">{{ __('admin.enabled') }}</option><option value="0">{{ __('admin.disabled') }}</option></select></div></div>
        </div>
        <div class="layui-form-item">
            <div class="layui-input-block">
                <button class="layui-btn" lay-submit lay-filter="submitProduction">{{ __('common.submit') }}</button>
            </div>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<div hidden data-layui-page="productions/index"></div>
@endsection
