# 前台账号注销业务闭环设计

## 目标

把旧项目 `UserCenterController::ajaxCancelAccount` 的真实注销语义迁移到新项目，并让现代接口与旧兼容接口共用同一条不可绕过的业务链路。申请成功必须同时表示：用户身份验证通过、一次性验证码有效、MT4 密码明确通过、远端账号已锁定、本地交易和出金能力已收口、待审核申请已创建、验证码已消费。

## 边界

- 当前用户身份只能来自 `user` guard 或旧 `suser` session，请求中的 `user_id`、`userId` 不参与归属判断。
- `status=0` 的待审核申请禁止重复提交；历史通过或拒绝记录不阻止重新申请。
- 未平仓、`total_funds` 或 `equity` 非零、存在直属下级、存在 `status in (0, 1)` 出金记录时必须在远端副作用前停止。
- 手机号、邮箱、身份证、验证码、密码缺一不可；现代接口不得只提交 `reason` 绕过敏感验证。
- MT4 密码结果分为 `verified`、`rejected`、`network_failure`。网络未知不得解释为密码错误或成功。
- 仅当 `mt4.enabled=true` 时调用远端锁号；`status=ok` 才允许写本地状态。
- 远端锁号成功而本地事务失败时调用 `unlockUser` 补偿，避免远端锁定而本地没有申请记录。

## 组件职责

- `CancelController`：编排完整状态机、映射现代/旧响应、管理验证码生命周期和补偿。
- `UserPasswordService`：集中解释本地哈希或 MT4 密码网关的三态结果。
- `Mt4ManagerService`：执行 `lockUser` 与失败补偿 `unlockUser`，控制器只接受明确成功。
- `CancelApply`、`UserInfo`、`UserTrade`、`WithdrawRecord`：分别承载申请、本地能力、持仓和处理中出金状态。

## 执行链路

```text
POST 现代/旧注销入口
  -> 从认证上下文取得 UserLogin 与 UserInfo
  -> 校验 reason 长度
  -> 拒绝未平仓
  -> 拒绝任意非零资金或净值（包括负数）
  -> 拒绝直属下级
  -> 拒绝待处理/处理中的出金
  -> 校验手机号、邮箱、身份证
  -> 校验一次性验证码以及发码邮箱/手机号绑定
  -> UserPasswordService::verify
       rejected -> 密码错误
       network_failure -> 第三方网络错误
       verified -> 继续
  -> 拒绝重复待审申请；已消费验证码的重放请求会在此前返回 codeErr
  -> mt4.enabled=true 时 Mt4ManagerService::lockUser
       异常/非 ok -> MT4 同步失败，不写库、不消费验证码
  -> 数据库事务
       user_infos.is_mt4_enabled = 0
       user_infos.is_mt4_readonly = 1
       user_infos.is_withdrawal_allowed = 1
       cancel_applies.status = 0
  -> 事务失败且远端已锁定 -> unlockUser 补偿
  -> 事务成功 -> 删除 Cache 与旧 session 验证码
  -> 返回现代统一响应或旧 SUC/FAIL 响应
```

## 错误语义

| 场景 | 旧响应 | 现代响应 |
|---|---|---|
| 非零资金/净值 | `ERRBALANCE` | `OPERATION_NOT_ALLOWED`，数据含 `ERRBALANCE` |
| 未平仓 | `ERRVOL` | `RISK_RATE_EXCEEDED`，数据含 `ERRVOL` |
| 存在直属下级 | `existSubUser` | `OPERATION_NOT_ALLOWED` |
| 存在处理中出金 | `UnfinishedOrder` | `OPERATION_NOT_ALLOWED` |
| 身份/验证码错误 | 对应字段错误码 | `VALIDATION_FAILED`，保留相同 `err/col` |
| 密码明确拒绝 | `passwordErr/password` | `VALIDATION_FAILED` |
| 密码结果未知 | `NETWORKFAIL/FATALCANOTCONNECT` | `THIRD_PARTY_ERROR` |
| MT4 锁号失败 | `MT4SYNCUPDATAFAIL/NOCOL` | `MT4_SYNC_FAILED` |
| 本地事务失败 | `cancelApplyErr/NOCOL` | `DB_ERROR` |

## 测试设计

- 先保留七个已确认红灯：现代验证绕过、负余额、直属下级、处理中出金、密码未知、锁号失败、本地状态缺失。
- 增加远端锁号成功但本地创建失败时调用解锁且事务回滚的回归用例。
- 更新现代归属边界成功用例，使其提交真实身份、验证码和密码，而不是依赖旧绕过行为。
- 执行注销专项、所有 `FrontCancel` 功能测试、PHP 语法检查和相关注释可读性测试。

## 自检结论

设计没有 `TODO` 或待定项；现代与旧入口只保留响应格式差异，不保留业务校验差异。远端未知、数据库失败和重复提交均不会返回伪成功。
