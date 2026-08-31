# 前台入金与支付逐路由执行链报告

## 1. 范围与结论边界

本报告对应旧项目 `new_co_gmtk_crmV3` 的前台入金创建、支付回调、同步返回和 MT4 入账链，映射到新项目 `co_crmv5` 的实际执行路径。

核心安全约束：

- 入金创建只接受 POST；同步返回只接受 GET；异步通知只接受 POST。
- 没有启用且配置字段完整的白名单 Adapter 时，渠道不展示、订单不创建、回调不修改订单。密钥引用能否解析由具体 Adapter 在执行时确认。
- 金额只接受十进制字符串，使用 `DECIMAL(18,2)`/`DECIMAL(18,8)`，不经过浮点计算。
- `Idempotency-Key + user_id + gateway_code` 唯一；`local_order_no` 唯一。
- 同步 return 只展示 `pending`，不能证明支付成功。
- 异步 callback 必须先验签，再严格核对 gateway、merchant、currency、provider amount、local order 和 provider order。
- callback 事务只更新本地支付状态并创建唯一 outbox；MT4 资金操作在事务外由 Job 执行。
- 外部调用连接前失败可重试；写入后超时或本地提交失败进入 `unknown`，禁止自动重复资金操作。
- `deposit_records` 与 `payment_settlement_outbox` 均为 InnoDB，保证订单状态和 outbox 原子提交。

## 2. 旧项目入口映射

旧项目入口主要位于：

- `app/Http/routes.php` 的 `deposit_request`、`deposit_request_otc`、各渠道 notify/return 路由。
- `User/UserDepositController@deposit_request` 与 `deposit_request_otc`。
- `PayController/PayCallBackController` 的 Tiger、WP、Exlink、BTB、PassTo、Switch 回调方法。
- 各渠道配置 Controller 负责组装字段、签名和外部 HTTP 请求。

新项目将这些分散入口收敛为：

- 创建：`DepositController@submitDeposit`。
- 渠道解析：`PaymentGatewayRegistry`。
- 协议：每渠道一个 `PaymentGatewayAdapter`。
- 回调：`PaymentNotifyController@notify` + `PaymentCallbackService@handle`。
- 资金入账/退款：`SettleDepositPayment` / `RefundDepositPayment`。

## 3. 入金页面与查询路由

### 3.1 `GET /user/deposit`

- 路由名：`legacy_user_deposit_page`。
- Controller：`Front\LegacyPageController@deposit`。
- 执行链：浏览器请求 -> Blade 入金页 -> 前端 JS 请求 `/api/front/deposits/form-options`。
- 数据边界：页面本身不创建订单、不推断支付成功。

### 3.2 `GET /front/deposit`

- 路由名：`front_page_deposit`。
- Controller：Blade 闭包返回 `front_layui::deposit.index`。
- 执行链：登录会话 -> Blade -> `pages.js`/支付渠道管理器 -> form-options API。
- return 参数中的 `gateway/status` 仅用于展示；最终结果仍读取异步回调写入的订单状态。

### 3.3 `GET /api/front/deposits/form-options`

- 路由名：`front_api_deposits_form_options`。
- 中间件：`jwt.auth:user` -> `sso:user`。
- Controller：`DepositController@depositPage`。
- 执行链：
  1. `legacyFrontUserInfo()` 解析当前用户，失败返回认证错误。
  2. `depositAvailability()` 检查用户禁入金标记、系统总开关、周末开关和每日时间窗。
  3. `PaymentChannel::enabled()` 读取启用渠道。
  4. `PaymentGatewayRegistry::resolve()` 校验渠道 code、Adapter 白名单、merchant/app、endpoint、secret/key reference 格式、currency、amount unit、notify route、return route。
  5. 仅返回可执行渠道的公开字段：名称、code、汇率、币种、限额、类型、说明和 remark items。
  6. `_adapter`、`_config` 和任何密钥值都不会进入响应。
- 失败关闭：数据库无渠道或配置不完整时 `data.channels=[]`，不生成旧 1-11 虚拟 fallback。

### 3.4 `GET /api/front/deposits/history`

- 路由名：`front_api_deposits_history`。
- 中间件：`jwt.auth:user` -> `sso:user`。
- Controller：`DepositController@depositHistory`。
- 执行链：认证用户 -> 可选状态/时间/分页校验 -> `deposit_records.user_id=current user` -> 状态文本与退款文本转换 -> JSON 分页响应。
- 所有权：查询始终绑定当前用户，不能读取他人订单。

## 4. 入金创建路由

