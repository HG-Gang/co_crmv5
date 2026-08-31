<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/28
 * Time: 23:49
 */

namespace Tests\Feature;

use App\Mail\FrontFeedbackNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/**
 * 旧前台意见反馈业务闭环测试。
 *
 * 文件功能：
 * - 验证旧意见反馈请求按新表字段保存，并真实发送到配置的业务邮箱。
 * - 验证邮件失败保留待处理记录但返回失败，参数错误则不产生任何副作用。
 *
 * 返回结果：
 * - code=0 表示记录保存且邮件发送成功。
 * - code=1 表示参数、数据库或邮件阶段失败，不能向旧页面伪报成功。
 */
class FrontLegacyFeedbackClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证成功请求同时完成新表字段映射和业务邮件通知。
     */
    public function test_feedback_persists_new_schema_and_sends_business_mail(): void
    {
        $email = 'legacy-feedback-success@example.test';
        $recipient = 'feedback-team@example.test';
        DB::table('offweb_feedbacks')->where('email', $email)->delete();
        config(['mail.feedback_to' => $recipient]);
        Mail::fake();

        $response = $this->postJson('/user/offweb/feedback', [
            'username' => '旧官网访客',
            'email' => $email,
            'phone' => '13900001001',
            'remarks' => '希望客服回电说明账户资料流程。',
        ]);

        $response->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('msg', __('response.success'));
        $record = DB::table('offweb_feedbacks')->where('email', $email)->first();
        $this->assertNotNull($record);
        $this->assertSame('旧官网访客', $record->title);
        $this->assertStringContainsString('希望客服回电说明账户资料流程。', $record->content);
        $this->assertStringContainsString('13900001001', $record->content);

        Mail::assertSent(FrontFeedbackNotification::class, function (FrontFeedbackNotification $mail) use ($recipient, $email): bool {
            return $mail->hasTo($recipient)
                && ($mail->feedback['email'] ?? '') === $email
                && ($mail->feedback['phone'] ?? '') === '13900001001';
        });
    }

    /**
     * 验证邮件传输异常时保留待处理记录，但响应必须明确失败。
     */
    public function test_feedback_mail_failure_keeps_record_and_returns_real_failure(): void
    {
        $email = 'legacy-feedback-mail-failure@example.test';
        $recipient = 'feedback-failure@example.test';
        DB::table('offweb_feedbacks')->where('email', $email)->delete();
        config(['mail.feedback_to' => $recipient]);
        Mail::shouldReceive('to')
            ->once()
            ->with($recipient)
            ->andThrow(new RuntimeException('forced feedback mail failure'));

        $response = $this->postJson('/user/offweb/feedback', [
            'username' => '邮件失败访客',
            'email' => $email,
            'phone' => '13900001002',
            'remarks' => '此记录应保留给后台人工处理。',
        ]);

        $response->assertOk()
            ->assertJsonPath('code', 1)
            ->assertJsonPath('msg', __('response.email_send_failed'));
        $this->assertDatabaseHas('offweb_feedbacks', [
            'email' => $email,
            'title' => '邮件失败访客',
            'status' => 0,
        ]);
    }

    /**
     * 验证空表单和非法邮箱不会写入空反馈，也不会调用邮件系统。
     */
    public function test_feedback_validation_failure_has_no_database_or_mail_side_effect(): void
    {
        $before = DB::table('offweb_feedbacks')->count();
        Mail::fake();

        $response = $this->postJson('/user/offweb/feedback', [
            'username' => '',
            'email' => 'not-an-email',
            'phone' => '',
            'remarks' => '',
        ]);

        $response->assertOk()
            ->assertJsonPath('code', 1)
            ->assertJsonPath('msg', __('response.validation_failed'));
        $this->assertSame($before, DB::table('offweb_feedbacks')->count());
        Mail::assertNothingSent();
    }
}
