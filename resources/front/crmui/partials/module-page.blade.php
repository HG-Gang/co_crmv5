{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/28
Time: 01:20
--}}
@php
    $page = $page ?? [];
    $filters = $page['filters'] ?? [];
    $columns = $page['columns'] ?? [];
    $metrics = $page['metrics'] ?? [];
    $formFields = $page['formFields'] ?? [];
    $panels = $page['panels'] ?? [];
    $actions = $page['actions'] ?? [];
    $mode = $page['mode'] ?? 'table';
    $isFormSurface = in_array($mode, ['form', 'profile'], true);
    $visibleActions = $isFormSurface
        ? array_values(array_filter($actions, fn ($action) => ($action['key'] ?? '') === 'refresh'))
        : $actions;
    $viewTabs = $page['viewTabs'] ?? [];
    $rowActions = $page['rowActions'] ?? [];
    $filterValues = $page['filterValues'] ?? [];
    $timeline = $page['timeline'] ?? '';
    $chartGroups = $page['chartGroups'] ?? [];
    $isTimeline = $timeline !== '';
    $scopeTitle = ($page['surface'] ?? 'front') === 'admin'
        ? __('crmui.common.admin_console')
        : (($page['surface'] ?? 'front') === 'big_agent' ? __('crmui.common.big_agent_console') : __('crmui.common.front_console'));
