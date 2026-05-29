@extends('front_layui::layouts.app')

@section('title', __('front.gift_address'))
@section('breadcrumb', __('breadcrumb.front_gift_address'))

@section('content')
@include('front_layui::partials.module-page', [
    'titleKey' => 'front.gift_address',
    'descriptionKey' => 'front.gift_address_desc',
    'api' => '/api/front/giftAddressList',
    'submitApi' => '/api/front/giftAddAddress',
    'editApi' => '/api/front/giftUpdateAddress',
    'filters' => [
        ['name' => 'recipient_name', 'label' => 'front.receiver_name', 'type' => 'text'],
        ['name' => 'recipient_phone', 'label' => 'front.phone', 'type' => 'text'],
        ['name' => 'is_default', 'label' => 'front.default_address', 'type' => 'select', 'options' => [
            ['value' => '1', 'label' => 'front.yes'],
            ['value' => '0', 'label' => 'front.no'],
        ]],
        ['name' => 'startdate', 'label' => 'front.date_from', 'type' => 'date'],
        ['name' => 'enddate', 'label' => 'front.date_to', 'type' => 'date'],
    ],
    'formFields' => [
        ['name' => 'recipient_name', 'label' => 'front.receiver_name', 'type' => 'text', 'verify' => 'required'],
        ['name' => 'recipient_phone', 'label' => 'front.phone', 'type' => 'text', 'verify' => 'required'],
        ['name' => 'recipient_address', 'label' => 'front.address', 'type' => 'textarea', 'verify' => 'required', 'width' => 12],
        ['name' => 'is_default', 'label' => 'front.default_address', 'title' => 'front.default_address', 'type' => 'checkbox', 'width' => 12],
    ],
    'columns' => [
        ['key' => 'recipient_name', 'label' => 'front.receiver_name'],
        ['key' => 'recipient_phone', 'label' => 'front.phone'],
        ['key' => 'recipient_address', 'label' => 'front.address'],
        ['key' => 'is_default', 'label' => 'front.default_address'],
    ],
    'rowActions' => [
        ['type' => 'edit', 'label' => 'common.edit', 'style' => 'normal'],
        ['api' => '/api/front/giftUpdateAddress', 'label' => 'front.set_default', 'confirm' => 'front.confirm_set_default', 'payload' => ['is_default' => 1]],
        ['api' => '/api/front/giftDeleteAddress', 'label' => 'common.delete', 'confirm' => 'common.confirm_delete', 'style' => 'danger'],
    ],
])
@endsection

@section('scripts')
<script src="{{ asset('/js/front/layui/module-page.js') }}?v=2026052907"></script>
@endsection
