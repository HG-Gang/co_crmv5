@extends('front.layouts.app')

@section('title', __('auth.dashboard'))

@section('content')
<div class="card">
    <h3>{{ __('auth.welcome') }}, {{ $userInfo->user_name ?? $userLogin->email }}</h3>
    <div style="display:flex;gap:16px;flex-wrap:wrap;">
        <div style="padding:8px 16px;background:#f0faf4;border-radius:6px;font-size:13px;">
            <strong>ID:</strong> {{ $userInfo->user_id ?? '-' }}
        </div>
        <div style="padding:8px 16px;background:#f0faf4;border-radius:6px;font-size:13px;">
            <strong>{{ __('auth.register_type') }}:</strong>
            {{ $userInfo && $userInfo->isAgent() ? __('auth.agent') : __('auth.customer') }}
        </div>
        @if($userInfo && $userInfo->parent_id)
        <div style="padding:8px 16px;background:#e3f2fd;border-radius:6px;font-size:13px;">
            <strong>{{ __('messages.parent_agent') }}:</strong> {{ $userInfo->parent_id }}
        </div>
        @endif
    </div>
</div>

@if($userInfo && $userInfo->isAgent() && !empty($stats))
<div class="stats-grid">
    <div class="stat-box">
        <div class="number">{{ $stats['direct_agents'] ?? 0 }}</div>
        <div class="label">{{ __('messages.direct') }} {{ __('messages.agent_count') }}</div>
    </div>
    <div class="stat-box">
        <div class="number">{{ $stats['total_agents'] ?? 0 }}</div>
        <div class="label">{{ __('messages.all') }} {{ __('messages.agent_count') }}</div>
    </div>
    <div class="stat-box">
        <div class="number">{{ $stats['direct_customers'] ?? 0 }}</div>
        <div class="label">{{ __('messages.direct') }} {{ __('messages.customer_count') }}</div>
    </div>
    <div class="stat-box">
        <div class="number">{{ $stats['total_customers'] ?? 0 }}</div>
        <div class="label">{{ __('messages.all') }} {{ __('messages.customer_count') }}</div>
    </div>
</div>
@endif

@if($userInfo && $userInfo->family_tree)
<div class="card">
    <h3>{{ __('messages.family_tree') }}</h3>
    <div style="font-family:monospace;font-size:14px;color:#666;word-break:break-all;">
        @php
            $treeIds = explode(',', $userInfo->family_tree);
        @endphp
        @foreach($treeIds as $idx => $treeId)
            @if($idx > 0) <span style="color:#18a058;">&rarr;</span> @endif
            <span style="{{ (int)$treeId === $userInfo->user_id ? 'color:#18a058;font-weight:bold;' : '' }}">{{ $treeId }}</span>
        @endforeach
    </div>
</div>
@endif
@endsection