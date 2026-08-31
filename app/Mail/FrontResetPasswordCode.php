<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 12:47
 */

/**
 * 前台重置密码验证码邮件。
 *
 * 文件功能：
 * - 用户忘记密码申请重置时，向注册邮箱发送重置验证码邮件。
 * - 使用纯文本模板 emails.front-reset-password-code 渲染验证码。
 *
 * 适用场景：
 * - 前台忘记密码流程：用户提交邮箱后系统生成验证码并发送本邮件。
 *
 * 入参例子：
 * - new FrontResetPasswordCode('735204') 发送重置验证码 735204。
 *
 * 方法功能：
 * - __construct(string $code)：保存要发送的重置密码验证码。
 * - build()：设置邮件主题（auth.password_reset_verification_mail_subject）并绑定纯文本模板。
 *
 * 返回值：
 * - build() 返回 $this，供 Laravel Mailer 直接发送。
 *
 * 异常或失败场景：
 * - 邮件发送失败由 Mail 门面抛出异常，由调用方捕获处理；本类不参与发送。
 */
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FrontResetPasswordCode extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * 重置密码验证码明文（六位数字）。作为 Mailable 公共属性供纯文本模板渲染；
     * 只负责“把验证码送达用户邮箱”，其缓存存储、600 秒有效期与三要素校验（user_id/email/code）都在控制器与 Cache 侧完成。
     *
     * @var string
     */
    public $code;

    /**
     * 构造重置密码验证码邮件。
     *
     * @param string $code 六位数字重置验证码，供 build() 渲染的纯文本模板使用。
     */
    public function __construct(string $code)
    {
        $this->code = $code;
    }

    /**
     * 设置邮件主题并绑定纯文本模板。
     *
     * @return $this 返回自身供 Laravel Mailer 直接发送。
     */
    public function build()
    {
        return $this->subject(__('auth.password_reset_verification_mail_subject'))
            ->text('emails.front-reset-password-code');
    }
}
