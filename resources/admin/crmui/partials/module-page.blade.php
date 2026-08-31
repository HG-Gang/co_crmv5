{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/29
Time: 14:31
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
    // 批量操作声明：仅出金等显式声明 batch 的页面非空，其余页面为空数组并完全跳过勾选列与批量弹窗。
    // 表格型页面才支持批量；表单/资料型 surface 没有行集合，因此一并排除。
    $batch = (!$isFormSurface && !empty($page['batch'])) ? $page['batch'] : [];
    $hasBatch = $batch !== [];
    $hasIpDetailAction = in_array('ip_detail', array_column($rowActions, 'key'), true);
    $ipDetailColumns = [
        'login_ip', 'user_id', 'user_name', 'login_count', 'latest_login_at',
        'open_order_count', 'closed_order_count', 'total_deposit', 'total_withdraw',
    ];
    $riskViewKeys = ['profit' => 'profit', 'positions' => 'positions', 'marginCalls' => 'margin_calls', 'ipRisk' => 'ip_risk'];
    $activeView = $riskViewKeys[$page['defaultRiskMode'] ?? ''] ?? ($viewTabs[0]['key'] ?? '');
    $recordsPanelId = 'crmui-records-panel-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', $page['key'] ?? 'page');
    $giftRecipientPicker = !empty($page['giftRecipientPicker']);
    $importAction = array_values(array_filter($actions, fn ($action) => ($action['key'] ?? '') === 'import'))[0] ?? null;
    $scopeTitle = ($page['surface'] ?? 'front') === 'admin'
        ? __('crmui.common.admin_console')
        : __('crmui.common.front_console');
