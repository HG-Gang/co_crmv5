{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/16
Time: 02:28
--}}
@php
    $voucher = $voucher ?? null;
    $voucherImages = $voucherImages ?? [];
    $voucherUser = $voucher && $voucher->relationLoaded('user') ? $voucher->user : null;
    $reviewStatus = $voucher ? (int) $voucher->review_status : 0;
@endphp

@extends('admin_layui::layouts.app')

@section('title', __('admin.voucher_detail'))

@section('content')
<main class="voucher-detail-page"
      data-voucher-detail-page="1"
      data-voucher-id="{{ $voucher?->id }}"
      data-voucher-user-id="{{ $voucher?->user_id }}"
      data-voucher-review-status="{{ $reviewStatus }}">
    <header class="voucher-detail-toolbar">
        <div>
            <p class="voucher-detail-eyebrow">{{ __('admin.vouchers') }}</p>
            <h2>{{ __('admin.voucher_detail') }}</h2>
        </div>
        <button type="button" class="layui-btn layui-btn-primary" id="voucherDetailBack">
            <i data-lucide="arrow-left" aria-hidden="true"></i>
            <span>{{ __('common.back') }}</span>
        </button>
    </header>

    @if (!$voucher)
        <section class="voucher-detail-empty" role="alert">{{ __('admin.voucher_not_found_or_processed') }}</section>
    @else
        <section class="voucher-detail-summary">
            <div class="voucher-detail-status-row">
                <span class="voucher-detail-eyebrow">{{ __('admin.user_id') }} {{ $voucher->user_id }}</span>
                <span class="voucher-detail-status" data-status="{{ $reviewStatus }}">
                    @switch($reviewStatus)
                        @case(1){{ __('admin.approved') }}@break
                        @case(2){{ __('admin.rejected') }}@break
                        @default{{ __('admin.pending') }}
                    @endswitch
                </span>
            </div>
            <dl class="voucher-detail-facts">
                <div><dt>{{ __('admin.user_name') }}</dt><dd>{{ $voucherUser?->user_name ?? '-' }}</dd></div>
                <div><dt>{{ __('admin.voucher_remarks') }}</dt><dd>{{ $voucher->remarks ?: '-' }}</dd></div>
                <div><dt>{{ __('admin.review_remark') }}</dt><dd>{{ $voucher->review_message ?: '-' }}</dd></div>
                <div><dt>{{ __('common.created_at') }}</dt><dd>{{ $voucher->created_at }}</dd></div>
            </dl>
        </section>

        <section class="voucher-detail-section" aria-labelledby="voucherImagesTitle">
            <div class="voucher-detail-section-head">
                <p class="voucher-detail-eyebrow">{{ __('front.voucher_images') }}</p>
                <h3 id="voucherImagesTitle">{{ __('front.voucher_images') }}</h3>
            </div>
            <div class="voucher-detail-images">
                @forelse($voucherImages as $image)
                    @php
                        $imageUrl = preg_match('/^https?:\/\//i', $image) ? $image : '/storage/' . ltrim($image, '/');
                    @endphp
                    <a href="{{ $imageUrl }}" target="_blank" rel="noopener">
                        <img src="{{ $imageUrl }}" alt="{{ __('front.voucher_images') }}" loading="lazy">
                    </a>
                @empty
                    <p class="voucher-detail-empty">{{ __('common.no_data') }}</p>
                @endforelse
            </div>
        </section>

        @if ($reviewStatus === 0)
            <section class="voucher-detail-section voucher-detail-review" aria-labelledby="voucherReviewTitle">
                <div class="voucher-detail-section-head">
                    <p class="voucher-detail-eyebrow">{{ __('admin.review_status') }}</p>
                    <h3 id="voucherReviewTitle">{{ __('admin.review_auth') }}</h3>
                </div>
                <form class="layui-form" id="voucherDetailReviewForm" lay-filter="voucherDetailReview">
                    <input type="hidden" name="recId" value="{{ $voucher->id }}">
                    <input type="hidden" name="userId" value="{{ $voucher->user_id }}">
                    <div class="layui-form-item">
                        <label class="layui-form-label">{{ __('admin.review_status') }}</label>
                        <div class="layui-input-block">
                            <input type="radio" name="reviewstatus" value="1" title="{{ __('admin.approve') }}" checked>
                            <input type="radio" name="reviewstatus" value="2" title="{{ __('admin.reject') }}">
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">{{ __('admin.review_remark') }}</label>
                        <div class="layui-input-block">
                            <textarea name="reviewmsg" class="layui-textarea" maxlength="2000"></textarea>
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <div class="layui-input-block">
                            <button type="submit" class="layui-btn" id="voucherDetailSubmit" lay-submit lay-filter="voucherDetailReview">{{ __('common.submit') }}</button>
                        </div>
                    </div>
                </form>
            </section>
        @endif
    @endif
</main>
@endsection

@section('scripts')
<div hidden data-layui-page="vouchers/detail"></div>
@endsection
