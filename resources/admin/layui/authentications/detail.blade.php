{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/07
Time: 19:41
--}}
@extends('admin_layui::layouts.app')

@section('title', __('admin.auth_detail'))

@section('content')
<main class="auth-detail-page"
      data-auth-detail-page="1"
      data-auth-detail-mode="{{ $authMode }}"
      data-auth-detail-user-id="{{ $authUserId }}">
    <header class="auth-detail-toolbar">
        <div>
            <p class="auth-detail-eyebrow">{{ __('admin.authentications') }}</p>
            <h2>{{ __('admin.auth_detail') }}</h2>
        </div>
        <button type="button" class="layui-btn layui-btn-primary auth-detail-back" id="authDetailBack">
            <i data-lucide="arrow-left" aria-hidden="true"></i>
            <span>{{ __('common.back') }}</span>
        </button>
    </header>

    <section class="auth-detail-state auth-detail-state-loading" id="authDetailLoading" role="status">
        <i data-lucide="loader-circle" class="auth-detail-loading-icon" aria-hidden="true"></i>
        <span>{{ __('common.loading') }}</span>
    </section>

    <section class="auth-detail-state auth-detail-state-error" id="authDetailError" role="alert" hidden>
        <i data-lucide="circle-alert" aria-hidden="true"></i>
        <p id="authDetailErrorMessage">{{ __('admin.auth_detail_load_failed') }}</p>
        <button type="button" class="layui-btn layui-btn-primary layui-btn-sm" id="authDetailRetry">
            <i data-lucide="refresh-cw" aria-hidden="true"></i>
            <span>{{ __('admin.retry') }}</span>
        </button>
    </section>

    <div id="authDetailContent" hidden>
        <section class="auth-detail-summary" aria-labelledby="authDetailSummaryTitle">
            <div class="auth-detail-section-head">
                <div>
                    <p class="auth-detail-eyebrow">{{ __('admin.user_id') }} <span id="authDetailUserId">-</span></p>
                    <h3 id="authDetailSummaryTitle">{{ __('admin.auth_basic_information') }}</h3>
                </div>
                <div class="auth-detail-status-group" aria-label="{{ __('admin.review_status') }}">
                    <span class="auth-detail-status" id="authDetailIdCardStatus">-</span>
                    <span class="auth-detail-status" id="authDetailBankStatus">-</span>
                </div>
            </div>

            <dl class="auth-detail-facts">
                <div><dt>{{ __('admin.user_name') }}</dt><dd id="authDetailUserName">-</dd></div>
                <div><dt>{{ __('admin.phone') }}</dt><dd id="authDetailPhone">-</dd></div>
                <div><dt>{{ __('front.email') }}</dt><dd id="authDetailEmail">-</dd></div>
                <div><dt>{{ __('admin.account_type') }}</dt><dd id="authDetailAccountType">-</dd></div>
                <div><dt>{{ __('common.created_at') }}</dt><dd id="authDetailCreatedAt">-</dd></div>
                <div><dt>{{ __('common.updated_at') }}</dt><dd id="authDetailUpdatedAt">-</dd></div>
            </dl>
        </section>

        <section class="auth-detail-section" data-auth-detail-component="id_card" aria-labelledby="authDetailIdCardTitle">
            <div class="auth-detail-section-head">
                <div>
                    <p class="auth-detail-eyebrow">{{ __('admin.id_card_auth') }}</p>
                    <h3 id="authDetailIdCardTitle">{{ __('admin.identity_materials') }}</h3>
                </div>
            </div>

            <dl class="auth-detail-facts auth-detail-facts-compact">
                <div><dt>{{ __('admin.id_card_no') }}</dt><dd id="authDetailIdCardNo">-</dd></div>
                <div><dt>{{ __('admin.review_remark') }}</dt><dd id="authDetailIdCardRemarks">-</dd></div>
            </dl>

            <div class="auth-detail-images">
                <figure class="auth-detail-image-item">
                    <figcaption>{{ __('admin.id_card_front') }}</figcaption>
                    <a id="authDetailIdCardFrontLink" href="javascript:;" target="_blank" rel="noopener" aria-disabled="true">
                        <img id="authDetailIdCardFront" alt="{{ __('admin.id_card_front') }}" loading="lazy" hidden>
                        <span class="auth-detail-image-empty">{{ __('common.no_data') }}</span>
                    </a>
                </figure>
                <figure class="auth-detail-image-item">
                    <figcaption>{{ __('admin.id_card_back') }}</figcaption>
                    <a id="authDetailIdCardBackLink" href="javascript:;" target="_blank" rel="noopener" aria-disabled="true">
                        <img id="authDetailIdCardBack" alt="{{ __('admin.id_card_back') }}" loading="lazy" hidden>
                        <span class="auth-detail-image-empty">{{ __('common.no_data') }}</span>
                    </a>
                </figure>
            </div>
        </section>

        <section class="auth-detail-section" data-auth-detail-component="bank" aria-labelledby="authDetailBankTitle">
            <div class="auth-detail-section-head">
                <div>
                    <p class="auth-detail-eyebrow">{{ __('admin.bank_auth') }}</p>
                    <h3 id="authDetailBankTitle">{{ __('admin.bank_materials') }}</h3>
                </div>
            </div>

            <dl class="auth-detail-facts auth-detail-facts-compact">
                <div><dt>{{ __('admin.bank_no') }}</dt><dd id="authDetailBankNo">-</dd></div>
                <div><dt>{{ __('admin.bank_name') }}</dt><dd id="authDetailBankName">-</dd></div>
                <div><dt>{{ __('admin.bank_addr') }}</dt><dd id="authDetailBankAddr">-</dd></div>
                <div><dt>{{ __('admin.review_remark') }}</dt><dd id="authDetailBankRemarks">-</dd></div>
            </dl>

            <div class="auth-detail-images">
                <figure class="auth-detail-image-item">
                    <figcaption>{{ __('admin.bank_card_front') }}</figcaption>
                    <a id="authDetailBankCardFrontLink" href="javascript:;" target="_blank" rel="noopener" aria-disabled="true">
                        <img id="authDetailBankCardFront" alt="{{ __('admin.bank_card_front') }}" loading="lazy" hidden>
                        <span class="auth-detail-image-empty">{{ __('common.no_data') }}</span>
                    </a>
                </figure>
                <figure class="auth-detail-image-item">
                    <figcaption>{{ __('admin.bank_card_back') }}</figcaption>
                    <a id="authDetailBankCardBackLink" href="javascript:;" target="_blank" rel="noopener" aria-disabled="true">
                        <img id="authDetailBankCardBack" alt="{{ __('admin.bank_card_back') }}" loading="lazy" hidden>
                        <span class="auth-detail-image-empty">{{ __('common.no_data') }}</span>
                    </a>
                </figure>
            </div>
        </section>

        @if (($authMode ?? 'show') === 'auth')
            <section class="auth-detail-section auth-detail-review" id="authDetailReviewSection" aria-labelledby="authDetailReviewTitle">
                <div class="auth-detail-section-head">
                    <div>
                        <p class="auth-detail-eyebrow">{{ __('admin.review_auth') }}</p>
                        <h3 id="authDetailReviewTitle">{{ __('admin.auth_review_decision') }}</h3>
                    </div>
                </div>

                <form class="layui-form" id="authDetailReviewForm" lay-filter="authDetailReviewForm">
                    <div class="auth-detail-review-component" data-auth-detail-review-component="id_card" hidden>
                        <h4>{{ __('admin.id_card_auth') }}</h4>
                        <div class="layui-form-item">
                            <label class="layui-form-label">{{ __('admin.review_status') }}</label>
                            <div class="layui-input-block">
                                <input type="radio" name="id_card_decision" value="1" title="{{ __('admin.review_pass') }}" checked>
                                <input type="radio" name="id_card_decision" value="2" title="{{ __('admin.review_reject') }}">
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">{{ __('admin.reject_reason') }}</label>
                            <div class="layui-input-block">
                                <textarea name="id_card_reason" class="layui-textarea" maxlength="500"></textarea>
                                <p class="auth-detail-field-error" data-auth-detail-error-for="id_card_reason" hidden></p>
                            </div>
                        </div>
                    </div>

                    <div class="auth-detail-review-component" data-auth-detail-review-component="bank" hidden>
                        <h4>{{ __('admin.bank_auth') }}</h4>
                        <div class="layui-form-item">
                            <label class="layui-form-label">{{ __('admin.review_status') }}</label>
                            <div class="layui-input-block">
                                <input type="radio" name="bank_decision" value="1" title="{{ __('admin.review_pass') }}" checked>
                                <input type="radio" name="bank_decision" value="2" title="{{ __('admin.review_reject') }}">
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">{{ __('admin.reject_reason') }}</label>
                            <div class="layui-input-block">
                                <textarea name="bank_reason" class="layui-textarea" maxlength="500"></textarea>
                                <p class="auth-detail-field-error" data-auth-detail-error-for="bank_reason" hidden></p>
                            </div>
                        </div>
                    </div>

                    <p class="auth-detail-no-review" id="authDetailNoReview" hidden>{{ __('admin.auth_no_reviewable_component') }}</p>
                    <div class="auth-detail-review-actions" id="authDetailReviewActions" hidden>
                        <button class="layui-btn" lay-submit lay-filter="submitAuthDetailReview" data-permission="admin_user_review_auth">
                            <i data-lucide="check-circle-2" aria-hidden="true"></i>
                            <span>{{ __('common.submit') }}</span>
                        </button>
                    </div>
                </form>
            </section>
        @endif
    </div>
</main>
@endsection

@section('scripts')
<div hidden data-layui-page="authentications/detail"></div>
@endsection
