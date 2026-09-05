# UI重写项目 - 样式规范文档

## 概述

本文档定义新版UI系统的样式规范，包括颜色系统、排版规范、组件样式和响应式设计标准。

---

## 后台UI (admin-tailwind)

### 技术栈
- **CSS框架**: Tailwind CSS 3.x
- **JavaScript框架**: Alpine.js 3.x
- **路由前缀**: `/admin-tailwind/*`

### 颜色系统

#### 主色调
```css
/* 背景色 */
--bg-primary: #0F172A (slate-900)
--bg-secondary: #1E293B (slate-800)
--bg-tertiary: #334155 (slate-700)
--bg-card: #FFFFFF (白色卡片)
--bg-hover: #F8FAFC (slate-50)

/* 强调色 */
--accent-primary: #3B82F6 (blue-500)
--accent-secondary: #8B5CF6 (violet-500)
--accent-success: #10B981 (emerald-500)
--accent-warning: #F59E0B (amber-500)
--accent-danger: #EF4444 (red-500)

/* 文本色 */
--text-primary: #0F172A (slate-900)
--text-secondary: #64748B (slate-500)
--text-muted: #94A3B8 (slate-400)
--text-white: #FFFFFF
```

#### 语义化颜色
```css
/* 状态颜色 */
--color-success: #10B981
--color-warning: #F59E0B
--color-error: #EF4444
--color-info: #3B82F6

/* 边框颜色 */
--border-light: #E2E8F0 (slate-200)
--border-default: #CBD5E1 (slate-300)
--border-dark: #94A3B8 (slate-400)
```

### 排版系统

#### 字体家族
```css
font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
```

#### 字号规范
```css
/* Tailwind 类名对应 */
.text-xs    { font-size: 0.75rem; }   /* 12px */
.text-sm    { font-size: 0.875rem; }  /* 14px */
.text-base  { font-size: 1rem; }      /* 16px */
.text-lg    { font-size: 1.125rem; }  /* 18px */
.text-xl    { font-size: 1.25rem; }   /* 20px */
.text-2xl   { font-size: 1.5rem; }    /* 24px */
.text-3xl   { font-size: 1.875rem; }  /* 30px */
.text-4xl   { font-size: 2.25rem; }   /* 36px */
```

#### 行高规范
```css
.leading-tight   { line-height: 1.25; }
.leading-normal  { line-height: 1.5; }
.leading-relaxed { line-height: 1.625; }
.leading-loose   { line-height: 2; }
```

#### 字重规范
```css
.font-normal    { font-weight: 400; }
.font-medium    { font-weight: 500; }
.font-semibold  { font-weight: 600; }
.font-bold      { font-weight: 700; }
```

### 间距系统

#### Tailwind 间距单位
```css
/* 基础单位: 0.25rem = 4px */
.p-0   { padding: 0; }
.p-1   { padding: 0.25rem; }   /* 4px */
.p-2   { padding: 0.5rem; }    /* 8px */
.p-3   { padding: 0.75rem; }   /* 12px */
.p-4   { padding: 1rem; }      /* 16px */
.p-5   { padding: 1.25rem; }   /* 20px */
.p-6   { padding: 1.5rem; }    /* 24px */
.p-8   { padding: 2rem; }      /* 32px */
.p-10  { padding: 2.5rem; }    /* 40px */
.p-12  { padding: 3rem; }      /* 48px */
```

#### 常用间距组合
```html
<!-- 页面容器 -->
<div class="container-fluid py-4">

<!-- 卡片间距 -->
<div class="card mb-4">
  <div class="card-body p-6">

<!-- 表单间距 -->
<form class="space-y-4">

<!-- 按钮组间距 -->
<div class="flex gap-3">
```

### 圆角系统

```css
.rounded-none   { border-radius: 0; }
.rounded-sm     { border-radius: 0.125rem; }  /* 2px */
.rounded        { border-radius: 0.25rem; }   /* 4px */
.rounded-md     { border-radius: 0.375rem; }  /* 6px */
.rounded-lg     { border-radius: 0.5rem; }    /* 8px */
.rounded-xl     { border-radius: 0.75rem; }   /* 12px */
.rounded-2xl    { border-radius: 1rem; }      /* 16px */
.rounded-full   { border-radius: 9999px; }
```

