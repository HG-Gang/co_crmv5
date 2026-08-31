{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/22
Time: 00:02
--}}
@extends('admin_layui::layouts.app')

@section('title', __('admin.news'))

@section('content')
{{-- 新闻公告管理页面：列表读取 admin_api_newsList，新增、编辑、删除、发布切换分别走 admin_api_createNews、admin_api_updateNews、admin_api_deleteNews、admin_api_toggleNews。 --}}
{{-- data-permission 来自 permissions.slug，前端按钮显隐只做体验控制，后端 check.permission:admin 才是最终接口安全边界。 --}}
<div class="layui-card crm-admin-panel">
    <div class="layui-card-header">{{ __('admin.news') }}</div>
    <div class="layui-card-body">
        <div class="layui-btn-container">
            <button class="layui-btn layui-btn-primary" id="reloadNews">{{ __('common.refresh') }}</button>
            <button class="layui-btn" id="addNews" data-permission="admin_news_create">
                <i data-lucide="plus"></i> {{ __('common.add') }}
            </button>
        </div>
        <form class="layui-form layui-form-pane" id="newsSearchForm">
            <div class="layui-form-item">
                <div class="layui-inline">
                    <label class="crm-sr-only" for="newsTitleFilter">{{ __('admin.title') }}</label>
                    <div class="layui-input-inline">
                        {{-- title 搜索参数与 NewsController@index 保持一致，用于按 news.title 过滤新闻公告。 --}}
                        <input type="text" id="newsTitleFilter" name="title" autocomplete="off" class="layui-input" placeholder="{{ __('admin.keyword') }}" aria-label="{{ __('admin.title') }}">
                    </div>
                </div>
                <div class="layui-inline">
                    <label class="crm-sr-only" for="newsStartDate">{{ __('admin.start_date') }}</label>
                    <div class="layui-input-inline">
                        <input type="text" id="newsStartDate" name="start_date" autocomplete="off" class="layui-input" placeholder="{{ __('admin.start_date') }}" aria-label="{{ __('admin.start_date') }}">
                    </div>
                </div>
                <div class="layui-inline">
                    <label class="crm-sr-only" for="newsEndDate">{{ __('admin.end_date') }}</label>
                    <div class="layui-input-inline">
                        <input type="text" id="newsEndDate" name="end_date" autocomplete="off" class="layui-input" placeholder="{{ __('admin.end_date') }}" aria-label="{{ __('admin.end_date') }}">
                    </div>
                </div>
                <div class="layui-inline">
                    <label class="crm-sr-only" for="newsPublishedFilter">{{ __('admin.publishStatus') }}</label>
                    <div class="layui-input-inline">
                        <select id="newsPublishedFilter" name="is_published" aria-label="{{ __('admin.publishStatus') }}">
                            <option value="">{{ __('common.all') }}</option>
                            <option value="1">{{ __('admin.published') }}</option>
                            <option value="0">{{ __('admin.unpublished') }}</option>
                        </select>
                    </div>
                </div>
                <div class="layui-inline">
                    <button class="layui-btn" lay-submit lay-filter="searchNews">{{ __('common.search') }}</button>
                    <button type="reset" id="resetNewsSearch" class="layui-btn layui-btn-primary">{{ __('common.reset') }}</button>
                </div>
            </div>
        </form>
        <table class="layui-hide" id="newsTable" lay-filter="newsTable"></table>
        <script type="text/html" id="newsActions">
            <a class="layui-btn layui-btn-xs" lay-event="edit" data-permission="admin_news_update">{{ __('common.edit') }}</a>
            <a class="layui-btn layui-btn-warm layui-btn-xs" lay-event="toggle" data-permission="admin_news_toggle">{{ __('common.status') }}</a>
            <a class="layui-btn layui-btn-danger layui-btn-xs" lay-event="delete" data-permission="admin_news_delete">{{ __('common.delete') }}</a>
        </script>
    </div>
</div>

<div id="newsModal" class="admin-dialog-body" style="display: none;">
    {{-- 新闻公告表单：title/content 为发布内容主体；is_published 控制前台是否可见。 --}}
    <form class="layui-form" id="newsForm" lay-filter="newsForm">
        <input type="hidden" name="id">

        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.title') }}</label>
            <div class="layui-input-block">
                <input type="text" name="title" required lay-verify="required" autocomplete="off" class="layui-input">
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.publishStatus') }}</label>
            <div class="layui-input-block">
                <select name="is_published">
                    <option value="1">{{ __('admin.published') }}</option>
                    <option value="0">{{ __('admin.unpublished') }}</option>
                </select>
            </div>
        </div>

        <div class="layui-form-item layui-form-text">
            <label class="layui-form-label">{{ __('admin.content') }}</label>
            <div class="layui-input-block">
                <textarea name="content" required lay-verify="required" class="layui-textarea"></textarea>
            </div>
        </div>

        <div class="layui-form-item">
            <div class="layui-input-block">
                <button class="layui-btn" id="saveNewsButton" data-permission="admin_news_create" lay-submit lay-filter="saveNews">{{ __('common.save') }}</button>
            </div>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<div hidden
     data-layui-page="news/index"
     data-news-mode="{{ $newsMode ?? 'list' }}"
     data-news-info="{{ json_encode($newsInfo ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}"></div>
@endsection