@endphp
<section class="crmui-page"
         data-visual-c-reference="{{ $page['key'] ?? '' }}"
         data-crmui-page="{{ $page['key'] ?? '' }}"
         data-crmui-mode="{{ $mode }}"
         data-api-url="{{ $page['apiUrl'] ?? '' }}"
         data-api-method="{{ $page['apiMethod'] ?? 'GET' }}"
         data-options-url="{{ $page['optionsUrl'] ?? '' }}"
         data-crmui-active-view="{{ $activeView }}"
         data-crmui-gift-recipient-picker="{{ $giftRecipientPicker ? '1' : '0' }}"
         data-empty-text="{{ $page['emptyText'] ?? '' }}"
         data-loading-text="{{ __('crmui.common.waiting_load') }}"
         data-error-text="{{ __('crmui.common.request_failed') }}"
         data-status-pending-text="{{ __('crmui.options.pending') }}"
         data-status-approved-text="{{ __('crmui.options.approved') }}"
         data-status-rejected-text="{{ __('crmui.options.rejected') }}">
    <header class="crmui-page-head">
        <div>
            <p class="crmui-page-scope">{{ $scopeTitle }}</p>
            <h1>{{ $page['title'] ?? '' }}</h1>
            <span>{{ $page['description'] ?? '' }}</span>
        </div>
        <div class="crmui-page-actions">
            @foreach($visibleActions as $action)
                <button class="crmui-button {{ $action['key'] === 'refresh' ? 'is-primary' : '' }}"
                        type="button"
                        data-crmui-action="{{ $action['key'] }}"
                        data-action-url="{{ $action['url'] ?? '' }}"
                        data-file-name="{{ $action['fileName'] ?? '' }}"
                        data-permission="{{ $action['permission'] ?? '' }}">
                    {{ $action['label'] }}
                </button>
            @endforeach
            @if($hasBatch)
                {{-- 批量操作入口：作用于表格勾选出的多行，与逐行操作和整页导出都不同。
                     transitions 与 targetStatuses 以 data-* 传给前端渲染器，
                     由它按勾选行的来源状态禁用非法目标项；后端仍会独立复校一次。 --}}
                <button class="crmui-button"
                        type="button"
                        data-crmui-batch-open
                        data-batch-url="{{ $batch['url'] }}"
                        data-batch-method="{{ $batch['method'] }}"
                        data-batch-record-key="{{ $batch['recordKey'] }}"
                        data-batch-source-field="{{ $batch['sourceStatusField'] }}"
                        data-batch-transitions='@json($batch['transitions'])'
                        data-batch-target-statuses='@json($batch['targetStatuses'])'
                        data-permission="{{ $batch['permission'] }}">
                    {{ $batch['label'] }}
                </button>
            @endif
        </div>
    </header>

    @if(count($viewTabs) > 0)
        <div class="crmui-tabs" data-crmui-view-tabs role="tablist" aria-label="{{ $page['title'] ?? '' }}">
            @foreach($viewTabs as $index => $tab)
                <button class="crmui-tab {{ $activeView === $tab['key'] ? 'is-active' : '' }}"
                        type="button"
                        id="crmui-tab-{{ $tab['key'] }}"
                        data-crmui-view="{{ $tab['key'] }}"
                        data-api-url="{{ $tab['apiUrl'] }}"
                        data-api-method="{{ $tab['method'] }}"
                        data-columns='@json($tab['columns'] ?? [])'
                        data-permission="{{ $tab['permission'] ?? '' }}"
                        role="tab"
                        aria-controls="{{ $recordsPanelId }}"
                        aria-selected="{{ $activeView === $tab['key'] ? 'true' : 'false' }}">
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
                  data-action-method="{{ $panel['method'] }}">
                @foreach($panel['fields'] as $field)
                    @if($field['type'] === 'hidden')
                        <input type="hidden" name="{{ $field['name'] }}">
                    @elseif($field['type'] === 'textarea')
                        <textarea class="crmui-input crmui-textarea" name="{{ $field['name'] }}" placeholder="{{ $field['placeholder'] }}"></textarea>
                    @elseif($field['type'] === 'select')
                        <select class="crmui-input" name="{{ $field['name'] }}" data-crmui-select="{{ $field['name'] }}" aria-label="{{ $field['label'] }}">
                            <option value="">{{ $field['placeholder'] }}</option>
                            @foreach($field['options'] as $option)
                                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    @elseif($field['type'] === 'file')
                        <label class="crmui-upload" data-crmui-upload="{{ $field['name'] }}" @if($field['name'] === 'avatar') data-crmui-avatar-upload data-upload-text="{{ __('crmui.actions.upload') }}" @endif>
                            <span>{{ $field['placeholder'] }}</span>
                            <input name="{{ $field['name'] }}" type="file" accept="image/*,.pdf">
                            <em data-crmui-upload-name>{{ __('crmui.common.no_file_selected') }}</em>
                            {{-- 统一上传组件：文件体积与行内错误提示由 public/js/shared/layui-upload.js 与 form-field-errors.js 统一渲染。 --}}
                            <em class="crmui-upload-size" data-crmui-upload-size></em>
                            <span class="crm-field-error" data-error-for="{{ $field['name'] }}" role="alert" aria-live="assertive"></span>
                        </label>
                    @elseif($field['type'] === 'checkbox')
                        <label class="crmui-check">
                            <input name="{{ $field['name'] }}" type="checkbox" value="1">
                            <span>{{ $field['label'] }}</span>
                        </label>
                    @else
                        <input class="crmui-input" name="{{ $field['name'] }}" type="{{ $field['type'] }}" placeholder="{{ $field['placeholder'] }}" autocomplete="off">
                    @endif
                @endforeach
                <button class="crmui-button is-primary" type="submit" data-permission="{{ $page['formPermission'] ?? '' }}">{{ __('crmui.actions.submit') }}</button>
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
                  data-action-method="{{ $page['formMethod'] ?? 'POST' }}">
                @foreach($formFields as $field)
                    @if($field['type'] === 'hidden')
                        <input type="hidden" name="{{ $field['name'] }}">
                    @elseif($field['type'] === 'textarea')
                        <textarea class="crmui-input crmui-textarea" name="{{ $field['name'] }}" placeholder="{{ $field['placeholder'] }}"></textarea>
                    @elseif($field['type'] === 'select')
                        <select class="crmui-input" name="{{ $field['name'] }}" data-crmui-select="{{ $field['name'] }}">
                            <option value="">{{ $field['placeholder'] }}</option>
                            @foreach($field['options'] as $option)
                                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    @elseif($field['type'] === 'file')
                        <label class="crmui-upload" data-crmui-upload="{{ $field['name'] }}" @if($field['name'] === 'avatar') data-crmui-avatar-upload data-upload-text="{{ __('crmui.actions.upload') }}" @endif>
                            <span>{{ $field['placeholder'] }}</span>
                            <input name="{{ $field['name'] }}" type="file" accept="image/*,.pdf">
                            <em data-crmui-upload-name>{{ __('crmui.common.no_file_selected') }}</em>
                            {{-- 统一上传组件：文件体积与行内错误提示由 public/js/shared/layui-upload.js 与 form-field-errors.js 统一渲染。 --}}
                            <em class="crmui-upload-size" data-crmui-upload-size></em>
                            <span class="crm-field-error" data-error-for="{{ $field['name'] }}" role="alert" aria-live="assertive"></span>
                        </label>
                    @elseif($field['type'] === 'checkbox')
                        <label class="crmui-check">
                            <input name="{{ $field['name'] }}" type="checkbox" value="1">
                            <span>{{ $field['label'] }}</span>
                        </label>
                    @else
                        <input class="crmui-input" name="{{ $field['name'] }}" type="{{ $field['type'] }}" placeholder="{{ $field['placeholder'] }}" autocomplete="off">
                    @endif
                @endforeach
                @if(($page['key'] ?? '') === 'front.deposit')
                    <output class="crmui-money-preview" data-crmui-money-preview>--</output>
                @endif
                @if($giftRecipientPicker)
                    <output class="crmui-money-preview" data-crmui-gift-recipient-preview>0</output>
                @endif
                <button class="crmui-button is-primary" type="submit" data-permission="{{ $page['formPermission'] ?? '' }}">{{ __('crmui.actions.submit') }}</button>
            </form>
        </section>
    @endif

    @unless($isFormSurface)
        <section class="crmui-panel"
                 id="{{ $recordsPanelId }}"
                 @if(count($viewTabs) > 0) role="tabpanel" aria-labelledby="crmui-tab-{{ $activeView }}" @endif>
            <div class="crmui-panel-head">
                <strong>{{ __('crmui.common.records') }}</strong>
                <span data-crmui-total>{{ __('crmui.common.waiting_load') }}</span>
            </div>

            <form class="crmui-filter" data-crmui-filter>
                @foreach($filters as $field)
                    @if($field['type'] === 'select')
                        <select class="crmui-input" name="{{ $field['name'] }}" data-crmui-select="{{ $field['name'] }}">
                            <option value="">{{ $field['placeholder'] }}</option>
                            @foreach($field['options'] as $option)
                                <option value="{{ $option['value'] }}" {{ (string) ($field['value'] ?? '') === (string) $option['value'] ? 'selected' : '' }}>{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    @else
                        <input class="crmui-input" name="{{ $field['name'] }}" type="{{ $field['type'] }}" value="{{ $field['value'] ?? '' }}" placeholder="{{ $field['placeholder'] }}" aria-label="{{ $field['label'] }}" autocomplete="off">
                    @endif
                @endforeach
                <button class="crmui-button is-primary" type="submit">{{ __('crmui.actions.search') }}</button>
                <button class="crmui-button" type="button" data-crmui-reset>{{ __('crmui.actions.reset') }}</button>
            </form>

            <div class="crmui-table-wrap">
                <table class="crmui-table">
                    <thead>
                        <tr>
                            @if($hasBatch)
                                {{-- 批量勾选列：只在声明了 batch 的页面出现。
                                     全选框只作用于当前页中「可批量」的行（终态行由渲染器禁用），不跨页保持选择。 --}}
                                <th class="crmui-table-select" data-crmui-select-column>
                                    <label class="crmui-check">
                                        <input type="checkbox" data-crmui-select-all
                                               aria-label="{{ __('crmui.actions.select_all') }}">
                                        <span class="crm-sr-only">{{ __('crmui.actions.select_all') }}</span>
                                    </label>
                                </th>
                            @endif
                            @foreach($columns as $column)
                                <th data-key="{{ $column['key'] }}">{{ $column['label'] }}</th>
                            @endforeach
                            @if(count($rowActions) > 0)
                                <th data-crmui-action-column>{{ __('crmui.common.operations') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody data-crmui-table-body>
                        <tr>
                            <td colspan="{{ max(count($columns) + ($hasBatch ? 1 : 0), 1) }}">{{ __('crmui.common.waiting_load') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav class="crmui-page-actions crmui-pagination"
                 data-crmui-pagination
                 aria-label="{{ __('common.pagination') }}">
                <button class="crmui-button"
                        type="button"
                        data-crmui-page-prev
                        aria-label="{{ __('common.prev_page') }}"
                        title="{{ __('common.prev_page') }}"
                        disabled>
                    <span data-lucide="chevron-left" aria-hidden="true"></span>
                </button>
                <output data-crmui-page-current aria-live="polite">1 / 1</output>
                <button class="crmui-button"
                        type="button"
                        data-crmui-page-next
                        aria-label="{{ __('common.next_page') }}"
                        title="{{ __('common.next_page') }}"
                        disabled>
                    <span data-lucide="chevron-right" aria-hidden="true"></span>
                </button>
            </nav>

            @if(count($rowActions) > 0)
                <template data-crmui-row-actions>
                    <div class="crmui-row-actions">
                        @foreach($rowActions as $action)
                            {{-- data-extra-payload：为本地行操作补充固定请求参数，典型场景是持仓汇总旧代理钻取的 searchtype。 --}}
                            <button class="crmui-row-button {{ $action['variant'] === 'danger' ? 'is-danger' : '' }}"
                                    type="button"
                                    data-crmui-row-action="{{ $action['key'] }}"
                                    data-action-url="{{ $action['url'] }}"
                                    data-action-method="{{ $action['method'] }}"
                                    data-record-key="{{ $action['recordKey'] }}"
                                    data-payload-name="{{ $action['payloadName'] }}"
                                    data-extra-payload='@json($action['extraPayload'] ?? [])'
                                    data-visible-when='@json($action['visibleWhen'] ?? [])'
                                    data-action-view="{{ $action['view'] ?? '' }}"
                                    data-permission-tree-url="{{ $action['permissionTreeUrl'] ?? '' }}"
                                    data-permission="{{ $action['permission'] ?? '' }}"
                                    data-confirm="{{ $action['confirm'] }}"
                                    data-crmui-local-modal="{{ $action['local'] ? '1' : '0' }}"
                                    data-field-rules='@json($action['fieldRules'] ?? [])'
                                    data-fields="@foreach($action['fields'] as $field)name:{{ $field['name'] }}:{{ $field['type'] }}:{{ $field['label'] }}:@foreach($field['options'] as $option){{ $option['value'] }}={{ $option['label'] }}@if(!$loop->last),@endif @endforeach @if(!$loop->last)|@endif @endforeach">
                                {{ $action['label'] }}
                            </button>
                        @endforeach
                    </div>
                </template>
            @endif
        </section>
    @endunless

    @if(!$isFormSurface && count($rowActions) > 0)
        <div class="crmui-modal" data-crmui-action-modal hidden>
            <div class="crmui-modal-backdrop" data-crmui-modal-close></div>
            <section class="crmui-modal-panel" role="dialog" aria-modal="true" aria-labelledby="crmui-action-modal-title">
                <header class="crmui-modal-head">
                    <strong id="crmui-action-modal-title" data-crmui-modal-title>{{ __('crmui.common.operations') }}</strong>
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

    @if($hasBatch)
        {{-- 批量操作弹窗：目标状态单选 + 备注。
             目标项在打开时按勾选行的来源状态禁用非法值；备注在 remarkRequired 的目标状态下必填，
             与单条拒绝共用后端同一 reason 字段与 500 字上限。 --}}
        <div class="crmui-modal" data-crmui-batch-modal hidden>
            <div class="crmui-modal-backdrop" data-crmui-batch-close></div>
            <section class="crmui-modal-panel" role="dialog" aria-modal="true" aria-labelledby="crmui-batch-modal-title">
                <header class="crmui-modal-head">
                    <strong id="crmui-batch-modal-title">{{ $batch['title'] }}</strong>
                    <button class="crmui-icon-close"
                            type="button"
                            data-crmui-batch-close
                            aria-label="{{ __('crmui.actions.cancel') }}"
                            title="{{ __('crmui.actions.cancel') }}">
                        <span data-lucide="x" aria-hidden="true"></span>
                    </button>
                </header>
                <form class="crmui-form" data-crmui-batch-form>
                    <p class="crmui-muted" data-crmui-batch-count role="status"></p>
                    <fieldset class="crmui-fieldset">
                        <legend>{{ __('admin.batch_target_status') }}</legend>
                        @foreach($batch['targetStatuses'] as $target)
                            <label class="crmui-check">
                                <input type="radio"
                                       name="target_status"
                                       value="{{ $target['value'] }}"
                                       data-remark-required="{{ $target['remarkRequired'] ? '1' : '0' }}">
                                <span>{{ $target['label'] }}</span>
                            </label>
                        @endforeach
                    </fieldset>
                    <label class="crmui-field">
                        <span>{{ __('admin.remark') }}</span>
                        <textarea name="remark"
                                  maxlength="500"
                                  data-crmui-batch-remark
                                  placeholder="{{ __('admin.remark') }}"></textarea>
                    </label>
                    <button class="crmui-button is-primary" type="submit">{{ __('crmui.actions.submit') }}</button>
                </form>
            </section>
        </div>
    @endif

    @if(!$isFormSurface && $hasIpDetailAction)
        <div class="crmui-modal" data-crmui-ip-detail-modal hidden>
            <div class="crmui-modal-backdrop" data-crmui-ip-detail-close></div>
            <section class="crmui-modal-panel crmui-modal-panel--wide"
                     role="dialog"
                     aria-modal="true"
                     aria-labelledby="crmui-ip-detail-title">
                <header class="crmui-modal-head">
                    <strong id="crmui-ip-detail-title" data-crmui-ip-detail-title>{{ __('crmui.actions.ip_detail') }}</strong>
                    <button class="crmui-icon-close"
                            type="button"
                            data-crmui-ip-detail-close
                            aria-label="{{ __('crmui.actions.cancel') }}"
                            title="{{ __('crmui.actions.cancel') }}">
                        <span data-lucide="x" aria-hidden="true"></span>
                    </button>
                </header>
                <div class="crmui-ip-detail-content"
                     data-loading-text="{{ __('crmui.common.waiting_load') }}"
                     data-empty-text="{{ __('crmui.empty.no_records') }}"
                     data-error-text="{{ __('crmui.common.request_failed') }}">
                    <p class="crmui-muted" data-crmui-ip-detail-status role="status"></p>
                    <div class="crmui-table-wrap" data-crmui-ip-detail-table hidden>
                        <table class="crmui-table">
                            <thead>
                                <tr>
                                    @foreach($ipDetailColumns as $column)
                                        <th data-key="{{ $column }}">{{ __('crmui.fields.' . $column) }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody data-crmui-ip-detail-body></tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    @endif

    @if(!$isFormSurface && $importAction)
        {{-- CSV 批量导入弹窗：共享 layui 上传组件（deferred 模式缓存文件），由 admin.js 组装 FormData 提交到导入端点。 --}}
        <div class="crmui-modal" data-crmui-import-modal hidden>
            <div class="crmui-modal-backdrop" data-crmui-import-close></div>
            <section class="crmui-modal-panel" role="dialog" aria-modal="true" aria-labelledby="crmui-import-modal-title">
                <header class="crmui-modal-head">
                    <strong id="crmui-import-modal-title">{{ __('admin.import_csv_file') }}</strong>
                    <button class="crmui-icon-close" type="button" data-crmui-import-close aria-label="{{ __('crmui.actions.cancel') }}">
                        <span data-lucide="x" aria-hidden="true"></span>
                    </button>
                </header>
                <p class="crmui-muted crm-upload-hint">{{ __('admin.import_csv_hint') }}</p>
                <div class="crm-upload-shell"
                     data-crm-upload="csv_import"
                     data-upload-field="csv_import"
                     data-upload-accept="file"
                     data-upload-exts="csv"
                     data-upload-max-size="20480"
                     data-upload-auto="0"
                     data-upload-multiple="0">
                    <button type="button" class="crmui-button crm-upload-action" data-upload-trigger aria-label="{{ __('admin.import_csv_file') }}">
                        <span data-lucide="file-up" aria-hidden="true"></span>
                        {{ __('admin.import_csv_file') }}
                    </button>
                    <button class="crmui-button" type="button" data-upload-clear="csv_import">{{ __('crmui.actions.reset') }}</button>
                    <span class="crm-upload-meta">
                        <b data-upload-name="csv_import">-</b>
                        <em data-upload-size-text="csv_import">-</em>
                        <em class="crm-upload-status" data-upload-status="csv_import">{{ __('front.no_file_selected') }}</em>
                    </span>
                    <div class="crm-upload-progress" data-upload-progress role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="{{ __('front.upload_uploading') }}"><div class="crm-upload-progress-bar" data-upload-progress-bar></div></div>
                </div>
                <button class="crmui-button is-primary" type="button" data-crmui-import-submit data-import-url="{{ $importAction['url'] }}" data-permission="{{ $importAction['permission'] ?? '' }}">
                    {{ __('admin.import_csv_start') }}
                </button>
            </section>
        </div>
    @endif
</section>
