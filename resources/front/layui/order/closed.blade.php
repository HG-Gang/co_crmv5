@extends('front_layui::layouts.app')

@section('title', __('front.closed_orders'))
@section('breadcrumb', __('breadcrumb.front_closed_orders'))

@section('content')
@include('front_layui::partials.module-page', [
    'titleKey' => 'front.closed_orders',
    'descriptionKey' => 'front.closed_orders_desc',
    'api' => '/api/front/closedOrders',
    'filters' => [
        ['name' => 'userId', 'label' => 'front.user_id', 'type' => 'text'],
        ['name' => 'orderId', 'label' => 'front.order_no', 'type' => 'text'],
        ['name' => 'symbol', 'label' => 'front.symbol', 'type' => 'text'],
        ['name' => 'is_coercion', 'label' => 'front.force_close', 'type' => 'select', 'options' => [
            ['value' => 'Yes', 'label' => 'front.yes'],
            ['value' => 'No', 'label' => 'front.no'],
        ]],
        ['name' => 'startdate', 'label' => 'front.date_from', 'type' => 'date'],
        ['name' => 'enddate', 'label' => 'front.date_to', 'type' => 'date'],
    ],
    'columns' => [
        ['key' => 'ticket', 'label' => 'front.ticket', 'action' => 'showOrderInfo', 'linkClass' => 'module-link-order'],
        ['key' => 'login', 'label' => 'front.user_id', 'action' => 'showUserInfo', 'linkClass' => 'module-link-user'],
        ['key' => 'symbol', 'label' => 'front.symbol'],
        ['key' => 'cmd', 'label' => 'front.order_cmd', 'format' => 'cmd'],
        ['key' => 'volume_lots', 'label' => 'front.volume', 'format' => 'lots'],
        ['key' => 'sl', 'label' => 'front.stop_loss'],
        ['key' => 'tp', 'label' => 'front.take_profit'],
        ['key' => 'commission', 'label' => 'front.commission', 'format' => 'money'],
        ['key' => 'profit', 'label' => 'front.profit', 'format' => 'money'],
        ['key' => 'swaps', 'label' => 'front.swaps', 'format' => 'money'],
        ['key' => 'open_price', 'label' => 'front.open_price'],
        ['key' => 'close_price', 'label' => 'front.close_price'],
        ['key' => 'close_time', 'label' => 'front.close_time'],
    ],
    'rowActions' => [
        ['type' => 'showOrderInfo', 'label' => 'common.detail', 'title' => 'front.order_detail', 'style' => 'normal'],
    ],
])
@endsection

@section('scripts')
<script src="{{ asset('/js/front/layui/module-page.js') }}?v=2026052907"></script>
@endsection
