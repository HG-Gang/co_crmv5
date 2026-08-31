{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/17
Time: 00:43
--}}
@extends('admin_layui::layouts.app')

@section('title', '新增客户')

@section('styles')
<style>
    .legacy-customer-editor {
        --customer-primary: var(--admin-blue);
        --customer-accent: var(--admin-warning);
        --customer-border: var(--admin-line);
        color: var(--admin-strong);
    }
    .legacy-customer-editor .customer-hero,
    .legacy-customer-editor .customer-form-card {
        border: 1px solid var(--customer-border);
        border-radius: 16px;
        background: var(--admin-panel);
        box-shadow: var(--crm-shadow);
    }
    .legacy-customer-editor .customer-hero {
        display: flex;
        justify-content: space-between;
        gap: 24px;
        margin-bottom: 18px;
        padding: 24px 28px;
        background: linear-gradient(135deg, var(--admin-hover) 0%, var(--admin-panel) 68%, var(--crm-warning-soft) 100%);
    }
    .legacy-customer-editor .customer-kicker {
        margin: 0 0 6px;
        color: var(--customer-primary);
        font-size: 12px;
        font-weight: 700;
        letter-spacing: .12em;
        text-transform: uppercase;
    }
    .legacy-customer-editor .customer-title {
        margin: 0;
        font-size: 24px;
        font-weight: 700;
        line-height: 1.35;
    }
    .legacy-customer-editor .customer-description {
        max-width: 720px;
        margin: 8px 0 0;
        color: var(--admin-muted);
        line-height: 1.7;
    }
    .legacy-customer-editor .customer-hero-badge {
        align-self: flex-start;
        border: 1px solid var(--customer-accent);
        border-radius: 999px;
        padding: 7px 12px;
        color: var(--customer-accent);
        background: var(--crm-warning-soft);
        font-size: 12px;
        font-weight: 700;
        white-space: nowrap;
    }
    .legacy-customer-editor .customer-form-card { padding: 26px 28px; }
    .legacy-customer-editor .customer-section-title {
        margin: 0 0 18px;
        font-size: 16px;
        font-weight: 700;
    }
    .legacy-customer-editor .customer-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px 22px;
    }
    .legacy-customer-editor .customer-field { min-width: 0; }
    .legacy-customer-editor .customer-field-full { grid-column: 1 / -1; }
    .legacy-customer-editor .customer-label {
        display: block;
        margin-bottom: 8px;
        color: var(--admin-text);
        font-size: 13px;
        font-weight: 700;
    }
    .legacy-customer-editor .layui-input,
    .legacy-customer-editor .layui-select-title input { border-radius: 9px; }
    .legacy-customer-editor .customer-help {
        margin-top: 7px;
        color: var(--admin-muted);
        font-size: 12px;
        line-height: 1.5;
    }
    .legacy-customer-editor .customer-actions {
        display: flex;
        gap: 10px;
        margin-top: 24px;
        padding-top: 20px;
        border-top: 1px solid var(--admin-line);
    }
    .legacy-customer-editor .customer-submit {
        border-radius: 9px;
        background: var(--customer-primary);
    }
    .legacy-customer-editor :focus-visible {
        outline: 3px solid var(--crm-focus);
        outline-offset: 2px;
    }
    @media (max-width: 768px) {
        .legacy-customer-editor .customer-hero { flex-direction: column; padding: 20px; }
        .legacy-customer-editor .customer-form-card { padding: 20px; }
        .legacy-customer-editor .customer-grid { grid-template-columns: 1fr; }
        .legacy-customer-editor .customer-field-full { grid-column: auto; }
    }
    @media (prefers-reduced-motion: reduce) {
        .legacy-customer-editor *,
        .legacy-customer-editor *::before,
        .legacy-customer-editor *::after { scroll-behavior: auto !important; transition: none !important; }
    }
</style>
@endsection

