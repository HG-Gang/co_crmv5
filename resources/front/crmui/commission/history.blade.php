{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/07/04
Time: 21:00
--}}
@extends('front_crmui::layouts.app')

@section('title', $page['title'] ?? '')

@section('content')
@include('front_crmui::partials.module-page', ['page' => $page])
@endsection
