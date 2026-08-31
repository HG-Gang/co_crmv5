{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/17
Time: 01:33
--}}
﻿@extends('front_layui::layouts.app')

@section('title', __('front.news_list'))
@section('breadcrumb', __('breadcrumb.front_news'))

@section('content')
    @include('front_layui::partials.module-page', [
        'pageClass' => 'news-timeline-module',
        'timeline' => 'news',
        'titleKey' => 'front.news_list',
        'descriptionKey' => 'front.news_list_desc',
        'api' => '/api/front/news',
        'method' => 'GET',
        'listKey' => 'news',
        'defaultFilters' => !empty($legacyNewsId) ? ['news_id' => (int) $legacyNewsId] : [],
        'filters' => [
            ['name' => 'startdate', 'label' => 'front.date_from', 'type' => 'date'],
            ['name' => 'enddate', 'label' => 'front.date_to', 'type' => 'date'],
        ],
        'summaryFields' => [],
        'columns' => [
            ['key' => 'news_id', 'label' => 'common.id'],
            ['key' => 'news_title', 'label' => 'front.news_title'],
            ['key' => 'rec_crt_date', 'label' => 'front.news_publish_time'],
        ],
    ])
@endsection

@section('scripts')
<script src="{{ asset('/js/apps/front/layui/module-page.js') }}?v=2026060701"></script>
@endsection