@section('content')
<div class="layui-fluid legacy-customer-editor" data-legacy-customer-add>
    <section class="customer-hero" aria-labelledby="legacyCustomerAddTitle">
        <div>
            <p class="customer-kicker">Customer onboarding</p>
            <h2 class="customer-title" id="legacyCustomerAddTitle">新增普通客户</h2>
            <p class="customer-description">创建客户登录账号并绑定直属代理。客户组仅展示当前已启用的普通客户组，提交后继续复用旧版客户开户接口。</p>
        </div>
        <span class="customer-hero-badge">旧入口兼容</span>
    </section>

    <section class="customer-form-card">
        <h3 class="customer-section-title">开户资料</h3>
        <form class="layui-form" id="legacyCustomerAddForm"
              data-create-endpoint="{{ url('/index/admin/cust/cust_save_add') }}">
            <input type="hidden" name="usergrpName" id="legacyCustomerGroupName" value="">
            <div class="customer-grid">
                <div class="customer-field">
                    <label class="customer-label" for="legacyCustomerUsername">客户姓名</label>
                    <input class="layui-input" id="legacyCustomerUsername" name="username" type="text"
                           maxlength="200" autocomplete="name" required placeholder="请输入客户姓名">
                </div>
                <div class="customer-field">
                    <label class="customer-label" for="legacyCustomerEmail">登录邮箱</label>
                    <input class="layui-input" id="legacyCustomerEmail" name="useremail" type="email"
                           maxlength="191" autocomplete="email" required placeholder="name@example.com">
                </div>
                <div class="customer-field">
                    <label class="customer-label" for="legacyCustomerPhone">手机号码</label>
                    <input class="layui-input" id="legacyCustomerPhone" name="userphoneNo" type="text"
                           maxlength="30" inputmode="tel" autocomplete="tel" required placeholder="请输入手机号码">
                    <input type="hidden" name="modules" value="86">
                </div>
                <div class="customer-field">
                    <label class="customer-label" for="legacyCustomerInviter">直属代理 ID</label>
                    <input class="layui-input" id="legacyCustomerInviter" name="userInviterId" type="text"
                           inputmode="numeric" pattern="[1-9][0-9]*" required placeholder="请输入有效代理 ID">
                    <p class="customer-help">开户前会由服务端再次校验代理身份和当前管理员的数据范围。</p>
                </div>
                <div class="customer-field">
                    <label class="customer-label" for="legacyCustomerIdCard">身份证号</label>
                    <input class="layui-input" id="legacyCustomerIdCard" name="userIdcardNo" type="text"
                           maxlength="50" autocomplete="off" required placeholder="请输入身份证号">
                </div>
                <div class="customer-field">
                    <label class="customer-label" for="legacyCustomerGroup">客户组</label>
                    <select id="legacyCustomerGroup" name="usergrpId" lay-filter="legacyCustomerGroup" required>
                        <option value="">请选择已启用客户组</option>
                        @foreach($customerGroups as $group)
                            <option value="{{ $group->id }}" data-group-name="{{ $group->name }}" data-is-ecn="{{ (int) $group->is_ecn }}">
                                {{ $group->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="customer-field">
                    <span class="customer-label">性别</span>
                    <div>
                        <input type="radio" name="sex" value="1" title="男" checked>
                        <input type="radio" name="sex" value="2" title="女">
                    </div>
                </div>
                <div class="customer-field">
                    <label class="customer-label" for="legacyCustomerPassword">登录密码</label>
                    <input class="layui-input" id="legacyCustomerPassword" name="password" type="password"
                           minlength="6" maxlength="100" autocomplete="new-password" required placeholder="至少 6 位">
                </div>
                <div class="customer-field">
                    <label class="customer-label" for="legacyCustomerPasswordConfirm">确认密码</label>
                    <input class="layui-input" id="legacyCustomerPasswordConfirm" name="againpassword" type="password"
                           minlength="6" maxlength="100" autocomplete="new-password" required placeholder="请再次输入密码">
                </div>
            </div>

            <div class="customer-actions">
                <button type="submit" class="layui-btn customer-submit" lay-submit lay-filter="legacyCustomerAddSubmit">创建客户</button>
                <button type="reset" class="layui-btn layui-btn-primary" id="legacyCustomerAddReset">重置</button>
            </div>
        </form>
    </section>
</div>
@endsection

@section('scripts')
<script src="{{ asset('/js/apps/admin/layui/users/customer-add.js') }}"></script>
@endsection
