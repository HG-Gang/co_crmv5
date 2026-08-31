{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/07/23
Time: 04:57
--}}
@extends('front_layui::legacy-big-agent.layout')

@section('title', __('front.dashboard'))

@section('content')
<div class="layui-card">
    <div class="layui-card-header">{{ __('common.welcome') }} {{ $legacyBigAgent['username'] ?? $legacyBigAgent['email'] ?? '' }}</div>
    <div class="layui-card-body">
        <p class="legacy-big-agent-muted">{{ __('front.big_number_login') }}</p>
        <div class="layui-row layui-col-space12" style="margin-top: 12px;">
            <div class="layui-col-md3 layui-col-sm6"><a class="layui-btn layui-btn-fluid" href="{{ route('legacy_user_agents_proxy_list') }}">{{ __('front.sub_agents') }}</a></div>
            <div class="layui-col-md3 layui-col-sm6"><a class="layui-btn layui-btn-fluid layui-bg-green" href="{{ route('legacy_user_agents_position_summary') }}">{{ __('front.position_summary') }}</a></div>
            <div class="layui-col-md3 layui-col-sm6"><a class="layui-btn layui-btn-fluid layui-bg-blue" href="{{ route('legacy_user_agents_open_order') }}">{{ __('front.open_orders') }}</a></div>
            <div class="layui-col-md3 layui-col-sm6"><a class="layui-btn layui-btn-fluid layui-bg-orange" href="{{ route('legacy_user_agents_close_order') }}">{{ __('front.closed_orders') }}</a></div>
        </div>
    </div>
</div>
@endsection
