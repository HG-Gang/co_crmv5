# Front Five Pages Design

## Overview

本次设计聚焦 CRM 前台第一批 5 个高频页面升级：

1. 登录页
2. 注册页
3. 控制台首页
4. 个人资料页
5. 入金页

目标是在保持 Laravel Blade 前后端不分离架构不变的前提下，基于现有 HTML、CSS、JS、Layui 能力，做出一套更现代、更专业、更清爽的前台视觉样板。原有页面保留不动；新版页面在对应目录下新增文件，后续由控制器 `view()` 指向新版模板。

## Register

product

## Feature Summary

这 5 个页面构成前台用户进入系统后的核心使用链路，覆盖“进入系统、完成注册、理解状态、维护资料、发起资金操作”五类关键任务。新版页面不是换技术栈，而是在传统 Blade 页面中重建更成熟的中后台视觉语言和信息层级。

## Primary User Action

用户应能在更低认知负担下完成当前任务：

- 登录页：快速登录
- 注册页：顺畅完成注册和验证
- 控制台：首页一眼理解账户与常用入口
- 个人资料页：快速查看并编辑关键资料
- 入金页：安全清晰地完成入金操作并查看状态

## Design Direction

### Color Strategy

Restrained

整体采用克制的产品型配色：中性背景、清晰表面层、少量强调色用于主按钮、当前状态和重要数据，不做营销式大面积高饱和铺色。

### Theme Scene Sentence

用户坐在桌面设备前，在办公或居家环境中处理真实业务任务，希望页面稳定、专业、清晰、低干扰，能够快速定位操作入口并完成提交。

### Anchor References

1. Tabler：参考其克制、整齐、稳定的后台页面结构
2. Soybean Admin：参考其清爽细节、留白节奏、现代感
3. Vue Vben Admin：参考其中后台信息组织、卡片和列表区的分层方式

### Explicit Non-Directions

- 不走营销官网风格
- 不做 Vue SPA / React SPA / 前后端分离迁移
- 不保留老式 Layui 默认视觉质感
- 不做花哨渐变、重阴影、大圆角、卡片套卡片

## Scope

### Fidelity

production-ready

### Breadth

5 个独立页面

### Interactivity

保留真实交互与真实表单行为，继续依赖现有 Blade、JS、Layui 和后端接口，不做静态假页。

### Time Intent

先交付一套高质量前台样板页面，用于验证整体视觉与交互方向，再决定是否向更多页面扩展。

## Layout Strategy

### 1. 登录页

采用聚焦式单栏布局。核心是账号输入、密码输入、登录按钮和辅助入口。弱化背景装饰，强化品牌识别、任务聚焦和错误提示可见性。

### 2. 注册页

采用单页分组表单布局。通过字段分组、标签层级、验证区块、验证码区块和条款确认区块，让长表单也保持清晰节奏。重点避免旧式堆叠输入框造成的压迫感。

### 3. 控制台首页

采用“三段式”结构：

- 顶部欢迎与身份摘要
- 中部关键统计与快捷入口
- 底部补充信息、提醒或近期动态

目标是一眼看懂身份、账户状态和下一步能做什么。

### 4. 个人资料页

采用“摘要 + 分组编辑”的结构：

- 顶部资料摘要卡
- 下方按主题拆分的资料表单

把查看与编辑放在同一信息语境里，减少用户在长表单中的迷失感。

### 5. 入金页

采用“主操作区 + 支撑信息区 + 历史记录区”的结构：

- 主操作区突出当前入金动作
- 支撑信息区呈现汇率、支付方式、说明与状态
- 历史记录区承载检索和表格

避免表单与历史列表混成一团。

## Key States

每个页面都必须考虑以下状态：

### 通用状态

- 默认态
- 加载态
- 成功提示态
- 错误提示态
- 中英文切换后的长文本态
- 窄屏下的响应式态

### 登录页

- 登录失败
- 账号为空
- 密码为空
- 登录中

