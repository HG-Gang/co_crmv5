{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/29
Time: 14:28
--}}
@extends('admin_layui::layouts.app')

@section('title', __('admin.withdraw_imports'))

@section('content')
{{-- 批量出金导入页面：页面只负责维护 withdraw_imports 导入记录，接口层继续做权限和数据范围校验。 --}}
<div class="layui-card crm-admin-panel">
    <div class="layui-card-header">{{ __('admin.withdraw_imports') }}</div>
    <div class="layui-card-body">
        <div class="layui-btn-container">
            <button class="layui-btn layui-btn-primary" id="reloadWithdrawImports">{{ __('common.refresh') }}</button>
            {{-- data-permission 对应 permissions.slug，前端只做体验控制，后端仍按 api_route 二次鉴权。 --}}
            <button class="layui-btn layui-btn-primary" id="downloadWithdrawImportTemplate" data-permission="admin_batch_withdraw_import_template">
                {{ __('admin.download_template') }}
            </button>
            <button class="layui-btn layui-btn-normal" id="exportWithdrawImports" data-permission="admin_batch_withdraw_import_export">
                {{ __('admin.export_imports') }}
            </button>
            <button class="layui-btn" id="addWithdrawImport" data-permission="admin_batch_withdraw_import_create">
                <i data-lucide="plus"></i> {{ __('common.add') }}
            </button>
            {{-- importWithdrawImportFile：打开共享上传组件弹窗，把按模板填写的 CSV 整批提交到 createWithdrawImport。 --}}
            <button class="layui-btn layui-btn-warm" id="importWithdrawImportFile" data-permission="admin_batch_withdraw_import_create">
                <i data-lucide="upload"></i> {{ __('admin.import_csv_file') }}
            </button>
        </div>

        <form class="layui-form layui-form-pane" id="withdrawImportSearchForm">
            <div class="layui-form-item">
                <div class="layui-inline"><div class="layui-input-inline">
                        {{-- user_id：业务用户 ID，对应 withdraw_imports.user_id，用于按用户筛选出金导入记录。 --}}
                        <input type="text" name="user_id" autocomplete="off" class="layui-input" placeholder="{{ __('admin.user_id') }}">
                    </div>
                </div>
                <div class="layui-inline"><div class="layui-input-inline">
                        {{-- batch_no：导入批次号，对应 withdraw_imports.batch_no，用于定位同一次批量导入。 --}}
                        <input type="text" name="batch_no" autocomplete="off" class="layui-input" placeholder="{{ __('admin.batch_no') }}">
                    </div>
                </div>
                <div class="layui-inline"><div class="layui-input-inline">
                        {{-- is_synced：同步状态，0=待处理，1=成功，2=失败。 --}}
                        <select name="is_synced">
                            <option value="">{{ __('admin.sync_status') }}</option>
                            <option value="0">{{ __('admin.import_pending') }}</option>
                            <option value="1">{{ __('admin.import_synced') }}</option>
                            <option value="2">{{ __('admin.import_failed') }}</option>
                        </select>
                    </div>
                </div>
                <div class="layui-inline">
                    <button class="layui-btn" lay-submit lay-filter="searchWithdrawImports">{{ __('common.search') }}</button>
                    <button type="reset" class="layui-btn layui-btn-primary">{{ __('common.reset') }}</button>
                </div>
            </div>
        </form>

        <table class="layui-hide" id="withdrawImportTable" lay-filter="withdrawImportTable"></table>

        <script type="text/html" id="withdrawImportActions">
            {{-- syncWithdrawImport：只允许待处理导入记录发起真实 MT4 出金同步，后端会再次校验数据范围和记录状态。 --}}
            <a class="layui-btn layui-btn-xs" lay-event="syncWithdrawImport" data-permission="admin_batch_withdraw_import_sync">{{ __('admin.sync_import') }}</a>
            {{-- retryWithdrawImport：只允许失败出金导入记录重新进入待处理队列，避免绕过后续真实资金审核。 --}}
            <a class="layui-btn layui-btn-xs layui-btn-warm" lay-event="retryWithdrawImport" data-permission="admin_batch_withdraw_import_retry">{{ __('admin.retry_import') }}</a>
        </script>
    </div>
</div>

<div id="withdrawImportModal" class="admin-dialog-body" style="display: none;">
    {{-- 单条出金导入记录表单：字段直接对应 withdraw_imports 表，后续 Excel 导入会复用同一套字段口径。 --}}
    <form class="layui-form" id="withdrawImportForm" lay-filter="withdrawImportForm">
        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.user_id') }}</label>
            <div class="layui-input-block">
                <input type="number" name="user_id" required lay-verify="required" autocomplete="off" class="layui-input">
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.user_name') }}</label>
            <div class="layui-input-block">
                <input type="text" name="user_name" autocomplete="off" class="layui-input" placeholder="{{ __('admin.optional_auto_fill') }}">
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.amount') }}</label>
            <div class="layui-input-block">
                <input type="number" name="amount" required lay-verify="required" autocomplete="off" class="layui-input" step="0.01" min="0.01">
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.batch_no') }}</label>
            <div class="layui-input-block">
                <input type="text" name="batch_no" required lay-verify="required" autocomplete="off" class="layui-input">
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.mt4_order_id') }}</label>
            <div class="layui-input-block">
                <input type="number" name="mt4_order_id" autocomplete="off" class="layui-input" value="0">
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.remarks') }}</label>
            <div class="layui-input-block">
                <textarea name="remarks" class="layui-textarea"></textarea>
            </div>
        </div>
        <div class="layui-form-item">
            <div class="layui-input-block">
                <button class="layui-btn" lay-submit lay-filter="saveWithdrawImport">{{ __('common.save') }}</button>
            </div>
        </div>
    </form>
</div>

<div id="withdrawImportUploadModal" class="admin-dialog-body" style="display: none;">
    <p class="crm-upload-hint">{{ __('admin.import_csv_hint') }}</p>
    <div class="crm-upload-shell" data-crm-upload="withdraw_import_csv" data-upload-field="withdraw_import_csv" data-upload-accept="file" data-upload-exts="csv" data-upload-max-size="20480" data-upload-auto="0" data-upload-multiple="0">
        <button type="button" class="layui-upload-drag crm-upload-action" data-upload-trigger aria-label="{{ __('admin.import_csv_file') }}">
            <i data-lucide="file-up"></i>
            <span>{{ __('admin.import_csv_file') }}</span>
        </button>
        <button type="button" class="layui-btn layui-btn-primary layui-btn-xs" data-upload-clear="withdraw_import_csv">{{ __('common.reset') }}</button>
        <span class="crm-upload-meta">
            <b data-upload-name="withdraw_import_csv">-</b>
            <em data-upload-size-text="withdraw_import_csv">-</em>
            <em class="crm-upload-status" data-upload-status="withdraw_import_csv">{{ __('front.no_file_selected') }}</em>
        </span>
        <div class="crm-upload-progress" data-upload-progress role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="{{ __('front.upload_uploading') }}"><div class="crm-upload-progress-bar" data-upload-progress-bar></div></div>
    </div>
    <button type="button" class="layui-btn" id="submitWithdrawImportFile">{{ __('admin.import_csv_start') }}</button>
</div>
@endsection

@section('scripts')
<div hidden data-layui-page="withdraw-imports/index"></div>
@endsection
