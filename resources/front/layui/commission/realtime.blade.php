@extends('front_layui::layouts.app')

@section('title', __('front.realtime_commission'))
@section('breadcrumb', __('breadcrumb.front_commission_rt'))

@section('content')
@include('front_layui::partials.module-page', [
    'pageClass' => 'commission-module commission-realtime-module',
    'titleKey' => 'front.realtime_commission',
    'descriptionKey' => 'front.realtime_commission_desc',
    'api' => '/api/front/commissionRealTime',
    'listKey' => 'list',
    'showSummary' => true,
    'filters' => [
        ['name' => 'userId', 'label' => 'front.user_id', 'type' => 'text'],
        ['name' => 'orderId', 'label' => 'front.order_no', 'type' => 'text'],
        ['name' => 'startdate', 'label' => 'front.date_from', 'type' => 'date'],
        ['name' => 'enddate', 'label' => 'front.date_to', 'type' => 'date'],
    ],
    'summaryFields' => [
        ['key' => 'total_commission', 'label' => 'front.total_commission'],
        ['key' => 'total_volume', 'label' => 'front.total_volume'],
        ['key' => 'profit_gain', 'label' => 'front.profit_gain'],
        ['key' => 'profit_loss', 'label' => 'front.profit_loss'],
        ['key' => 'profit_net', 'label' => 'front.profit_net'],
    ],
    'columns' => [
        ['key' => 'ticket', 'label' => 'front.ticket', 'action' => 'showOrderInfo', 'linkClass' => 'module-link-order'],
        ['key' => 'login', 'label' => 'front.user_id', 'action' => 'showUserInfo', 'linkClass' => 'module-link-user'],
        ['key' => 'volume_lots', 'label' => 'front.volume', 'format' => 'lots'],
        ['key' => 'profit_gain', 'label' => 'front.profit_gain', 'format' => 'money'],
        ['key' => 'profit_loss', 'label' => 'front.profit_loss', 'format' => 'money'],
        ['key' => 'profit_net', 'label' => 'front.profit_net', 'format' => 'money'],
        ['key' => 'comment', 'label' => 'common.remark'],
        ['key' => 'modify_time', 'label' => 'common.updated_at'],
    ],
])
@endsection

@section('scripts')
<script src="{{ asset('/js/front/layui/module-page.js') }}?v=2026052911"></script>
@endsection
