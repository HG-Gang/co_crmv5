{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/07/26
Time: 11:27
--}}
{{--
    旧前台直属客户详情兼容视图。

    适用路由：user/cust/show_direct_cust_info/{role}/{uid}。
    控制器已完成代理树授权、字段映射和联系方式脱敏；本视图仅渲染已安全的资料字段。
    role=admin 时额外显示旧 Blade 的登录历史表格，并调用同名旧接口读取最近四周登录记录。
--}}
<div class="legacy-customer-detail">
    <style>
        .legacy-customer-detail{font-family:Arial,"Microsoft YaHei",sans-serif;padding:20px;color:#172033;background:#f7f9fc}.legacy-customer-detail h2{margin:0 0 18px;font-size:18px;font-weight:700}.legacy-customer-fields{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.legacy-customer-field{display:flex;flex-direction:column;gap:6px;min-width:0}.legacy-customer-field--wide{grid-column:1 / -1}.legacy-customer-field label{font-size:13px;color:#617087}.legacy-customer-field input{box-sizing:border-box;width:100%;height:38px;border:1px solid #dce3ed;border-radius:4px;padding:0 10px;background:#fff;color:#1f3d68;font-size:14px}.legacy-customer-history{margin-top:24px;border-top:1px solid #dce3ed;padding-top:18px}.legacy-customer-history h3{margin:0 0 12px;font-size:15px}.legacy-customer-history table{width:100%;border-collapse:collapse;background:#fff}.legacy-customer-history th,.legacy-customer-history td{padding:9px;border:1px solid #dce3ed;text-align:left;font-size:13px}.legacy-customer-history th{background:#f0f4f9;color:#4d6078;font-weight:600}.legacy-customer-history-empty{text-align:center;color:#718096}.legacy-customer-pager{display:flex;align-items:center;gap:10px;margin-top:12px}.legacy-customer-pager button{height:32px;border:1px solid #bdc9d8;border-radius:4px;background:#fff;padding:0 12px;color:#314560;cursor:pointer}.legacy-customer-pager button:disabled{cursor:not-allowed;opacity:.45}@media (max-width:760px){.legacy-customer-fields{grid-template-columns:1fr}.legacy-customer-field--wide{grid-column:auto}}
    </style>

    <h2>客户资料</h2>
    <form class="legacy-customer-fields" aria-label="客户资料">
        @foreach ($fields as $field)
            <div class="legacy-customer-field{{ !empty($field['wide']) ? ' legacy-customer-field--wide' : '' }}">
                <label for="{{ $field['name'] }}">{{ $field['label'] }}</label>
                <input
                    id="{{ $field['name'] }}"
                    name="{{ $field['name'] }}"
                    data-legacy-field="{{ $field['name'] }}"
                    type="text"
                    value="{{ $field['value'] }}"
                    readonly
                >
            </div>
        @endforeach
    </form>

    @if ($showLoginHistory)
        <section class="legacy-customer-history"
                 aria-labelledby="login-history-title"
                 data-login-history-root
                 data-login-history-url="{{ $loginHistoryUrl }}"
                 data-csrf-token="{{ csrf_token() }}">
            <h3 id="login-history-title">登录历史记录</h3>
            <table id="login_history">
                <thead>
                    <tr>
                        <th>登录账户</th>
                        <th>IP 归属地</th>
                        <th>登录 IP</th>
                        <th>登录时间</th>
                    </tr>
                </thead>
                <tbody id="login_history_rows">
                    <tr><td class="legacy-customer-history-empty" colspan="4">正在加载</td></tr>
                </tbody>
            </table>
            <div class="legacy-customer-pager" aria-label="登录历史分页">
                <button id="login_history_previous" type="button" disabled>上一页</button>
                <span id="login_history_page">第 1 页</span>
                <button id="login_history_next" type="button" disabled>下一页</button>
            </div>
        </section>

        {{-- 登录历史逻辑集中在外部资源中，避免 Blade 混入可执行脚本并保留旧分页协议。 --}}
        <script src="{{ asset('js/apps/front/legacy/direct-customer-detail.js') }}" defer></script>
    @endif
</div>
