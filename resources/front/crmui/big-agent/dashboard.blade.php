{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/18
Time: 16:48
--}}
@extends('front_crmui::big-agent.layout')

@section('content')
<section class="crmui-page" data-crmui-page="{{ $page['key'] }}">
    <header class="crmui-page-head"><div><p class="crmui-page-scope">{{ __('crmui.common.big_agent_console') }}</p><h1>{{ $page['title'] }}</h1><span>{{ $page['description'] }}</span></div></header>
    <div class="crmui-metrics">
        <a class="crmui-metric" href="{{ route($page['routeNames']['app'], ['path' => 'proxy/list']) }}"><i data-lucide="network"></i><strong>{{ __('crmui.front.pages.big_agent_proxy_list.title') }}</strong></a>
        <a class="crmui-metric" href="{{ route($page['routeNames']['app'], ['path' => 'position/summary']) }}"><i data-lucide="chart-no-axes-combined"></i><strong>{{ __('crmui.front.pages.big_agent_position_summary.title') }}</strong></a>
        <a class="crmui-metric" href="{{ route($page['routeNames']['app'], ['path' => 'orders/open']) }}"><i data-lucide="activity"></i><strong>{{ __('crmui.front.pages.big_agent_open_orders.title') }}</strong></a>
        <a class="crmui-metric" href="{{ route($page['routeNames']['app'], ['path' => 'orders/closed']) }}"><i data-lucide="circle-check"></i><strong>{{ __('crmui.front.pages.big_agent_closed_orders.title') }}</strong></a>
    </div>
</section>
@endsection
