<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/31
 * Time: 23:23
 */

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * 前台注册成功欢迎邮件。
 *
 * 文件功能：
 * - 在 MT4 账户同步处理成功后，向注册邮箱发送交易账号与开通提示。
 * - 与旧项目 registerSuc 欢迎邮件对齐，但不再回显明文密码（密码由用户注册时自设）。
 */
class FrontRegistrationSuccessNotification extends Mailable
{
    use Queueable, SerializesModels;

    /** @var int 业务用户编号，即 MT4 交易账号。 */
    public $tradingAccount;

    /** @var string 注册填写的用户名，用于邮件称呼。 */
    public $userName;

    /**
     * 创建注册成功欢迎邮件。
     *
     * @param int $tradingAccount 业务用户编号（user_id / mt4_code）。
     * @param string $userName 注册填写的用户名。
     */
    public function __construct(int $tradingAccount, string $userName)
    {
        $this->tradingAccount = $tradingAccount;
        $this->userName = $userName;
    }

    /**
     * 构建邮件主题与纯文本模板。
     *
     * @return $this 返回可由 Laravel Mailer 发送的 Mailable。
     */
    public function build()
    {
        return $this->subject(__('auth.registration_success_mail_subject'))
            ->text('emails.front-registration-success-notification');
    }
}
