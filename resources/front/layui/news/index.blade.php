@extends('front_layui::layouts.app')

@section('title', __('front.news_list'))
@section('breadcrumb', __('breadcrumb.front_news'))

@section('content')
    @include('front_layui::partials.module-page', [
        'titleKey' => 'front.news_list',
        'descriptionKey' => 'front.news_list_desc',
        'api' => '/api/front/newsList',
        'listKey' => 'news',
        'filters' => [
            ['name' => 'title', 'label' => 'front.news_title', 'type' => 'text'],
            ['name' => 'author_name', 'label' => 'front.news_author', 'type' => 'text'],
            ['name' => 'startdate', 'label' => 'front.date_from', 'type' => 'date'],
            ['name' => 'enddate', 'label' => 'front.date_to', 'type' => 'date'],
        ],
        'summaryFields' => [],
        'columns' => [
            ['key' => 'id', 'label' => 'common.id'],
            ['key' => 'title', 'label' => 'front.news_title'],
            ['key' => 'author_name', 'label' => 'front.news_author'],
            ['key' => 'created_at', 'label' => 'front.news_publish_time'],
        ],
    ])
@endsection

@section('scripts')
<script src="{{ asset('/js/front/layui/module-page.js') }}?v=2026052907"></script>
@endsection
