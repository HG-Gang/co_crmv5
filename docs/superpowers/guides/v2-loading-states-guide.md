# V2 页面交互状态使用说明

**文档创建时间：** 2026-06-14  
**适用范围：** 所有 v2 前台页面

---

## 加载状态（Loading States）

### 1. 按钮加载状态

**CSS 类：** `.is-loading`

**用途：** 表单提交时显示旋转加载图标

**使用方法：**

在提交按钮的点击事件中添加：

```javascript
// 开始加载
$submitBtn.addClass('is-loading').prop('disabled', true);

// 完成后移除（成功或失败都需要）
$submitBtn.removeClass('is-loading').prop('disabled', false);
```

**完整示例：**

```javascript
// login.js 中
form.on('submit(doLogin)', function(data) {
    var $btn = $('[lay-submit]');
    $btn.addClass('is-loading').prop('disabled', true);
    
    $.ajax({
        url: routes.login,
        type: 'POST',
        data: data.field,
        success: function(res) {
            // 处理成功
            $btn.removeClass('is-loading').prop('disabled', false);
        },
        error: function() {
            // 处理错误
            $btn.removeClass('is-loading').prop('disabled', false);
        }
    });
    
    return false;
});
```

**视觉效果：**
- 按钮文字变透明
- 显示旋转的白色圆圈图标
- 按钮变为禁用状态

---

### 2. 统计卡片骨架屏

**CSS 类：** `.is-loading` 或 `.skeleton-pulse`

**用途：** 数据加载中时显示脉动效果

**使用方法：**

**方法 A：整个卡片**
```javascript
// 开始加载
$('.front-v2-stat').addClass('is-loading');

// 数据到达后
$('.front-v2-stat').removeClass('is-loading');
$('#directAgentsCount').text(data.directAgents);
```

**方法 B：单个数据值**
```html
<!-- 初始状态 -->
<dl class="front-v2-stat is-loading">
    <dt>直属代理</dt>
    <dd id="directAgentsCount">0</dd>
</dl>
```

```javascript
// 数据到达
$('.front-v2-stat').removeClass('is-loading');
$('#directAgentsCount').text(data.directAgents);
```

**视觉效果：**
- 数据部分显示流动的灰色渐变
- 自动隐藏真实数值
- 1.5 秒循环动画

---

### 3. 空状态（Empty States）

**CSS 类：** `.front-v2-empty-state`

**用途：** 当列表、表格、容器无数据时显示友好提示

**HTML 结构：**

```html
<div class="dashboard-share-list" id="shareUrlList">
    <!-- 空状态默认显示 -->
    <div class="front-v2-empty-state" data-empty-placeholder>
        <div class="front-v2-empty-icon">📋</div>
        <p class="front-v2-empty-title">暂无分享链接</p>
        <p class="front-v2-empty-text">代理账户可以在此查看邀请注册链接</p>
    </div>
    <!-- JS 加载数据后追加到这里 -->
</div>
```

**JavaScript 处理：**

```javascript
// 有数据时
if (data.shareUrls.length > 0) {
    // 隐藏空状态
    $('#shareUrlList [data-empty-placeholder]').addClass('is-hidden');
    
    // 渲染数据
    data.shareUrls.forEach(function(url) {
        $('#shareUrlList').append('<div class="share-item">...</div>');
    });
} else {
    // 显示空状态
    $('#shareUrlList [data-empty-placeholder]').removeClass('is-hidden');
}
```

**视觉效果：**
- 垂直居中的空状态提示
- 大号 emoji 图标
- 两行文字说明

---

## 当前已实现的页面

### ✅ Dashboard (index_v2.blade.php)

**已实现：**
- 空状态：分享链接区域、新闻列表
- 骨架屏 CSS：统计卡片（需在 JS 中应用 `.is-loading` 类）

**需要在 JS 中添加：**
```javascript
// dashboard/index.js 中
function loadDashboardStats() {
    // 添加加载状态
    $('.front-v2-stat').addClass('is-loading');
    
    $.ajax({
        url: routes.dashboardStats,
        success: function(data) {
            // 移除加载状态
            $('.front-v2-stat').removeClass('is-loading');
            
            // 更新数据
            $('#monthlyDeposit').text(data.monthlyDeposit);
            // ... 其他字段
        }
    });
}
```

### ✅ Login (login_v2.blade.php)

**需要添加：**
```javascript
// login.js 中找到提交按钮处理
form.on('submit(doLogin)', function(data) {
    var $btn = $('[lay-filter="doLogin"]');
    $btn.addClass('is-loading').prop('disabled', true);
    
    // ... 现有逻辑
    
    // 记得在 success 和 error 回调中移除
});
```

### ✅ Register (register_v2.blade.php)

**需要添加：**
```javascript
// register.js 中
form.on('submit(registerSubmit)', function(data) {
    var $btn = $('[lay-filter="registerSubmit"]');
    $btn.addClass('is-loading').prop('disabled', true);
    
    // ... 现有逻辑
});
```

### ✅ Profile (profile/index_v2.blade.php)

**需要添加：**
```javascript
// 表单提交时
form.on('submit(saveProfile)', function(data) {
    var $btn = $('[lay-submit]');
    $btn.addClass('is-loading').prop('disabled', true);
    
    // ... 现有逻辑
});
```

### ✅ Deposit (deposit/index_v2.blade.php)

**需要添加：**
```javascript
// 入金提交时
form.on('submit(depositSubmit)', function(data) {
    var $btn = $('[lay-filter="depositSubmit"]');
    $btn.addClass('is-loading').prop('disabled', true);
    
    // ... 现有逻辑
});
```

---

## 实施清单

### 高优先级（立即实施）
- [ ] Login 页面：提交按钮加载状态
- [ ] Register 页面：提交按钮加载状态
- [ ] Dashboard 页面：统计卡片骨架屏
- [ ] Profile 页面：提交按钮加载状态
- [ ] Deposit 页面：提交按钮加载状态

### 中优先级（本周完成）
- [ ] Dashboard 页面：分享链接空状态逻辑
- [ ] Dashboard 页面：新闻列表空状态逻辑
- [ ] Deposit 页面：历史记录空状态（如适用）

### 低优先级（后续迭代）
- [ ] 统一错误提示样式
- [ ] 统一成功提示样式
- [ ] 添加过渡动画

---

## CSS 类速查表

| 类名 | 用途 | 应用元素 |
|------|------|----------|
| `.is-loading` | 按钮加载状态 | `<button>` |
| `.is-loading` | 统计卡片骨架屏 | `.front-v2-stat` |
| `.skeleton-pulse` | 通用骨架屏 | 任意元素 |
| `.front-v2-empty-state` | 空状态容器 | `<div>` |
| `.is-hidden` | 隐藏空状态 | `.front-v2-empty-state` |
| `[data-empty-placeholder]` | 空状态占位标识 | `.front-v2-empty-state` |

---

## 注意事项

1. **始终移除加载状态** - 无论成功或失败，都要在回调中移除 `.is-loading` 类
2. **防止重复提交** - 添加加载状态的同时设置 `disabled: true`
3. **错误处理** - 确保 error 回调中也移除加载状态
4. **空状态判断** - 优先判断数据是否为空，再决定显示/隐藏

---

**文档维护：** 开发团队  
**最后更新：** 2026-06-14
