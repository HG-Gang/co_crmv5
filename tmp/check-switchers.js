(function() {
    const results = {
        themeSwitch: null,
        langSwitch: null,
        uiFamilySwitch: null
    };

    // 检查主题切换器
    const themeBtns = document.querySelectorAll('[data-theme], [data-crm-theme]');
    if (themeBtns.length > 0) {
        const firstBtn = themeBtns[0];
        const parent = firstBtn.closest('[data-crm-theme-switch], .crm-theme-switch, #crm-theme-switch');
        results.themeSwitch = {
            count: themeBtns.length,
            hasParentContainer: !!parent,
            parentSelector: parent ? parent.id || parent.className : null,
            firstBtnHtml: firstBtn.outerHTML.substring(0, 150),
            hasIcon: firstBtn.querySelector('i, svg, .icon, [class*="icon"]') !== null,
            textContent: firstBtn.textContent.trim()
        };
    }

    // 检查语言切换器
    const langBtns = document.querySelectorAll('[data-lang], [data-crm-lang]');
    if (langBtns.length > 0) {
        const firstBtn = langBtns[0];
        const parent = firstBtn.closest('[data-crm-lang-switch], .crm-lang-switch, #crm-lang-switch');
        results.langSwitch = {
            count: langBtns.length,
            hasParentContainer: !!parent,
            parentSelector: parent ? parent.id || parent.className : null,
            firstBtnHtml: firstBtn.outerHTML.substring(0, 150),
            hasIcon: firstBtn.querySelector('i, svg, .icon, [class*="icon"]') !== null,
            textContent: firstBtn.textContent.trim()
        };
    }

    // 检查UI家族切换器
    const familyBtns = document.querySelectorAll('[data-family], [data-crm-family]');
    if (familyBtns.length > 0) {
        results.uiFamilySwitch = {
            count: familyBtns.length,
            firstBtnText: familyBtns[0].textContent.trim(),
            hasIcon: familyBtns[0].querySelector('i, svg, .icon') !== null
        };
    }

    // 检查头部区域的切换器布局
    const header = document.querySelector('.layui-header, .crm-header, header, [class*="header"]');
    if (header) {
        const switchersInHeader = header.querySelectorAll('[data-theme], [data-lang], [data-family]');
        results.headerSwitchers = {
            count: switchersInHeader.length,
            headerWidth: header.offsetWidth
        };
    }

    return results;
})()
