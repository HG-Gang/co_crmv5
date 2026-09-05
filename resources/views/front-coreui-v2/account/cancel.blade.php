@extends('front-coreui-v2.layouts.app')

@section('title', '注销账户')

@section('content')
<div class="container-lg px-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('front_coreui_v2_page_profile') }}">个人资料</a></li>
                    <li class="breadcrumb-item active">注销账户</li>
                </ol>
            </nav>
            <h2 class="mb-2 text-danger">
                <i class="cil-warning me-2"></i>注销账户
            </h2>
            <p class="text-body-secondary mb-0">此操作不可逆，请谨慎操作</p>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8 mx-auto">
            <!-- Warning Alert -->
            <div class="alert alert-danger border-0 mb-4">
                <h5 class="alert-heading">
                    <i class="cil-bell me-2"></i>重要提示
                </h5>
                <p class="mb-2">账户注销后将产生以下影响：</p>
                <ul class="mb-0">
                    <li>您的所有账户数据将被永久删除，无法恢复</li>
                    <li>所有MT4交易账户将被关闭</li>
                    <li>账户余额和未结算佣金将被清零</li>
                    <li>您将无法再使用该账户登录</li>
                    <li>历史交易记录和报表将无法查询</li>
                </ul>
            </div>

            <!-- Prerequisites Check -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-bottom">
                    <h5 class="mb-0">
                        <i class="cil-task me-2"></i>注销前置条件检查
                    </h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <div>
                                <i class="cil-wallet me-2"></i>账户余额
                                <p class="mb-0 small text-body-secondary">账户余额必须为0才能注销</p>
                            </div>
                            <span id="checkBalance">
                                <div class="spinner-border spinner-border-sm text-primary"></div>
                            </span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <div>
                                <i class="cil-chart-line me-2"></i>持仓订单
                                <p class="mb-0 small text-body-secondary">不能有未平仓的订单</p>
                            </div>
                            <span id="checkPositions">
                                <div class="spinner-border spinner-border-sm text-primary"></div>
                            </span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <div>
                                <i class="cil-credit-card me-2"></i>出金申请
                                <p class="mb-0 small text-body-secondary">不能有待处理的出金申请</p>
                            </div>
                            <span id="checkWithdrawals">
                                <div class="spinner-border spinner-border-sm text-primary"></div>
                            </span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <div>
                                <i class="cil-people me-2"></i>代理关系
                                <p class="mb-0 small text-body-secondary">不能有下级代理或客户</p>
                            </div>
                            <span id="checkAgents">
                                <div class="spinner-border spinner-border-sm text-primary"></div>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cancel Form -->
            <div class="card shadow-sm border-0 mb-4" id="cancelForm" style="display: none;">
                <div class="card-header bg-transparent border-bottom">
                    <h5 class="mb-0">
                        <i class="cil-comment-square me-2"></i>注销申请表单
                    </h5>
                </div>
                <div class="card-body">
                    <form id="submitCancelForm">
                        <div class="mb-4">
                            <label class="form-label">注销原因 <span class="text-danger">*</span></label>
                            <select id="cancelReason" class="form-select" required>
                                <option value="">请选择注销原因</option>
                                <option value="no_need">不再需要使用</option>
                                <option value="switch_platform">转换其他平台</option>
                                <option value="security">安全隐私考虑</option>
                                <option value="service">对服务不满意</option>
                                <option value="other">其他原因</option>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">详细说明</label>
                            <textarea id="cancelDescription" class="form-control" rows="4" placeholder="请详细描述您的注销原因（选填）"></textarea>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">验证密码 <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="cil-lock-locked"></i></span>
                                <input type="password" id="password" class="form-control" placeholder="请输入登录密码以确认身份" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword()">
                                    <i class="cil-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="confirmCheckbox" required>
                                <label class="form-check-label" for="confirmCheckbox">
                                    我已知晓并同意账户注销的所有后果，确认注销账户
                                </label>
                            </div>
                        </div>

                        <div class="alert alert-warning border-0 mb-4">
                            <i class="cil-info me-2"></i>
                            <strong>审核说明：</strong>提交注销申请后，我们将在1-3个工作日内完成审核。审核通过后，您的账户将被永久注销。
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-danger px-4">
                                <i class="cil-trash me-2"></i>确认注销
                            </button>
                            <a href="{{ route('front_coreui_v2_page_profile') }}" class="btn btn-outline-secondary px-4">
                                <i class="cil-arrow-left me-2"></i>返回
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Cannot Cancel Notice -->
            <div class="card shadow-sm border-0" id="cannotCancel" style="display: none;">
                <div class="card-body text-center py-5">
                    <i class="cil-x-circle text-danger" style="font-size: 4rem;"></i>
                    <h5 class="mt-3 mb-2">暂时无法注销账户</h5>
                    <p class="text-body-secondary mb-4" id="cannotCancelReason">请先处理上述未完成事项</p>
                    <a href="{{ route('front_coreui_v2_page_profile') }}" class="btn btn-outline-primary">
                        <i class="cil-arrow-left me-2"></i>返回个人资料
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    checkCancelConditions();

    document.getElementById('submitCancelForm').addEventListener('submit', function(e) {
        e.preventDefault();
        submitCancel();
    });
});

function checkCancelConditions() {
    fetch('{{ route("front_api_account_cancel_check") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            updateCheckResult('checkBalance', data.checks.balance_ok);
            updateCheckResult('checkPositions', data.checks.positions_ok);
            updateCheckResult('checkWithdrawals', data.checks.withdrawals_ok);
            updateCheckResult('checkAgents', data.checks.agents_ok);

            if (data.can_cancel) {
                document.getElementById('cancelForm').style.display = 'block';
                document.getElementById('cannotCancel').style.display = 'none';
            } else {
                document.getElementById('cancelForm').style.display = 'none';
                document.getElementById('cannotCancel').style.display = 'block';
                document.getElementById('cannotCancelReason').textContent = data.message || '请先处理上述未完成事项';
            }
        }
    })
    .catch(err => {
        console.error('Check conditions error:', err);
    });
}

function updateCheckResult(elementId, isPassed) {
    const element = document.getElementById(elementId);
    if (isPassed) {
        element.innerHTML = '<i class="cil-check-circle text-success" style="font-size: 1.5rem;"></i>';
    } else {
        element.innerHTML = '<i class="cil-x-circle text-danger" style="font-size: 1.5rem;"></i>';
    }
}

function togglePassword() {
    const field = document.getElementById('password');
    if (field.type === 'password') {
        field.type = 'text';
    } else {
        field.type = 'password';
    }
}

function submitCancel() {
    const reason = document.getElementById('cancelReason').value;
    const description = document.getElementById('cancelDescription').value.trim();
    const password = document.getElementById('password').value;
    const confirmed = document.getElementById('confirmCheckbox').checked;

    if (!reason) {
        alert('请选择注销原因');
        return;
    }

    if (!password) {
        alert('请输入登录密码');
        return;
    }

    if (!confirmed) {
        alert('请勾选确认条款');
        return;
    }

    if (!confirm('确定要注销账户吗？此操作不可撤销！')) {
        return;
    }

    const data = {
        reason: reason,
        description: description,
        password: password
    };

    fetch('{{ route("front_api_account_cancel_submit") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert('注销申请已提交，请等待审核');
            window.location.href = '{{ route("front_coreui_v2_page_profile") }}';
        } else {
            alert(data.message || '提交失败');
        }
    })
    .catch(err => {
        console.error('Submit cancel error:', err);
        alert('网络错误，请稍后重试');
    });
}
</script>
@endsection
