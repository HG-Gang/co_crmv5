<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vuexy 风格 - 完整演示</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --vuexy-primary: #7367f0;
            --vuexy-success: #28c76f;
            --vuexy-danger: #ea5455;
            --vuexy-warning: #ff9f43;
            --vuexy-info: #00cfe8;
            --vuexy-dark: #4b4b4b;
            --vuexy-light: #f8f8f8;
            --vuexy-sidebar: #fff;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: var(--vuexy-light);
            font-family: 'Montserrat', sans-serif;
        }
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 260px;
            height: 100vh;
            background: #fff;
            box-shadow: 0 4px 24px 0 rgba(34,41,47,.1);
            z-index: 1000;
        }
        .sidebar-brand {
            padding: 24px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid #ebe9f1;
        }
        .sidebar-brand .logo {
            width: 36px;
            height: 36px;
            background: linear-gradient(118deg, #7367f0, rgba(115,103,240,.7));
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 1.125rem;
        }
        .sidebar-brand span {
            font-size: 1.375rem;
            font-weight: 600;
            color: #5e5873;
        }
        .sidebar-nav {
            padding: 16px 0;
        }
        .nav-header {
            padding: 16px 24px 8px;
            color: #b9b9c3;
            font-size: 0.6875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }
        .nav-item {
            margin: 0 12px 4px;
        }
        .nav-link {
            display: flex;
            align-items: center;
            padding: 11px 16px;
            color: #6e6b7b;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.25s;
            font-size: 0.9375rem;
        }
        .nav-link:hover {
            background: rgba(115,103,240,0.08);
            color: var(--vuexy-primary);
        }
        .nav-link.active {
            background: linear-gradient(118deg, #7367f0, rgba(115,103,240,.7));
            color: #fff;
            box-shadow: 0 0 10px 1px rgba(115,103,240,.7);
        }
        .nav-link i {
            width: 20px;
            margin-right: 12px;
            font-size: 1.125rem;
        }
        .main-content {
            margin-left: 260px;
            padding: 24px;
        }
        .navbar {
            background: #fff;
            padding: 16px 24px;
            margin: -24px -24px 24px -24px;
            border-radius: 0;
            box-shadow: 0 4px 24px 0 rgba(34,41,47,.1);
        }
        .card {
            background: #fff;
            border: 0;
            border-radius: 10px;
            box-shadow: 0 4px 24px 0 rgba(34,41,47,.1);
            margin-bottom: 24px;
        }
        .card-header {
            background: transparent;
            border: 0;
            padding: 20px 24px;
            font-weight: 600;
            font-size: 1.0625rem;
        }
        .card-body {
            padding: 24px;
        }
        .stat-card {
            padding: 24px;
            border-radius: 10px;
            background: #fff;
            box-shadow: 0 4px 24px 0 rgba(34,41,47,.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #fff;
        }
        .stat-icon.primary { background: linear-gradient(118deg, #7367f0, rgba(115,103,240,.7)); }
        .stat-icon.success { background: linear-gradient(118deg, #28c76f, rgba(40,199,111,.7)); }
        .stat-icon.danger { background: linear-gradient(118deg, #ea5455, rgba(234,84,85,.7)); }
        .stat-icon.warning { background: linear-gradient(118deg, #ff9f43, rgba(255,159,67,.7)); }
        .stat-content h3 {
            font-size: 1.875rem;
            font-weight: 600;
            color: #5e5873;
            margin-bottom: 4px;
        }
        .stat-content p {
            color: #b9b9c3;
            font-size: 0.875rem;
            margin: 0;
        }
        .stat-trend {
            font-size: 0.8125rem;
            margin-top: 8px;
        }
        .btn {
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 500;
            border: 0;
            transition: all 0.2s;
        }
        .btn-primary {
            background: linear-gradient(118deg, #7367f0, rgba(115,103,240,.7));
            color: #fff;
            box-shadow: 0 3px 8px rgba(115,103,240,.4);
        }
        .btn-primary:hover {
            box-shadow: 0 6px 16px rgba(115,103,240,.5);
        }
        .btn-sm {
            padding: 6px 14px;
            font-size: 0.875rem;
        }
        .table {
            margin-bottom: 0;
        }
        .table thead th {
            border-bottom: 1px solid #ebe9f1;
            padding: 16px 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #b9b9c3;
        }
        .table tbody td {
            padding: 16px 20px;
            vertical-align: middle;
            border-bottom: 1px solid #ebe9f1;
            color: #6e6b7b;
        }
        .table tbody tr:hover {
            background: #fafafa;
        }
        .badge {
            padding: 6px 12px;
            border-radius: 4px;
            font-weight: 500;
            font-size: 0.75rem;
        }
        .badge-success {
            background: rgba(40,199,111,0.12);
            color: var(--vuexy-success);
        }
        .badge-warning {
            background: rgba(255,159,67,0.12);
            color: var(--vuexy-warning);
        }
        .badge-primary {
            background: rgba(115,103,240,0.12);
            color: var(--vuexy-primary);
        }
        .badge-danger {
            background: rgba(234,84,85,0.12);
            color: var(--vuexy-danger);
        }
        .progress {
            height: 6px;
            border-radius: 999px;
            background: #ebe9f1;
        }
        .progress-bar {
            border-radius: 999px;
        }
        .form-control {
            padding: 10px 16px;
            border: 1px solid #d8d6de;
            border-radius: 6px;
            color: #6e6b7b;
        }
        .form-control:focus {
            border-color: var(--vuexy-primary);
            box-shadow: 0 3px 10px 0 rgba(34,41,47,.1);
        }
        .chart-container {
            position: relative;
            height: 280px;
        }
        .upload-area {
            border: 2px dashed #d8d6de;
            border-radius: 10px;
            padding: 48px 24px;
            text-align: center;
            background: #fafafa;
            cursor: pointer;
            transition: all 0.3s;
        }
        .upload-area:hover {
            border-color: var(--vuexy-primary);
            background: rgba(115,103,240,0.04);
        }
        .back-link {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            background: linear-gradient(118deg, #7367f0, rgba(115,103,240,.7));
            color: #fff;
            padding: 12px 24px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(115,103,240,.4);
        }
        .back-link:hover {
            color: #fff;
            box-shadow: 0 6px 20px rgba(115,103,240,.5);
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(34,41,47,0.4);
            z-index: 9000;
            align-items: center;
            justify-content: center;
        }
        .modal.show { display: flex; }
        .modal-content {
            background: #fff;
            border-radius: 10px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 10px 40px rgba(34,41,47,.2);
        }
        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid #ebe9f1;
        }
        .modal-body {
            padding: 24px;
        }
        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid #ebe9f1;
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
            <div class="logo">V</div>
            <span>Vuexy</span>
        </div>
        <div class="sidebar-nav">
            <div class="nav-header">主菜单</div>
            <div class="nav-item">
                <a class="nav-link active" href="#"><i class="fas fa-home"></i>仪表盘</a>
            </div>
            <div class="nav-item">
                <a class="nav-link" href="#"><i class="fas fa-chart-pie"></i>分析统计</a>
            </div>
            <div class="nav-header">应用功能</div>
            <div class="nav-item">
                <a class="nav-link" href="#"><i class="fas fa-list"></i>数据列表</a>
            </div>
            <div class="nav-item">
                <a class="nav-link" href="#"><i class="fas fa-file-alt"></i>表单管理</a>
            </div>
            <div class="nav-item">
                <a class="nav-link" href="#"><i class="fas fa-cloud-upload-alt"></i>文件上传</a>
            </div>
            <div class="nav-header">用户管理</div>
            <div class="nav-item">
                <a class="nav-link" href="#"><i class="fas fa-users"></i>用户列表</a>
            </div>
            <div class="nav-item">
                <a class="nav-link" href="#"><i class="fas fa-cog"></i>系统设置</a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Navbar -->
        <div class="navbar">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-0" style="color:#5e5873;font-weight:600;">Dashboard 📊</h4>
                    <small style="color:#b9b9c3;">Vuexy 极简现代风格演示</small>
                </div>
                <button class="btn btn-primary" onclick="showModal()">
                    <i class="fas fa-plus me-2"></i>新建任务
                </button>
            </div>
        </div>

        <!-- Stats -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="stat-content">
                        <h3>23.5k</h3>
                        <p>总销售额</p>
                        <div class="stat-trend text-success">
                            <i class="fas fa-arrow-up"></i> 25.8%
                        </div>
                    </div>
                    <div class="stat-icon primary">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="stat-content">
                        <h3>¥148k</h3>
                        <p>总收入</p>
                        <div class="stat-trend text-success">
                            <i class="fas fa-arrow-up"></i> 18.2%
                        </div>
                    </div>
                    <div class="stat-icon success">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="stat-content">
                        <h3>2,468</h3>
                        <p>新用户</p>
                        <div class="stat-trend text-danger">
                            <i class="fas fa-arrow-down"></i> 4.3%
                        </div>
                    </div>
                    <div class="stat-icon warning">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="stat-content">
                        <h3>86.5%</h3>
                        <p>转化率</p>
                        <div class="stat-trend text-success">
                            <i class="fas fa-arrow-up"></i> 12.8%
                        </div>
                    </div>
                    <div class="stat-icon danger">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-chart-area me-2" style="color:#7367f0;"></i>收入统计
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="incomeChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-chart-doughnut me-2" style="color:#7367f0;"></i>客户来源
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="sourceChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-receipt me-2" style="color:#7367f0;"></i>最新交易</span>
                <div class="d-flex gap-2">
                    <input type="text" class="form-control form-control-sm" placeholder="搜索交易记录..." style="width:220px;">
                    <button class="btn btn-primary btn-sm"><i class="fas fa-search"></i></button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>交易ID</th>
                            <th>客户</th>
                            <th>项目</th>
                            <th>金额</th>
                            <th>状态</th>
                            <th>进度</th>
                            <th>日期</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span style="color:#7367f0;font-weight:600;">#VXY-8924</span></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle text-white me-2 d-flex align-items-center justify-content-center" style="width:32px;height:32px;background:linear-gradient(118deg,#7367f0,rgba(115,103,240,.7));font-size:0.875rem;">孙</div>
                                    <span>孙先生</span>
                                </div>
                            </td>
                            <td>网站开发</td>
                            <td style="font-weight:600;">¥15,800</td>
                            <td><span class="badge badge-success">已完成</span></td>
                            <td>
                                <div style="width:85px;">
                                    <div class="progress">
                                        <div class="progress-bar bg-success" style="width:100%"></div>
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
                            <td><span style="color:#7367f0;font-weight:600;">#VXY-8925</span></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle text-white me-2 d-flex align-items-center justify-content-center" style="width:32px;height:32px;background:linear-gradient(118deg,#28c76f,rgba(40,199,111,.7));font-size:0.875rem;">钱</div>
                                    <span>钱女士</span>
                                </div>
                            </td>
                            <td>APP设计</td>
                            <td style="font-weight:600;">¥28,500</td>
                            <td><span class="badge badge-warning">进行中</span></td>
                            <td>
                                <div style="width:85px;">
                                    <div class="progress">
                                        <div class="progress-bar bg-warning" style="width:65%"></div>
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
                            <td><span style="color:#7367f0;font-weight:600;">#VXY-8926</span></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle text-white me-2 d-flex align-items-center justify-content-center" style="width:32px;height:32px;background:linear-gradient(118deg,#ff9f43,rgba(255,159,67,.7));font-size:0.875rem;">周</div>
                                    <span>周先生</span>
                                </div>
                            </td>
                            <td>品牌策划</td>
                            <td style="font-weight:600;">¥9,200</td>
                            <td><span class="badge badge-primary">待审核</span></td>
                            <td>
                                <div style="width:85px;">
                                    <div class="progress">
                                        <div class="progress-bar" style="width:25%;background:#7367f0;"></div>
                                    </div>
                                </div>
                            </td>
                            <td>2024-09-03</td>
                            <td>
                                <button class="btn btn-sm btn-primary"><i class="fas fa-eye"></i></button>
                                <button class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></button>
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
                        <i class="fas fa-cloud-upload-alt me-2" style="color:#7367f0;"></i>文件上传
                    </div>
                    <div class="card-body">
                        <div class="upload-area" onclick="document.getElementById('fileUp').click()">
                            <i class="fas fa-file-upload fa-3x mb-3" style="color:#b9b9c3;"></i>
                            <p class="mb-1" style="color:#5e5873;font-weight:500;">点击或拖拽文件到此处上传</p>
                            <small style="color:#b9b9c3;">支持 JPG, PNG, PDF, DOCX - 最大 20MB</small>
                            <input type="file" id="fileUp" style="display:none;" multiple>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-layer-group me-2" style="color:#7367f0;"></i>业务分类
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span style="font-weight:500;color:#5e5873;">网站开发</span>
                                <span style="font-weight:600;color:#7367f0;">45%</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar" style="width:45%;background:linear-gradient(118deg,#7367f0,rgba(115,103,240,.7));"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span style="font-weight:500;color:#5e5873;">APP设计</span>
                                <span style="font-weight:600;color:#28c76f;">30%</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar" style="width:30%;background:linear-gradient(118deg,#28c76f,rgba(40,199,111,.7));"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span style="font-weight:500;color:#5e5873;">品牌策划</span>
                                <span style="font-weight:600;color:#ff9f43;">15%</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar" style="width:15%;background:linear-gradient(118deg,#ff9f43,rgba(255,159,67,.7));"></div>
                            </div>
                        </div>
                        <div class="mb-0">
                            <div class="d-flex justify-content-between mb-2">
                                <span style="font-weight:500;color:#5e5873;">其他服务</span>
                                <span style="font-weight:600;color:#ea5455;">10%</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar" style="width:10%;background:linear-gradient(118deg,#ea5455,rgba(234,84,85,.7));"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal" id="taskModal">
        <div class="modal-content">
            <div class="modal-header">
                <h5 style="margin:0;font-weight:600;color:#5e5873;">新建任务</h5>
                <button style="border:0;background:transparent;font-size:1.5rem;color:#b9b9c3;cursor:pointer;" onclick="hideModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" style="font-weight:500;color:#5e5873;">任务名称</label>
                    <input type="text" class="form-control" placeholder="请输入任务名称">
                </div>
                <div class="mb-3">
                    <label class="form-label" style="font-weight:500;color:#5e5873;">负责人</label>
                    <input type="text" class="form-control" placeholder="请输入负责人">
                </div>
                <div class="mb-3">
                    <label class="form-label" style="font-weight:500;color:#5e5873;">任务优先级</label>
                    <select class="form-control">
                        <option>低</option>
                        <option>中</option>
                        <option>高</option>
                        <option>紧急</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label" style="font-weight:500;color:#5e5873;">任务描述</label>
                    <textarea class="form-control" rows="3" placeholder="请输入任务描述"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn" style="background:#ebe9f1;color:#5e5873;" onclick="hideModal()">取消</button>
                <button class="btn btn-primary">创建任务</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.3.0/dist/chart.umd.min.js"></script>
    <script>
        function showModal() {
            document.getElementById('taskModal').classList.add('show');
        }
        function hideModal() {
            document.getElementById('taskModal').classList.remove('show');
        }

        const incomeCtx = document.getElementById('incomeChart').getContext('2d');
        new Chart(incomeCtx, {
            type: 'bar',
            data: {
                labels: ['1月', '2月', '3月', '4月', '5月', '6月', '7月'],
                datasets: [{
                    label: '收入',
                    data: [45000, 52000, 48000, 68000, 62000, 78000, 72000],
                    backgroundColor: 'rgba(115,103,240,0.8)'
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
            type: 'pie',
            data: {
                labels: ['网站', '推荐', '广告', '其他'],
                datasets: [{
                    data: [45, 30, 15, 10],
                    backgroundColor: ['#7367f0', '#28c76f', '#ff9f43', '#ea5455']
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
