{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/07/04
Time: 19:39
--}}
@extends('admin_layui::layouts.app')

@section('title', __('admin.vouchers'))

@section('content')
{{-- 凭证审核：review_status 为空表示全部状态，审核动作通过带 ID 的 API 路由提交。 --}}
<div class="layui-card crm-admin-panel">
    <div class="layui-card-header">{{ __('admin.vouchers') }}</div>
    <div class="layui-card-body">
        <form class="layui-form layui-form-pane" id="voucherSearchForm">
            <div class="layui-form-item">
                <div class="layui-inline"><div class="layui-input-inline">
                        <select name="review_status">
                            <option value="">{{ __('admin.review_status') }}</option>
                            <option value="0">{{ __('admin.pending') }}</option>
                            <option value="1">{{ __('admin.approved') }}</option>
                            <option value="2">{{ __('admin.rejected') }}</option>
                        </select>
                    </div>
                </div>
                <div class="layui-inline">
                    <button class="layui-btn" lay-submit lay-filter="searchVouchers">{{ __('common.search') }}</button>
                    <button type="reset" class="layui-btn layui-btn-primary">{{ __('common.reset') }}</button>
                </div>
            </div>
        </form>
        <table class="layui-hide" id="voucherTable" lay-filter="voucherTable"></table>
        <script type="text/html" id="voucherActions">
            <a class="layui-btn layui-btn-xs" lay-event="approve" data-permission="admin_voucher_approve">{{ __('admin.approve') }}</a>
            <a class="layui-btn layui-btn-danger layui-btn-xs" lay-event="reject" data-permission="admin_voucher_reject">{{ __('admin.reject') }}</a>
        </script>
    </div>
</div>
@endsection

@section('scripts')
<div hidden data-layui-page="vouchers/index"></div>
@endsection
