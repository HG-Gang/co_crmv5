# 「译文当提交值」同类缺陷全库排查报告（2026-08-29）

> 背景：`docs/audits/2026-08-28-full-serial-audit-handoff.md` §五.3 建议全库排查「译文当 option value」模式。
> 原始缺陷：`resources/front/layui/flow/index.blade.php` 曾把 `__('front.bank_transfer')` 译文当作
> option 提交值，语言切换后筛选静默失效；已改为稳定键 `bank_transfer` / `crypto_currency`
> 并由 `tests/Feature/FrontFlowWithdrawSourceLocaleClosureModuleTest.php` 红绿锁定。

---

## 一、排查范围与方法

| 检查项 | 范围 | 方法 |
| --- | --- | --- |
| option value 挂译文 | `resources/**/*.blade.php` | `grep -rEn "value=[\"']\{\{ *(__|trans)"`，0 命中 |
| value 挂 trans() | 同上 | `grep -rn "value=\"{{ trans("`，0 命中 |
| data-* 属性挂译文 | 同上 | `grep -rEn "data-[a-z-]+=[\"']\{\{ *(__|trans)"`，17 命中，逐一核验 |
| 隐藏域/提交值挂译文 | 同上 | `grep -rn "hidden.*__(\|value=\"{{ \$.*__(\""`，0 命中 |

## 二、data-* 命中核验（17 处，全部为展示用途，非提交值）

| 文件 | 属性 | 结论 |
| --- | --- | --- |
| `resources/admin/crmui/authentications/detail.blade.php` (29,98,106,113,121) | `data-no-reviewable-text` / `data-label` | 展示文本，JS 仅读取渲染，不提交 |
| `resources/admin/crmui/partials/module-page.blade.php` (39-43,119,164,325-327) | `data-loading-text` / `data-error-text` / `data-status-*-text` / `data-upload-text` / `data-empty-text` | 展示文本，不提交 |
| 其余命中 | `data-empty-text` 等 | 展示文本，不提交 |

结论：`data-label` 等属性由 CrmUI 表单 JS 用于错误提示定位（需求「验证提示语准确定位字段」的实现载体），
不是提交值，无静默失效风险。

## 三、结论

1. 「译文作为 option/value 提交值」的缺陷类在 `resources/` 全量 Blade 中已清零；
   `front/layui/flow` 是该类缺陷的唯一实例，已由稳定键修复 + 专项测试锁定。
2. 后端比对逻辑（`FlowController::applyWithdrawSourceFilter`）接受语言无关稳定键，
   与 2026-08-28 修复一致，未发现其他后端按译文比对的入口。
3. 排查仅覆盖静态模板层；JS 运行时把 DOM 文案反序列化为请求参数的场景
   由各页面的参数组装代码决定，未发现以 `textContent` 作为筛选参数的实现。

> 本报告为只读静态审计，无代码改动，无测试改动。
