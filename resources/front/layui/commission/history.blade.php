@extends('front_layui::layouts.app')

@section('title', __('front.commission_history'))
@section('breadcrumb', __('breadcrumb.front_commission_hist'))

@section('content')
@include('front_layui::partials.module-page', [
    'pageClass' => 'commission-module commission-history-module',
    'titleKey' => 'front.commission_history',
    'descriptionKey' => 'front.commission_history_desc',
    'api' => '/api/front/commissionHistory',
    'filters' => [
        ['name' => 'orderId', 'label' => 'front.order_no', 'type' => 'text'],
        ['name' => 'startdate', 'label' => 'front.date_from', 'type' => 'date'],
        ['name' => 'enddate', 'label' => 'front.date_to', 'type' => 'date'],
    ],
    'summaryFields' => [
        ['key' => 'commission_amount', 'label' => 'front.commission'],
        ['key' => 'returned_amount', 'label' => 'front.returned_amount'],
        ['key' => 'real_amount', 'label' => 'front.real_amount'],
        ['key' => 'agent_volume', 'label' => 'front.total_volume'],
    ],
    'columns' => [
        ['key' => 'unique_id', 'label' => 'front.unique_id'],
        ['key' => 'agent_id', 'label' => 'front.user_id'],
        ['key' => 'order_no', 'label' => 'front.order_no'],
        ['key' => 'commission_amount', 'label' => 'front.commission', 'format' => 'money'],
        ['key' => 'returned_amount', 'label' => 'front.returned_amount', 'format' => 'money'],
        ['key' => 'real_amount', 'label' => 'front.real_amount', 'format' => 'money'],
        ['key' => 'settle_status_text', 'label' => 'front.settle_status'],
        ['key' => 'comment', 'label' => 'common.remark'],
        ['key' => 'data_type', 'label' => 'front.flow_type'],
        ['key' => 'created_time', 'label' => 'common.created_at'],
        ['key' => 'modify_time', 'label' => 'common.updated_at'],
    ],
])
@endsection

@section('scripts')
<script src="{{ asset('/js/front/layui/module-page.js') }}?v=2026052911"></script>
@endsection
