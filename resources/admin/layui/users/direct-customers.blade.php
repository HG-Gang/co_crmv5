{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/17
Time: 00:45
--}}
@extends('admin_layui::layouts.app')

@php
    $result = $directCustomerResult ?? ['rows' => [], 'total' => 0, 'summary' => []];
    $rows = $result['rows'] ?? [];
    $summary = $result['summary'] ?? [];
    $parentAgent = $parentAgent ?? null;
@endphp

@section('title', __('admin.users'))

@section('styles')
<style>
    .legacy-direct-customer-page { --legacy-blue: var(--admin-blue); --legacy-blue-soft: var(--admin-hover); --legacy-border: var(--admin-line); color: var(--admin-strong); }
    .legacy-direct-customer-hero { display:flex; justify-content:space-between; gap:24px; align-items:flex-start; margin-bottom:18px; padding:22px 24px; border:1px solid var(--legacy-border); border-radius:14px; background:linear-gradient(135deg,var(--admin-panel) 0%,var(--legacy-blue-soft) 100%); }
    .legacy-direct-customer-hero h2 { margin:0 0 6px; color:var(--admin-blue); font-size:20px; line-height:1.3; }
    .legacy-direct-customer-hero p { margin:0; color:var(--admin-muted); }
    .legacy-direct-customer-hero .metric { min-width:120px; text-align:right; }
    .legacy-direct-customer-hero .metric strong { display:block; color:var(--legacy-blue); font:600 26px/1.1 'Fira Sans', sans-serif; }
    .legacy-direct-customer-hero .metric span { color:var(--admin-muted); font-size:12px; }
    .legacy-direct-customer-summary { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; margin-bottom:18px; }
    .legacy-direct-customer-summary article { padding:14px 16px; border:1px solid var(--admin-line); border-radius:12px; background:var(--admin-panel); box-shadow:var(--crm-shadow); }
    .legacy-direct-customer-summary span { display:block; color:var(--admin-muted); font-size:12px; }
    .legacy-direct-customer-summary strong { display:block; margin-top:6px; color:var(--admin-strong); font:600 20px/1.2 'Fira Code', monospace; }
    .legacy-direct-customer-table-wrap { overflow-x:auto; border:1px solid var(--admin-line); border-radius:12px; background:var(--admin-panel); }
    .legacy-direct-customer-table { min-width:1180px; margin:0; }
    .legacy-direct-customer-table th { color:var(--admin-text); background:var(--admin-hover); white-space:nowrap; }
    .legacy-direct-customer-table td { white-space:nowrap; }
    .legacy-direct-customer-table .mono { font-family:'Fira Code', monospace; }
    .legacy-direct-customer-table tfoot td { color:var(--admin-blue); background:var(--admin-hover); font-weight:600; }
    @media (max-width: 900px) {
        .legacy-direct-customer-hero { flex-direction:column; }
        .legacy-direct-customer-hero .metric { text-align:left; }
        .legacy-direct-customer-summary { grid-template-columns:repeat(2,minmax(0,1fr)); }
    }
    @media (max-width: 520px) { .legacy-direct-customer-summary { grid-template-columns:1fr; } }
</style>
@endsection

@section('content')
<div class="layui-fluid legacy-direct-customer-page" data-legacy-direct-customers>
    <section class="legacy-direct-customer-hero">
        <div>
            <h2>{{ __('admin.users') }} · {{ $parentAgent ? $parentAgent->user_name : '' }}</h2>
            <p>{{ __('front.user_id') }}: <span class="mono">{{ $parentAgent ? $parentAgent->user_id : '' }}</span></p>
        </div>
        <div class="metric">
            <strong>{{ (int) ($result['total'] ?? 0) }}</strong>
            <span>{{ __('admin.users') }}</span>
        </div>
    </section>

    <section class="legacy-direct-customer-summary" aria-label="{{ __('admin.users') }}">
        <article><span>{{ __('front.account_balance') }}</span><strong>{{ $summary['mt4_balance'] ?? '0.00' }}</strong></article>
        <article><span>{{ __('front.account_equity') }}</span><strong>{{ $summary['mt4_equity'] ?? '0.00' }}</strong></article>
        <article><span>{{ __('systemlanguageadmin.account_deposit_moneny') }}</span><strong>{{ $summary['total_yuerj'] ?? '0.00' }}</strong></article>
        <article><span>{{ __('systemlanguageadmin.account_withdrawal_moneny') }}</span><strong>{{ $summary['total_yuecj'] ?? '0.00' }}</strong></article>
    </section>

    <div class="legacy-direct-customer-table-wrap">
        <table class="layui-table legacy-direct-customer-table">
            <thead>
                <tr>
                    <th>{{ __('front.account_type') }}</th>
                    <th>{{ __('front.account_group') }}</th>
                    <th>{{ __('front.account_id') }}</th>
                    <th>{{ __('front.user_name') }}</th>
                    <th>{{ __('front.account_balance') }}</th>
                    <th>{{ __('front.account_equity') }}</th>
                    <th>{{ __('systemlanguageadmin.account_deposit_moneny') }}</th>
                    <th>{{ __('systemlanguageadmin.account_withdrawal_moneny') }}</th>
                    <th>{{ __('systemlanguageadmin.position_summary_net_deposit') }}</th>
                    <th>{{ __('systemlanguageadmin.position_summary_total_comm') }}</th>
                    <th>{{ __('systemlanguageadmin.position_summary_total_money') }}</th>
                    <th>{{ __('systemlanguageadmin.Registration_time') }}</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($rows as $row)
                <tr data-customer-id="{{ (int) ($row['user_id'] ?? 0) }}">
                    <td>{{ __('register.customer') }}</td>
                    <td>{{ $row['mt4_grp'] ?? '' }}</td>
                    <td class="mono">{{ $row['user_id'] ?? '' }}</td>
                    <td>{{ $row['user_name'] ?? '' }}</td>
                    <td class="mono">{{ $row['mt4_balance'] ?? '0.00' }}</td>
                    <td class="mono">{{ $row['mt4_equity'] ?? '0.00' }}</td>
                    <td class="mono">{{ $row['total_yuerj'] ?? '0.00' }}</td>
                    <td class="mono">{{ $row['total_yuecj'] ?? '0.00' }}</td>
                    <td class="mono">{{ $row['total_net_worth'] ?? '0.00' }}</td>
                    <td class="mono">{{ $row['total_comm'] ?? '0.00' }}</td>
                    <td class="mono">{{ $row['total_profit'] ?? '0.00' }}</td>
                    <td>{{ $row['rec_crt_date'] ?? '' }}</td>
                </tr>
            @empty
                <tr><td colspan="12" class="crm-empty-state">{{ __('common.no_data') }}</td></tr>
            @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4">{{ __('systemlanguage.total') }}</td>
                    <td class="mono">{{ $summary['mt4_balance'] ?? '0.00' }}</td>
                    <td class="mono">{{ $summary['mt4_equity'] ?? '0.00' }}</td>
                    <td class="mono">{{ $summary['total_yuerj'] ?? '0.00' }}</td>
                    <td class="mono">{{ $summary['total_yuecj'] ?? '0.00' }}</td>
                    <td class="mono">{{ $summary['total_net_worth'] ?? '0.00' }}</td>
                    <td class="mono">{{ $summary['total_comm'] ?? '0.00' }}</td>
                    <td class="mono">{{ $summary['total_profit'] ?? '0.00' }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection

@section('scripts')
<div hidden data-layui-page="users/direct-customers"></div>
@endsection
