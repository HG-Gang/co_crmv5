(function() {
    const results = {
        viewport: {
            width: window.innerWidth,
            height: window.innerHeight
        },
        sidebar: null,
        table: null,
        themeSwitch: null,
        langSwitch: null
    };

    // 检查侧边栏
    const sidebar = document.querySelector('.layui-side, .crm-sidebar, [class*="sidebar"]');
    if (sidebar) {
        const computed = window.getComputedStyle(sidebar);
        results.sidebar = {
            display: computed.display,
            width: computed.width,
            position: computed.position,
            transform: computed.transform
        };
    }

    // 检查表格布局
    const table = document.querySelector('.layui-table, table');
    if (table) {
        const container = table.closest('.layui-form, .layui-card-body, [class*="table-container"]');
        const computed = window.getComputedStyle(container || table.parentElement);
        results.table = {
            containerWidth: computed.width,
            containerPadding: computed.paddingLeft + ' / ' + computed.paddingRight,
            tableWidth: window.getComputedStyle(table).width,
            marginLeft: window.getComputedStyle(table).marginLeft,
            marginRight: window.getComputedStyle(table).marginRight
        };
    }

    // 检查主题切换器
    const themeSwitch = document.querySelector('#crm-theme-switch, [data-crm-theme-switch]');
    if (themeSwitch) {
        const computed = window.getComputedStyle(themeSwitch);
        results.themeSwitch = {
            exists: true,
            display: computed.display,
            hasIcon: themeSwitch.querySelector('i, svg, .icon') !== null,
            text: themeSwitch.textContent.trim().substring(0, 20)
        };
    }

    // 检查语言切换器
    const langSwitch = document.querySelector('#crm-lang-switch, [data-crm-lang-switch]');
    if (langSwitch) {
        const computed = window.getComputedStyle(langSwitch);
        results.langSwitch = {
            exists: true,
            display: computed.display,
            hasIcon: langSwitch.querySelector('i, svg, .icon') !== null,
            text: langSwitch.textContent.trim().substring(0, 20)
        };
    }

    return results;
})()
