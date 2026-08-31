{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/07/19
Time: 21:30
--}}
@extends('front_layui::layouts.app')

@section('title', __('front.deposit'))
@section('breadcrumb', __('breadcrumb.front_deposit'))

@section('styles')
<link rel="stylesheet" href="{{ asset('/css/front/v2.css') }}?v=2026061401">
<style>
    .front-v2-deposit .layui-card { margin-bottom: 0; border: 0; box-shadow: none; }
    .front-v2-deposit .layui-card-header { height: auto; min-height: 48px; line-height: 22px; padding: 14px 16px; border-bottom-color: var(--v2-line-soft); font-weight: 800; }
    .front-v2-deposit .layui-card-body { padding: 16px; }
    .front-v2-deposit .layui-btn { border-radius: 8px; }
    .front-v2-deposit .layui-input,
    .front-v2-deposit .layui-select,
    .front-v2-deposit select { border-color: var(--v2-line); border-radius: 8px; }
    .front-v2-deposit .layui-input:focus { border-color: var(--v2-primary); }
    .front-v2-deposit .layui-form-label { background: var(--v2-surface-soft); border-color: var(--v2-line); color: var(--v2-muted); }
    .front-v2-deposit .deposit-form-area.is-disabled { opacity: .55; pointer-events: none; }
    .front-v2-deposit .deposit-retry { display: none; margin-left: 10px; }
    .front-v2-deposit.is-disabled .deposit-form-area { opacity: .55; pointer-events: none; }
    .front-v2-deposit #depositHistorySummary { justify-content: flex-start; margin: 4px 0 10px; padding-left: 0; }
    .front-v2-deposit #depositHistorySummary .crm-table-summary-item { margin-left: 0; }
    .front-v2-deposit .payment-channel-layui-tabs { margin-top: 12px; }
    .front-v2-deposit .layui-tab-title { border-bottom-color: var(--v2-line-soft); }
    .front-v2-deposit .layui-tab-title li { border-radius: 8px 8px 0 0; }
    .front-v2-deposit .layui-tab-title .layui-this { border-color: var(--v2-line-soft) var(--v2-line-soft) transparent; color: var(--v2-primary); }
    .front-v2-deposit .layui-tab-content { padding: 16px 0; }
</style>
@endsection

@section('content')
<div class="front-v2-page front-v2-page-shell front-v2-deposit deposit-page crm-visual-page crm-deposit-page">
    <div class="front-v2-panel">
        <div class="front-v2-panel-title">
            <h2 data-translate="front.deposit">{{ __('front.deposit') }}</h2>
            <p>{{ app()->getLocale() === 'en' ? 'Submit deposit requests and track payment status.' : '提交入金申请并跟踪支付状态。' }}</p>
        </div>
        <div class="front-v2-panel-body">
            <div class="front-inline-notice layui-hide" id="depositDisabledNotice"></div>

            <form class="layui-form layui-form-pane" id="depositForm" lay-filter="depositForm">
                <div class="deposit-form-area">
                    <input type="hidden" name="idempotency_key" value="{{ $legacyFormIntentNonce ?? '' }}">
                    <input type="hidden" name="channel" id="depositChannel" value="">
                    <input type="hidden" name="pay_channel" id="pay_channel" value="">
                    <input type="hidden" name="passageway" id="passageway" value="">

                    <div class="front-v2-form-grid">
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="front.deposit_account">{{ __('front.deposit_account') }}</label>
                            <div class="layui-input-block">
                                <input type="text" name="userId" id="depositUserId" class="layui-input" readonly>
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="front.deposit_amount">{{ __('front.deposit_amount') }}</label>
                            <div class="layui-input-block">
                                <input type="number" name="deposit_amt_usd" id="deposit_amt_usd" lay-verify="required|number" class="layui-input"
                                       data-translate-placeholder="front.deposit_amount" placeholder="{{ __('front.deposit_amount') }}" autocomplete="off">
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="front.exchange_rate">{{ __('front.exchange_rate') }}</label>
                            <div class="layui-input-block">
                                <input type="text" id="depositExchangeRate" class="layui-input" readonly>
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="front.actual_payment">{{ __('front.actual_payment') }}</label>
                            <div class="layui-input-block">
                                <input type="text" name="deposit_pay_amt_rmb" id="deposit_pay_amt_rmb" class="layui-input" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="layui-form-item">
                        <label class="layui-form-label" data-translate="front.payment_channel">{{ __('front.payment_channel') }}</label>
                        <div class="layui-input-block">
                            <div id="depositChannelList" class="layui-tab layui-tab-brief payment-channel-layui-tabs" lay-filter="depositPaymentChannelTabs"></div>
                        </div>
                    </div>
                </div>

                <div style="margin-top:18px;">
                    <button class="layui-btn layui-bg-blue" id="depositBtn" lay-submit lay-filter="depositSubmit" data-translate="common.submit">{{ __('common.submit') }}</button>
                    <a href="javascript:void(0);" class="layui-btn layui-btn-primary deposit-retry" id="openBlankBtn" target="_blank" rel="noopener" data-translate="front.payment_retry">{{ __('front.payment_retry') }}</a>
                </div>
            </form>
        </div>
    </div>

    <div class="front-v2-panel">
        <div class="front-v2-panel-title">
            <h2 data-translate="front.deposit_history">{{ __('front.deposit_history') }}</h2>
            <p>{{ app()->getLocale() === 'en' ? 'View and filter your deposit records.' : '查看并筛选您的入金记录。' }}</p>
        </div>
        <div class="front-v2-panel-body">
            <form class="layui-form layui-form-pane" id="depositSearchForm" lay-filter="depositSearchForm">
                <div class="front-v2-form-grid">
                    <div class="layui-form-item"><div class="layui-input-block">
                            <select name="status">
                                <option value="" data-translate="common.status">{{ __('common.status') }}</option>
                                <option value="01" data-translate="front.status_unpaid">{{ __('front.status_unpaid') }}</option>
                                <option value="02" data-translate="front.status_completed">{{ __('front.status_completed') }}</option>
                                <option value="03" data-translate="front.status_rejected">{{ __('front.status_rejected') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="layui-form-item"><div class="layui-input-block">
                            <select name="deposit_type">
                                <option value="" data-translate="front.deposit_type">{{ __('front.deposit_type') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="layui-form-item"><div class="layui-input-block">
                            <input type="text" name="date_range" class="layui-input crm-date-range" readonly data-translate-placeholder="common.date_range" placeholder="{{ __('common.date_range') }}">
                        </div>
                    </div>
                    <div class="layui-form-item" style="margin-top:0;">
                        <div class="layui-input-block">
                            <button class="layui-btn layui-btn-sm layui-bg-blue" lay-submit lay-filter="depositSearchSubmit" data-translate="common.search">{{ __('common.search') }}</button>
                            <button type="button" class="layui-btn layui-btn-sm layui-btn-primary" id="depositSearchReset" data-translate="common.reset">{{ __('common.reset') }}</button>
                        </div>
                    </div>
                </div>
            </form>

            <div id="depositHistorySummary" class="crm-table-summary-bar"></div>
            <div class="front-v2-table-wrap">
                <table id="depositHistoryTable" lay-filter="depositHistoryTable"></table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('/js/shared/pay-channel-manager.js') }}?v=2026071901"></script>
<script src="{{ asset('/js/shared/deposit-page-core.js') }}?v=2026071901"></script>
<div hidden data-layui-page="deposit/index"></div>
@endsection