### 注册页

- 首次打开
- 邀请人已带入
- 验证码已发送
- 表单校验失败
- 提交中

### 控制台页

- 有统计数据
- 数据为空
- 某个统计模块加载失败

### 个人资料页

- 有完整资料
- 资料缺失待补充
- 上传中
- 保存成功 / 保存失败

### 入金页

- 正常可入金
- 渠道为空
- 入金功能暂不可用
- 提交中
- 历史记录为空

## Interaction Model

### Common

- 保留现有后端接口和核心 JS 行为
- 用更清晰的按钮层级、提示位置、分组布局改善体验
- 页面交互应尽量短路径完成，不增加无必要确认弹层

### Login

- 点击主按钮直接提交
- 出错信息出现在表单附近，不隐藏在页面边缘
- 次级入口如注册、忘记密码保持弱但可见

### Register

- 字段按业务关系分组
- 验证码操作区与对应字段强绑定
- 当存在邀请人时，邀请信息需要有明显提示但不喧宾夺主

### Dashboard

- 快捷入口应可直接跳转到常用页面
- 关键数字突出，但不做夸张大屏风格

### Profile

- 摘要区帮助用户先确认“我是谁、当前状态如何”
- 编辑区按逻辑分组，降低长表单疲劳

### Deposit

- 当前最重要动作是发起入金，所以主按钮、金额、支付渠道优先级最高
- 历史记录检索与当前操作在视觉上分离

## Content Requirements

### Copy and Labels

- 标题、副标题、字段标签应简洁明确
- 错误提示要直接说明问题，不使用空泛文案
- 空状态要说明“当前没有什么”以及“下一步能做什么”

### Dynamic Content

- 用户名、用户编号、账户类型
- 邀请人信息
- 统计数字
- 入金渠道和入金记录
- 资料字段和上传状态

### Realistic Data Ranges

- 登录页和注册页字段数固定
- 控制台统计卡通常 4-8 个
- 资料页表单中等长度
- 入金记录可能为空，也可能有几十到上百条分页数据

## File Strategy

新版文件放在原有目录中，保持资源邻近和责任清晰，不覆盖原文件。

建议新增以下模板：

- `resources/front/layui/auth/login_v2.blade.php`
- `resources/front/layui/auth/register_v2.blade.php`
- `resources/front/layui/dashboard/index_v2.blade.php`
- `resources/front/layui/profile/index_v2.blade.php`
- `resources/front/layui/deposit/index_v2.blade.php`

样式与脚本原则：

- 尽量复用现有 `public/css/front/style.css`
- 只在必要时新增小范围 page-level CSS 或单独的 v2 样式文件
- 继续复用现有页面 JS；只有当 DOM 结构变化导致必要适配时，才增加 v2 版本脚本

## Controller Mapping Strategy

原页面保留不变，只修改控制器 `view()` 指向新版模板。

优先考虑以下控制器：

- `App\Http\Controllers\Front\AuthController`
- `App\Http\Controllers\Front\DashboardController`
- `App\Http\Controllers\Front\LegacyPageController`

需要确保切换后仍命中原有业务接口与脚本逻辑，不引入路由层破坏性变化。

## Recommended References

实现阶段最值得使用的 impeccable 参考方向：

- `reference/layout.md`
- `reference/typeset.md`
- `reference/colorize.md`
- `reference/harden.md`
- `reference/clarify.md`

## Constraints

- 必须保持 Laravel Blade 前后端不分离
- 必须保留原页面文件
- 必须在对应目录新增新版页面
- 必须通过控制器 `view()` 切换新版模板
- 不引入 SPA 框架迁移
- 不改变核心业务接口

## Testing Expectations

实现完成后至少验证：

1. Blade 模板语法无错误
2. 控制器 `view()` 指向正确
3. 页面可正常打开
4. 主要表单提交与基础交互不报 JS 错误
5. 中英文切换与响应式布局不出现明显错位
