{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/07/26
Time: 04:06
--}}
<div class="layui-header">
    <div class="layui-logo" data-translate="common.adminSystemName">{{ __('common.admin_system_name') }}</div>
    <ul class="layui-nav layui-layout-left">
        <li class="layui-nav-item"><a href="">{{ __('admin.dashboard') }}</a></li>
    </ul>
    <ul class="layui-nav layui-layout-right">
        <li class="layui-nav-item">
            <a href="javascript:;" title="{{ __('common.language') }}">
                <i data-lucide="languages"></i>
            </a>
            <dl class="layui-nav-child">
                <dd><a href="javascript:;" class="lang-switch" data-lang="en">
                    <span class="lang-icon">🇺🇸</span>
                    <span data-translate="common.langEn">{{ __('common.lang_en') }}</span>
                    <i class="lang-selected" data-lucide="check" style="display: none;"></i>
                </a></dd>
                <dd><a href="javascript:;" class="lang-switch" data-lang="zh-CN">
                    <span class="lang-icon">🇨🇳</span>
                    <span data-translate="common.langZh">{{ __('common.lang_zh') }}</span>
                    <i class="lang-selected" data-lucide="check" style="display: none;"></i>
                </a></dd>
            </dl>
        </li>
        <li class="layui-nav-item">
            <a href="javascript:;" title="{{ __('front.ui_style') }}">
                <i data-lucide="palette"></i>
            </a>
            <dl class="layui-nav-child">
                <dd><a href="javascript:;" class="crm-style-switch" data-style="layui">
                    <span data-translate="front.layout_classic">{{ __('front.layout_classic') }}</span>
                    <i class="style-selected" data-lucide="check" style="display: none;"></i>
                </a></dd>
                <dd><a href="javascript:;" class="crm-style-switch" data-style="crmui">
                    <span data-translate="front.layout_crmui">{{ __('front.layout_crmui') }}</span>
                    <i class="style-selected" data-lucide="check" style="display: none;"></i>
                </a></dd>
            </dl>
        </li>
        <li class="layui-nav-item">
            <a href="javascript:;">
                <i data-lucide="user"></i>
                <span>{{ auth()->user()->name ?? __('common.admin') }}</span>
            </a>
            <dl class="layui-nav-child">
                <dd><a href=""><i data-lucide="settings"></i> {{ __('front.profile') }}</a></dd>
                <dd><a href=""><i data-lucide="log-out"></i> {{ __('auth.logout') }}</a></dd>
            </dl>
        </li>
    </ul>
</div>
