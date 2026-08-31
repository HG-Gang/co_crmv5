{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/17
Time: 22:06
--}}
@php
    $crmThemePickerId = $themePickerId ?? 'crm-theme-picker';
    $crmThemePickerCompact = $themePickerCompact ?? false;
    $crmThemePickerLabel = $themePickerLabel ?? true;
    $crmThemeOptions = config('crm_themes.themes', []);
@endphp
<div class="crm-preference-menu crm-theme-picker {{ $crmThemePickerCompact ? 'is-compact' : '' }}" data-crm-preference-menu>
    <button class="crm-preference-trigger" type="button" data-crm-preference-trigger="theme"
            aria-haspopup="menu" aria-expanded="false" aria-label="{{ __('front.skin_mode') }}"
            title="{{ __('front.skin_mode') }}">
        <i data-lucide="palette" aria-hidden="true"></i>
    </button>
    <div class="crm-preference-popover" role="menu" aria-label="{{ __('front.skin_mode') }}" hidden>
        @foreach($crmThemeOptions as $crmThemeOptionKey => $crmThemeOption)
            <button type="button" class="crm-preference-item" role="menuitemradio"
                    data-theme="{{ $crmThemeOptionKey }}" aria-current="false" aria-checked="false">
                <span>{{ __($crmThemeOption['label']) }}</span>
                <i data-lucide="check" aria-hidden="true"></i>
            </button>
        @endforeach
    </div>
    <label class="crm-sr-only" for="{{ $crmThemePickerId }}">{{ __('front.skin_mode') }}</label>
    <select id="{{ $crmThemePickerId }}" class="crm-sr-only" data-crm-skin-select aria-label="{{ __('front.skin_mode') }}">
        @foreach($crmThemeOptions as $crmThemeOptionKey => $crmThemeOption)
            <option value="{{ $crmThemeOptionKey }}">{{ __($crmThemeOption['label']) }}</option>
        @endforeach
    </select>
</div>
