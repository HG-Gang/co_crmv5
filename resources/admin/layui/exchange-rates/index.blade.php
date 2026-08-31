{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/07/28
Time: 23:44
--}}
@extends('admin_layui::layouts.app')

@section('title', __('admin.exchange_rates'))

@section('content')
{{-- 汇率配置页面：只维护入金汇率与出金汇率两个 key，真实保存由 admin_api_updateExchangeRate 写入 system_configs 表。 --}}
<div class="crm-admin-workbench">
    <div class="crm-page-head">
        <div>
            <h1>{{ __('admin.exchange_rates') }}</h1>
            <p>{{ __('admin.exchange_rates_desc') }}</p>
        </div>
        <button class="layui-btn layui-btn-primary" id="reloadExchangeRate">
            <i data-lucide="refresh-cw"></i>{{ __('common.refresh') }}
        </button>
    </div>

    <div class="layui-card crm-admin-panel">
        <div class="layui-card-header">{{ __('admin.exchange_rate') }}</div>
        <div class="layui-card-body">
            {{-- exchangeRateForm 的字段名必须与 system_configs.key 保持一致，后端按字段名定位配置项。 --}}
            <form class="layui-form" id="exchangeRateForm" lay-filter="exchangeRateForm">
                <div class="layui-row layui-col-space16">
                    <div class="layui-col-md6">
                        <div class="layui-form-item">
                            <label class="layui-form-label">{{ __('admin.sys_deposit_rate') }}</label>
                            <div class="layui-input-block">
                                <input
                                    type="number"
                                    name="sys_deposit_rate"
                                    min="0.000001"
                                    step="0.000001"
                                    lay-verify="required|number"
                                    autocomplete="off"
                                    class="layui-input"
                                    placeholder="{{ __('admin.sys_deposit_rate_placeholder') }}"
                                >
                            </div>
                        </div>
                    </div>

                    <div class="layui-col-md6">
                        <div class="layui-form-item">
                            <label class="layui-form-label">{{ __('admin.sys_draw_rate') }}</label>
                            <div class="layui-input-block">
                                <input
                                    type="number"
                                    name="sys_draw_rate"
                                    min="0.000001"
                                    step="0.000001"
                                    lay-verify="required|number"
                                    autocomplete="off"
                                    class="layui-input"
                                    placeholder="{{ __('admin.sys_draw_rate_placeholder') }}"
                                >
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 出金手续费：拆成「是否扣」与「扣多少」两个独立维度。
                     开关关闭时后端把固定费与费率一并按 0 计算，但原配置值仍原样保留在
                     system_configs 中，重新开启即恢复既有标准，运营无需自己记录旧值。 --}}
                <div class="layui-form-item">
                    <label class="layui-form-label">{{ __('admin.withdrawal_fee_enabled') }}</label>
                    <div class="layui-input-block">
                        <input
                            type="checkbox"
                            name="withdrawal_fee_enabled"
                            lay-skin="switch"
                            lay-text="{{ __('admin.fee_charge_on') }}|{{ __('admin.fee_charge_off') }}"
                            lay-filter="withdrawalFeeEnabled"
                        >
                        <div class="layui-word-aux">{{ __('admin.withdrawal_fee_enabled_desc') }}</div>
                    </div>
                </div>

                {{-- 金额输入区：开关关闭时由 JS 置为 disabled，避免管理员误以为填了就会生效。
                     disabled 只是交互提示，真正的「不扣」由后端开关判定，不依赖前端状态。 --}}
                <div class="layui-row layui-col-space16" data-withdrawal-fee-amounts>
                    <div class="layui-col-md6">
                        <div class="layui-form-item">
                            <label class="layui-form-label">{{ __('admin.withdrawal_fixed_fee_usd') }}</label>
                            <div class="layui-input-block">
                                <input
                                    type="number"
                                    name="withdrawal_fixed_fee_usd"
                                    min="0"
                                    step="0.01"
                                    lay-verify="number"
                                    autocomplete="off"
                                    class="layui-input"
                                    placeholder="{{ __('admin.withdrawal_fixed_fee_usd_placeholder') }}"
                                >
                                <div class="layui-word-aux">{{ __('admin.withdrawal_fixed_fee_usd_desc') }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="layui-col-md6">
                        <div class="layui-form-item">
                            <label class="layui-form-label">{{ __('admin.withdrawal_fee_rate') }}</label>
                            <div class="layui-input-block">
                                <input
                                    type="number"
                                    name="withdrawal_fee_rate"
                                    min="0"
                                    max="100"
                                    step="0.0001"
                                    lay-verify="number"
                                    autocomplete="off"
                                    class="layui-input"
                                    placeholder="{{ __('admin.withdrawal_fee_rate_placeholder') }}"
                                >
                                <div class="layui-word-aux">{{ __('admin.withdrawal_fee_rate_desc') }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="layui-form-item">
                    <div class="layui-input-block">
                        <button class="layui-btn" lay-submit lay-filter="saveExchangeRate" data-permission="admin_exchange_rate_update">
                            <i data-lucide="check"></i>{{ __('common.save') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<div hidden data-layui-page="exchange-rates/index"></div>
@endsection
