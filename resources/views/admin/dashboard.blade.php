@extends('admin.layouts.app')

@section('title', __('auth.dashboard'))

@section('content')
<div class="page-header"><h2>{{ __('auth.dashboard') }}</h2></div>

<div class="stat-card">
    <div class="stat-item">
        <div class="stat-number">{{ $stats['total_agents'] ?? 0 }}</div>
        <div class="stat-label">{{ __('messages.agent_count') }}</div>
    </div>
    <div class="stat-item">
        <div class="stat-number">{{ $stats['total_customers'] ?? 0 }}</div>
        <div class="stat-label">{{ __('messages.customer_count') }}</div>
    </div>
    <div class="stat-item">
        <div class="stat-number">{{ $stats['total_users'] ?? 0 }}</div>
        <div class="stat-label">{{ __('auth.user_management') }}</div>
    </div>
    <div class="stat-item">
        <div class="stat-number">{{ $stats['today_logins'] ?? 0 }}</div>
        <div class="stat-label">{{ __('auth.login_log') }}</div>
    </div>
</div>
@endsection
