<header class="header d-flex align-items-center justify-content-between px-4">
    <!-- Left: Menu Toggle (Mobile) -->
    <button class="btn btn-link text-dark d-lg-none" type="button" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Center: Search (Desktop) -->
    <div class="flex-grow-1 mx-4 d-none d-md-block" style="max-width: 600px;">
        <div class="input-group">
            <span class="input-group-text bg-white border-end-0">
                <i class="fas fa-search text-muted"></i>
            </span>
            <input type="text" class="form-control border-start-0 ps-0" placeholder="搜索订单、账户、交易记录...">
        </div>
    </div>

    <!-- Right: User Actions -->
    <div class="d-flex align-items-center gap-3">
        <!-- Notifications -->
        <div class="dropdown">
            <button class="btn btn-link text-dark position-relative" type="button" data-bs-toggle="dropdown">
                <i class="fas fa-bell fs-5"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;">
                    3
                </span>
            </button>
            <div class="dropdown-menu dropdown-menu-end shadow border-0" style="width: 320px;">
                <div class="px-3 py-2 border-bottom">
                    <h6 class="mb-0">通知消息</h6>
                </div>
                <div class="list-group list-group-flush">
                    <a href="#" class="list-group-item list-group-item-action">
                        <div class="d-flex gap-3">
                            <div class="flex-shrink-0">
                                <div class="rounded-circle bg-success bg-opacity-10 p-2" style="width: 40px; height: 40px;">
                                    <i class="fas fa-check text-success"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <p class="mb-1 fw-semibold">入金成功</p>
                                <p class="mb-1 small text-muted">您的入金申请已处理完成</p>
                                <p class="mb-0 small text-muted">5分钟前</p>
                            </div>
                        </div>
                    </a>
                    <a href="#" class="list-group-item list-group-item-action">
                        <div class="d-flex gap-3">
                            <div class="flex-shrink-0">
                                <div class="rounded-circle bg-warning bg-opacity-10 p-2" style="width: 40px; height: 40px;">
                                    <i class="fas fa-exclamation-triangle text-warning"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <p class="mb-1 fw-semibold">风控提醒</p>
                                <p class="mb-1 small text-muted">您的账户保证金率低于50%</p>
                                <p class="mb-0 small text-muted">1小时前</p>
                            </div>
                        </div>
                    </a>
                    <a href="#" class="list-group-item list-group-item-action">
                        <div class="d-flex gap-3">
                            <div class="flex-shrink-0">
                                <div class="rounded-circle bg-info bg-opacity-10 p-2" style="width: 40px; height: 40px;">
                                    <i class="fas fa-info-circle text-info"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <p class="mb-1 fw-semibold">系统公告</p>
                                <p class="mb-1 small text-muted">系统将于今晚维护更新</p>
                                <p class="mb-0 small text-muted">3小时前</p>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="px-3 py-2 border-top text-center">
                    <a href="#" class="text-decoration-none small">查看全部通知</a>
                </div>
            </div>
        </div>

        <!-- User Menu -->
        <div class="dropdown">
            <button class="btn btn-link text-dark d-flex align-items-center gap-2 text-decoration-none" type="button" data-bs-toggle="dropdown">
                <div class="d-none d-md-block text-end me-2">
                    <div class="fw-semibold small">张三</div>
                    <div class="text-muted" style="font-size: 0.75rem;">MT4: 12345678</div>
                </div>
                <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #321fdb, #5856d6); border-radius: 50%;" class="d-flex align-items-center justify-content-center">
                    <span class="text-white fw-bold">张</span>
                </div>
            </button>
            <div class="dropdown-menu dropdown-menu-end shadow border-0">
                <a class="dropdown-item" href="{{ route('front_coreui_v2_page_account_info') }}">
                    <i class="fas fa-user w-25px"></i> 个人信息
                </a>
                <a class="dropdown-item" href="{{ route('front_coreui_v2_page_account_voucher') }}">
                    <i class="fas fa-id-card w-25px"></i> 实名认证
                </a>
                <a class="dropdown-item" href="#">
                    <i class="fas fa-key w-25px"></i> 修改密码
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item text-danger" href="#">
                    <i class="fas fa-sign-out-alt w-25px"></i> 退出登录
                </a>
            </div>
        </div>
    </div>
</header>

<style>
.w-25px {
    display: inline-block;
    width: 25px;
    text-align: center;
}
</style>
