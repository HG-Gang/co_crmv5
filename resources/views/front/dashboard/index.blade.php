@extends('front.layouts.app')
@section('title', '控制台')
@section('breadcrumb', '控制台')

@section('content')
<div class="page-title">{{ __('common.dashboard') }}</div>

<!-- 用户信息卡 -->
<div class="card">
    <div class="user-info-card">
        <div class="big-avatar">
            @if(!empty(optional($userInfo)->avatar))
                <img src="{{ asset('storage/' . $userInfo->avatar) }}" alt="avatar">
            @else
                {{ strtoupper(substr(optional($userInfo)->user_name ?? 'U', 0, 1)) }}
            @endif
        </div>
        <div class="user-meta">
            <h2>{{ optional($userInfo)->user_name ?? __('common.no_data') }}</h2>
            <p>{{ $loginUser->email }}</p>
            <div style="margin-top:8px;display:flex;gap:8px;align-items:center;">
                @if(optional($userInfo)->account_type == 1)
                    <span class="badge badge-agent">{{ __('user.agent') }}</span>
                    <span style="font-size:13px;color:#888;">ID: <b>{{ $userInfo->user_id }}</b></span>
                @else
                    <span class="badge badge-member">{{ __('user.member') }}</span>
                    <span style="font-size:13px;color:#888;">ID: <b>{{ optional($userInfo)->user_id }}</b></span>
                @endif
            </div>
        </div>
    </div>

    @if(optional($userInfo)->account_type == 1)
    <!-- 代理商关系链 -->
    <div style="border-top:1px solid var(--border);padding-top:16px;margin-top:8px;">
        <div style="font-size:13px;color:#888;margin-bottom:8px;">{{ __('user.relation_chain') }}：</div>
        <div style="font-family:monospace;font-size:13px;background:var(--body-bg,#f5f7fa);padding:8px 12px;border-radius:6px;">
            {{ str_replace(',', ' → ', $userInfo->family_tree) ?: '顶级代理' }}
        </div>
    </div>
    @endif
</div>

@if(optional($userInfo)->account_type == 1 && $nodeStats)
<!-- 代理商统计 -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:20px;">
    <div class="card" style="padding:16px;">
        <div style="font-size:13px;color:#888;margin-bottom:6px;">直属代理商</div>
        <div style="font-size:28px;font-weight:700;color:var(--primary);">{{ $nodeStats->direct_agent_count }}</div>
    </div>
    <div class="card" style="padding:16px;">
        <div style="font-size:13px;color:#888;margin-bottom:6px;">间接代理商</div>
        <div style="font-size:28px;font-weight:700;color:var(--primary);">{{ $nodeStats->indirect_agent_count }}</div>
    </div>
    <div class="card" style="padding:16px;">
        <div style="font-size:13px;color:#888;margin-bottom:6px;">直属客户</div>
        <div style="font-size:28px;font-weight:700;color:var(--primary);">{{ $nodeStats->direct_member_count }}</div>
    </div>
    <div class="card" style="padding:16px;">
        <div style="font-size:13px;color:#888;margin-bottom:6px;">间接客户</div>
        <div style="font-size:28px;font-weight:700;color:var(--primary);">{{ $nodeStats->indirect_member_count }}</div>
    </div>
</div>
@endif

<!-- 快捷操作 -->
<div class="card">
    <div class="card-title">快捷操作</div>
    <div style="display:flex;gap:12px;flex-wrap:wrap;">
        <a href="{{ route('user.profile') }}" class="btn-primary" style="text-decoration:none;padding:10px 20px;border-radius:8px;">编辑资料</a>
        <a href="{{ route('user.change.password') }}" style="padding:10px 20px;border:1px solid var(--border);border-radius:8px;text-decoration:none;color:var(--text);font-size:14px;">修改密码</a>
    </div>
</div>
@endsection
