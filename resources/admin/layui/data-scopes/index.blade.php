{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/07
Time: 23:13
--}}
@extends('admin_layui::layouts.app')

@section('title', __('admin.data_scopes'))

@section('styles')
<style>
    .data-scope-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        gap: 16px;
    }

    .data-scope-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 12px;
    }

    .data-scope-hint {
        color: var(--admin-muted);
        font-size: 12px;
        line-height: 1.6;
    }

    .data-scope-form-note {
        padding: 8px 12px;
        margin-bottom: 16px;
        color: var(--admin-text);
        background: var(--admin-hover);
        border: 1px solid var(--admin-line);
        border-radius: 6px;
        line-height: 1.6;
    }

    @media (max-width: 1180px) {
        .data-scope-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endsection

@section('content')
<div class="data-scope-grid">
    <div class="layui-card crm-admin-panel">
        <div class="layui-card-header">{{ __('admin.role_data_scopes') }}</div>
        <div class="layui-card-body">
            <div class="data-scope-toolbar">
                <div class="data-scope-hint">{{ __('admin.role_data_scope_hint') }}</div>
                <button class="layui-btn layui-btn-sm" id="reloadRoleScopes">
                    <i data-lucide="refresh-cw"></i> {{ __('common.refresh') }}
                </button>
            </div>
            <table class="layui-hide" id="roleScopeTable" lay-filter="roleScopeTable"></table>
            <script type="text/html" id="roleScopeActions">
                <a class="layui-btn layui-btn-xs" lay-event="edit" data-permission="admin_data_scope_role_save">{{ __('common.edit') }}</a>
            </script>
        </div>
    </div>

    <div class="layui-card crm-admin-panel">
        <div class="layui-card-header">{{ __('admin.admin_agent_bindings') }}</div>
        <div class="layui-card-body">
            <div class="data-scope-toolbar">
                <div class="data-scope-hint">{{ __('admin.admin_agent_binding_hint') }}</div>
                <button class="layui-btn layui-btn-sm" id="addAdminAgentBinding" data-permission="admin_data_scope_binding_save">
                    <i data-lucide="plus"></i> {{ __('common.add') }}
                </button>
            </div>
            <table class="layui-hide" id="adminAgentBindingTable" lay-filter="adminAgentBindingTable"></table>
            <script type="text/html" id="adminAgentBindingActions">
                <a class="layui-btn layui-btn-xs" lay-event="edit" data-permission="admin_data_scope_binding_save">{{ __('common.edit') }}</a>
                <a class="layui-btn layui-btn-danger layui-btn-xs" lay-event="delete" data-permission="admin_data_scope_binding_delete">{{ __('common.delete') }}</a>
            </script>
        </div>
    </div>
</div>

<div id="roleScopeModal" class="admin-dialog-body" style="display: none;">
    <form class="layui-form" id="roleScopeForm" lay-filter="roleScopeForm">
        <div class="data-scope-form-note">{{ __('admin.role_data_scope_form_note') }}</div>
        <input type="hidden" name="role_id">

        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.role') }}</label>
            <div class="layui-input-block">
                <input type="text" name="role_name" class="layui-input" disabled>
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.scope_type') }}</label>
            <div class="layui-input-block">
                <select name="scope_type" lay-verify="required">
                    <option value="all">{{ __('admin.scope_all') }}</option>
                    <option value="self">{{ __('admin.scope_self') }}</option>
                    <option value="created">{{ __('admin.scope_created') }}</option>
                    <option value="agent_tree">{{ __('admin.scope_agent_tree') }}</option>
                    <option value="custom_agents">{{ __('admin.scope_custom_agents') }}</option>
                    <option value="custom_users">{{ __('admin.scope_custom_users') }}</option>
                </select>
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.agent_ids') }}</label>
            <div class="layui-input-block">
                <textarea name="agent_ids" class="layui-textarea" placeholder="{{ __('admin.agent_ids_placeholder') }}"></textarea>
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.user_ids') }}</label>
            <div class="layui-input-block">
                <textarea name="user_ids" class="layui-textarea" placeholder="{{ __('admin.user_ids_placeholder') }}"></textarea>
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('common.status') }}</label>
            <div class="layui-input-block">
                <input type="checkbox" name="status" value="1" lay-skin="switch" lay-text="{{ __('common.enabled') }}|{{ __('common.disabled') }}" checked>
            </div>
        </div>

        <div class="layui-form-item">
            <div class="layui-input-block">
                <button class="layui-btn" lay-submit lay-filter="saveRoleScope">{{ __('common.save') }}</button>
            </div>
        </div>
    </form>
</div>

<div id="adminAgentBindingModal" class="admin-dialog-body" style="display: none;">
    <form class="layui-form" id="adminAgentBindingForm" lay-filter="adminAgentBindingForm">
        <div class="data-scope-form-note">{{ __('admin.admin_agent_binding_form_note') }}</div>

        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.admin_id') }}</label>
            <div class="layui-input-block">
                <input type="number" name="admin_id" required lay-verify="required|number" class="layui-input" placeholder="{{ __('admin.admin_id_placeholder') }}">
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.agent_id') }}</label>
            <div class="layui-input-block">
                <input type="number" name="agent_id" required lay-verify="required|number" class="layui-input" placeholder="{{ __('admin.agent_id_placeholder') }}">
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.binding_type') }}</label>
            <div class="layui-input-block">
                <select name="binding_type" lay-verify="required">
                    <option value="primary">{{ __('admin.binding_primary') }}</option>
                    <option value="extra">{{ __('admin.binding_extra') }}</option>
                </select>
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('common.status') }}</label>
            <div class="layui-input-block">
                <input type="checkbox" name="status" value="1" lay-skin="switch" lay-text="{{ __('common.enabled') }}|{{ __('common.disabled') }}" checked>
            </div>
        </div>

        <div class="layui-form-item">
            <div class="layui-input-block">
                <button class="layui-btn" lay-submit lay-filter="saveAdminAgentBinding">{{ __('common.save') }}</button>
            </div>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<div hidden data-layui-page="data-scopes/index"></div>
@endsection
