@extends('front-coreui-v2.layouts.app')

@section('title', '添加地址')

@section('content')
<div class="container-lg px-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('front_coreui_v2_page_gift_address') }}">礼品地址</a></li>
                    <li class="breadcrumb-item active">添加地址</li>
                </ol>
            </nav>
            <h2 class="mb-2">添加收货地址</h2>
            <p class="text-body-secondary mb-0">填写您的收货地址信息</p>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-gradient-primary text-white">
                    <h5 class="mb-0">
                        <i class="cil-location-pin me-2"></i>地址信息
                    </h5>
                </div>
                <div class="card-body">
                    <form id="addressForm">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">收货人姓名 <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="receiverName" placeholder="请输入收货人姓名">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">联系电话 <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="phone" placeholder="请输入联系电话">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">国家/地区 <span class="text-danger">*</span></label>
                                <select class="form-select" id="country" onchange="loadProvinces()">
                                    <option value="">请选择国家/地区</option>
                                    <option value="China">中国</option>
                                    <option value="USA">美国</option>
                                    <option value="UK">英国</option>
                                    <option value="Japan">日本</option>
                                    <option value="Other">其他</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">省份/州 <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="province" placeholder="请输入省份/州">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">城市 <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="city" placeholder="请输入城市">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">区/县</label>
                                <input type="text" class="form-control" id="district" placeholder="请输入区/县">
                            </div>
                            <div class="col-12">
                                <label class="form-label">详细地址 <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="address" rows="3" placeholder="请输入详细地址，如街道、门牌号等"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">邮政编码</label>
                                <input type="text" class="form-control" id="postalCode" placeholder="请输入邮政编码">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">&nbsp;</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="isDefault">
                                    <label class="form-check-label" for="isDefault">
                                        设为默认地址
                                    </label>
                                </div>
                            </div>
                            <div class="col-12">
                                <hr>
                            </div>
                            <div class="col-12">
                                <button type="button" class="btn btn-primary" onclick="submitAddress()">
                                    <i class="cil-check me-2"></i>保存地址
                                </button>
                                <a href="{{ route('front_coreui_v2_page_gift_address') }}" class="btn btn-secondary">
                                    <i class="cil-x me-2"></i>取消
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tips Card -->
            <div class="card shadow-sm border-0 mt-4">
                <div class="card-header bg-transparent border-bottom">
                    <h6 class="mb-0">
                        <i class="cil-lightbulb me-2"></i>温馨提示
                    </h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <i class="cil-check-circle text-success me-2"></i>
                            <small>请确保收货地址准确无误，以免影响礼品配送</small>
                        </li>
                        <li class="mb-2">
                            <i class="cil-check-circle text-success me-2"></i>
                            <small>联系电话请填写您常用的手机号码</small>
                        </li>
                        <li class="mb-2">
                            <i class="cil-check-circle text-success me-2"></i>
                            <small>您可以添加多个收货地址并设置默认地址</small>
                        </li>
                        <li>
                            <i class="cil-check-circle text-success me-2"></i>
                            <small>国际配送可能需要额外的清关信息</small>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function submitAddress() {
    const receiverName = document.getElementById('receiverName').value.trim();
    const phone = document.getElementById('phone').value.trim();
    const country = document.getElementById('country').value;
    const province = document.getElementById('province').value.trim();
    const city = document.getElementById('city').value.trim();
    const district = document.getElementById('district').value.trim();
    const address = document.getElementById('address').value.trim();
    const postalCode = document.getElementById('postalCode').value.trim();
    const isDefault = document.getElementById('isDefault').checked;

    if (!receiverName) {
        alert('请输入收货人姓名');
        return;
    }

    if (!phone) {
        alert('请输入联系电话');
        return;
    }

    if (!country) {
        alert('请选择国家/地区');
        return;
    }

    if (!province) {
        alert('请输入省份/州');
        return;
    }

    if (!city) {
        alert('请输入城市');
        return;
    }

    if (!address) {
        alert('请输入详细地址');
        return;
    }

    fetch('{{ route("front_api_gift_address_add") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            receiver_name: receiverName,
            phone: phone,
            country: country,
            province: province,
            city: city,
            district: district,
            address: address,
            postal_code: postalCode,
            is_default: isDefault ? 1 : 0
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert('地址添加成功');
            window.location.href = '{{ route("front_coreui_v2_page_gift_address") }}';
        } else {
            alert(data.message || '添加失败');
        }
    })
    .catch(err => {
        console.error('Submit error:', err);
        alert('网络错误，请稍后重试');
    });
}

function loadProvinces() {
    const country = document.getElementById('country').value;
    // 可以根据国家加载对应的省份列表
    console.log('Selected country:', country);
}
</script>

<style>
.bg-gradient-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
</style>
@endsection
