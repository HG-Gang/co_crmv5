# 逐路由执行链路详解 —— 项目2新 co_crmv5

> **文档版本**: v1.0 | **生成日期**: 2026-08-01
> **路由文件来源**: `routes/front.php`（82条）、`routes/admin.php`（281条）| **总计**: 363 条 API 路由

---

## 目录

1. [新架构总览](#新架构总览)
2. [中间件三件套详解](#中间件三件套详解)
3. [统一响应码对照表（39个状态码）](#统一响应码对照表)
4. [数据库表映射（56个Model → 56张表）](#数据库表映射)
5. [前台模块 — 逐路由详解（82条）](#前台模块)
6. [后台模块 — 逐路由详解（281条）](#后台模块)

---

## 新架构总览

### 与旧项目 V3 的架构差异

| 维度 | 旧项目 V3 | 新项目 co_crmv5 |
|---|---|---|
| **认证方式** | PHP Session / Cookie 会话 | JWT Bearer Token (Firebase\JWT\JWT) |
| **单点登录** | 无（同一账号可多端同时登录） | Redis SSO 缓存 `sso:{guard}:{sub}`，强制单设备在线 |
| **权限鉴权** | 控制器内 `if/else` 硬编码判断 | 中间件链 `check.permission:admin` → permissions 表动态匹配 |
| **路由与权限绑定** | 无命名路由，直接 URL 字符串匹配 | Laravel named route + permissions.api_route 精确匹配 |
| **白名单** | 硬编码在控制器 Base 层 | `CheckPermission::isPermissionWhiteRoute()` 集中声明 |
| **超级管理员** | 特殊 ID 硬编码判断 | `isSuperAdmin()` 方法集中判断 (id=1 或 role.name=super_admin) |
| **多语言** | 中文硬编码 | `__('response.*')` Laravel 语言包，支持 zh-CN / en |
| **响应格式** | 各控制器自行组装 JSON | `ApiResponse` trait 统一输出 `{code, message, data}` |
| **中间件分组** | 无（路由组仅做前缀分组） | 前台 `jwt.auth:user → sso:user`；后台 `jwt.auth:admin → sso:admin → check.permission:admin` |
| **数据范围** | 管理员可见全部数据 | `AdminDataScopeService` 按角色数据范围过滤 |
| **文件上传** | 各模块自行处理 | 统一 UploadController (Common命名空间，跨命名空间调用) |
| **异步队列** | 无 | Outbox出箱模式 + Laravel Job 异步处理（MT4入出金/返佣转账/支付结算） |

---

## 中间件三件套详解

```
HTTP请求进入
  |
  +--(1) JwtAuthMiddleware (jwt.auth:{guard})
  |    +-- 检查 Authorization: Bearer {token} 请求头
  |    +-- 正则匹配: /Bearer\s+(.*)$/i → 不匹配 → code=4004 "令牌缺失"
  |    +-- JwtService::parseToken() 解析JWT签名
  |    |    +-- Firebase\JWT\JWT::decode(token, secret, algo)
  |    |    +-- secret = config('jwt.secret') . config('jwt.custom_salt')
  |    |    +-- algo = HS256 (默认)
  |    |    +-- 签名/过期验证失败 → code=4001 "认证失败" / code=4002 "令牌已过期"
  |    +-- 提取载荷: sub(用户ID)、guard(user/admin)、jti(令牌唯一ID)、iat、exp、iss
  |    +-- Auth::guard(guard)->getProvider()->retrieveById(sub) 检索用户
  |    |    +-- 用户不存在 → code=2008 "用户不存在"
  |    +-- Auth::guard(guard)->setUser($user) 注入认证用户到Auth门面
  |    +-- request->attributes 写入: jwt_payload、jwt_guard、jwt_token
  |    +-- 放行 → 进入下一个中间件
  |
  +--(2) SingleSignOn (sso:{guard})
  |    +-- 读取 jwt_payload.sub、.guard、.jti
  |    |    +-- 载荷缺失 → code=4001 "认证失败"
  |    +-- 构造 Redis 缓存键: cacheKey = "sso:{guard}:{sub}"
  |    |    +-- 示例: "sso:user:12345" 或 "sso:admin:1"
  |    +-- activeJti = Cache::get(cacheKey) 从Redis读取当前有效jti
  |    +-- 比对: activeJti !== payload.jti → code=4003 "已在其他地方登录/SSO冲突"
  |    |    +-- Cache中无key（被forceOffline或logout清理）→ code=4003
  |    +-- 一致 → 放行 → 进入下一个中间件或控制器
  |    +-- 注: 登录/刷新Token时JwtService::generateToken()会更新此Redis key
  |
  +--(3) CheckPermission (check.permission:admin) [仅后台，前台不使用]
       +-- $user = Auth::guard(guardType)->user()
       |    +-- 未登录 → code=4001 "认证失败"
       +-- $routeName = optional($request->route())->getName()
       |    +-- 例如: "admin_api_userList"
       +-- 白名单检查: isPermissionWhiteRoute($routeName)
       |    +-- 名单: logout、refreshToken、menus、profileInfo、updateProfile、changePassword、uploadAvatar
       |    +-- 命中 → 直接放行 (仍需已完成JWT+SSO)
       +-- 超级管理员检查: isSuperAdmin($user)
       |    +-- 条件1: (int)$user->id === 1
       |    +-- 条件2: $user->role && $user->role->name === 'super_admin'
       |    +-- 命中 → 直接放行
       +-- 角色检查: $user->role存在?
       |    +-- 不存在 → code=4006 "权限不足"
       +-- 权限记录匹配
       |    +-- Permission::where('guard_type', guardType)
       |        →where('api_route', routeName)
       |        →where('status', 1)→first()
       |    +-- 不存在 → code=4006 "权限不足"
       +-- 角色权限交叉验证
       |    +-- $user->role->permissionsRelation()
       |        →where('permissions.id', $permission->id)
       |        →where('permissions.status', 1)→exists()
       |    +-- 角色不拥有该权限 → code=4006 "权限不足"
       +-- 通过 → 放行 → 进入控制器方法
```

### 关键源代码位置

| 组件 | 文件路径 | 行号 |
|---|---|---|
| JwtAuthMiddleware | `app\Http\Middleware\JwtAuthMiddleware.php` | :40 |
| SingleSignOn | `app\Http\Middleware\SingleSignOn.php` | :27 |
| CheckPermission | `app\Http\Middleware\CheckPermission.php` | :46 |
| JwtService::generateToken | `app\Services\JwtService.php` | :68 |
| ApiResponse trait::success | `app\Traits\ApiResponse.php` | :32 |
| ApiResponse trait::error | `app\Traits\ApiResponse.php` | :56 |
| ResponseCode常量 | `app\Constants\ResponseCode.php` | :16 |

---

## 统一响应码对照表（全部39个状态码）

### 1xxx 成功类 (6个)

| 响应码 | 常量名 | 中文含义 | 触发场景 |
|---|---|---|---|
| 1000 | SUCCESS | 操作成功 | 通用成功响应，大部分接口默认返回 |
| 1001 | CREATED | 创建成功 | 新增记录完成 |
| 1002 | UPDATED | 更新成功 | 修改记录完成 |
| 1003 | DELETED | 删除成功 | 删除记录完成 |
| 1004 | UPLOADED | 上传成功 | 文件上传完成 |
| 1015 | DEFAULT_ADDRESS_MUST_EXIST | 必须保留一个默认收货地址 | 删除唯一默认地址时触发 |

### 2xxx 业务逻辑类 (26个)

| 响应码 | 常量名 | 中文含义 | 触发场景 |
|---|---|---|---|
| 2000 | REGISTER_SUCCESS | 注册成功 | 前台用户注册完成（含MT4同步成功） |
| 2001 | EMAIL_EXISTS | 邮箱已存在 | 注册/修改邮箱时邮箱已被注册 |
| 2002 | PHONE_EXISTS | 手机号已存在 | 注册/修改手机号时手机号已被占用 |
| 2003 | INVALID_INVITER | 邀请人无效 | 注册时提供的邀请人ID不存在或非代理 |
| 2004 | INVITER_DISABLED | 邀请人已禁用 | 注册时邀请人账号状态为禁用 |
| 2005 | INVALID_COMMISSION_RATE | 返佣比例无效 | 创建代理时佣金比例不在配置范围内 |
| 2006 | INVALID_GROUP | 组别无效 | 分配的组别ID不存在 |
| 2007 | INVALID_AGENT_LEVEL | 代理级别无效 | 分配代理级别时级别不存在 |
| 2008 | USER_NOT_FOUND | 用户不存在 | 查询用户时ID不存在 / JWT解析后用户被删 |
| 2009 | USER_DISABLED | 用户已禁用 | 操作用户时该用户 status != 1 |
| 2010 | USER_CANCELLED | 用户已注销 | 操作用户时该用户已申请/完成注销 |
| 2011 | INVALID_AUDIT_STATUS | 审核状态无效 | 审核操作时当前状态不允许该操作 |
| 2012 | WITHDRAWAL_NOT_ALLOWED | 出金不允许 | 存在未平仓持仓或余额不足 |
| 2013 | DEPOSIT_NOT_ALLOWED | 入金不允许 | 通道关闭 / 用户状态不可入金 |
| 2014 | INVALID_AMOUNT | 金额无效 | 入金/出金金额不在允许范围 |
| 2015 | INSUFFICIENT_BALANCE | 余额不足 | 出金金额大于可用余额 |
| 2016 | RISK_RATE_EXCEEDED | 风险率超限 | 当前风险率超过风控阈值 |
| 2017 | CANCEL_APPLY_EXISTS | 注销申请已存在 | 用户已有未处理的注销申请 |
| 2018 | BLACKLISTED | 黑名单用户 | 用户存在于 blacklists 表中 |
| 2019 | DATA_NOT_FOUND | 数据不存在 | 查询单条记录时无匹配 |
| 2020 | DATA_ALREADY_EXISTS | 数据已存在 | 创建记录时唯一键冲突 |
| 2021 | OPERATION_NOT_ALLOWED | 操作不允许 | 业务规则禁止当前操作 |
| 2022 | COMMISSION_EXCEEDS_PARENT | 返佣比例不能大于上级 | 下级佣金比例设置超过上级 |
| 2023 | SETTLEMENT_NOT_FOUND | 结算记录不存在 | 佣金结算依据不存在 |
| 2024 | ORDER_NOT_FOUND | 订单不存在 | 交易订单ID查无此单 |
| 2025 | MT4_SYNC_FAILED | MT4同步失败 | 注册成功但MT4开通异步处理中 |

### 3xxx 数据操作类 (7个)

| 响应码 | 常量名 | 中文含义 | 触发场景 |
|---|---|---|---|
| 3000 | QUERY_SUCCESS | 查询成功 | 列表查询完成 |
| 3001 | QUERY_FAILED | 查询失败 | 数据库查询异常 |
| 3002 | IMPORT_SUCCESS | 导入成功 | 批量CSV导入完成 |
| 3003 | IMPORT_FAILED | 导入失败 | CSV格式/数据校验不通过 |
| 3004 | EXPORT_SUCCESS | 导出成功 | CSV导出生成完成 |
| 3005 | BATCH_SUCCESS | 批量操作成功 | 批量审核/导入全部成功 |
| 3006 | BATCH_PARTIAL_FAILED | 批量操作部分失败 | 批量操作有部分失败 |

### 4xxx 认证授权类 (10个)

| 响应码 | 常量名 | 中文含义 | 触发场景 |
|---|---|---|---|
| 4000 | ERROR | 操作失败 | 通用错误兜底 |
| 4001 | AUTH_FAILED | 认证失败 | 密码错误 / JWT解析失败 / 签名无效 |
| 4002 | TOKEN_EXPIRED | 令牌已过期 | JWT的exp时间戳小于当前时间 |
| 4003 | SSO_CONFLICT | 已在其他地方登录 | Redis中jti与请求token的jti不一致 |
| 4004 | TOKEN_MISSING | 令牌缺失 | Authorization头缺失或格式不是 Bearer xxx |
| 4005 | VALIDATION_FAILED | 参数验证失败 | Laravel Validator校验不通过 |
| 4006 | PERMISSION_DENIED | 权限不足 | 角色不拥有该接口的permissions记录 |
| 4007 | ACCOUNT_LOCKED | 账户已锁定 | 登录失败超限或管理员锁定 |
| 4008 | OLD_PASSWORD_WRONG | 旧密码不正确 | 修改密码时旧密码验证失败 |
| 4009 | RATE_LIMITED | 频率限制 | 同一邮箱120秒内重复注册 |

### 5xxx 系统错误类 (5个)

| 响应码 | 常量名 | 中文含义 | 触发场景 |
|---|---|---|---|
| 5000 | SERVER_ERROR | 服务器内部错误 | 未捕获异常 / 配置错误 |
| 5001 | DB_ERROR | 数据库错误 | SQL执行失败 / 连接超时 / 死锁 |
| 5002 | FILE_UPLOAD_FAILED | 文件上传失败 | 格式不支持 / 大小超限 |
| 5003 | EMAIL_SEND_FAILED | 邮件发送失败 | SMTP连接失败 / 发送超时 |
| 5004 | THIRD_PARTY_ERROR | 第三方接口错误 | MT4接口异常 / 支付网关超时 |

> **重要**: 旧项目V3使用 `loginStatus` 字段和 string 类型 status 标识。新项目统一为 `code` int + `message` 多语言文案。部分旧接口兼容层仍返回 `code=1000` 作成功码。

---

## 数据库表映射（56个Model → 56张表）

| Model | 表名 | 用途 |
|---|---|---|
| Admin | admins | 后台管理员 |
| AdminAgentBinding | admin_agent_bindings | 管理员-代理节点绑定 |
| AdminLoginLog | admin_login_logs | 后台登录审计日志 |
| AdminRole | admin_roles | 管理员-角色关联 |
| AgentDescendant | agent_descendants | 代理家族树/关系链（递归CTE） |
| AgentLevel | agent_levels | 代理级别定义 |
| AgentNodeStats | agent_node_stats | 代理节点统计数据 |
| BigAgent | big_agents | 大代理账号 |
| BigAgentLoginLog | big_agent_login_logs | 大代理登录日志 |
| Blacklist | blacklists | 黑名单 |
| CancelApply | cancel_applies | 注销申请 |
| CommissionRebatePayout | commission_rebate_payouts | 返佣发放记录 |
| CommissionRecord | commission_records | 佣金结算记录 |
| CommissionTransfer | commission_transfers | 佣金转账记录 |
| CommissionTransferOutbox | commission_transfer_outboxes | 佣金转账出箱（异步Job处理） |
| CreditImport | credit_imports | 批量信用导入 |
| DataOperationLog | data_operation_logs | 数据操作日志 |
| DataScope | data_scopes | 数据范围配置 |
| DepositImport | deposit_imports | 批量入金导入 |
| DepositRecord | deposit_records | 入金记录 |
| GiftItem | gift_items | 礼品项目定义 |
| GiftShipment | gift_shipments | 礼品发货记录 |
| GroupConfig | group_configs | 组别配置 |
| IdSequence | id_sequences | 用户编号序列（自增发号器） |
| MailSetting | mail_settings | 邮件配置 |
| Menu | menus | 菜单配置 |
| Mt4Trade | mt4_trades | MT4交易记录 |
| Mt4User | mt4_users | MT4用户资金快照 |
| News | news | 新闻公告 |
| OperationLog | operation_logs | 操作日志 |
| PaymentChannel | payment_channels | 支付通道 |
| PaymentSettlementOutbox | payment_settlement_outboxes | 支付结算出箱（异步） |
| Permission | permissions | 权限/菜单字典（按 guard_type + api_route 索引） |
| Role | roles | 角色 |
| RoleDataScope | role_data_scopes | 角色数据范围关联 |
| SpreadConfig | spread_configs | 点差配置 |
| SymbolPrice | symbol_prices | 交易品种实时报价 |
| SystemConfig | system_configs | 系统配置 |
| TransApplyLog | trans_apply_logs | 交易申请日志 |
| User | users | 用户（兼容User模型） |
| UserAddress | user_addresses | 用户收货地址 |
| UserAuth | user_auths | 用户认证（身份证/银行卡/审核状态） |
| UserAuthInfo | user_auth_infos | 认证附加信息 |
| UserGroup | user_groups | 用户分组 |
| UserInfo | user_infos | 用户业务信息（余额/MT4账号/级别/组别） |
| UserLogin | user_logins | 用户登录表（邮箱/密码/状态） |
| UserLoginLog | user_login_logs | 用户登录日志（登录/退出/IP/UA） |
| UserMt4ProvisioningOutbox | user_mt4_provisioning_outboxes | MT4账户开通出箱（异步） |
| UserOnline | user_onlines | 在线用户记录 |
| UserTrade | user_trades | 用户交易记录 |
| VoucherInfo | voucher_infos | 入金凭证 |
| WhsExpZero | whs_exp_zeros | 体验金清零记录 |
| WithdrawImport | withdraw_imports | 批量出金导入 |
| WithdrawRecord | withdraw_records | 出金记录 |
| WithdrawSettlementOutbox | withdraw_settlement_outboxes | 出金结算出箱（异步） |

---

## 前台模块

> **路由前缀**: `/api/front` | **控制器命名空间**: `App\Http\Controllers\Front` | **总计**: 82条路由
>
> **保护中间件**: `jwt.auth:user → sso:user`（公开路由除外）| **前台不使用**: check.permission

---

### 一、用户注册登录（6条公开路由）

---

## 路由: POST /api/front/auth/login

- **路由名称**: `front_api_auth_login`
- **调用控制器方法**: `AuthController@login`
- **中间件链路**: 无（公开）
- **为什么这样做（业务目的）**: 旧项目V3使用PHP Session登录，`$_SESSION['user']` 存用户信息，无法做SSO单点登录且跨域不共享。新架构改为JWT Token，`JwtService::generateToken()` 同步写入Redis `sso:user:{sub}` 实现单设备登录。支持统一account字段自动识别邮箱/用户编号登录。
- **入参**:
  - `account`: string, 可选, 统一账号（自动判断email/user_id），V3对应 `$_POST['email']`/`$_POST['user_id']`
  - `email`: string, 可选, 邮箱登录
  - `user_id`: string, 可选, 用户编号登录
  - `password`: string, 必填, 登录密码
- **执行步骤（详细链路）**:
  1. 读取account/email/user_id判定登录方式（含@→邮箱，数字→用户编号）
  2. 邮箱登录: `UserLogin::where('email', email)->where('user_type', 1)->first()`
  3. 用户编号登录: `UserInfo::where('user_id', userId)->first()` 再找UserLogin
  4. 检查 `status != 1` → code=2009 "用户已禁用"
  5. `Hash::check(password, userLogin.password)` → 不匹配→code=4001
  6. `JwtService::generateToken(['sub'=>userLogin.id, 'guard'=>'user'])` 签发JWT+写SSO缓存
  7. `UserLoginLog::create()` 记录登录审计
  8. 返回 `access_token`, `token_type=Bearer`, `expires_in`, 用户基础信息
- **涉及的数据库表**: `user_logins`, `user_infos`, `user_login_logs`
- **返回值（成功）**: `{"code":1000,"message":"操作成功","data":{"access_token":"eyJ...","token_type":"Bearer","expires_in":3600,"user":{"id":42,"user_id":1001,"email":"user@example.com"}}}`
- **各执行结果中文含义**: code=1000(成功), 2008(用户不存在), 2009(已禁用), 4001(密码错误), 4005(password为空), 5000(异常)

---

## 路由: POST /api/front/auth/register

- **路由名称**: `front_api_auth_register`
- **调用控制器方法**: `AuthController@register`
- **中间件链路**: 无（公开）
- **为什么这样做（业务目的）**: 旧项目V3注册分散在 `register.php`、`register_post.php`。新架构统一到 `AuthController@register` + `UserRegistrationService` 事务管理四表同步写入（user_logins→user_infos→user_auths→agent_descendants），并通过 `UserMt4ProvisioningOutbox` 出箱异步开MT4账户。新增邮箱验证码+图形验证码双重防刷。
- **入参**:
  - `email`: string, 必填, 注册邮箱
  - `password`: string, 必填, 最少6位
  - `password_confirmation`: string, 必填
  - `user_name`: string, 必填, 姓名
  - `phone_code`: string, 必填, 区号
  - `phone_number`: string, 必填, 手机号
  - `phone`: string, 必填, 完整手机号
  - `id_card_no`: string, 必填, 身份证号
  - `gender`: int, 可选, 1=男2=女
  - `account_type`: int, 必填, 1=代理2=客户
  - `inviter_id`: int, 可选, 邀请人编号
  - `captcha_key`: string, 必填, 图形验证码key
  - `captcha_code`: string, 必填, 图形验证码值
  - `email_code`: string, 必填, 邮箱验证码
  - `agree_terms`: bool, 必填
- **执行步骤（详细链路）**:
  1. `Validator::make()` 15字段校验 → 失败→code=4005
  2. `Cache::lock('register_submit_lock_'.sha1(email), 120s)` 防重复提交
  3. `verifyRegisterCaptcha()`: Cache读取captcha_key比对
  4. `verifyRegisterEmailCode()`: Cache读取 `register_email_code_{email}` 比对
  5. `UserRegistrationService::validateRegistration()`: 校验邀请人/佣金规则/组别
  6. `UserRegistrationService::register()`: 事务写四表+ `IdSequence` 生成编号
  7. `UserMt4ProvisioningOutbox` 出箱异步开MT4
  8. 发送 `FrontRegistrationSuccessNotification` 欢迎邮件
  9. `JwtService::generateToken()` 签发Token
  10. 消费验证码缓存
- **涉及的数据库表**: `user_logins`, `user_infos`, `user_auths`, `agent_descendants`, `id_sequences`, `user_mt4_provisioning_outboxes`
- **返回值（成功）**: `{"code":1000,"message":"注册成功","data":{"access_token":"eyJ...","token_type":"Bearer","expires_in":3600,"user":{"id":42,"user_id":1001,"email":"user@example.com"}}}`
- **各执行结果中文含义**: code=1000(成功), 2001(邮箱已存在), 2002(手机号已存在), 2003(邀请人无效), 2004(邀请人禁用), 2005(返佣比例无效), 2006(组别无效), 2007(级别无效), 2025(MT4同步中), 4005(参数校验失败), 4009(频率限制), 5000(异常)

---

## 路由: GET /api/front/auth/register/captcha

- **路由名称**: `front_api_auth_register_captcha`
- **调用控制器方法**: `AuthController@registerCaptcha`
- **中间件链路**: 无（公开）
- **为什么这样做（业务目的）**: 旧项目GD库直接 `imagepng()` 输出图片到浏览器。新架构生成验证码存Cache(key,code,300sTTL)→返回JSON(含base64图片+captcha_key)，不依赖Session。
- **入参**: 无
- **执行步骤（详细链路）**:
  1. 生成4位随机验证码字符串
  2. 生成32位唯一 `captcha_key`
  3. GD库绘制图片→转base64
  4. `Cache::put('register_captcha_'.captcha_key, 验证码, 300s)`
  5. 返回 `captcha_key` 和 `captcha_img` (base64)
- **涉及的数据库表**: 无
- **返回值（成功）**: `{"code":1000,"data":{"captcha_key":"a1b2...","captcha_img":"data:image/png;base64,..."}}`

---

## 路由: POST /api/front/auth/register/email-code

- **路由名称**: `front_api_auth_register_email_code`
- **调用控制器方法**: `AuthController@registerSendCode`
- **中间件链路**: 无（公开）
- **为什么这样做（业务目的）**: 旧项目SDK直接调用无统一封装。新架构用 `FrontRegistrationVerificationCode` Mailable + Laravel Mail facade + Cache存验证码(300sTTL)。
- **入参**: `email`: string, 必填; `captcha_key`: string, 必填; `captcha_code`: string, 必填
- **执行步骤（详细链路）**:
  1. Validator校验email
  2. Cache读取图形验证码比对→失败→code=4005
  3. `UserLogin::where('email',email)->exists()` → 已存在→code=2001
  4. 生成6位数字验证码
  5. `Cache::put('register_email_code_'.email, 验证码, 300s)`
  6. `Mail::to(email)->send(new FrontRegistrationVerificationCode(code))` → 失败→code=5003
- **涉及的数据库表**: `user_logins`
- **返回值（成功）**: `{"code":1000,"message":"操作成功","data":{}}`

---

## 路由: POST /api/front/auth/register/verify

- **路由名称**: `front_api_auth_register_verify`
- **调用控制器方法**: `AuthController@registerVerifyInfo`
- **中间件链路**: 无（公开）
- **为什么这样做（业务目的）**: 旧项目注册一次性提交无分步验证。新架构独立验证码校验接口减轻注册主接口压力。
- **入参**: `email`: string, 必填; `email_code`: string, 必填
- **执行步骤**: Validator校验→Cache读取 `register_email_code_{email}` 比对→不一致/过期→code=4005
- **返回值（成功）**: `{"code":1000,"message":"操作成功","data":{}}`

---

## 路由: GET /api/front/auth/email/check

- **路由名称**: `front_api_auth_email_check`
- **调用控制器方法**: `AuthController@checkEmail`
- **中间件链路**: 无（公开）
- **为什么这样做（业务目的）**: 旧项目注册提交时才校验重复，体验差。新架构独立接口前端AJAX实时查询邮箱是否可用。
- **入参**: `email`: string, 必填
- **执行步骤**: Validator校验→`UserLogin::where('email',email)->exists()` → 已存在→code=2001
- **涉及的数据库表**: `user_logins`
- **返回值（成功）**: `{"code":1000,"data":{"available":true}}` (true=可用,false=已注册)

---

### 二、密码重置（3条公开路由）

---

## 路由: POST /api/front/auth/password/email-code

- **路由名称**: `front_api_auth_password_email_code`
- **调用控制器方法**: `ForgotPasswordController@sendResetCode`
- **中间件链路**: 无（公开）
- **为什么这样做（业务目的）**: 旧项目密码重置通过管理员后台手动操作。新架构用户自助通过邮箱验证码重置。
- **入参**: `email`: string, 必填; `captcha_key`: string, 可选; `captcha_code`: string, 可选
- **执行步骤**: Validator→查user_logins确认邮箱存在(code=2008)→校验图形验证码→生成6位重置码→`Cache::put('password_reset_code_'.email, code, 600s)`→发送邮件(code=5003)
- **涉及的数据库表**: `user_logins`
- **返回值（成功）**: `{"code":1000,"message":"操作成功","data":{}}`

---

## 路由: POST /api/front/auth/password/reset

- **路由名称**: `front_api_auth_password_reset`
- **调用控制器方法**: `ForgotPasswordController@resetPassword`
- **中间件链路**: 无（公开）
- **为什么这样做（业务目的）**: 旧项目无自助重置。新架构两步完成重置，完成后清除SSO缓存强制所有设备重新登录。
- **入参**: `email`: string, 必填; `email_code`: string, 必填; `password`: string, 必填(>=6位); `password_confirmation`: string, 必填
- **执行步骤**: Validator→Cache读取 `password_reset_code_{email}` 比对→UserLogin::where→`UserPasswordService::resetPassword()`→`Cache::forget('sso:user:'.id)` 清SSO→删除重置码缓存
- **涉及的数据库表**: `user_logins`
- **返回值（成功）**: `{"code":1000,"message":"操作成功","data":{}}`
- **各执行结果**: code=1000(成功), 2008(用户不存在), 4005(验证码错误), 5001(DB错误)

---

## 路由: GET /api/front/auth/inviter

- **路由名称**: `front_api_auth_inviter`
- **调用控制器方法**: `AuthController@validateInviter`
- **中间件链路**: 无（公开）
- **为什么这样做（业务目的）**: 旧项目提交注册时才校验邀请人。新架构注册页输入邀请码时实时AJAX查邀请人姓名头像。
- **入参**: `inviter_id`: string, 必填, 邀请人编号
- **执行步骤**: Validator→`UserInfo::where('user_id',inviter_id)->first()`→不存在→code=2003→查UserLogin status→禁用→code=2004→返回姓名头像
- **涉及的数据库表**: `user_infos`, `user_logins`
- **返回值（成功）**: `{"code":1000,"data":{"inviter_name":"张三","inviter_avatar":"https://..."}}`

---

### 三、大代理登录（2条）

---

## 路由: GET /api/front/auth/big-number/captcha

- **路由名称**: `front_api_auth_big_number_captcha`
- **调用控制器方法**: `BigNumberController@captcha`
- **中间件链路**: `web`（保留Session读取中间件）
- **为什么这样做（业务目的）**: 旧大代理登录验证码在Session中。新架构保留web中间件兼容旧流程同时写Cache(新流程)。
- **入参**: 无
- **返回值（成功）**: `{"code":1000,"data":{"captcha_img":"data:image/png;base64,..."}}`

---

## 路由: POST /api/front/auth/big-number/login

- **路由名称**: `front_api_auth_big_number_login`
- **调用控制器方法**: `BigNumberController@login`
- **中间件链路**: 无（公开）
- **为什么这样做（业务目的）**: 大代理(超级代理)独立登录，统一JWT但guard='user'，`big_agent_login_logs` 独立审计。
- **入参**: `username`: string, 必填; `password`: string, 必填; `captcha_key`: 可选; `captcha_code`: 可选
- **执行步骤**: 校验验证码→查 `big_agents` 表→`Hash::check`→`JwtService::generateToken`→写 `big_agent_login_logs`
- **涉及的数据库表**: `big_agents`, `big_agent_login_logs`
- **返回值（成功）**: `{"code":1000,"data":{"access_token":"eyJ...","token_type":"Bearer","expires_in":3600}}`

---

### 四、支付回调（2条公开路由）

---

## 路由: POST /api/front/payment/notify/{gateway}

- **路由名称**: `front_api_payment_notify`
- **调用控制器方法**: `PaymentNotifyController@notify`
- **中间件链路**: 无（支付网关回调不能加JWT）
- **为什么这样做（业务目的）**: 旧项目回调散落多PHP文件各有独立签名。新架构 `{gateway}` 动态分发到不同网关适配器（Epay/USDT），`PaymentSettlementOutbox` 出箱+Job队列保证幂等。
- **入参**: `gateway`: 路径变量(epay/usdt_trc20等); 其余由支付网关POST回传
- **执行步骤**: 查 `payment_channels`→加载适配器验证签名(code=4001)→查 `payment_settlement_outboxes` 防重→更新 `deposit_records`→触发 `SettleDepositPayment` Job→返回网关要求格式("success")
- **涉及的数据库表**: `payment_channels`, `payment_settlement_outboxes`, `deposit_records`

---

## 路由: GET /api/front/payment/return/{gateway}

- **路由名称**: `front_api_payment_return`
- **调用控制器方法**: `PaymentNotifyController@returnPage`
- **中间件链路**: 无（用户浏览器跳转）
- **为什么这样做（业务目的）**: 支付完成后浏览器从网关跳回站内。`{gateway}` 动态返回对应结果页面。
- **执行步骤**: 验证同步参数→查 `deposit_records` 状态→渲染HTML页面
- **涉及的数据库表**: `deposit_records`
- **返回值**: HTML页面（非JSON）

---

### 五、认证令牌管理（3条JWT保护路由）

---

## 路由: POST /api/front/auth/logout

- **路由名称**: `front_api_auth_logout`
- **调用控制器方法**: `AuthController@logout`
- **中间件链路**: `jwt.auth:user → sso:user`
- **为什么这样做（业务目的）**: 旧项目仅 `session_destroy()`。新架构清SSO缓存Redis key使旧Token立即失效+写退出审计日志。
- **执行步骤**: JWT Auth→SSO验证→`JwtService::invalidateToken(token)`(jti加黑名单)→`Cache::forget('sso:user:'.sub)` 清SSO→写 `user_login_logs` 退出时间
- **涉及的数据库表**: `user_login_logs`
- **返回值（成功）**: `{"code":1000,"message":"操作成功","data":{}}`

---

## 路由: POST /api/front/auth/token/refresh

- **路由名称**: `front_api_auth_token_refresh`
- **调用控制器方法**: `AuthController@refreshToken`
- **中间件链路**: `jwt.auth:user → sso:user`
- **为什么这样做（业务目的）**: 旧项目无Token刷新，Session自续。JWT无状态需在过期前当前有效Token换新Token。`JwtService::refreshToken()` 生成新jti+更新Redis SSO缓存。
- **执行步骤**: 解析Token(允许过期但在刷新窗口内)→SSO验证→`JwtService::refreshToken(token)`(检查exp+refreshTtl>now)→生成新JWT(新jti+新exp)→更新Redis `sso:user:{sub}` 为新jti→旧jti加黑名单
- **涉及的数据库表**: 无（纯JWT+Cache）
- **返回值（成功）**: `{"code":1000,"data":{"access_token":"eyJ...","token_type":"Bearer","expires_in":3600}}`
- **各执行结果**: code=4002(超刷新窗口需重登), 4001(黑名单/SSO失效)

---

## 路由: GET /api/front/navigation/menus

- **路由名称**: `front_api_navigation_menus`
- **调用控制器方法**: `MenuController@userMenus`
- **中间件链路**: `jwt.auth:user → sso:user`
- **为什么这样做（业务目的）**: 旧项目菜单在PHP模板硬编码无动态权限。新架构 `permissions` 表+ `role_permissions` 关联动态生成菜单树(guard_type='front')+返回按钮权限slug(`front.commission.transfer`)前端据此隐藏无权限按钮。
- **执行步骤**: 查当前用户role→获取 `role_permissions` 权限ID列表→查 `permissions` WHERE guard_type='front' AND status=1 AND parent_id=0(顶级)→递归构建children菜单树→返回树形结构+扁平slug列表
- **涉及的数据库表**: `roles`, `role_permissions`(隐式中间表), `permissions`
- **返回值（成功）**: `{"code":1000,"data":{"menus":[{"id":1,"name":"仪表盘","icon":"layui-icon-home","url":"/front/dashboard","children":[]}],"permissions":["front.dashboard","front.profile.view"]}}`

---

## 路由: GET /api/front/menus

- **路由名称**: `front_api_menus`
- **调用控制器方法**: `MenuController@userMenus`(同上)
- **中间件链路**: `jwt.auth:user → sso:user`
- **为什么这样做（业务目的）**: 旧前台硬编码路径 `/api/front/menus`，新架构保留此兼容路由。
- **入参/执行步骤/返回值**: 与 `/navigation/menus` 完全相同

---

### 六、仪表盘（1条JWT保护路由）

---

## 路由: GET /api/front/dashboard

- **路由名称**: `front_api_dashboard`
- **调用控制器方法**: `DashboardController@dashboardData`
- **中间件链路**: `jwt.auth:user → sso:user`
- **为什么这样做（业务目的）**: 旧项目仪表盘数据各页面分别查询。新架构一次性返资金/持仓/最近交易/代理统计，减少多AJAX。
- **执行步骤**: 查 `user_infos` 余额→查 `mt4_trades` 当前持仓汇总→查最近5笔平仓→如果是代理查 `agent_descendants` 下级统计
- **涉及的数据库表**: `user_infos`, `mt4_trades`, `agent_descendants`
- **返回值（成功）**: `{"code":1000,"data":{"balance":50000.00,"equity":52000.00,"floating_pnl":2000.00,"position_count":3,"net_deposit":100000.00,"agent_count":15,"recent_trades":[...]}}`

---

### 七、个人中心（23条JWT保护路由）| ProfileController

---

## 路由: GET /api/front/profile

- **路由名称**: `front_api_profile`
- **调用控制器方法**: `ProfileController@profileInfo`
- **中间件链路**: `jwt.auth:user → sso:user`
- **为什么这样做（业务目的）**: 旧项目资料查询分散多PHP页面。新架构一次性返回三表数据(user_logins+user_infos+user_auths)+头像URL+脱敏手机/邮箱(maskPhone→138\*\*\*\*5678, maskEmail→u\*\*\*@example.com)。
- **涉及的数据库表**: `user_logins`, `user_infos`, `user_auths`
- **返回值（成功）**: 三部分结构 login/ info(含phone_masked,email_masked,avatar_url) /auth(含id_card_no脱敏,bank_card_no脱敏,audit_status)

---

## 路由: PATCH /api/front/profile → ProfileController@updateProfile

修改姓名/性别/手机/地址等可更新字段。涉及表: `user_infos`。code=1002(成功), 4005(格式不符)。

---

## 路由: POST /api/front/profile/password → ProfileController@changePassword

改密码: `Hash::check`旧密码→code=4008→`UserPasswordService::changePassword`→清SSO→退旧Token发新Token。涉及表: `user_logins`。code=1002(成功,返新Token), 4008(旧密码错)。

---

## 路由: POST /api/front/profile/email → ProfileController@changeEmail

改邮箱: 密码确认+Cache验证码校验+查新邮箱未注册→更新 `user_logins.email`。code=2001(已存在), 4001(密码错), 4005(验证码错)。

---

## 路由: POST /api/front/profile/phone → ProfileController@changePhone

改手机号: 密码确认→查手机未占用→更新 `user_infos.phone`+`user_logins.phone`。code=2002(已存在)。

涉及表: `user_infos`, `user_logins`。

---

## 路由: POST /api/front/profile/avatar → ProfileController@uploadAvatar

上传头像: `Storage::disk('public')->putFileAs('avatars',file,name)`→同步公开目录→更新 `user_infos.avatar`。code=1004(成功), 5002(失败)。涉及表: `user_infos`。

---

## 路由: POST /api/front/profile/identity → ProfileController@submitIdentity

实名认证提交: 检查audit_status(已通过→code=2011)→Validator校验身份证格式→更新/创建 `user_auths`(audit_status=0)。涉及表: `user_auths`。

---

## 路由: POST /api/front/profile/bank-card → ProfileController@submitBankCard

银行卡首次绑定: 检查是否已绑→Validator校验卡号→更新 `user_auths`。涉及表: `user_auths`。

---

## 路由: POST /api/front/profile/bank-card-change → ProfileController@submitBankChange

银行卡换绑: 密码确认+验证码校验+Validator→更新 `user_auths`。code=4001(密码错), 4005(验证码错)。涉及表: `user_auths`。

---

## 路由: POST /api/front/profile/identity-card-uploads → ProfileController@uploadIdCard

身份证照片上传: 存到 `public/id_cards/`→返回 `file_path`(提交换绑时回传)+`file_url`(预览)。涉及表: 无。

---

## 路由: POST /api/front/profile/bank-card-uploads → ProfileController@uploadBankCard

银行卡照片首次上传: 存到 `public/bank_cards/`。

---

## 路由: POST /api/front/profile/bank-card-change-uploads → ProfileController@uploadChangeBankCard

银行卡换绑照片上传: 存到 `public/bank_card_changes/`，与首次分离便于审计。

---

## 路由: POST /api/front/profile/head-image → ProfileController@uploadHeadImg

旧前台兼容路由: 旧版用 `/profile/head-image` 上传头像，保留确保旧H5页面工作。

---

## 路由: POST /api/front/profile/contact-info → ProfileController@updatePhoneEmailInfo

统一联系方式更新: 同时改手机号+邮箱+密码确认，V3需两个接口。

---

## 路由: POST /api/front/profile/bank-card-change/verification-checks → ProfileController@changeBankCardVerifyCode

换绑前验证码校验: Cache读取验证码比对。

---

## 路由: POST /api/front/profile/verification-checks → ProfileController@updateVerifyInfo

修改安全信息前的验证码确认: type参数区分password/phone/email。

---

## 路由: POST /api/front/profile/verification-cancellation-checks → ProfileController@cancelVerifyInfo

取消审核: 仅audit_status=0可取消→重置对应字段。涉及表: `user_auths`。

---

## 路由: POST /api/front/profile/verification-password/verification-codes → ProfileController@updVerifyPassSendCode

安全信息修改验证码发送: 统一入口通过type区分目的。

---

## 路由: POST /api/front/profile/bank-card-change/verification-codes → ProfileController@changeBankCardSendCode

银行卡换绑专用验证码发送: Cache key含 `bank_card_change_` 前缀。

---

## 路由: POST /api/front/profile/verification-cancellation/verification-codes → ProfileController@cancelVerifyPassSendCode

取消认证审核专用验证码发送: Cache key含 `verification_cancellation_` 前缀。

---

## 路由: GET /api/front/profile/relationship-path → ProfileController@relationShip

代理关系链(JSON): agent_descendants递归查ancestors构建JSON数组。涉及表: `agent_descendants`, `user_infos`。

---

## 路由: GET /api/front/profile/relationship-path/html → ProfileController@relationShipHtml

旧兼容: 返回HTML片段(非JSON)。涉及表: `agent_descendants`, `user_infos`。

---

## 路由: GET /api/front/profile/relationship-tree/html → ProfileController@relationShipHtmlV2

V2版树形关系链: 查descendants构建递归树HTML。涉及表: `agent_descendants`, `user_infos`。

---

## 路由: POST /api/front/uploads → \App\Http\Controllers\Common\UploadController@upload

通用文件上传(跨命名空间)。

---

## 路由: POST /api/front/uploads/single → UploadController@singleFileUpload

单文件上传。

---

## 路由: POST /api/front/uploads/multiple → UploadController@multipleFileUpload

多文件批量上传: files数组循环处理。

---

### 八、账户管理（4条）| AccountController

---

## 路由: GET /api/front/account/profile → AccountController@accountInfo

账户综合信息: 查 `user_infos`(余额/MT4信息)+汇总 `deposit_records`(累计入金)+汇总 `withdraw_records`(累计出金)+查 `mt4_users`(MT4状态)。涉及表: `user_infos`, `deposit_records`, `withdraw_records`, `mt4_users`。返回值: balance/mt4_account/total_deposit/net_deposit/credit。

---

## 路由: GET /api/front/account/balance → AccountController@accountBalance

轻量级余额查询: `UserInfo::where('user_id',id)->value('balance')`，用于顶部栏高频查询。涉及表: `user_infos`。

---

## 路由: POST /api/front/account/voucher-submissions → AccountController@submitVoucher

入金凭证上传: Validator校验金额→创建 `voucher_infos`(status=0待审)。涉及表: `voucher_infos`, `payment_channels`。

---

## 路由: GET /api/front/account/vouchers → AccountController@voucherList

证凭历史分页: `VoucherInfo::where('user_id',id)->orderByDesc('id')->paginate()`。status:0待审/1通过/2拒绝。涉及表: `voucher_infos`。

---

### 九、代理管理（14条）| AgentController

---

## 路由: GET /api/front/agents/direct → AgentController@subList

直属下级代理列表: 当前用户须代理→查 `agent_descendants` depth=1→JOIN `user_infos`+`user_logins`→分页。涉及表: `agent_descendants`, `user_infos`, `user_logins`。返回direct_count/total_descendants/commission_rate/level_name。

---

## 路由: GET /api/front/agents/direct-customers → AgentController@customerList

直属客户列表: agent_descendants depth=1且account_type=2。涉及表: `agent_descendants`, `user_infos`。

---

## 路由: GET /api/front/agents/statistics → AgentController@statistics

代理统计: 下级代理/客户数+ `deposit_records` 入金总额+ `mt4_trades` 交易手数。涉及表: `agent_descendants`, `deposit_records`, `mt4_trades`。

---

## 路由: GET /api/front/agents/level-confirmation → AgentController@confirmLevel

查看当前级别及升级条件: 查 `agent_levels`+统计满足条件。涉及表: `agent_levels`, `user_infos`。

---

## 路由: POST /api/front/agents/level-confirmation/changes → AgentController@confirmLevelChange

确认升级: 校验条件→更新 `user_infos.agent_level_id`→`OperationLog`记日志。涉及表: `user_infos`, `agent_levels`, `operation_logs`。

---

## 路由: GET /api/front/agents/group-changes → AgentController@groupChangeList

组别变更历史: 查 `operation_logs` type=group_change。涉及表: `operation_logs`。

---

## 路由: POST /api/front/agents/group-change-applications → AgentController@groupChange

申请变更组别: 校验 `group_configs` 目标组→创建申请记录。入参: group_id。涉及表: `group_configs`, `operation_logs`。

---

## 路由: GET /api/front/users/login-history → AgentController@userLoginHistory

下级登录历史: 获取所有下级user_id→查 `user_login_logs` 按时间倒序分页。涉及表: `agent_descendants`, `user_login_logs`。

---

## 路由: POST /api/front/customers/commission-transfers → AgentController@directUserCommTrans

代理向直属下级转账返佣: 校验target_user_id是直属下级→校验余额→`CommissionService::transfer()`→创建 `commission_transfers`→写 `commission_transfer_outboxes` 出箱→Job异步处理。涉及表: `commission_transfers`, `commission_transfer_outboxes`, `user_infos`。

---

## 路由: GET /api/front/users/{user} → AgentController@showUser

查单个下级用户(RESTful): 校验可查看→查三表返回详情。

---

## 路由: POST /api/front/users/detail → AgentController@userDetail

旧兼容: POST body传user_id查详情。保留避免旧页面改造。

---

## 路由: GET /api/front/agents/direct-level-options → AgentController@getSubAgentsGrpIdList

代理级别和组别下拉选项: 查 `agent_levels`+`group_configs` 启用项。

---

## 路由: GET /api/front/agents/hierarchy-path → AgentController@getParentPath

完整上级链路JSON: 查 `agent_descendants` ancestors。涉及表: `agent_descendants`, `user_infos`。

---

## 路由: GET /api/front/customers/group-change-requests → AgentController@directCustChangeListSearch

直属客户组别变更请求列表: 查 `operation_logs`。涉及表: `operation_logs`。

---

### 十、佣金返佣（4条）| CommissionController

---

## 路由: GET /api/front/commissions/realtime → CommissionController@realTime

实时返佣查询(COMMENT命中关键词的mt4_trades): 查 `mt4_trades` WHERE COMMENT匹配→按佣金比例计算→分页+total_commission汇总。涉及表: `mt4_trades`, `agent_descendants`。

---

## 路由: GET /api/front/commissions/history → CommissionController@history

历史返佣已结算记录: 查 `commission_records`+关联 `commission_transfers`。涉及表: `commission_records`, `commission_transfers`。

---

## 路由: POST /api/front/commissions/transfers → CommissionController@transfer

代理返佣转账: 校验余额+关系→创建 `commission_transfers`(pending)→写入出箱→Job异步。入参: target_user_id, amount, source。涉及表: `commission_transfers`, `commission_transfer_outboxes`, `user_infos`。

---

## 路由: GET /api/front/commissions/transfer-agent-options → CommissionController@transferAgentOptions

可转账代理列表: 查上级+直属下级。涉及表: `agent_descendants`, `user_infos`。

---

### 十一、入金提现（6条）

---

## 路由: GET /api/front/deposits/form-options → DepositController@depositPage

入金表单选项: 查 `payment_channels` status=1 type='deposit'+查 `system_configs` 汇率+限额。涉及表: `payment_channels`, `system_configs`。

---

## 路由: POST /api/front/deposits/submissions → DepositController@submitDeposit

提交入金: 校验金额范围→检查通道→创建 `deposit_records`(0待支付)→调网关→写 `payment_settlement_outboxes`→返回支付URL。涉及表: `deposit_records`, `payment_settlement_outboxes`, `payment_channels`。code=2013(不可入金), 2014(金额无效), 5004(网关异常)。

---

## 路由: GET /api/front/deposits/history → DepositController@depositHistory

入金历史分页。涉及表: `deposit_records`。

---

## 路由: GET /api/front/withdrawals/form-options → WithdrawController@withdrawPage

出金表单选项: 查余额+手续费+已绑银行卡。涉及表: `user_infos`, `user_auths`, `system_configs`。

---

## 路由: POST /api/front/withdrawals/submissions → WithdrawController@submitWithdraw

提交出金: 密码确认→Idempotency-Key防重→检查余额>=amount+fee→检查未平仓持仓(code=2012)→创建 `withdraw_records`(0待审)→写 `withdraw_settlement_outboxes`→`ProcessWithdrawFunding` Job异步。涉及表: `withdraw_records`, `withdraw_settlement_outboxes`, `user_infos`。code=2012(持仓不允许), 2014(金额无效), 2015(余额不足), 4001(密码错), 4005(幂等key无效)。

---

## 路由: GET /api/front/withdrawals/history → WithdrawController@withdrawHistory

出金历史分页(0待审/1处理中/2完成/3拒绝)。涉及表: `withdraw_records`。

---

### 十二、资金流水（8条）| FlowController

---

## 路由: GET /api/front/flows/account → FlowController@accountFlow

全量流水总览: UNION查询 `deposit_records`+`withdraw_records`+`commission_transfers`+`mt4_trades`→按时间排序分页。

---

## 路由: GET /api/front/flows/deposits → FlowController@depositFlowSearch

入金流水分页。涉及表: `deposit_records`。

---

## 路由: GET /api/front/flows/withdrawals → FlowController@withdrawalFlowSearch

出金流水分页。涉及表: `withdraw_records`。

---

## 路由: GET /api/front/flows/withdrawal-applications → FlowController@withdrawApplyFlowSearch

出金申请流水(仅待审separate from完成出金)。涉及表: `withdraw_records`。

---

## 路由: GET /api/front/flows/direct-deposits → FlowController@directDepositFlowSearch

代理查看直属下级入金: 获取下级user_id列表→查 `deposit_records`。涉及表: `agent_descendants`, `deposit_records`。

---

## 路由: GET /api/front/flows/direct-withdrawals → FlowController@directWithdrawalFlowSearch

代理查看直属下级出金。涉及表: `agent_descendants`, `withdraw_records`。

---

## 路由: GET /api/front/flows/direct-agent-deposits → FlowController@directDepositFlowSearch

旧兼容路由名(同#5)。

---

## 路由: GET /api/front/flows/direct-agent-withdrawals → FlowController@directWithdrawalFlowSearch

旧兼容路由名(同#6)。

---

### 十三、持仓订单（6条）

---

## 路由: GET /api/front/trade-symbols → TradeSymbolController@index

可交易品种报价: 查 `symbol_prices` 所有启用品种→返回symbol/bid/ask/spread/digits/contract_size。涉及表: `symbol_prices`。

---

## 路由: GET /api/front/positions/summary → PositionController@positionSummary

持仓按品种汇总: `mt4_trades` WHERE CMD IN(0,1) AND CLOSE_TIME IS NULL→按SYMBOL分组SUM(VOLUME,PROFIT),AVG(OPEN_PRICE)→关联 `symbol_prices` 计算浮动盈亏。涉及表: `mt4_trades`, `symbol_prices`。

---

## 路由: GET /api/front/positions/direct-agent-summaries → PositionController@subPositionSummary

代理查看下级持仓汇总。涉及表: `agent_descendants`, `mt4_trades`。

---

## 路由: GET /api/front/positions/trades → PositionController@positionDetail

持仓明细(TICKET/OPEN_TIME/PRICE/VOLUME/PROFIT)。涉及表: `mt4_trades`。

---

## 路由: GET /api/front/orders/open → OrderController@openOrders

未平仓订单(含挂单)。涉及表: `mt4_trades`。

---

## 路由: GET /api/front/orders/closed → OrderController@closedOrders

已平仓历史单(按symbol/date_range筛选)。涉及表: `mt4_trades`。

---

### 十四、赠品管理（5条）| GiftController

---

## 路由: GET /api/front/gift-addresses → GiftController@addressSearch

收货地址列表: 查 `user_addresses` 按is_default排序。涉及表: `user_addresses`。

---

## 路由: POST /api/front/gift-addresses → GiftController@addAddress

新增地址: Validator→is_default=1则先取消其他默认→创建。入参: contact_name/contact_phone/province/city/address/is_default。涉及表: `user_addresses`。

---

## 路由: PATCH /api/front/gift-addresses/{address} → GiftController@updateAddress

修改地址: 校验归属当前用户→更新。涉及表: `user_addresses`。

---

## 路由: DELETE /api/front/gift-addresses/{address} → GiftController@deleteAddress

删地址: 最后默认地址不可删(code=1015)。涉及表: `user_addresses`。

---

## 路由: GET /api/front/gifts → GiftController@giftList

礼品列表: 查 `gift_items` WHERE status=1 AND等级满足。涉及表: `gift_items`。

---

### 十五、新闻公告（1条）

---

## 路由: GET /api/front/news → NewsController@newsList

公告列表: `News::where('status',1)->orderByDesc('created_at')->paginate()`。涉及表: `news`。

---

### 十六、注销账户（2条）

---

## 路由: GET /api/front/account/cancellation → CancelController@status

查注销申请状态: `CancelApply::where('user_id',id)->latest()->first()`。涉及表: `cancel_applies`。

---

## 路由: POST /api/front/account/cancellation-applications → CancelController@apply

提交注销申请: 密码确认→检查未平仓持仓(code=2012)→检查已有申请(code=2017)→创建 `cancel_applies`(status=0待审)。入参: reason可选, password必填。涉及表: `cancel_applies`。

---
## 后台模块

> **路由前缀**: `/api/admin` | **控制器命名空间**: `App\Http\Controllers\Admin` | **总计**: 281条路由
>
> **保护中间件链**: `jwt.auth:admin → sso:admin → check.permission:admin`（登录接口除外）
>
> **权限白名单** (跳过check.permission): `logout`, `refreshToken`, `menus`, `profileInfo`, `updateProfile`, `changePassword`, `uploadAvatar`
>
> **超级管理员**: `admins.id=1` 或 `role.name=super_admin` 跳过权限表校验但仍需JWT+SSO

---

### 一、认证登录（1条公开 + 10条保护路由）| AuthController

---

## 路由: POST /api/admin/login
- **路由名称**: `admin_api_login`
- **调用控制器方法**: `AuthController@login`
- **中间件链路**: 无（公开）
- **为什么这样做（业务目的）**: 旧项目后台Session登录。新架构JWT统一认证，guard='admin'，SSO缓存`sso:admin:{sub}`不允许同一管理员多设备在线。
- **入参**: `username`: string, 必填(可传admins.username或email); `password`: string, 必填
- **执行步骤**: 查`admins`表(username或email)→`Hash::check`→`JwtService::generateToken(['sub'=>admin.id,'guard'=>'admin'])`→写`admin_login_logs`
- **涉及的数据库表**: `admins`, `admin_login_logs`
- **返回值（成功）**: `{"code":1000,"data":{"access_token":"eyJ...","token_type":"Bearer","expires_in":3600,"user":{"id":1,"username":"admin","name":"超级管理员"}}}`
- **各执行结果**: code=1000(成功), 2009(禁用), 4001(密码错), 4005(参数缺失), 5000(异常)

---

## 路由: POST /api/admin/logout
- **路由名称**: `admin_api_logout` (白名单)
- **调用控制器方法**: `AuthController@logout`
- **中间件链路**: `jwt.auth:admin → sso:admin → [白名单跳过check.permission]`
- **执行步骤**: JWT+SSO→`Cache::forget('sso:admin:'.id)`→jti加黑名单→写`admin_login_logs`
- **涉及的数据库表**: `admin_login_logs`
- **返回值**: `{"code":1000,"message":"操作成功","data":{}}`

---

## 路由: POST /api/admin/refreshToken
- **路由名称**: `admin_api_refreshToken` (白名单)
- **调用控制器方法**: `AuthController@refreshToken`
- **执行步骤**: 解析Token→检查刷新窗口(exp+refreshTtl>now)→生成新JWT新jti→更新Redis SSO→返回新Token
- **返回值**: `{"code":1000,"data":{"access_token":"eyJ...","token_type":"Bearer","expires_in":3600}}`

---

## 路由: POST /api/admin/menus
- **路由名称**: `admin_api_menus` (白名单)
- **调用控制器方法**: `MenuController@adminMenus`
- **为什么这样做（业务目的）**: 后台菜单树+按钮权限slug。动态从`permissions`表(guard_type='admin')生成，`check.permission`中间件后续会用同一表鉴权。
- **执行步骤**: 查当前admin role→获取`role_permissions`权限ID→查`permissions` WHERE guard_type='admin' AND status=1 AND parent_id=0(顶级)→递归构建children→返回`data.menus`+`data.permissions`
- **涉及的数据库表**: `roles`, `role_permissions`(隐式中间表), `permissions`
- **返回值**: `{"code":1000,"data":{"menus":[...],"permissions":["admin.userList","admin.createUser",...]}}`

---

## 路由: POST /api/admin/profileInfo
- **路由名称**: `admin_api_profileInfo` (白名单)
- **调用控制器方法**: `AuthController@profileInfo`
- **执行步骤**: 返回当前管理员`admins`表资料(id/username/email/mobile/name/avatar/role)
- **涉及的数据库表**: `admins`, `roles`

---

## 路由: POST /api/admin/updateProfile
- **路由名称**: `admin_api_updateProfile` (白名单)
- **调用控制器方法**: `AuthController@updateProfile`
- **入参**: email可选/mobile可选。涉及表: `admins`。

---

## 路由: POST /api/admin/changePassword
- **路由名称**: `admin_api_changePassword` (白名单)
- **调用控制器方法**: `AuthController@changePassword`
- **入参**: old_password必填/password必填/password_confirmation必填。改后清SSO退旧Token。code=4008(旧密码错)。

---

## 路由: POST /api/admin/uploadAvatar
- **路由名称**: `admin_api_uploadAvatar` (白名单)
- **调用控制器方法**: `AuthController@uploadAvatar`
- **入参**: avatar file。涉及表: `admins`。

---

### 二、仪表盘（4条）| AdminDashboardController / BigNumberController

---

## 路由: POST /api/admin/dashboard → AdminDashboardController@dashboardData
- **路由名称**: `admin_api_dashboardData`
- **执行步骤**: 汇总总用户数/代理数/今日交易量/今日入出金/在线用户/风控警报
- **涉及的数据库表**: `user_infos`, `deposit_records`, `withdraw_records`, `mt4_trades`, `user_onlines`
- **返回值**: total_users/total_agents/today_trades/today_deposit/today_withdraw/online_users/risk_alerts

---

## 路由: POST /api/admin/dashboardData → AdminDashboardController@dashboardData (别名，指向同一方法)
- **路由名称**: `admin_api_dashboardData`
- **为什么这样做（业务目的）**: 兼容旧后台路径`/api/admin/dashboard`和`/api/admin/dashboardData`两个入口。

---

## 路由: POST /api/admin/bigNumberDashboard → BigNumberController@dashboard
- **路由名称**: `admin_api_bigNumberDashboard`
- **执行步骤**: 大代理仪表盘汇总(下级代理数/客户数/交易量/入金量)
- **涉及的数据库表**: `big_agents`, `agent_descendants`, `mt4_trades`, `deposit_records`

---

## 路由: POST /api/admin/bigNumberTrend → BigNumberController@trend
- **路由名称**: `admin_api_bigNumberTrend`
- **执行步骤**: 大代理趋势图：按日期聚合交易量+入金量
- **涉及的数据库表**: `big_agents`, `agent_descendants`, `mt4_trades`, `deposit_records`

---

### 三、用户管理（12条）| AdminUserController + AuthenticationController + LegacyAdminActionController

---

## 路由: POST /api/admin/users → AdminUserController@userList
- **路由名称**: `admin_api_userList`
- **入参**: page/limit/keyword/status/account_type/agent_id/group_id/date_range
- **执行步骤**: 多条件筛选`user_logins` JOIN `user_infos`→应用`AdminDataScopeService`数据范围过滤→分页
- **涉及的数据库表**: `user_logins`, `user_infos`, `user_auths`, `agent_descendants`
- **返回值**: 分页列表含user_id/name/email/phone/account_type/agent_level/balance/status/register_time

---

## 路由: POST /api/admin/users/{user} → AdminUserController@userDetail
- **路由名称**: `admin_api_userDetail`
- **入参**: user路径变量(user_id)
- **执行步骤**: 查三表`user_logins`+`user_infos`+`user_auths`完整资料
- **涉及的数据库表**: `user_logins`, `user_infos`, `user_auths`

---

## 路由: PATCH /api/admin/users/{user} → AdminUserController@updateUser
- **路由名称**: `admin_api_updateUser`
- **入参**: user路径变量; body: name/phone/email/group_id/agent_level_id/commission_rate等可修改字段
- **执行步骤**: 校验数据范围→Validator→更新`user_infos`/`user_logins`
- **涉及的数据库表**: `user_infos`, `user_logins`

---

## 路由: PATCH /api/admin/users/{user}/status → AdminUserController@changeUserStatus
- **路由名称**: `admin_api_changeUserStatus`
- **入参**: user路径变量; status: int(1启用/0禁用)
- **执行步骤**: 更新`user_logins.status`→同步MT4状态(如需要)→清用户SSO缓存

---

## 路由: POST /api/admin/userList → AdminUserController@userList (别名)
## 路由: POST /api/admin/userDetail → AdminUserController@userDetail (别名)
## 路由: POST /api/admin/updateUser → AdminUserController@updateUser (别名)
## 路由: POST /api/admin/changeUserStatus → AdminUserController@changeUserStatus (别名)

---

## 路由: POST /api/admin/createUser → LegacyAdminActionController@createUser
- **路由名称**: `admin_api_createUser`
- **为什么这样做（业务目的）**: 旧后台兼容——通过LegacyAdminActionController创建用户。code=1002(邮箱/手机重复), 1033(父代理不在数据范围)。
- **涉及的数据库表**: `user_logins`, `user_infos`, `user_auths`, `agent_descendants`

---

## 路由: POST /api/admin/resetUserPassword → LegacyAdminActionController@resetUserPassword
- **路由名称**: `admin_api_resetUserPassword`
- **执行步骤**: 管理员强制重置用户密码→清用户SSO缓存。涉及表: `user_logins`。

---

## 路由: POST /api/admin/exportUsers → AdminUserController@exportUsers
- **路由名称**: `admin_api_exportUsers`
- **执行步骤**: 同userList→CSV导出。code=3004(成功),3003(失败)。

---

## 路由: POST /api/admin/reviewAuth → AdminUserController@reviewAuth
- **路由名称**: `admin_api_reviewAuth`
- **执行步骤**: 审核用户实名认证→更新`user_auths.audit_status`(1通过/2拒绝)
- **涉及的数据库表**: `user_auths`

---

## 路由: POST /api/admin/authPendingList → AuthenticationController@pendingList
- **路由名称**: `admin_api_authPendingList`
- **执行步骤**: 查`user_auths` WHERE audit_status=0(待审)→JOIN `user_infos`
- **涉及的数据库表**: `user_auths`, `user_infos`

---

## 路由: POST /api/admin/authCertifiedList → AuthenticationController@certifiedList
- **路由名称**: `admin_api_authCertifiedList`
- **执行步骤**: 查`user_auths` WHERE audit_status IN(1,2)(已审)→JOIN `user_infos`
- **涉及的数据库表**: `user_auths`, `user_infos`

---

### 四、角色管理（5条）| RoleController

---

## 路由: POST /api/admin/roles → RoleController@roleList
- **路由名称**: `admin_api_roleList`
- **执行步骤**: 分页查`roles`表
- **涉及的数据库表**: `roles`

## 路由: POST /api/admin/roleList → RoleController@roleList (别名)

## 路由: POST /api/admin/createRole → RoleController@createRole
- **路由名称**: `admin_api_createRole`
- **入参**: name必填/description可选/status可选
- **涉及的数据库表**: `roles`

## 路由: POST /api/admin/updateRole → RoleController@updateRole
- **路由名称**: `admin_api_updateRole`
- **涉及的数据库表**: `roles`

## 路由: POST /api/admin/deleteRole → RoleController@deleteRole
- **路由名称**: `admin_api_deleteRole`
- **执行步骤**: 检查角色下是否有管理员→删除。code=2021(有关联管理员)。
- **涉及的数据库表**: `roles`, `admin_roles`

## 路由: POST /api/admin/assignPermissions → RoleController@assignPermissions
- **路由名称**: `admin_api_assignPermissions`
- **入参**: role_id必填/permission_ids[]必填
- **执行步骤**: 清除角色旧权限→批量插入`role_permissions`关联
- **涉及的数据库表**: `role_permissions`(隐式中间表)

---

### 五、数据范围管理（5条）| DataScopeController

---

## 路由: POST /api/admin/roleDataScopeList → DataScopeController@roleDataScopeList
- **路由名称**: `admin_api_roleDataScopeList`
- **涉及的数据库表**: `role_data_scopes`, `roles`, `data_scopes`

## 路由: POST /api/admin/saveRoleDataScope → DataScopeController@saveRoleDataScope
- **路由名称**: `admin_api_saveRoleDataScope`
- **入参**: role_id必填/data_scope_type必填/scope_value可选
- **涉及的数据库表**: `role_data_scopes`, `data_scopes`

## 路由: POST /api/admin/adminAgentBindingList → DataScopeController@adminAgentBindingList
- **路由名称**: `admin_api_adminAgentBindingList`
- **涉及的数据库表**: `admin_agent_bindings`, `admins`, `user_infos`

## 路由: POST /api/admin/saveAdminAgentBinding → DataScopeController@saveAdminAgentBinding
- **路由名称**: `admin_api_saveAdminAgentBinding`
- **入参**: admin_id必填/agent_ids[]必填
- **涉及的数据库表**: `admin_agent_bindings`

## 路由: POST /api/admin/deleteAdminAgentBinding → DataScopeController@deleteAdminAgentBinding
- **路由名称**: `admin_api_deleteAdminAgentBinding`
- **涉及的数据库表**: `admin_agent_bindings`

---

### 六、权限管理（6条）| PermissionController + MenuController

---

## 路由: POST /api/admin/permissionTree → PermissionController@permissionTree
- **路由名称**: `admin_api_permissionTree`
- **执行步骤**: 查`permissions`表(guard_type='admin')→构建权限树
- **涉及的数据库表**: `permissions`

## 路由: POST /api/admin/permissions/tree → PermissionController@permissionTree (别名)

## 路由: POST /api/admin/createPermission → PermissionController@createPermission
- **路由名称**: `admin_api_createPermission`
- **入参**: name必填/api_route必填/guard_type必填/icon可选/parent_id可选
- **涉及的数据库表**: `permissions`

## 路由: POST /api/admin/updatePermission → PermissionController@updatePermission
- **路由名称**: `admin_api_updatePermission`

## 路由: POST /api/admin/deletePermission → PermissionController@deletePermission
- **路由名称**: `admin_api_deletePermission`
- **执行步骤**: 检查子权限+角色引用→删除。code=2021(有子权限或角色引用)。

## 路由: POST /api/admin/menuTree → MenuController@menuTree
- **路由名称**: `admin_api_menuTree`
- **执行步骤**: 查`permissions`表(guard_type='admin')→构建菜单管理树
- **涉及的数据库表**: `permissions`

## 路由: POST /api/admin/createMenu → MenuController@createMenu
- **路由名称**: `admin_api_createMenu`

## 路由: POST /api/admin/updateMenu → MenuController@updateMenu
- **路由名称**: `admin_api_updateMenu`

## 路由: POST /api/admin/deleteMenu → MenuController@deleteMenu
- **路由名称**: `admin_api_deleteMenu`

---

### 七、代理管理（11条）| AgentController + LegacyAdminActionController

---

## 路由: POST /api/admin/agents → AgentController@index
- **路由名称**: `admin_api_agentList`
- **入参**: page/limit/keyword/level_id/group_id/status/date_range
- **执行步骤**: 多条件筛选`user_infos` WHERE account_type=1→应用数据范围→分页
- **涉及的数据库表**: `user_infos`, `user_logins`, `agent_descendants`, `agent_levels`, `group_configs`
- **返回值**: user_id/name/email/level_name/group_name/direct_count/total_descendants/commission_rate/balance

## 路由: POST /api/admin/agentList → AgentController@index (别名)

## 路由: POST /api/admin/createAgent → LegacyAdminActionController@createAgent
- **路由名称**: `admin_api_createAgent`
- **为什么这样做（业务目的）**: 旧后台兼容创建代理。code=1033(父代理不在数据范围)。
- **涉及的数据库表**: `user_logins`, `user_infos`, `user_auths`, `agent_descendants`

## 路由: POST /api/admin/exportAgents → AgentController@exportAgents
- **路由名称**: `admin_api_exportAgents`
- **执行步骤**: CSV导出代理列表

## 路由: POST /api/admin/agentStatsList → AgentController@listWithStats
- **路由名称**: `admin_api_agentStatsList`
- **执行步骤**: 代理列表+统计(下级数/入金/交易量/返佣)
- **涉及的数据库表**: `user_infos`, `agent_descendants`, `deposit_records`, `mt4_trades`, `commission_records`

## 路由: POST /api/admin/confirmAgent → AgentController@confirmAgent
- **路由名称**: `admin_api_confirmAgent`
- **执行步骤**: 通过代理升级确认→更新`user_infos.agent_level_id`
- **涉及的数据库表**: `user_infos`, `agent_levels`

## 路由: POST /api/admin/rejectAgentConfirmation → AgentController@rejectAgentConfirmation
- **路由名称**: `admin_api_rejectAgentConfirmation`
- **执行步骤**: 拒绝代理升级申请

## 路由: POST /api/admin/agentDetail → AgentController@show
- **路由名称**: `admin_api_agentDetail`
- **执行步骤**: 查代理完整资料+统计
- **涉及的数据库表**: `user_infos`, `user_logins`, `user_auths`, `agent_descendants`

## 路由: POST /api/admin/agentParentPath → AgentController@parentPath
- **路由名称**: `admin_api_agentParentPath`
- **执行步骤**: 查`agent_descendants` ancestors→返回上级链路JSON
- **涉及的数据库表**: `agent_descendants`, `user_infos`

## 路由: POST /api/admin/agentDescendants → AgentController@descendants
- **路由名称**: `admin_api_agentDescendants`
- **执行步骤**: 查`agent_descendants` descendants→返回下级树
- **涉及的数据库表**: `agent_descendants`, `user_infos`

## 路由: POST /api/admin/updateAgentLevel → AgentController@updateLevel
- **路由名称**: `admin_api_updateAgentLevel`
- **入参**: user_id必填/agent_level_id必填
- **涉及的数据库表**: `user_infos`, `agent_levels`

## 路由: POST /api/admin/updateAgentCommission → AgentController@updateCommission
- **路由名称**: `admin_api_updateAgentCommission`
- **入参**: user_id必填/commission_rate必填
- **执行步骤**: 校验不超过上级佣金(code=2022)→更新`user_infos`
- **涉及的数据库表**: `user_infos`

---

### 八、代理级别（4条）| AgentLevelController

---

## 路由: POST /api/admin/agent-levels → AgentLevelController@index
- **路由名称**: `admin_api_agentLevelList`
- **涉及的数据库表**: `agent_levels`

## 路由: POST /api/admin/agentLevelList → AgentLevelController@index (别名)

## 路由: POST /api/admin/createAgentLevel → AgentLevelController@store
- **路由名称**: `admin_api_createAgentLevel`
- **入参**: name必填/min_agents可选/min_deposit可选/max_commission_rate可选

## 路由: POST /api/admin/updateAgentLevel2/{id} → AgentLevelController@update
- **路由名称**: `admin_api_updateAgentLevel2`
- **入参**: id路径变量(agent_levels.id)

## 路由: POST /api/admin/deleteAgentLevel/{id} → AgentLevelController@destroy
- **路由名称**: `admin_api_deleteAgentLevel`
- **执行步骤**: 检查代理使用此级别→删除。code=2021(有代理使用)。

---

### 九、组别配置（4+4条旧兼容）| GroupConfigController + UserGroupController

---

## 路由: POST /api/admin/group-configs → GroupConfigController@index
- **路由名称**: `admin_api_groupConfigList`
- **涉及的数据库表**: `group_configs`

## 路由: POST /api/admin/groupConfigList → GroupConfigController@index (别名)

## 路由: POST /api/admin/createGroupConfig → GroupConfigController@store
- **路由名称**: `admin_api_createGroupConfig`
- **入参**: name必填/description可选/status可选

## 路由: POST /api/admin/updateGroupConfig/{id} → GroupConfigController@update
- **路由名称**: `admin_api_updateGroupConfig`

## 路由: POST /api/admin/deleteGroupConfig/{id} → GroupConfigController@destroy
- **路由名称**: `admin_api_deleteGroupConfig`

## 路由: POST /api/admin/userGroupList → UserGroupController@index
- **路由名称**: `admin_api_userGroupList`
- **为什么这样做（业务目的）**: 旧UserGroupController已合并到group_configs，保留避免前后端断链。

## 路由: POST /api/admin/createUserGroup → UserGroupController@store
- **路由名称**: `admin_api_createUserGroup`

## 路由: POST /api/admin/updateUserGroup/{id} → UserGroupController@update
- **路由名称**: `admin_api_updateUserGroup`

## 路由: POST /api/admin/deleteUserGroup/{id} → UserGroupController@destroy
- **路由名称**: `admin_api_deleteUserGroup`

---

### 十、入金管理（11条）| DepositController + BatchAmountImportController + LegacyAdminExportController

---

## 路由: POST /api/admin/deposits → DepositController@index
- **路由名称**: `admin_api_depositList`
- **入参**: page/limit/status/user_id/channel_id/date_range
- **执行步骤**: 多条件筛选`deposit_records`→应用数据范围→分页
- **涉及的数据库表**: `deposit_records`, `user_infos`, `payment_channels`
- **返回值**: 入金记录含id/order_no/amount/status/channel_name/user_name/time

## 路由: POST /api/admin/depositList → DepositController@index (别名)

## 路由: POST /api/admin/exportDeposits → LegacyAdminExportController@exportDeposits
- **路由名称**: `admin_api_exportDeposits`
- **执行步骤**: CSV导出入金

## 路由: POST /api/admin/depositDetail → DepositController@show
- **路由名称**: `admin_api_depositDetail`
- **入参**: deposit_id必填
- **涉及的数据库表**: `deposit_records`, `payment_settlement_outboxes`

## 路由: POST /api/admin/depositApprove → DepositController@approve
- **路由名称**: `admin_api_depositApprove`
- **执行步骤**: 审核通过→更新`deposit_records.status`→写`payment_settlement_outboxes`→触发`SettleDepositPayment` Job
- **涉及的数据库表**: `deposit_records`, `payment_settlement_outboxes`

## 路由: POST /api/admin/depositReject → DepositController@reject
- **路由名称**: `admin_api_depositReject`
- **执行步骤**: 拒绝入金→`deposit_records.status=2`(已取消)
- **涉及的数据库表**: `deposit_records`

## 路由: POST /api/admin/depositImportList → BatchAmountImportController@depositImportList
- **路由名称**: `admin_api_depositImportList`
- **涉及的数据库表**: `deposit_imports`

## 路由: POST /api/admin/createDepositImport → BatchAmountImportController@createDepositImport
- **路由名称**: `admin_api_createDepositImport`
- **入参**: file(CSV)→解析→逐行处理。code=3002/3003/3006。
- **涉及的数据库表**: `deposit_imports`, `deposit_records`

## 路由: POST /api/admin/depositImportTemplate → BatchAmountImportController@depositImportTemplate
- **路由名称**: `admin_api_depositImportTemplate`
- **执行步骤**: 返回CSV模板文件下载

## 路由: POST /api/admin/exportDepositImports → BatchAmountImportController@exportDepositImports
- **路由名称**: `admin_api_exportDepositImports`

## 路由: POST /api/admin/retryDepositImport/{id} → BatchAmountImportController@retryDepositImport
- **路由名称**: `admin_api_retryDepositImport`
- **执行步骤**: 重试失败行

## 路由: POST /api/admin/syncDepositImport/{id} → BatchAmountImportController@syncDepositImport
- **路由名称**: `admin_api_syncDepositImport`
- **执行步骤**: 同步导入状态与MT4结果

---

### 十一、出金管理（11条）| WithdrawController + BatchAmountImportController + LegacyAdminExportController

---

## 路由: POST /api/admin/withdrawals → WithdrawController@index
- **路由名称**: `admin_api_withdrawList`
- **入参**: page/limit/status/user_id/date_range
- **执行步骤**: 筛选`withdraw_records`→数据范围→分页
- **涉及的数据库表**: `withdraw_records`, `user_infos`
- **返回值**: id/amount/fee/status/user_name/bank_card/time

## 路由: POST /api/admin/withdrawList → WithdrawController@index (别名)

## 路由: POST /api/admin/exportWithdrawals → LegacyAdminExportController@exportWithdrawals
- **路由名称**: `admin_api_exportWithdrawals`

## 路由: POST /api/admin/withdrawProcess → WithdrawController@process
- **路由名称**: `admin_api_withdrawProcess`
- **执行步骤**: 审核通过→status=1→写`withdraw_settlement_outboxes`→触发`ProcessWithdrawFunding` Job MT4扣款
- **涉及的数据库表**: `withdraw_records`, `withdraw_settlement_outboxes`

## 路由: POST /api/admin/withdrawComplete → WithdrawController@complete
- **路由名称**: `admin_api_withdrawComplete`
- **执行步骤**: 确认完成→status=2
- **涉及的数据库表**: `withdraw_records`

## 路由: POST /api/admin/withdrawReject → WithdrawController@reject
- **路由名称**: `admin_api_withdrawReject`
- **执行步骤**: 拒绝→status=3→如有扣款则退款Job
- **涉及的数据库表**: `withdraw_records`

## 路由: POST /api/admin/withdrawImportList → BatchAmountImportController@withdrawImportList
- **路由名称**: `admin_api_withdrawImportList`
- **涉及的数据库表**: `withdraw_imports`

## 路由: POST /api/admin/createWithdrawImport → BatchAmountImportController@createWithdrawImport
- **路由名称**: `admin_api_createWithdrawImport`
- **入参**: file(CSV)。code=3002/3003/3006。
- **涉及的数据库表**: `withdraw_imports`, `withdraw_records`

## 路由: POST /api/admin/withdrawImportTemplate → BatchAmountImportController@withdrawImportTemplate
- **路由名称**: `admin_api_withdrawImportTemplate`

## 路由: POST /api/admin/exportWithdrawImports → BatchAmountImportController@exportWithdrawImports
- **路由名称**: `admin_api_exportWithdrawImports`

## 路由: POST /api/admin/retryWithdrawImport/{id} → BatchAmountImportController@retryWithdrawImport
- **路由名称**: `admin_api_retryWithdrawImport`

## 路由: POST /api/admin/syncWithdrawImport/{id} → BatchAmountImportController@syncWithdrawImport
- **路由名称**: `admin_api_syncWithdrawImport`

---

### 十二、资金流水（7条）| FundFlowController

---

## 路由: POST /api/admin/depositFlowList → FundFlowController@depositFlowList
- **路由名称**: `admin_api_depositFlowList`
- **执行步骤**: 查`mt4_trades`+`deposit_records`入金流水→数据范围→分页
- **涉及的数据库表**: `mt4_trades`, `deposit_records`

## 路由: POST /api/admin/exportDepositFlows → FundFlowController@exportDepositFlows
- **路由名称**: `admin_api_exportDepositFlows`

## 路由: POST /api/admin/withdrawFlowList → FundFlowController@withdrawFlowList
- **路由名称**: `admin_api_withdrawFlowList`
- **涉及的数据库表**: `mt4_trades`, `withdraw_records`

## 路由: POST /api/admin/exportWithdrawFlows → FundFlowController@exportWithdrawFlows
- **路由名称**: `admin_api_exportWithdrawFlows`

## 路由: POST /api/admin/undepositFlowList → FundFlowController@undepositFlowList
- **路由名称**: `admin_api_undepositFlowList`
- **为什么这样做（业务目的）**: 未入金用户列表(有MT4流水未匹配到入金记录)，风控追查。
- **涉及的数据库表**: `mt4_trades`, `deposit_records`, `user_infos`

## 路由: POST /api/admin/exportUndepositFlows → FundFlowController@exportUndepositFlows
- **路由名称**: `admin_api_exportUndepositFlows`

## 路由: POST /api/admin/neverDepositUserList → FundFlowController@neverDepositUserList
- **路由名称**: `admin_api_neverDepositUserList`
- **为什么这样做（业务目的）**: 从未入金用户列表，运营分析。
- **涉及的数据库表**: `user_infos`, `deposit_records`

---

### 十三、返佣管理（8条）| CommissionController + RealtimeCommissionController + BatchCreditImportController

---

## 路由: POST /api/admin/commissions → CommissionController@index
- **路由名称**: `admin_api_commissionList`
- **入参**: page/limit/user_id/status/date_range
- **执行步骤**: 分页`commission_records`→数据范围
- **涉及的数据库表**: `commission_records`, `user_infos`

## 路由: POST /api/admin/commissionList → CommissionController@index (别名)

## 路由: POST /api/admin/commissionSettle → CommissionController@settle
- **路由名称**: `admin_api_commissionSettle`
- **执行步骤**: 执行结算→汇总一段时间交易→计算佣金→创建`commission_records`→写`commission_rebate_payouts`
- **涉及的数据库表**: `commission_records`, `commission_rebate_payouts`, `mt4_trades`

## 路由: POST /api/admin/commission-transfers/reconciliation-cases → CommissionController@reconciliationCases
- **路由名称**: `admin_api_commissionTransferReconciliationList`
- **执行步骤**: 查`commission_transfers`对账案例列表
- **涉及的数据库表**: `commission_transfers`

## 路由: GET /api/admin/commission-transfers/reconciliation-cases/{transfer} → CommissionController@reconciliationCase
- **路由名称**: `admin_api_commissionTransferReconciliationDetail`
- **入参**: transfer路径变量(transfer_id)

## 路由: POST /api/admin/commission-transfers/reconciliation-cases/{transfer}/decisions → CommissionController@reconcileTransfer
- **路由名称**: `admin_api_commissionTransferReconcile`
- **入参**: transfer路径变量; decision(approve/reject)
- **涉及的数据库表**: `commission_transfers`, `commission_transfer_outboxes`

## 路由: POST /api/admin/realtimeCommissionList → RealtimeCommissionController@realtimeCommissionList
- **路由名称**: `admin_api_realtimeCommissionList`
- **为什么这样做（业务目的）**: 读`mt4_trades` COMMENT命中旧返佣关键词的正向余额记录实时展示。
- **涉及的数据库表**: `mt4_trades`, `agent_descendants`

## 路由: POST /api/admin/exportRealtimeCommissions → RealtimeCommissionController@exportRealtimeCommissions
- **路由名称**: `admin_api_exportRealtimeCommissions`

## 路由: POST /api/admin/creditImportList → BatchCreditImportController@creditImportList
- **路由名称**: `admin_api_creditImportList`
- **涉及的数据库表**: `credit_imports`

## 路由: POST /api/admin/createCreditImport → BatchCreditImportController@createCreditImport
- **路由名称**: `admin_api_createCreditImport`
- **入参**: file(CSV)。code=3002/3003/3006。
- **涉及的数据库表**: `credit_imports`, `user_infos`

## 路由: POST /api/admin/creditImportTemplate → BatchCreditImportController@creditImportTemplate
## 路由: POST /api/admin/exportCreditImports → BatchCreditImportController@exportCreditImports
## 路由: POST /api/admin/retryCreditImport/{id} → BatchCreditImportController@retryCreditImport
## 路由: POST /api/admin/syncCreditImport/{id} → BatchCreditImportController@syncCreditImport

---

### 十四、系统配置（3条）| SystemConfigController + ExchangeRateController

---

## 路由: POST /api/admin/system-configs → SystemConfigController@index
- **路由名称**: `admin_api_systemConfigList`
- **涉及的数据库表**: `system_configs`

## 路由: POST /api/admin/systemConfigList → SystemConfigController@index (别名)

## 路由: POST /api/admin/updateSystemConfig → SystemConfigController@update
- **路由名称**: `admin_api_updateSystemConfig`
- **入参**: key必填/value必填
- **涉及的数据库表**: `system_configs`

## 路由: POST /api/admin/operationLogs → SystemConfigController@logs
- **路由名称**: `admin_api_operationLogs`
- **执行步骤**: 分页查`operation_logs`
- **涉及的数据库表**: `operation_logs`

## 路由: POST /api/admin/operation-logs → SystemConfigController@logs (别名)

## 路由: POST /api/admin/exchangeRateInfo → ExchangeRateController@info
- **路由名称**: `admin_api_exchangeRateInfo`
- **执行步骤**: 查`system_configs`汇率配置

## 路由: POST /api/admin/updateExchangeRate → ExchangeRateController@update
- **路由名称**: `admin_api_updateExchangeRate`
- **入参**: deposit_rate可选/withdraw_rate可选

---

### 十五、在线用户（2条）| OnlineUserController

---

## 路由: POST /api/admin/onlineUserList → OnlineUserController@onlineUserList
- **路由名称**: `admin_api_onlineUserList`
- **执行步骤**: 查`user_onlines`最近活跃记录→分页
- **涉及的数据库表**: `user_onlines`, `user_infos`

## 路由: POST /api/admin/forceOfflineUser/{id} → OnlineUserController@forceOffline
- **路由名称**: `admin_api_forceOfflineUser`
- **入参**: id路径变量(user_logins.id)
- **执行步骤**: `Cache::forget('sso:user:'.id)`强制下线

---

### 十六、产品/交易品种（5条）| ProductionController

---

## 路由: POST /api/admin/productionList → ProductionController@productionList
- **路由名称**: `admin_api_productionList`
- **执行步骤**: 查`symbol_prices`+汇总`mt4_trades`当前持仓→分页
- **涉及的数据库表**: `symbol_prices`, `mt4_trades`

## 路由: POST /api/admin/exportProductions → ProductionController@exportProductions
- **路由名称**: `admin_api_exportProductions`

## 路由: POST /api/admin/createProduction → ProductionController@createProduction
- **路由名称**: `admin_api_createProduction`
- **入参**: symbol必填/name必填/digits可选/contract_size可选/status可选
- **涉及的数据库表**: `symbol_prices`

## 路由: POST /api/admin/updateProduction/{id} → ProductionController@updateProduction
- **路由名称**: `admin_api_updateProduction`

## 路由: POST /api/admin/deleteProduction/{id} → ProductionController@deleteProduction
- **路由名称**: `admin_api_deleteProduction`

---

### 十七、礼品管理（8条）| GiftController

---

## 路由: POST /api/admin/giftShipmentList → GiftController@shipmentList
- **路由名称**: `admin_api_giftShipmentList`
- **涉及的数据库表**: `gift_shipments`, `user_addresses`, `gift_items`

## 路由: POST /api/admin/exportGiftShipments → GiftController@exportGiftShipments
- **路由名称**: `admin_api_exportGiftShipments`

## 路由: POST /api/admin/giftAddressList → GiftController@addressList
- **路由名称**: `admin_api_giftAddressList`
- **涉及的数据库表**: `user_addresses`, `user_infos`

## 路由: POST /api/admin/sendGift → GiftController@sendGift
- **路由名称**: `admin_api_sendGift`
- **入参**: user_id必填/gift_item_id必填/address_id必填/quantity可选
- **执行步骤**: 事务写`gift_shipments`→减库存
- **涉及的数据库表**: `gift_shipments`, `gift_items`, `user_addresses`

## 路由: POST /api/admin/updateGiftShipment/{id} → GiftController@updateShipment
- **路由名称**: `admin_api_updateGiftShipment`
- **入参**: status可选/tracking_no可选

## 路由: POST /api/admin/giftItemList → GiftController@giftItemList
- **路由名称**: `admin_api_giftItemList`
- **涉及的数据库表**: `gift_items`

## 路由: POST /api/admin/createGiftItem → GiftController@createGiftItem
- **路由名称**: `admin_api_createGiftItem`
- **入参**: name必填/points必填/stock必填/image可选/description可选

## 路由: POST /api/admin/updateGiftItem/{id} → GiftController@updateGiftItem
- **路由名称**: `admin_api_updateGiftItem`

## 路由: POST /api/admin/deleteGiftItem/{id} → GiftController@deleteGiftItem
- **路由名称**: `admin_api_deleteGiftItem`

---

### 十八、新闻公告（5条）| NewsController

---

## 路由: POST /api/admin/news → NewsController@index
- **路由名称**: `admin_api_newsList`
- **涉及的数据库表**: `news`

## 路由: POST /api/admin/newsList → NewsController@index (别名)

## 路由: POST /api/admin/createNews → NewsController@store
- **路由名称**: `admin_api_createNews`
- **入参**: title必填/content必填/status可选

## 路由: POST /api/admin/updateNews/{id} → NewsController@update
- **路由名称**: `admin_api_updateNews`

## 路由: POST /api/admin/deleteNews/{id} → NewsController@destroy
- **路由名称**: `admin_api_deleteNews`

## 路由: POST /api/admin/toggleNews/{id} → NewsController@togglePublish
- **路由名称**: `admin_api_toggleNews`
- **执行步骤**: 切换发布⇔下架状态

---

### 十九、支付通道（5条）| PaymentChannelController

---

## 路由: POST /api/admin/channels → PaymentChannelController@index
- **路由名称**: `admin_api_channelList`
- **涉及的数据库表**: `payment_channels`

## 路由: POST /api/admin/channelList → PaymentChannelController@index (别名)

## 路由: POST /api/admin/createChannel → PaymentChannelController@store
- **路由名称**: `admin_api_createChannel`
- **入参**: name必填/gateway必填/config_json可选/status可选

## 路由: POST /api/admin/updateChannel/{id} → PaymentChannelController@update
- **路由名称**: `admin_api_updateChannel`

## 路由: POST /api/admin/deleteChannel/{id} → PaymentChannelController@destroy
- **路由名称**: `admin_api_deleteChannel`

## 路由: POST /api/admin/toggleChannel/{id} → PaymentChannelController@toggleEnable
- **路由名称**: `admin_api_toggleChannel`
- **执行步骤**: 切换启用/禁用

---

### 二十、管理员管理（6条）| AdminController + LegacyAdminActionController

---

## 路由: POST /api/admin/admins → AdminController@index
- **路由名称**: `admin_api_adminList`
- **执行步骤**: 分页查`admins`→关联`roles`
- **涉及的数据库表**: `admins`, `roles`

## 路由: POST /api/admin/adminList → AdminController@index (别名)

## 路由: POST /api/admin/createAdmin → AdminController@store
- **路由名称**: `admin_api_createAdmin`
- **入参**: username必填/password必填/email可选/name必填/role_id必填
- **涉及的数据库表**: `admins`, `admin_roles`

## 路由: POST /api/admin/updateAdmin/{id} → AdminController@update
- **路由名称**: `admin_api_updateAdmin`
- **入参**: id路径变量; email可选/name可选/role_id可选/status可选

## 路由: POST /api/admin/resetAdminPassword/{id} → AdminController@resetPassword
- **路由名称**: `admin_api_resetAdminPassword`
- **入参**: id路径变量; password必填
- **执行步骤**: 重置密码→清该管理员SSO缓存

## 路由: POST /api/admin/deleteAdmin/{id} → AdminController@destroy
- **路由名称**: `admin_api_deleteAdmin`
- **执行步骤**: 不能删自己(code=2021)→删除

## 路由: POST /api/admin/changeAdminStatus → LegacyAdminActionController@changeAdminStatus
- **路由名称**: `admin_api_changeAdminStatus`
- **为什么这样做（业务目的）**: 旧兼容——启用/禁用管理员
- **涉及的数据库表**: `admins`

---

### 二十一、凭证审核（3条）| VoucherController

---

## 路由: POST /api/admin/vouchers → VoucherController@index
- **路由名称**: `admin_api_voucherList`
- **入参**: page/limit/status/user_id
- **涉及的数据库表**: `voucher_infos`, `user_infos`, `payment_channels`

## 路由: POST /api/admin/voucherList → VoucherController@index (别名)

## 路由: POST /api/admin/voucherApprove/{id} → VoucherController@approve
- **路由名称**: `admin_api_voucherApprove`
- **入参**: id路径变量
- **执行步骤**: 通过→status=1→创建`deposit_records`入金→写`payment_settlement_outboxes`
- **涉及的数据库表**: `voucher_infos`, `deposit_records`, `payment_settlement_outboxes`

## 路由: POST /api/admin/voucherReject/{id} → VoucherController@reject
- **路由名称**: `admin_api_voucherReject`
- **执行步骤**: 拒绝→status=2

---

### 二十二、风控管理（6条）| RiskController

---

## 路由: POST /api/admin/riskPositions → RiskController@positions
- **路由名称**: `admin_api_riskPositions`
- **执行步骤**: 高风险持仓监控(大仓位/高杠杆/浮亏超阈值)→分页
- **涉及的数据库表**: `mt4_trades`, `user_infos`

## 路由: POST /api/admin/riskMarginCalls → RiskController@marginCalls
- **路由名称**: `admin_api_riskMarginCalls`
- **执行步骤**: 追加保证金通知→查风险率低于阈值用户持仓
- **涉及的数据库表**: `mt4_trades`, `mt4_users`, `user_infos`

## 路由: POST /api/admin/riskIpList → RiskController@riskIpList
- **路由名称**: `admin_api_riskIpList`
- **为什么这样做（业务目的）**: 异常IP风控——查`user_login_logs`中同一IP登录多个业务账号的风险聚合结果
- **涉及的数据库表**: `user_login_logs`, `user_infos`

## 路由: POST /api/admin/riskIpDetail → RiskController@riskIpDetail
- **路由名称**: `admin_api_riskIpDetail`
- **入参**: login_ip必填
- **执行步骤**: 按IP展开登录明细/交易统计/资金统计
- **涉及的数据库表**: `user_login_logs`, `user_infos`, `mt4_trades`, `deposit_records`

## 路由: POST /api/admin/riskForceClose/{id} → RiskController@forceClose
- **路由名称**: `admin_api_riskForceClose`
- **入参**: id路径变量(mt4_trades.ticket)
- **执行步骤**: 强制平仓→调MT4 Manager API→更新`mt4_trades`
- **涉及的数据库表**: `mt4_trades`

---

### 二十三、黑名单（4条）| BlacklistController

---

## 路由: POST /api/admin/blacklistList → BlacklistController@index
- **路由名称**: `admin_api_blacklistList`
- **涉及的数据库表**: `blacklists`

## 路由: POST /api/admin/createBlacklist → BlacklistController@store
- **路由名称**: `admin_api_createBlacklist`
- **入参**: user_id可选/email可选/phone可选/id_card可选/reason可选

## 路由: POST /api/admin/updateBlacklist/{id} → BlacklistController@update
- **路由名称**: `admin_api_updateBlacklist`

## 路由: POST /api/admin/deleteBlacklist/{id} → BlacklistController@destroy
- **路由名称**: `admin_api_deleteBlacklist`

---

### 二十四、注销申请（3条）| CancelApplyController

---

## 路由: POST /api/admin/cancelApplyList → CancelApplyController@index
- **路由名称**: `admin_api_cancelApplyList`
- **涉及的数据库表**: `cancel_applies`, `user_infos`

## 路由: POST /api/admin/cancelApplyApprove/{id} → CancelApplyController@approve
- **路由名称**: `admin_api_cancelApplyApprove`
- **入参**: id路径变量
- **执行步骤**: 通过→status=1→禁用`user_logins`+`user_infos`→MT4清理→清SSO
- **涉及的数据库表**: `cancel_applies`, `user_logins`, `user_infos`

## 路由: POST /api/admin/cancelApplyReject/{id} → CancelApplyController@reject
- **路由名称**: `admin_api_cancelApplyReject`
- **执行步骤**: 拒绝→status=2
- **涉及的数据库表**: `cancel_applies`

---

### 二十五、交易订单（13条）| TradeController + PositionSummaryController + RightsSummaryController + AdminWhsExpZeroController

---

## 路由: POST /api/admin/tradeList → TradeController@index
- **路由名称**: `admin_api_tradeList`
- **入参**: page/limit/user_id/symbol/cmd/date_range
- **执行步骤**: 多条件筛选`mt4_trades`→数据范围→分页
- **涉及的数据库表**: `mt4_trades`, `user_infos`
- **返回值**: ticket/symbol/cmd/volume/open_price/close_price/profit/commission/time

## 路由: POST /api/admin/openPositions → TradeController@openPositions
- **路由名称**: `admin_api_openPositions`
- **执行步骤**: 查`mt4_trades` WHERE CLOSE_TIME IS NULL(持仓单)→分页
- **涉及的数据库表**: `mt4_trades`

## 路由: POST /api/admin/closedPositions → TradeController@closedPositions
- **路由名称**: `admin_api_closedPositions`
- **执行步骤**: 查`mt4_trades` WHERE CLOSE_TIME IS NOT NULL(已平仓)→分页
- **涉及的数据库表**: `mt4_trades`

## 路由: POST /api/admin/exportClosedPositions → TradeController@exportClosedPositions
- **路由名称**: `admin_api_exportClosedPositions`

## 路由: POST /api/admin/tradeSummary → TradeController@summary
- **路由名称**: `admin_api_tradeSummary`
- **执行步骤**: 交易汇总统计(按品种/用户/日期聚合手数/盈亏/手续费)
- **涉及的数据库表**: `mt4_trades`

## 路由: POST /api/admin/rightsSummaryList → RightsSummaryController@rightsSummaryList
- **路由名称**: `admin_api_rightsSummaryList`
- **为什么这样做（业务目的）**: 权益汇总——读`mt4_users`资金快照通过`user_infos.mt4_code`映射业务用户后应用数据范围
- **涉及的数据库表**: `mt4_users`, `user_infos`

## 路由: POST /api/admin/exportRightsSummary → RightsSummaryController@exportRightsSummary
- **路由名称**: `admin_api_exportRightsSummary`

## 路由: POST /api/admin/manualConfirmRightsSettlement/{id} → RightsSummaryController@manualConfirmRightsSettlement
- **路由名称**: `admin_api_manualConfirmRightsSettlement`
- **入参**: id路径变量
- **为什么这样做（业务目的）**: 权益结算手动确认——只确认`rights_settlements`待处理记录不调用MT4

## 路由: POST /api/admin/positionSummaryList → PositionSummaryController@positionSummaryList
- **路由名称**: `admin_api_positionSummaryList`
- **为什么这样做（业务目的）**: 持仓汇总——按用户维度聚合`mt4_trades`手数/盈亏/手续费/品种分类手数
- **涉及的数据库表**: `mt4_trades`, `user_infos`

## 路由: POST /api/admin/exportPositionSummary → PositionSummaryController@exportPositionSummary
- **路由名称**: `admin_api_exportPositionSummary`

## 路由: POST /api/admin/whsExpZeroList → AdminWhsExpZeroController@zeroList
- **路由名称**: `admin_api_whsExpZeroList`
- **为什么这样做（业务目的）**: 体验金清零列表
- **涉及的数据库表**: `whs_exp_zeros`

## 路由: POST /api/admin/whsExpZeroRecords → AdminWhsExpZeroController@recordList
- **路由名称**: `admin_api_whsExpZeroRecords`
- **执行步骤**: 查清零记录详情

## 路由: POST /api/admin/whsExpZero → AdminWhsExpZeroController@oneKeyZero
- **路由名称**: `admin_api_whsExpZero`
- **执行步骤**: 一键清零体验金→批量处理→调MT4 API
- **涉及的数据库表**: `whs_exp_zeros`, `mt4_trades`

---

### 二十六、大代理管理（5条）| BigAgentController + LegacyAdminActionController

---

## 路由: POST /api/admin/bigAgentList → BigAgentController@index
- **路由名称**: `admin_api_bigAgentList`
- **涉及的数据库表**: `big_agents`

## 路由: POST /api/admin/changeBigAgentStatus → LegacyAdminActionController@changeBigAgentStatus
- **路由名称**: `admin_api_changeBigAgentStatus`

## 路由: POST /api/admin/createBigAgent → BigAgentController@store
- **路由名称**: `admin_api_createBigAgent`
- **入参**: username必填/password必填/name必填/email可选

## 路由: POST /api/admin/updateBigAgent/{id} → BigAgentController@update
- **路由名称**: `admin_api_updateBigAgent`

## 路由: POST /api/admin/deleteBigAgent/{id} → BigAgentController@destroy
- **路由名称**: `admin_api_deleteBigAgent`

---

### 二十七、文件上传（1条）

---

## 路由: POST /api/admin/uploadFile → \App\Http\Controllers\Common\UploadController@upload
- **路由名称**: `admin_api_uploadFile`
- **中间件链路**: `jwt.auth:admin → sso:admin → check.permission:admin`
- **为什么这样做（业务目的）**: 后台通用文件上传(跨命名空间Common)。统一上传逻辑。
- **入参**: file必填/type可选

---

## 附录：新旧项目路由对照

| 旧项目 V3 典型路径 | 新项目路由 | 改进点 |
|---|---|---|
| `/index.php/user/login` | `POST /api/front/auth/login` | Session→JWT, SSO支持 |
| `/index.php/user/register` | `POST /api/front/auth/register` | 多步验证码, Service事务, MT4异步 |
| `/admin.php/login` | `POST /api/admin/login` | JWT替代Cookie |
| `/admin.php/user/list` | `POST /api/admin/userList` | Named Route + 权限表鉴权 |
| `/admin.php/agent/create` | `POST /api/admin/createAgent` | 数据范围Service过滤 |
| `/admin.php/withdraw/import` | `POST /api/admin/createWithdrawImport` | BatchAmountImportController闭环 |
| 无对应 | `POST /api/front/auth/token/refresh` | 新增JWT刷新机制 |
| 无对应 | `POST /api/admin/forceOfflineUser/{id}` | 新增强制下线(清SSO缓存) |
| 无对应 | `POST /api/admin/riskIpList` | 新增异常IP风控 |
| 无对应 | `POST /api/admin/whsExpZero` | 新增一键清零体验金 |

---

> **文档结束** | 总计覆盖 363 条路由 | 39个响应码 | 56个数据表 | 17个中间件
