<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 12:53
 */

/**
 * 前台注册邮箱验证码邮件。
 *
 * 文件功能：
 * - 注册流程要求邮箱验证时，向用户邮箱发送验证码邮件。
 * - 使用纯文本模板 emails.front-registration-verification-code 渲染验证码。
 *
 * 适用场景：
 * - 前台用户注册时选择邮箱验证、或注册流程校验邮箱归属。
 *
 * 入参例子：
 * - new FrontRegistrationVerificationCode('482913') 发送验证码 482913。
 *
 * 方法功能：
 * - __construct(string $code)：保存要发送的邮箱验证码。
 * - build()：设置邮件主题（auth.registration_verification_mail_subject）并绑定纯文本模板。
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

class FrontRegistrationVerificationCode extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * 注册验证码明文（六位数字）。作为 Mailable 的公共属性随 SerializesModels 序列化进入队列渲染模板；
     * 生命周期到邮件发送完成即止，验证码的真正存储与校验由调用方写入 Cache，不落在本类。
     *
     * @var string
     */
    public $code;

    /**
     * 构造注册验证码邮件。
     *
     * @param string $code 六位邮箱验证码，随邮件正文发送给用户。
     */
    public function __construct(string $code)
    {
        $this->code = $code;
    }

    /**
     * 组装邮件主题与纯文本模板。
     *
     * 主题读取多语言 key auth.registration_verification_mail_subject，
     * 正文使用纯文本模板避免富文本被邮件客户端渲染差异影响验证码可读性。
     *
     * @return $this 返回自身供 Mail 门面链式发送。
     */
    public function build()
    {
        return $this->subject(__('auth.registration_verification_mail_subject'))
            ->text('emails.front-registration-verification-code');
    }
}
