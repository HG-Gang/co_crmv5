{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/28
Time: 01:17
--}}
@php
    $filters = $filters ?? [];
    $formFields = $formFields ?? [];
    $columns = $columns ?? [];
    $summaryFields = $summaryFields ?? [];
    $chartGroups = $chartGroups ?? [];
    $comparisonTable = $comparisonTable ?? '';
    $submitApi = $submitApi ?? '';
    $verificationApi = $verificationApi ?? '';
    $verificationCodeApi = $verificationCodeApi ?? '';
    $editApi = $editApi ?? '';
    $editMethod = $editMethod ?? 'POST';
    $method = $method ?? 'POST';
    $listKey = $listKey ?? '';
    $rowActions = $rowActions ?? [];
    $showSummary = $showSummary ?? true;
    $showChain = $showChain ?? false;
    $pageClass = $pageClass ?? '';
    $timeline = $timeline ?? '';
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
    .front-module-page .module-summary-toggle { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 30px; margin: 0 0 10px 7.5px; padding: 0; border: 1px solid var(--front-line); border-radius: 8px; color: var(--front-blue); background: var(--front-panel); cursor: pointer; }
    .front-module-page .module-summary-toggle span { font-weight: 800; line-height: 1; }
    .front-module-page .module-summary-items { display: flex; flex-wrap: wrap; width: 100%; }
    .front-module-page .module-summary-items.is-collapsed { display: none; }
    .front-module-page .module-empty { text-align: center; color: var(--front-muted); padding: 28px 0; }
    .front-module-page .layui-card-header { font-weight: 600; }
    .front-module-page .module-table-wrap { width: 100%; overflow-x: auto; }
    .front-module-page .module-table-wrap table { min-width: 980px; }
    .front-module-page .module-chart-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 12px; margin-bottom: 15px; }
    .front-module-page .module-chart-card { border: 1px solid var(--front-line); border-radius: 8px; padding: 12px; background: var(--front-panel); }
    .front-module-page .module-chart-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 8px; }
    .front-module-page .module-chart-title { color: var(--front-strong); font-weight: 700; }
    .front-module-page .module-chart-controls { display: inline-flex; flex: 0 0 auto; gap: 4px; }
    .front-module-page .module-chart-type { display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; padding: 0; border: 1px solid var(--front-line); border-radius: 6px; background: var(--front-input); color: var(--front-text); cursor: pointer; }
    .front-module-page .module-chart-type:hover { border-color: var(--front-blue); color: var(--front-blue); background: var(--front-hover); }
    .front-module-page .module-chart-type.is-active { border-color: var(--front-blue); color: var(--front-panel); background: var(--front-blue); }
    .front-module-page .module-chart-type:focus-visible { position: relative; z-index: 1; outline: 2px solid var(--front-blue); outline-offset: -2px; }
    .front-module-page .module-chart-type svg { width: 18px; height: 18px; }
    @media screen and (max-width: 560px) { .front-module-page .module-chart-head { align-items: flex-start; flex-direction: column; } }
    .front-module-page .module-chart-canvas { height: 240px; }
    .front-module-page .module-comparison-table { margin-bottom: 15px; border: 1px solid var(--front-line); border-radius: 8px; overflow: hidden; background: var(--front-panel); }
    .front-module-page .module-comparison-title { padding: 12px 14px; color: var(--front-strong); font-weight: 700; border-bottom: 1px solid var(--front-line); }
    .front-module-page .module-comparison-table table { width: 100%; margin: 0; }
    .front-module-page .module-comparison-table th,
    .front-module-page .module-comparison-table td { text-align: left; white-space: nowrap; }
    .front-module-page .module-comparison-table td:last-child { font-weight: 700; color: var(--front-strong); }
</style>

<div
    id="frontModulePage"
    class="front-module-page {{ $pageClass }}"
    data-api="{{ $api }}"
    data-method="{{ $method }}"
    data-submit-api="{{ $submitApi }}"
    data-verification-api="{{ $verificationApi }}"
    data-verification-code-api="{{ $verificationCodeApi }}"
    data-edit-api="{{ $editApi }}"
    data-edit-method="{{ $editMethod }}"
    data-list-key="{{ $listKey }}"
    data-default-filters='@json($defaultFilters)'
    data-columns='@json($columns)'
    data-summary-fields='@json($summaryFields)'
    data-chart-groups='@json($chartGroups)'
    data-comparison-table="{{ $comparisonTable }}"
    data-row-actions='@json($rowActions)'
    data-timeline="{{ $timeline }}"
    data-per-page="20"
    data-initial-news-id="{{ (int) ($legacyNewsId ?? 0) }}"
    data-legacy-target-user-id="{{ (int) ($legacyTargetUserId ?? 0) }}"
    data-legacy-address-id="{{ (int) ($legacyAddressId ?? 0) }}"
