# 旧前台意见反馈业务闭环设计

## 目标

恢复旧项目 `RegisterController::demandFeedback` 的两项真实副作用：保存反馈记录、向业务邮箱发送反馈通知。新项目继续使用 `offweb_feedbacks` 新表字段，不复制旧表结构，但返回值必须真实表达邮件发送结果。

## 字段映射

| 旧请求/旧表字段 | 新表字段 | 含义 |
|---|---|---|
| `username` / `user_name` | `title` | 反馈人称呼 |
| `email` | `email` | 联系邮箱 |
| `phone` | `content` 内联系方式 | 新表没有独立手机号列，按可读文本保留 |
| `remarks` | `content` | 反馈正文 |
| 固定 `mktg@gmtkg.com` | `mail.feedback_to` | 可由 `MAIL_FEEDBACK_TO` 覆盖的业务收件邮箱 |
| 邮件返回值 | JSON `code` | `0=保存并发送成功`，`1=验证、保存或发送失败` |

## 执行链路

```text
POST /user/offweb/feedback
  -> 校验 username、email、phone，限制 remarks 长度
  -> 从 user guard 或旧 suser session 解析可选 user_id
  -> 写入 offweb_feedbacks 待处理记录
       数据库失败 -> code=1，停止发送
  -> 校验 mail.feedback_to
  -> Mail::to(recipient)->send(FrontFeedbackNotification)
       邮件失败 -> 保留待处理记录，code=1
       邮件成功 -> code=0
```

## 失败边界

- 空字段、非法邮箱和超长文本不落库、不发邮件。
- 数据库失败不发送内容，避免业务邮箱收到无法追踪的孤立反馈。
- 邮件失败保留数据库记录，后台仍可处理，但接口不得伪报成功。
- 日志只记录记录 ID、收件地址和异常类别，不记录整份用户反馈正文。

## 测试

- 成功提交：断言新表字段映射、手机号内容、收件人和 Mailable 数据。
- 邮件异常：断言记录保留且响应 `code=1`、邮件失败消息。
- 参数错误：断言不落库且不发送邮件。

## 自检

设计与旧项目“先落库、后发邮件”的顺序一致；新项目的字段差异通过明确映射解决，邮件失败不再返回假成功。
