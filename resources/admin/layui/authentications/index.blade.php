{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/07
Time: 19:07
--}}
@extends('admin_layui::layouts.app')

@section('title', __('admin.authentications'))

@section('content')
{{-- 实名认证审核页面：待审列表、已审列表和审核弹窗共用同一 Blade 页面，真实数据由后台 API 按 permissions.api_route 鉴权后返回。 --}}
<div class="crm-admin-workbench">
    <div class="crm-page-head">
        <div>
            <h1>{{ __('admin.authentications') }}</h1>
            <p>{{ __('admin.authentications_desc') }}</p>
        </div>
    </div>

    <div class="layui-card crm-admin-panel">
        <div class="layui-card-header">{{ __('admin.auth_pending') }}</div>
        <div class="layui-card-body">
            <form class="layui-form layui-form-pane" id="authPendingSearchForm">
                <div class="layui-form-item">
                    <div class="layui-inline"><div class="layui-input-inline">
                            {{-- user_id：业务用户 ID，对应 user_auths.user_id，用于定位某一个用户的认证资料。 --}}
                            <input type="text" name="user_id" autocomplete="off" class="layui-input" placeholder="{{ __('admin.user_id') }}">
                        </div>
                    </div>
                    <div class="layui-inline"><div class="layui-input-inline">
                            {{-- user_name：业务用户姓名，来自 user_infos.user_name，支持模糊查询。 --}}
                            <input type="text" name="user_name" autocomplete="off" class="layui-input" placeholder="{{ __('admin.user_name') }}">
                        </div>
                    </div>
                    <div class="layui-inline"><div class="layui-input-inline">
                            {{-- auth_status：保持旧项目筛选语义，1=待审核，2=审核未通过，空值显示两类记录。 --}}
                            <select name="auth_status">
                                <option value="">{{ __('admin.review_status') }}</option>
                                <option value="1">{{ __('admin.auth_reviewing') }}</option>
                                <option value="2">{{ __('admin.auth_rejected') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="layui-inline"><div class="layui-input-inline">
                            <input type="text" name="start_date" id="authPendingStartDate" value="2024-01-01" autocomplete="off" class="layui-input" placeholder="{{ __('admin.start_date') }}">
                        </div>
                    </div>
                    <div class="layui-inline"><div class="layui-input-inline">
                            <input type="text" name="end_date" id="authPendingEndDate" value="{{ date('Y-m-d') }}" autocomplete="off" class="layui-input" placeholder="{{ __('admin.end_date') }}">
                        </div>
                    </div>
                    <div class="layui-inline">
                        <button class="layui-btn" lay-submit lay-filter="searchAuthPending">
                            <i data-lucide="search"></i>{{ __('common.search') }}
                        </button>
                        <button type="reset" class="layui-btn layui-btn-primary">{{ __('common.reset') }}</button>
                    </div>
                </div>
            </form>

            <table class="layui-hide" id="authPendingTable" lay-filter="authPendingTable"></table>
        </div>
    </div>

    <div class="layui-card crm-admin-panel">
        <div class="layui-card-header">{{ __('admin.auth_certified') }}</div>
        <div class="layui-card-body">
            <form class="layui-form layui-form-pane" id="authCertifiedSearchForm">
                <div class="layui-form-item">
                    <div class="layui-inline"><div class="layui-input-inline">
                            {{-- user_id：业务用户 ID，对应 user_auths.user_id，用于查看已审核认证记录。 --}}
                            <input type="text" name="user_id" autocomplete="off" class="layui-input" placeholder="{{ __('admin.user_id') }}">
                        </div>
                    </div>
                    <div class="layui-inline"><div class="layui-input-inline">
                            {{-- user_name：用户姓名，来自 user_infos.user_name。 --}}
                            <input type="text" name="user_name" autocomplete="off" class="layui-input" placeholder="{{ __('admin.user_name') }}">
                        </div>
                    </div>
                    <div class="layui-inline"><div class="layui-input-inline">
                            <input type="text" name="start_date" id="authCertifiedStartDate" value="2024-01-01" autocomplete="off" class="layui-input" placeholder="{{ __('admin.start_date') }}">
                        </div>
                    </div>
                    <div class="layui-inline"><div class="layui-input-inline">
                            <input type="text" name="end_date" id="authCertifiedEndDate" value="{{ date('Y-m-d') }}" autocomplete="off" class="layui-input" placeholder="{{ __('admin.end_date') }}">
                        </div>
                    </div>
                    <div class="layui-inline">
                        <button class="layui-btn layui-btn-primary" lay-submit lay-filter="searchAuthCertified">
                            <i data-lucide="search"></i>{{ __('common.search') }}
                        </button>
                        <button type="reset" class="layui-btn layui-btn-primary">{{ __('common.reset') }}</button>
                    </div>
                </div>
            </form>

            <table class="layui-hide" id="authCertifiedTable" lay-filter="authCertifiedTable"></table>
        </div>
    </div>
</div>

<script type="text/html" id="authPendingToolbar">
    <button class="layui-btn layui-btn-primary layui-btn-xs" lay-event="detail" data-permission="admin_auth_detail">
        <i data-lucide="eye" aria-hidden="true"></i>{{ __('common.detail') }}
    </button>
    {{-- 仅对当前仍有可审组件的记录显示快捷审核，完整资料和独立审核也可在详情页完成。 --}}
    @{{# if (String(d.id_card_status) === '1' || String(d.bank_status) === '1' || String(d.bank_status) === '3') { }}
    <button class="layui-btn layui-btn-xs" lay-event="review" data-permission="admin_user_review_auth">
        <i data-lucide="clipboard-check" aria-hidden="true"></i>{{ __('admin.review_auth') }}
    </button>
    @{{# } }}
</script>

<script type="text/html" id="authCertifiedToolbar">
    <button class="layui-btn layui-btn-primary layui-btn-xs" lay-event="detail" data-permission="admin_auth_detail">
        <i data-lucide="eye" aria-hidden="true"></i>{{ __('common.detail') }}
    </button>
</script>

<div id="authReviewModal" class="admin-dialog-body" style="display: none;">
    {{-- authReviewForm：user_id 由待审表格行写入，每个可审组件分别提交通过或拒绝决定。 --}}
    <form class="layui-form" id="authReviewForm" lay-filter="authReviewForm">
        <input type="hidden" name="user_id">
        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.user_id') }}</label>
            <div class="layui-input-block">
                <input type="text" name="display_user_id" readonly class="layui-input layui-disabled">
            </div>
        </div>

        <div data-auth-review-component="id_card">
            <div class="layui-form-item">
                <label class="layui-form-label">{{ __('admin.id_card_status') }}</label>
                <div class="layui-input-block">
                    <input type="radio" name="id_card_decision" value="1" title="{{ __('admin.review_pass') }}" checked>
                    <input type="radio" name="id_card_decision" value="2" title="{{ __('admin.review_reject') }}">
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">{{ __('admin.reject_reason') }}</label>
                <div class="layui-input-block">
                    <textarea name="id_card_reason" class="layui-textarea" maxlength="500"></textarea>
                </div>
            </div>
        </div>

        <div data-auth-review-component="bank">
            <div class="layui-form-item">
                <label class="layui-form-label">{{ __('admin.bank_status') }}</label>
                <div class="layui-input-block">
                    <input type="radio" name="bank_decision" value="1" title="{{ __('admin.review_pass') }}" checked>
                    <input type="radio" name="bank_decision" value="2" title="{{ __('admin.review_reject') }}">
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">{{ __('admin.reject_reason') }}</label>
                <div class="layui-input-block">
                    <textarea name="bank_reason" class="layui-textarea" maxlength="500"></textarea>
                </div>
            </div>
        </div>

        <div class="layui-form-item">
            <div class="layui-input-block">
                <button class="layui-btn" lay-submit lay-filter="submitAuthReview">{{ __('common.submit') }}</button>
            </div>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<div hidden data-layui-page="authentications/index"></div>
@endsection
