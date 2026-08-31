# 旧前台资料页上传组件并入共享 layui-upload 闭环报告（2026-08-29）

> 依据：`docs/audits/2026-08-28-full-serial-audit-handoff.md` §五.2 记录的最后缺口——
> 「`resources/front/layui/profile/legacy-action.blade.php` 仍用自有 `data-profile-file` 上传，
> 未并入共享 `layui-upload.js`。该页有独立错误/CSRF 契约，半迁移会威胁
> `FrontLegacyProfileVerificationLifecycleClosureModuleTest`，刻意保留」。
> 本轮在完整保全旧 Session/CSRF/err-col 契约的前提下完成迁移，全部相关测试恢复绿色。

---

## 一、迁移范围

旧页面四类文件字段全部由自有实现切换到 `public/js/shared/layui-upload.js`（CrmUpload，Layui 2.13.5）：

| 字段（旧表单名，保持不变） | 页面动作 |
| --- | --- |
| `Idphoto1` / `Idphoto2` | 身份证正/反面（identity） |
| `bankimg` | 银行卡正面（bank / bank-change） |
| `headimg` | 头像（avatar） |

## 二、契约保全（迁移不变量）

1. **旧 Session + CSRF 提交链路不变**：表单仍以 `_token` + `multipart` POST 到旧路由
   （`/user/center/uploadIdCard|uploadBankCard|uploadChangeBankCard|uploadHeadImg`），
   `data-submit-url` / `data-verify-url` / `data-code-url` / `data-success-url` 契约原样保留。
2. **后端字段口径不变**：提交前按旧字段名把共享组件缓存的 File 追加进 FormData
   （`requestData.append(field, file, file.name)`），后端继续收到 `Idphoto1/Idphoto2/bankimg/headimg`。
3. **err/col 错误位映射不变**：组件拒绝非法文件（类型/体积）时，页面把拒绝原因同步映射到
   `data-error-for="<旧字段名>"` 段落（`showError(config.field, messages.unsupportedImage)`），
   与后端响应错误共用同一提示位置与 aria 关联（`wireAccessibleStatus`/`aria-describedby` 保留）。
4. **验证码倒计时 60 秒、useremail 翻译、`以 FormData 或 URL 编码提交旧 Session 路由` 等既有锁定项不变。**

## 三、实现要点

- **共享组件 deferred 模式**（`data-upload-auto="0"`）：本地类型/体积校验（`exts=jpg|jpeg|png|gif`、
  20MB 上限内的 10MB 语义由 10240KB 表达）、拖拽/键盘可达、文件名/体积展示、本地预览、移除按钮、
  进度条与状态文案全部交给 CrmUpload；页面不再维护 FileReader/clearFileSelection 自有实现。
- **缓存即事实来源**：`validateForm()` 以 `CrmUpload.file(field)` 判空做必填拦截（非法文件已被组件
  复位缓存，天然不会入库）；提交前按字段追加进 FormData。
- **初始化与多语言**：registry 内 `CrmUpload.init(document, {onError})` + `CrmLang.switchUI()`
  （状态节点 `data-translate="front.no_file_selected"` 随当前语言重译）。
- **样式**：共享壳使用 `public/css/common/crm-upload.css`（front layui 布局已加载）+ 页面既有
  `legacy-file-field`/`legacy-file-preview` 类；主题 token 契约（`var(--crm-ink/muted/line`）不受影响。

## 四、测试契约演进（红绿验证）

`FrontLegacyProfilePageClosureModuleTest::test_legacy_profile_component_keeps_blade_css_and_js_contract`
中锁定旧实现的断言按同等意图更新为锁定新实现：

| 旧断言（旧自有实现） | 新断言（共享组件等价契约） |
| --- | --- |
| `data-file-size`（Blade） | `data-crm-upload="Idphoto1/Idphoto2/bankimg/headimg"` 四块存在 |
| `$container.find('[data-file-size]')` | `CrmUpload.init(document`（组件接管展示与校验） |
| `function clearFileSelection(...)` + 两处 `$container.find(...)` | `showError(config.field, messages.unsupportedImage);`（拒绝原因映射旧错误位） |
| （无） | `CrmUpload.file(field)` + `requestData.append(field, file, file.name);`（缓存即提交事实来源） |

- 迁移后：`FrontLegacyProfilePageClosureModuleTest` 3 tests / 105 assertions 绿；
  `FrontLegacyProfileVerificationLifecycleClosureModuleTest`（后端生命周期，交接报告点名的风险面）
  与 `FrontUploadSessionCompatibilityTest` 等合并回归 **217 tests / 7128 assertions** 全绿；
- `node --check`（pages.js）通过；Blade `view:cache` 通过。
- 全量串行：见文末。

## 五、最终验证

> 本轮完成后全量串行 `storage/logs/full-serial-20260829-151746.out`（13:40.326）：
> **`OK (4305 tests, 80348 assertions)` / `PHPUNIT_EXIT=0`** —— 较上一轮 144445 的 80343 增加的断言
> 来自契约演进的新断言，全量零回归。至此 2026-08-28 交接报告 §五 所列全部可本地修复缺口清零。
