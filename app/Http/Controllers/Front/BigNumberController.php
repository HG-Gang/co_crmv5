<?php

namespace App\Http\Controllers\Front;

use App\Constants\ResponseCode;
use App\Models\UserInfo;
use App\Models\UserLogin;
use App\Services\JwtService;
use App\Support\FrontLegacyData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Big-number agent portal (legacy /user/agents/*).
 */
class BigNumberController extends FrontBaseController
{
    protected $jwtService;

    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required_without:user_id',
            'user_id' => 'required_without:email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $login = $request->filled('email')
            ? UserLogin::where('email', $request->input('email'))->first()
            : UserLogin::where('user_id', $request->input('user_id'))->first();

        if (!$login || !Hash::check($request->input('password'), $login->password) || !$login->isActive()) {
            return $this->error('auth.failed', ResponseCode::AUTH_FAILED);
        }

        $info = UserInfo::where('user_id', $login->user_id)->first();
        if (!$info || (int) $info->account_type !== 1) {
            return $this->error('response.permission_denied', ResponseCode::PERMISSION_DENIED);
        }

        $token = $this->jwtService->generateToken([
            'sub' => $login->id,
            'guard' => 'user',
            'portal' => 'big_number',
        ]);

        return $this->success([
            'access_token' => $token,
            'user' => [
                'user_id' => $login->user_id,
                'email' => $login->email,
            ],
        ], 'auth.login_success', ResponseCode::SUCCESS);
    }

    public function agentSubList(Request $request)
    {
        $user = $request->user('user');
        $query = UserInfo::query()
            ->where('parent_id', (int) $user->user_id)
            ->where('account_type', 1);

        if ($request->filled('userId')) {
            $query->where('user_id', (int) $request->input('userId'));
        }

        $userIds = (clone $query)->pluck('user_id')->map(function ($id) {
            return (int) $id;
        })->all();
        $totalRow = FrontLegacyData::financialTotalRowForUserIds($userIds, $request, 'user_id');

        $list = $query->orderBy('user_id')
            ->paginate(FrontLegacyData::perPage($request))
            ->through(function (UserInfo $agent) use ($request) {
                return array_merge(
                    FrontLegacyData::userBasicAlias($agent),
                    FrontLegacyData::userFinancialSummary($agent, $request, true)
                );
            });

        return $this->success(
            FrontLegacyData::paginatedListResponse($list, $totalRow),
            'response.query_success',
            ResponseCode::SUCCESS
        );
    }
}
