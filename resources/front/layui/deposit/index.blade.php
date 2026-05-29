@extends('front_layui::layouts.app')

@section('title', __('front.deposit'))
@section('breadcrumb', __('breadcrumb.front_deposit'))

@section('styles')
<style>
    .deposit-channel-card { border: 1px solid #e6e6e6; border-radius: 6px; padding: 9px 10px; min-height: 86px; cursor: pointer; background: #fff; }
    .deposit-channel-card.is-active { border-color: #16baaa; box-shadow: 0 0 0 2px rgba(22, 186, 170, .12); }
    .deposit-page .layui-card-header { font-weight: 600; }
    .deposit-page .deposit-form-row .layui-input { max-width: 240px; }
    .deposit-page .deposit-channel-card .layui-form-item { margin-bottom: 4px; }
    .deposit-page .deposit-channel-card .layui-form-radio { margin: 0; padding-right: 0; }
    .deposit-page .deposit-channel-card .channel-meta { margin-top: 3px; font-size: 12px; line-height: 18px; color: #6b7280; }
    .deposit-page .deposit-channel-card .channel-rate { display: inline-block; padding: 1px 6px; border-radius: 999px; color: #0f766e; background: #e6fffb; border: 1px solid #99f6e4; }
    .deposit-page .deposit-retry { display: none; margin-left: 10px; }
    .deposit-page.is-disabled .deposit-form-area { opacity: .55; pointer-events: none; }
</style>
@endsection

@section('content')
<div class="deposit-page">
    <div class="layui-card">
        <div class="layui-card-header" data-translate="front.deposit">{{ __('front.deposit') }}</div>
        <div class="layui-card-body">
            <div class="front-inline-notice layui-hide" id="depositDisabledNotice"></div>

            <form class="layui-form layui-form-pane" id="depositForm" lay-filter="depositForm">
                <div class="deposit-form-area">
                <input type="hidden" name="channel" id="depositChannel" value="">
                <input type="hidden" name="pay_channel" id="pay_channel" value="">
                <input type="hidden" name="passageway" id="passageway" value="">

                <div class="layui-row layui-col-space12 deposit-form-row">
                    <div class="layui-col-md4 layui-col-sm12">
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="front.deposit_account">{{ __('front.deposit_account') }}</label>
                            <div class="layui-input-block">
                                <input type="text" name="userId" id="depositUserId" class="layui-input" readonly>
                            </div>
                        </div>
                    </div>
                    <div class="layui-col-md4 layui-col-sm12">
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="front.deposit_amount">{{ __('front.deposit_amount') }}</label>
                            <div class="layui-input-block">
                                <input type="number" name="deposit_amt_usd" id="deposit_amt_usd" lay-verify="required|number" class="layui-input"
                                       data-translate-placeholder="front.deposit_amount" placeholder="{{ __('front.deposit_amount') }}" autocomplete="off">
                            </div>
                        </div>
                    </div>
                    <div class="layui-col-md4 layui-col-sm12">
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="front.exchange_rate">{{ __('front.exchange_rate') }}</label>
                            <div class="layui-input-block">
                                <input type="text" id="depositExchangeRate" class="layui-input" readonly>
                            </div>
                        </div>
                    </div>
                    <div class="layui-col-md4 layui-col-sm12">
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="front.actual_payment">{{ __('front.actual_payment') }}</label>
                            <div class="layui-input-block">
                                <input type="text" name="deposit_pay_amt_rmb" id="deposit_pay_amt_rmb" class="layui-input" readonly>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Req 8: Payment channels displayed using tabs --}}
                <div class="layui-form-item">
                    <label class="layui-form-label" data-translate="front.payment_channel">{{ __('front.payment_channel') }}</label>
                    <div class="layui-input-block">
                        <div class="layui-tab layui-tab-brief" lay-filter="depositChannelTabs" id="depositChannelTabs">
                            <ul class="layui-tab-title" id="depositChannelTabTitles"></ul>
                            <div class="layui-tab-content" id="depositChannelTabContent"></div>
                        </div>
                        <div class="layui-row layui-col-space12 layui-hide" id="depositChannelList"></div>
                    </div>
                </div>
                </div>

                <button class="layui-btn layui-bg-blue" id="depositBtn" lay-submit lay-filter="depositSubmit" data-translate="common.submit">{{ __('common.submit') }}</button>
                <a href="javascript:void(0);" class="layui-btn layui-btn-primary deposit-retry" id="openBlankBtn" target="_blank" rel="noopener" data-translate="front.payment_retry">{{ __('front.payment_retry') }}</a>
            </form>
        </div>
    </div>

    <div class="layui-card">
        <div class="layui-card-header" data-translate="front.deposit_history">{{ __('front.deposit_history') }}</div>
        <div class="layui-card-body">
            <form class="layui-form layui-form-pane" id="depositSearchForm" lay-filter="depositSearchForm">
                <div class="layui-row layui-col-space10">
                    <div class="layui-col-md3 layui-col-sm6">
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="common.status">{{ __('common.status') }}</label>
                            <div class="layui-input-block">
                                <select name="status">
                                    <option value="" data-translate="common.all">{{ __('common.all') }}</option>
                                    <option value="01" data-translate="front.status_unpaid">{{ __('front.status_unpaid') }}</option>
                                    <option value="02" data-translate="front.status_completed">{{ __('front.status_completed') }}</option>
                                    <option value="03" data-translate="front.status_rejected">{{ __('front.status_rejected') }}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="layui-col-md3 layui-col-sm6">
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="front.date_from">{{ __('front.date_from') }}</label>
                            <div class="layui-input-block">
                                <input type="text" name="startdate" class="layui-input J_layDate" autocomplete="off">
                            </div>
                        </div>
                    </div>
                    <div class="layui-col-md3 layui-col-sm6">
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="front.date_to">{{ __('front.date_to') }}</label>
                            <div class="layui-input-block">
                                <input type="text" name="enddate" class="layui-input J_layDate" autocomplete="off">
                            </div>
                        </div>
                    </div>
                    <div class="layui-col-md3 layui-col-sm6">
                        <button class="layui-btn" lay-submit lay-filter="depositSearch" data-translate="common.search">{{ __('common.search') }}</button>
                        <button type="button" class="layui-btn layui-btn-primary" id="depositSearchReset" data-translate="common.reset">{{ __('common.reset') }}</button>
                    </div>
                </div>
            </form>

            <div class="crm-table-summary" id="depositHistorySummary"></div>
            <table id="depositHistoryTable" lay-filter="depositHistoryTable"></table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('/js/common/pay-channel-manager.js') }}"></script>
<script src="{{ asset('/js/common/deposit-page-core.js') }}"></script>
<script src="{{ asset('/js/front/layui/deposit/index.js') }}"></script>
@endsection