@endphp
<section class="crmui-page"
         data-visual-c-reference="{{ $page['key'] ?? '' }}"
         data-crmui-page="{{ $page['key'] ?? '' }}"
         data-crmui-mode="{{ $mode }}"
         data-api-url="{{ $page['apiUrl'] ?? '' }}"
         data-api-method="{{ $page['apiMethod'] ?? 'GET' }}"
         data-crmui-session="{{ $page['session'] ?? '' }}"
         data-crmui-legacy-response="{{ !empty($page['legacyResponse']) ? '1' : '0' }}"
         data-options-url="{{ $page['optionsUrl'] ?? '' }}"
         data-list-key="{{ $page['listKey'] ?? '' }}"
         data-page-size="{{ $page['pageSize'] ?? 15 }}"
         data-default-filters='@json($page['defaultFilters'] ?? [])'
         data-timeline="{{ $page['timeline'] ?? '' }}"
         data-chart-groups='@json($page['chartGroups'] ?? [])'
        data-empty-text="{{ $page['emptyText'] ?? '' }}">
    <header class="crmui-page-head">
        <div>
            <p class="crmui-page-scope">{{ $scopeTitle }}</p>
            <h1>{{ $page['title'] ?? '' }}</h1>
            <span>{{ $page['description'] ?? '' }}</span>
        </div>
        <div class="crmui-page-actions">
            @foreach($visibleActions as $action)
                <button class="crmui-button {{ $action['key'] === 'refresh' ? 'is-primary' : '' }}" type="button" data-crmui-action="{{ $action['key'] }}">
                    {{ $action['label'] }}
                </button>
            @endforeach
        </div>
    </header>

    @if(count($viewTabs) > 0)
        <div class="crmui-tabs" data-crmui-view-tabs>
            @foreach($viewTabs as $index => $tab)
                <button class="crmui-tab {{ $index === 0 ? 'is-active' : '' }}"
                        type="button"
                        data-crmui-view="{{ $tab['key'] }}"
                        data-api-url="{{ $tab['apiUrl'] }}"
                        data-api-method="{{ $tab['method'] }}">
                    {{ $tab['label'] }}
                </button>
            @endforeach
        </div>
    @endif

    @if(count($metrics) > 0)
        <div class="crmui-metrics">
            @foreach($metrics as $metric)
                <article class="crmui-metric" data-crmui-metric="{{ $metric['key'] }}">
                    <span>{{ $metric['label'] }}</span>
                    <strong>{{ $metric['value'] }}</strong>
                </article>
            @endforeach
        </div>
    @endif

    @foreach($panels as $panel)
        <section class="crmui-panel" data-crmui-panel="{{ $panel['key'] }}">
            <div class="crmui-panel-head">
                <strong>{{ $panel['title'] }}</strong>
                <span>{{ __('crmui.common.form_hint') }}</span>
            </div>
            <form class="crmui-form crmui-form-grid"
                  data-crmui-form
                  enctype="multipart/form-data"
                  data-action-url="{{ $panel['apiUrl'] }}"
                  data-action-method="{{ $panel['method'] }}"
                  data-verification-url="{{ $page['verificationUrl'] ?? '' }}"
                  data-verification-code-url="{{ $page['verificationCodeUrl'] ?? '' }}">
                @foreach($panel['fields'] as $field)
                    @if($field['type'] === 'hidden')
                        <input name="{{ $field['name'] }}" type="hidden" value="">
                    @elseif($field['type'] === 'textarea')
                        <textarea class="crmui-input crmui-textarea" name="{{ $field['name'] }}" placeholder="{{ $field['placeholder'] }}"></textarea>
                    @elseif($field['type'] === 'select')
                        <select class="crmui-input" name="{{ $field['name'] }}" data-crmui-select="{{ $field['name'] }}" @if(!empty($field['dynamicOptions'])) data-dynamic-options="{{ $field['dynamicOptions'] }}" @endif>
                            <option value="">{{ $field['placeholder'] }}</option>
                            @foreach($field['options'] as $option)
                                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                        @if(($page['key'] ?? '') === 'front.deposit' && $field['name'] === 'channel')
                            <div class="crmui-channel-remarks" data-crmui-channel-remarks></div>
                        @endif
                    @elseif($field['type'] === 'file')
                        <label class="crmui-upload" data-crmui-upload="{{ $field['name'] }}" @if($field['name'] === 'avatar') data-crmui-avatar-upload data-upload-text="{{ __('crmui.actions.upload') }}" @endif>
                            <span>{{ $field['placeholder'] }}</span>
                            <input name="{{ $field['name'] }}" type="file" accept="{{ $field['accept'] ?? 'image/*,.pdf' }}" @if($field['multiple'] ?? false) multiple @endif>
                            <em data-crmui-upload-name>{{ __('crmui.common.no_file_selected') }}</em>
                            {{-- 统一上传组件：文件体积与行内错误提示由 public/js/shared/layui-upload.js 与 form-field-errors.js 统一渲染。 --}}
                            <em class="crmui-upload-size" data-crmui-upload-size></em>
                            <img class="crmui-upload-preview" data-crmui-upload-preview alt="" role="button" tabindex="0" hidden>
                            <span class="crm-field-error" data-error-for="{{ $field['name'] }}" role="alert" aria-live="assertive"></span>
                        </label>
                    @elseif($field['type'] === 'verification_code')
                        <label class="crmui-code-field">
                            <span>{{ $field['label'] }}</span>
                            <input class="crmui-input" name="{{ $field['name'] }}" type="text" placeholder="{{ $field['placeholder'] }}" autocomplete="off">
                            <button class="crmui-button" type="button" data-crmui-cancel-code>{{ __('front.send_email_code') }}</button>
                        </label>
                    @elseif($field['type'] === 'checkbox')
                        <label class="crmui-check">
                            <input name="{{ $field['name'] }}" type="checkbox" value="1">
                            <span>{{ $field['label'] }}</span>
                        </label>
                    @else
                        <input class="crmui-input" name="{{ $field['name'] }}" type="{{ $field['type'] }}" value="{{ $filterValues[$field['name']] ?? '' }}" placeholder="{{ $field['placeholder'] }}" autocomplete="off">
                    @endif
                @endforeach
                <button class="crmui-button is-primary" type="submit">{{ __('crmui.actions.submit') }}</button>
            </form>
        </section>
    @endforeach

    @if(count($formFields) > 0)
        <section class="crmui-panel">
            <div class="crmui-panel-head">
                <strong>{{ __('crmui.actions.submit') }}</strong>
                <span>{{ __('crmui.common.form_hint') }}</span>
            </div>
            <form class="crmui-form crmui-form-grid"
                   data-crmui-form
                   data-action-url="{{ $page['formUrl'] ?? '' }}"
                   data-action-method="{{ $page['formMethod'] ?? 'POST' }}"
                   data-success-url="{{ $page['successUrl'] ?? '' }}"
                   data-verification-url="{{ $page['verificationUrl'] ?? '' }}"
                  data-verification-code-url="{{ $page['verificationCodeUrl'] ?? '' }}">
                @foreach($formFields as $field)
                    @if($field['type'] === 'hidden')
                        <input name="{{ $field['name'] }}" type="hidden" value="">
                    @elseif($field['type'] === 'textarea')
                        <textarea class="crmui-input crmui-textarea" name="{{ $field['name'] }}" placeholder="{{ $field['placeholder'] }}"></textarea>
                    @elseif($field['type'] === 'select')
                        <select class="crmui-input" name="{{ $field['name'] }}" data-crmui-select="{{ $field['name'] }}" @if(!empty($field['dynamicOptions'])) data-dynamic-options="{{ $field['dynamicOptions'] }}" @endif>
                            <option value="">{{ $field['placeholder'] }}</option>
                            @foreach($field['options'] as $option)
                                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                        @if(($page['key'] ?? '') === 'front.deposit' && $field['name'] === 'channel')
                            <div class="crmui-channel-remarks" data-crmui-channel-remarks></div>
                        @endif
                    @elseif($field['type'] === 'file')
                        <label class="crmui-upload" data-crmui-upload="{{ $field['name'] }}" @if($field['name'] === 'avatar') data-crmui-avatar-upload data-upload-text="{{ __('crmui.actions.upload') }}" @endif>
                            <span>{{ $field['placeholder'] }}</span>
                            <input name="{{ $field['name'] }}" type="file" accept="{{ $field['accept'] ?? 'image/*,.pdf' }}" @if($field['multiple'] ?? false) multiple @endif>
                            <em data-crmui-upload-name>{{ __('crmui.common.no_file_selected') }}</em>
                            {{-- 统一上传组件：文件体积与行内错误提示由 public/js/shared/layui-upload.js 与 form-field-errors.js 统一渲染。 --}}
                            <em class="crmui-upload-size" data-crmui-upload-size></em>
                            <img class="crmui-upload-preview" data-crmui-upload-preview alt="" role="button" tabindex="0" hidden>
                            <span class="crm-field-error" data-error-for="{{ $field['name'] }}" role="alert" aria-live="assertive"></span>
                        </label>
                    @elseif($field['type'] === 'verification_code')
                        <label class="crmui-code-field">
                            <span>{{ $field['label'] }}</span>
                            <input class="crmui-input" name="{{ $field['name'] }}" type="text" placeholder="{{ $field['placeholder'] }}" autocomplete="off">
                            <button class="crmui-button" type="button" data-crmui-cancel-code>{{ __('front.send_email_code') }}</button>
                        </label>
                    @elseif($field['type'] === 'checkbox')
                        <label class="crmui-check">
                            <input name="{{ $field['name'] }}" type="checkbox" value="1">
                            <span>{{ $field['label'] }}</span>
                        </label>
                    @else
                        <input class="crmui-input" name="{{ $field['name'] }}" type="{{ $field['type'] }}" value="{{ $filterValues[$field['name']] ?? '' }}" placeholder="{{ $field['placeholder'] }}" autocomplete="off">
                    @endif
                @endforeach
                @if(($page['key'] ?? '') === 'front.deposit')
                    <output class="crmui-money-preview" data-crmui-money-preview>--</output>
                @endif
                <button class="crmui-button is-primary" type="submit">{{ __('crmui.actions.submit') }}</button>
            </form>
        </section>
    @endif

    @if(count($chartGroups) > 0)
        <section class="crmui-panel crmui-chart-panel">
            <div class="crmui-panel-head">
                <strong>{{ __('crmui.common.analytics') }}</strong>
                <span>{{ __('crmui.common.records') }}</span>
            </div>
            <div class="crmui-chart-grid" data-crmui-chart-grid>
                @foreach($chartGroups as $chart)
                    <article class="crmui-chart-card">
                        <div class="crmui-chart-head">
                            <strong>{{ __($chart['title'] ?? 'crmui.common.analytics') }}</strong>
                            <select class="crmui-input" data-crmui-chart-type data-crmui-chart-target="{{ $chart['target'] ?? '' }}">
                                <option value="bar">{{ __('front.chart_bar') }}</option>
                                <option value="line">{{ __('front.chart_line') }}</option>
                                <option value="area">{{ __('front.chart_area') }}</option>
                                <option value="pie">{{ __('front.chart_pie') }}</option>
                            </select>
                        </div>
                        <div class="crmui-chart-canvas" id="{{ $chart['target'] ?? '' }}"></div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    @if(!$isFormSurface && $isTimeline)
        <section class="crmui-panel crmui-timeline-panel">
            <div class="crmui-panel-head">
                <strong>{{ __('crmui.common.records') }}</strong>
                <span data-crmui-total>{{ __('crmui.common.waiting_load') }}</span>
            </div>
            <div class="crmui-news-timeline" id="crmuiNewsTimeline" data-crmui-news-timeline></div>
        </section>
    @elseif(!$isFormSurface)
        <section class="crmui-panel">
            <div class="crmui-panel-head">
                <strong>{{ __('crmui.common.records') }}</strong>
                <span data-crmui-total>{{ __('crmui.common.waiting_load') }}</span>
            </div>

            <form class="crmui-filter" data-crmui-filter>
                @foreach($filters as $field)
                    @if($field['type'] === 'select')
                        <select class="crmui-input" name="{{ $field['name'] }}" data-crmui-select="{{ $field['name'] }}" @if(!empty($field['dynamicOptions'])) data-dynamic-options="{{ $field['dynamicOptions'] }}" @endif>
                            <option value="">{{ $field['placeholder'] }}</option>
                            @foreach($field['options'] as $option)
                                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    @else
                        <input class="crmui-input" name="{{ $field['name'] }}" type="{{ $field['type'] }}" value="{{ $filterValues[$field['name']] ?? '' }}" placeholder="{{ $field['placeholder'] }}" autocomplete="off">
                    @endif
                @endforeach
                <button class="crmui-button is-primary" type="submit">{{ __('crmui.actions.search') }}</button>
                <button class="crmui-button" type="button" data-crmui-reset>{{ __('crmui.actions.reset') }}</button>
            </form>

            <div class="crmui-table-wrap">
                <table class="crmui-table">
                    <thead>
                        <tr>
                            @foreach($columns as $column)
                                <th data-key="{{ $column['key'] }}"
                                    data-format="{{ $column['format'] ?? '' }}"
                                    data-action="{{ $column['action'] ?? '' }}"
                                    data-record-key="{{ $column['recordKey'] ?? $column['key'] }}">{{ $column['label'] }}</th>
                            @endforeach
                            @if(count($rowActions) > 0)
                                <th data-crmui-action-column>{{ __('crmui.common.operations') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody data-crmui-table-body>
                        <tr>
                            <td colspan="{{ max(count($columns), 1) }}">{{ __('crmui.common.waiting_load') }}</td>
                        </tr>
                    </tbody>
                    <tfoot data-crmui-table-footer hidden></tfoot>
                </table>
            </div>

            <nav class="crmui-pagination" data-crmui-pagination aria-label="{{ __('common.page_size') }}">
                <button class="crmui-icon-button" type="button" data-crmui-page-previous aria-label="{{ __('common.prev_page') }}" title="{{ __('common.prev_page') }}" disabled>
                    <i data-lucide="chevron-left" aria-hidden="true"></i>
                </button>
                <span class="crmui-page-status" aria-live="polite">
                    <strong data-crmui-page-current>1</strong>
                    <span aria-hidden="true">/</span>
                    <span data-crmui-page-count>1</span>
                </span>
                <label class="crmui-page-size">
                    <span>{{ __('common.page_size') }}</span>
                    <select class="crmui-input" data-crmui-page-size>
                        @foreach([15, 30, 50, 100] as $pageSize)
                            <option value="{{ $pageSize }}" @selected((int) ($page['pageSize'] ?? 15) === $pageSize)>{{ $pageSize }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="crmui-icon-button" type="button" data-crmui-page-next aria-label="{{ __('common.next_page') }}" title="{{ __('common.next_page') }}" disabled>
                    <i data-lucide="chevron-right" aria-hidden="true"></i>
                </button>
            </nav>

            @if(count($rowActions) > 0)
                <template data-crmui-row-actions>
                    <div class="crmui-row-actions">
                        @foreach($rowActions as $action)
                            @if(!empty($action['href']))
                                <a class="crmui-row-button" data-crmui-row-link data-crmui-href="{{ $action['href'] }}" data-record-key="{{ $action['recordKey'] }}" href="#">{{ $action['label'] }}</a>
                            @else
                            <button class="crmui-row-button {{ $action['variant'] === 'danger' ? 'is-danger' : '' }}"
                                    type="button"
                                    data-crmui-row-action="{{ $action['key'] }}"
                                    data-action-url="{{ $action['url'] }}"
                                    data-action-method="{{ $action['method'] }}"
                                    data-record-key="{{ $action['recordKey'] }}"
                                    data-payload-name="{{ $action['payloadName'] }}"
                                    data-static-payload="{{ json_encode($action['payload'] ?? [], JSON_UNESCAPED_UNICODE) }}"
                                    data-confirm="{{ $action['confirm'] }}"
                                    data-crmui-local-modal="{{ $action['local'] ? '1' : '0' }}"
                                    data-field-config='@json($action['fields'])'
                                    data-fields="@foreach($action['fields'] as $field)name:{{ $field['name'] }}:{{ $field['type'] }}:{{ $field['label'] }}:@foreach($field['options'] as $option){{ $option['value'] }}={{ $option['label'] }}@if(!$loop->last),@endif @endforeach @if(!$loop->last)|@endif @endforeach">
                                {{ $action['label'] }}
                            </button>
                            @endif
                        @endforeach
                    </div>
                </template>
            @endif
        </section>
    @endif

    @if(!$isFormSurface && (count($rowActions) > 0 || count(array_filter($columns, fn ($column) => !empty($column['action'] ?? ''))) > 0))
        <div class="crmui-modal" data-crmui-action-modal hidden>
            <div class="crmui-modal-backdrop" data-crmui-modal-close></div>
            <section class="crmui-modal-panel" role="dialog" aria-modal="true">
                <header class="crmui-modal-head">
                    <strong data-crmui-modal-title>{{ __('crmui.common.operations') }}</strong>
                    <button class="crmui-icon-close" type="button" data-crmui-modal-close>{{ __('crmui.actions.cancel') }}</button>
                </header>
                <form class="crmui-form" data-crmui-action-form>
                    <div data-crmui-modal-fields></div>
                    <pre class="crmui-record-preview" data-crmui-record-preview></pre>
                    <button class="crmui-button is-primary" type="submit">{{ __('crmui.actions.submit') }}</button>
                </form>
            </section>
        </div>
    @endif
</section>
