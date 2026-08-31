# co_crmv5 — CRM 管理系统（Laravel 双 UI 家族）

前后端不分离的 Laravel CRM 项目，由旧项目 `new_co_gmtk_crmv3` 全量等价迁移而来。后台与前台各提供 **Layui** 与 **CrmUI** 两套视觉家族，业务逻辑共用同一批现代 API 与数据表。

> 旧项目目录 `DB1` 与旧库 `DB2` 仅作只读参照，禁止任何写入。

## 技术栈

| 项 | 内容 |
| --- | --- |
| 框架 | Laravel 8.83 / PHP 7.4+ |
| 数据库 | MySQL（端口 3307） |
| 视图 | Blade + Layui / CrmUI 双 UI 家族（交互全部 CSS/JS 实现） |
| 测试 | PHPUnit 9.5（仅允许写 `co_crmv5_test` 测试库） |
| MT4 | 永久禁用；所有 MT4 同步走 `Mt4ManagerService` 失败关闭 |

## 数据库约定

| 库名 | 用途 | 写入约束 |
| --- | --- | --- |
| `co_crmv5` | 正式库 | 业务开发可写；PHPUnit 禁写 |
| `co_crmv5_test` | 测试库 | PHPUnit 唯一允许写入的库 |
| `hank_zl_data` | 旧项目库 | 永久只读 |

## 快速开始

```powershell
composer install
copy .env.example .env      # 配置 DB_HOST=127.0.0.1、DB_PORT=3307、DB_DATABASE=co_crmv5 等
php artisan key:generate
php artisan migrate          # 空库初始化 schema
php artisan serve            # http://127.0.0.1:8000
```

常用入口：

- 后台 Layui：`/admin/login`；后台 CrmUI：`/admin-crmui/login`
- 前台 Layui：`/front/login`；前台 CrmUI：`/front-crmui/login`
- 大代理独立壳：`/front-naive/big-agent/*`

## 目录结构要点

```
app/Http/Controllers/
  Admin/        后台现代 API 控制器（含 LegacyAdminController 旧路由兼容层）
  Front/        前台现代 API 控制器
  CrmUi/        CrmUI 页面定义控制器（页面 → API → 列/筛选元数据）
resources/
  admin/layui   后台 Layui Blade 页面族
  admin/crmui   后台 CrmUI Blade 页面族
  front/layui   前台 Layui 页面族
  front/crmui   前台 CrmUI 页面族
public/js/apps/ 各家族页面 JS（layui/pages.js、crmui/admin.js、crmui/front.js）
docs/           进度梳理、审计证据、实施计划与结果报告
storage/app/audits/  权威迁移核验矩阵 JSON
```

## 旧路由兼容层

旧项目的 `index/admin/...` 路由由 `routes/legacy_admin.php` 按 `storage/app/audits/legacy-routes.json` 清单自动注册，统一进入 `LegacyAdminController@handle`：

- 参数归一（驼峰/蛇形别名、嵌套 `data`、默认日期窗口）后转发到对应现代命名路由；
- 响应补回旧协议字段（如 `msg/col`、`rows/total`、`code/msg/count/data/totalRow`）；
- 鉴权走 `legacy.admin.auth` + `legacy_permission_route`，数据范围经 `AdminDataScopeService`。

## 测试

```powershell
# 全量（串行，写 co_crmv5_test）
vendor\bin\phpunit --colors=never

# 关键词过滤示例
vendor\bin\phpunit --colors=never --filter "(?i)withdraw" tests\Feature
```

约束：

- 测试严格隔离，仅写 `co_crmv5_test`；
- `MT4_ENABLED=false`，任何 MT4 真实连接在测试与生产均失败关闭；
- 正式库 `co_crmv5` 在 PHPUnit 中禁写。

## 运维命令

```powershell
php artisan agent-hierarchy:rebuild          # 代理层级闭包只读审计
php artisan agent-hierarchy:rebuild --apply  # 备份后事务化重建（需授权）
php artisan view:cache / view:clear          # Blade 编译缓存
```

日志默认走 `stack → daily` 通道，按天滚动保留 14 天（见 `config/logging.php`）。

## 文档索引

| 文档 | 说明 |
| --- | --- |
| `docs/项目整体进度梳理-2026-08-17.md` | 项目整体进度权威账本 |
| `storage/app/audits/旧项目模块逻辑迁移核验矩阵.json` | 475 条旧路由核验矩阵（当前 475/475 verified） |
| `docs/audits/旧项目路由核验证据.json` | 各批次七维核验证据组 |
| `docs/superpowers/plans/` | 各专项实施计划 |
| `docs/superpowers/guides/dual-ui-implementation-handbook.md` | 双 UI 家族实施手册 |

## 安全红线

1. 旧库 `DB1` 永久只读。
2. PHPUnit 只写 `co_crmv5_test`；正式库禁写测试数据。
3. `database/sql/full_reset_and_migrate.sql` 会清空 `co_crmv5` 全部业务表，执行前必须备份并获用户明确授权。
4. 不伪造测试通过或浏览器验收结论；被安全策略阻塞的验收如实标注 `BLOCKED_BY_BROWSER_POLICY`。
