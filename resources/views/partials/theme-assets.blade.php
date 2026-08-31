{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/18
Time: 10:37
--}}
@php($crmThemes = config('crm_themes.themes', []))
@php($crmThemeValues = json_encode(array_keys($crmThemes), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT))
<script type="application/json" id="crm-theme-values">{!! $crmThemeValues !!}</script>
<script src="{{ asset('/js/shared/theme-sync.js') }}?v=2026080701"></script>
<script src="{{ asset('/js/shared/preference-menu.js') }}?v=2026081701" defer></script>
<link rel="stylesheet" href="{{ asset('/css/common/theme-sync.css') }}?v=2026080701">
<style id="crm-theme-catalog">
@foreach($crmThemes as $crmThemeKey => $crmTheme)
@php($colors = $crmTheme['colors'])
@php($ui = $crmTheme['ui'])
html[data-crm-theme="{{ $crmThemeKey }}"],
html[data-crm-theme="{{ $crmThemeKey }}"] body[data-ui-family="layui"][data-visual-direction="c"],
html[data-crm-theme="{{ $crmThemeKey }}"] body[data-ui-family="crmui"][data-visual-direction="c"],
html[data-crm-theme="{{ $crmThemeKey }}"] body[data-ui-family="naive"][data-visual-direction="c"] {
    --crm-theme-bg: {{ $colors['background'] }};
    --crm-color-scheme: {{ $crmTheme['mode'] }};
    --crm-theme-panel: {{ $colors['surface'] }};
    --crm-theme-line: {{ $colors['border'] }};
    --crm-theme-text: {{ $colors['text'] }};
    --crm-theme-muted: {{ $colors['muted'] }};
    --crm-theme-strong: {{ $colors['heading'] }};
    --crm-theme-side: {{ $colors['sidebar'] }};
    --crm-theme-side-soft: {{ $colors['sidebar_hover'] }};
    --crm-theme-accent: {{ $colors['accent'] }};
    --crm-theme-blue: {{ $colors['accent'] }};
    --crm-theme-hover: {{ $colors['surface_alt'] }};
    --crm-theme-input: {{ $colors['surface'] }};
    --crm-bg: {{ $colors['background'] }};
    --crm-surface: {{ $colors['surface'] }};
    --crm-surface-soft: {{ $colors['surface_alt'] }};
    --crm-surface-strong: {{ $colors['surface_alt'] }};
    --crm-ink: {{ $colors['text'] }};
    --crm-ink-soft: {{ $colors['text'] }};
    --crm-muted: {{ $colors['muted'] }};
    --crm-line: {{ $colors['border'] }};
    --crm-line-strong: {{ $colors['border_strong'] }};
    --crm-primary: {{ $colors['accent'] }};
    --crm-primary-hover: {{ $colors['accent_hover'] }};
    --crm-accent: {{ $colors['accent'] }};
    --crm-accent-hover: {{ $colors['accent_hover'] }};
    --crm-on-accent: {{ $colors['on_accent'] }};
    --crm-warning: {{ $colors['warning'] }};
    --crm-warning-soft: {{ $colors['warning_bg'] }};
    --crm-on-warning: {{ $colors['warning_on'] }};
    --crm-danger: {{ $colors['danger'] }};
    --crm-danger-soft: {{ $colors['danger_bg'] }};
    --crm-on-danger: {{ $colors['danger_on'] }};
    --crm-success: {{ $colors['accent'] }};
    --crm-success-soft: color-mix(in srgb, {{ $colors['accent'] }} 12%, {{ $colors['surface'] }});
    --crm-info: {{ $colors['accent'] }};
    --crm-info-soft: color-mix(in srgb, {{ $colors['accent'] }} 12%, {{ $colors['surface'] }});
    --crm-online: {{ $colors['accent'] }};
    --crm-online-soft: color-mix(in srgb, {{ $colors['accent'] }} 12%, {{ $colors['surface'] }});
    --crm-sidebar: {{ $colors['sidebar'] }};
    --crm-sidebar-soft: {{ $colors['sidebar_hover'] }};
    --crm-sidebar-ink: {{ $colors['sidebar_text'] }};
    --crm-sidebar-muted: {{ $colors['sidebar_muted'] }};
    --crm-radius: {{ $ui['radius'] }};
    --crm-radius-sm: {{ $ui['radius_sm'] }};
    --crm-shadow: {{ $ui['shadow'] }};
    --crm-shadow-raised: {{ $ui['shadow'] }};
    --crm-scrim: color-mix(in srgb, {{ $colors['heading'] }} 62%, transparent);
    --crm-focus: color-mix(in srgb, {{ $colors['focus'] }} 22%, transparent);
    --crm-sidebar-width: {{ $ui['sidebar_width'] }};
    --front-bg: {{ $colors['background'] }};
    --front-panel: {{ $colors['surface'] }};
    --front-input: {{ $colors['surface'] }};
    --front-text: {{ $colors['text'] }};
    --front-muted: {{ $colors['muted'] }};
    --front-strong: {{ $colors['heading'] }};
    --front-line: {{ $colors['border'] }};
    --front-blue: {{ $colors['accent'] }};
    --front-accent: {{ $colors['accent'] }};
    --front-cyan: {{ $colors['accent'] }};
    --front-hover: {{ $colors['surface_alt'] }};
    --front-soft-accent: {{ $colors['surface_alt'] }};
    --front-chip-bg: {{ $colors['surface_alt'] }};
    --front-shadow: color-mix(in srgb, {{ $colors['heading'] }} 10%, transparent);
    --front-table-head: {{ $colors['surface_alt'] }};
    --front-focus-ring: color-mix(in srgb, {{ $colors['focus'] }} 22%, transparent);
    --front-side: {{ $colors['sidebar'] }};
    --front-side-soft: {{ $colors['sidebar_hover'] }};
    --front-side-text: {{ $colors['sidebar_text'] }};
    --front-side-muted: {{ $colors['sidebar_muted'] }};
    --front-on-accent: {{ $colors['on_accent'] }};
    --front-danger: {{ $colors['danger'] }};
    --front-danger-bg: {{ $colors['danger_bg'] }};
    --front-warn: {{ $colors['warning'] }};
    --front-warn-bg: {{ $colors['warning_bg'] }};
    --front-success: {{ $colors['accent'] }};
    --front-info: {{ $colors['accent'] }};
    --front-online: {{ $colors['accent'] }};
    --admin-bg: {{ $colors['background'] }};
    --admin-panel: {{ $colors['surface'] }};
    --admin-input: {{ $colors['surface'] }};
    --admin-text: {{ $colors['text'] }};
    --admin-muted: {{ $colors['muted'] }};
    --admin-strong: {{ $colors['heading'] }};
    --admin-line: {{ $colors['border'] }};
    --admin-blue: {{ $colors['accent'] }};
    --admin-accent: {{ $colors['accent'] }};
    --admin-hover: {{ $colors['surface_alt'] }};
    --admin-side: {{ $colors['sidebar'] }};
    --admin-side-soft: {{ $colors['sidebar_hover'] }};
    --admin-side-text: {{ $colors['sidebar_text'] }};
    --admin-side-muted: {{ $colors['sidebar_muted'] }};
    --admin-on-accent: {{ $colors['on_accent'] }};
    --admin-danger: {{ $colors['danger'] }};
    --admin-warning: {{ $colors['warning'] }};
    --admin-success: {{ $colors['accent'] }};
    --admin-info: {{ $colors['accent'] }};
    --admin-online: {{ $colors['accent'] }};
    --crmui-bg: {{ $colors['background'] }};
    --crmui-surface: {{ $colors['surface'] }};
    --crmui-surface-2: {{ $colors['surface_alt'] }};
    --crmui-ink: {{ $colors['text'] }};
    --crmui-muted: {{ $colors['muted'] }};
    --crmui-subtle: {{ $colors['muted'] }};
    --crmui-border: {{ $colors['border'] }};
    --crmui-border-strong: {{ $colors['border_strong'] }};
    --crmui-primary: {{ $colors['accent'] }};
    --crmui-primary-ink: {{ $colors['on_accent'] }};
    --crmui-accent: {{ $colors['accent'] }};
    --crmui-warning: {{ $colors['warning'] }};
    --crmui-warning-soft: {{ $colors['warning_bg'] }};
    --crmui-warning-ink: {{ $colors['warning_on'] }};
    --crmui-danger: {{ $colors['danger'] }};
    --crmui-danger-soft: {{ $colors['danger_bg'] }};
    --crmui-danger-ink: {{ $colors['danger_on'] }};
    --crmui-success: {{ $colors['accent'] }};
    --crmui-info: {{ $colors['accent'] }};
    --crmui-online: {{ $colors['accent'] }};
    --crmui-shadow: {{ $ui['shadow'] }};
    --crmui-focus: 0 0 0 3px color-mix(in srgb, {{ $colors['focus'] }} 22%, transparent);
    --crmui-radius: {{ $ui['radius'] }};
    --crmui-sidebar: {{ $ui['sidebar_width'] }};
    --crmui-admin-sidebar-bg: {{ $colors['sidebar'] }};
    --crmui-admin-sidebar-bg-dark: {{ $colors['sidebar'] }};
    --crmui-admin-sidebar-text: {{ $colors['sidebar_text'] }};
    --crmui-admin-sidebar-hover: {{ $colors['sidebar_hover'] }};
    --crmui-admin-sidebar-active: {{ $colors['sidebar_hover'] }};
    --crmui-admin-topbar-bg: {{ $colors['surface'] }};
    --crmui-admin-topbar-bg-dark: {{ $colors['surface'] }};
    --crmui-admin-topbar-border: {{ $colors['border'] }};
    --crmui-admin-card-bg: {{ $colors['surface'] }};
    --crmui-admin-card-bg-dark: {{ $colors['surface'] }};
    --crmui-admin-card-shadow: {{ $ui['shadow'] }};
    --crmui-admin-card-shadow-dark: {{ $ui['shadow'] }};
    --crmui-vc-bg: {{ $colors['background'] }};
    --crmui-vc-surface: {{ $colors['surface'] }};
    --crmui-vc-surface-raised: {{ $colors['surface_alt'] }};
    --crmui-vc-text: {{ $colors['text'] }};
    --crmui-vc-muted: {{ $colors['muted'] }};
    --crmui-vc-border: {{ $colors['border'] }};
    --crmui-vc-border-strong: {{ $colors['border_strong'] }};
    --crmui-vc-accent: {{ $colors['accent'] }};
    --crmui-vc-success: {{ $colors['accent'] }};
    --crmui-vc-info: {{ $colors['accent'] }};
    --crmui-vc-online: {{ $colors['accent'] }};
    --crmui-vc-warning: {{ $colors['warning'] }};
    --crmui-vc-danger: {{ $colors['danger'] }};
    --crmui-vc-radius: {{ $ui['radius'] }};
    --crmui-vc-on-accent: {{ $colors['on_accent'] }};
    --crmui-vc-sidebar-bg: {{ $colors['sidebar'] }};
    --crmui-vc-scrim: color-mix(in srgb, {{ $colors['heading'] }} 62%, transparent);
    --crmui-vc-shadow-color: color-mix(in srgb, {{ $colors['heading'] }} 32%, transparent);
    --layui-vc-bg: {{ $colors['background'] }};
    --layui-vc-surface: {{ $colors['surface'] }};
    --layui-vc-surface-raised: {{ $colors['surface_alt'] }};
    --layui-vc-border: {{ $colors['border'] }};
    --layui-vc-text: {{ $colors['text'] }};
    --layui-vc-muted: {{ $colors['muted'] }};
    --layui-vc-accent: {{ $colors['accent'] }};
    --layui-vc-success: {{ $colors['accent'] }};
    --layui-vc-danger: {{ $colors['danger'] }};
    --layui-vc-info: {{ $colors['accent'] }};
    --layui-vc-online: {{ $colors['accent'] }};
    --layui-vc-warning: {{ $colors['warning'] }};
    --layui-vc-on-accent: {{ $colors['on_accent'] }};
    --layui-vc-sidebar-bg: {{ $colors['sidebar'] }};
    --layui-vc-scrim: color-mix(in srgb, {{ $colors['heading'] }} 62%, transparent);
    --layui-vc-shadow-color: color-mix(in srgb, {{ $colors['heading'] }} 32%, transparent);
    --layui-vc-radius: {{ $ui['radius'] }};
    --layui-vc-sidebar: {{ $ui['sidebar_width'] }};
    --v2-bg: {{ $colors['background'] }};
    --v2-bg-soft: {{ $colors['surface_alt'] }};
    --v2-surface: {{ $colors['surface'] }};
    --v2-surface-soft: {{ $colors['surface_alt'] }};
    --v2-panel-bg: {{ $colors['sidebar'] }};
    --v2-panel-text: {{ $colors['sidebar_text'] }};
    --v2-panel-text-muted: {{ $colors['sidebar_muted'] }};
    --v2-ink: {{ $colors['text'] }};
    --v2-muted: {{ $colors['muted'] }};
    --v2-line: {{ $colors['border'] }};
    --v2-line-soft: {{ $colors['border'] }};
    --v2-primary: {{ $colors['accent'] }};
    --v2-primary-dark: {{ $colors['accent_hover'] }};
    --v2-on-primary: {{ $colors['on_accent'] }};
    --v2-success: {{ $colors['accent'] }};
    --v2-info: {{ $colors['accent'] }};
    --v2-online: {{ $colors['accent'] }};
    --v2-warning: {{ $colors['warning'] }};
    --v2-danger: {{ $colors['danger'] }};
    --v2-radius: {{ $ui['radius'] }};
    --v2-radius-sm: {{ $ui['radius_sm'] }};
    --v2-shadow: {{ $ui['shadow'] }};
    --crm-table-row-height: {{ $ui['table_row_height'] }};
}
@endforeach
</style>
<link rel="stylesheet" href="{{ asset('/css/common/crm-themes.css') }}?v=2026080702">
