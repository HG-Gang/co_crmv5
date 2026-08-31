{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/17
Time: 20:28
--}}
@extends('front_layui::legacy-big-agent.layout')

@section('title', __($legacyModule['title']))

@section('content')
<div class="layui-card">
    <div class="layui-card-header">{{ __($legacyModule['title']) }}</div>
    <div class="layui-card-body">
        <form class="layui-form layui-form-pane" id="legacyBigAgentSearchForm">
            <div class="layui-row layui-col-space10">
                @foreach($legacyModule['filters'] as $filter)
                    <div class="layui-col-md3 layui-col-sm6">
                        <div class="layui-form-item">
                            <div class="layui-input-block">
                                @if(($filter['type'] ?? 'text') === 'select')
                                    <select name="{{ $filter['name'] }}"
                                            data-options-endpoint="{{ $filter['endpoint'] }}"
                                            lay-search>
                                        <option value="">{{ __($filter['label']) }}</option>
                                    </select>
                                @else
                                    <input type="{{ ($filter['type'] ?? 'text') === 'date' ? 'date' : 'text' }}"
                                           name="{{ $filter['name'] }}"
                                           class="layui-input"
                                           autocomplete="off"
                                           placeholder="{{ __($filter['label']) }}">
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
                <div class="layui-col-md3 layui-col-sm6">
                    <button class="layui-btn" lay-submit lay-filter="legacyBigAgentSearch">{{ __('common.search') }}</button>
                    <button type="button" class="layui-btn layui-btn-primary" id="legacyBigAgentReset">{{ __('common.reset') }}</button>
                </div>
            </div>
        </form>

        <div class="layui-table-box" style="overflow-x: auto;">
            <table class="layui-table" id="legacyBigAgentResultTable">
                <thead><tr>
                    @foreach($legacyModule['columns'] as $column)
                        <th>{{ __($column['label']) }}</th>
                    @endforeach
                </tr></thead>
                <tbody><tr><td colspan="{{ count($legacyModule['columns']) }}" class="legacy-big-agent-muted">{{ __('common.loading') }}</td></tr></tbody>
                <tfoot></tfoot>
            </table>
        </div>
        <div id="legacyBigAgentPagination"></div>
    </div>
</div>

<div hidden id="legacyBigAgentTableConfig"
     data-endpoint="{{ url($legacyModule['endpoint']) }}"
     data-child-endpoint="{{ !empty($legacyModule['childEndpoint']) ? url($legacyModule['childEndpoint']) : '' }}"
     data-columns='@json($legacyModule['columns'])'
     data-empty-text="{{ __('common.no_data') }}"
     data-error-text="{{ __('common.error') }}"></div>
@endsection
