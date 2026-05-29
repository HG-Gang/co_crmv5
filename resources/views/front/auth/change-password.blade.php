@extends('front.layouts.app')

@section('title', __('auth.change_password'))

@section('content')
<div class="card" style="max-width: 500px;">
    <h3>{{ __('auth.change_password') }}</h3>
    <form action="{{ route('front.password.update') }}" method="POST">
        @csrf
        <div class="form-group" style="margin-bottom:16px;">
            <label style="display:block;margin-bottom:4px;font-size:13px;color:#333;">{{ __('auth.old_password') }}</label>
            <input type="password" name="old_password" required style="width:100%;height:38px;padding:0 12px;border:1px solid #d9d9d9;border-radius:6px;font-size:14px;outline:none;">
        </div>
        <div class="form-group" style="margin-bottom:16px;">
            <label style="display:block;margin-bottom:4px;font-size:13px;color:#333;">{{ __('auth.new_password') }}</label>
            <input type="password" name="password" required minlength="6" style="width:100%;height:38px;padding:0 12px;border:1px solid #d9d9d9;border-radius:6px;font-size:14px;outline:none;">
        </div>
        <div class="form-group" style="margin-bottom:20px;">
            <label style="display:block;margin-bottom:4px;font-size:13px;color:#333;">{{ __('auth.new_password_confirm') }}</label>
            <input type="password" name="password_confirmation" required style="width:100%;height:38px;padding:0 12px;border:1px solid #d9d9d9;border-radius:6px;font-size:14px;outline:none;">
        </div>
        <button type="submit" style="height:40px;background:#18a058;color:#fff;border:none;border-radius:6px;font-size:15px;cursor:pointer;padding:0 24px;">{{ __('auth.submit') }}</button>
    </form>
</div>
@endsection