@extends('front_layui::layouts.app')

@section('title', __('front.account_overview'))
@section('breadcrumb', __('breadcrumb.front_account_info'))

@section('content')
@include('front_layui::partials.module-page', [
    'titleKey' => 'front.account_overview',
    'descriptionKey' => 'front.account_overview_desc',
    'api' => '/api/front/accountInfo',
    'summaryFields' => [
        ['key' => 'user_id', 'label' => 'front.user_id'],
        ['key' => 'user_name', 'label' => 'front.user_name'],
        ['key' => 'email', 'label' => 'front.email'],
        ['key' => 'total_funds', 'label' => 'front.total_funds'],
        ['key' => 'equity', 'label' => 'front.equity'],
        ['key' => 'used_margin', 'label' => 'front.used_margin'],
        ['key' => 'avail_margin', 'label' => 'front.avail_margin'],
        ['key' => 'effective_credit', 'label' => 'front.effective_credit'],
        ['key' => 'risk_ratio', 'label' => 'front.risk_ratio'],
        ['key' => 'leverage', 'label' => 'front.leverage'],
        ['key' => 'group_id', 'label' => 'front.group_id'],
    ],
])
@endsection

@section('scripts')
<script src="{{ asset('/js/front/layui/module-page.js') }}?v=2026052907"></script>
@endsection
