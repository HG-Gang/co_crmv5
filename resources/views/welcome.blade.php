{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/01
Time: 22:32
--}}
<!DOCTYPE html>
{{--
 * CO CRM 品牌门户页（替换 Laravel 默认脚手架页）。
 *
 * 文件功能：
 * - 作为访问根路径时的品牌兜底页，展示前台、代理商、后台三大入口。
 * - 使用 crmui 设计 token 与本地 Lucide 图标，保持全站视觉一致。
 *
 * 适用场景：
 * - 直接访问站点根域名（正常运行时 / 会重定向到前台登录，本页仅作品牌兜底）。
 *
 * 说明：
 * - 全程无表情符号，图标统一来自本地 lucide vendor。
 * - 深色/浅色跟随系统 prefers-color-scheme，并支持主题按钮手动切换。
 * - 所有入口链接均使用命名路由，路由缺失时自动隐藏对应入口。
--}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('crmui.common.admin_console') }} — CO CRM</title>

    {{-- 本地 Lucide 资源与动态图标桥接器，避免退回字体图标。 --}}
    @include('partials.lucide-assets')

    <style>
        :root {
            --crm-bg: oklch(97% 0.006 250);
            --crm-surface: oklch(100% 0 0 / 0.72);
            --crm-surface-2: oklch(94% 0.01 248);
            --crm-ink: oklch(22% 0.035 255);
            --crm-muted: oklch(43% 0.03 255);
            --crm-subtle: oklch(58% 0.025 255);
            --crm-border: oklch(88% 0.014 250);
            --crm-primary: oklch(51% 0.14 248);
            --crm-primary-ink: oklch(99% 0.004 250);
            --crm-accent: oklch(61% 0.15 165);
            --crm-font: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", "Microsoft YaHei", sans-serif;
            --crm-shadow: 0 24px 60px oklch(22% 0.035 255 / 0.1);
            --crm-glow: radial-gradient(1200px 600px at 20% -10%, oklch(51% 0.14 248 / 0.16), transparent 60%),
                         radial-gradient(900px 500px at 90% 110%, oklch(61% 0.15 165 / 0.14), transparent 55%);
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --crm-bg: oklch(20% 0.02 255);
                --crm-surface: oklch(25% 0.025 255 / 0.72);
                --crm-surface-2: oklch(30% 0.026 255);
                --crm-ink: oklch(94% 0.008 255);
                --crm-muted: oklch(77% 0.018 255);
                --crm-subtle: oklch(66% 0.02 255);
                --crm-border: oklch(38% 0.025 255);
                --crm-shadow: 0 24px 60px oklch(0% 0 0 / 0.4);
                --crm-glow: radial-gradient(1200px 600px at 20% -10%, oklch(51% 0.14 248 / 0.22), transparent 60%),
                             radial-gradient(900px 500px at 90% 110%, oklch(61% 0.15 165 / 0.18), transparent 55%);
            }
        }

        * { box-sizing: border-box; }
        html { min-width: 320px; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 48px 24px;
            color: var(--crm-ink);
            background: var(--crm-bg);
            font-family: var(--crm-font);
            line-height: 1.5;
            background-image: var(--crm-glow);
            background-attachment: fixed;
        }
        a { color: inherit; text-decoration: none; }
        button { font: inherit; cursor: pointer; }

        .crm-portal { width: 100%; max-width: 1040px; }
        .crm-theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            border-radius: 999px;
            border: 1px solid var(--crm-border);
            background: var(--crm-surface);
            color: var(--crm-muted);
            backdrop-filter: blur(10px);
            transition: color 0.2s ease, transform 0.2s ease, border-color 0.2s ease;
        }
        .crm-theme-toggle:hover { color: var(--crm-ink); transform: translateY(-2px); border-color: var(--crm-primary); }

        .crm-hero { text-align: center; margin-bottom: 56px; }
        .crm-brand-mark {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 64px;
            height: 64px;
            border-radius: 20px;
            font-size: 26px;
            font-weight: 800;
            letter-spacing: 0.06em;
            color: var(--crm-primary-ink);
            background: linear-gradient(135deg, var(--crm-primary), oklch(45% 0.16 260));
            box-shadow: var(--crm-shadow);
            margin-bottom: 28px;
        }
        .crm-hero h1 {
            margin: 0 0 16px;
            font-size: clamp(2.2rem, 6vw, 4.4rem);
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1.08;
            background: linear-gradient(120deg, var(--crm-ink), var(--crm-primary) 55%, var(--crm-accent));
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .crm-hero p {
            margin: 0 auto;
            max-width: 560px;
            color: var(--crm-muted);
            font-size: clamp(1rem, 2vw, 1.15rem);
        }

        .crm-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 20px;
        }
        .crm-card {
            position: relative;
            display: flex;
            flex-direction: column;
            gap: 14px;
            padding: 28px;
            border-radius: 20px;
            border: 1px solid var(--crm-border);
            background: var(--crm-surface);
            backdrop-filter: blur(12px);
            box-shadow: var(--crm-shadow);
            transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1), border-color 0.25s ease, box-shadow 0.25s ease;
            overflow: hidden;
        }
        .crm-card::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, oklch(51% 0.14 248 / 0.08), transparent 45%);
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
        }
        .crm-card:hover {
            transform: translateY(-6px);
            border-color: var(--crm-primary);
            box-shadow: 0 32px 70px oklch(22% 0.035 255 / 0.16);
        }
        .crm-card:hover::after { opacity: 1; }
        .crm-card-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            border-radius: 14px;
            color: var(--crm-primary-ink);
            background: linear-gradient(135deg, var(--crm-primary), oklch(45% 0.16 260));
        }
        .crm-card-icon svg { width: 24px; height: 24px; }
        .crm-card h2 { margin: 0; font-size: 1.25rem; font-weight: 700; }
        .crm-card p { margin: 0; color: var(--crm-muted); font-size: 0.9rem; flex: 1; }
        .crm-card-actions { display: flex; flex-wrap: wrap; gap: 10px; }
        .crm-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 999px;
            border: 1px solid var(--crm-border);
            background: var(--crm-surface-2);
            color: var(--crm-ink);
            font-size: 0.875rem;
            font-weight: 600;
            transition: transform 0.18s ease, border-color 0.18s ease, background 0.18s ease;
        }
        .crm-btn:hover { transform: translateY(-2px); border-color: var(--crm-primary); }
        .crm-btn svg { width: 16px; height: 16px; }
        .crm-btn.is-primary {
            color: var(--crm-primary-ink);
            background: linear-gradient(135deg, var(--crm-primary), oklch(45% 0.16 260));
            border-color: transparent;
        }

        .crm-footer { margin-top: 48px; text-align: center; color: var(--crm-subtle); font-size: 0.8rem; }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body>
    @php
        // 入口路由可用性探测：命名路由不存在时隐藏对应按钮，避免出现死链。
        $frontLogin = Route::has('front_page_login') ? route('front_page_login') : null;
        $frontRegister = Route::has('front_page_register') ? route('front_page_register') : null;
        $bigAgentLogin = Route::has('front_crmui_big_agent_login') ? route('front_crmui_big_agent_login') : null;
        $adminLogin = Route::has('admin_page_login') ? route('admin_page_login') : null;
    @endphp

    <button class="crm-theme-toggle" type="button" data-crm-theme-toggle aria-label="切换主题">
        <i data-lucide="palette"></i>
    </button>

    <main class="crm-portal">
        <section class="crm-hero">
            <div class="crm-brand-mark">CO</div>
            <h1>CO CRM</h1>
            <p>面向外汇交易业务的统一客户关系管理平台：普通用户、代理商与后台管理员三端一体化。</p>
        </section>

        <section class="crm-grid" aria-label="系统入口">
            @if ($frontLogin || $frontRegister)
                <article class="crm-card">
                    <div class="crm-card-icon"><i data-lucide="user-round"></i></div>
                    <h2>{{ __('crmui.common.front_console') }}</h2>
                    <p>入金、出金、账户流水、仓位总结与个人资料管理。</p>
                    <div class="crm-card-actions">
                        @if ($frontLogin)
                            <a class="crm-btn is-primary" href="{{ $frontLogin }}"><i data-lucide="log-in"></i> {{ __('auth.login') }}</a>
                        @endif
                        @if ($frontRegister)
                            <a class="crm-btn" href="{{ $frontRegister }}"><i data-lucide="user-plus"></i> {{ __('auth.register') }}</a>
                        @endif
                    </div>
                </article>
            @endif

            @if ($bigAgentLogin)
                <article class="crm-card">
                    <div class="crm-card-icon"><i data-lucide="network"></i></div>
                    <h2>{{ __('crmui.common.big_agent_console') }}</h2>
                    <p>下级代理、直属客户、仓位汇总与佣金结算管理。</p>
                    <div class="crm-card-actions">
                        <a class="crm-btn is-primary" href="{{ $bigAgentLogin }}"><i data-lucide="log-in"></i> {{ __('auth.login') }}</a>
                    </div>
                </article>
            @endif

            @if ($adminLogin)
                <article class="crm-card">
                    <div class="crm-card-icon"><i data-lucide="shield-check"></i></div>
                    <h2>{{ __('crmui.common.admin_console') }}</h2>
                    <p>客户、代理、财务、风控、资讯与系统权限管理。</p>
                    <div class="crm-card-actions">
                        <a class="crm-btn is-primary" href="{{ $adminLogin }}"><i data-lucide="log-in"></i> {{ __('auth.login') }}</a>
                    </div>
                </article>
            @endif
        </section>

        <footer class="crm-footer">
            CO CRM · {{ __('crmui.common.blade_ui') }} · {{ Illuminate\Foundation\Application::VERSION }}
        </footer>
    </main>

    <script src="{{ asset('/js/vendor/jquery/jquery-3.6.0.min.js') }}"></script>
    @include('partials.frontend-routes')
    <script src="{{ asset('/js/apps/front/welcome-portal.js') }}?v=2026080101"></script>
</body>
</html>
