# 旧前台意见反馈业务闭环实施计划

**目标：** 为 `/user/offweb/feedback` 补齐参数校验、反馈持久化、业务邮件通知和真实失败响应。

**架构：** `LegacyPageController::feedback` 负责编排，`FrontFeedbackNotification` 封装邮件内容，`mail.feedback_to` 提供环境化收件地址。数据库记录先创建，邮件失败时记录保留供后台处理。

**技术栈：** Laravel 8、Validator、Mail/Mailable、PHPUnit、Mail fake。

---

### 任务一：红灯

- [x] 新建 `tests/Feature/FrontLegacyFeedbackClosureModuleTest.php`。
- [x] 证明当前实现没有发送邮件、邮件异常仍伪成功、空表单仍落库。
- [x] 运行专项测试，三个用例分别因缺少邮件、伪成功和缺少验证而失败。

### 任务二：邮件契约

- [x] 新建 `app/Mail/FrontFeedbackNotification.php`，公开只读测试数据并使用文本模板。
- [x] 新建 `resources/views/emails/front-feedback-notification.blade.php`，完整展示用户名、邮箱、手机号和反馈内容。
- [x] 在 `config/mail.php` 新增 `feedback_to`，在 `.env.example` 新增 `MAIL_FEEDBACK_TO`。

### 任务三：控制器闭环

- [x] 修改 `LegacyPageController::feedback`，校验必填字段和长度。
- [x] 使用当前认证上下文写入 `offweb_feedbacks` 新表映射字段。
- [x] 数据库异常返回数据库失败；邮件异常保留记录并返回邮件失败；只有邮件发送成功返回 `code=0`。
- [x] 按中文注释标准说明字段映射、返回值和失败场景。

### 任务四：验证

- [x] 反馈专项为 `3 tests / 18 assertions`；旧路由、公开认证、注释和反馈筛选回归全部通过。
- [x] 对全部修改 PHP 文件执行 `php -l`，均无语法错误。
- [x] 邮件文本模板已通过真实 `Mailable::render()` 输出验证；继续定位下一模块。

## 计划自检

- 没有待定字段或占位实现。
- 邮件与数据库失败语义分别可测试。
- 不新增数据库列，不破坏既有旧数据迁移脚本。
