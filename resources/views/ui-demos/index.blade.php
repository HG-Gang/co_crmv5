<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UI 模板演示导航 - 6 套方案对比</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #0A0D12 0%, #161D2B 100%);
            min-height: 100vh;
            padding: 48px 24px;
        }
        .container {
            max-width: 1600px;
            margin: 0 auto;
        }
        h1 {
            color: #38BDF8;
            font-size: clamp(2rem, 4vw, 3rem);
            margin-bottom: 12px;
            text-align: center;
            letter-spacing: -0.02em;
        }
        .subtitle {
            color: #94A3B8;
            text-align: center;
            font-size: 1.125rem;
            margin-bottom: 48px;
        }
        .table-wrapper {
            background: #1E2636;
            border-radius: 16px;
            padding: 0;
            border: 1px solid #2B3742;
            overflow: hidden;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        thead {
            background: #262F3E;
        }
        th {
            color: #38BDF8;
            font-weight: 600;
            text-align: left;
            padding: 20px 24px;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #2B3742;
        }
        th:first-child {
            width: 200px;
        }
        td {
            padding: 24px;
            color: #F8FAFC;
            border-bottom: 1px solid #2B3742;
            vertical-align: top;
        }
        tbody tr:last-child td {
            border-bottom: none;
        }
        tbody tr {
            transition: background 0.2s;
        }
        tbody tr:hover {
            background: rgba(56, 189, 248, 0.05);
        }
        .template-name {
            font-size: 1.25rem;
            font-weight: 600;
            color: #F8FAFC;
            margin-bottom: 8px;
        }
        .badge {
            display: inline-block;
            background: #38BDF8;
            color: #0A0D12;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-left: 8px;
        }
        .badge.hot { background: #EF4444; color: #fff; }
        .badge.pro { background: #6EE7B7; }
        .badge.popular { background: #E9A568; }
        .desc {
            color: #94A3B8;
            font-size: 0.875rem;
            line-height: 1.5;
        }
        .tech-stack {
            color: #60D5FF;
            font-weight: 500;
        }
        .stars {
            color: #F59E0B;
            font-weight: 500;
        }
        .difficulty {
            font-size: 1.125rem;
        }
        .trend {
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
        }
        .trend.up { color: #6EE7B7; }
        .trend.stable { color: #60D5FF; }
        .trend.down { color: #94A3B8; }
        .links {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .link-btn {
            display: block;
            text-align: center;
            padding: 10px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.875rem;
            transition: all 0.2s;
        }
        .link-btn.primary {
            background: #38BDF8;
            color: #0A0D12;
        }
        .link-btn.primary:hover {
            background: #60D5FF;
            transform: translateY(-1px);
        }
        .link-btn.secondary {
            background: #2B3742;
            color: #F8FAFC;
            border: 1px solid #3B4A5C;
        }
        .link-btn.secondary:hover {
            background: #3B4A5C;
            border-color: #4B5A6C;
        }
        @media (max-width: 1200px) {
            .table-wrapper {
                overflow-x: auto;
            }
            table {
                min-width: 1000px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎨 UI 模板演示导航</h1>
        <p class="subtitle">6 套现代化管理后台方案 - 完整核心功能展示对比</p>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>模板名称</th>
                        <th>技术栈</th>
                        <th>流行度</th>
                        <th>难度</th>
                        <th>2026趋势</th>
                        <th>特点说明</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <div class="template-name">
                                CoreUI
                                <span class="badge hot">首选</span>
                            </div>
                        </td>
                        <td><span class="tech-stack">Bootstrap 5</span></td>
                        <td><span class="stars">⭐⭐⭐⭐⭐ (12K+)</span></td>
                        <td><span class="difficulty">⭐⭐⭐</span></td>
                        <td>
                            <div class="trend up">
                                <span>↑</span>
                                <span>企业首选</span>
                            </div>
                        </td>
                        <td>
                            <div class="desc">
                                扁平化现代设计，数据表格强大，最适合金融 CRM 系统。成熟稳定，组件丰富，易于维护。
                            </div>
                        </td>
                        <td>
                            <div class="links">
                                <a href="/ui-demos/coreui/dashboard" class="link-btn primary">查看演示</a>
                                <a href="https://coreui.io/demos/bootstrap/5.0/free/" target="_blank" class="link-btn secondary">官方 Demo</a>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="template-name">
                                Notus
                                <span class="badge pro">Tailwind</span>
                            </div>
                        </td>
                        <td><span class="tech-stack">Tailwind CSS</span></td>
                        <td><span class="stars">⭐⭐⭐⭐⭐ (极高)</span></td>
                        <td><span class="difficulty">⭐⭐</span></td>
                        <td>
                            <div class="trend up">
                                <span>↑↑</span>
                                <span>未来趋势</span>
                            </div>
                        </td>
                        <td>
                            <div class="desc">
                                Tailwind 是 2026 年最热门框架，实用主义设计，开发效率高，定制灵活。需要重写现有 CSS。
                            </div>
                        </td>
                        <td>
                            <div class="links">
                                <a href="/ui-demos/notus/dashboard" class="link-btn primary">查看演示</a>
                                <a href="https://demos.creative-tim.com/notus-js/" target="_blank" class="link-btn secondary">官方 Demo</a>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="template-name">
                                Vuexy
                                <span class="badge popular">畅销</span>
                            </div>
                        </td>
                        <td><span class="tech-stack">Bootstrap 5</span></td>
                        <td><span class="stars">⭐⭐⭐⭐ (商业)</span></td>
                        <td><span class="difficulty">⭐⭐⭐</span></td>
                        <td>
                            <div class="trend up">
                                <span>↑</span>
                                <span>高端项目</span>
                            </div>
                        </td>
                        <td>
                            <div class="desc">
                                极简现代，紫色主题，细节精致。市面最畅销商业模板，设计一致性好，用户体验出色。
                            </div>
                        </td>
                        <td>
                            <div class="links">
                                <a href="/ui-demos/vuexy/dashboard" class="link-btn primary">查看演示</a>
                                <a href="https://pixinvent.com/demo/vuexy-html-bootstrap-admin-template/" target="_blank" class="link-btn secondary">官方 Demo</a>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="template-name">
                                Argon Dashboard
                                <span class="badge">经典</span>
                            </div>
                        </td>
                        <td><span class="tech-stack">Bootstrap 5</span></td>
                        <td><span class="stars">⭐⭐⭐ (13K+)</span></td>
                        <td><span class="difficulty">⭐⭐⭐</span></td>
                        <td>
                            <div class="trend stable">
                                <span>→</span>
                                <span>稳定</span>
                            </div>
                        </td>
                        <td>
                            <div class="desc">
                                渐变色 + 毛玻璃效果，视觉炫酷。适合需要视觉冲击力的项目，但渐变风格在 2026 年略显过时。
                            </div>
                        </td>
                        <td>
                            <div class="links">
                                <a href="/ui-demos/argon/dashboard" class="link-btn primary">查看演示</a>
                                <a href="https://demos.creative-tim.com/argon-dashboard/" target="_blank" class="link-btn secondary">官方 Demo</a>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="template-name">
                                Volt Dashboard
                                <span class="badge">快速</span>
                            </div>
                        </td>
                        <td><span class="tech-stack">Bootstrap 5</span></td>
                        <td><span class="stars">⭐⭐⭐ (中等)</span></td>
                        <td><span class="difficulty">⭐⭐</span></td>
                        <td>
                            <div class="trend stable">
                                <span>→</span>
                                <span>小众</span>
                            </div>
                        </td>
                        <td>
                            <div class="desc">
                                紫蓝配色，卡片式布局，迁移难度最低。MIT 许可，适合快速上线项目。
                            </div>
                        </td>
                        <td>
                            <div class="links">
                                <a href="/ui-demos/volt/dashboard" class="link-btn primary">查看演示</a>
                                <a href="https://demo.themesberg.com/volt/" target="_blank" class="link-btn secondary">官方 Demo</a>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="template-name">
                                Ample Admin
                            </div>
                        </td>
                        <td><span class="tech-stack">Bootstrap 5</span></td>
                        <td><span class="stars">⭐⭐ (较低)</span></td>
                        <td><span class="difficulty">⭐⭐</span></td>
                        <td>
                            <div class="trend down">
                                <span>↓</span>
                                <span>传统</span>
                            </div>
                        </td>
                        <td>
                            <div class="desc">
                                清爽简洁，企业风格。适合传统后台系统，但设计相对保守，流行度较低。
                            </div>
                        </td>
                        <td>
                            <div class="links">
                                <a href="/ui-demos/ample/dashboard" class="link-btn primary">查看演示</a>
                                <a href="https://www.wrappixel.com/demos/admin-templates/ample-admin-lite/" target="_blank" class="link-btn secondary">官方 Demo</a>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