### 4.1 `POST /api/front/deposits/submissions`

- 路由名：`front_api_deposits_submissions`。
- 中间件：`jwt.auth:user` -> `sso:user` -> Laravel CSRF/API 边界。
- Controller：`DepositController@submitDeposit`。
- 请求字段：`amount`、`channel`；Header `Idempotency-Key` 必填。
- 详细执行链：
  1. 解析当前用户及 `user_infos`，确认允许入金。
  2. `Idempotency-Key` 仅允许 1-100 位安全字符。
  3. `Money::fromDecimalString()` 拒绝数字 JSON、科学计数、三位小数、零、负数、越界和 DECIMAL 溢出。
  4. `resolvePaymentChannel()` 只接受数据库中启用且 Registry 可解析的渠道。
  5. 再按渠道 `min_amount/max_amount` 精确校验。
  6. `PaymentOrderService::createOrRetrieve()` 在事务中按 `idempotency_key + user_id + gateway_code` 锁定/创建订单。
  7. 相同 key、相同金额返回原订单；相同 key、不同金额返回冲突。
  8. 订单快照写入 merchant、gateway、currency、provider amount、汇率和 channel config 语义。
  9. CAS 将 `payment_status` 从 `pending` 改为 `provider_create_in_progress`，防止并发重复建单。
  10. 数据库事务结束后调用 Adapter `createOrder()`，外部 HTTP 不在本地事务中。
  11. Adapter 结果 gateway 必须与订单一致；成功保存 provider order 和安全结果快照，再返回 redirect/form。
  12. 建单外部结果不确定时标记 `provider_create_unknown`，禁止自动重复发起。
- 响应：`CREATED` 只表示支付入口创建成功，不表示已到账。

### 4.2 `POST /user/deposit_request`

- 路由名：`legacy_user_deposit_request`。
- Controller：`DepositController@deposit_request`。
- 兼容字段：
  - `deposit_amt_usd`/`deposit_amt` -> `amount`。
  - `pay_channel`/`passageway` -> `channel`。
- 后续链：完全复用 `submitDeposit()`，没有独立弱校验或静态 payment URL 成功分支。

### 4.3 `POST /user/deposit_request_otc`

- 路由名：`legacy_user_deposit_request_otc`。
- Controller：`DepositController@deposit_request_otc`。
- 执行链：OTC 旧字段归一化 -> `deposit_request()` -> `submitDeposit()`。
- 当前边界：只有配置完整、Registry 支持且 Adapter 本身允许创建的 OTC 渠道才可用；当前 `OtcAdapter` 对不具备可验证建单协议的配置保持 fail-closed。

## 5. 现代回调与同步返回

### 5.1 `POST /api/front/payment/notify/{gateway}`

- 路由名：`front_api_payment_notify`。
- 认证：支付平台服务器入口，不使用用户 JWT。
- CSRF：只豁免精确支付 notify URI；不豁免创建和 return。
- Controller：`PaymentNotifyController@notify`。
- 详细执行链：
  1. 按 `channel_code={gateway}` 查启用渠道。
  2. 未知 gateway 返回 404；已知旧 gateway 但无完整配置返回 422。
  3. Registry 再次校验 Adapter 和完整配置。
  4. Adapter `verifyCallback()` 验签；失败返回 400，订单不变。
  5. Adapter `parseCallback()` 将渠道字段转换为统一 `PaymentCallback`。
  6. `PaymentCallbackService@handle()` 开启事务，按唯一 `local_order_no` `lockForUpdate()`。
  7. 严格核对 gateway、merchant、currency、精确 provider amount；已有 provider order 时还必须一致。
  8. 状态机拒绝非法倒退：success 不会被 failed 覆盖；退款不能早于 success；重复 success 幂等。
  9. 首次 success 写 `payment_status=success`、`settlement_status=pending`、真实 DATETIME `payment_time`。
  10. 同事务 `firstOrCreate(event_type, deposit_record_id)` 创建唯一 settlement outbox。
  11. 提交后按 outbox id `afterCommit()` 派发 Job；派发失败由每分钟 scanner 恢复。
  12. 只有上述处理成功后才调用 Adapter `acknowledge()` 返回渠道 ACK。
- 日志：只记录 gateway、path、payload hash、原因和异常类，不记录完整敏感 payload。

### 5.2 `GET /api/front/payment/return/{gateway}`

- 路由名：`front_api_payment_return`。
- Controller：`PaymentNotifyController@returnPage`。
- 执行链：忽略第三方传入的成功语义 -> 重定向 `front_page_deposit` -> 强制 `status=pending`。
- 数据写入：无。

