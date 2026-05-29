<div class="layui-header">
    <div class="layui-logo" data-translate="common.adminSystemName">{{ __('common.admin_system_name') }}</div>
    <ul class="layui-nav layui-layout-left">
        <li class="layui-nav-item"><a href="">{{ __('admin.dashboard') }}</a></li>
    </ul>
    <ul class="layui-nav layui-layout-right">
        <li class="layui-nav-item">
            <a href="javascript:;" data-translate="common.language">{{ __('common.language') }}</a>
            <dl class="layui-nav-child">
                <dd><a href="javascript:;" class="lang-switch" data-lang="en" data-translate="common.langEn">{{ __('common.lang_en') }}</a></dd>
                <dd><a href="javascript:;" class="lang-switch" data-lang="zh-CN" data-translate="common.langZh">{{ __('common.lang_zh') }}</a></dd>
            </dl>
        </li>
        <li class="layui-nav-item">
            <a href="javascript:;">
                {{ auth()->user()->name ?? __('common.admin') }}
            </a>
            <dl class="layui-nav-child">
                <dd><a href="">{{ __('front.profile') }}</a></dd>
                <dd><a href="">{{ __('auth.logout') }}</a></dd>
            </dl>
        </li>
    </ul>
</div>
