<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Argon Dashboard - 完整演示</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --argon-primary: linear-gradient(87deg, #5e72e4 0, #825ee4 100%);
            --argon-success: linear-gradient(87deg, #2dce89 0, #2dcecc 100%);
            --argon-info: linear-gradient(87deg, #11cdef 0, #1171ef 100%);
            --argon-warning: linear-gradient(87deg, #fb6340 0, #fbb140 100%);
            --argon-bg: #f7fafc;
        }
        body {
            background: var(--argon-bg);
            font-family: 'Open Sans', sans-serif;
        }
        .navbar {
            background: #fff;
            box-shadow: 0 0 2rem 0 rgba(136,152,170,.15);
        }
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100vh;
            background: linear-gradient(87deg, #172b4d 0, #1a174d 100%);
            padding-top: 20px;
            z-index: 1000;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,.8);
            padding: 12px 24px;
            margin-bottom: 4px;
            border-radius: 8px;
            transition: all 0.2s;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255,255,255,.1);
            color: #fff;
        }
        .sidebar .nav-link i {
            width: 24px;
            margin-right: 12px;
        }
        .main-content {
            margin-left: 250px;
            padding: 24px;
        }
        .card {
            border: 0;
            border-radius: 12px;
            box-shadow: 0 0 2rem 0 rgba(136,152,170,.15);
            margin-bottom: 24px;
        }
        .card-stats {
            background: #fff;
            position: relative;
            overflow: hidden;
        }
        .card-stats .card-body {
            padding: 20px 24px;
        }
        .card-stats .icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.25rem;
        }
        .icon-primary { background: var(--argon-primary); }
        .icon-success { background: var(--argon-success); }
        .icon-info { background: var(--argon-info); }
        .icon-warning { background: var(--argon-warning); }
        .btn-primary {
            background: var(--argon-primary);
            border: 0;
            box-shadow: 0 4px 6px rgba(50,50,93,.11), 0 1px 3px rgba(0,0,0,.08);
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 7px 14px rgba(50,50,93,.1), 0 3px 6px rgba(0,0,0,.08);
        }
        .table thead th {
            border: 0;
            padding: 16px 20px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #8898aa;
        }
        .table td {
            padding: 16px 20px;
            vertical-align: middle;
        }
        .progress {
            height: 8px;
            border-radius: 999px;
            overflow: hidden;
        }
        .modal-content {
            border: 0;
            border-radius: 16px;
        }
        .form-control {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 12px 16px;
        }
        .form-control:focus {
            border-color: #5e72e4;
            box-shadow: 0 0 0 3px rgba(94,114,228,.1);
        }
        .badge {
            padding: 6px 12px;
            border-radius: 999px;
            font-weight: 600;
        }
        .chart-container {
            position: relative;
            height: 280px;
        }
        .sidebar-brand {
            padding: 20px 24px;
            color: #fff;
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .upload-zone {
            border: 2px dashed #dee2e6;
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            background: #f8f9fa;
            cursor: pointer;
            transition: all 0.3s;
        }
        .upload-zone:hover {
            border-color: #5e72e4;
            background: #f7f8fe;
        }
        .back-link {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            background: rgba(94,114,228,.9);
            color: #fff;
            padding: 12px 24px;
            border-radius: 999px;
            text-decoration: none;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(0,0,0,.15);
        }
        .back-link:hover {
            background: rgba(94,114,228,1);
            color: #fff;
        }
    </style>
</head>
<body>
    <a href="/ui-demos" class="back-link"><i class="fas fa-arrow-left me-2"></i>返回导航</a>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <i class="fas fa-gem"></i>
            <span>Argon Design</span>
        </div>
        <ul class="nav flex-column px-3">
            <li class="nav-item">
                <a class="nav-link active" href="#"><i class="fas fa-th-large"></i>仪表盘</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#"><i class="fas fa-chart-bar"></i>数据统计</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#"><i class="fas fa-table"></i>数据表格</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#"><i class="fas fa-file-invoice"></i>表单管理</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#"><i class="fas fa-upload"></i>文件上传</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#"><i class="fas fa-users"></i>用户管理</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#"><i class="fas fa-cog"></i>系统设置</a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-0">Dashboard</h2>
                <p class="text-muted mb-0">Argon Dashboard 完整功能演示</p>
            </div>
            <div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="fas fa-plus me-2"></i>新增记录
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row">
            <div class="col-xl-3 col-md-6">
                <div class="card card-stats">
                    <div class="card-body">
                        <div class="row">
                            <div class="col">
                                <h5 class="card-title text-uppercase text-muted mb-0">总收入</h5>
                                <span class="h2 font-weight-bold mb-0">¥350,897</span>
                            </div>
                            <div class="col-auto">
                                <div class="icon icon-primary">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                            </div>
                        </div>
                        <p class="mt-3 mb-0 text-sm">
                            <span class="text-success me-2"><i class="fas fa-arrow-up"></i> 12.5%</span>
                            <span class="text-nowrap">较上月增长</span>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-stats">
                    <div class="card-body">
                        <div class="row">
                            <div class="col">
                                <h5 class="card-title text-uppercase text-muted mb-0">新用户</h5>
                                <span class="h2 font-weight-bold mb-0">2,356</span>
                            </div>
                            <div class="col-auto">
                                <div class="icon icon-success">
                                    <i class="fas fa-users"></i>
                                </div>
                            </div>
                        </div>
                        <p class="mt-3 mb-0 text-sm">
                            <span class="text-danger me-2"><i class="fas fa-arrow-down"></i> 3.2%</span>
                            <span class="text-nowrap">较上月下降</span>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-stats">
                    <div class="card-body">
                        <div class="row">
                            <div class="col">
                                <h5 class="card-title text-uppercase text-muted mb-0">订单量</h5>
                                <span class="h2 font-weight-bold mb-0">924</span>
                            </div>
                            <div class="col-auto">
                                <div class="icon icon-info">
                                    <i class="fas fa-shopping-cart"></i>
                                </div>
                            </div>
                        </div>
                        <p class="mt-3 mb-0 text-sm">
                            <span class="text-success me-2"><i class="fas fa-arrow-up"></i> 8.1%</span>
                            <span class="text-nowrap">较上周增长</span>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-stats">
                    <div class="card-body">
                        <div class="row">
                            <div class="col">
                                <h5 class="card-title text-uppercase text-muted mb-0">转化率</h5>
                                <span class="h2 font-weight-bold mb-0">49.65%</span>
                            </div>
                            <div class="col-auto">
                                <div class="icon icon-warning">
                                    <i class="fas fa-percent"></i>
                                </div>
                            </div>
                        </div>
                        <p class="mt-3 mb-0 text-sm">
                            <span class="text-success me-2"><i class="fas fa-arrow-up"></i> 4.7%</span>
                            <span class="text-nowrap">较昨日增长</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="row">
            <div class="col-xl-8">
                <div class="card">
                    <div class="card-header border-0">
                        <h3 class="mb-0">销售趋势</h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="salesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card">
                    <div class="card-header border-0">
                        <h3 class="mb-0">流量来源</h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="trafficChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header border-0 d-flex justify-content-between align-items-center">
                        <h3 class="mb-0">最近交易</h3>
                        <div class="input-group" style="width: 300px;">
                            <input type="text" class="form-control" placeholder="搜索交易...">
                            <button class="btn btn-primary"><i class="fas fa-search"></i></button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-items-center table-flush">
                            <thead class="thead-light">
                                <tr>
                                    <th>订单号</th>
                                    <th>客户</th>
                                    <th>金额</th>
                                    <th>状态</th>
                                    <th>完成度</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>#10234</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar rounded-circle bg-primary text-white me-2" style="width:32px;height:32px;display:flex;align-items:center;justify-content:center;">
                                                张
                                            </div>
                                            <span>张三</span>
                                        </div>
                                    </td>
                                    <td>¥4,500</td>
                                    <td><span class="badge bg-success">已完成</span></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <span class="me-2">100%</span>
                                            <div class="progress" style="width: 80px;">
                                                <div class="progress-bar bg-success" style="width: 100%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary"><i class="fas fa-eye"></i></button>
                                        <button class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>#10235</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar rounded-circle bg-info text-white me-2" style="width:32px;height:32px;display:flex;align-items:center;justify-content:center;">
                                                李
                                            </div>
                                            <span>李四</span>
                                        </div>
                                    </td>
                                    <td>¥8,200</td>
                                    <td><span class="badge bg-warning">处理中</span></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <span class="me-2">75%</span>
                                            <div class="progress" style="width: 80px;">
                                                <div class="progress-bar bg-warning" style="width: 75%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary"><i class="fas fa-eye"></i></button>
                                        <button class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>#10236</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar rounded-circle bg-success text-white me-2" style="width:32px;height:32px;display:flex;align-items:center;justify-content:center;">
                                                王
                                            </div>
                                            <span>王五</span>
                                        </div>
                                    </td>
                                    <td>¥3,680</td>
                                    <td><span class="badge bg-primary">待审核</span></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <span class="me-2">40%</span>
                                            <div class="progress" style="width: 80px;">
                                                <div class="progress-bar bg-info" style="width: 40%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary"><i class="fas fa-eye"></i></button>
                                        <button class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upload Section -->
        <div class="row">
            <div class="col-xl-6">
                <div class="card">
                    <div class="card-header border-0">
                        <h3 class="mb-0">文件上传</h3>
                    </div>
                    <div class="card-body">
                        <div class="upload-zone" onclick="document.getElementById('fileInput').click()">
                            <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                            <p class="text-muted mb-2">点击或拖拽文件到此区域上传</p>
                            <small class="text-muted">支持 JPG、PNG、PDF 格式，单个文件不超过 10MB</small>
                            <input type="file" id="fileInput" style="display: none;" multiple>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="card">
                    <div class="card-header border-0">
                        <h3 class="mb-0">分类统计</h3>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span>电子产品</span>
                                <span class="fw-bold">45%</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar" style="width: 45%; background: var(--argon-primary);"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span>服装鞋包</span>
                                <span class="fw-bold">30%</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar" style="width: 30%; background: var(--argon-success);"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span>家居生活</span>
                                <span class="fw-bold">15%</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar" style="width: 15%; background: var(--argon-info);"></div>
                            </div>
                        </div>
                        <div class="mb-0">
                            <div class="d-flex justify-content-between mb-2">
                                <span>其他</span>
                                <span class="fw-bold">10%</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar" style="width: 10%; background: var(--argon-warning);"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Modal -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title">新增记录</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form>
                        <div class="mb-3">
                            <label class="form-label">客户名称</label>
                            <input type="text" class="form-control" placeholder="请输入客户名称">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">订单金额</label>
                            <input type="number" class="form-control" placeholder="请输入金额">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">订单状态</label>
                            <select class="form-control">
                                <option>待审核</option>
                                <option>处理中</option>
                                <option>已完成</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">备注</label>
                            <textarea class="form-control" rows="3" placeholder="请输入备注信息"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="button" class="btn btn-primary">确认提交</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.3.0/dist/chart.umd.min.js"></script>
    <script>
        // Sales Chart
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: ['1月', '2月', '3月', '4月', '5月', '6月', '7月'],
                datasets: [{
                    label: '销售额',
                    data: [30, 45, 35, 55, 45, 65, 58],
                    borderColor: 'rgba(94,114,228,1)',
                    backgroundColor: 'rgba(94,114,228,0.1)',
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

        // Traffic Chart
        const trafficCtx = document.getElementById('trafficChart').getContext('2d');
        new Chart(trafficCtx, {
            type: 'doughnut',
            data: {
                labels: ['直接访问', '搜索引擎', '社交媒体', '其他'],
                datasets: [{
                    data: [45, 30, 15, 10],
                    backgroundColor: [
                        'rgba(94,114,228,0.8)',
                        'rgba(45,206,137,0.8)',
                        'rgba(17,205,239,0.8)',
                        'rgba(251,99,64,0.8)'
                    ]
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
