(function() {
    const stats = ['totalUsers', 'totalAgents', 'totalCustomers', 'pendingDeposits', 'pendingWithdraws', 'todayNew'];
    const results = {};

    stats.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            const computed = window.getComputedStyle(el);
            results[id] = {
                color: computed.color,
                background: computed.backgroundColor,
                parentBackground: window.getComputedStyle(el.parentElement).backgroundColor,
                grandparentBackground: window.getComputedStyle(el.parentElement.parentElement).backgroundColor,
                classList: Array.from(el.classList),
                innerHTML: el.innerHTML.substring(0, 50)
            };
        }
    });

    return results;
})()
