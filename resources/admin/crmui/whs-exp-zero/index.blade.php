{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/07/05
Time: 01:18
--}}
@extends('admin_crmui::layouts.app')

@section('title', $page['title'] ?? '')

@section('content')
@include('admin_crmui::partials.module-page', ['page' => $page])
@endsection
