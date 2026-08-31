<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/29
 * Time: 15:15
 */

namespace Tests\Feature;

use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 旧前台资料操作 GET 页面闭环测试。
 *
 * 文件功能：
 * - 验证身份证、银行卡、换绑、头像、联系方式和密码入口渲染各自独立表单。
 * - 验证表单保留旧字段名和旧 session POST 地址，不依赖 JWT API 才能完成提交。
 * - 验证所有操作按钮使用 Lucide 图标，并由同一个 Blade/JS 组件维护交互状态。
 *
 * 返回结果：
 * - HTTP 200 且页面动作、字段和端点一致，表示旧弹层地址可以完整执行原业务。
 * - 任一入口退回整张 profile 页面、缺字段或错误端点时测试失败。
 */
class FrontLegacyProfilePageClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证六类旧资料入口各自渲染正确业务表单，而不是重复整张个人中心。
     */
    public function test_legacy_profile_get_pages_render_dedicated_session_forms(): void
    {
        $user = $this->createActiveUser(419050100);
        $client = $this->actingAs($user, 'user')->withSession([
            'suser' => ['user_id' => $user->user_id],
        ]);

        $cases = [
            ['/user/center/uploadIdCard?frame=1', 'identity', '/user/center/uploadIdCard', ['username', 'userIdcardNo', 'Idphoto1', 'Idphoto2']],
            ['/user/center/uploadBank?frame=1', 'bank', '/user/center/uploadBankCard', ['username', 'bankclass', 'bankno', 'bankinfo', 'bankimg']],
            ['/user/center/uploadChangeBank/changeBank?frame=1', 'bank-change', '/user/center/uploadChangeBankCard', ['bankclass', 'bankno', 'bankinfo', 'useremail', 'userverfcode', 'password', 'bankimg']],
            ['/user/center/uploadHead_browse?frame=1', 'avatar', '/user/center/uploadHeadImg', ['headimg']],
            ['/user/center/updPhoneEmail/phone?frame=1', 'contact-phone', '/user/center/updatePhoneEmailInfo', ['oldphonefill', 'userphoneNo', 'newuserphoneNo', 'password']],
            ['/user/center/updPhoneEmail/email?frame=1', 'contact-email', '/user/center/updatePhoneEmailInfo', ['oldemail', 'useremail', 'updVerifyCode', 'password']],
            ['/user/editpsw?frame=1', 'password', '/user/editpsw_save', ['olduserpsw', 'newuserpsw', 'confirmuserpsw']],
        ];

        foreach ($cases as [$uri, $action, $endpoint, $fields]) {
            $response = $client->get($uri);
            $response->assertOk()
                ->assertSee('data-legacy-profile-action="' . $action . '"', false)
                ->assertSee('data-submit-url="' . $endpoint . '"', false)
                ->assertSee('data-layui-page="legacy/profile/action"', false)
                ->assertSee('data-lucide=', false)
                ->assertDontSee('lay-filter="profileForm"', false);

            foreach ($fields as $field) {
                $response->assertSee('name="' . $field . '"', false);
            }
        }
    }

    /**
     * 验证银行卡换绑与邮箱修改页保留旧版两阶段验证码端点。
     */
    public function test_legacy_sensitive_profile_pages_expose_verification_endpoints(): void
    {
        $user = $this->createActiveUser(419050200);
        $client = $this->actingAs($user, 'user')->withSession([
            'suser' => ['user_id' => $user->user_id],
        ]);

        $client->get('/user/center/uploadChangeBank/changeBank?frame=1')
            ->assertOk()
            ->assertSee('data-verify-url="/user/center/changeBankCardVerifyCode"', false)
            ->assertSee('data-code-url="/user/center/changeBankCardSendCode"', false);

        $client->get('/user/center/updPhoneEmail/email?frame=1')
            ->assertOk()
            ->assertSee('data-verify-url="/user/center/updateVerifyInfo"', false)
            ->assertSee('data-code-url="/user/center/updVerifyPassSendCode"', false);

        // 密码保存会注销 Session，成功页继续走旧退出路由，统一完成 Guard 和会话清理后再跳登录页。
        $client->get('/user/editpsw?frame=1')
            ->assertOk()
            ->assertSee('data-success-url="/user/loginOut"', false);
    }

    /**
     * 验证旧资料组件的 Blade、CSS 和聚合脚本均存在中文职责注释及会话提交逻辑。
     */
    public function test_legacy_profile_component_keeps_blade_css_and_js_contract(): void
    {
        $blade = file_get_contents(resource_path('front/layui/profile/legacy-action.blade.php')) ?: '';
        $css = file_get_contents(public_path('css/front/profile-legacy-action.css')) ?: '';
        $script = file_get_contents(public_path('js/apps/front/layui/pages.js')) ?: '';

        $this->assertStringContainsString('旧前台资料操作专用页面', $blade);
        $this->assertStringContainsString('旧前台资料操作页面视觉组件', $css);
        $this->assertStringContainsString("registry['legacy/profile/action']", $script);
        $this->assertStringContainsString('旧前台资料操作页使用 Web Session', $script);
        // 视觉变量必须复用项目真实 token，否则暗色主题会回退到固定浅色值。
        $this->assertStringContainsString('var(--crm-ink', $css);
        $this->assertStringContainsString('var(--crm-muted', $css);
        $this->assertStringContainsString('var(--crm-line', $css);
        $this->assertStringContainsString('width: min(100%, 760px);', $css);
        // 四类文件字段全部并入共享 layui 上传组件（deferred 模式）：缓存与展示键沿用旧字段名。
        $this->assertStringContainsString('data-crm-upload="Idphoto1"', $blade);
        $this->assertStringContainsString('data-crm-upload="Idphoto2"', $blade);
        $this->assertStringContainsString('data-crm-upload="bankimg"', $blade);
        $this->assertStringContainsString('data-crm-upload="headimg"', $blade);
        $this->assertStringContainsString('CrmUpload.init(document', $script);
        // 提交侧以组件缓存为事实来源：未选文件按必填拦截，文件按旧字段名补进 FormData。
        $this->assertStringContainsString('CrmUpload.file(field)', $script);
        $this->assertStringContainsString('requestData.append(field, file, file.name);', $script);
        // 异步错误必须关联到对应输入框，验证码状态也要向辅助技术播报。
        $this->assertStringContainsString('function wireAccessibleStatus()', $script);
        $this->assertStringContainsString(".attr('aria-describedby', errorId)", $script);
        $this->assertStringContainsString('data-code-label aria-live="polite" aria-atomic="true"', $blade);
        // 非法文件由共享上传组件复位缓存与展示（组件内部 reset/fail），页面把拒绝原因映射到旧错误位。
        $this->assertStringContainsString('CrmUpload.init(document', $script);
        $this->assertStringContainsString('showError(config.field, messages.unsupportedImage);', $script);
        $this->assertStringContainsString('fileValidationMessage(window.CrmUpload ? CrmUpload.file(field) : null);', $script);
        // 银行卡换绑邮箱前置校验返回 err=useremail，页面必须翻译业务含义而不是显示裸 FAIL。
        $this->assertStringContainsString("useremail: '邮箱与当前账户不一致。'", $script);
        // 发码成功后的首屏必须完整显示 60 秒，避免实际锁定时长短于旧交互协议。
        $this->assertMatchesRegularExpression(
            '/function startCodeCountdown\(\$button\)\s*\{\s*var seconds = 60;/',
            $script
        );
        $this->assertStringNotContainsString('var seconds = 59;', $script);
        $this->assertStringContainsString('以 FormData 或 URL 编码提交旧 Session 路由', $script);
        $this->assertStringNotContainsString('以 FormData 提交旧 Session 路由', $script);
        $this->assertStringNotContainsString('node_modules', $blade . $css);
    }

    /**
     * 创建启用且未注销的普通用户，供旧 web 中间件与页面数据绑定共同验证。
     */
    private function createActiveUser(int $userId): UserLogin
    {
        $now = time();
        $loginId = (int) DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'legacy-profile-page-' . $userId . '@example.test',
            'password' => Hash::make('password'),
            'account_type' => 2,
            'role_id' => 0,
            'is_enabled' => 1,
            'is_cancelled' => 0,
            'source_type' => 0,
            'jwt_token_id' => '',
            'last_login_ip' => '',
            'last_login_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        DB::table('user_infos')->insert([
            'user_id' => $userId,
            'login_id' => $loginId,
            'user_name' => '旧资料页面用户' . $userId,
            'phone' => '86-13800138000',
            'gender' => 1,
            'account_type' => 2,
            'parent_id' => 0,
            'family_tree' => '',
            'total_funds' => 0,
            'equity' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        return UserLogin::query()->findOrFail($loginId);
    }
}