## 6. Legacy callback/return 路由逐项映射

所有旧入口先进入 `PaymentNotifyController@legacyCallback`，再按路径分流到 `notify()` 或 `returnPage()`；不存在旧 Controller 直接改订单状态的旁路。

| HTTP | 路由 | 统一 gateway | 分流 | 最终执行 |
|---|---|---|---|---|
| POST | `/user/deposit_notfiy` | `legacy_default` | notify | Registry -> Adapter 验签 -> CallbackService -> ACK |
| POST | `/user/deposit_notfiy2` | `legacy_default_2` | notify | 同上 |
| POST | `/user/deposit_tigerpay_notify` | `tigerpay` | notify | Tiger RSA 验签/解密 -> 统一回调 |
| POST | `/user/deposit_wppay_notify` | `wppay` | notify | WP 签名 -> 统一回调 |
| GET | `/user/deposit_wppay_return` | `wppay` | return | 只重定向 pending |
| POST | `/user/deposit_exlink_bbnotify` | `exlink_bb` | notify | Exlink crypto 签名 -> 统一回调 |
| GET | `/user/deposit_exlink_bbreturn` | `exlink_bb` | return | 只重定向 pending |
| POST | `/user/deposit_exlink_fbnotify` | `exlink_fb` | notify | Exlink fiat 签名/pay type -> 统一回调 |
| GET | `/user/deposit_exlink_fbreturn` | `exlink_fb` | return | 只重定向 pending |
| POST | `/user/deposit_btb_notify` | `btb` | notify | BTB 签名 -> 统一回调 |
| GET | `/user/deposit_btb_return` | `btb` | return | 只重定向 pending |
| POST | `/user/deposit_passto_notify` | `passto` | notify | PassTo 签名 -> 统一回调 |
| POST | `/user/deposit_switch_notify` | `switch` | notify | Switch 签名/pay type -> 统一回调 |
| POST | `/user/deposit_notfiy_otc` | `otc_deposit` | notify | 仅完整可验证配置可执行，否则 422 |
| POST | `/user/withdraw_notfiy_otc` | `otc_withdraw_notify` | notify | 仅完整可验证配置可执行，否则 422 |
| POST | `/user/withdraw_verify_otc` | `otc_withdraw_verify` | notify | 仅完整可验证配置可执行，否则 422 |
| GET | `/user/deposit_return` | `legacy_default` | return | 只重定向 pending |
| GET | `/user/deposit_return2` | `legacy_default_2` | return | 只重定向 pending |

## 7. Adapter 与签名边界

| Adapter | 别名/旧通道 | 创建输出 | 回调保护 |
|---|---|---|---|
| `TigerPayAdapter` | `tiger`, `tigerpay`, `1` | 加密/RSA 协议请求 | RSA 验签、解密、订单/金额/商户/币种/状态核对 |
| `WpPayAdapter` | `wp`, `wppay`, `2` | WP 表单/JSON 请求 | 对称签名与 `hash_equals` |
| `ExlinkFiatAdapter` | `exlink_fb`, `3`, `6`, `7` | 明确 pay type profile | MD5/协议签名、字段一致性 |
| `ExlinkCryptoAdapter` | `exlink_bb`, `4` | crypto amount 语义 | 签名、币种和金额一致性 |
| `BtbAdapter` | `btb`, `5` | BTB query/form | 对称签名和状态核对 |
| `PassToAdapter` | `passto`, `8` | PassTo JSON | 对称签名和商户/订单核对 |
| `SwitchAdapter` | `switch`, `9`, `10`, `11` | pay type profile | 双密钥引用、签名和状态核对 |
| `OtcAdapter` | `otc` | 无可信协议时拒绝 | 不接受不可验证回调 |

所有 endpoint、merchant/app 和 secret/key 都来自运行时 `payment_channels.config` 与 `SecretReference`；源码和 fixture 不保存生产凭据。

## 8. Outbox 与 MT4 入账链

### 8.1 数据库契约

- `deposit_records`：InnoDB；`amount/actual_amount=DECIMAL(18,2)`；`exchange_rate=DECIMAL(18,8)`；`payment_time/refund_time=DATETIME`。
- 唯一索引：`local_order_no`；`idempotency_key + user_id + gateway_code`。
- `payment_settlement_outbox`：InnoDB；主键 `BIGINT UNSIGNED AUTO_INCREMENT`。
- 唯一索引：`event_type + deposit_record_id`；同一订单同一资金事件只能有一条。

