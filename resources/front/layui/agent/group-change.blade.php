@extends('front_layui::layouts.app')

@section('title', __('front.group_change'))
@section('breadcrumb', __('breadcrumb.front_group_change'))

@section('content')
@include('front_layui::partials.module-page', [
    'titleKey' => 'front.group_change',
    'descriptionKey' => 'front.group_change_desc',
    'api' => '/api/front/agentGroupChangeList',
    'submitApi' => '/api/front/agentGroupChange',
    'filters' => [
        ['name' => 'userId', 'label' => 'front.user_id', 'type' => 'text'],
        ['name' => 'groupId', 'label' => 'front.group_id', 'type' => 'text'],
        ['name' => 'startdate', 'label' => 'front.date_from', 'type' => 'date'],
        ['name' => 'enddate', 'label' => 'front.date_to', 'type' => 'date'],
    ],
    'formFields' => [
        ['name' => 'target_user_id', 'label' => 'front.target_user_id', 'type' => 'number', 'verify' => 'required|number'],
        ['name' => 'new_group_id', 'label' => 'front.new_group_id', 'type' => 'number', 'verify' => 'required|number'],
        ['name' => 'reason', 'label' => 'front.apply_reason', 'type' => 'textarea', 'width' => 12],
    ],
    'columns' => [
        ['key' => 'trans_uid', 'label' => 'front.user_id'],
        ['key' => 'trans_type_gid', 'label' => 'front.group_id'],
        ['key' => 'trans_apply_status', 'label' => 'front.group_change_status'],
        ['key' => 'trans_apply_reason', 'label' => 'front.group_change_reason'],
        ['key' => 'rec_crt_date', 'label' => 'common.created_at'],
    ],
])
@endsection

@section('scripts')
<script src="{{ asset('/js/front/layui/module-page.js') }}?v=2026052907"></script>
@endsection
