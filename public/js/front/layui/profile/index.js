/**
 * 前台 Layui 资料页交互脚本。
 *
 * 这个文件把头像、资料、密码、邮箱、手机号、实名和银行卡验证
 * 统一放在一起，方便每个提交入口只校验自己对应卡片里的字段。
 */
layui.use(['form', 'layer', 'jquery', 'upload'], function() {
    var form = layui.form;
    var layer = layui.layer;
    var $ = layui.jquery;
    var upload = layui.upload;
    var uploadFiles = {};

    CrmLang.switchUI();
    loadProfileInfo();
    bindPreviewUpload('#selectAvatar', '#avatarPreview', 'avatar');
    bindPreviewUpload('#idCardFrontBtn', '#idCardFrontPreview', 'id_card_front');
    bindPreviewUpload('#idCardBackBtn', '#idCardBackPreview', 'id_card_back');
    bindPreviewUpload('#bankCardImgBtn', '#bankCardImgPreview', 'bank_card_img');
    bindPreviewUpload('#bankCardBackImgBtn', '#bankCardBackImgPreview', 'bank_card_img_back');
    bindPreviewUpload('#bankChangeCardImgBtn', '#bankChangeCardImgPreview', 'bank_change_card_img');
    bindPreviewUpload('#bankChangeCardBackImgBtn', '#bankChangeCardBackImgPreview', 'bank_change_card_img_back');

    form.verify({
        password: function(value) {
            if (value.length < 6) {
                return CrmLang.t('register.passwordRule');
            }
        },
        confirmPass: function(value) {
            if (value !== $('#new_password').val()) {
                return CrmLang.t('register.passwordMismatch');
            }
        },
        profileRequired: function(value, elem) {
            if (!$.trim(value || '')) {
                return requiredMessage(elem);
            }
        }
    });

    $('#submitAvatar').on('click', function() {
        if (!uploadFiles.avatar) {
            layer.msg(requiredTemplateMessage(translateOr('front.avatar_upload', '头像上传'), translateOr('front.avatar', '头像')), {icon: 2});
            return;
        }

        var formData = new FormData();
        formData.append('avatar', uploadFiles.avatar);

        var loadIdx = layer.load(1);
        CrmAjax.upload({
            guard: 'front',
            url: '/api/front/uploadAvatar',
            formData: formData,
            success: function(res) {
                layer.close(loadIdx);
                if (res.code === 1000 || res.code === 1004 || res.code === 2000) {
                    $('#avatarPreview').attr('src', (res.data && res.data.url) || '/images/default-avatar.svg');
                    layer.msg(CrmLang.t('profile.saveSuccess'), {icon: 1});
                    loadProfileInfo();
                    notifyParentAvatar((res.data && res.data.url) || '');
                    delete uploadFiles.avatar;
                    return;
                }
                layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
            },
            error: function(res) {
                layer.close(loadIdx);
                layer.msg((res && res.message) || CrmLang.t('common.error'), {icon: 2});
            }
        });

    });

    form.on('submit(profileSubmit)', function(data) {
        var payload = $.extend({}, data.field);
        if (!$.trim(payload.phone || '')) {
            delete payload.phone;
        }
        if (!$.trim(payload.id_card_no || '')) {
            delete payload.id_card_no;
        }

        var loadIdx = layer.load(1);
        CrmAjax.request({
            guard: 'front',
            url: '/api/front/updateProfile',
            data: payload,
            success: function(res) {
                layer.close(loadIdx);
                if (res.code === 1000 || res.code === 1002 || res.code === 2000) {
                    layer.msg(res.message || CrmLang.t('profile.saveSuccess'), {icon: 1});
                    loadProfileInfo();
                    return;
                }
                layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
            },
            error: function(res) {
                layer.close(loadIdx);
                layer.msg((res && res.message) || CrmLang.t('common.error'), {icon: 2});
            }
        });
        return false;
    });

    form.on('submit(passwordSubmit)', function(data) {
        var loadIdx = layer.load(1);
        CrmAjax.request({
            guard: 'front',
            url: '/api/front/changePassword',
            data: data.field,
            success: function(res) {
                layer.close(loadIdx);
                if (res.code === 1000 || res.code === 1002 || res.code === 2000) {
                    layer.msg(res.message || CrmLang.t('profile.passwordChanged'), {icon: 1});
                    CrmAjax.removeToken('front');
                    setTimeout(function() {
                        window.location.href = '/front/login';
                    }, 1200);
                    return;
                }
                layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
            },
            error: function(res) {
                layer.close(loadIdx);
                layer.msg((res && res.message) || CrmLang.t('common.error'), {icon: 2});
            }
        });
        return false;
    });

    form.on('submit(emailSubmit)', function(data) {
        var loadIdx = layer.load(1);
        CrmAjax.request({
            guard: 'front',
            url: '/api/front/changeEmail',
            data: data.field,
            success: function(res) {
                layer.close(loadIdx);
                if (res.code === 1000 || res.code === 1002 || res.code === 2000) {
                    layer.msg(res.message || CrmLang.t('profile.emailChanged'), {icon: 1});
                    $('[lay-filter="emailForm"]')[0].reset();
                    loadProfileInfo();
                    return;
                }
                layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
            },
            error: function(res) {
                layer.close(loadIdx);
                layer.msg((res && res.message) || CrmLang.t('common.error'), {icon: 2});
            }
        });
        return false;
    });

    form.on('submit(phoneSubmit)', function(data) {
        submitJson('/api/front/changePhone', data.field, function() {
            layer.msg(CrmLang.t('profile.phoneChanged'), {icon: 1});
            $('[lay-filter="phoneForm"]')[0].reset();
            loadProfileInfo();
        });
        return false;
    });

    form.on('submit(identitySubmit)', function(data) {
        if (!validateRequired($(data.form), {
            id_card_front: 'profile.idCardFront',
            id_card_back: 'profile.idCardBack'
        })) {
            return false;
        }
        submitMultipart('/api/front/submitIdentity', data.form, {
            id_card_front: 'id_card_front',
            id_card_back: 'id_card_back'
        }, function() {
            layer.msg(CrmLang.t('profile.saveSuccess'), {icon: 1});
            data.form.reset();
            clearUploadPreview(['id_card_front', 'id_card_back']);
            loadProfileInfo();
        });
        return false;
    });

    form.on('submit(bankSubmit)', function(data) {
        if (!validateRequired($(data.form), {
            bank_card_img: 'profile.bankCardFront',
            bank_card_img_back: 'profile.bankCardBack'
        })) {
            return false;
        }
        submitMultipart('/api/front/submitBankCard', data.form, {
            bank_card_img: 'bank_card_img',
            bank_card_back_img: 'bank_card_img_back'
        }, function() {
            layer.msg(CrmLang.t('profile.saveSuccess'), {icon: 1});
            data.form.reset();
            clearUploadPreview(['bank_card_img', 'bank_card_img_back']);
            loadProfileInfo();
        });
        return false;
    });

    form.on('submit(bankChangeSubmit)', function(data) {
        if (!validateRequired($(data.form), {
            bank_change_card_img: 'profile.bankCardFront',
            bank_change_card_img_back: 'profile.bankCardBack'
        })) {
            return false;
        }
        submitMultipart('/api/front/submitBankChange', data.form, {
            bank_card_img: 'bank_change_card_img',
            bank_card_back_img: 'bank_change_card_img_back'
        }, function() {
            layer.msg(CrmLang.t('profile.saveSuccess'), {icon: 1});
            data.form.reset();
            clearUploadPreview(['bank_change_card_img', 'bank_change_card_img_back']);
            loadProfileInfo();
        });
        return false;
    });

    function submitJson(url, payload, done) {
        var loadIdx = layer.load(1);

        CrmAjax.request({
            guard: 'front',
            url: url,
            data: payload,
            success: function(res) {
                layer.close(loadIdx);
                if (res.code === 1000 || res.code === 1002 || res.code === 2000) {
                    if (done) done(res);
                    return;
                }
                layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
            },
            error: function(res) {
                layer.close(loadIdx);
                layer.msg((res && res.message) || CrmLang.t('common.error'), {icon: 2});
            }
        });
    }

    function translateOr(key, fallback) {
        var value = CrmLang.t(key);
        return value && value !== key ? value : fallback;
    }

    function requiredTemplateMessage(formTitle, fieldTitle) {
        var template = translateOr('front.profile_required_message', '请填写【{form}】的【{field}】');
        return template
            .replace('{form}', $.trim(formTitle || translateOr('front.profile', '个人中心')))
            .replace('{field}', $.trim(fieldTitle || ''));
    }

    function formTitle(elemOrForm) {
        var $form = $(elemOrForm).is('form') ? $(elemOrForm) : $(elemOrForm).closest('form');
        var $title = $form.closest('.layui-card-body').find('.profile-section-title').first().clone();

        $title.find('.layui-badge').remove();
        return $.trim($title.text()) || translateOr('front.profile', '个人中心');
    }

    function requiredMessage(elem) {
        var label = $(elem).closest('.layui-form-item').find('.layui-form-label').first().text() || $(elem).attr('name') || '';
        return requiredTemplateMessage(formTitle(elem), label);
    }

    function uploadRequiredMessage(labelKey, formEl) {
        return requiredTemplateMessage(formTitle(formEl), CrmLang.t(labelKey));
    }

    // 把缓存字段映射到用户能看懂的上传文案，保证校验提示能精确指向
    // 对应按钮，不会让错误信息和实际操作脱节。
    function uploadLabelKey(fieldName) {
        var labels = {
            id_card_front: 'profile.idCardFront',
            id_card_back: 'profile.idCardBack',
            bank_card_img: 'profile.bankCardFront',
            bank_card_img_back: 'profile.bankCardBack',
            bank_change_card_img: 'profile.bankCardFront',
            bank_change_card_img_back: 'profile.bankCardBack'
        };

        return labels[fieldName] || fieldName;
    }

    function validateRequired($form, fileMap) {
        var valid = true;
        $form.find('[lay-verify*="required"],[lay-verify*="profileRequired"]').each(function () {
            if (!$.trim($(this).val() || '')) {
                layer.msg(requiredMessage(this), {icon: 2});
                this.focus();
                valid = false;
                return false;
            }
        });
        if (!valid) {
            return false;
        }
        $.each(fileMap || {}, function (fieldName, labelKey) {
            if (!uploadFiles[fieldName]) {
                layer.msg(uploadRequiredMessage(labelKey, $form), {icon: 2});
                valid = false;
                return false;
            }
        });
        return valid;
    }

    // 把已选预览文件按后端字段名塞进 FormData。通过这层映射，同一
    // 个按钮可以对应不同的上传接口。
    function submitMultipart(url, formEl, fileMap, done) {
        var loadIdx = layer.load(1);
        var formData = new FormData(formEl);
        var requestField;
        var cacheField;

        fileMap = fileMap || {};
        for (requestField in fileMap) {
            if (Object.prototype.hasOwnProperty.call(fileMap, requestField)) {
                cacheField = fileMap[requestField];
                if (!uploadFiles[cacheField]) {
                    layer.close(loadIdx);
                    layer.msg(uploadRequiredMessage(uploadLabelKey(cacheField), formEl), {icon: 2});
                    return;
                }
                formData.append(requestField, uploadFiles[cacheField]);
            }
        }

        CrmAjax.upload({
            guard: 'front',
            url: url,
            formData: formData,
            success: function(res) {
                layer.close(loadIdx);
                if (res.code === 1000 || res.code === 1002 || res.code === 2000) {
                    if (done) done(res);
                    return;
                }
                layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
            },
            error: function(res) {
                layer.close(loadIdx);
                layer.msg((res && res.message) || CrmLang.t('common.error'), {icon: 2});
            }
        });
    }

    function notifyParentAvatar(url) {
        if (!url || !window.parent || window.parent === window) {
            return;
        }

        window.parent.postMessage({
            type: 'crm:avatar-updated',
            url: url
        }, window.location.origin);
    }

    function loadProfileInfo() {
        CrmAjax.request({
            guard: 'front',
            url: '/api/front/profileInfo',
            success: function(res) {
                if (res.code !== 1000 && res.code !== 2000 && res.code !== 3000) {
                    layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                    return;
                }

                var info = res.data.info || {};
                var login = res.data.login || {};
                var auth = res.data.auth || {};
                var avatar = info.avatar_url || info.avatar || '/images/default-avatar.svg';

                $('#profileName').text(info.user_name || login.email || '-');
                $('#avatarPreview').attr('src', avatar);
                $('#profileUserId').text(info.user_id || login.user_id || '-');
                $('#profilePhoneMasked').text(info.phone_masked || info.phone || '-');
                $('#profileEmailMasked').text(login.email_masked || login.email || info.email || '-');
                $('#profileIdCardMasked').text(auth.id_card_no_masked || info.id_card_no_masked || info.id_card_no || '-');
                $('#profilePhoneReadonly').val(info.phone_masked || '-');
                $('#profileIdCardReadonly').val(auth.id_card_no_masked || info.id_card_no_masked || '-');
                $('#idCardStatusText').text((auth && auth.id_card_status_text) || '-');
                $('#bankStatusText').text((auth && auth.bank_status_text) || '-');

                form.val('profileForm', {
                    user_name: info.user_name || '',
                    gender: info.gender ? String(info.gender) : '1',
                    address: info.address || ''
                });

                form.val('identityForm', {id_card_no: ''});
                $('[lay-filter="identityForm"] input[name="id_card_no"]').attr('placeholder', auth.id_card_no_masked || CrmLang.t('profile.fullIdCardPlaceholder'));
                form.val('bankForm', {
                    bank_name: auth.bank_name || '',
                    bank_no: '',
                    bank_addr: auth.bank_addr || ''
                });
                $('[lay-filter="bankForm"] input[name="bank_no"]').attr('placeholder', auth.bank_no_masked || '');
                CrmLang.switchUI();
                form.render();
            }
        });
    }

    function bindPreviewUpload(elem, preview, fieldName) {
        upload.render({
            elem: elem,
            auto: false,
            accept: 'images',
            exts: 'jpg|jpeg|png|gif|webp',
            size: 4096,
            drag: true,
            choose: function(obj) {
                var files = obj.pushFile();
                var keys = Object.keys(files);
                var file = keys.length ? files[keys[0]] : null;

                if (!file) {
                    return;
                }

                uploadFiles[fieldName] = file;
                obj.preview(function(index, selectedFile, result) {
                    $(preview).attr('src', result).show();
                });
            }
        });
    }

    function clearUploadPreview(fields) {
        $.each(fields || [], function(_, fieldName) {
            delete uploadFiles[fieldName];
        });
        $('#idCardFrontPreview, #idCardBackPreview, #bankCardImgPreview, #bankCardBackImgPreview, #bankChangeCardImgPreview, #bankChangeCardBackImgPreview').hide().attr('src', '');
    }
});
