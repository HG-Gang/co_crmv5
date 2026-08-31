<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/28
 * Time: 23:50
 */

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * 旧前台意见反馈业务通知邮件。
 *
 * 文件功能：
 * - 接收已经成功写入 offweb_feedbacks 的反馈快照。
 * - 使用纯文本模板发送用户名、邮箱、手机号和反馈内容，供业务团队离线处理。
 */
class FrontFeedbackNotification extends Mailable
{
    use Queueable, SerializesModels;

    /** @var array<string, mixed> 已落库反馈的业务字段快照。 */
    public $feedback;

    /**
     * 创建反馈通知邮件。
     *
     * @param array<string, mixed> $feedback 反馈 ID、用户 ID、用户名、邮箱、手机号和反馈正文。
     */
    public function __construct(array $feedback)
    {
        $this->feedback = $feedback;
    }

    /**
     * 构建业务邮件主题和纯文本模板。
     *
     * @return $this 返回可由 Laravel Mailer 发送的 Mailable。
     */
    public function build()
    {
        return $this->subject('需求反馈留言')
            ->text('emails.front-feedback-notification');
    }
}
