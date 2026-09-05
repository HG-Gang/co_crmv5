<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CoreUI - 完整演示</title>
    <link href="https://cdn.jsdelivr.net/npm/@coreui/coreui@5.0.0/dist/css/coreui.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --cui-primary: #321fdb;
            --cui-success: #2eb85c;
            --cui-info: #39f;
            --cui-warning: #f9b115;
            --cui-danger: #e55353;
            --cui-light: #f0f4f7;
            --cui-dark: #2c3e50;
        }
        body {
            background: var(--cui-light);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 256px;
            height: 100vh;
            background: #fff;
            border-right: 1px solid #d8dbe0;
            z-index: 1000;
            overflow-y: auto;
        }
        .sidebar-brand {
            padding: 20px;
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--cui-primary);
            border-bottom: 1px solid #d8dbe0;
        }
        .sidebar-nav {
            padding: 12px 0;
        }
        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #5c6873;
            text-decoration: none;
            transition: all 0.2s;
        }
        .nav-link:hover {
            background: #f0f4f7;
            color: var(--cui-primary);
        }
        .nav-link.active {
            background: var(--cui-primary);
            color: #fff;
            border-left: 4px solid var(--cui-primary);
        }
        .nav-link i {
            width: 24px;
            margin-right: 12px;
            font-size: 1.125rem;
        }
        .main-content {
            margin-left: 256px;
            padding: 24px;
        }
        .header {
            background: #fff;
            padding: 16px 24px;
            margin: -24px -24px 24px -24px;
            border-bottom: 1px solid #d8dbe0;
        }
        .card {
            background: #fff;
            border: 1px solid #d8dbe0;
            border-radius: 8px;
            margin-bottom: 24px;
        }
        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid #d8dbe0;
            font-weight: 600;
        }
        .card-body {
            padding: 20px;
        }
        .stat-card {
            padding: 20px;
            border-left: 4px solid;
        }
        .stat-card.primary { border-color: var(--cui-primary); }
        .stat-card.success { border-color: var(--cui-success); }
        .stat-card.info { border-color: var(--cui-info); }
        .stat-card.warning { border-color: var(--cui-warning); }
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .stat-label {
            color: #768192;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stat-trend {
            margin-top: 8px;
            font-size: 0.875rem;
        }
        .table {
            width: 100%;
            margin-bottom: 0;
        }
        .table thead th {
            background: #f8f9fa;
            padding: 12px 16px;
            font-size: 0.875rem;
            font-weight: 600;
            color: #5c6873;
            border-bottom: 2px solid #d8dbe0;
        }
        .table tbody td {
            padding: 12px 16px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
        }
        .table tbody tr:hover {
            background: #f8f9fa;
        }
        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 500;
            border: 0;
            transition: all 0.2s;
        }
        .btn-primary {
            background: var(--cui-primary);
            color: #fff;
        }
        .btn-primary:hover {
            background: #2819b0;
        }
        .btn-sm {
            padding: 4px 12px;
            font-size: 0.875rem;
        }
        .badge {
            padding: 4px 10px;
            border-radius: 4px;
            font-weight: 500;
            font-size: 0.75rem;
        }
        .badge-success { background: var(--cui-success); color: #fff; }
        .badge-warning { background: var(--cui-warning); color: #000; }
        .badge-primary { background: var(--cui-primary); color: #fff; }
        .badge-danger { background: var(--cui-danger); color: #fff; }
        .progress {
            height: 6px;
            border-radius: 999px;
            background: #e9ecef;
            overflow: hidden;
        }
        .progress-bar {
            height: 100%;
            border-radius: 999px;
            transition: width 0.3s;
        }
        .form-control {
            padding: 10px 14px;
            border: 1px solid #d8dbe0;
            border-radius: 6px;
        }
        .form-control:focus {
            border-color: var(--cui-primary);
            outline: 0;
            box-shadow: 0 0 0 3px rgba(50,31,219,0.1);
        }
        .chart-container {
            position: relative;
            height: 260px;
        }
        .upload-area {
            border: 2px dashed #d8dbe0;
            border-radius: 8px;
            padding: 48px 20px;
            text-align: center;
            background: #f8f9fa;
            cursor: pointer;
            transition: all 0.3s;
        }
        .upload-area:hover {
            border-color: var(--cui-primary);
            background: #f0f4f7;
        }
        .back-link {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            background: var(--cui-primary);
            color: #fff;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(0,0,0,.15);
        }
        .back-link:hover {
            background: #2819b0;
            color: #fff;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9000;
            align-items: center;
            justify-content: center;
        }
        .modal.show {
            display: flex;
        }
        .modal-dialog {
            background: #fff;
            border-radius: 8px;
            max-width: 500px;
            width: 90%;
        }
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #d8dbe0;
        }
        .modal-body {
            padding: 20px;
        }
        .modal-footer {
            padding: 16px 20px;
            border-top: 1px solid #d8dbe0;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .btn-close {
            background: transparent;
            border: 0;
            font-size: 1.5rem;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <a href="/ui-demos" class="back-link"><i class="fas fa-arrow-left me-2"></i>返回导航</a>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <i class="fas fa-cube me-2"></i>CoreUI Admin
        </div>
        <div class="sidebar-nav">
            <a class="nav-link active" href="#"><i class="fas fa-tachometer-alt"></i>仪表盘</a>
            <a class="nav-link" href="#"><i class="fas fa-chart-line"></i>数据分析</a>
            <a class="nav-link" href="#"><i class="fas fa-table"></i>数据表格</a>
            <a class="nav-link" href="#"><i class="fas fa-file-alt"></i>表单管理</a>
            <a class="nav-link" href="#"><i class="fas fa-cloud-upload-alt"></i>文件上传</a>
            <a class="nav-link" href="#"><i class="fas fa-users"></i>用户管理</a>
            <a class="nav-link" href="#"><i class="fas fa-cog"></i>系统设置</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-0">Dashboard</h4>
                    <small class="text-muted">CoreUI 数据管理系统演示</small>
                </div>
                <button class="btn btn-primary" onclick="document.getElementById('addModal').classList.add('show')">
                    <i class="fas fa-plus me-2"></i>新增数据
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row">
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card stat-card primary">
                    <div class="stat-value text-primary">12,340</div>
                    <div class="stat-label">总访问量</div>
                    <div class="stat-trend text-success">
                        <i class="fas fa-arrow-up"></i> 15.8% 较上月
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card stat-card success">
                    <div class="stat-value text-success">1,875</div>
                    <div class="stat-label">新增用户</div>
                    <div class="stat-trend text-success">
                        <i class="fas fa-arrow-up"></i> 8.3% 较上月
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card stat-card info">
                    <div class="stat-value text-info">568</div>
                    <div class="stat-label">活跃订单</div>
                    <div class="stat-trend text-danger">
                        <i class="fas fa-arrow-down"></i> 2.1% 较上月
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card stat-card warning">
                    <div class="stat-value text-warning">¥89,560</div>
                    <div class="stat-label">总收益</div>
                    <div class="stat-trend text-success">
                        <i class="fas fa-arrow-up"></i> 22.5% 较上月
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="row">
            <div class="col-lg-8 mb-4">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-chart-bar me-2"></i>月度销售趋势
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="salesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-chart-pie me-2"></i>产品分类占比
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="categoryChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-list me-2"></i>最近交易记录</span>
                <div class="d-flex gap-2">
                    <input type="text" class="form-control" placeholder="搜索..." style="width: 200px;">
                    <button class="btn btn-primary btn-sm"><i class="fas fa-search"></i></button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>订单号</th>
                            <th>客户</th>
                            <th>金额</th>
                            <th>状态</th>
                            <th>进度</th>
                            <th>日期</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>1</td>
                            <td>#ORD-2024-001</td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="bg-primary text-white rounded-circle me-2" style="width:28px;height:28px;display:flex;align-items:center;justify-content:center;font-size:0.75rem;">张</div>
                                    <span>张三</span>
                                </div>
                            </td>
                            <td>¥5,280</td>
                            <td><span class="badge badge-success">已完成</span></td>
                            <td>
                                <div style="width:80px;">
                                    <div class="progress">
                                        <div class="progress-bar" style="width:100%;background:var(--cui-success)"></div>
                                    </div>
                                </div>
                            </td>
                            <td>2024-09-01</td>
                            <td>
                                <button class="btn btn-sm btn-primary"><i class="fas fa-eye"></i></button>
                                <button class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></button>
                            </td>
                        </tr>
                        <tr>
                            <td>2</td>
                            <td>#ORD-2024-002</td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="bg-success text-white rounded-circle me-2" style="width:28px;height:28px;display:flex;align-items:center;justify-content:center;font-size:0.75rem;">李</div>
                                    <span>李四</span>
                                </div>
                            </td>
                            <td>¥8,950</td>
                            <td><span class="badge badge-warning">处理中</span></td>
                            <td>
                                <div style="width:80px;">
                                    <div class="progress">
                                        <div class="progress-bar" style="width:65%;background:var(--cui-warning)"></div>
                                    </div>
                                </div>
                            </td>
                            <td>2024-09-02</td>
                            <td>
                                <button class="btn btn-sm btn-primary"><i class="fas fa-eye"></i></button>
                                <button class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></button>
                            </td>
                        </tr>
                        <tr>
                            <td>3</td>
                            <td>#ORD-2024-003</td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="bg-info text-white rounded-circle me-2" style="width:28px;height:28px;display:flex;align-items:center;justify-content:center;font-size:0.75rem;">王</div>
                                    <span>王五</span>
                                </div>
                            </td>
                            <td>¥3,200</td>
                            <td><span class="badge badge-primary">待审核</span></td>
                            <td>
                                <div style="width:80px;">
                                    <div class="progress">
                                        <div class="progress-bar" style="width:30%;background:var(--cui-info)"></div>
                                    </div>
                                </div>
                            </td>
                            <td>2024-09-03</td>
                            <td>
                                <button class="btn btn-sm btn-primary"><i class="fas fa-eye"></i></button>
                                <button class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></button>
                            </td>
                        </tr>
                        <tr>
                            <td>4</td>
                            <td>#ORD-2024-004</td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="bg-warning text-dark rounded-circle me-2" style="width:28px;height:28px;display:flex;align-items:center;justify-content:center;font-size:0.75rem;">赵</div>
                                    <span>赵六</span>
                                </div>
                            </td>
                            <td>¥12,680</td>
                            <td><span class="badge badge-danger">已取消</span></td>
                            <td>
                                <div style="width:80px;">
                                    <div class="progress">
                                        <div class="progress-bar" style="width:0%;background:var(--cui-danger)"></div>
                                    </div>
                                </div>
                            </td>
                            <td>2024-09-03</td>
                            <td>
                                <button class="btn btn-sm btn-primary"><i class="fas fa-eye"></i></button>
                                <button class="btn btn-sm btn-primary"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Upload & Category -->
        <div class="row mt-4">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-upload me-2"></i>文件上传
                    </div>
                    <div class="card-body">
                        <div class="upload-area" onclick="document.getElementById('fileUpload').click()">
                            <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                            <p class="mb-0">点击或拖拽文件到此处上传</p>
                            <small class="text-muted">支持 JPG, PNG, PDF, 最大 10MB</small>
                            <input type="file" id="fileUpload" style="display:none;" multiple>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-tags me-2"></i>类别统计
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span>电子产品</span>
                                <strong>42%</strong>
                            </div>
                            <div class="progress">
                                <div class="progress-bar" style="width:42%;background:var(--cui-primary)"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span>服装配饰</span>
                                <strong>28%</strong>
                            </div>
                            <div class="progress">
                                <div class="progress-bar" style="width:28%;background:var(--cui-success)"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span>家居用品</span>
                                <strong>18%</strong>
                            </div>
                            <div class="progress">
                                <div class="progress-bar" style="width:18%;background:var(--cui-info)"></div>
                            </div>
                        </div>
                        <div class="mb-0">
                            <div class="d-flex justify-content-between mb-2">
                                <span>其他</span>
                                <strong>12%</strong>
                            </div>
                            <div class="progress">
                                <div class="progress-bar" style="width:12%;background:var(--cui-warning)"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal" id="addModal">
        <div class="modal-dialog">
            <div class="modal-header">
                <h5>新增数据</h5>
                <button class="btn-close" onclick="document.getElementById('addModal').classList.remove('show')">×</button>
            </div>
            <div class="modal-body">
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
                    <label class="form-label">备注信息</label>
                    <textarea class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="document.getElementById('addModal').classList.remove('show')">取消</button>
                <button class="btn btn-primary">保存</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.3.0/dist/chart.umd.min.js"></script>
    <script>
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        new Chart(salesCtx, {
            type: 'bar',
            data: {
                labels: ['1月', '2月', '3月', '4月', '5月', '6月'],
                datasets: [{
                    label: '销售额',
                    data: [42000, 48000, 45000, 58000, 52000, 62000],
                    backgroundColor: 'rgba(50,31,219,0.8)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }
            }
        });

        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        new Chart(categoryCtx, {
            type: 'pie',
            data: {
                labels: ['电子产品', '服装配饰', '家居用品', '其他'],
                datasets: [{
                    data: [42, 28, 18, 12],
                    backgroundColor: [
                        'rgba(50,31,219,0.8)',
                        'rgba(46,184,92,0.8)',
                        'rgba(51,153,255,0.8)',
                        'rgba(249,177,21,0.8)'
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
