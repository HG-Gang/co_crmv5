(function() {
    const buttons = Array.from(document.querySelectorAll('button, a.layui-btn, .crm-btn, input[type="submit"], input[type="button"]'));
    const inputs = Array.from(document.querySelectorAll('input[type="text"], input[type="password"], input[type="email"], textarea, select'));

    const measureElement = (el) => {
        const rect = el.getBoundingClientRect();
        const computed = window.getComputedStyle(el);
        return {
            width: rect.width,
            height: rect.height,
            display: computed.display,
            minHeight: computed.minHeight,
            padding: computed.paddingTop + ' ' + computed.paddingBottom
        };
    };

    const buttonSizes = buttons.slice(0, 10).map(btn => ({
        tag: btn.tagName,
        text: btn.textContent.trim().substring(0, 15),
        ...measureElement(btn)
    }));

    const inputSizes = inputs.slice(0, 10).map(input => ({
        type: input.type || input.tagName,
        placeholder: input.placeholder ? input.placeholder.substring(0, 15) : '',
        ...measureElement(input)
    }));

    return {
        viewport: { width: window.innerWidth, height: window.innerHeight },
        buttonCount: buttons.length,
        inputCount: inputs.length,
        buttons: buttonSizes,
        inputs: inputSizes
    };
})()
