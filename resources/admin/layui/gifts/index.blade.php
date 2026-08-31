{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/21
Time: 23:51
--}}
@extends('admin_layui::layouts.app')

@section('title', __('admin.gifts'))

@section('content')
@php
    $giftPageMode = in_array($giftPageMode ?? 'all', ['all', 'send', 'shipments'], true)
        ? ($giftPageMode ?? 'all')
        : 'all';
@endphp
{{-- 礼品后台页面：发货列表、可发放地址列表和发放弹窗共用同一 Blade 页面，真实数据由后台 API 按 permissions.api_route 鉴权后返回。 --}}
<div class="crm-admin-workbench" data-gift-page-mode="{{ $giftPageMode }}">
    <div class="crm-page-head">
        <div>
            <h1>{{ __('admin.gifts') }}</h1>
            <p>{{ __('admin.gifts_desc') }}</p>
        </div>
        @if(in_array($giftPageMode, ['all', 'send'], true))
            <button class="layui-btn" id="openSendGift" data-permission="admin_gift_send">
                <i data-lucide="send"></i>{{ __('admin.send_gift') }}
            </button>
        @endif
        @if($giftPageMode === 'all')
            <button class="layui-btn layui-btn-normal" id="openGiftItemForm" data-permission="admin_gift_item_create">
                <i data-lucide="plus"></i>{{ __('admin.gift_item_create') }}
            </button>
        @endif
    </div>

    @if($giftPageMode === 'all')
    <section class="layui-card crm-admin-panel" id="giftItemsSection" aria-labelledby="giftItemsHeading">
        <div class="layui-card-header" id="giftItemsHeading">{{ __('admin.gift_items') }}</div>
        <div class="layui-card-body">
            <form class="layui-form layui-form-pane" id="giftItemSearchForm">
                <div class="layui-form-item">
                    <div class="layui-inline"><div class="layui-input-inline">
                            <input type="text" name="name" autocomplete="off" class="layui-input" placeholder="{{ __('admin.gift_name') }}">
                        </div>
                    </div>
                    <div class="layui-inline"><div class="layui-input-inline">
                            <input type="number" name="points_cost" min="0" autocomplete="off" class="layui-input" placeholder="{{ __('admin.points_cost') }}">
                        </div>
                    </div>
                    <div class="layui-inline"><div class="layui-input-inline">
                            <select name="status">
                                <option value="">{{ __('admin.status') }}</option>
                                <option value="1">{{ __('common.enabled') }}</option>
                                <option value="0">{{ __('common.disabled') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="layui-inline">
                        <button class="layui-btn" lay-submit lay-filter="searchGiftItem">
                            <i data-lucide="search"></i>{{ __('common.search') }}
                        </button>
                        <button type="reset" class="layui-btn layui-btn-primary">{{ __('common.reset') }}</button>
                    </div>
                </div>
            </form>

            <table class="layui-hide" id="giftItemTable" aria-labelledby="giftItemsHeading" lay-filter="giftItemTable"></table>
            <script type="text/html" id="giftItemActions">
                <button class="layui-btn layui-btn-xs" lay-event="editGiftItem" data-permission="admin_gift_item_update">{{ __('common.edit') }}</button>
                <button class="layui-btn layui-btn-danger layui-btn-xs" lay-event="deleteGiftItem" data-permission="admin_gift_item_delete">{{ __('common.delete') }}</button>
            </script>
        </div>
    </section>
    @endif

    @if(in_array($giftPageMode, ['all', 'shipments'], true))
    <section class="layui-card crm-admin-panel" id="giftShipmentsSection" aria-labelledby="giftShipmentsHeading">
        <div class="layui-card-header" id="giftShipmentsHeading">{{ __('admin.gift_shipments') }}</div>
        <div class="layui-card-body">
            <form class="layui-form layui-form-pane" id="giftShipmentSearchForm">
                <div class="layui-form-item">
                    <div class="layui-inline"><div class="layui-input-inline">
                            {{-- user_id：业务用户 ID，对应 gift_shipments.user_id，用于查询某个用户的礼品发货记录。 --}}
                            <input type="text" name="user_id" autocomplete="off" class="layui-input" placeholder="{{ __('admin.user_id') }}">
                        </div>
                    </div>
                    <div class="layui-inline"><div class="layui-input-inline">
                            {{-- gift_name：礼品名称，对应 gift_shipments.gift_name，后端按 LIKE 模糊筛选。 --}}
                            <input type="text" name="gift_name" autocomplete="off" class="layui-input" placeholder="{{ __('admin.gift_name') }}">
                        </div>
                    </div>
                    <div class="layui-inline"><div class="layui-input-inline">
                            {{-- recipient_name：收件人名称，对应 gift_shipments.recipient_name。 --}}
                            <input type="text" name="recipient_name" autocomplete="off" class="layui-input" placeholder="{{ __('admin.recipient_name') }}">
                        </div>
                    </div>
                    <div class="layui-inline"><div class="layui-input-inline">
                            <input type="date" name="start_date" autocomplete="off" class="layui-input" placeholder="{{ __('common.start_date') }}" aria-label="{{ __('common.start_date') }}">
                        </div>
                    </div>
                    <div class="layui-inline"><div class="layui-input-inline">
                            <input type="date" name="end_date" autocomplete="off" class="layui-input" placeholder="{{ __('common.end_date') }}" aria-label="{{ __('common.end_date') }}">
                        </div>
                    </div>
                    <div class="layui-inline">
                        <button class="layui-btn" lay-submit lay-filter="searchGiftShipment">
                            <i data-lucide="search"></i>{{ __('common.search') }}
                        </button>
                        <button type="reset" class="layui-btn layui-btn-primary">{{ __('common.reset') }}</button>
                        <button type="button" class="layui-btn" id="exportGiftShipments" data-permission="admin_gift_export">{{ __('common.export') }}</button>
                    </div>
                </div>
            </form>

            <table class="layui-hide" id="giftShipmentTable" aria-labelledby="giftShipmentsHeading" lay-filter="giftShipmentTable"></table>
            <script type="text/html" id="giftShipmentActions">
                <button class="layui-btn layui-btn-xs" lay-event="updateGiftShipment" data-permission="admin_gift_update_shipment">
                    {{ __('admin.update_gift_shipment') }}
                </button>
            </script>
        </div>
    </section>
    @endif

    @if(in_array($giftPageMode, ['all', 'send'], true))
    <section class="layui-card crm-admin-panel" id="giftAddressesSection" aria-labelledby="giftAddressesHeading">
        <div class="layui-card-header" id="giftAddressesHeading">{{ __('admin.gift_addresses') }}</div>
        <div class="layui-card-body">
            <form class="layui-form layui-form-pane" id="giftAddressSearchForm">
                <div class="layui-form-item">
                    <div class="layui-inline"><div class="layui-input-inline">
                            {{-- user_id：业务用户 ID，对应 user_addresses.user_id，用于定位可发放礼品用户地址。 --}}
                            <input type="text" name="user_id" autocomplete="off" class="layui-input" placeholder="{{ __('admin.user_id') }}">
                        </div>
                    </div>
                    <div class="layui-inline"><div class="layui-input-inline">
                        {{-- recipient_phone：收件人手机号，对应 user_addresses.recipient_phone。 --}}
                        <input type="text" name="recipient_phone" autocomplete="off" class="layui-input" placeholder="{{ __('admin.recipient_phone') }}">
                    </div>
                    </div>
                    <div class="layui-inline"><div class="layui-input-inline">
                        {{-- recipient_name：收件人姓名，对应 user_addresses.recipient_name，支持后端模糊匹配。 --}}
                        <input type="text" name="recipient_name" autocomplete="off" class="layui-input" placeholder="{{ __('admin.recipient_name') }}">
                    </div>
                    </div>
                    <div class="layui-inline"><div class="layui-input-inline">
                        {{-- is_default：默认地址标记，空值查询全部地址，1/0 分别查询默认/非默认地址。 --}}
                        <select name="is_default">
                            <option value="">{{ __('admin.is_default') }}</option>
                            <option value="1" {{ $giftPageMode === 'send' ? 'selected' : '' }}>{{ __('common.yes') }}</option>
                            <option value="0">{{ __('common.no') }}</option>
                        </select>
                    </div>
                    </div>
                    <div class="layui-inline">
                        <button class="layui-btn layui-btn-primary" lay-submit lay-filter="searchGiftAddress">
                            <i data-lucide="search"></i>{{ __('common.search') }}
                        </button>
                        <button type="reset" class="layui-btn layui-btn-primary">{{ __('common.reset') }}</button>
                    </div>
                </div>
            </form>

            <table class="layui-hide" id="giftAddressTable" aria-labelledby="giftAddressesHeading" lay-filter="giftAddressTable"></table>
        </div>
    </section>
    @endif
</div>

@if($giftPageMode === 'all')
<div id="giftItemModal" class="admin-dialog-body" style="display: none;">
    <form class="layui-form" id="giftItemForm" lay-filter="giftItemForm">
        <input type="hidden" name="id">
        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.gift_name') }}</label>
            <div class="layui-input-block">
                <input type="text" name="name" required lay-verify="required" class="layui-input">
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.description') }}</label>
            <div class="layui-input-block">
                <textarea name="description" class="layui-textarea"></textarea>
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.points_cost') }}</label>
            <div class="layui-input-block">
                <input type="number" name="points_cost" min="0" required lay-verify="required|number" class="layui-input">
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.stock_quantity') }}</label>
            <div class="layui-input-block">
                <input type="number" name="stock_quantity" min="0" required lay-verify="required|number" class="layui-input">
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.status') }}</label>
            <div class="layui-input-block">
                <select name="status" required lay-verify="required">
                    <option value="1">{{ __('common.enabled') }}</option>
                    <option value="0">{{ __('common.disabled') }}</option>
                </select>
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.image_url') }}</label>
            <div class="layui-input-block">
                <input type="text" name="image_url" class="layui-input">
            </div>
        </div>
        <div class="layui-form-item">
            <div class="layui-input-block">
                <button class="layui-btn" lay-submit lay-filter="submitGiftItemForm">{{ __('common.submit') }}</button>
            </div>
        </div>
    </form>
</div>
@endif

@if(in_array($giftPageMode, ['all', 'send'], true))
<div id="sendGiftModal" class="admin-dialog-body" style="display: none;">
    {{-- sendGiftForm：发放礼品表单；address_payload 由 JS 根据地址表格勾选项生成 recipients 数组后提交。 --}}
    <form class="layui-form" id="sendGiftForm" lay-filter="sendGiftForm">
        <input type="hidden" name="address_payload">
        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.sender_name') }}</label>
            <div class="layui-input-block">
                <input type="text" name="sender_name" required lay-verify="required" class="layui-input">
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.gift_name') }}</label>
            <div class="layui-input-block">
                <input type="text" name="gift_name" required lay-verify="required" class="layui-input">
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.gift_quantity') }}</label>
            <div class="layui-input-block">
                <input type="number" name="gift_quantity" min="1" value="1" required lay-verify="required|number" class="layui-input">
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.tracking_number') }}</label>
            <div class="layui-input-block">
                <input type="text" name="tracking_number" class="layui-input">
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.remark') }}</label>
            <div class="layui-input-block">
                <textarea name="remark" class="layui-textarea"></textarea>
            </div>
        </div>
        <div class="layui-form-item">
            <div class="layui-input-block">
                <button class="layui-btn" lay-submit lay-filter="submitSendGift">{{ __('common.submit') }}</button>
            </div>
        </div>
    </form>
</div>
@endif

@if(in_array($giftPageMode, ['all', 'shipments'], true))
<div id="updateGiftShipmentModal" class="admin-dialog-body" style="display: none;">
    <form class="layui-form" id="updateGiftShipmentForm" lay-filter="updateGiftShipmentForm">
        <input type="hidden" name="id">
        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.status') }}</label>
            <div class="layui-input-block">
                <select name="status" required lay-verify="required">
                    <option value="0">{{ __('admin.gift_status_pending') }}</option>
                    <option value="1">{{ __('admin.gift_status_shipped') }}</option>
                    <option value="2">{{ __('admin.gift_status_in_transit') }}</option>
                    <option value="3">{{ __('admin.gift_status_delivered') }}</option>
                    <option value="4">{{ __('admin.gift_status_exception') }}</option>
                </select>
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.tracking_number') }}</label>
            <div class="layui-input-block">
                <input type="text" name="tracking_number" class="layui-input">
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.remark') }}</label>
            <div class="layui-input-block">
                <textarea name="remark" class="layui-textarea"></textarea>
            </div>
        </div>
        <div class="layui-form-item">
            <div class="layui-input-block">
                <button class="layui-btn" lay-submit lay-filter="submitUpdateGiftShipment">{{ __('common.submit') }}</button>
            </div>
        </div>
    </form>
</div>
@endif
@endsection

@section('scripts')
<div hidden data-layui-page="gifts/index"></div>
@endsection
