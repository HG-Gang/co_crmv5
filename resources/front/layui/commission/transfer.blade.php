@extends('front_layui::layouts.app')

@section('title', __('front.commission_transfer'))
@section('breadcrumb', __('breadcrumb.front_commission_transfer'))

@section('content')
@include('front_layui::partials.module-page', [
    'pageClass' => 'commission-module commission-transfer-module',
    'titleKey' => 'front.commission_transfer',
    'descriptionKey' => 'front.commission_transfer_desc',
    'api' => '/api/front/commissionHistory',
    'submitApi' => '/api/front/commissionTransfer',
    'defaultFilters' => [
        'dataType' => 'transfer',
    ],
    'filters' => [
        ['name' => 'orderId', 'label' => 'front.order_no', 'type' => 'text'],
        ['name' => 'date_from', 'label' => 'front.date_from', 'type' => 'date'],
        ['name' => 'date_to', 'label' => 'front.date_to', 'type' => 'date'],
    ],
    'formFields' => [
        ['name' => 'sub_agent_id', 'label' => 'front.sub_agent_id', 'type' => 'number', 'verify' => 'required|number'],
        ['name' => 'amount', 'label' => 'front.amount', 'type' => 'number', 'verify' => 'required|number'],
        ['name' => 'remark', 'label' => 'common.remark', 'type' => 'textarea', 'width' => 12],
    ],
    'summaryFields' => [
        ['key' => 'commission_amount', 'label' => 'front.amount'],
        ['key' => 'real_amount', 'label' => 'front.real_amount'],
    ],
    'columns' => [
        ['key' => 'unique_id', 'label' => 'front.unique_id'],
        ['key' => 'agent_id', 'label' => 'front.user_id'],
        ['key' => 'commission_amount', 'label' => 'front.amount', 'format' => 'money'],
        ['key' => 'real_amount', 'label' => 'front.real_amount', 'format' => 'money'],
        ['key' => 'settle_status_text', 'label' => 'front.settle_status'],
        ['key' => 'data_type', 'label' => 'front.flow_type'],
        ['key' => 'remarks', 'label' => 'common.remark'],
        ['key' => 'created_time', 'label' => 'common.created_at'],
    ],
])
@endsection

@section('scripts')
<script src="{{ asset('/js/front/layui/module-page.js') }}?v=2026052907"></script>
@endsection
