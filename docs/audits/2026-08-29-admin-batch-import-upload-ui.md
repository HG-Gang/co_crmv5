# 后台批量导入 Excel/CSV 上传 UI 闭环报告（2026-08-29）

> 依据：`docs/audits/2026-08-28-full-serial-audit-handoff.md` §五.1 记录的缺口——
> 「后台批量导入无上传 UI：credit / deposit / withdraw-imports 的『新增』是手工单条表单；
> 旧 Excel URI 经 LegacyAdminController 映射且有 API 测试，但后台无法上传批量文件」。
> 本轮补齐 Layui 与 CrmUI 双家族的批量导入上传 UI，实现并测试全部闭环。

---

## 一、后端契约（既有，未改动）

| 模块 | 上传端点（旧 URI → 现代路由） | 权限 slug |
| --- | --- | --- |
| 批量授信 | `index/admin/credit/creditImportExcel` → `POST /api/admin/createCreditImport` | `admin_batch_credit_import_create` |
| 批量入金 | `index/admin/amount/depositImportExcel` → `POST /api/admin/createDepositImport` | `admin_batch_deposit_import_create` |
| 批量出金 | `index/admin/amount/withdrawImportExcel` → `POST /api/admin/createWithdrawImport` | `admin_batch_withdraw_import_create` |

- 文件字段：multipart `file`（控制器兼容 `file` / `import_file` / `csv_file` 三种字段名）。
- 格式：CSV 含表头（模板下载路由 `*ImportTemplate` 页面已存在）。
- 语义：先逐行校验全批数据，任一行非法整体拒绝；全部通过后单事务批量落库；响应为统一
  `code/message/data` 信封（`data` 为新建记录数组，前端据此展示创建条数）。
- 三个 `create*Import` 控制器同时承载「手工单条」与「文件整批」两种入口，本次 UI 复用同一端点与权限口。

## 二、前端实现（前后端不分离，交互全部 CSS/JS）

### 2.1 共享上传组件（Layui 2.13.5 最新交互）

- 复用 `public/js/shared/layui-upload.js`（CrmUpload）：拖拽选区、文件名/体积展示、状态文案、进度条、
  移除/重选、键盘可达性与行内错误，均与全站上传组件同一套交互。
- 采用 **deferred 模式**（`data-upload-auto="0"`）：组件只做本地校验与 File 缓存，提交时由页面
  组装 FormData 走 `CrmAjax.upload({guard:'admin'})`——后者携带 `Authorization: Bearer` 与 `X-Locale` 头；
  不用 layui 原生 auto 直发（其请求不带管理员 JWT，会被后台鉴权拒绝，这是选择 deferred 的关键原因）。
- 文件约束：`accept=file`、`exts=csv`、`max-size=20480KB`，与后端 CSV 解析口径一致。

### 2.2 Layui 家族（3 页面）

- `resources/admin/layui/{credit-imports,deposit-imports,withdraw-imports}/index.blade.php`：
  按钮区新增「导入 CSV 批量数据」（与新增按钮同一 create 权限 slug）+ 隐藏上传弹窗
  （`*ImportUploadModal`，内含 CrmUpload 块与「开始导入」按钮）。
- `public/js/apps/admin/layui/pages.js`：三个 registry 各新增打开弹窗（layer.open + CrmUpload.init +
  lucide 图标重绘）与提交处理（FormData → `CrmAjax.upload` → 成功后关闭弹窗、复位上传块、刷新表格、
  按 `data` 数组长度提示创建条数；失败透传后端 message，含逐行校验的 `CSV row N: ...` 文案）。

### 2.3 CrmUI 家族（3 页面共用 module-page 局部）

- `app/Http/Controllers/CrmUi/Admin/PageController.php`：`importActions()` 增加
  `['key' => 'import', 'route' => <create 路由>, 'permission' => <create slug>]` 声明，三处调用点分别传入
  各模块的 create 路由与权限。
- `resources/admin/crmui/partials/module-page.blade.php`：页面级动作循环自动渲染导入按钮
  （`data-crmui-action="import"`）；新增 `data-crmui-import-modal` 弹窗（同 CrmUpload 块结构 + 提交按钮）。
- `public/js/apps/crmui/admin.js`：`bindPageActions` 增加 `import` 分支；新增 `openImportDialog` /
  `closeImportDialog` / `submitImportDialog`（含提交 busy 态、焦点恢复、成功后 `loadPage` 刷新）；
  弹窗关闭（背景/关闭钮）与提交事件统一绑定。

### 2.4 多语言

- `resources/lang/{zh-CN,en}/admin.php`：新增 `import_csv_file` / `import_csv_hint` / `import_csv_start` /
  `import_csv_result`（结果文案带 `:count` 占位）。
- `resources/lang/{zh-CN,en}/crmui.php`：`actions.import` 新增（CrmUI 动作标签自动解析）。

## 三、测试闭环

- `AdminBatchCreditImportModuleTest` / `AdminBatchAmountImportModuleTest` 扩展 UI 契约断言：
  Layui 侧导入按钮/弹窗/上传块/提交按钮存在性 + create 权限 slug；CrmUI 侧
  `data-crmui-action="import"`、`data-crmui-import-modal`、`data-crm-upload="csv_import"`、
  `data-crmui-import-submit` 存在性。合计 `15 tests / 170 assertions` exit 0。
- 组合回归 `(?i)import|Localization|CrmUiStack|LegacyUiReplacement`：`175 tests / 2247 assertions` exit 0。
- JS 语法：`pages.js` / `admin.js` / `layui-upload.js` `node --check` 全部通过；Blade `view:cache` 通过。

## 四、过程事故与修复（如实记录）

- 修改 `crmui.php` 语言键时，一次性 PHP 内联脚本的两处缺陷（`strpos` 首个锚点命中文件头注释区、
  行首定位把 `<?php` 的 `<` 一并吞入）导致两份语言文件头部损坏（`<?php` 开标签缺失，整个文件被当作
  内联 HTML 输出，`require` 不再返回数组），引发 `CrmUiStackTest` 未解析翻译键失败。
- 已逐字节定位（`od -c` 文件头）并修复：恢复 `<?php` 开标签 + 在 `actions` 数组 `'template'` 行后正确插入
  `'import'` 键；修复后 `require` 返回数组且键就位，`CrmUiStackTest`（6 tests / 520 assertions）与组合回归全部恢复绿色。
- 教训：批量语言键插入必须走脚本文件并在插入后断言 `require` 返回结构与既有键存活，禁止内联双引号 heredoc。

## 五、最终验证

> 本轮完成后全量串行 `storage/logs/full-serial-20260829-144445.out`（13:25.967）：
> **`OK (4305 tests, 80343 assertions)` / `PHPUNIT_EXIT=0`** —— 新增 UI 契约断言并入既有测试方法
> （测试总数不变、断言数较加固轮 135656 的 80315 增加 28），全量零回归。
