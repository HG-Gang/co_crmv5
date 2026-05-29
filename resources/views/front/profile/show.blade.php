@extends('front.layouts.app')
@section('title', __('user.profile'))
@section('breadcrumb', __('user.edit_profile'))

@section('content')
<div class="page-title">{{ __('user.edit_profile') }}</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start;">
    <!-- 基本信息 -->
    <div class="card">
        <div class="card-title">{{ __('user.basic_info') }}</div>
        <form method="POST" action="{{ route('user.profile.update') }}">
            @csrf
            <div class="form-group">
                <label>{{ __('user.user_name') }}</label>
                <input type="text" name="user_name" class="form-control" value="{{ $userInfo->user_name }}" required>
            </div>
            <div class="form-group">
                <label>{{ __('user.phone_no') }}</label>
                <input type="text" name="phone_no" class="form-control" value="{{ $userInfo->phone_no }}" required>
            </div>
            <div class="form-group">
                <label>{{ __('user.gender') }}</label>
                <select name="gender" class="form-control">
                    <option value="1" {{ $userInfo->gender==1?'selected':'' }}>{{ __('user.male') }}</option>
                    <option value="2" {{ $userInfo->gender==2?'selected':'' }}>{{ __('user.female') }}</option>
                </select>
            </div>
            <div class="form-group">
                <label>{{ __('user.country') }}</label>
                <input type="text" name="country" class="form-control" value="{{ $userInfo->country }}" placeholder="{{ __('user.country') }}">
            </div>
            <div style="display:flex;gap:10px;margin-top:8px;">
                <button type="submit" class="btn-primary">{{ __('user.save') }}</button>
                <a href="{{ route('user.dashboard') }}" class="btn-secondary">{{ __('user.cancel') }}</a>
            </div>
        </form>
    </div>

    <!-- 头像上传 -->
    <div class="card">
        <div class="card-title">{{ __('user.upload_avatar') }}</div>
        <div class="upload-zone" id="avatarZone">
            @if(!empty($userInfo->avatar))
                <img src="{{ asset('storage/' . $userInfo->avatar) }}" class="upload-preview" id="avatarPreview">
            @else
                <div id="avatarPreview">
                    <div style="font-size:40px;">📷</div>
                    <div style="margin-top:8px;font-size:14px;">{{ __('user.choose_avatar') }}</div>
                    <div style="font-size:12px;color:#bbb;margin-top:4px;">{{ __('user.avatar_help') }}</div>
                </div>
            @endif
        </div>
        <input type="file" id="avatarFile" accept="image/*" style="display:none">
        <div id="uploadMsg" style="margin-top:8px;font-size:13px;color:#888;"></div>
    </div>
</div>

<!-- 账户信息（只读） -->
<div class="card">
    <div class="card-title">{{ __('user.account_info') }}</div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;">
        <div>
            <div style="font-size:12px;color:#888;margin-bottom:4px;">{{ __('user.user_id') }}</div>
            <div style="font-size:16px;font-weight:600;">{{ $userInfo->user_id }}</div>
        </div>
        <div>
            <div style="font-size:12px;color:#888;margin-bottom:4px;">{{ __('auth.email') }}</div>
            <div style="font-size:14px;">{{ $loginUser->email }}</div>
        </div>
        <div>
            <div style="font-size:12px;color:#888;margin-bottom:4px;">{{ __('user.account_type') }}</div>
            <div>
                @if($userInfo->account_type == 1)
                    <span class="badge badge-agent">{{ __('user.agent') }}</span>
                @else
                    <span class="badge badge-member">{{ __('user.member') }}</span>
                @endif
            </div>
        </div>
        <div>
            <div style="font-size:12px;color:#888;margin-bottom:4px;">{{ __('user.auth_status') }}</div>
            <div style="font-size:14px;">{{ [0=>__('user.unverified'),1=>__('user.verified'),2=>__('user.reviewing'),3=>__('user.disabled')][$userInfo->auth_status] ?? '-' }}</div>
        </div>
        @if($userInfo->account_type == 1)
        <div>
            <div style="font-size:12px;color:#888;margin-bottom:4px;">{{ __('user.parent_agent') }}</div>
            <div style="font-size:14px;">{{ $userInfo->parent_id ?: __('user.top_agent') }}</div>
        </div>
        <div>
            <div style="font-size:12px;color:#888;margin-bottom:4px;">{{ __('user.relation_chain') }}</div>
            <div style="font-size:12px;font-family:monospace;word-break:break-all;">{{ str_replace(',', ' → ', $userInfo->family_tree) ?: '-' }}</div>
        </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
layui.use(['jquery'], function() {
    var $ = layui.jquery;

    $('#avatarZone').on('click', function() {
        $('#avatarFile').trigger('click');
    });

    $('#avatarFile').on('change', function() {
        var file = $(this)[0].files[0];
        var formData = new FormData();

        if (!file) {
            return;
        }

        formData.append('avatar', file);
        formData.append('_token', $('meta[name=csrf-token]').attr('content'));
        $('#uploadMsg').text('{{ __("common.loading") }}').css('color', '#888');

        $.ajax({
            url: '{{ route("user.upload.avatar") }}',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(data) {
                if (data.code === 0) {
                    $('#avatarZone').html('<img src="' + data.url + '" class="upload-preview" id="avatarPreview">');
                    $('#uploadMsg').text(data.message).css('color', '#166534');
                    return;
                }
                $('#uploadMsg').text('{{ __("messages.upload_failed") }}').css('color', '#e03131');
            },
            error: function() {
                $('#uploadMsg').text('{{ __("messages.upload_failed") }}').css('color', '#e03131');
            }
        });
    });
});
</script>
@endpush
