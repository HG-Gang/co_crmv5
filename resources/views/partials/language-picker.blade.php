{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/17
Time: 22:09
--}}
@php
    $crmLanguagePickerCompact = $languagePickerCompact ?? false;
    $crmCurrentLocale = app()->getLocale() === 'en' ? 'en' : 'zh-CN';
    $crmLanguageOptions = [
        'zh-CN' => __('common.lang_zh'),
        'en' => __('common.lang_en'),
    ];
@endphp
<div class="crm-preference-menu crm-language-picker {{ $crmLanguagePickerCompact ? 'is-compact' : '' }}" data-crm-preference-menu>
    <button class="crm-preference-trigger" type="button" data-crm-preference-trigger="language"
            aria-haspopup="menu" aria-expanded="false" aria-label="{{ __('common.language') }}"
            title="{{ __('common.language') }}">
        <i data-lucide="languages" aria-hidden="true"></i>
    </button>
    <div class="crm-preference-popover" role="menu" aria-label="{{ __('common.language') }}" hidden>
        @foreach($crmLanguageOptions as $crmLocale => $crmLanguageLabel)
            @php($crmLocaleIsCurrent = $crmLocale === $crmCurrentLocale)
            <button type="button" class="crm-preference-item {{ $crmLocaleIsCurrent ? 'is-current' : '' }}"
                    role="menuitemradio" data-crm-locale="{{ $crmLocale }}"
                    aria-current="{{ $crmLocaleIsCurrent ? 'true' : 'false' }}"
                    aria-checked="{{ $crmLocaleIsCurrent ? 'true' : 'false' }}">
                <span>{{ $crmLanguageLabel }}</span>
                <i data-lucide="check" aria-hidden="true"></i>
            </button>
        @endforeach
    </div>
</div>
