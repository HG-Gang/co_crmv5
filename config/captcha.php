<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:27
 */

/**
 * 验证码组件配置（mews/captcha）。
 *
 * 配置用途：
 * - 定义前台/后台登录、注册等场景使用的图形验证码参数（长度、尺寸、干扰线、有效期等）。
 * - 与旧项目 custom_captcha 参数保持一致，避免迁移后验证码样式与校验行为变化。
 *
 * 注意：
 * - disable 开启后所有验证码校验直接跳过（仅用于本地调试，生产环境必须关闭）。
 * - 每个 key（default/custom_captcha/user_captcha）独立生效，修改后需清除验证码缓存。
 */

return [
    // 是否全局禁用验证码：true=跳过校验（仅限本地调试），false=正常校验（生产必须为 false）。
    'disable' => env('CAPTCHA_DISABLE', false),

    // 验证码可用字符集（去掉了易混淆的 0/O/1/l 等字符），默认与旧项目保持一致。
    'characters' => '2346789abcdefghjmnpqrtuxyzABCDEFGHJMNPQRTUXYZ',
    // 默认验证码配置组：未指定 key 时使用的默认样式与有效期。
    'default' => [
        'length' => 5, // 默认验证码字符个数（个）。
        'width' => 120, // 验证码图片宽度（像素）。
        'height' => 36, // 验证码图片高度（像素）。
        'quality' => 90, // 图片质量（0-100，越高越清晰、文件越大）。
        'math' => false, // 是否使用数学算式验证码：false=普通字符，true=算术题。
        'expire' => 60, // 验证码有效期（秒），过期后校验失败需刷新。
        'encrypt' => false, // 是否对验证码答案做加密存储（mews/captcha 内部开关）。
    ],
    // custom_captcha 配置组：对齐旧项目 custom_captcha 参数的验证码样式。
    'custom_captcha' => [
        'length' => 4, // 字符个数（个）。
        'width' => 150, // 图片宽度（像素）。
        'height' => 35, // 图片高度（像素）。
        'quality' => 90, // 图片质量（0-100）。
        'lines' => 6, // 干扰线数量（条），越多越难识别。
        'bgImage' => false, // 是否使用背景图片：false=纯色背景。
        'bgColor' => '#ecf2f4', // 背景颜色（HEX），bgImage=false 时生效。
        'fontColors' => [ // 文字颜色候选列表（HEX），随机取用。
            '#2c3e50',
            '#c0392b',
            '#16a085',
            '#8e44ad',
            '#303f9f',
            '#f57c00',
            '#795548',
        ],
        'contrast' => -5, // 对比度调整值（负数降低对比度，增加识别难度）。
        'sensitive' => false, // 是否区分大小写：false=不区分（校验时忽略大小写），true=区分。
        'expire' => 60, // 验证码有效期（秒）。
        'encrypt' => false, // 是否加密存储答案。
    ],
    // 保留旧前台 registercaptcha 的组件配置；当前注册页使用独立 key/cache 校验，
    // 其他兼容调用仍可能直接执行 mews/captcha::create('user_captcha')。
    'user_captcha' => [
        'length' => 4, // 字符个数（个）。
        'width' => 150, // 图片宽度（像素）。
        'height' => 35, // 图片高度（像素）。
        'quality' => 90, // 图片质量（0-100）。
        'lines' => 6, // 干扰线数量（条）。
        'bgImage' => false, // 是否使用背景图片。
        'bgColor' => '#ecf2f4', // 背景颜色（HEX）。
        'fontColors' => [ // 文字颜色候选列表（HEX）。
            '#2c3e50',
            '#c0392b',
            '#16a085',
            '#c0392b',
            '#8e44ad',
            '#303f9f',
            '#f57c00',
            '#795548',
        ],
        'contrast' => -5, // 对比度调整值。
        'sensitive' => false, // 是否区分大小写。
        'expire' => 60, // 验证码有效期（秒）。
        'encrypt' => false, // 是否加密存储答案。
    ],
];
