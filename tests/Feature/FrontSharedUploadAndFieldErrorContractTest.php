<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/28
 * Time: 01:39
 */

/**
 * 前台共享上传组件与字段级校验提示契约测试。
 *
 * 文件功能：
 * - 需求 3：验证每个提交按钮只校验自己所属表单，且错误锚定到具体输入框（行内文案 + aria-invalid + 聚焦 + 滚动）。
 * - 需求 4：验证注册页手机号可输入并完整显示长于 11 位的号码，且 Blade、JS、PHP 三处口径一致。
 * - 需求 5：验证银行卡上传基于 Layui 2.13.5 重建，且同时具备正面与反面两个上传槽位。
 * - 需求 7：验证所有上传入口统一到 public/js/shared/layui-upload.js + public/css/common/crm-upload.css。
 *
 * 入参例子：
 * - 本测试只读取源码与静态资源，不需要 HTTP 入参。
 *
 * 返回值：
 * - 测试无返回值；全部断言通过表示前端契约闭环。
 *
 * 异常或失败场景：
 * - 断言失败表示某个上传入口或某个表单回退到了全局 toast / 旧上传实现。
 */

namespace Tests\Feature;

use Tests\TestCase;

class FrontSharedUploadAndFieldErrorContractTest extends TestCase
{
    /**
     * 需求 3：共享字段级校验模块必须提供按表单校验与字段锚定能力。
     *
     * @return void 契约完整时无返回值。
     */
    public function test_shared_field_error_module_anchors_errors_to_the_offending_input(): void
    {
        $script = file_get_contents(public_path('js/shared/form-field-errors.js')) ?: '';

        foreach ([
            'window.CrmFieldErrors = {',
            'function validateRequired(target, options)',
            'function show(target, field, message)',
            'function showUpload(target, field, message)',
            'function clearField(target, field)',
            'function bindAutoClear(target)',
            'function ensureSlot(form, field)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $script, 'Shared field error module is missing: ' . $needle);
        }

        // 行内提示 + 无障碍关联 + 聚焦 + 滚动到可视区域，缺一不可。
        $this->assertStringContainsString("slot.setAttribute('role', 'alert')", $script);
        $this->assertStringContainsString("slot.setAttribute('aria-live', 'assertive')", $script);
        $this->assertStringContainsString("control.setAttribute('aria-describedby', slotId)", $script);
        $this->assertStringContainsString("control.setAttribute('aria-invalid', 'true')", $script);
        $this->assertStringContainsString('scrollIntoView(anchor);', $script);
        $this->assertStringContainsString('focusControl(control);', $script);
        $this->assertStringContainsString("behavior: 'smooth', block: 'center'", $script);

        // 校验必须按 DOM 顺序在当前表单内推进，命中第一个空字段即返回。
        $this->assertStringContainsString('controls = form.querySelectorAll(CONTROL_SELECTOR);', $script);
        $this->assertStringContainsString('if (controlValue(form, control) === \'\') {', $script);

        $this->assertStringNotContainsString('layer.msg', $script, 'Field level errors must not fall back to a global toast.');
    }

    /**
     * 需求 3：资料页每个提交按钮必须在捕获阶段先校验自己的表单，再交给 Layui 提交。
     *
     * @return void 契约完整时无返回值。
     */
    public function test_profile_submit_buttons_validate_only_their_own_form(): void
    {
        $script = $this->profileScript();

        $this->assertStringContainsString('function bindProfileFieldValidation()', $script);
        $this->assertStringContainsString('function validateProfileForm(form)', $script);
        $this->assertStringContainsString('function validateProfileFormats(form)', $script);
        $this->assertStringContainsString("$('[lay-submit]').each(function() {", $script);
        $this->assertStringContainsString("var form = $(button).closest('form')[0];", $script);
        // 捕获阶段监听 + 阻断，保证 Layui 的 document 冒泡委托不会再弹全局提示。
        $this->assertStringContainsString("button.addEventListener('click', function(event) {", $script);
        $this->assertStringContainsString('event.stopImmediatePropagation();', $script);
        $this->assertStringContainsString('}, true);', $script);
        $this->assertStringContainsString('window.CrmFieldErrors.validateRequired(form, {', $script);
        $this->assertStringContainsString('var formUploadRequirements = {', $script);

        foreach (['identityForm', 'bankForm', 'bankChangeForm'] as $filter) {
            $this->assertStringContainsString($filter . ': {', $script, 'Missing per-form upload requirement map: ' . $filter);
        }

        // 缺少必传图片时提示必须锚定到上传块，而不是全局 toast。
        $this->assertStringContainsString('window.CrmFieldErrors.showUpload(formEl, cacheField', $script);
    }

    /**
     * 需求 3：资料页 Blade 必须为每个上传字段声明行内错误提示位。
     *
     * @return void 契约完整时无返回值。
     */
    public function test_profile_blades_declare_inline_error_slots(): void
    {
        $blade = file_get_contents(resource_path('front/layui/profile/index.blade.php')) ?: '';
        $v2Blade = file_get_contents(resource_path('front/layui/profile/index_v2.blade.php')) ?: '';

        foreach ([
            'avatar',
            'id_card_front',
            'id_card_back',
            'bank_card_img',
            'bank_card_img_back',
            'bank_change_card_img',
            'bank_change_card_img_back',
        ] as $field) {
            $this->assertStringContainsString(
                'data-error-for="' . $field . '"',
                $blade,
                $field . ' is missing an inline error slot on the profile page.'
            );
        }

        foreach (['avatar', 'id_card_front', 'id_card_back', 'bank_card_img', 'bank_card_img_back'] as $field) {
            $this->assertStringContainsString(
                'data-error-for="' . $field . '"',
                $v2Blade,
                $field . ' is missing an inline error slot on the profile v2 page.'
            );
        }

        $this->assertStringContainsString('class="crm-field-error"', $blade);
        $this->assertStringContainsString('aria-live="assertive"', $blade);
    }

    /**
     * 需求 3：字段级错误样式必须落在共享样式层，并使用语义皮肤令牌。
     *
     * @return void 契约完整时无返回值。
     */
    public function test_field_error_styles_live_in_the_shared_design_system(): void
    {
        $css = file_get_contents(public_path('css/common/crm-design-system.css')) ?: '';

        $this->assertStringContainsString('.crm-field-error {', $css);
        $this->assertStringContainsString('.crm-field-error.is-visible {', $css);
        $this->assertStringContainsString('color: var(--crm-danger);', $css);
        $this->assertStringContainsString('--crm-danger-ring:', $css);
        $this->assertStringContainsString('[data-upload-field][aria-invalid="true"] .layui-upload-drag,', $css);
    }

    /**
     * 需求 4：注册页手机号必须能输入并完整显示长于 11 位的号码。
     *
     * @return void 契约完整时无返回值。
     */
    public function test_register_phone_input_accepts_and_displays_more_than_eleven_digits(): void
    {
        $blade = file_get_contents(resource_path('front/layui/auth/register.blade.php')) ?: '';
        $v2Blade = file_get_contents(resource_path('front/layui/auth/register_v2.blade.php')) ?: '';
        $crmuiBlade = file_get_contents(resource_path('front/crmui/auth/register.blade.php')) ?: '';
        $css = file_get_contents(public_path('css/front/style.css')) ?: '';

        foreach ([$blade, $v2Blade] as $index => $source) {
            $label = $index === 0 ? 'register.blade.php' : 'register_v2.blade.php';

            $this->assertStringContainsString('name="phone_number"', $source, $label . ' is missing the phone input.');
            $this->assertStringContainsString('maxlength="20"', $source, $label . ' must allow up to 20 digits.');
            $this->assertStringContainsString('minlength="11"', $source, $label . ' must accept 11-digit local numbers.');
            $this->assertStringContainsString('size="20"', $source, $label . ' must render an input wide enough for 20 digits.');
            $this->assertStringNotContainsString('maxlength="11"', $source, $label . ' must not cap the phone number at 11 characters.');
            $this->assertStringContainsString('register-phone-input', $source, $label . ' must use the widened phone input class.');
            $this->assertStringContainsString('register-phone-hint', $source, $label . ' must explain the accepted length.');
        }

        $this->assertStringContainsString('pattern="[0-9]{11,20}"', $crmuiBlade, 'CrmUI register must share the 11-20 digit contract.');
        $this->assertStringContainsString('maxlength="20"', $crmuiBlade);

        // 输入框必须留出至少 20 位数字的可视宽度。
        $this->assertStringContainsString('.register-phone-row.is-wide {', $css);
        $this->assertStringContainsString('grid-template-columns: 132px minmax(240px, 1fr);', $css);
        $this->assertStringContainsString('.register-phone-input {', $css);
        $this->assertStringContainsString('min-width: 240px;', $css);
    }

    /**
     * 需求 4：手机号口径必须在 Blade、前端 JS 与后端校验之间保持一致。
     *
     * @return void 三处一致时无返回值。
     */
    public function test_register_phone_length_contract_is_consistent_between_js_and_php(): void
    {
        $registerScript = $this->registryScript('auth/register');
        $controller = file_get_contents(app_path('Http/Controllers/Front/AuthController.php')) ?: '';

        $this->assertStringContainsString('/^[0-9]{11,20}$/', $registerScript, 'Front JS must enforce the 11-20 digit contract.');
        $this->assertStringNotContainsString('/^[0-9]{12,20}$/', $registerScript, 'The stale 12-digit floor must be gone.');

        $this->assertStringContainsString('private const PHONE_NUMBER_MIN_LENGTH = 11;', $controller);
        $this->assertStringContainsString('private const PHONE_NUMBER_MAX_LENGTH = 20;', $controller);
        $this->assertStringContainsString(
            "'min:' . self::PHONE_NUMBER_MIN_LENGTH, 'max:' . self::PHONE_NUMBER_MAX_LENGTH, 'regex:/^[0-9]+$/'",
            $controller,
            'Server side must enforce the same digits-only 11-20 contract.'
        );
        $this->assertStringNotContainsString(
            "'phone_number'  => 'required|string|max:30'",
            $controller,
            'The looser max:30 rule must be replaced by the shared contract.'
        );
        $this->assertStringNotContainsString(
            "'phone_number' => 'required|string|max:30'",
            $controller,
            'The verification code entry must not stay looser than registration.'
        );
    }

    /**
     * 需求 5：银行卡上传必须同时具备正反面槽位，并基于最新 Layui 上传组件重建。
     *
     * @return void 契约完整时无返回值。
     */
    public function test_bank_card_upload_has_paired_front_and_back_slots(): void
    {
        $blade = file_get_contents(resource_path('front/layui/profile/index.blade.php')) ?: '';
        $v2Blade = file_get_contents(resource_path('front/layui/profile/index_v2.blade.php')) ?: '';

        // 绑定银行卡与换绑银行卡两组表单，都必须成对出现正反面。
        foreach ([
            ['bank_card_img', 'bank_card_img_back'],
            ['bank_change_card_img', 'bank_change_card_img_back'],
        ] as $pair) {
            foreach ($pair as $field) {
                $this->assertStringContainsString('data-crm-upload="' . $field . '"', $blade, $field . ' must use the shared upload component.');
                $this->assertStringContainsString('data-upload-preview="' . $field . '"', $blade, $field . ' must expose a preview thumbnail.');
                $this->assertStringContainsString('data-upload-clear="' . $field . '"', $blade, $field . ' must expose a remove/re-pick button.');
            }
        }

        // v2 视觉家族此前缺少反面槽位，必须补齐。
        $this->assertStringContainsString('data-crm-upload="bank_card_img"', $v2Blade);
        $this->assertStringContainsString('data-crm-upload="bank_card_img_back"', $v2Blade, 'Profile v2 must gain the bank card back slot.');
        $this->assertStringContainsString('data-upload-preview="bank_card_img_back"', $v2Blade);
        $this->assertStringContainsString('data-upload-clear="bank_card_img_back"', $v2Blade);

        // 成对布局与拖拽区、进度条同时具备。
        $this->assertSame(3, substr_count($blade, 'class="layui-form-item crm-upload-pair"'), 'Profile page must render three paired upload groups.');
        $this->assertStringContainsString('crm-upload-pair-slot', $blade);
        $this->assertStringContainsString('layui-upload-drag', $blade);
        $this->assertStringContainsString('data-upload-progress', $blade);
        $this->assertStringContainsString('crm-upload-drag-hint', $blade);
    }

    /**
     * 需求 5/7：后端银行卡接口契约必须保持不变，只替换前端交互。
     *
     * @return void 契约未变时无返回值。
     */
    public function test_bank_card_upload_preserves_the_existing_api_contract(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Front/ProfileController.php')) ?: '';
        $script = $this->profileScript();

        // 后端字段名、类型与体积上限保持原样。
        $this->assertStringContainsString("'bank_card_img' => 'required|image|mimes:jpeg,png,jpg,gif|max:4096'", $controller);
        $this->assertStringContainsString("'bank_card_back_img' => 'required|image|mimes:jpeg,png,jpg,gif|max:4096'", $controller);

        // 前端仍按原有映射把缓存文件塞进 FormData，接口地址不变。
        $this->assertStringContainsString("submitMultipart('/api/front/profile/bank-card', data.form, {", $script);
        $this->assertStringContainsString("submitMultipart('/api/front/profile/bank-card-change', data.form, {", $script);
        $this->assertStringContainsString("bank_card_back_img: 'bank_card_img_back'", $script);
        $this->assertStringContainsString("bank_card_back_img: 'bank_change_card_img_back'", $script);
    }

    /**
     * 需求 7：共享上传组件必须提供拖拽、缩略图、进度条、移除与友好错误文案能力。
     *
     * @return void 契约完整时无返回值。
     */
    public function test_shared_upload_module_provides_the_full_layui_upload_experience(): void
    {
        $script = file_get_contents(public_path('js/shared/layui-upload.js')) ?: '';
        $css = file_get_contents(public_path('css/common/crm-upload.css')) ?: '';

        foreach ([
            'window.CrmUpload = {',
            'function initBlock(block, overrides)',
            'function handleChoose(block, config, obj, options)',
            'function setProgress(block, percent, visible)',
            'function showFile(block, file, previewUrl)',
            'function reset(block, keepPreview)',
            'function fail(block, message)',
            'function validateFile(file, config)',
            'function errorText(reason, config)',
            'function formatSize(size)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $script, 'Shared upload module is missing: ' . $needle);
        }

        // 基于 layui.upload 渲染，且拖拽默认开启。
        $this->assertStringContainsString('upload = window.layui && window.layui.upload;', $script);
        $this->assertStringContainsString('instances[config.field] = upload.render(renderOptions);', $script);
        $this->assertStringContainsString('drag: true,', $script);
        // 上传地址、字段名、accept 与体积上限全部来自调用方，不在组件里写死。
        $this->assertStringContainsString("url: config.url || undefined,", $script);
        $this->assertStringContainsString('field: config.name || config.field,', $script);
        $this->assertStringContainsString('accept: config.accept,', $script);
        $this->assertStringContainsString('size: config.size || undefined,', $script);
        // 友好错误文案走语言包，不硬编码业务文案。
        $this->assertStringContainsString("t('front.upload_error_type'", $script);
        $this->assertStringContainsString("t('front.upload_error_size'", $script);
        $this->assertStringContainsString("t('front.upload_error_network'", $script);

        foreach ([
            '.crm-upload .layui-upload-drag,',
            '.crm-upload-progress {',
            '.crm-upload-progress-bar {',
            '.crm-upload-clear {',
            '.crm-upload-pair {',
            '.crm-upload-status.is-error {',
        ] as $selector) {
            $this->assertStringContainsString($selector, $css, 'Shared upload CSS is missing: ' . $selector);
        }

        $this->assertDoesNotMatchRegularExpression(
            '/#[0-9a-f]{3,8}\b|rgba?\(|hsla?\(/i',
            $css,
            'Shared upload CSS must consume theme tokens instead of colour literals.'
        );
    }

    /**
     * 需求 7：共享上传资源必须在前后台各视觉家族的入口统一注册。
     *
     * @return void 全部注册时无返回值。
     */
    public function test_shared_upload_assets_are_registered_across_every_shell(): void
    {
        $shells = [
            'resources/front/layui/layouts/app.blade.php',
            'resources/admin/layui/layouts/app.blade.php',
            'resources/front/crmui/layouts/app.blade.php',
            'resources/admin/crmui/layouts/app.blade.php',
        ];

        foreach ($shells as $shell) {
            $blade = file_get_contents(base_path($shell)) ?: '';

            $this->assertStringContainsString('/js/shared/form-field-errors.js', $blade, $shell . ' must load the shared field error module.');
            $this->assertStringContainsString('/js/shared/layui-upload.js', $blade, $shell . ' must load the shared upload module.');
            $this->assertStringContainsString('/css/common/crm-upload.css', $blade, $shell . ' must load the shared upload stylesheet.');
        }

        // 注册页是独立入口，也需要字段级校验提示。
        foreach ([
            'resources/front/layui/auth/register.blade.php',
            'resources/front/layui/auth/register_v2.blade.php',
        ] as $authBlade) {
            $this->assertStringContainsString(
                '/js/shared/form-field-errors.js',
                file_get_contents(base_path($authBlade)) ?: '',
                $authBlade . ' must load the shared field error module.'
            );
        }
    }

    /**
     * 需求 7：模块化表单与 CrmUI 家族的上传入口都必须接入共享组件。
     *
     * @return void 全部接入时无返回值。
     */
    public function test_module_and_crmui_upload_entries_use_the_shared_component(): void
    {
        $frontPartial = file_get_contents(resource_path('front/layui/partials/module-page.blade.php')) ?: '';
        $frontCrmuiPartial = file_get_contents(resource_path('front/crmui/partials/module-page.blade.php')) ?: '';
        $adminCrmuiPartial = file_get_contents(resource_path('admin/crmui/partials/module-page.blade.php')) ?: '';
        $moduleScript = file_get_contents(public_path('js/apps/front/layui/module-page.js')) ?: '';
        $crmuiFront = file_get_contents(public_path('js/apps/crmui/front.js')) ?: '';
        $crmuiAdmin = file_get_contents(public_path('js/apps/crmui/admin.js')) ?: '';

        // Layui 模块页上传：共享属性 + 进度条 + 拖拽提示 + 行内错误。
        $this->assertStringContainsString('data-crm-upload="{{ $field[\'name\'] }}"', $frontPartial);
        $this->assertStringContainsString('data-upload-max-size="{{ $uploadMaxSize }}"', $frontPartial);
        $this->assertStringContainsString('crm-upload-progress', $frontPartial);
        $this->assertStringContainsString('crm-upload-drag-hint', $frontPartial);
        $this->assertStringContainsString('data-error-for="{{ $field[\'name\'] }}"', $frontPartial);
        // 原有 accept、multiple 与字段名保持不变。
        $this->assertStringContainsString('name="{{ $field[\'name\'] }}"', $frontPartial);
        $this->assertStringContainsString('accept="{{ $field[\'accept\'] }}"', $frontPartial);

        // 模块页脚本把必传上传项的错误锚定到上传块。
        $this->assertStringContainsString('window.CrmFieldErrors.showUpload(', $moduleScript);
        $this->assertStringContainsString('window.CrmFieldErrors.clearUpload(document, fieldName);', $moduleScript);

        // CrmUI/Naive 家族上传：补齐体积文案与行内错误，并移除硬编码英文。
        foreach ([$frontCrmuiPartial, $adminCrmuiPartial] as $partial) {
            $this->assertStringContainsString('data-crmui-upload-size', $partial);
            $this->assertStringContainsString('data-error-for="{{ $field[\'name\'] }}"', $partial);
        }

        foreach ([$crmuiFront, $crmuiAdmin] as $crmuiScript) {
            $this->assertStringContainsString('window.CrmUpload.formatSize(file.size || 0)', $crmuiScript);
            $this->assertStringContainsString("data-crmui-upload-size", $crmuiScript);
            $this->assertStringNotContainsString("text(file ? file.name : 'No file selected')", $crmuiScript, 'CrmUI upload text must come from the language pack.');
        }
    }

    /**
     * 需求 7：新增的上传与校验文案必须同时存在于两种语言的 PHP 与 JS 语言包。
     *
     * @return void 两侧齐备时无返回值。
     */
    public function test_upload_and_validation_messages_exist_in_both_locales(): void
    {
        $keys = [
            'field_required_message',
            'upload_error_type',
            'upload_error_size',
            'upload_error_network',
            'upload_error_unknown',
            'upload_uploading',
            'upload_done',
            'upload_choose_or_drag',
            'upload_no_preview',
            'upload_remove',
        ];

        $en = require resource_path('lang/en/front.php');
        $zh = require resource_path('lang/zh-CN/front.php');
        $enJs = file_get_contents(public_path('js/shared/lang/common/en.js')) ?: '';
        $zhJs = file_get_contents(public_path('js/shared/lang/common/zh-CN.js')) ?: '';

        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $en, 'lang/en/front.php is missing ' . $key);
            $this->assertArrayHasKey($key, $zh, 'lang/zh-CN/front.php is missing ' . $key);
            $this->assertNotSame('', trim((string) $en[$key]));
            $this->assertNotSame('', trim((string) $zh[$key]));
            $this->assertStringContainsString($key . ':', $enJs, 'shared/lang/common/en.js is missing ' . $key);
            $this->assertStringContainsString($key . ':', $zhJs, 'shared/lang/common/zh-CN.js is missing ' . $key);
        }

        // 银行卡与身份证成对上传的分组标题也要两语齐备。
        $enProfile = require resource_path('lang/en/profile.php');
        $zhProfile = require resource_path('lang/zh-CN/profile.php');
        foreach (['bank_card_images', 'id_card_images', 'emailInvalid'] as $key) {
            $this->assertArrayHasKey($key, $enProfile, 'lang/en/profile.php is missing ' . $key);
            $this->assertArrayHasKey($key, $zhProfile, 'lang/zh-CN/profile.php is missing ' . $key);
        }

        // 注册页手机号提示同样需要两语齐备。
        $enRegister = require resource_path('lang/en/register.php');
        $zhRegister = require resource_path('lang/zh-CN/register.php');
        foreach (['phone_number_placeholder', 'phone_length_hint'] as $key) {
            $this->assertArrayHasKey($key, $enRegister, 'lang/en/register.php is missing ' . $key);
            $this->assertArrayHasKey($key, $zhRegister, 'lang/zh-CN/register.php is missing ' . $key);
        }
    }

    /**
     * 读取聚合后的资料页脚本片段。
     *
     * @return string registry['profile/index'] 对应的脚本源码。
     */
    private function profileScript(): string
    {
        $source = file_get_contents(public_path('js/apps/front/layui/pages.js')) ?: '';
        $needle = "registry['profile/index'] = once(function () {";
        $start = strpos($source, $needle);

        if ($start === false) {
            return '';
        }

        $end = strpos($source, "\n    registry['", $start + strlen($needle));

        return $end === false ? substr($source, $start) : substr($source, $start, $end - $start);
    }

    /**
     * 读取聚合脚本中指定页面的注册片段。
     *
     * @param string $page registry 键名，例如 auth/register。
     * @return string 对应片段源码；未找到时返回空字符串。
     */
    private function registryScript(string $page): string
    {
        $source = file_get_contents(public_path('js/apps/front/layui/pages.js')) ?: '';
        $needle = "registry['" . $page . "'] = once(function () {";
        $start = strpos($source, $needle);

        if ($start === false) {
            return '';
        }

        $end = strpos($source, "\n    registry['", $start + strlen($needle));

        return $end === false ? substr($source, $start) : substr($source, $start, $end - $start);
    }
}
