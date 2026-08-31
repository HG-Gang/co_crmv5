{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/07
Time: 23:13
--}}
@extends('front_layui::layouts.app')

@section('title', __('front.withdraw'))
@section('breadcrumb', __('breadcrumb.front_withdraw'))

@section('styles')
<style>
    .withdraw-page .layui-card-header { font-weight: 600; }
    .withdraw-page .withdraw-form-area .layui-input { max-width: 260px; }
    .withdraw-page .withdraw-notice { margin-top: 8px; color: var(--front-muted); line-height: 1.8; }
    .withdraw-page .withdraw-notice-title { color: var(--front-danger); font-weight: 600; }
    .withdraw-page .withdraw-readonly { background: var(--front-hover); color: var(--front-blue); }
    .withdraw-page.is-disabled .withdraw-form-area { opacity: .55; pointer-events: none; }
    .withdraw-page .withdraw-table-wrap { width: 100%; overflow-x: auto; }
    .withdraw-page .withdraw-table-wrap .layui-table-view { min-width: 1100px; }
    .withdraw-page .crm-table-summary { justify-content: flex-start; }
</style>
@endsection

@section('content')
<div class="withdraw-page">
    <div class="layui-card">
        <div class="layui-card-header" data-translate="front.withdraw">{{ __('front.withdraw') }}</div>
        <div class="layui-card-body">
            <div class="front-inline-notice layui-hide" id="withdrawDisabledNotice"></div>

            <form class="layui-form layui-form-pane" id="withdrawForm" lay-filter="withdrawForm">
                <div class="withdraw-form-area">
                    <input type="hidden" name="idempotency_key" value="{{ $legacyFormIntentNonce ?? '' }}">
                    <div class="layui-row layui-col-space12">
                        <div class="layui-col-md4 layui-col-sm12">
                            <div class="layui-form-item">
                                <label class="layui-form-label" data-translate="front.user_id">{{ __('front.user_id') }}</label>
                                <div class="layui-input-block">
                                    <input type="text" id="withdrawUserId" name="userId" class="layui-input withdraw-readonly" readonly>
                                </div>
                            </div>
                        </div>
                        <div class="layui-col-md4 layui-col-sm12">
                            <div class="layui-form-item">
                                <label class="layui-form-label" data-translate="front.account_balance">{{ __('front.account_balance') }}</label>
                                <div class="layui-input-block">
                                    <input type="text" id="withdrawBalance" class="layui-input withdraw-readonly" readonly>
                                </div>
                            </div>
                        </div>
                        <div class="layui-col-md4 layui-col-sm12">
                            <div class="layui-form-item">
                                <label class="layui-form-label" data-translate="front.available_amount">{{ __('front.available_amount') }}</label>
                                <div class="layui-input-block">
                                    <input type="text" id="withdrawAvailable" class="layui-input withdraw-readonly" readonly>
                                </div>
                            </div>
                        </div>
                        <div class="layui-col-md4 layui-col-sm12">
                            <div class="layui-form-item">
                                <label class="layui-form-label" data-translate="front.exchange_rate">{{ __('front.exchange_rate') }}</label>
                                <div class="layui-input-block">
                                    <input type="text" id="withdrawExchangeRate" class="layui-input withdraw-readonly" readonly>
                                </div>
                            </div>
                        </div>
                        <div class="layui-col-md4 layui-col-sm12">
                            <div class="layui-form-item">
                                <label class="layui-form-label" data-translate="front.fee">{{ __('front.fee') }}</label>
                                <div class="layui-input-block">
                                    <input type="text" id="withdrawFee" class="layui-input withdraw-readonly" readonly>
                                </div>
                            </div>
                        </div>
                        <div class="layui-col-md4 layui-col-sm12">
                            <div class="layui-form-item">
                                <label class="layui-form-label" data-translate="front.actual_amount">{{ __('front.actual_amount') }}</label>
                                <div class="layui-input-block">
                                    <input type="text" id="withdrawActualAmount" class="layui-input withdraw-readonly" readonly>
                                </div>
                            </div>
                        </div>
                        <div class="layui-col-md4 layui-col-sm12">
                            <div class="layui-form-item">
                                <label class="layui-form-label" data-translate="front.withdraw_amount">{{ __('front.withdraw_amount') }}</label>
                                <div class="layui-input-block">
                                    <input type="number" name="amount" id="withdrawAmount" lay-verify="required|number" class="layui-input"
                                           data-translate-placeholder="front.withdraw_amount" placeholder="{{ __('front.withdraw_amount') }}" autocomplete="off">
                                </div>
                            </div>
                        </div>
                        <div class="layui-col-md4 layui-col-sm12">
                            <div class="layui-form-item">
                                <label class="layui-form-label" data-translate="front.withdraw_password">{{ __('front.withdraw_password') }}</label>
                                <div class="layui-input-block">
                                    <input type="password" name="password" id="withdrawPassword" lay-verify="required" class="layui-input"
                                           data-translate-placeholder="front.withdraw_password_placeholder" placeholder="{{ __('front.withdraw_password_placeholder') }}" autocomplete="off">
                                </div>
                            </div>
                        </div>
                        <div class="layui-col-md4 layui-col-sm12">
                            <div class="layui-form-item">
                                <label class="layui-form-label" data-translate="front.bank_name">{{ __('front.bank_name') }}</label>
                                <div class="layui-input-block">
                                    <input type="text" id="withdrawBankName" class="layui-input withdraw-readonly" readonly>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="layui-form-item">
                        <div class="layui-input-block">
                            <input type="checkbox" name="agree" id="withdrawAgree" value="1" lay-skin="primary"
                                   data-translate-title="front.read_and_agree" title="{{ __('front.read_and_agree') }}">
                        </div>
                    </div>

                    <div class="withdraw-notice">
                        <div class="withdraw-notice-title" data-translate="front.withdraw_notice_title">{{ __('front.withdraw_notice_title') }}</div>
                        <p data-translate="front.withdraw_notice_1">{{ __('front.withdraw_notice_1') }}</p>
                        <p data-translate="front.withdraw_notice_2">{{ __('front.withdraw_notice_2') }}</p>
                        <p data-translate="front.withdraw_notice_3">{{ __('front.withdraw_notice_3') }}</p>
                        <p data-translate="front.withdraw_notice_4">{{ __('front.withdraw_notice_4') }}</p>
                    </div>
                </div>

                <button class="layui-btn layui-bg-blue" id="withdrawBtn" lay-submit lay-filter="withdrawSubmit" data-translate="common.submit">{{ __('common.submit') }}</button>
            </form>
        </div>
    </div>

    <div class="layui-card">
        <div class="layui-card-header" data-translate="front.withdraw_history">{{ __('front.withdraw_history') }}</div>
        <div class="layui-card-body">
            <form class="layui-form layui-form-pane" id="withdrawSearchForm" lay-filter="withdrawSearchForm">
                <div class="layui-row layui-col-space10">
                    <div class="layui-col-md3 layui-col-sm6">
                        <div class="layui-form-item"><div class="layui-input-block">
                                <select name="status">
                                    <option value="" data-translate="common.status">{{ __('common.status') }}</option>
                                    <option value="0" data-translate="front.status_pending">{{ __('front.status_pending') }}</option>
                                    <option value="2" data-translate="front.status_approved">{{ __('front.status_approved') }}</option>
                                    <option value="3" data-translate="front.status_rejected">{{ __('front.status_rejected') }}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="layui-col-md3 layui-col-sm6">
                        <div class="layui-form-item"><div class="layui-input-block">
                                <input type="text" name="startdate" class="layui-input J_layDate" autocomplete="off" data-translate-placeholder="front.date_from" placeholder="{{ __('front.date_from') }}">
                            </div>
                        </div>
                    </div>
                    <div class="layui-col-md3 layui-col-sm6">
                        <div class="layui-form-item"><div class="layui-input-block">
                                <input type="text" name="enddate" class="layui-input J_layDate" autocomplete="off" data-translate-placeholder="front.date_to" placeholder="{{ __('front.date_to') }}">
                            </div>
                        </div>
                    </div>
                    <div class="layui-col-md3 layui-col-sm6">
                        <button class="layui-btn" lay-submit lay-filter="withdrawSearch" data-translate="common.search">{{ __('common.search') }}</button>
                        <button type="button" class="layui-btn layui-btn-primary" id="withdrawSearchReset" data-translate="common.reset">{{ __('common.reset') }}</button>
                    </div>
                </div>
            </form>

            <div class="crm-table-summary" id="withdrawHistorySummary"></div>
            <div class="withdraw-table-wrap">
                <table id="withdrawHistoryTable" lay-filter="withdrawHistoryTable"></table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<div hidden data-layui-page="withdraw/index"></div>
@endsection
