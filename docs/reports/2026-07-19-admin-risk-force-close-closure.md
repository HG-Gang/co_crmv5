# 后台风控强平真实逻辑闭环

- 日期：2026-07-19
- 缺口：`RiskController@forceClose` 原先仅返回“信号已发送”伪成功，未调用 MT4、未写审计。

## 执行链路

```text
POST /api/admin/riskForceClose/{id}
  -> api + jwt.auth:admin + sso:admin + check.permission:admin
  -> RiskController@forceClose
  -> 校验路由 id 为严格整数
  -> 查询 mt4_trades 未平仓记录（cmd 0-5, close_time null/0）
  -> AdminDataScopeService 数据范围
  -> RiskForceCloseGateway::close(login, ticket, comment)
      -> Mt4RiskForceCloseGateway
      -> Mt4ManagerService::closeOrder -> ORDER_CLOSE
  -> 成功：写 operation_logs（content 含 risk_force_close / ticket / provider_reference）
  -> 失败：返回 force_close_failed，不写伪成功，不改本地 open 持仓镜像
```

## 变更文件

| 文件 | 作用 |
|---|---|
| `app/Contracts/RiskForceCloseGateway.php` | 网关契约 |
| `app/Services/Risk/RiskForceCloseResult.php` | closed/rejected/unknown/retryable 结果 |
| `app/Services/Risk/Mt4RiskForceCloseGateway.php` | MT4 响应映射 |
| `app/Services/Mt4ManagerService.php` | `closeOrder()` / `ORDER_CLOSE` |
| `app/Providers/Mt4ServiceProvider.php` | 绑定网关 |
| `app/Http/Controllers/Admin/RiskController.php` | 真实强平 + 审计 |
| `resources/lang/en|zh-CN/admin.php` | `force_close_failed` |
| `tests/Feature/AdminRiskForceCloseGatewayClosureModuleTest.php` | 成功/失败/命令契约 |

## 测试

```text
AdminRiskForceCloseGatewayClosureModuleTest  OK (3, 12)
AdminRiskForceCloseRouteIdValidationClosureModuleTest  OK
```