### 8.2 `SettleDepositPayment`

1. 事务内锁 outbox 和订单，验证 event type、状态、available time、payload hash。
2. 将 outbox/订单改为 processing 后提交。
3. 事务外调用 `DepositSettlementGateway::deposit()`。
4. 成功：事务内写 MT4 ticket、订单 `settled/status=02`、outbox processed。
5. `retryable_not_sent`：恢复 pending/retryable 并设置退避时间。
6. `unknown/rejected`：终态化；unknown 不自动重试。
7. 外部成功但本地提交失败：订单和 outbox 标记 unknown，进入人工对账。

### 8.3 `RefundDepositPayment`

1. 仅处理 `deposit_refund` outbox，锁定并校验订单已 `refund_pending + settled`。
2. 事务外调用 MT4 refund gateway。
3. 成功写 `refund_mt4_ticket`、真实 `refund_time`、订单 refunded/status=05。
4. retryable 仅用于明确未发送；不确定结果进入 `refund_unknown/unknown`。
5. 入金 processing 时退款 outbox 为 blocked；入金终态后协调为 pending、processed 或 unknown。

### 8.4 每分钟恢复扫描

- Artisan：`payments:dispatch-deposit-settlements`。
- 调度：每分钟执行且禁止重叠。
- 扫描：pending、到期 retryable、过期 processing。
- 分发：`deposit_settlement` -> `SettleDepositPayment`；`deposit_refund` -> `RefundDepositPayment`。
- 错误 event type 安全 no-op/终态化，不会误执行资金操作。

## 9. 状态机摘要

支付状态：

`pending -> provider_create_in_progress -> pending -> success|failed`

不确定建单：

`provider_create_in_progress -> provider_create_unknown`

入账状态：

`pending -> processing -> settled|retryable|unknown|rejected`

退款状态：

`success + settled -> refund_pending -> refund_processing -> refunded|refund_unknown|refund_rejected`

关键不变量：success 不回退为 failed；settled 必须有数字 MT4 ticket；refunded 必须有退款 MT4 ticket；unknown 不自动重复资金操作。

## 10. 配置可用性与部署阻断项

渠道只有同时满足以下条件才可用：

- `is_enabled=1`。
- `channel_code` 与请求 gateway 一致。
- `adapter` 在 Registry 白名单内。
- `merchant_id` 或 `app_id` 非空。
- endpoint 非空。
- `secret_reference` 或 `key_reference` 格式有效。实际密钥由具体 Adapter 在创建订单或验签时解析。
- currency 在 Adapter 支持列表中。
- `amount_unit`、`notify_route`、`return_route` 完整。

缺少真实 merchant/endpoint 会使 Registry 拒绝渠道；密钥引用格式有效但运行时无法解析时，Adapter 会 fail-closed。创建阶段已生成的本地幂等订单会进入 `provider_create_unknown`，不得自动重复调用。测试 fixture 和本地 fake provider 只能证明协议与状态机，不代表生产渠道已开通。

## 11. 验证证据

- Payment/deposit 逐文件首轮：32/33 文件通过；发现 `FrontUiRegressionTest` 仍要求不安全 fallback。
- 修正规格后：`FrontUiRegressionTest` fresh `137 tests / 2983 assertions`。
- outbox InnoDB RED：实际得到 MyISAM，测试正确失败。
- 修复后迁移重入：`10 tests / 26 assertions`。
- information_schema：strict SQL mode 开启；`deposit_records` 与 `payment_settlement_outbox` 均为 InnoDB；金额/汇率/DATETIME/唯一索引符合契约。
- fake provider：`create -> redirect -> HMAC signed callback -> duplicate callback -> unique settlement outbox`，fresh `1 test / 14 assertions`。
- 修复后的最终逐文件回归：34 个独立文件，合计 `491 tests / 6106 assertions`，`FAILED_FILES=0`；包含全部 payment/deposit 测试、`FrontLegacyRouteCompatibilityTest`、`FrontendRouteManifestTest` 和 `FrontUiRegressionTest`。
- 浏览器 fresh 冒烟：`customer1@test.com` 登录后真实跳转 `/front/dashboard`；受保护的 `/front/deposit?frame=1` 显示入金账号、入金金额、汇率、实付金额、支付通道、提交按钮和历史表格；控制台 `0` error。数据库无完整渠道配置时通道区域不展示可用项，符合 fail-closed。

## 12. 当前报告状态

支付实现链、数据库链和自动化测试链已记录；Task 6 最终封板仍要求：

1. 通过独立规格审查与代码质量审查。
