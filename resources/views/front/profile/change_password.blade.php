@extends('front.layouts.app')
@section('title', __('user.change_password'))
@section('breadcrumb', __('user.change_password'))

@section('content')
<div class="page-title">{{ __('user.change_password') }}</div>

<div style="max-width:480px;">
    <div class="card">
        <form method="POST" action="{{ route('user.change.password.post') }}">
            @csrf
            <div class="form-group">
                <label>当前密码</label>
                <input type="password" name="current_password" class="form-control {{ $errors->has('current_password') ? 'is-invalid' : '' }}" required>
                @error('current_password') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="form-group">
                <label>新密码</label>
                <input type="password" name="password" class="form-control {{ $errors->has('password') ? 'is-invalid' : '' }}" required placeholder="至少8位">
                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="form-group">
                <label>确认新密码</label>
                <input type="password" name="password_confirmation" class="form-control" required>
            </div>
            <div style="display:flex;gap:10px;margin-top:16px;">
                <button type="submit" class="btn-primary">{{ __('user.save') }}</button>
                <a href="{{ route('user.dashboard') }}" class="btn-secondary">{{ __('user.cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection
