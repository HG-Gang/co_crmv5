@extends('admin.layouts.app')
@section('title', '控制台')
@section('breadcrumb', '控制台')

@section('content')
<div class="page-header">
    <div class="page-title">{{ __('common.dashboard') }}</div>
</div>

<div class="stat-cards">
    <div class="stat-card">
        <div class="label">代理商总数</div>
        <div class="value">{{ number_format($stats['total_agents']) }}</div>
    </div>
    <div class="stat-card">
        <div class="label">普通客户总数</div>
        <div class="value">{{ number_format($stats['total_members']) }}</div>
    </div>
    <div class="stat-card">
        <div class="label">今日注册</div>
        <div class="value">{{ $stats['today_register'] }}</div>
    </div>
</div>

<div class="card">
    <h3 style="margin-bottom:16px;font-size:16px;">系统说明</h3>
    <ul style="list-style:none;line-height:2;font-size:14px;color:#888;">
        <li>✅ 代理商用户ID从 <b>1001</b> 起自增（自定义序列）</li>
        <li>✅ 普通客户用户ID从 <b>600001</b> 起自增</li>
        <li>✅ family_tree 记录完整层级链路（逗号分隔）</li>
        <li>✅ agent_node_stats 记录每个代理商的直属/间接下级统计</li>
        <li>✅ 支持中文/English 多语言切换</li>
        <li>✅ 支持亮色/暗色主题切换</li>
        <li>✅ 数据表格支持当前 Layui 后台风格</li>
    </ul>
</div>
@endsection
