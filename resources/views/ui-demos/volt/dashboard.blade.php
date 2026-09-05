<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Volt Dashboard - 完整演示</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --volt-primary: #262b40;
            --volt-purple: #6f42c1;
            --volt-blue: #0d6efd;
            --volt-success: #00d4aa;
            --volt-warning: #ffc107;
            --volt-danger: #dc3545;
            --volt-light: #f5f8fb;
            --volt-sidebar: #262b40;
        }
        body {
            background: var(--volt-light);
            font-family: 'Nunito Sans', sans-serif;
        }
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 260px;
            height: 100vh;
            background: var(--volt-sidebar);
            z-index: 1000;
            overflow-y: auto;
        }
        .sidebar-brand {
            padding: 24px 20px;
            color: #fff;
            font-size: 1.375rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .sidebar-brand i {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--volt-purple), var(--volt-blue));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .sidebar-nav {
            padding: 0 16px 20px;
        }
        .nav-item {
            margin-bottom: 4px;
        }
        .nav-link {
            color: rgba(255,255,255,0.7);
            padding: 12px 16px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            text-decoration: none;
            transition: all 0.2s;
        }
        .nav-link:hover {
            background: rgba(255,255,255,0.05);
            color: #fff;
        }
        .nav-link.active {
            background: linear-gradient(135deg, var(--volt-purple), var(--volt-blue));
            color: #fff;
        }
        .nav-link i {
            width: 20px;
            margin-right: 12px;
        }
        .main-content {
            margin-left: 260px;
            padding: 28px;
        }
        .header-bar {
            background: #fff;
            padding: 20px 28px;
            margin: -28px -28px 28px -28px;
            border-bottom: 1px solid #e9ecef;
            border-radius: 0;
        }
        .card {
            border: 0;
            border-radius: 12px;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
            margin-bottom: 24px;
        }
        .card-body {
            padding: 24px;
        }
        .stat-card {
            background: #fff;
            padding: 24px;
            border-radius: 12px;
            position: relative;
            overflow: hidden;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
        }
        .stat-card.primary::before { background: var(--volt-purple); }
        .stat-card.success::before { background: var(--volt-success); }
        .stat-card.info::before { background: var(--volt-blue); }
        .stat-card.warning::before { background: var(--volt-warning); }
        .stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #fff;
            margin-bottom: 16px;
        }
        .stat-icon.primary { background: linear-gradient(135deg, var(--volt-purple), #8f5fe5); }
        .stat-icon.success { background: linear-gradient(135deg, var(--volt-success), #00f7c5); }
        .stat-icon.info { background: linear-gradient(135deg, var(--volt-blue), #3d8bfd); }
        .stat-icon.warning { background: linear-gradient(135deg, var(--volt-warning), #ffd43b); }
        .stat-value {
            font-size: 2.25rem;
            font-weight: 700;
            margin-bottom: 4px;
            line-height: 1;
        }
        .stat-label {
            color: #6c757d;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stat-trend {
            margin-top: 12px;
            font-size: 0.875rem;
        }
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            border: 0;
            transition: all 0.2s;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--volt-purple), var(--volt-blue));
            color: #fff;
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(111,66,193,0.3);
        }
        .btn-sm {
            padding: 6px 14px;
            font-size: 0.875rem;
        }
        .table {
            margin-bottom: 0;
        }
        .table thead th {
            border-bottom: 2px solid #e9ecef;
            padding: 14px 16px;
            font-size: 0.8125rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6c757d;
            background: #f8f9fa;
        }
        .table tbody td {
            padding: 16px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f3f5;
        }
        .table tbody tr:hover {
            background: #f8f9fa;
        }
        .badge {
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.75rem;
        }
        .badge-success { background: var(--volt-success); color: #fff; }
        .badge-warning { background: var(--volt-warning); color: #000; }
        .badge-primary { background: var(--volt-purple); color: #fff; }
        .badge-danger { background: var(--volt-danger); color: #fff; }
        .progress {
            height: 8px;
            border-radius: 999px;
            background: #e9ecef;
        }
        .progress-bar {
            border-radius: 999px;
        }
        .form-control {
            padding: 10px 16px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
        }
        .form-control:focus {
            border-color: var(--volt-purple);
            box-shadow: 0 0 0 3px rgba(111,66,193,0.1);
        }
        .chart-container {
            position: relative;
            height: 280px;
        }
        .upload-box {
            border: 2px dashed #dee2e6;
            border-radius: 12px;
            padding: 48px 24px;
            text-align: center;
            background: #f8f9fa;
            cursor: pointer;
            transition: all 0.3s;
        }
        .upload-box:hover {
            border-color: var(--volt-purple);
            background: #f5f8fb;
        }
        .back-link {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            background: linear-gradient(135deg, var(--volt-purple), var(--volt-blue));
            color: #fff;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(0,0,0,.15);
        }
        .back-link:hover {
            color: #fff;
            transform: translateY(-2px);
        }
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: none;
            z-index: 8000;
        }
        .modal-backdrop.show {
            display: block;
        }
        .modal-dialog {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #fff;
            border-radius: 12px;
            max-width: 540px;
            width: 90%;
            z-index: 9000;
            display: none;
        }
        .modal-dialog.show {
            display: block;
        }
        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e9ecef;
        }
        .modal-body {
            padding: 24px;
        }
        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid #e9ecef;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
    </style>
</head>
<body>
    <a href="/ui-demos" class="back-link"><i class="fas fa-arrow-left me-2"></i>返回导航</a>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <i class="fas fa-bolt"></i>
            <span>Volt Pro</span>
        </div>
        <ul class="sidebar-nav">
            <li class="nav-item"><a class="nav-link active" href="#"><i class="fas fa-home"></i>仪表盘</a></li>
            <li class="nav-item"><a class="nav-link" href="#"><i class="fas fa-chart-pie"></i>数据分析</a></li>
            <li class="nav-item"><a class="nav-link" href="#"><i class="fas fa-list"></i>数据列表</a></li>
            <li class="nav-item"><a class="nav-link" href="#"><i class="fas fa-edit"></i>表单管理</a></li>
            <li class="nav-item"><a class="nav-link" href="#"><i class="fas fa-upload"></i>文件上传</a></li>
            <li class="nav-item"><a class="nav-link" href="#"><i class="fas fa-users"></i>用户管理</a></li>
            <li class="nav-item"><a class="nav-link" href="#"><i class="fas fa-cog"></i>系统设置</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header-bar">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="mb-1">Dashboard</h3>
                    <p class="text-muted mb-0">Volt Dashboard 现代化管理系统</p>
                </div>
                <button class="btn btn-primary" onclick="showModal()">
                    <i class="fas fa-plus me-2"></i>创建新记录
                </button>
            </div>
        </div>

        <!-- Stats -->
        <div class="row">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card primary">
                    <div class="stat-icon primary">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-value">¥156,240</div>
                    <div class="stat-label">总销售额</div>
                    <div class="stat-trend text-success">
                        <i class="fas fa-arrow-up"></i> 18.2% 较上月
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card success">
                    <div class="stat-icon success">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value">3,240</div>
                    <div class="stat-label">活跃用户</div>
                    <div class="stat-trend text-success">
                        <i class="fas fa-arrow-up"></i> 12.5% 较上月
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card info">
                    <div class="stat-icon info">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <div class="stat-value">1,468</div>
                    <div class="stat-label">订单总数</div>
                    <div class="stat-trend text-danger">
                        <i class="fas fa-arrow-down"></i> 3.1% 较上月
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card warning">
                    <div class="stat-icon warning">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-value">62.4%</div>
                    <div class="stat-label">转化率</div>
                    <div class="stat-trend text-success">
                        <i class="fas fa-arrow-up"></i> 5.7% 较上月
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="row">
            <div class="col-lg-8 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="mb-3"><i class="fas fa-chart-area me-2 text-primary"></i>收入趋势</h5>
                        <div class="chart-container">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="mb-3"><i class="fas fa-chart-doughnut me-2 text-primary"></i>订单来源</h5>
                        <div class="chart-container">
                            <canvas id="sourceChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0"><i class="fas fa-receipt me-2 text-primary"></i>最新订单</h5>
                    <div class="d-flex gap-2">
                        <input type="text" class="form-control form-control-sm" placeholder="搜索订单..." style="width:200px;">
                        <button class="btn btn-primary btn-sm"><i class="fas fa-search"></i></button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>订单ID</th>
                                <th>客户</th>
                                <th>产品</th>
                                <th>金额</th>
                                <th>状态</th>
                                <th>进度</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>#VLT-001</td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle text-white me-2 d-flex align-items-center justify-content-center" style="width:32px;height:32px;background:linear-gradient(135deg,#6f42c1,#0d6efd);font-size:0.875rem;">陈</div>
                                        <span>陈先生</span>
                                    </div>
                                </td>
                                <td>iPhone 15 Pro</td>
                                <td>¥8,999</td>
                                <td><span class="badge badge-success">已完成</span></td>
                                <td>
                                    <div style="width:90px;">
                                        <div class="progress">
                                            <div class="progress-bar bg-success" style="width:100%"></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-primary"><i class="fas fa-eye"></i></button>
                                    <button class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></button>
                                </td>
                            </tr>
                            <tr>
                                <td>#VLT-002</td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle text-white me-2 d-flex align-items-center justify-content-center" style="width:32px;height:32px;background:linear-gradient(135deg,#00d4aa,#00f7c5);font-size:0.875rem;">刘</div>
                                        <span>刘女士</span>
                                    </div>
                                </td>
                                <td>MacBook Air</td>
                                <td>¥9,499</td>
                                <td><span class="badge badge-warning">配送中</span></td>
                                <td>
                                    <div style="width:90px;">
                                        <div class="progress">
                                            <div class="progress-bar bg-warning" style="width:70%"></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-primary"><i class="fas fa-eye"></i></button>
                                    <button class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></button>
                                </td>
                            </tr>
                            <tr>
                                <td>#VLT-003</td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle text-white me-2 d-flex align-items-center justify-content-center" style="width:32px;height:32px;background:linear-gradient(135deg,#ffc107,#ffd43b);font-size:0.875rem;">周</div>
                                        <span>周先生</span>
                                    </div>
                                </td>
                                <td>AirPods Pro</td>
                                <td>¥1,899</td>
                                <td><span class="badge badge-primary">待付款</span></td>
                                <td>
                                    <div style="width:90px;">
                                        <div class="progress">
                                            <div class="progress-bar" style="width:20%;background:#6f42c1"></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-primary"><i class="fas fa-eye"></i></button>
                                    <button class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Upload & Categories -->
        <div class="row mt-4">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="mb-3"><i class="fas fa-cloud-upload-alt me-2 text-primary"></i>文件上传</h5>
                        <div class="upload-box" onclick="document.getElementById('fileUp').click()">
                            <i class="fas fa-file-upload fa-3x text-muted mb-3"></i>
                            <p class="mb-1 fw-bold">拖拽文件到此处或点击上传</p>
                            <small class="text-muted">支持 JPG, PNG, PDF, DOCX - 最大 15MB</small>
                            <input type="file" id="fileUp" style="display:none;" multiple>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="mb-3"><i class="fas fa-layer-group me-2 text-primary"></i>产品分类</h5>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="fw-semibold">数码电子</span>
                                <span class="text-muted">48%</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar" style="width:48%;background:linear-gradient(135deg,#6f42c1,#8f5fe5)"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="fw-semibold">家电产品</span>
                                <span class="text-muted">28%</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar" style="width:28%;background:linear-gradient(135deg,#00d4aa,#00f7c5)"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="fw-semibold">时尚配饰</span>
                                <span class="text-muted">15%</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar" style="width:15%;background:linear-gradient(135deg,#0d6efd,#3d8bfd)"></div>
                            </div>
                        </div>
                        <div class="mb-0">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="fw-semibold">其他</span>
                                <span class="text-muted">9%</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar" style="width:9%;background:linear-gradient(135deg,#ffc107,#ffd43b)"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal-backdrop" id="modalBackdrop" onclick="hideModal()"></div>
    <div class="modal-dialog" id="addModal">
        <div class="modal-header">
            <h5>创建新记录</h5>
            <button class="btn-close" onclick="hideModal()">×</button>
        </div>
        <div class="modal-body">
            <div class="mb-3">
                <label class="form-label fw-semibold">客户名称</label>
                <input type="text" class="form-control" placeholder="请输入客户名称">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">产品名称</label>
                <input type="text" class="form-control" placeholder="请输入产品名称">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">订单金额</label>
                <input type="number" class="form-control" placeholder="请输入金额">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">订单状态</label>
                <select class="form-control">
                    <option>待付款</option>
                    <option>处理中</option>
                    <option>配送中</option>
                    <option>已完成</option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="hideModal()">取消</button>
            <button class="btn btn-primary">保存</button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.3.0/dist/chart.umd.min.js"></script>
    <script>
        function showModal() {
            document.getElementById('modalBackdrop').classList.add('show');
            document.getElementById('addModal').classList.add('show');
        }
        function hideModal() {
            document.getElementById('modalBackdrop').classList.remove('show');
            document.getElementById('addModal').classList.remove('show');
        }

        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: ['1月', '2月', '3月', '4月', '5月', '6月', '7月'],
                datasets: [{
                    label: '收入',
                    data: [35000, 42000, 38000, 52000, 48000, 65000, 58000],
                    borderColor: '#6f42c1',
                    backgroundColor: 'rgba(111,66,193,0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }
            }
        });

        const sourceCtx = document.getElementById('sourceChart').getContext('2d');
        new Chart(sourceCtx, {
            type: 'doughnut',
            data: {
                labels: ['官网', '京东', '淘宝', '其他'],
                datasets: [{
                    data: [45, 25, 20, 10],
                    backgroundColor: ['#6f42c1', '#00d4aa', '#0d6efd', '#ffc107']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    </script>
</body>
</html>
