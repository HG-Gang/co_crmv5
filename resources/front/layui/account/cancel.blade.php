@extends('front_layui::layouts.app')

@section('title', __('front.account_cancel'))
@section('breadcrumb', __('breadcrumb.front_cancel'))

@section('content')
@include('front_layui::partials.module-page', [
    'titleKey' => 'front.account_cancel',
    'descriptionKey' => 'front.account_cancel_desc',
    'api' => '/api/front/cancelStatus',
    'submitApi' => '/api/front/cancelApply',
    'formFields' => [
        ['name' => 'reason', 'label' => 'front.apply_reason', 'type' => 'textarea', 'width' => 12],
    ],
    'summaryFields' => [
        ['key' => 'status', 'label' => 'common.status'],
        ['key' => 'cancel_remark', 'label' => 'front.apply_reason'],
        ['key' => 'reject_reason', 'label' => 'front.reject_reason'],
        ['key' => 'created_at', 'label' => 'common.created_at'],
        ['key' => 'updated_at', 'label' => 'common.updated_at'],
    ],
])
@endsection

@section('scripts')
<script src="{{ asset('/js/front/layui/module-page.js') }}?v=2026052911"></script>
@endsection
