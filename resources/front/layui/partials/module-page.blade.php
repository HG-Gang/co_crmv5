@php
    $filters = $filters ?? [];
    $formFields = $formFields ?? [];
    $columns = $columns ?? [];
    $summaryFields = $summaryFields ?? [];
    $chartGroups = $chartGroups ?? [];
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
    .front-module-page .module-stat { background: var(--front-panel); border: 1px solid var(--front-line); border-radius: 6px; padding: 18px; margin-bottom: 15px; min-height: 78px; }
    .front-module-page .module-stat-label { color: var(--front-muted); font-size: 13px; margin-bottom: 8px; }
    .front-module-page .module-stat-value { color: var(--front-strong); font-size: 22px; font-weight: 600; word-break: break-word; }
    .front-module-page .module-summary-toggle { display: inline-flex; align-items: center; gap: 5px; height: 30px; margin: 0 0 10px 7.5px; padding: 0 12px; border: 1px solid var(--front-line); border-radius: 999px; color: var(--front-text); background: var(--front-panel); cursor: pointer; }
    .front-module-page .module-summary-toggle span { color: var(--front-blue); font-weight: 800; }
    .front-module-page .module-summary-items { display: flex; flex-wrap: wrap; width: 100%; }
    .front-module-page .module-summary-items.is-collapsed { display: none; }
    .front-module-page .module-empty { text-align: center; color: var(--front-muted); padding: 28px 0; }
    .front-module-page .layui-card-header { font-weight: 600; }
    .front-module-page .module-table-wrap { width: 100%; overflow-x: auto; }
    .front-module-page .module-table-wrap table { min-width: 980px; }
    .front-module-page .module-chart-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 12px; margin-bottom: 15px; }
    .front-module-page .module-chart-card { border: 1px solid var(--front-line); border-radius: 10px; padding: 12px; background: var(--front-panel); }
    .front-module-page .module-chart-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 8px; }
    .front-module-page .module-chart-title { color: var(--front-strong); font-weight: 700; }
    .front-module-page .module-chart-type { width: 112px; height: 30px; border: 1px solid var(--front-line); border-radius: 999px; padding: 0 9px; background: var(--front-panel); color: var(--front-text); }
    .front-module-page .module-chart-canvas { height: 240px; }
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
    data-chart-groups='@json($chartGroups)'
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
                                            @if(($field['type'] ?? 'text') === 'file')
                                                @php $uploadId = 'crm_upload_' . md5($field['name'] . $loop->index); @endphp
                                                <div class="crm-upload-card">
                                                    <div class="crm-upload-main">
                                                        <button type="button" class="layui-btn layui-btn-primary crm-upload-action" id="{{ $uploadId }}_trigger">
                                                            <i class="layui-icon layui-icon-upload-drag"></i>
                                                            <span data-translate="{{ $field['label'] }}">{{ __($field['label']) }}</span>
                                                        </button>
                                                        <div class="crm-upload-tip">
                                                            <span data-translate="front.upload_drag_tip">{{ __('front.upload_drag_tip') }}</span>
                                                        </div>
                                                    </div>
                                                    <input
                                                        id="{{ $uploadId }}"
                                                        type="file"
                                                        name="{{ $field['name'] }}"
                                                        class="layui-hide J_crmUploadInput"
                                                        @if(!empty($field['accept'])) accept="{{ $field['accept'] }}" @endif
                                                        @if(!empty($field['multiple'])) multiple @endif
                                                        @if(!empty($field['verify'])) lay-verify="{{ $field['verify'] }}" @endif
                                                    >
                                                    <div class="crm-upload-list" id="{{ $uploadId }}_list"></div>
                                                </div>
                                            @else
                                                <input
                                                    type="{{ $field['type'] ?? 'text' }}"
                                                    name="{{ $field['name'] }}"
                                                    class="layui-input"
                                                    data-translate-placeholder="{{ $field['placeholder'] ?? $field['label'] }}"
                                                    placeholder="{{ __($field['placeholder'] ?? $field['label']) }}"
                                                    autocomplete="off"
                                                    @if(!empty($field['verify'])) lay-verify="{{ $field['verify'] }}" @endif
                                                >
                                            @endif
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

            @if(!empty($summaryFields) && $showSummary)
                <div class="layui-row layui-col-space15" id="moduleSummary"></div>
            @endif

            @if(!empty($chartGroups))
                <div class="module-chart-grid" id="moduleChartGrid">
                    @foreach($chartGroups as $chart)
                        <div class="module-chart-card">
                            <div class="module-chart-head">
                                <div class="module-chart-title" data-translate="{{ $chart['title'] }}">{{ __($chart['title']) }}</div>
                                <select class="module-chart-type J_moduleChartType" data-chart-target="{{ $chart['target'] }}" lay-ignore></select>
                            </div>
                            <div class="module-chart-canvas" id="{{ $chart['target'] }}"></div>
                        </div>
                    @endforeach
                </div>
            @endif

            @if($showChain)
                <div class="module-chain" id="moduleChain"></div>
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
