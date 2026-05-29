@extends('admin.layouts.app')

@section('title', __('messages.view') . ' - ' . $userInfo->user_name)

@section('content')
<div class="page-header">
    <h2>{{ __('messages.view') }}: {{ $userInfo->user_name }} (ID: {{ $userInfo->user_id }})</h2>
</div>

<div class="content-card">
    <h3 style="margin-bottom:15px;">{{ __('auth.profile') }}</h3>
    <table class="layui-table" style="margin:0;">
        <colgroup><col width="200"><col></colgroup>
        <tbody>
            <tr><td>{{ __('messages.id') }}</td><td>{{ $userInfo->user_id }}</td></tr>
            <tr><td>{{ __('auth.user_name') }}</td><td>{{ $userInfo->user_name }}</td></tr>
            <tr><td>{{ __('auth.email') }}</td><td>{{ $userInfo->login->email ?? '' }}</td></tr>
            <tr><td>{{ __('auth.phone') }}</td><td>{{ $userInfo->phone }}</td></tr>
            <tr><td>{{ __('auth.register_type') }}</td><td>{{ $userInfo->account_type === 1 ? __('auth.agent') : __('auth.customer') }}</td></tr>
            <tr><td>{{ __('messages.parent_agent') }}</td><td>{{ $userInfo->parent_id ?: '-' }}</td></tr>
            <tr><td>{{ __('messages.family_tree') }}</td><td><code>{{ $userInfo->family_tree }}</code></td></tr>
            <tr><td>{{ __('messages.level') }}</td><td>{{ $userInfo->level->name ?? '-' }}</td></tr>
            <tr><td>{{ __('messages.group') }}</td><td>{{ $userInfo->groupConfig->name ?? '-' }}</td></tr>
            <tr><td>{{ __('messages.commission_rate') }}</td><td>{{ $userInfo->comm_rate }}%</td></tr>
            <tr><td>{{ __('auth.country') }}</td><td>{{ $userInfo->country }}</td></tr>
            <tr><td>{{ __('messages.created_at') }}</td><td>{{ $userInfo->created_at }}</td></tr>
        </tbody>
    </table>
</div>

@if($userInfo->isAgent())
<div class="content-card">
    <h3 style="margin-bottom:15px;">{{ __('messages.sub_agents') }} & {{ __('messages.my_customers') }}</h3>
    <div class="stat-card">
        <div class="stat-item">
            <div class="stat-number">{{ $stats['direct_agents'] ?? 0 }}</div>
            <div class="stat-label">{{ __('messages.direct') }} {{ __('messages.agent_count') }}</div>
        </div>
        <div class="stat-item">
            <div class="stat-number">{{ $stats['indirect_agents'] ?? 0 }}</div>
            <div class="stat-label">{{ __('messages.indirect') }} {{ __('messages.agent_count') }}</div>
        </div>
        <div class="stat-item">
            <div class="stat-number">{{ $stats['direct_customers'] ?? 0 }}</div>
            <div class="stat-label">{{ __('messages.direct') }} {{ __('messages.customer_count') }}</div>
        </div>
        <div class="stat-item">
            <div class="stat-number">{{ $stats['indirect_customers'] ?? 0 }}</div>
            <div class="stat-label">{{ __('messages.indirect') }} {{ __('messages.customer_count') }}</div>
        </div>
    </div>
</div>
@endif

@if(!empty($ancestors))
<div class="content-card">
    <h3 style="margin-bottom:15px;">{{ __('messages.family_tree') }}</h3>
    <div style="display:flex;align-items:center;flex-wrap:wrap;gap:8px;">
        @foreach($ancestors as $ancestor)
            <a href="{{ route('admin.users.show', $ancestor['user_id']) }}" style="padding:4px 12px;background:#e3f2fd;border-radius:4px;text-decoration:none;color:#1565c0;font-size:13px;">
                {{ $ancestor['user_name'] }} ({{ $ancestor['user_id'] }})
            </a>
            <span style="color:#999;">&rarr;</span>
        @endforeach
        <span style="padding:4px 12px;background:#e8f5e9;border-radius:4px;color:#2e7d32;font-size:13px;font-weight:bold;">
            {{ $userInfo->user_name }} ({{ $userInfo->user_id }})
        </span>
    </div>
</div>
@endif

<div style="margin-top:15px;">
    <a href="{{ route('admin.users.index') }}" class="layui-btn layui-btn-primary">{{ __('auth.back') }}</a>
</div>
@endsection
