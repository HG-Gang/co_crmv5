(function() {
    const results = {
        header: null,
        allSwitchers: []
    };

    // 找到头部区域
    const header = document.querySelector('.layui-header, header, [class*="header"]');
    if (header) {
        results.header = {
            className: header.className,
            id: header.id,
            innerHTML: header.innerHTML.substring(0, 500)
        };

        // 查找所有可能的切换器容器
        const potentialSwitchers = header.querySelectorAll('[data-theme], [data-lang], [data-family], [class*="switch"], [class*="theme"], [class*="lang"]');

        potentialSwitchers.forEach((el, idx) => {
            if (idx < 10) { // 只取前10个
                results.allSwitchers.push({
                    tag: el.tagName,
                    className: el.className,
                    id: el.id,
                    dataset: Object.keys(el.dataset).join(','),
                    html: el.outerHTML.substring(0, 150)
                });
            }
        });
    }

    return results;
})()
