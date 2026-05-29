@php
    $filters = $filters ?? [];
    $formFields = $formFields ?? [];
    $columns = $columns ?? [];
    $summaryFields = $summaryFields ?? [];
    $submitApi = $submitApi ?? '';
    $editApi = $editApi ?? '';
    $listKey = $listKey ?? '';
    $rowActions = $rowActions ?? [];
    $showSummary = $showSummary ?? true;
    $showChain = $showChain ?? false;
    $pageClass = $pageClass ?? '';
    $defaultFilters = $defaultFilters ?? [];
    $hasColumnGroups = false;
    foreach ($columns as $column) {
        if (!empty($column['group'])) {
            $hasColumnGroups = true;
            break;
        }
    }
@endphp

<style>
    .front-module-page .module-toolbar { margin-bottom: 15px; }
    .front-module-page .module-stats-row { display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-start; text-align: left; margin-bottom: 14px; }
    .front-module-page .module-stat-item { background: var(--front-panel); border: 1px solid var(--front-line); border-radius: 6px; padding: 12px 16px; min-width: 140px; }
    .front-module-page .module-stat-label { color: var(--front-muted); font-size: 12px; margin-bottom: 6px; }
    .front-module-page .module-stat-value { color: var(--front-strong); font-size: 18px; font-weight: 600; word-break: break-word; }
    /* Req 16: collapsible chart section for commission pages */
    .module-chart-collapse { margin-bottom: 14px; }
    .module-chart-toggle { display: inline-flex; align-items: center; gap: 4px; padding: 4px 12px; border: 1px solid var(--front-line, #dce3ec); border-radius: 6px; background: transparent; color: var(--front-muted, #6b7280); font-size: 12px; cursor: pointer; user-select: none; }
    .module-chart-toggle .toggle-arrow { display: inline-block; transition: transform .25s; }
    .module-chart-toggle.open .toggle-arrow { transform: rotate(90deg); }
    .module-chart-body { display: none; margin-top: 10px; }
    .module-chart-body.show { display: block; }
    .front-module-page .module-empty { text-align: center; color: var(--front-muted); padding: 28px 0; }
    .front-module-page .layui-card-header { font-weight: 600; }
    .front-module-page .module-table-wrap { width: 100%; overflow-x: auto; }
    .front-module-page .module-table-wrap table { min-width: 980px; }
</style>

<div
    id="frontModulePage"
    class="front-module-page {{ $pageClass }}"
    data-api="{{ $api }}"
    data-submit-api="{{ $submitApi }}"
    data-edit-api="{{ $editApi }}"
    data-list-key="{{ $listKey }}"
    data-default-filters='@json($defaultFilters)'
    data-columns='@json($columns)'
    data-summary-fields='@json($summaryFields)'
    data-row-actions='@json($rowActions)'
    data-per-page="20"
>
    <div class="layui-card">
        <div class="layui-card-header" data-translate="{{ $titleKey }}">{{ __($titleKey) }}</div>
        <div class="layui-card-body">
            @if(!empty($filters))
                <form class="layui-form layui-form-pane module-toolbar" lay-filter="moduleSearchForm">
                    <div class="layui-row layui-col-space10">
                        @foreach($filters as $filter)
                            <div class="layui-col-md3 layui-col-sm6">
                                <div class="layui-form-item">
                                    <label class="layui-form-label" data-translate="{{ $filter['label'] }}">{{ __($filter['label']) }}</label>
                                    <div class="layui-input-block">
                                        @if(($filter['type'] ?? 'text') === 'select')
                                            <select name="{{ $filter['name'] }}" class="J_moduleFilter">
                                                <option value="" data-translate="common.all">{{ __('common.all') }}</option>
                                                @foreach(($filter['options'] ?? []) as $option)
                                                    <option value="{{ $option['value'] }}" data-translate="{{ $option['label'] }}">{{ __($option['label']) }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            @php $inputType = ($filter['type'] ?? 'text') === 'date' ? 'text' : ($filter['type'] ?? 'text'); @endphp
                                            <input
                                                type="{{ $inputType }}"
                                                name="{{ $filter['name'] }}"
                                                class="layui-input J_moduleFilter {{ ($filter['type'] ?? 'text') === 'date' ? 'J_layDate' : '' }}"
                                                data-translate-placeholder="{{ $filter['placeholder'] ?? $filter['label'] }}"
                                                placeholder="{{ __($filter['placeholder'] ?? $filter['label']) }}"
                                                autocomplete="off"
                                            >
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        <div class="layui-col-md3 layui-col-sm6">
                            <button class="layui-btn" lay-submit lay-filter="moduleSearchSubmit" data-translate="common.search">{{ __('common.search') }}</button>
                            <button type="button" class="layui-btn layui-btn-primary J_moduleReset" data-translate="common.reset">{{ __('common.reset') }}</button>
                        </div>
                    </div>
                </form>
            @endif

            @if(!empty($formFields))
                <form class="layui-form layui-form-pane J_moduleForm" lay-filter="moduleSubmitForm" enctype="multipart/form-data">
                    @if(!empty($editApi))
                        <input type="hidden" name="id" class="J_moduleRecordId" value="">
                    @endif
                    <div class="layui-row layui-col-space10">
                        @foreach($formFields as $field)
                            <div class="layui-col-md{{ $field['width'] ?? 6 }} layui-col-sm12">
                                <div class="layui-form-item">
                                    <label class="layui-form-label" data-translate="{{ $field['label'] }}">{{ __($field['label']) }}</label>
                                    <div class="layui-input-block">
                                        @if(($field['type'] ?? 'text') === 'textarea')
                                            <textarea
                                                name="{{ $field['name'] }}"
                                                class="layui-textarea"
                                                data-translate-placeholder="{{ $field['placeholder'] ?? $field['label'] }}"
                                                placeholder="{{ __($field['placeholder'] ?? $field['label']) }}"
                                                @if(!empty($field['verify'])) lay-verify="{{ $field['verify'] }}" @endif
                                            ></textarea>
                                        @elseif(($field['type'] ?? 'text') === 'select')
                                            <select name="{{ $field['name'] }}" @if(!empty($field['verify'])) lay-verify="{{ $field['verify'] }}" @endif>
                                                <option value="" data-translate="{{ $field['placeholder'] ?? $field['label'] }}">{{ __($field['placeholder'] ?? $field['label']) }}</option>
                                                @foreach(($field['options'] ?? []) as $option)
                                                    <option value="{{ $option['value'] }}" data-translate="{{ $option['label'] }}">{{ __($option['label']) }}</option>
                                                @endforeach
                                            </select>
                                        @elseif(($field['type'] ?? 'text') === 'checkbox')
                                            <input
                                                type="checkbox"
                                                name="{{ $field['name'] }}"
                                                value="{{ $field['value'] ?? 1 }}"
                                                lay-skin="primary"
                                                data-translate-title="{{ $field['title'] ?? $field['label'] }}"
                                                title="{{ __($field['title'] ?? $field['label']) }}"
                                            >
                                        @else
                                            <input
                                                type="{{ $field['type'] ?? 'text' }}"
                                                name="{{ $field['name'] }}"
                                                class="layui-input"
                                                data-translate-placeholder="{{ $field['placeholder'] ?? $field['label'] }}"
                                                placeholder="{{ __($field['placeholder'] ?? $field['label']) }}"
                                                autocomplete="off"
                                                @if(!empty($field['accept'])) accept="{{ $field['accept'] }}" @endif
                                                @if(!empty($field['multiple'])) multiple @endif
                                                @if(!empty($field['verify'])) lay-verify="{{ $field['verify'] }}" @endif
                                            >
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        <div class="layui-col-md12">
                            <button class="layui-btn layui-bg-blue" lay-submit lay-filter="moduleFormSubmit" data-translate="common.submit">{{ __('common.submit') }}</button>
                        </div>
                    </div>
                </form>
                <hr>
            @endif

            {{-- Req 9: summary in independent left-aligned div --}}
            @if(!empty($summaryFields) && $showSummary)
                <div id="moduleSummary"></div>
            @endif

            {{-- Req 16: collapsible chart div for commission stats --}}
            @if(!empty($showChartCollapse))
            <div class="module-chart-collapse">
                <button type="button" class="module-chart-toggle" id="moduleChartToggle">
                    <span class="toggle-arrow">»</span> <span data-translate="front.chart_view">{{ __('front.chart_view', '图表查看') }}</span>
                </button>
                <div class="module-chart-body" id="moduleChartBody">
                    <div id="moduleStatsChart" style="width:100%;height:240px;"></div>
                </div>
            </div>
            @endif

            @if($showChain)
                <div class="module-chain" id="moduleChain" style="display:none;"></div>
            @endif

            @if(!empty($columns))
                <div class="crm-table-summary module-auto-summary" id="moduleAutoSummary"></div>
                <div class="module-table-wrap">
                    <table class="layui-table" lay-size="sm">
                        <thead>
                            @if($hasColumnGroups)
                                <tr>
                                    @php $index = 0; $columnCount = count($columns); @endphp
                                    @while($index < $columnCount)
                                        @php $column = $columns[$index]; @endphp
                                        @if(!empty($column['group']))
                                            @php
                                                $groupKey = $column['group'];
                                                $span = 0;
                                                while (($index + $span) < $columnCount && (($columns[$index + $span]['group'] ?? '') === $groupKey)) {
                                                    $span++;
                                                }
                                            @endphp
                                            <th colspan="{{ $span }}" class="module-table-group" data-translate="{{ $groupKey }}">{{ __($groupKey) }}</th>
                                            @php $index += $span; @endphp
                                        @else
                                            <th rowspan="2" data-translate="{{ $column['label'] }}">{{ __($column['label']) }}</th>
                                            @php $index++; @endphp
                                        @endif
                                    @endwhile
                                    @if(!empty($rowActions))
                                        <th rowspan="2" data-translate="common.operation">{{ __('common.operation') }}</th>
                                    @endif
                                </tr>
                                <tr>
                                    @foreach($columns as $column)
                                        @if(!empty($column['group']))
                                            <th data-translate="{{ $column['label'] }}">{{ __($column['label']) }}</th>
                                        @endif
                                    @endforeach
                                </tr>
                            @else
                                <tr>
                                    @foreach($columns as $column)
                                        <th data-translate="{{ $column['label'] }}">{{ __($column['label']) }}</th>
                                    @endforeach
                                    @if(!empty($rowActions))
                                        <th data-translate="common.operation">{{ __('common.operation') }}</th>
                                    @endif
                                </tr>
                            @endif
                        </thead>
                        <tbody id="moduleTableBody"></tbody>
                    </table>
                </div>
                <div id="modulePager"></div>
            @endif
        </div>
    </div>
</div>
