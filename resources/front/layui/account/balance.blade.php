@extends('front_layui::layouts.app')

@section('title', __('front.account_overview'))
@section('breadcrumb', __('breadcrumb.front_account_info'))

@section('content')
@include('front_layui::partials.module-page', [
    'titleKey' => 'front.account_overview',
    'descriptionKey' => 'front.account_overview_desc',
    'api' => '/api/front/accountBalance',
    'summaryFields' => [
        ['key' => 'user_id', 'label' => 'front.user_id'],
        ['key' => 'user_name', 'label' => 'front.user_name'],
        ['key' => 'email', 'label' => 'front.email'],
        ['key' => 'balance', 'label' => 'front.balance'],
        ['key' => 'credit', 'label' => 'front.credit'],
        ['key' => 'equity', 'label' => 'front.equity'],
        ['key' => 'margin', 'label' => 'front.margin'],
        ['key' => 'free_margin', 'label' => 'front.free_margin'],
        ['key' => 'margin_level', 'label' => 'front.margin_level'],
        ['key' => 'leverage', 'label' => 'front.leverage'],
        ['key' => 'group_id', 'label' => 'front.group_id'],
    ],
])
@endsection

@section('scripts')
<script src="{{ asset('/js/front/layui/module-page.js') }}?v=2026052907"></script>
@endsection