>
    <div class="layui-card">
        <div class="layui-card-header" data-translate="{{ $titleKey }}">{{ __($titleKey) }}</div>
        <div class="layui-card-body">
            @if(!empty($filters))
                <form class="layui-form layui-form-pane module-toolbar" lay-filter="moduleSearchForm">
                    <div class="layui-row layui-col-space10">
                        @foreach($filters as $filter)
                            <div class="layui-col-md3 layui-col-sm6">
                                <div class="layui-form-item"><div class="layui-input-block">
                                        @if(($filter['type'] ?? 'text') === 'select')
                                            <select name="{{ $filter['name'] }}" class="J_moduleFilter" @if(!empty($filter['dynamicOptions'])) data-dynamic-options="{{ $filter['dynamicOptions'] }}" @endif>
                                                <option value="" data-translate="{{ $filter['placeholder'] ?? $filter['label'] }}">{{ __($filter['placeholder'] ?? $filter['label']) }}</option>
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
                                                autocomplete="off" data-translate-placeholder="{{ $filter['label'] }}" placeholder="{{ __($filter['label']) }}">
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
                                            <select
                                                name="{{ $field['name'] }}"
                                                @if(!empty($field['verify'])) lay-verify="{{ $field['verify'] }}" @endif
                                                @if(!empty($field['dynamicOptions'])) data-dynamic-options="{{ $field['dynamicOptions'] }}" @endif
                                            >
                                                <option value="" data-translate="{{ $field['placeholder'] ?? $field['label'] }}">{{ __($field['placeholder'] ?? $field['label']) }}</option>
                                                @foreach(($field['options'] ?? []) as $option)
                                                    <option value="{{ $option['value'] }}" data-translate="{{ $option['label'] }}">{{ __($option['label']) }}</option>
                                                @endforeach
                                            </select>
                                        @elseif(($field['type'] ?? 'text') === 'verification_code')
                                            <div class="layui-input-inline" style="width: calc(100% - 132px);">
                                                <input
                                                    type="text"
                                                    name="{{ $field['name'] }}"
                                                    class="layui-input"
                                                    data-translate-placeholder="{{ $field['placeholder'] ?? $field['label'] }}"
                                                    placeholder="{{ __($field['placeholder'] ?? $field['label']) }}"
                                                    autocomplete="off"
                                                    @if(!empty($field['verify'])) lay-verify="{{ $field['verify'] }}" @endif
                                                >
                                            </div>
                                            <button type="button" class="layui-btn layui-btn-primary J_cancelCodeButton" data-translate="front.send_email_code">{{ __('front.send_email_code') }}</button>
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
                                                @php
                                                    $uploadId = 'crm_upload_' . md5($field['name'] . $loop->index);
                                                    // 体积上限与后端校验保持一致：调用方未指定时沿用既有 4096KB 默认值。
                                                    $uploadMaxSize = (int) ($field['maxSize'] ?? 4096);
                                                @endphp
                                                {{-- 统一上传组件：拖拽区 + 缩略图 + 进度条 + 移除/重选 + 行内错误，
                                                     接口地址、字段名、accept 与体积上限完全沿用原有契约。 --}}
                                                <div
                                                    class="crm-upload-card crm-upload-shell"
                                                    data-upload-card="{{ $uploadId }}"
                                                    data-crm-upload="{{ $field['name'] }}"
                                                    data-upload-field="{{ $field['name'] }}"
                                                    data-upload-accept="{{ !empty($field['accept']) && strpos($field['accept'], 'image') !== false ? 'images' : 'file' }}"
                                                    data-upload-max-size="{{ $uploadMaxSize }}"
                                                    data-upload-auto="0"
                                                    data-upload-multiple="{{ !empty($field['multiple']) ? '1' : '0' }}"
                                                >
                                                    <div class="crm-upload-main">
                                                        <button type="button" class="crm-upload-action" id="{{ $uploadId }}_trigger" data-upload-trigger aria-label="{{ __($field['label']) }}">
                                                            <i data-lucide="cloud-upload" aria-hidden="true"></i>
                                                            <span class="crm-upload-drag-text" data-translate="{{ $field['label'] }}">{{ __($field['label']) }}</span>
                                                            <span class="crm-upload-drag-hint" data-translate="front.upload_choose_or_drag">{{ __('front.upload_choose_or_drag') }}</span>
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="crm-upload-clear J_crmUploadClear"
                                                            data-upload-target="{{ $uploadId }}"
                                                            data-upload-clear="{{ $field['name'] }}"
                                                            title="{{ __('front.upload_remove') }}"
                                                            aria-label="{{ __('front.upload_remove') }}"
                                                        >
                                                            <i data-lucide="x" aria-hidden="true"></i>
                                                        </button>
                                                    </div>
                                                    <input
                                                        id="{{ $uploadId }}"
                                                        type="file"
                                                        name="{{ $field['name'] }}"
                                                        class="layui-hide J_crmUploadInput"
                                                        @if(!empty($field['accept'])) accept="{{ $field['accept'] }}" @endif
                                                        @if(!empty($field['multiple'])) multiple @endif
                                                        @if(!empty($field['verify'])) lay-verify="{{ $field['verify'] }}" @endif
                                                        data-max-size="{{ $uploadMaxSize }}"
                                                    >
                                                    <div class="crm-upload-progress" data-upload-progress role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="{{ __('front.upload_uploading') }}">
                                                        <div class="crm-upload-progress-bar" data-upload-progress-bar></div>
                                                    </div>
                                                    <p class="crm-upload-hint crm-upload-status" id="{{ $uploadId }}_status" data-upload-status="{{ $field['name'] }}" data-translate="front.no_file_selected">{{ __('front.no_file_selected') }}</p>
                                                    <div class="crm-upload-list" id="{{ $uploadId }}_list"></div>
                                                    <p class="crm-field-error" data-error-for="{{ $field['name'] }}" role="alert" aria-live="assertive"></p>
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

            @if(!empty($comparisonTable))
                <div id="moduleComparisonTable"></div>
            @endif

            @if(!empty($chartGroups))
                <div class="module-chart-grid" id="moduleChartGrid">
                    @php
                        // 图表查看方式清单：语言 key 全部写成字面量，保证首屏翻译审计能静态扫描到。
                        $chartViewModes = [
                            ['type' => 'bar', 'icon' => 'chart-column', 'label' => __('front.chart_bar')],
                            ['type' => 'line', 'icon' => 'chart-line', 'label' => __('front.chart_line')],
                            ['type' => 'area', 'icon' => 'chart-area', 'label' => __('front.chart_area')],
                            ['type' => 'pie', 'icon' => 'chart-pie', 'label' => __('front.chart_pie')],
                        ];
                    @endphp
                    @foreach($chartGroups as $chart)
                        @php $chartDefaultType = $chart['defaultType'] ?? 'bar'; @endphp
                        <div class="module-chart-card">
                            <div class="module-chart-head">
                                <div class="module-chart-title" data-translate="{{ $chart['title'] }}">{{ __($chart['title']) }}</div>
                                {{-- 多种查看方式：柱状图/折线图/面积图/饼图切换按钮，与控制台保持同一交互约定和 44px 触控目标。 --}}
                                <div class="module-chart-controls" role="group" aria-label="{{ __('front.chart_view_mode') }}">
                                    @foreach($chartViewModes as $chartViewMode)
                                        <button
                                            type="button"
                                            class="module-chart-type J_moduleChartType{{ $chartViewMode['type'] === $chartDefaultType ? ' is-active' : '' }}"
                                            data-chart-target="{{ $chart['target'] }}"
                                            data-chart-type="{{ $chartViewMode['type'] }}"
                                            title="{{ $chartViewMode['label'] }}"
                                            aria-label="{{ $chartViewMode['label'] }}"
                                            aria-pressed="{{ $chartViewMode['type'] === $chartDefaultType ? 'true' : 'false' }}"
                                        >
                                            <i data-lucide="{{ $chartViewMode['icon'] }}" aria-hidden="true"></i>
                                            <span class="crm-sr-only">{{ $chartViewMode['label'] }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                            <div class="module-chart-canvas" id="{{ $chart['target'] }}"></div>
                        </div>
                    @endforeach
                </div>
            @endif

            @if($showChain)
                <div class="module-chain" id="moduleChain"></div>
            @endif

            @if(!empty($timeline))
                <div class="module-news-timeline-wrap">
                    <ul class="layui-timeline module-news-timeline" id="moduleNewsTimeline"></ul>
                </div>
                <div id="modulePager"></div>
            @elseif(!empty($columns))
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
