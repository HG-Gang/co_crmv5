{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/16
Time: 03:36
--}}
@extends('admin_crmui::layouts.app')

@section('title', $page['title'] ?? __('crmui.admin.pages.users.title'))

@section('content')
<main data-crmui-page="admin.users.customer-detail">
    @include('admin_crmui::partials.module-page', ['page' => $page])
</main>
@endsection
