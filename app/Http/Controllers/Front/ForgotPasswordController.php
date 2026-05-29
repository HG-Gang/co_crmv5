<?php

namespace App\Http\Controllers\Front;

use App\Constants\ResponseCode;
use App\Models\UserLogin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ForgotPasswordController extends FrontBaseController
{
    public function sendResetCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $login = UserLogin::where('email', $request->input('email'))->first();
        if (!$login) {
            return $this->error('auth.user_not_found', ResponseCode::USER_NOT_FOUND);
        }

        $code = (string) random_int(100000, 999999);
        Cache::put('front_reset_code:' . strtolower($login->email), $code, 600);

        return $this->success([
            'email' => $login->email,
            'debug_code' => app()->environment('production') ? '' : $code,
        ], 'auth.reset_code_sent', ResponseCode::SUCCESS);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'code' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $email = strtolower(trim((string) $request->input('email')));
        $cached = Cache::get('front_reset_code:' . $email);
        if (!$cached || (string) $cached !== (string) $request->input('code')) {
            return $this->error('auth.reset_code_invalid', ResponseCode::VALIDATION_FAILED);
        }

        $login = UserLogin::where('email', $request->input('email'))->first();
        if (!$login) {
            return $this->error('auth.user_not_found', ResponseCode::USER_NOT_FOUND);
        }

        $login->update(['password' => Hash::make($request->input('password'))]);
        Cache::forget('front_reset_code:' . $email);

        return $this->success([], 'auth.password_reset_success', ResponseCode::UPDATED);
    }
}
