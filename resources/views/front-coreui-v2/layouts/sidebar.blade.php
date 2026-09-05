<div class="sidebar">
    <!-- Logo -->
    <div class="d-flex align-items-center justify-content-center border-bottom" style="height: 56px;">
        <div class="d-flex align-items-center gap-2">
            <div style="width: 36px; height: 36px; background: linear-gradient(135deg, #321fdb, #5856d6); border-radius: 8px;" class="d-flex align-items-center justify-center">
                <i class="fas fa-chart-line text-white"></i>
            </div>
            <span class="fw-bold fs-5 text-dark">CRM 系统</span>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="mt-3">
        <!-- 仪表盘 -->
        <a href="{{ route('front_coreui_v2_page_dashboard') }}"
           class="nav-link d-flex align-items-center gap-3 px-4 py-2 text-decoration-none {{ request()->routeIs('front_coreui_v2_page_dashboard') ? 'bg-primary text-white' : 'text-dark' }}">
            <i class="fas fa-home" style="width: 20px;"></i>
            <span>仪表盘</span>
        </a>

        <!-- 账户管理 -->
        <div class="nav-group mt-2">
            <div class="px-4 py-2 text-muted small fw-semibold text-uppercase">账户管理</div>
            <a href="{{ route('front_coreui_v2_page_account_info') }}"
               class="nav-link d-flex align-items-center gap-3 px-4 py-2 text-decoration-none {{ request()->routeIs('front_coreui_v2_page_account_info') ? 'border-start border-primary border-3 bg-light text-primary' : 'text-dark' }}">
                <i class="fas fa-user" style="width: 20px;"></i>
                <span>账户信息</span>
            </a>
            <a href="{{ route('front_coreui_v2_page_account_voucher') }}"
               class="nav-link d-flex align-items-center gap-3 px-4 py-2 text-decoration-none {{ request()->routeIs('front_coreui_v2_page_account_voucher') ? 'border-start border-primary border-3 bg-light text-primary' : 'text-dark' }}">
                <i class="fas fa-image" style="width: 20px;"></i>
                <span>凭证管理</span>
            </a>
        </div>

        <!-- 入出金 -->
        <div class="nav-group mt-2">
            <div class="px-4 py-2 text-muted small fw-semibold text-uppercase">入出金</div>
            <a href="{{ route('front_coreui_v2_page_deposit') }}"
               class="nav-link d-flex align-items-center gap-3 px-4 py-2 text-decoration-none {{ request()->routeIs('front_coreui_v2_page_deposit') ? 'border-start border-success border-3 bg-light text-success' : 'text-dark' }}">
                <i class="fas fa-arrow-down" style="width: 20px;"></i>
                <span>在线入金</span>
            </a>
            <a href="{{ route('front_coreui_v2_page_withdraw') }}"
               class="nav-link d-flex align-items-center gap-3 px-4 py-2 text-decoration-none {{ request()->routeIs('front_coreui_v2_page_withdraw') ? 'border-start border-warning border-3 bg-light text-warning' : 'text-dark' }}">
                <i class="fas fa-arrow-up" style="width: 20px;"></i>
                <span>在线出金</span>
            </a>
            <a href="#" class="nav-link d-flex align-items-center gap-3 px-4 py-2 text-decoration-none text-dark">
                <i class="fas fa-list" style="width: 20px;"></i>
                <span>流水记录</span>
            </a>
        </div>

        <!-- 交易管理 -->
        <div class="nav-group mt-2">
            <div class="px-4 py-2 text-muted small fw-semibold text-uppercase">交易管理</div>
            <a href="{{ route('front_coreui_v2_page_position_summary') }}"
               class="nav-link d-flex align-items-center gap-3 px-4 py-2 text-decoration-none {{ request()->routeIs('front_coreui_v2_page_position_summary') ? 'border-start border-info border-3 bg-light text-info' : 'text-dark' }}">
                <i class="fas fa-chart-pie" style="width: 20px;"></i>
                <span>持仓汇总</span>
            </a>
            <a href="{{ route('front_coreui_v2_page_order_open') }}"
               class="nav-link d-flex align-items-center gap-3 px-4 py-2 text-decoration-none {{ request()->routeIs('front_coreui_v2_page_order_open') ? 'border-start border-info border-3 bg-light text-info' : 'text-dark' }}">
                <i class="fas fa-folder-open" style="width: 20px;"></i>
                <span>开仓订单</span>
            </a>
            <a href="{{ route('front_coreui_v2_page_order_closed') }}"
               class="nav-link d-flex align-items-center gap-3 px-4 py-2 text-decoration-none {{ request()->routeIs('front_coreui_v2_page_order_closed') ? 'border-start border-info border-3 bg-light text-info' : 'text-dark' }}">
                <i class="fas fa-folder" style="width: 20px;"></i>
                <span>平仓订单</span>
            </a>
        </div>

        <!-- 代理功能 -->
        <div class="nav-group mt-2">
            <div class="px-4 py-2 text-muted small fw-semibold text-uppercase">代理功能</div>
            <a href="{{ route('front_coreui_v2_page_agent_sub') }}"
               class="nav-link d-flex align-items-center gap-3 px-4 py-2 text-decoration-none {{ request()->routeIs('front_coreui_v2_page_agent_sub') ? 'border-start border-secondary border-3 bg-light text-secondary' : 'text-dark' }}">
                <i class="fas fa-users" style="width: 20px;"></i>
                <span>下级代理</span>
            </a>
            <a href="{{ route('front_coreui_v2_page_agent_customers') }}"
               class="nav-link d-flex align-items-center gap-3 px-4 py-2 text-decoration-none {{ request()->routeIs('front_coreui_v2_page_agent_customers') ? 'border-start border-secondary border-3 bg-light text-secondary' : 'text-dark' }}">
                <i class="fas fa-user-friends" style="width: 20px;"></i>
                <span>客户管理</span>
            </a>
            <a href="{{ route('front_coreui_v2_page_commission_realtime') }}"
               class="nav-link d-flex align-items-center gap-3 px-4 py-2 text-decoration-none {{ request()->routeIs('front_coreui_v2_page_commission_realtime') ? 'border-start border-secondary border-3 bg-light text-secondary' : 'text-dark' }}">
                <i class="fas fa-dollar-sign" style="width: 20px;"></i>
                <span>实时返佣</span>
            </a>
        </div>

        <!-- 系统 -->
        <div class="nav-group mt-2 mb-4">
            <div class="px-4 py-2 text-muted small fw-semibold text-uppercase">系统</div>
            <a href="#" class="nav-link d-flex align-items-center gap-3 px-4 py-2 text-decoration-none text-dark">
                <i class="fas fa-bell" style="width: 20px;"></i>
                <span>消息通知</span>
            </a>
            <a href="#" class="nav-link d-flex align-items-center gap-3 px-4 py-2 text-decoration-none text-dark">
                <i class="fas fa-cog" style="width: 20px;"></i>
                <span>个人设置</span>
            </a>
        </div>
    </nav>
</div>

<style>
.nav-link:hover {
    background-color: #f8f9fa;
}
.nav-link.bg-primary:hover {
    background-color: var(--cui-primary) !important;
}
</style>
