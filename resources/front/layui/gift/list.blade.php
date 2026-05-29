@extends('front_layui::layouts.app')

@section('title', __('front.gift_list'))
@section('breadcrumb', __('breadcrumb.front_gift_list'))

@section('content')
@include('front_layui::partials.module-page', [
    'titleKey' => 'front.gift_list',
    'descriptionKey' => 'front.gift_list_desc',
    'api' => '/api/front/giftList',
    'listKey' => 'available_gifts',
    'filters' => [
        ['name' => 'name', 'label' => 'front.gift_name', 'type' => 'text'],
        ['name' => 'points_cost', 'label' => 'front.points_cost', 'type' => 'text'],
    ],
    'columns' => [
        ['key' => 'id', 'label' => 'common.id'],
        ['key' => 'name', 'label' => 'front.gift_name'],
        ['key' => 'description', 'label' => 'front.description'],
        ['key' => 'points_cost', 'label' => 'front.points_cost'],
    ],
])
@endsection

@section('scripts')
<script src="{{ asset('/js/front/layui/module-page.js') }}?v=2026052907"></script>
@endsection
