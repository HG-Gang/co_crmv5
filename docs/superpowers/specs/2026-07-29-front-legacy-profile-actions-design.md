# 旧前台资料操作专用页面设计

## 设计依据

- 项目1的身份证、银行卡、银行卡换绑、头像、联系方式和密码入口分别渲染独立 Blade，并通过旧 Web Session 路由提交。
- 项目2当前把这些 GET 入口全部指向 `LegacyPageController::profile()`，因此浏览器得到整张个人中心，旧字段、旧提交地址和验证码阶段丢失。
- `FrontLegacyProfilePageClosureModuleTest` 与 `FrontLegacyRouteCompatibilityTest` 已把专用页面、专用 Controller 动作、Lucide 图标和中文注释作为闭环契约。
- 用户已批准按项目1路由、Controller 和 Blade 逐项迁移，本设计不改变已批准的业务范围。

## 方案选择

采用“专用 Controller 动作 + 共享参数化 Blade + 共享页面脚本”方案。

- 不复制六份结构相近的 Blade，避免字段提示、响应码翻译和上传状态在后续维护时分叉。
- 不继续让通用 `profile()` 根据 URI 隐式猜测页面，避免路由执行链无法从 action 名直接判断职责。
- 不把旧页面改成 JWT API 页面；所有请求继续携带 Web Session 和 CSRF，保持旧弹层调用方式。

## 路由与组件

| 旧入口 | Controller 动作 | 页面 action | 提交地址 |
| --- | --- | --- | --- |
| `GET user/center/uploadIdCard` | `profileIdentity` | `identity` | `POST user/center/uploadIdCard` |
| `GET user/center/uploadBank` | `profileBank` | `bank` | `POST user/center/uploadBankCard` |
| `GET user/center/uploadChangeBank/{type}` | `profileBankChange` | `bank-change` | `POST user/center/uploadChangeBankCard` |
| `GET user/center/uploadHead_browse` | `profileAvatar` | `avatar` | `POST user/center/uploadHeadImg` |
| `GET user/center/updPhoneEmail/{type}` | `profileContact` | `contact-phone` 或 `contact-email` | `POST user/center/updatePhoneEmailInfo` |
| `GET user/editpsw` | `profilePassword` | `password` | `POST user/editpsw_save` |

`resources/front/layui/profile/legacy-action.blade.php` 只根据已校验的 action 输出对应表单。`public/css/front/profile-legacy-action.css` 负责稳定的单任务布局和响应式字段排列；`public/js/apps/front/layui/pages.js` 的 `legacy/profile/action` 注册项负责本页会话请求、文件预览、验证码和旧响应处理。

## 数据流

1. `legacy.front.auth` 验证普通用户旧 Session。
2. 专用 Controller 动作校验 `{type}`，读取当前用户显示信息，并向共享 Blade 传入 action、提交 URL、验证码 URL。
3. Blade 输出旧字段名和 CSRF，不输出可修改的目标 user_id。
4. 页面脚本只校验当前 action 的字段；上传类请求使用 `FormData`，其它请求使用普通表单序列化。
5. 后端现有 `ProfileController` 继续负责用户归属、密码、验证码、唯一性、事务和文件原子写入。
6. `msg=SUC` 或 `msg=SUCCESS` 表示旧协议成功；`msg=FAIL` 时按 `err/col` 定位错误，未知错误保留服务端原始信息。

## 交互与视觉

- 页面是任务型工具，不使用营销 Hero、装饰性动效、渐变文字或卡片套卡片。
- 使用现有 `--crm-*` tokens，表单容器最大圆角 8px，无宽模糊阴影。
- 所有命令按钮使用 Lucide 图标；不使用字体图标、字符图标或表情符号。
- 文件选择后展示文件名、大小和本地预览；请求期间禁用提交按钮，完成后恢复。
- 控件具备 hover、focus、disabled 和错误状态；窄屏下字段与按钮纵向排列，不溢出容器。
- 动效只用于 160 至 200 毫秒的状态变化，并遵循 `prefers-reduced-motion`。

## 错误边界

- 联系方式 `{type}` 只接受 `phone`、`email`；银行卡换绑 type 只允许非空安全标识，非法值返回 404。
- 文件缺失、格式或大小错误在前端提示，同时以后端校验为最终边界。
- 验证码校验与发送保持两阶段调用，发送失败立即恢复按钮，不产生假成功。
- 会话失效沿用 `legacy.front.auth` 重定向或 JSON 登录协议。
- 密码修改成功跳转旧退出入口，确保新旧认证状态同时清理。

## 验证范围

- 先保持现有两个失败测试为 RED 证据。
- 实现后运行资料页面、资料生命周期、所有 Profile owner boundary、上传 Session、注销生命周期、认证边界、路由兼容和 Lucide 策略测试。
- 运行 `node --check public/js/apps/front/layui/pages.js`、`php artisan view:cache` 和目标页面 HTTP 烟测。
