<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ample Admin - 完整演示</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --ample-primary: #26c6da;
            --ample-success: #00c292;
            --ample-info: #03a9f3;
            --ample-warning: #fec107;
            --ample-danger: #ef5350;
            --ample-sidebar: #fff;
            --ample-topbar: #fff;
        }
        body {
            background: #f2f7f8;
            font-family: 'Poppins', sans-serif;
        }
        .sidebar {
            position: fixed;
            top: 64px;
            left: 0;
            width: 240px;
            height: calc(100vh - 64px);
            background: #fff;
            border-right: 1px solid #e5eaec;
            overflow-y: auto;
            z-index: 100;
        }
        .topbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 64px;
            background: #fff;
            border-bottom: 1px solid #e5eaec;
            z-index: 200;
            display: flex;
            align-items: center;
            padding: 0 20px;
        }
        .topbar-brand {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--ample-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .topbar-brand i {
            width: 36px;
            height: 36px;
            background: var(--ample-primary);
            color: #fff;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .sidebar-nav {
            padding: 20px 0;
        }
        .nav-label {
            padding: 16px 20px 8px;
            font-size: 0.75rem;
            font-weight: 600;
            color: #99abb4;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .nav-item {
            margin: 2px 8px;
        }
        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            color: #67757c;
            text-decoration: none;
            border-radius: 4px;
            transition: all 0.2s;
        }
        .nav-link:hover {
            background: #f2f7f8;
            color: var(--ample-primary);
        }
        .nav-link.active {
            background: var(--ample-primary);
            color: #fff;
        }
        .nav-link i {
            width: 20px;
            margin-right: 12px;
        }
        .main-content {
            margin-left: 240px;
            margin-top: 64px;
            padding: 28px;
        }
        .page-header {
            margin-bottom: 28px;
        }
        .page-header h3 {
            font-size: 1.75rem;
            font-weight: 600;
            color: #2b2b2b;
            margin-bottom: 4px;
        }
        .page-header p {
            color: #99abb4;
            margin: 0;
        }
        .card {
            background: #fff;
            border: 1px solid #e5eaec;
            border-radius: 6px;
            margin-bottom: 24px;
        }
        .card-header {
            background: #fff;
            border-bottom: 1px solid #e5eaec;
            padding: 16px 20px;
            font-weight: 600;
            color: #2b2b2b;
        }
        .card-body {
            padding: 20px;
        }
        .stat-card {
            padding: 24px;
            border-radius: 6px;
            background: #fff;
            border: 1px solid #e5eaec;
        }
        .stat-card .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #fff;
            margin-bottom: 16px;
        }
        .stat-card .stat-icon.primary { background: var(--ample-primary); }
        .stat-card .stat-icon.success { background: var(--ample-success); }
        .stat-card .stat-icon.info { background: var(--ample-info); }
        .stat-card .stat-icon.warning { background: var(--ample-warning); }
        .stat-card h4 {
            font-size: 1.875rem;
            font-weight: 600;
            color: #2b2b2b;
            margin-bottom: 4px;
        }
        .stat-card p {
            color: #99abb4;
            font-size: 0.875rem;
            margin: 0;
        }
        .stat-card .stat-change {
            margin-top: 10px;
            font-size: 0.8125rem;
        }
        .btn {
            padding: 10px 20px;
            border-radius: 4px;
            font-weight: 500;
            border: 0;
            transition: all 0.2s;
        }
        .btn-primary {
            background: var(--ample-primary);
            color: #fff;
        }
        .btn-primary:hover {
            background: #1db5c8;
        }
        .btn-sm {
            padding: 6px 14px;
            font-size: 0.875rem;
        }
        .table {
            margin-bottom: 0;
        }
        .table thead th {
            border-bottom: 2px solid #e5eaec;
            padding: 14px 16px;
            font-size: 0.8125rem;
            font-weight: 600;
            color: #99abb4;
            text-transform: uppercase;
            background: #f9f9f9;
        }
        .table tbody td {
            padding: 14px 16px;
            vertical-align: middle;
            border-bottom: 1px solid #e5eaec;
            color: #67757c;
        }
        .table tbody tr:hover {
            background: #f9f9f9;
        }
        .badge {
            padding: 5px 10px;
            border-radius: 3px;
            font-weight: 500;
            font-size: 0.75rem;
        }
        .badge-success {
            background: var(--ample-success);
            color: #fff;
        }
        .badge-warning {
            background: var(--ample-warning);
            color: #fff;
        }
        .badge-primary {
            background: var(--ample-primary);
            color: #fff;
        }
        .badge-danger {
            background: var(--ample-danger);
            color: #fff;
        }
        .progress {
            height: 6px;
            border-radius: 999px;
            background: #e5eaec;
        }
        .progress-bar {
            border-radius: 999px;
        }
        .form-control {
            padding: 10px 14px;
            border: 1px solid #e5eaec;
            border-radius: 4px;
        }
        .form-control:focus {
            border-color: var(--ample-primary);
            box-shadow: 0 0 0 2px rgba(38,198,218,0.1);
        }
        .chart-container {
            position: relative;
            height: 280px;
        }
        .upload-zone {
            border: 2px dashed #e5eaec;
            border-radius: 6px;
            padding: 48px 24px;
            text-align: center;
            background: #f9f9f9;
            cursor: pointer;
            transition: all 0.3s;
        }
        .upload-zone:hover {
            border-color: var(--ample-primary);
            background: #f2f7f8;
        }
        .back-link {
            position: fixed;
            top: 76px;
            right: 20px;
            z-index: 9999;
            background: var(--ample-primary);
            color: #fff;
            padding: 10px 20px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(0,0,0,.15);
        }
        .back-link:hover {
            background: #1db5c8;
            color: #fff;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.4);
            z-index: 9000;
            align-items: center;
            justify-content: center;
        }
        .modal.show {
            display: flex;
        }
        .modal-content {
            background: #fff;
            border-radius: 6px;
            max-width: 500px;
            width: 90%;
        }
        .modal-header {
            padding: 18px 20px;
            border-bottom: 1px solid #e5eaec;
        }
        .modal-body {
            padding: 20px;
        }
        .modal-footer {
            padding: 14px 20px;
            border-top: 1px solid #e5eaec;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
    </style>
</head>
<body>
    <a href="/ui-demos" class="back-link"><i class="fas fa-arrow-left me-2"></i>返回导航</a>

    <!-- Topbar -->
    <div class="topbar">
        <div class="topbar-brand">
            <i class="fas fa-chart-line"></i>
            <span>Ample Admin</span>
        </div>
        <div class="ms-auto d-flex align-items-center gap-3">
            <button class="btn btn-primary" onclick="showModal()">
                <i class="fas fa-plus me-2"></i>新增数据
            </button>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-nav">
            <div class="nav-label">主导航</div>
            <div class="nav-item">
                <a class="nav-link active" href="#"><i class="fas fa-tachometer-alt"></i>仪表盘</a>
            </div>
            <div class="nav-item">
                <a class="nav-link" href="#"><i class="fas fa-chart-bar"></i>数据分析</a>
            </div>
            <div class="nav-label">功能模块</div>
            <div class="nav-item">
                <a class="nav-link" href="#"><i class="fas fa-table"></i>数据表格</a>
            </div>
            <div class="nav-item">
                <a class="nav-link" href="#"><i class="fas fa-edit"></i>表单管理</a>
            </div>
            <div class="nav-item">
                <a class="nav-link" href="#"><i class="fas fa-upload"></i>文件上传</a>
            </div>
            <div class="nav-label">系统管理</div>
            <div class="nav-item">
                <a class="nav-link" href="#"><i class="fas fa-users"></i>用户管理</a>
            </div>
            <div class="nav-item">
                <a class="nav-link" href="#"><i class="fas fa-cog"></i>系统设置</a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h3>Dashboard</h3>
            <p>Ample Admin 简洁企业风格演示</p>
        </div>

        <!-- Stats -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <h4>3,568</h4>
                    <p>总订单数</p>
                    <div class="stat-change text-success">
                        <i class="fas fa-arrow-up"></i> 14.5% 较上月
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <h4>¥98,240</h4>
                    <p>月收入</p>
                    <div class="stat-change text-success">
                        <i class="fas fa-arrow-up"></i> 22.8% 较上月
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="stat-icon info">
                        <i class="fas fa-users"></i>
                    </div>
                    <h4>1,846</h4>
                    <p>新增客户</p>
                    <div class="stat-change text-danger">
                        <i class="fas fa-arrow-down"></i> 5.2% 较上月
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <h4>74.3%</h4>
                    <p>完成率</p>
                    <div class="stat-change text-success">
                        <i class="fas fa-arrow-up"></i> 9.6% 较上月
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-chart-area me-2 text-primary"></i>月度销售统计
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="salesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-chart-pie me-2 text-primary"></i>渠道分布
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="channelChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-list me-2 text-primary"></i>最新订单列表</span>
                <div class="d-flex gap-2">
                    <input type="text" class="form-control form-control-sm" placeholder="搜索订单..." style="width:200px;">
                    <button class="btn btn-primary btn-sm"><i class="fas fa-search"></i></button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>编号</th>
                            <th>订单ID</th>
                            <th>客户名称</th>
                            <th>产品</th>
                            <th>金额</th>
                            <th>状态</th>
                            <th>进度</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>1</td>
                            <td><strong>#AMP-1024</strong></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle text-white me-2 d-flex align-items-center justify-content-center" style="width:30px;height:30px;background:#26c6da;font-size:0.8rem;">何</div>
                                    <span>何先生</span>
                                </div>
                            </td>
                            <td>办公设备</td>
                            <td><strong>¥12,580</strong></td>
                            <td><span class="badge badge-success">已完成</span></td>
                            <td>
                                <div style="width:80px;">
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
                            <td>2</td>
                            <td><strong>#AMP-1025</strong></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle text-white me-2 d-flex align-items-center justify-content-center" style="width:30px;height:30px;background:#00c292;font-size:0.8rem;">卢</div>
                                    <span>卢女士</span>
                                </div>
                            </td>
                            <td>办公家具</td>
                            <td><strong>¥8,960</strong></td>
                            <td><span class="badge badge-warning">处理中</span></td>
                            <td>
                                <div style="width:80px;">
                                    <div class="progress">
                                        <div class="progress-bar bg-warning" style="width:60%"></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary"><i class="fas fa-eye"></i></button>
                                <button class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></button>
                            </td>
                        </tr>
                        <tr>
                            <td>3</td>
                            <td><strong>#AMP-1026</strong></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle text-white me-2 d-flex align-items-center justify-content-center" style="width:30px;height:30px;background:#03a9f3;font-size:0.8rem;">姚</div>
                                    <span>姚先生</span>
                                </div>
                            </td>
                            <td>电子配件</td>
                            <td><strong>¥3,240</strong></td>
                            <td><span class="badge badge-primary">待确认</span></td>
                            <td>
                                <div style="width:80px;">
                                    <div class="progress">
                                        <div class="progress-bar" style="width:30%;background:#26c6da;"></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary"><i class="fas fa-eye"></i></button>
                                <button class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></button>
                            </td>
                        </tr>
                        <tr>
                            <td>4</td>
                            <td><strong>#AMP-1027</strong></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle text-white me-2 d-flex align-items-center justify-content-center" style="width:30px;height:30px;background:#fec107;font-size:0.8rem;">邵</div>
                                    <span>邵女士</span>
                                </div>
                            </td>
                            <td>耗材用品</td>
                            <td><strong>¥1,560</strong></td>
                            <td><span class="badge badge-danger">已取消</span></td>
                            <td>
                                <div style="width:80px;">
                                    <div class="progress">
                                        <div class="progress-bar bg-danger" style="width:0%"></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary"><i class="fas fa-eye"></i></button>
                                <button class="btn btn-sm btn-primary"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Upload & Categories -->
        <div class="row mt-4">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-cloud-upload-alt me-2 text-primary"></i>文件上传
                    </div>
                    <div class="card-body">
                        <div class="upload-zone" onclick="document.getElementById('file').click()">
                            <i class="fas fa-upload fa-3x text-muted mb-3"></i>
                            <p class="mb-1 fw-semibold">拖拽文件到此处或点击上传</p>
                            <small class="text-muted">支持 JPG, PNG, PDF, XLSX - 最大 10MB</small>
                            <input type="file" id="file" style="display:none;" multiple>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-tags me-2 text-primary"></i>产品类别
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="fw-semibold">办公设备</span>
                                <strong style="color:#26c6da;">40%</strong>
                            </div>
                            <div class="progress">
                                <div class="progress-bar" style="width:40%;background:#26c6da;"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="fw-semibold">办公家具</span>
                                <strong style="color:#00c292;">32%</strong>
                            </div>
                            <div class="progress">
                                <div class="progress-bar" style="width:32%;background:#00c292;"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="fw-semibold">电子配件</span>
                                <strong style="color:#03a9f3;">18%</strong>
                            </div>
                            <div class="progress">
                                <div class="progress-bar" style="width:18%;background:#03a9f3;"></div>
                            </div>
                        </div>
                        <div class="mb-0">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="fw-semibold">其他</span>
                                <strong style="color:#fec107;">10%</strong>
                            </div>
                            <div class="progress">
                                <div class="progress-bar" style="width:10%;background:#fec107;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal" id="dataModal">
        <div class="modal-content">
            <div class="modal-header">
                <h5 style="margin:0;font-weight:600;">新增数据</h5>
                <button style="border:0;background:transparent;font-size:1.5rem;cursor:pointer;" onclick="hideModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">客户名称</label>
                    <input type="text" class="form-control" placeholder="请输入客户名称">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">产品类别</label>
                    <select class="form-control">
                        <option>办公设备</option>
                        <option>办公家具</option>
                        <option>电子配件</option>
                        <option>其他</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">订单金额</label>
                    <input type="number" class="form-control" placeholder="请输入金额">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">备注说明</label>
                    <textarea class="form-control" rows="3" placeholder="请输入备注"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="hideModal()">取消</button>
                <button class="btn btn-primary">保存</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.3.0/dist/chart.umd.min.js"></script>
    <script>
        function showModal() {
            document.getElementById('dataModal').classList.add('show');
        }
        function hideModal() {
            document.getElementById('dataModal').classList.remove('show');
        }

        const salesCtx = document.getElementById('salesChart').getContext('2d');
        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: ['1月', '2月', '3月', '4月', '5月', '6月', '7月'],
                datasets: [{
                    label: '销售额',
                    data: [38000, 45000, 42000, 58000, 52000, 68000, 62000],
                    borderColor: '#26c6da',
                    backgroundColor: 'rgba(38,198,218,0.1)',
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

        const channelCtx = document.getElementById('channelChart').getContext('2d');
        new Chart(channelCtx, {
            type: 'doughnut',
            data: {
                labels: ['线上商城', '线下门店', '批发渠道', '其他'],
                datasets: [{
                    data: [40, 32, 18, 10],
                    backgroundColor: ['#26c6da', '#00c292', '#03a9f3', '#fec107']
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
