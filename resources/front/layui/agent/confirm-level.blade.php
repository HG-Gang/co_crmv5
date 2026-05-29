@extends('front_layui::layouts.app')

@section('title', __('front.confirm_level'))
@section('breadcrumb', __('breadcrumb.front_agent_confirm'))

@section('content')
@include('front_layui::partials.module-page', [
    'pageClass' => 'agent-confirm-module',
    'titleKey' => 'front.confirm_level',
    'descriptionKey' => 'front.confirm_level_desc',
    'api' => '/api/front/agentConfirmLevel',
    'listKey' => 'list',
    'showSummary' => false,
    'filters' => [
        ['name' => 'userId', 'label' => 'front.user_id', 'type' => 'text'],
        ['name' => 'startdate', 'label' => 'front.date_from', 'type' => 'date'],
        ['name' => 'enddate', 'label' => 'front.date_to', 'type' => 'date'],
    ],
    'summaryFields' => [
        ['key' => 'summary.current_level.name', 'label' => 'front.current_level'],
        ['key' => 'summary.commission_rate', 'label' => 'front.commission_rate'],
        ['key' => 'summary.is_confirmed', 'label' => 'front.is_confirmed'],
    ],
    'columns' => [
        ['key' => 'userId', 'label' => 'front.user_id'],
        ['key' => 'userName', 'label' => 'front.user_name'],
        ['key' => 'gender', 'label' => 'front.gender', 'format' => 'gender'],
        ['key' => 'userEmail', 'label' => 'front.email'],
        ['key' => 'userPhone', 'label' => 'front.phone'],
        ['key' => 'agent_level_name', 'label' => 'front.agent_level', 'format' => 'agentLevel', 'rankKey' => 'agent_level_rank'],
        ['key' => 'userGroupId', 'label' => 'front.commission_rate', 'format' => 'agentLevelSelect'],
        ['key' => 'rec_crt_date', 'label' => 'common.created_at'],
    ],
    'rowActions' => [
        [
            'api' => '/api/front/agentConfirmLevelChange',
            'type' => 'confirmAgentLevel',
            'label' => 'front.confirm_level',
            'confirm' => 'front.confirm_level_desc',
            'idField' => 'userId',
        ],
    ],
])
@endsection

@section('scripts')
<script src="{{ asset('/js/front/layui/module-page.js') }}?v=2026052907"></script>
@endsection