### 阴影系统

```css
.shadow-sm   { box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05); }
.shadow      { box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1); }
.shadow-md   { box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1); }
.shadow-lg   { box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1); }
.shadow-xl   { box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1); }
```

### 组件样式规范

#### 卡片样式
```html
<div class="bg-white rounded-xl shadow-lg p-6 mb-4">
  <h3 class="text-xl font-semibold text-slate-900 mb-4">卡片标题</h3>
  <p class="text-slate-600">卡片内容</p>
</div>
```

#### 按钮样式
```html
<!-- 主要按钮 -->
<button class="bg-blue-500 hover:bg-blue-600 text-white font-medium px-4 py-2 rounded-lg transition-colors">
  主要按钮
</button>

<!-- 次要按钮 -->
<button class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium px-4 py-2 rounded-lg transition-colors">
  次要按钮
</button>

<!-- 危险按钮 -->
<button class="bg-red-500 hover:bg-red-600 text-white font-medium px-4 py-2 rounded-lg transition-colors">
  危险操作
</button>

<!-- 轮廓按钮 -->
<button class="border-2 border-blue-500 text-blue-500 hover:bg-blue-50 font-medium px-4 py-2 rounded-lg transition-colors">
  轮廓按钮
</button>
```

#### 表单样式
```html
<!-- 输入框 -->
<input type="text" 
  class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all" 
  placeholder="请输入内容">

<!-- 下拉选择 -->
<select class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
  <option>选项1</option>
  <option>选项2</option>
</select>

<!-- 文本域 -->
<textarea 
  class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
  rows="4"></textarea>
```

#### 表格样式
```html
<table class="w-full">
  <thead class="bg-slate-50">
    <tr>
      <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">表头</th>
    </tr>
  </thead>
  <tbody>
    <tr class="border-b border-slate-200 hover:bg-slate-50 transition-colors">
      <td class="px-4 py-3 text-sm text-slate-600">表格内容</td>
    </tr>
  </tbody>
</table>
```

#### 徽章样式
```html
<span class="bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded-full">成功</span>
<span class="bg-yellow-100 text-yellow-800 text-xs font-medium px-2.5 py-0.5 rounded-full">警告</span>
<span class="bg-red-100 text-red-800 text-xs font-medium px-2.5 py-0.5 rounded-full">错误</span>
<span class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded-full">信息</span>
```

---

## 前台UI (front-coreui-v2)

### 技术栈
- **CSS框架**: Bootstrap 5.3.0 + CoreUI 5.0.0
- **JavaScript库**: 原生JavaScript / jQuery (可选)
- **路由前缀**: `/front-coreui-v2/*`

### 颜色系统

#### CoreUI 主色调
```css
/* 主题色 */
--cui-primary: #321fdb
--cui-secondary: #9da5b1
--cui-success: #2eb85c
--cui-info: #39f
--cui-warning: #f9b115
--cui-danger: #e55353

/* 背景色 */
--cui-body-bg: #ebedef
--cui-card-bg: #ffffff
--cui-hover-bg: #f8f9fa

/* 文本色 */
--cui-body-color: #212529
--cui-secondary-color: #6c757d
--cui-muted-color: #8a93a2
```

#### 渐变色
```css
/* 主渐变 */
background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);

/* 副渐变 */
background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
```

### 排版系统

#### 字体家族
```css
font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
```

#### Bootstrap 字号类
```css
.fs-1 { font-size: calc(1.375rem + 1.5vw); }  /* 响应式 */
.fs-2 { font-size: calc(1.325rem + 0.9vw); }
.fs-3 { font-size: calc(1.3rem + 0.6vw); }
.fs-4 { font-size: calc(1.275rem + 0.3vw); }
.fs-5 { font-size: 1.25rem; }
.fs-6 { font-size: 1rem; }

/* 固定字号 */
.small { font-size: 0.875em; }
.text-sm { font-size: 0.875rem; }
```

