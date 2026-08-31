{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/07/28
Time: 23:50
--}}
新的意见反馈

反馈编号：{{ $feedback['id'] }}
用户编号：{{ $feedback['user_id'] ?? '未登录访客' }}
称呼：{{ $feedback['username'] }}
邮箱：{{ $feedback['email'] }}
手机号：{{ $feedback['phone'] }}
反馈内容：
{{ $feedback['remarks'] !== '' ? $feedback['remarks'] : '未填写反馈正文' }}
