/**
 * 前台旧版入口占位脚本。
 * 主要用于确认旧 CRM.use 加载链路仍可执行，真实页面逻辑由各模块脚本负责。
 */
CRM.use(['jquery'], function() {
    // 保留这个入口可以兼容仍引用 front/app.js 的旧模板，避免页面因脚本缺失报错。
    // 仅输出初始化标记，方便排查旧版入口是否被重复加载。
    console.log('Front App Initialized');
});
