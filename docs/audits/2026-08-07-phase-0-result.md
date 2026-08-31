# Phase 0 安全与迁移证据基线结果

- 执行日期：2026-08-07
- 状态：完成
- 旧项目：`D:\Software\PhpProject\Demo\new_co_gmtk_crmv3`（只读）
- 当前项目：`D:\Software\PhpProject\Demo\co_crmv5`
- 数据边界：未准备、迁移或写入任何数据库；路由审计进程强制使用 `co_crmv5_test` 身份。

## 命令结果

| 验证项 | 实际命令 | 退出码 | 结果 |
|---|---|---:|---|
| 旧源码清单 | `php scripts\generate-legacy-source-inventory.php ...` | 0 | Controller 85、方法 967、Blade 223、表单 174、AJAX 96 |
| 旧新路由审计 | `php artisan legacy-routes:audit ... --scope=all ...` | 0 | 395 条：匹配 375、明确方法限制 20、gap 0 |
| 业务核验矩阵 | `php scripts\generate-legacy-implementation-matrix.php ...` | 0 | 旧路由方法 475 |
| 当前项目表面清单 | `php scripts\generate-current-project-surface-inventory.php ...` | 0 | Controller 79、路由文件 8、Blade 180、JS 41、CSS 14、migration 130、测试 581 |
| Phase 0 目标测试 | 六个目标 Unit 测试文件逐一执行 | 0 | 48 tests、240 assertions、0 failures、0 errors |
| Composer 配置 | `composer validate --no-check-publish` | 0 | 配置有效；存在既有精确版本约束警告 |
| PHP 语法 | `php -l` 检查 9 个本阶段相关 PHP 文件 | 0 | 全部无语法错误 |
| 数据库目标静态检查 | `Select-String -Path scripts\prepare-test-database.php -Pattern 'co_crmv5\|hank_zl_data'` | 0 | 仅命中 `co_crmv5_test` 两处 |

Windows 下批处理文件会把 PHPUnit `--filter` 中的 `|` 解释为命令分隔符，因此目标测试按文件逐一执行：

| 测试文件 | Tests | Assertions |
|---|---:|---:|
| `TestDatabaseGuardTest.php` | 20 | 46 |
| `TestRunnerContractTest.php` | 6 | 85 |
| `LegacySourceInventoryTest.php` | 4 | 35 |
| `LegacyRouteInventoryTest.php` | 2 | 14 |
| `LegacyImplementationMatrixTest.php` | 13 | 41 |
| `CurrentProjectSurfaceInventoryTest.php` | 3 | 19 |
| **合计** | **48** | **240** |

## 迁移证据状态

| 状态 | 数量 |
|---|---:|
| 已完成业务核验 `verified` | 185 |
| 待人工业务核验 `needs_manual_business_review` | 290 |
| 旧源码证据未解决 | 0 |
| 当前路由未匹配 | 0 |

静态路由匹配不会自动提升为 `verified`。Phase 0 只建立安全基线与可重复清单；剩余 290 项仍需在后续阶段逐模块完成业务闭环核验。

## Blade 页面族

| 页面族 | 文件数 |
|---|---:|
| `admin_crmui` | 44 |
| `admin_layui` | 44 |
| `front_crmui` | 42 |
| `front_layui` | 41 |
| `shared_views` | 9 |
| **合计** | **180** |

机器校验结果：`BLADE_FILES=180`，`UNKNOWN_BLADE_FAMILIES=0`。

## 生成物 SHA-256

| 生成物 | SHA-256 |
|---|---|
| `storage/app/audits/旧项目源码逻辑清单.json` | `AC416FE06CD6B30D25499A0178433DD8148B59EF5654EC12250DF4DE8B9B54C7` |
| `storage/app/audits/current-legacy-route-audit.json` | `F84E277D38F732AD796794E57F1791BA1AB8A07E4B6FE288FD49FF8E6C3A9D7D` |
| `storage/app/audits/旧项目模块逻辑迁移核验矩阵.json` | `9A37A7A05702955A5DC9F0C53D62A5F4116D2F88E9FF7E936A38F02B50508E92` |
| `storage/app/audits/2026-08-07-current-project-surface-inventory.json` | `E97AB226CC094CDD9A89D261C7B52253ECDBA7B370B6D06243674E5FD3A96E79` |

## 完成判断

- 目标测试：零 Failure、零 Error。
- 路由审计：`gaps = 0`。
- 当前 Blade：全部属于五个允许页面族。
- 旧库与新正式库：本阶段无连接、迁移、准备或写入命令；测试库准备器仅允许 `co_crmv5_test`。
- 四组 JSON/Markdown 证据均已生成，四个 JSON 均有 SHA-256。

Phase 0 完成。该结论不表示全项目已达到百分百替换；当前可信的后续入口是矩阵中的 290 个待人工业务核验项。
