{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/17
Time: 09:50
--}}
@extends('admin_crmui::layouts.app')

@section('title', $page['title'] ?? '')

@section('content')
@php
    $authMode = $page['authenticationMode'] ?? 'show';
    $detailFields = [
        'user_id', 'user_name', 'phone', 'email', 'account_type', 'auth_status',
        'id_card_no', 'id_card_status', 'id_card_remarks',
        'bank_no', 'bank_name', 'bank_addr', 'bank_status', 'bank_remarks',
        'created_at', 'updated_at',
    ];
    $images = [
        'id_card_front_url' => 'id_card_front',
        'id_card_back_url' => 'id_card_back',
        'bank_card_img_url' => 'bank_card_img',
        'bank_card_back_img_url' => 'bank_card_img_back',
    ];
@endphp
<section class="crmui-page crmui-auth-detail"
         data-visual-c-reference="admin.authentication_detail"
         data-crmui-page="admin.authentication_detail"
         data-crmui-auth-detail="1"
         data-crmui-auth-user-id="{{ $page['authenticationUserId'] ?? '' }}"
         data-crmui-auth-mode="{{ $authMode }}"
         data-api-url="{{ $page['apiUrl'] ?? '' }}"
         data-review-url="{{ $page['reviewUrl'] ?? '' }}"
         data-no-reviewable-text="{{ __('crmui.auth_detail.no_reviewable') }}">
    <header class="crmui-page-head">
        <div>
            <p class="crmui-page-scope">{{ __('crmui.common.admin_console') }}</p>
            <h1>{{ $page['title'] ?? '' }}</h1>
            <span>{{ $page['description'] ?? '' }}</span>
        </div>
    </header>

    <section class="crmui-auth-state" data-crmui-auth-state="loading">
        <i data-lucide="loader-circle" class="crmui-auth-loading-icon" aria-hidden="true"></i>
        <span>{{ __('crmui.auth_detail.loading') }}</span>
    </section>

    <section class="crmui-auth-state is-error" data-crmui-auth-state="error" hidden>
        <i data-lucide="circle-alert" aria-hidden="true"></i>
        <span data-crmui-auth-error>{{ __('crmui.auth_detail.load_error') }}</span>
        <button class="crmui-button" type="button" data-crmui-auth-retry>
            <i data-lucide="refresh-cw" aria-hidden="true"></i>
            {{ __('crmui.auth_detail.retry') }}
        </button>
    </section>

    <section class="crmui-auth-state" data-crmui-auth-state="empty" hidden>
        <i data-lucide="file-x" aria-hidden="true"></i>
        <span>{{ __('crmui.auth_detail.empty') }}</span>
    </section>

    <div data-crmui-auth-state="content" hidden>
        <section class="crmui-panel">
            <div class="crmui-panel-head">
                <strong>{{ __('crmui.auth_detail.profile') }}</strong>
            </div>
            <dl class="crmui-auth-detail-grid">
                @foreach($detailFields as $field)
                    <div>
                        <dt>{{ __('crmui.fields.' . $field) }}</dt>
                        <dd data-crmui-auth-field="{{ $field }}">--</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <section class="crmui-panel">
            <div class="crmui-panel-head">
                <strong>{{ __('crmui.auth_detail.documents') }}</strong>
            </div>
            <div class="crmui-auth-image-grid">
                @foreach($images as $field => $label)
                    <figure>
                        <figcaption>{{ __('crmui.fields.' . $label) }}</figcaption>
                        <img data-crmui-auth-image="{{ $field }}" alt="{{ __('crmui.fields.' . $label) }}" hidden>
                        <span data-crmui-auth-image-empty="{{ $field }}">{{ __('crmui.auth_detail.no_image') }}</span>
                    </figure>
                @endforeach
            </div>
        </section>

        @if($authMode === 'auth')
            <section class="crmui-panel crmui-auth-review">
                <div class="crmui-panel-head">
                    <strong>{{ __('crmui.auth_detail.review') }}</strong>
                </div>
                <p class="crmui-auth-review-empty" data-crmui-auth-review-empty hidden>{{ __('crmui.auth_detail.no_reviewable') }}</p>
                <form class="crmui-form crmui-form-grid" data-crmui-auth-review-form hidden>
                    <fieldset data-crmui-auth-review-component="id_card" hidden>
                        <legend>{{ __('crmui.panels.identity_audit') }}</legend>
                        <label>
                            <span>{{ __('crmui.fields.id_card_decision') }}</span>
                            <select class="crmui-input" name="id_card_decision" data-label="{{ __('crmui.fields.id_card_decision') }}">
                                <option value="">{{ __('crmui.fields.id_card_decision') }}</option>
                                <option value="1">{{ __('crmui.options.approved') }}</option>
                                <option value="2">{{ __('crmui.options.rejected') }}</option>
                            </select>
                        </label>
                        <label>
                            <span>{{ __('crmui.fields.id_card_reason') }}</span>
                            <textarea class="crmui-input crmui-textarea" name="id_card_reason" maxlength="500" data-label="{{ __('crmui.fields.id_card_reason') }}"></textarea>
                        </label>
                    </fieldset>
                    <fieldset data-crmui-auth-review-component="bank" hidden>
                        <legend>{{ __('crmui.panels.bank_card') }}</legend>
                        <label>
                            <span>{{ __('crmui.fields.bank_decision') }}</span>
                            <select class="crmui-input" name="bank_decision" data-label="{{ __('crmui.fields.bank_decision') }}">
                                <option value="">{{ __('crmui.fields.bank_decision') }}</option>
                                <option value="1">{{ __('crmui.options.approved') }}</option>
                                <option value="2">{{ __('crmui.options.rejected') }}</option>
                            </select>
                        </label>
                        <label>
                            <span>{{ __('crmui.fields.bank_reason') }}</span>
                            <textarea class="crmui-input crmui-textarea" name="bank_reason" maxlength="500" data-label="{{ __('crmui.fields.bank_reason') }}"></textarea>
                        </label>
                    </fieldset>
                    <button class="crmui-button is-primary" type="submit" data-permission="admin_user_review_auth">
                        <i data-lucide="badge-check" aria-hidden="true"></i>
                        {{ __('crmui.auth_detail.submit_review') }}
                    </button>
                </form>
            </section>
        @endif
    </div>
</section>
@endsection
