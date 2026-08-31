{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/21
Time: 23:53
--}}
@extends('admin_layui::layouts.app')

@section('title', __('admin.whs_exp_zero'))

@section('content')
<div class="layui-card crm-admin-panel" data-visual-c-reference="admin-whs-exp-zero">
    <div class="layui-card-header">
        <div class="crm-page-title">{{ __('admin.whs_exp_zero') }}</div>
        <div class="crm-page-desc">{{ __('admin.whs_exp_zero_desc') }}</div>
    </div>
    <div class="layui-card-body">
        <div class="layui-btn-container">
            <button type="button" class="layui-btn layui-btn-primary" id="reloadWhsExpZero">
                <span data-lucide="refresh-cw" aria-hidden="true"></span>
                {{ __('common.refresh') }}
            </button>
        </div>

        <div class="layui-tab layui-tab-brief" lay-filter="whsExpZeroTabs">
            <ul class="layui-tab-title" role="tablist" aria-label="{{ __('admin.whs_exp_zero') }}">
                <li class="layui-this" lay-id="zero_candidates" role="tab" aria-selected="true" aria-controls="whsExpZeroCandidatePanel">
                    {{ __('admin.zero_candidates') }}
                </li>
                <li lay-id="zero_records" role="tab" aria-selected="false" aria-controls="whsExpZeroRecordPanel" data-permission="admin_whs_exp_zero_records">
                    {{ __('admin.zero_records') }}
                </li>
            </ul>

            <div class="layui-tab-content">
                <div class="layui-tab-item layui-show" id="whsExpZeroCandidatePanel" role="tabpanel">
                    <form class="layui-form layui-form-pane" id="whsExpZeroCandidateSearchForm">
                        <div class="layui-form-item">
                            <div class="layui-inline">
                                <div class="layui-input-inline">
                                    <label class="crm-sr-only" for="whsCandidateUserId">{{ __('admin.user_id') }}</label>
                                    <input type="number" id="whsCandidateUserId" name="user_id" min="1" step="1" autocomplete="off" class="layui-input" placeholder="{{ __('admin.user_id') }}" aria-label="{{ __('admin.user_id') }}">
                                </div>
                            </div>
                            <div class="layui-inline">
                                <div class="layui-input-inline">
                                    <label class="crm-sr-only" for="whsCandidateUserName">{{ __('admin.user_name') }}</label>
                                    <input type="text" id="whsCandidateUserName" name="user_name" maxlength="100" autocomplete="off" class="layui-input" placeholder="{{ __('admin.user_name') }}" aria-label="{{ __('admin.user_name') }}">
                                </div>
                            </div>
                            <div class="layui-inline crm-form-actions">
                                <button class="layui-btn" lay-submit lay-filter="searchWhsExpZeroCandidates">
                                    <span data-lucide="search" aria-hidden="true"></span>
                                    {{ __('common.search') }}
                                </button>
                                <button type="reset" class="layui-btn layui-btn-primary" id="resetWhsExpZeroCandidates">
                                    <span data-lucide="rotate-ccw" aria-hidden="true"></span>
                                    {{ __('common.reset') }}
                                </button>
                            </div>
                        </div>
                    </form>

                    <table class="layui-hide" id="whsExpZeroTable" lay-filter="whsExpZeroTable"></table>
                </div>

                <div class="layui-tab-item" id="whsExpZeroRecordPanel" role="tabpanel" data-permission="admin_whs_exp_zero_records">
                    <form class="layui-form layui-form-pane" id="whsExpZeroRecordSearchForm">
                        <div class="layui-form-item">
                            <div class="layui-inline">
                                <div class="layui-input-inline">
                                    <label class="crm-sr-only" for="whsRecordUserId">{{ __('admin.user_id') }}</label>
                                    <input type="number" id="whsRecordUserId" name="user_id" min="1" step="1" autocomplete="off" class="layui-input" placeholder="{{ __('admin.user_id') }}" aria-label="{{ __('admin.user_id') }}">
                                </div>
                            </div>
                            <div class="layui-inline">
                                <div class="layui-input-inline">
                                    <label class="crm-sr-only" for="whsRecordUserName">{{ __('admin.user_name') }}</label>
                                    <input type="text" id="whsRecordUserName" name="user_name" maxlength="100" autocomplete="off" class="layui-input" placeholder="{{ __('admin.user_name') }}" aria-label="{{ __('admin.user_name') }}">
                                </div>
                            </div>
                            <div class="layui-inline">
                                <div class="layui-input-inline">
                                    <label class="crm-sr-only" for="whsRecordStatus">{{ __('admin.status') }}</label>
                                    <select id="whsRecordStatus" name="status" aria-label="{{ __('admin.status') }}">
                                        <option value="">{{ __('common.all') }}</option>
                                        <option value="0">{{ __('admin.processing') }}</option>
                                        <option value="1">{{ __('admin.pending') }}</option>
                                        <option value="2">{{ __('admin.completed') }}</option>
                                        <option value="3">{{ __('admin.failed') }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="layui-inline">
                                <div class="layui-input-inline">
                                    <label class="crm-sr-only" for="whsRecordStartDate">{{ __('admin.start_date') }}</label>
                                    <input type="date" id="whsRecordStartDate" name="start_date" autocomplete="off" class="layui-input" placeholder="{{ __('admin.start_date') }}" aria-label="{{ __('admin.start_date') }}">
                                </div>
                            </div>
                            <div class="layui-inline">
                                <div class="layui-input-inline">
                                    <label class="crm-sr-only" for="whsRecordEndDate">{{ __('admin.end_date') }}</label>
                                    <input type="date" id="whsRecordEndDate" name="end_date" autocomplete="off" class="layui-input" placeholder="{{ __('admin.end_date') }}" aria-label="{{ __('admin.end_date') }}">
                                </div>
                            </div>
                            <div class="layui-inline crm-form-actions">
                                <button class="layui-btn" lay-submit lay-filter="searchWhsExpZeroRecords">
                                    <span data-lucide="search" aria-hidden="true"></span>
                                    {{ __('common.search') }}
                                </button>
                                <button type="reset" class="layui-btn layui-btn-primary" id="resetWhsExpZeroRecords">
                                    <span data-lucide="rotate-ccw" aria-hidden="true"></span>
                                    {{ __('common.reset') }}
                                </button>
                            </div>
                        </div>
                    </form>

                    <table class="layui-hide" id="whsExpZeroRecordTable" lay-filter="whsExpZeroRecordTable"></table>
                </div>
            </div>
        </div>

        <script type="text/html" id="whsExpZeroActions">
            <button type="button" class="layui-btn layui-btn-danger layui-btn-xs" lay-event="oneKeyZero" data-permission="admin_whs_exp_zero">
                <span data-lucide="circle-dollar-sign" aria-hidden="true"></span>
                {{ __('admin.one_key_zero') }}
            </button>
        </script>
    </div>
</div>
@endsection

@section('scripts')
<div hidden data-layui-page="whs-exp-zero/index"></div>
@endsection