#### 字重类
```css
.fw-light     { font-weight: 300; }
.fw-normal    { font-weight: 400; }
.fw-medium    { font-weight: 500; }
.fw-semibold  { font-weight: 600; }
.fw-bold      { font-weight: 700; }
```

### 间距系统

#### Bootstrap 间距单位
```css
/* 基础单位: 0.25rem = 4px */
.m-0  { margin: 0; }
.m-1  { margin: 0.25rem; }   /* 4px */
.m-2  { margin: 0.5rem; }    /* 8px */
.m-3  { margin: 1rem; }      /* 16px */
.m-4  { margin: 1.5rem; }    /* 24px */
.m-5  { margin: 3rem; }      /* 48px */

.p-0  { padding: 0; }
.p-1  { padding: 0.25rem; }  /* 4px */
.p-2  { padding: 0.5rem; }   /* 8px */
.p-3  { padding: 1rem; }     /* 16px */
.p-4  { padding: 1.5rem; }   /* 24px */
.p-5  { padding: 3rem; }     /* 48px */

/* 方向性间距 */
.mt-3 { margin-top: 1rem; }
.mb-4 { margin-bottom: 1.5rem; }
.px-3 { padding-left: 1rem; padding-right: 1rem; }
.py-4 { padding-top: 1.5rem; padding-bottom: 1.5rem; }
```

#### 间距组合推荐
```html
<!-- 页面容器 -->
<div class="container-fluid p-4">

<!-- 卡片间距 -->
<div class="card mb-4">
  <div class="card-body p-4">

<!-- 表单间距 -->
<form class="row g-3">

<!-- 按钮组间距 -->
<div class="d-flex gap-2">
```

### 圆角系统

```css
.rounded-0      { border-radius: 0; }
.rounded-1      { border-radius: 0.25rem; }   /* 4px */
.rounded-2      { border-radius: 0.375rem; }  /* 6px */
.rounded-3      { border-radius: 0.5rem; }    /* 8px */
.rounded-4      { border-radius: 1rem; }      /* 16px */
.rounded-5      { border-radius: 2rem; }      /* 32px */
.rounded-pill   { border-radius: 50rem; }
.rounded-circle { border-radius: 50%; }
```

### 阴影系统

```css
.shadow-none { box-shadow: none; }
.shadow-sm   { box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); }
.shadow      { box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15); }
.shadow-lg   { box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.175); }
```

### 组件样式规范

#### 卡片样式
```html
<div class="card shadow-sm rounded-4 mb-4">
  <div class="card-header bg-transparent border-0">
    <h5 class="card-title mb-0">卡片标题</h5>
  </div>
  <div class="card-body">
    <p class="card-text text-body-secondary">卡片内容</p>
  </div>
</div>

<!-- 带渐变的卡片 -->
<div class="card text-white mb-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
  <div class="card-body">
    <h5 class="card-title">渐变卡片</h5>
  </div>
</div>
```

#### 按钮样式
```html
<!-- 主要按钮 -->
<button class="btn btn-primary rounded-3 px-4">主要按钮</button>

<!-- 次要按钮 -->
<button class="btn btn-secondary rounded-3 px-4">次要按钮</button>

<!-- 成功按钮 -->
<button class="btn btn-success rounded-3 px-4">成功</button>

<!-- 危险按钮 -->
<button class="btn btn-danger rounded-3 px-4">危险</button>

<!-- 轮廓按钮 -->
<button class="btn btn-outline-primary rounded-3 px-4">轮廓按钮</button>

<!-- 渐变按钮 -->
<button class="btn text-white rounded-3 px-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
  渐变按钮
</button>
```

#### 表单样式
```html
<!-- 输入框 -->
<div class="mb-3">
  <label class="form-label">标签</label>
  <input type="text" class="form-control rounded-3" placeholder="请输入内容">
</div>

<!-- 下拉选择 -->
<div class="mb-3">
  <label class="form-label">选择</label>
  <select class="form-select rounded-3">
    <option>选项1</option>
    <option>选项2</option>
  </select>
</div>

<!-- 文本域 -->
<div class="mb-3">
  <label class="form-label">描述</label>
  <textarea class="form-control rounded-3" rows="3"></textarea>
</div>

<!-- 复选框 -->
<div class="form-check">
  <input class="form-check-input" type="checkbox" id="check1">
  <label class="form-check-label" for="check1">选项</label>
</div>

<!-- 单选框 -->
<div class="form-check">
  <input class="form-check-input" type="radio" name="radio1" id="radio1">
  <label class="form-check-label" for="radio1">选项1</label>
</div>
```

