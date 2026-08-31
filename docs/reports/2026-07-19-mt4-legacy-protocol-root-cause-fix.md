# MT4 协议根因修复报告

- 日期：2026-07-19
- 范围：新项目 `Mt4ManagerService` 与旧项目真实 MT4 外部接口对齐

## 1. 根因（不是配置单独问题）

对照旧项目两套实现后确认：**新项目协议写错了**。

| 维度 | 旧项目（可工作） | 新项目修复前（错误） |
|---|---|---|
| 查询串 | `act=deposit&ver=000005&key=...&acc=&amt=&cmt=` | `USER_DEPOSIT:acc=\|amt=\|cmt=\|key=\|ver=` |
| 帧格式 | `E{query}\r\nQUIT\r\n` | `{CMD}:k=v\|...\n` |
| 动作名 | `deposit` / `withdrawal` / `accountinfo` / `lock_user` | `USER_DEPOSIT` / `USER_WITHDRAW` / `USER_INFO_GET` / `USER_LOCK` |
| 响应 | `&` 分隔 `k=v`，读到 `end` | `STATUS\|MSG\|DATA` 管道分隔 |
| 成功码 | `err=0`，票据 `tck` | 假设 `status=ok` + data[0] |
| 连接策略 | 每命令新连接 + 重试 | 长连接复用 + 单次 fgets |

因此即使用正确 host/port/key，**旧 MT4 外部服务也听不懂新客户端发出的命令**，表现为“测不通”。

## 2. 旧项目证据源

1. `new_co_gmtk_crmV3/app/Http/Controllers/CommonController/Abstract_Service_Controller.php`
   - `_exte_mt4_query_request`: `fwrite($fp, "E$query\r\nQUIT\r\n")`
   - 响应 `explode('&')`，成功看 `err=0`，入金票据 `tck`
2. `new_co_gmtk_crmV3/app/Http/Services/Mt4ManagerService.php`
   - `buildBaseParams`: `act=&ver=&key=`
   - 同样 `E{$query}\r\nQUIT\r\n`
3. 旧配置：`config/mt4.php` → host/port/ver/key/timeout/retries

## 3. 修复内容

### 3.1 重写 `app/Services/Mt4ManagerService.php`
- 发送：`Eact=...&ver=...&key=...&...\r\nQUIT\r\n`
- 读取：循环到 `end`
- 解析：`&` / `=` → 字段数组
- 规范化：
  - `err=0` → `status=ok`，`ticket=tck`
  - 业务错误 → `status=error`，`error_code=err`
  - 连接/写/读失败 → `connection_failed` / `write_failed` / `read_timeout`
- 动作名对齐：`deposit`、`withdrawal`、`credit-in`、`accountinfo`、`lock_user`、`unlock_user`、`enable_user`、`disable_user`、`change_group`、`update_user`、`reset_password`、`change_password`
- accountinfo 映射：`bal/eqy/mrg/fre/lvg` → balance/equity/margin/free_margin/leverage
- 支持 retries / retry_delay（仅 connection_failed）

### 3.2 配置 `config/mt4.php`
- 兼容 `MT4_*` 与旧 `MT4_MANAGER_*`
- 增加 `retries` / `retry_delay`
- 默认 `enabled=false`（测试不误连真实 socket）

### 3.3 Provider
- 注入 retries / retry_delay

### 3.4 网关层
- 无需改契约：仍消费 `status/ticket/error_code`
- deposit/withdraw/credit 等 gateway 测试保持通过

## 4. 测试结果

```text
Mt4ManagerServiceLegacyProtocolClosureModuleTest  OK (5)
Mt4ManagerServiceSocketLifecycleTest              OK (2)
Mt4DepositSettlementGatewayClosureModuleTest      OK (11)
Mt4WithdrawalFundingGatewayClosureModuleTest      OK (11)
Mt4CreditSettlementGatewayClosureModuleTest       OK (7)
Mt4DepositRefundGatewayClosureModuleTest          OK (6)
Mt4ManagerServiceLocalizationTest                 OK (2)
```

## 5. 联调检查清单（真实环境）

在 `.env` 设置（与旧部署一致）：

```env
MT4_ENABLED=true
MT4_HOST=172.31.29.140          # 或 MT4_MANAGER_HOST
MT4_PORT=3490
MT4_API_KEY=Ar7MPOxL            # 生产密钥按环境
MT4_API_VERSION=000005
MT4_TIMEOUT=30
MT4_RETRIES=3
MT4_RETRY_DELAY=1
```

建议最小连通验证：
1. `accountinfo`（getAccountInfo）对已知 login
2. 小额 `deposit` 到测试账号
3. 对应 `withdrawal`
4. `lock_user` / `unlock_user`

## 6. 结论

- **根因**：协议不兼容（命令帧/动作名/响应解析全错），不是单纯“开关没开”。
- **修复**：客户端按旧项目协议重写，网关兼容层保留。
- **下一步**：在可访问 MT4 Manager 的环境打开 `MT4_ENABLED` 做真实联调。
