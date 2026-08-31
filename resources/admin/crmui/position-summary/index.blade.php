{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/07/04
Time: 21:00
--}}
@extends('admin_crmui::layouts.app')

@section('title', $page['title'] ?? '')

@section('content')
@include('admin_crmui::partials.module-page', ['page' => $page])
@endsection