#### 表格样式
```html
<table class="table table-striped table-hover">
  <thead class="table-light">
    <tr>
      <th scope="col">表头1</th>
      <th scope="col">表头2</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>内容1</td>
      <td>内容2</td>
    </tr>
  </tbody>
</table>

<!-- 无边框表格 -->
<table class="table table-borderless">
  ...
</table>

<!-- 紧凑表格 -->
<table class="table table-sm">
  ...
</table>
```

#### 徽章样式
```html
<span class="badge bg-success rounded-pill">成功</span>
<span class="badge bg-warning text-dark rounded-pill">警告</span>
<span class="badge bg-danger rounded-pill">错误</span>
<span class="badge bg-info rounded-pill">信息</span>
<span class="badge bg-primary rounded-pill">主要</span>
```

#### 提示框样式
```html
<div class="alert alert-success d-flex align-items-center" role="alert">
  <i class="cil-check-circle me-2"></i>
  <div>操作成功！</div>
</div>

<div class="alert alert-warning d-flex align-items-center" role="alert">
  <i class="cil-warning me-2"></i>
  <div>请注意！</div>
</div>

<div class="alert alert-danger d-flex align-items-center" role="alert">
  <i class="cil-x-circle me-2"></i>
  <div>操作失败！</div>
</div>
```

---

## 响应式设计

### 断点系统

#### Tailwind 断点
```css
/* min-width */
sm: 640px
md: 768px
lg: 1024px
xl: 1280px
2xl: 1536px

/* 使用示例 */
<div class="w-full md:w-1/2 lg:w-1/3">
```

#### Bootstrap 断点
```css
/* min-width */
sm: 576px
md: 768px
lg: 992px
xl: 1200px
xxl: 1400px

/* 使用示例 */
<div class="col-12 col-md-6 col-lg-4">
```

### 响应式布局示例

#### 后台 Tailwind 布局
```html
<!-- 网格布局 -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
  <div class="bg-white rounded-lg p-4">卡片1</div>
  <div class="bg-white rounded-lg p-4">卡片2</div>
  <div class="bg-white rounded-lg p-4">卡片3</div>
</div>

<!-- Flex布局 -->
<div class="flex flex-col md:flex-row gap-4">
  <div class="flex-1">左侧内容</div>
  <div class="flex-1">右侧内容</div>
</div>

<!-- 隐藏/显示 -->
<div class="block md:hidden">移动端显示</div>
<div class="hidden md:block">桌面端显示</div>
```

#### 前台 Bootstrap 布局
```html
<!-- 网格布局 -->
<div class="row g-4">
  <div class="col-12 col-md-6 col-lg-4">
    <div class="card">卡片1</div>
  </div>
  <div class="col-12 col-md-6 col-lg-4">
    <div class="card">卡片2</div>
  </div>
  <div class="col-12 col-md-6 col-lg-4">
    <div class="card">卡片3</div>
  </div>
</div>

<!-- Flex布局 -->
<div class="d-flex flex-column flex-md-row gap-3">
  <div class="flex-fill">左侧内容</div>
  <div class="flex-fill">右侧内容</div>
</div>

<!-- 隐藏/显示 -->
<div class="d-block d-md-none">移动端显示</div>
<div class="d-none d-md-block">桌面端显示</div>
```

---

## 动画与过渡

### Tailwind 过渡
```css
.transition          { transition-property: all; transition-duration: 150ms; }
.transition-colors   { transition-property: color, background-color, border-color; }
.transition-opacity  { transition-property: opacity; }
.transition-transform{ transition-property: transform; }

.duration-150 { transition-duration: 150ms; }
.duration-300 { transition-duration: 300ms; }
.duration-500 { transition-duration: 500ms; }

.ease-linear { transition-timing-function: linear; }
.ease-in     { transition-timing-function: cubic-bezier(0.4, 0, 1, 1); }
.ease-out    { transition-timing-function: cubic-bezier(0, 0, 0.2, 1); }
.ease-in-out { transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1); }
```

