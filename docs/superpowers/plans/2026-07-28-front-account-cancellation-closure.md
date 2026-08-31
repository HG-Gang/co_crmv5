# 前台账号注销业务闭环实施计划

> **执行要求：** 当前仓库没有 Git 元数据，无法创建 worktree 或提交；使用目标文件 SHA256 作为每个修改检查点。实施过程严格执行 TDD 红灯、最小实现、绿灯和回归验证。

**目标：** 让现代和旧前台注销入口完整迁移旧项目的身份验证、风控、MT4 锁号、本地状态收口、申请创建、验证码消费和失败补偿逻辑。

**架构：** `CancelController` 作为唯一编排入口，`ajaxCancelAccount` 仅做旧字段归一化和响应模式标记，所有业务判断集中在 `apply`。密码使用 `UserPasswordService` 三态语义，MT4 使用 `Mt4ManagerService`，本地状态和申请在同一事务中写入。

**技术栈：** Laravel 8、PHP 7.4/8、Eloquent、Cache/Session、PHPUnit 9、Mockery。

---

### 任务一：固化失败补偿红灯

**文件：**
- 修改：`tests/Feature/FrontCancelLifecycleClosureModuleTest.php`

- [x] 增加“远端锁号成功、`CancelApply` 创建抛异常时必须调用 `unlockUser`”用例。
- [x] 断言响应为失败、本地三个能力字段回滚、申请不存在、验证码仍有效。
- [x] 运行：`php vendor/bin/phpunit --colors=never tests/Feature/FrontCancelLifecycleClosureModuleTest.php`
- [x] 红灯证据：八个用例全部因生产逻辑缺失失败；实现后扩展为九个用例并全部通过。

### 任务二：实现单一注销状态机

**文件：**
- 修改：`app/Http/Controllers/Front/CancelController.php`

- [x] 注入 `UserPasswordService` 与 `Mt4ManagerService`，移除控制器直接 `Hash::check`。
- [x] 让现代和旧入口统一执行重复申请、未平仓、非零资金、直属下级和处理中出金检查。
- [x] 统一校验当前用户手机号、邮箱、身份证、验证码绑定和密码三态。
- [x] `mt4.enabled=true` 时只接受 `lockUser` 的明确 `status=ok`，异常或错误结果失败关闭。
- [x] 在数据库事务中写入以下状态并创建待审申请：

```php
$userInfo->update([
    'is_mt4_enabled' => 0,
    'is_mt4_readonly' => 1,
    'is_withdrawal_allowed' => 1,
]);
CancelApply::create($applyData);
```

- [x] 事务失败且远端已锁定时调用 `unlockUser`；成功后删除 Cache 和旧 session 验证码。
- [x] 每个新增私有方法按 `docs/中文注释标准.md` 说明参数、返回值和失败含义。

### 任务三：修正现代归属边界用例

**文件：**
- 修改：`tests/Feature/FrontCancelApplyOwnerBoundaryClosureModuleTest.php`

- [x] 为成功场景建立 `user_auths` 身份资料与当前用户验证码缓存。
- [x] 提交完整身份、验证码、密码和伪造的其他用户 ID。
- [x] 继续断言申请只能写入当前认证用户，证明加强验证后归属边界仍成立。

### 任务四：专项与回归验证

**文件：**
- 验证：`app/Http/Controllers/Front/CancelController.php`
- 验证：`tests/Feature/FrontCancelLifecycleClosureModuleTest.php`
- 验证：`tests/Feature/FrontCancelApplyOwnerBoundaryClosureModuleTest.php`
- 验证：`tests/Feature/FrontCancelLegacyApplyOwnerBoundaryClosureModuleTest.php`

- [x] 运行注销生命周期测试：`9 tests / 59 assertions`，零失败。
- [x] 运行现代和旧归属边界测试，零失败。
- [x] 运行 `php vendor/bin/phpunit --colors=never --filter FrontCancel tests/Feature`：`22 tests / 140 assertions`，零失败。
- [x] 运行三个修改 PHP 文件的 `php -l`，均显示 `No syntax errors detected`。
- [x] 重新计算 SHA256 并记录计划内文件变更；并发新增的验证码绑定用例已保留并纳入回归。

### 任务五：回到普通用户总迁移清单

**文件：**
- 读取：`D:/Php-project/Php/new_co_gmtk_crmV3/routes/web.php`
- 读取：`D:/Software/PhpProject/Demo/co_crmv5/docs/legacy-route-coverage.csv`
- 读取：普通用户对应旧控制器、Blade 与新项目映射文件

- [ ] 以旧路由、旧控制器和旧 Blade 的真实调用为权威源，定位下一个仍由占位响应、静态页面或缺少副作用构成的业务缺口。
- [ ] 为该缺口创建下一个独立设计、红灯和实施循环，不以静态审计结果替代迁移完成。

## 计划自检

- 需求覆盖：现代/旧入口、风险、身份、验证码、密码三态、MT4、事务、补偿、归属和回归均有对应任务。
- 占位扫描：没有 `TBD`、`TODO` 或“稍后实现”。
- 类型一致：控制器依赖、状态字段、错误码和测试文件均来自当前项目真实定义。
