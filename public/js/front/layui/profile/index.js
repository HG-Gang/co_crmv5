layui.use(['form', 'layer', 'jquery', 'upload'], function() {
    var form = layui.form;
    var layer = layui.layer;
    var $ = layui.jquery;
    var upload = layui.upload;
    var uploadFiles = {};

    CrmLang.switchUI();
    loadProfileInfo();
    bindPreviewUpload('#selectAvatar', '#avatarPreview', 'avatar');
    bindPreviewUpload('#idCardFrontBtn', '#idCardFrontPreview', 'id_card_front', '#idCardFrontName');
    bindPreviewUpload('#idCardBackBtn', '#idCardBackPreview', 'id_card_back', '#idCardBackName');
    bindPreviewUpload('#bankCardFrontBtn', '#bankCardFrontPreview', 'bank_card_img', '#bankCardFrontName');
    bindPreviewUpload('#bankCardBackBtn', '#bankCardBackPreview', 'bank_card_back_img', '#bankCardBackName');
    bindPreviewUpload('#bankChangeCardFrontBtn', '#bankChangeCardFrontPreview', 'bank_change_card_img', '#bankChangeCardFrontName');
    bindPreviewUpload('#bankChangeCardBackBtn', '#bankChangeCardBackPreview', 'bank_change_card_back_img', '#bankChangeCardBackName');

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
        }
    });

    $('#submitAvatar').on('click', function() {
        if (!uploadFiles.avatar) {
            layer.msg(CrmLang.t('common.error'), {icon: 2});
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
        submitMultipart('/api/front/submitBankCard', data.form, {
            bank_card_img: 'bank_card_img',
            bank_card_back_img: 'bank_card_back_img'
        }, function() {
            layer.msg(CrmLang.t('profile.saveSuccess'), {icon: 1});
            data.form.reset();
            clearUploadPreview(['bank_card_img', 'bank_card_back_img']);
            loadProfileInfo();
        });
        return false;
    });

    form.on('submit(bankChangeSubmit)', function(data) {
        submitMultipart('/api/front/submitBankChange', data.form, {
            bank_card_img: 'bank_change_card_img',
            bank_card_back_img: 'bank_change_card_back_img'
        }, function() {
            layer.msg(CrmLang.t('profile.saveSuccess'), {icon: 1});
            data.form.reset();
            clearUploadPreview(['bank_change_card_img', 'bank_change_card_back_img']);
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
                    layer.msg(CrmLang.t('profile.uploadRequired') || CrmLang.t('common.error'), {icon: 2});
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

    function bindPreviewUpload(elem, preview, fieldName, nameElem) {
        upload.render({
            elem: elem,
            auto: false,
            accept: 'images',
            exts: 'jpg|jpeg|png|gif|webp',
            choose: function(obj) {
                var files = obj.pushFile();
                var keys = Object.keys(files);
                var file = keys.length ? files[keys[0]] : null;

                if (!file) {
                    return;
                }

                uploadFiles[fieldName] = file;
                $(elem).addClass('is-ready');
                $(nameElem).text(file.name || CrmLang.t('profile.uploadReady') || '').removeAttr('data-translate');
                obj.preview(function(index, selectedFile, result) {
                    $(preview).attr('src', result).addClass('has-src').show();
                });
            }
        });
    }

    function clearUploadPreview(fields) {
        $.each(fields || [], function(_, fieldName) {
            delete uploadFiles[fieldName];
        });
        var previewMap = {
            id_card_front: '#idCardFrontPreview',
            id_card_back: '#idCardBackPreview',
            bank_card_img: '#bankCardFrontPreview',
            bank_card_back_img: '#bankCardBackPreview',
            bank_change_card_img: '#bankChangeCardFrontPreview',
            bank_change_card_back_img: '#bankChangeCardBackPreview'
        };
        var nameMap = {
            id_card_front: '#idCardFrontName',
            id_card_back: '#idCardBackName',
            bank_card_img: '#bankCardFrontName',
            bank_card_back_img: '#bankCardBackName',
            bank_change_card_img: '#bankChangeCardFrontName',
            bank_change_card_back_img: '#bankChangeCardBackName'
        };
        $.each(fields || [], function(_, fieldName) {
            $(previewMap[fieldName]).hide().removeClass('has-src').attr('src', '');
            $(nameMap[fieldName]).text(CrmLang.t('profile.no_file_selected')).attr('data-translate', 'profile.no_file_selected');
        });
        $('.profile-upload-card').removeClass('is-ready');
    }
});
