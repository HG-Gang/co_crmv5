{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/17
Time: 21:22
--}}
@extends('admin_layui::layouts.app')

@section('title', __('admin.withdrawal_detail'))

@section('content')
@php
    $withdrawStatus = (int) $withdraw->status;
    $statusLabels = [
        0 => __('admin.pending'),
        1 => __('admin.processing'),
        2 => __('admin.completed'),
        3 => __('admin.rejected'),
    ];
    $formatMoney = static function ($value): string {
        $decimal = trim((string) $value);
        if (!preg_match('/^-?[0-9]+(?:\.[0-9]{1,2})?$/D', $decimal)) {
            throw new UnexpectedValueException('Withdrawal amount must be a plain decimal string.');
        }

        [$whole, $fraction] = array_pad(explode('.', $decimal, 2), 2, '');

        return $whole . '.' . str_pad($fraction, 2, '0');
    };
@endphp
<main class="withdraw-detail-page"
      data-withdraw-detail-page="1"
      data-withdraw-record-id="{{ $withdraw->id }}"
      data-status="{{ $withdrawStatus }}">
    <header class="withdraw-detail-toolbar">
        <div>
            <p class="withdraw-detail-eyebrow">{{ __('admin.withdrawals') }}</p>
            <h2>{{ __('admin.withdrawal_detail') }}</h2>
        </div>
        <a href="{{ url('/index/admin/amount/withdraw_apply') }}"
           class="layui-btn layui-btn-primary withdraw-detail-back">
            <i data-lucide="arrow-left" aria-hidden="true"></i>
            <span>{{ __('common.back') }}</span>
        </a>
    </header>

    <section class="withdraw-detail-summary" aria-labelledby="withdrawDetailSummaryTitle">
        <div class="withdraw-detail-section-head">
            <div>
                <p class="withdraw-detail-eyebrow">{{ __('admin.user_id') }} {{ $withdraw->user_id }}</p>
                <h3 id="withdrawDetailSummaryTitle">{{ $withdraw->user_name ?: '-' }}</h3>
            </div>
            <span class="withdraw-detail-status" data-status="{{ $withdrawStatus }}">
                {{ $statusLabels[$withdrawStatus] ?? '-' }}
            </span>
        </div>
        <dl class="withdraw-detail-metrics">
            <div><dt>{{ __('admin.apply_amount') }}</dt><dd>{{ $formatMoney($withdraw->apply_amount) }}</dd></div>
            <div><dt>{{ __('admin.actual_amount') }}</dt><dd>{{ $formatMoney($withdraw->actual_amount) }}</dd></div>
            <div><dt>{{ __('admin.fee') }}</dt><dd>{{ $formatMoney($withdraw->fee) }}</dd></div>
            <div><dt>{{ __('admin.exchange_rate') }}</dt><dd>{{ $withdraw->exchange_rate }}</dd></div>
        </dl>
    </section>

    <section class="withdraw-detail-section" aria-labelledby="withdrawDetailOrderTitle">
        <div class="withdraw-detail-section-head">
            <p class="withdraw-detail-eyebrow">{{ __('admin.order_information') }}</p>
            <h3 id="withdrawDetailOrderTitle">{{ __('admin.order_information') }}</h3>
        </div>
        <dl class="withdraw-detail-facts">
            <div><dt>{{ __('admin.local_order_no') }}</dt><dd class="withdraw-detail-mono">{{ $withdraw->local_order_no ?: '-' }}</dd></div>
            <div><dt>{{ __('admin.channel_order_no') }}</dt><dd class="withdraw-detail-mono">{{ $withdraw->third_order_no ?: '-' }}</dd></div>
            <div><dt>{{ __('admin.mt4_ticket') }}</dt><dd class="withdraw-detail-mono">{{ $withdraw->mt4_ticket ?: '-' }}</dd></div>
            <div><dt>{{ __('admin.external_status') }}</dt><dd>{{ $withdraw->mt4_return_status ?: '-' }}</dd></div>
            <div><dt>{{ __('admin.funding_status') }}</dt><dd>{{ $withdraw->funding_status ?: '-' }}</dd></div>
            <div><dt>{{ __('admin.rmb_fee') }}</dt><dd>{{ $formatMoney($withdraw->rmb_fee) }}</dd></div>
        </dl>
    </section>

    <section class="withdraw-detail-section" aria-labelledby="withdrawDetailBankTitle">
        <div class="withdraw-detail-section-head">
            <p class="withdraw-detail-eyebrow">{{ __('admin.bank_snapshot') }}</p>
            <h3 id="withdrawDetailBankTitle">{{ __('admin.bank_snapshot') }}</h3>
        </div>
        <dl class="withdraw-detail-facts">
            <div><dt>{{ __('admin.bank_no') }}</dt><dd class="withdraw-detail-mono">{{ $withdraw->bank_no ?: '-' }}</dd></div>
            <div><dt>{{ __('admin.bank_name') }}</dt><dd>{{ $withdraw->bank_name ?: '-' }}</dd></div>
            <div><dt>{{ __('admin.bank_addr') }}</dt><dd>{{ $withdraw->bank_addr ?: '-' }}</dd></div>
        </dl>
    </section>

    <section class="withdraw-detail-section" aria-labelledby="withdrawDetailAuditTitle">
        <div class="withdraw-detail-section-head">
            <p class="withdraw-detail-eyebrow">{{ __('admin.audit_information') }}</p>
            <h3 id="withdrawDetailAuditTitle">{{ __('admin.audit_information') }}</h3>
        </div>
        <dl class="withdraw-detail-facts">
            <div class="withdraw-detail-wide"><dt>{{ __('admin.reject_reason') }}</dt><dd>{{ $withdraw->reject_reason ?: '-' }}</dd></div>
            <div><dt>{{ __('common.created_at') }}</dt><dd>{{ $withdraw->created_at }}</dd></div>
            <div><dt>{{ __('common.updated_at') }}</dt><dd>{{ $withdraw->updated_at }}</dd></div>
            <div><dt>{{ __('admin.created_by') }}</dt><dd>{{ $withdraw->created_by ?: '-' }}</dd></div>
            <div><dt>{{ __('admin.updated_by') }}</dt><dd>{{ $withdraw->updated_by ?: '-' }}</dd></div>
        </dl>
    </section>
</main>
@endsection
