@extends('front.layouts.app')

@section('title', __('auth.profile'))

@section('content')
<div class="card" style="max-width: 600px;">
    <h3>{{ __('auth.edit_profile') }}</h3>

    <!-- Avatar Upload -->
    <div style="margin-bottom: 24px; text-align: center;">
        <div style="position:relative;display:inline-block;">
            @if($userInfo && $userInfo->avatar)
                <img id="avatar-preview" src="{{ Storage::url($userInfo->avatar) }}" alt="avatar" style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid #f0f0f0;">
            @else
                <div id="avatar-preview" style="width:80px;height:80px;border-radius:50%;background:#e0e0e0;display:flex;align-items:center;justify-content:center;font-size:32px;color:#999;margin:0 auto;">
                    <i class="layui-icon layui-icon-user"></i>
                </div>
            @endif
        </div>
        <form id="avatar-form" style="margin-top:10px;">
            @csrf
            <input type="file" name="avatar" id="avatar-input" accept="image/*" style="display:none;">
            <button type="button" id="avatarUploadTrigger" style="padding:6px 16px;background:#18a058;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:13px;">
                {{ __('auth.upload_avatar') }}
            </button>
        </form>
    </div>

    <!-- Profile Form -->
    <form action="{{ route('front.profile.update') }}" method="POST">
        @csrf
        @method('PUT')
        <div style="margin-bottom:16px;">
            <label style="display:block;margin-bottom:4px;font-size:13px;color:#333;font-weight:500;">{{ __('auth.user_name') }}</label>
            <input type="text" name="user_name" value="{{ $userInfo->user_name ?? '' }}" required style="width:100%;height:38px;padding:0 12px;border:1px solid #d9d9d9;border-radius:6px;font-size:14px;outline:none;">
        </div>
        <div style="display:flex;gap:16px;margin-bottom:16px;">
            <div style="flex:1;">
                <label style="display:block;margin-bottom:4px;font-size:13px;color:#333;font-weight:500;">{{ __('auth.phone') }}</label>
                <input type="text" name="phone" value="{{ $userInfo->phone ?? '' }}" style="width:100%;height:38px;padding:0 12px;border:1px solid #d9d9d9;border-radius:6px;font-size:14px;outline:none;">
            </div>
            <div style="flex:1;">
                <label style="display:block;margin-bottom:4px;font-size:13px;color:#333;font-weight:500;">{{ __('auth.gender') }}</label>
                <select name="gender" style="width:100%;height:38px;padding:0 12px;border:1px solid #d9d9d9;border-radius:6px;font-size:14px;outline:none;">
                    <option value="1" {{ ($userInfo->gender ?? 1) == 1 ? 'selected' : '' }}>{{ __('auth.male') }}</option>
                    <option value="2" {{ ($userInfo->gender ?? 1) == 2 ? 'selected' : '' }}>{{ __('auth.female') }}</option>
                </select>
            </div>
        </div>
        <div style="margin-bottom:16px;">
            <label style="display:block;margin-bottom:4px;font-size:13px;color:#333;font-weight:500;">{{ __('auth.country') }}</label>
            <input type="text" name="country" value="{{ $userInfo->country ?? '' }}" style="width:100%;height:38px;padding:0 12px;border:1px solid #d9d9d9;border-radius:6px;font-size:14px;outline:none;">
        </div>
        <div style="display:flex;gap:16px;margin-bottom:16px;">
            <div style="flex:1;">
                <label style="display:block;margin-bottom:4px;font-size:13px;color:#333;font-weight:500;">{{ __('auth.city') }}</label>
                <input type="text" name="city" value="{{ $userInfo->city ?? '' }}" style="width:100%;height:38px;padding:0 12px;border:1px solid #d9d9d9;border-radius:6px;font-size:14px;outline:none;">
            </div>
            <div style="flex:1;">
                <label style="display:block;margin-bottom:4px;font-size:13px;color:#333;font-weight:500;">State</label>
                <input type="text" name="state" value="{{ $userInfo->state ?? '' }}" style="width:100%;height:38px;padding:0 12px;border:1px solid #d9d9d9;border-radius:6px;font-size:14px;outline:none;">
            </div>
        </div>
        <div style="margin-bottom:20px;">
            <label style="display:block;margin-bottom:4px;font-size:13px;color:#333;font-weight:500;">{{ __('auth.address') }}</label>
            <input type="text" name="address" value="{{ $userInfo->address ?? '' }}" style="width:100%;height:38px;padding:0 12px;border:1px solid #d9d9d9;border-radius:6px;font-size:14px;outline:none;">
        </div>
        <button type="submit" style="height:40px;background:#18a058;color:#fff;border:none;border-radius:6px;font-size:15px;cursor:pointer;padding:0 24px;">{{ __('auth.save') }}</button>
    </form>
</div>
@endsection

@section('scripts')
<script>
layui.use(['jquery', 'layer'], function() {
    var $ = layui.jquery;
    var layer = layui.layer;

    $('#avatarUploadTrigger').on('click', function() {
        $('#avatar-input').trigger('click');
    });

    $('#avatar-input').on('change', function() {
        var file = $(this)[0].files[0];
        var formData = new FormData();

        if (!file) {
            return;
        }

        formData.append('avatar', file);
        formData.append('_token', '{{ csrf_token() }}');

        $.ajax({
            url: '{{ route("front.profile.avatar") }}',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {'X-Requested-With': 'XMLHttpRequest'},
            success: function(data) {
                if (data.code === 0) {
                    if ($('#avatar-preview').is('img')) {
                        $('#avatar-preview').attr('src', data.data.url);
                    } else {
                        $('#avatar-preview').replaceWith('<img id="avatar-preview" src="' + data.data.url + '" alt="avatar" style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid #f0f0f0;">');
                    }
                    layer.msg(data.msg);
                    return;
                }
                layer.msg(data.msg || '{{ __("messages.upload_failed") }}');
            },
            error: function() {
                layer.msg('{{ __("messages.upload_failed") }}');
            }
        });
    });
});
</script>
@endsection
