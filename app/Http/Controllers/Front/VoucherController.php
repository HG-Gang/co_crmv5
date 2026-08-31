<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 09:13
 */

namespace App\Http\Controllers\Front;

use App\Models\VoucherInfo;
use App\Constants\ResponseCode;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * 前台凭证控制器。
 *
 * 文件功能：
 * - 处理前台用户上传入金凭证、保存凭证图片、写入 voucher_infos 表以及查询当前用户凭证记录。
 * - 本控制器只允许当前 user guard 登录用户操作自己的凭证数据，后台审核通过/拒绝逻辑由 Admin\VoucherController 处理。
 * - 凭证图片保存到 public 磁盘的 vouchers/{user_id} 目录，voucher_infos.images 只保存相对路径，便于后台预览和后续存储迁移。
 *
 * 安全边界：
 * - 文件白名单：images.* 只允许 jpeg/png/jpg/gif 且单张最大 5120KB；保存目录按当前登录用户 user_id 固定生成，不信任客户端路径。
 * - 数据归属：查询与写入都以 user guard 解析出的 userInfo 为准，请求体不携带 user_id 等归属字段。
 */
class VoucherController extends FrontBaseController
{
    /**
     * 提交当前前台用户的凭证图片。
     *
     * 业务逻辑说明：
     * - store 用于提交当前前台用户的凭证图片。
     * - images 表示凭证图片上传字段，前端必须至少上传一张图片。
     * - images.* 表示每一张凭证图片文件，只允许 jpeg、png、jpg、gif，单张最大 5120KB。
     * - remarks 表示用户提交凭证时填写的备注，允许为空，最大 2000 个字符。
     * - userLogin 表示当前 user guard 登录记录，来源于 jwt.auth:user 中间件解析出的前台登录账号。
     * - userInfo 表示当前登录记录关联的业务用户资料，缺失时不能落库凭证，避免写入没有业务用户 ID 的孤儿记录。
     * - imagePaths 表示已保存到 public 磁盘的凭证图片相对路径集合，最终用英文逗号拼接写入 voucher_infos.images。
     * - review_status=0 表示凭证待后台审核，后台审核通过后改为 1，拒绝后改为 2。
     * - created_by 表示凭证提交人的显示名称，便于后台列表不再额外联表也能看到提交人。
     *
     * @param Request $request HTTP 请求对象，承载 images[] 上传文件、remarks 备注和当前登录用户。
     * @return JsonResponse 凭证提交成功返回新建的 voucher_infos 记录，失败返回统一错误码。
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'images'   => 'required|array|min:1',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:5120',
            'remarks'  => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $userLogin = $request->user('user');
        $userInfo = $userLogin->userInfo;

        if (!$userInfo) {
            return $this->error(__('auth.user_info_not_found'), ResponseCode::USER_NOT_FOUND);
        }

        $imagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                // 凭证图片保存到 storage/app/public/vouchers/{user_id}/，数据库只保存 public 磁盘相对路径。
                $path = $file->store('vouchers/' . $userInfo->user_id, 'public');
                $imagePaths[] = $path;
            }
        }

        $voucher = VoucherInfo::create([
            'user_id'       => $userInfo->user_id,
            'images'        => implode(',', $imagePaths),
            'remarks'       => $request->input('remarks', ''),
            'review_status' => 0,
            'created_by'    => $userInfo->user_name,
        ]);

        return $this->success($voucher, __('response.success'), ResponseCode::SUCCESS);
    }

    /**
     * 查询当前前台用户自己的凭证提交记录。
     *
     * 业务逻辑说明：
     * - records 用于返回当前前台用户自己的凭证提交记录，查询条件固定为 voucher_infos.user_id=当前业务用户 ID。
     * - review_status 表示按凭证审核状态筛选，传入时按 voucher_infos.review_status 精确匹配。
     * - date_from 表示凭证创建开始日期，会扩展为当天 00:00:00 的时间戳下界。
     * - date_to 表示凭证创建结束日期，会扩展为当天 23:59:59 的时间戳上界。
     * - per_page 表示每页返回记录数量，未传时默认 15 条。
     * - records 返回 Laravel 分页对象，供 Layui 表格和前台分页组件读取 data、total、current_page 等分页元数据。
     *
     * @param Request $request HTTP 请求对象，承载 review_status、date_from、date_to、per_page 以及当前登录用户。
     * @return JsonResponse 当前用户凭证分页列表，用户资料不存在时返回 USER_NOT_FOUND。
     */
    public function records(Request $request): JsonResponse
    {
        $userLogin = $request->user('user');
        $userInfo = $userLogin->userInfo;

        if (!$userInfo) {
            return $this->error(__('auth.user_info_not_found'), ResponseCode::USER_NOT_FOUND);
        }

        $query = VoucherInfo::where('user_id', $userInfo->user_id);

        if ($request->filled('review_status')) {
            $query->where('review_status', $request->input('review_status'));
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', strtotime($request->input('date_from') . ' 00:00:00'));
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', strtotime($request->input('date_to') . ' 23:59:59'));
        }

        $records = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return $this->success($records, __('response.query_success'), ResponseCode::SUCCESS);
    }
}
