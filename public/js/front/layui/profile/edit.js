layui.use(['form', 'layer', 'jquery', 'upload'], function() {
    var form = layui.form, layer = layui.layer, $ = layui.jquery, upload = layui.upload;
    var selectedAvatar = null;
    
    // Initial UI i18n
    CrmLang.switchUI();

    // Load initial profile data
    loadProfileInfo();

    form.verify({
        profileRequired: function(value, elem) {
            if (!$.trim(value || '')) {
                return requiredMessage(elem);
            }
        }
    });

    function translateOr(key, fallback) {
        var value = CrmLang.t(key);
        return value && value !== key ? value : fallback;
    }

    // 统一独立资料页的必填提示格式，明确告诉用户是哪个表单、哪个字段没有填写。
    function requiredTemplateMessage(formTitle, fieldTitle) {
        var template = translateOr('front.profile_required_message', '请填写【{form}】的【{field}】');
        return template
            .replace('{form}', $.trim(formTitle || translateOr('profile.editProfile', '编辑资料')))
            .replace('{field}', $.trim(fieldTitle || ''));
    }

    // 从当前输入框所在卡片标题和表单标签中提取可读名称，避免只提示笼统的 common.error。
    function requiredMessage(elem) {
        var $elem = $(elem);
        var formTitle = $.trim($elem.closest('.layui-card').find('.layui-card-header').first().text()) || translateOr('profile.editProfile', '编辑资料');
        var fieldTitle = $.trim($elem.closest('.layui-form-item').find('.layui-form-label').first().text()) || $elem.attr('name') || '';

        return requiredTemplateMessage(formTitle, fieldTitle);
    }

    function loadProfileInfo() {
        CrmAjax.request({
            guard: 'front',
            url: '/api/front/profileInfo',
            success: function(res) {
                if (res.code === 1000 || res.code === 2000) {
                    // 编辑页只写 user_infos 可编辑字段，login/auth 字段不混入表单，避免误提交。
                    var user = res.data.info || {};
                    form.val('profileForm', {
                        'user_name': user.user_name,
                        'phone': user.phone,
                        'gender': user.gender ? user.gender.toString() : '1',
                        'address': user.address
                    });
                    $('#avatarPreview').attr('src', user.avatar_url || user.avatar || '/images/default-avatar.svg');
                    form.render();
                    CrmLang.switchUI();
                }
            }
        });
    }

    // Avatar upload
    upload.render({
        elem: '#uploadAvatar',
        auto: false,
        accept: 'images',
        exts: 'jpg|jpeg|png|gif|webp',
        choose: function(obj) {
            var files = obj.pushFile();
            var keys = Object.keys(files);
            selectedAvatar = keys.length ? files[keys[0]] : null;

            if (!selectedAvatar) {
                return;
            }

            obj.preview(function(index, file, result) {
                $('#avatarPreview').attr('src', result);
            });
        }
    });

    $('#submitAvatar').on('click', function() {
        if (!selectedAvatar) {
            layer.msg(requiredTemplateMessage(translateOr('profile.uploadAvatar', '头像上传'), translateOr('front.avatar', '头像')), {icon: 2});
            return;
        }

        var formData = new FormData();
        var loadIdx = layer.load(1);
        formData.append('avatar', selectedAvatar);

        CrmAjax.upload({
            guard: 'front',
            url: '/api/front/uploadAvatar',
            formData: formData,
            success: function(res) {
                layer.close(loadIdx);
                if (res.code === 1000 || res.code === 2000) {
                    $('#avatarPreview').attr('src', res.data.url);
                    selectedAvatar = null;
                    layer.msg(CrmLang.t('common.success'), {icon: 1});
                } else {
                    layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                }
            },
            error: function() {
                layer.close(loadIdx);
                layer.msg(CrmLang.t('common.error'), {icon: 2});
            }
        });
    });

    // Profile form submit
    form.on('submit(profileSubmit)', function(data) {
        var payload = $.extend({}, data.field);
        if (!$.trim(payload.phone || '') || payload.phone.indexOf('*') !== -1) {
            delete payload.phone;
        }
        var loadIdx = layer.load(1);
        CrmAjax.request({
            guard: 'front',
            url: '/api/front/updateProfile',
            method: 'POST',
            data: payload,
            success: function(res) {
                layer.close(loadIdx);
                if (res.code === 1000 || res.code === 2000) {
                    layer.msg(CrmLang.t('profile.saveSuccess'), {icon: 1});
                } else {
                    layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                }
            },
            error: function() {
                layer.close(loadIdx);
                layer.msg(CrmLang.t('common.error'), {icon: 2});
            }
        });
        return false;
    });
});
