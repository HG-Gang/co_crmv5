{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/18
Time: 10:32
--}}
@php
    $accountTypeChangeUrl = $accountTypeChangeUrl ?? route('legacy_user_change_account_save', [], false);
    $accountTypeChangeMethod = strtoupper($accountTypeChangeMethod ?? 'POST');
    $accountTypeButtonClass = $accountTypeButtonClass ?? 'layui-btn';
@endphp
<form
    id="accountTypeSwitch"
    class="account-type-switch"
    data-change-api="{{ $accountTypeChangeUrl }}"
    data-change-method="{{ $accountTypeChangeMethod }}"
    data-minimum-ecn-equity="3000"
    aria-labelledby="accountTypeSwitchTitle"
    aria-busy="true"
>
    @csrf
    <header class="account-type-switch__header">
        <span class="account-type-switch__icon" aria-hidden="true">
            <i data-lucide="shield-check"></i>
        </span>
        <span class="account-type-switch__heading">
            <strong id="accountTypeSwitchTitle" data-translate="front.account_type_management">{{ __('front.account_type_management') }}</strong>
            <small data-translate="front.account_type_management_desc">{{ __('front.account_type_management_desc') }}</small>
        </span>
        <span class="account-type-switch__current">
            <small data-translate="front.current_account_type">{{ __('front.current_account_type') }}</small>
            <strong id="accountTypeCurrent">--</strong>
        </span>
    </header>

    <div class="account-type-switch__workspace">
        <div class="account-type-selector" role="radiogroup" aria-label="{{ __('front.account_type_management') }}">
            <label class="account-type-option" data-account-type="0">
                <input type="radio" name="is_enc" value="0" disabled>
                <span class="account-type-option__body">
                    <span class="account-type-option__head">
                        <span class="account-type-option__name">
                            <i data-lucide="route" aria-hidden="true"></i>
                            <strong data-translate="front.stp_account">{{ __('front.stp_account') }}</strong>
                        </span>
                        <span class="account-type-option__state" data-translate="front.current_account_type">{{ __('front.current_account_type') }}</span>
                    </span>
                    <span class="account-type-option__facts">
                        <span><small data-translate="front.trading_leverage">{{ __('front.trading_leverage') }}</small><strong>1:100</strong></span>
                        <span><small data-translate="front.stop_out_level">{{ __('front.stop_out_level') }}</small><strong>100%</strong></span>
                        <span class="is-wide"><small data-translate="front.trading_products">{{ __('front.trading_products') }}</small><strong data-translate="front.stp_products">{{ __('front.stp_products') }}</strong></span>
                        <span class="is-wide"><small data-translate="front.application_criteria">{{ __('front.application_criteria') }}</small><strong data-translate="front.stp_application_criteria">{{ __('front.stp_application_criteria') }}</strong></span>
                    </span>
                </span>
            </label>

            <label class="account-type-option" data-account-type="1">
                <input type="radio" name="is_enc" value="1" disabled>
                <span class="account-type-option__body">
                    <span class="account-type-option__head">
                        <span class="account-type-option__name">
                            <i data-lucide="zap" aria-hidden="true"></i>
                            <strong data-translate="front.ecn_account">{{ __('front.ecn_account') }}</strong>
                        </span>
                        <span class="account-type-option__state" data-translate="front.current_account_type">{{ __('front.current_account_type') }}</span>
                    </span>
                    <span class="account-type-option__facts">
                        <span><small data-translate="front.trading_leverage">{{ __('front.trading_leverage') }}</small><strong>1:200</strong></span>
                        <span><small data-translate="front.stop_out_level">{{ __('front.stop_out_level') }}</small><strong>20%</strong></span>
                        <span class="is-wide"><small data-translate="front.trading_products">{{ __('front.trading_products') }}</small><strong data-translate="front.ecn_products">{{ __('front.ecn_products') }}</strong></span>
                        <span class="is-wide"><small data-translate="front.application_criteria">{{ __('front.application_criteria') }}</small><strong data-translate="front.ecn_application_criteria">{{ __('front.ecn_application_criteria') }}</strong></span>
                    </span>
                </span>
            </label>
        </div>

        <aside class="account-type-action" aria-label="{{ __('front.account_type_qualification') }}">
            <div class="account-type-action__metric">
                <span data-translate="front.current_equity">{{ __('front.current_equity') }}</span>
                <strong id="accountTypeEquity">--</strong>
            </div>
            <div class="account-type-action__metric">
                <span data-translate="front.ecn_minimum_equity">{{ __('front.ecn_minimum_equity') }}</span>
                <strong id="accountTypeMinimumEquity">$3,000.00</strong>
            </div>
            <div class="account-type-status is-loading" id="accountTypeStatus" role="status" aria-live="polite">
                <span class="account-type-status__icon" id="accountTypeStatusIcon" aria-hidden="true">
                    <i data-lucide="loader-circle"></i>
                </span>
                <span id="accountTypeStatusText" data-translate="front.account_type_loading">{{ __('front.account_type_loading') }}</span>
            </div>
            <button type="submit" class="{{ $accountTypeButtonClass }} account-type-submit" id="accountTypeSubmit" disabled>
                <i data-lucide="repeat-2" aria-hidden="true"></i>
                <span id="accountTypeSubmitText" data-translate="front.confirm_account_type_change">{{ __('front.confirm_account_type_change') }}</span>
            </button>
        </aside>
    </div>
</form>