### Bootstrap 过渡
```css
/* 淡入淡出 */
.fade {
  transition: opacity 0.15s linear;
}

/* 折叠 */
.collapse {
  transition: height 0.35s ease;
}
```

### 常用动画示例
```html
<!-- 悬停放大 -->
<button class="transform hover:scale-105 transition-transform duration-200">
  悬停放大
</button>

<!-- 悬停变色 -->
<div class="bg-blue-500 hover:bg-blue-600 transition-colors duration-300">
  悬停变色
</div>

<!-- 淡入效果 -->
<div class="opacity-0 hover:opacity-100 transition-opacity duration-500">
  淡入内容
</div>
```

---

## 图标系统

### CoreUI Icons

#### 常用图标
```html
<!-- 用户相关 -->
<i class="cil-user"></i>
<i class="cil-user-follow"></i>
<i class="cil-user-unfollow"></i>

<!-- 操作相关 -->
<i class="cil-pencil"></i>
<i class="cil-trash"></i>
<i class="cil-plus"></i>
<i class="cil-check"></i>
<i class="cil-x"></i>

<!-- 状态相关 -->
<i class="cil-check-circle"></i>
<i class="cil-x-circle"></i>
<i class="cil-warning"></i>
<i class="cil-info"></i>

<!-- 导航相关 -->
<i class="cil-chevron-left"></i>
<i class="cil-chevron-right"></i>
<i class="cil-chevron-top"></i>
<i class="cil-chevron-bottom"></i>
<i class="cil-home"></i>
<i class="cil-settings"></i>
```

#### 图标尺寸
```html
<i class="cil-user" style="font-size: 1rem;"></i>     <!-- 16px -->
<i class="cil-user" style="font-size: 1.5rem;"></i>   <!-- 24px -->
<i class="cil-user" style="font-size: 2rem;"></i>     <!-- 32px -->
<i class="cil-user" style="font-size: 3rem;"></i>     <!-- 48px -->
```

---

## 可访问性规范

### 语义化HTML
```html
<!-- 使用语义化标签 -->
<header>头部</header>
<nav>导航</nav>
<main>主要内容</main>
<article>文章</article>
<aside>侧边栏</aside>
<footer>页脚</footer>
```

### ARIA属性
```html
<!-- 按钮 -->
<button aria-label="关闭">×</button>

<!-- 输入框 -->
<input type="text" aria-required="true" aria-describedby="help-text">
<span id="help-text">帮助文本</span>

<!-- 模态框 -->
<div role="dialog" aria-labelledby="modal-title" aria-modal="true">
  <h2 id="modal-title">模态框标题</h2>
</div>

<!-- 表格 -->
<table role="table">
  <thead role="rowgroup">
    <tr role="row">
      <th role="columnheader" scope="col">表头</th>
    </tr>
  </thead>
</table>
```

### 键盘导航
- 确保所有交互元素可通过Tab键访问
- 使用`:focus`样式突出显示焦点
- 模态框支持Esc键关闭
- 下拉菜单支持方向键导航

---

## 最佳实践

### 1. 一致性原则
- 在同一项目中保持统一的间距、圆角、阴影标准
- 相同功能的组件使用相同样式
- 保持颜色使用的一致性

### 2. 性能优化
- 避免过度使用阴影和渐变
- 合理使用动画，避免影响性能
- 使用CSS类复用，减少样式重复

### 3. 可维护性
- 使用语义化的类名
- 将自定义样式提取为CSS类
- 避免内联样式，除非必要

### 4. 响应式优先
- 移动优先设计
- 测试多种屏幕尺寸
- 确保触摸友好的交互元素

### 5. 可访问性
- 保持足够的颜色对比度
- 提供替代文本
- 支持键盘导航
- 使用ARIA属性增强可访问性
