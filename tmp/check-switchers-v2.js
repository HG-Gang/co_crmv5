(function() {
    const results = {
        themeSwitch: null,
        langSwitch: null,
        uiFamilySwitch: null
    };

    // 检查主题切换器容器
    const themeContainer = document.querySelector('[data-crm-theme-switch]');
    if (themeContainer) {
        const triggerBtn = themeContainer.querySelector('button, a, [role="button"]');
        results.themeSwitch = {
            exists: true,
            containerClass: themeContainer.className,
            triggerHtml: triggerBtn ? triggerBtn.outerHTML.substring(0, 200) : 'no trigger',
            hasIcon: triggerBtn ? (triggerBtn.querySelector('i, svg, .icon') !== null) : false,
            triggerText: triggerBtn ? triggerBtn.textContent.trim() : ''
        };
    }

    // 检查语言切换器容器
    const langContainer = document.querySelector('[data-crm-lang-switch]');
    if (langContainer) {
        const triggerBtn = langContainer.querySelector('button, a, [role="button"]');
        results.langSwitch = {
            exists: true,
            containerClass: langContainer.className,
            triggerHtml: triggerBtn ? triggerBtn.outerHTML.substring(0, 200) : 'no trigger',
            hasIcon: triggerBtn ? (triggerBtn.querySelector('i, svg, .icon') !== null) : false,
            triggerText: triggerBtn ? triggerBtn.textContent.trim() : ''
        };
    }

    // 检查UI家族切换器
    const familyContainer = document.querySelector('[data-crm-family-switch]');
    if (familyContainer) {
        const triggerBtn = familyContainer.querySelector('button, a, [role="button"]');
        results.uiFamilySwitch = {
            exists: true,
            containerClass: familyContainer.className,
            triggerHtml: triggerBtn ? triggerBtn.outerHTML.substring(0, 200) : 'no trigger',
            hasIcon: triggerBtn ? (triggerBtn.querySelector('i, svg, .icon') !== null) : false,
            triggerText: triggerBtn ? triggerBtn.textContent.trim() : ''
        };
    }

    return results;
})()
