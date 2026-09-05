<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notus - 完整演示</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'notus-primary': '#1e293b',
                        'notus-secondary': '#64748b',
                        'notus-accent': '#3b82f6',
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
    </style>
</head>
<body class="bg-gray-50">
    <a href="/ui-demos" class="fixed top-5 right-5 z-50 bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold shadow-lg hover:bg-blue-700 transition">
        <i class="fas fa-arrow-left mr-2"></i>返回导航
    </a>

    <!-- Sidebar -->
    <div class="fixed top-0 left-0 w-64 h-screen bg-slate-800 text-white z-40">
        <div class="p-6 border-b border-slate-700">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-700 rounded-lg flex items-center justify-center">
                    <i class="fas fa-layer-group text-lg"></i>
                </div>
                <span class="text-xl font-bold">Notus Admin</span>
            </div>
        </div>
        <nav class="p-4 space-y-1">
            <a href="#" class="flex items-center gap-3 px-4 py-3 rounded-lg bg-gradient-to-r from-blue-600 to-blue-700 text-white shadow-lg">
                <i class="fas fa-th-large w-5"></i>
                <span class="font-medium">仪表盘</span>
            </a>
            <a href="#" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-300 hover:bg-slate-700 transition">
                <i class="fas fa-chart-bar w-5"></i>
                <span class="font-medium">数据统计</span>
            </a>
            <a href="#" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-300 hover:bg-slate-700 transition">
                <i class="fas fa-table w-5"></i>
                <span class="font-medium">数据表格</span>
            </a>
            <a href="#" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-300 hover:bg-slate-700 transition">
                <i class="fas fa-edit w-5"></i>
                <span class="font-medium">表单管理</span>
            </a>
            <a href="#" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-300 hover:bg-slate-700 transition">
                <i class="fas fa-upload w-5"></i>
                <span class="font-medium">文件上传</span>
            </a>
            <a href="#" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-300 hover:bg-slate-700 transition">
                <i class="fas fa-users w-5"></i>
                <span class="font-medium">用户管理</span>
            </a>
            <a href="#" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-300 hover:bg-slate-700 transition">
                <i class="fas fa-cog w-5"></i>
                <span class="font-medium">系统设置</span>
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="ml-64 p-8">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">Dashboard</h1>
                    <p class="text-gray-500 mt-1">Notus Tailwind CSS 管理系统</p>
                </div>
                <button onclick="showModal()" class="bg-gradient-to-r from-blue-600 to-blue-700 text-white px-6 py-3 rounded-lg font-semibold shadow-lg hover:shadow-xl transition">
                    <i class="fas fa-plus mr-2"></i>新建项目
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm font-semibold uppercase tracking-wide">总收入</p>
                        <p class="text-3xl font-bold text-gray-800 mt-2">¥245,890</p>
                        <p class="text-green-600 text-sm mt-2">
                            <i class="fas fa-arrow-up"></i> 16.5% 较上月
                        </p>
                    </div>
                    <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center shadow-lg">
                        <i class="fas fa-wallet text-white text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm font-semibold uppercase tracking-wide">新客户</p>
                        <p class="text-3xl font-bold text-gray-800 mt-2">1,258</p>
                        <p class="text-green-600 text-sm mt-2">
                            <i class="fas fa-arrow-up"></i> 9.8% 较上月
                        </p>
                    </div>
                    <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-green-600 rounded-full flex items-center justify-center shadow-lg">
                        <i class="fas fa-users text-white text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-orange-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm font-semibold uppercase tracking-wide">待处理</p>
                        <p class="text-3xl font-bold text-gray-800 mt-2">348</p>
                        <p class="text-red-600 text-sm mt-2">
                            <i class="fas fa-arrow-down"></i> 4.2% 较上月
                        </p>
                    </div>
                    <div class="w-14 h-14 bg-gradient-to-br from-orange-500 to-orange-600 rounded-full flex items-center justify-center shadow-lg">
                        <i class="fas fa-clipboard-list text-white text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-purple-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm font-semibold uppercase tracking-wide">转化率</p>
                        <p class="text-3xl font-bold text-gray-800 mt-2">68.5%</p>
                        <p class="text-green-600 text-sm mt-2">
                            <i class="fas fa-arrow-up"></i> 7.3% 较上月
                        </p>
                    </div>
                    <div class="w-14 h-14 bg-gradient-to-br from-purple-500 to-purple-600 rounded-full flex items-center justify-center shadow-lg">
                        <i class="fas fa-chart-line text-white text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <div class="lg:col-span-2 bg-white rounded-xl shadow-md p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-chart-area text-blue-600 mr-2"></i>营收趋势
                </h3>
                <div class="h-64">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-chart-pie text-blue-600 mr-2"></i>流量分布
                </h3>
                <div class="h-64">
                    <canvas id="trafficChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden mb-8">
            <div class="p-6 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-800 flex items-center">
                    <i class="fas fa-list text-blue-600 mr-2"></i>最新订单
                </h3>
                <div class="flex gap-2">
                    <input type="text" placeholder="搜索订单..." class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <button class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">订单号</th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">客户</th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">产品</th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">金额</th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">状态</th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">进度</th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">#NOT-5284</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-blue-700 rounded-full flex items-center justify-center text-white text-xs font-bold">吴</div>
                                    <span class="text-sm text-gray-900">吴先生</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">笔记本电脑</td>
                            <td class="px-6 py-4 text-sm font-semibold text-gray-900">¥6,899</td>
                            <td class="px-6 py-4">
                                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">已完成</span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="w-24">
                                    <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                                        <div class="h-full bg-green-500 rounded-full" style="width:100%"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <button class="text-blue-600 hover:text-blue-800 mr-3"><i class="fas fa-eye"></i></button>
                                <button class="text-blue-600 hover:text-blue-800"><i class="fas fa-edit"></i></button>
                            </td>
                        </tr>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">#NOT-5285</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 bg-gradient-to-br from-green-500 to-green-700 rounded-full flex items-center justify-center text-white text-xs font-bold">郑</div>
                                    <span class="text-sm text-gray-900">郑女士</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">智能手表</td>
                            <td class="px-6 py-4 text-sm font-semibold text-gray-900">¥2,499</td>
                            <td class="px-6 py-4">
                                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">配送中</span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="w-24">
                                    <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                                        <div class="h-full bg-yellow-500 rounded-full" style="width:60%"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <button class="text-blue-600 hover:text-blue-800 mr-3"><i class="fas fa-eye"></i></button>
                                <button class="text-blue-600 hover:text-blue-800"><i class="fas fa-edit"></i></button>
                            </td>
                        </tr>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">#NOT-5286</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 bg-gradient-to-br from-purple-500 to-purple-700 rounded-full flex items-center justify-center text-white text-xs font-bold">冯</div>
                                    <span class="text-sm text-gray-900">冯先生</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">无线耳机</td>
                            <td class="px-6 py-4 text-sm font-semibold text-gray-900">¥899</td>
                            <td class="px-6 py-4">
                                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">处理中</span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="w-24">
                                    <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                                        <div class="h-full bg-blue-500 rounded-full" style="width:35%"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <button class="text-blue-600 hover:text-blue-800 mr-3"><i class="fas fa-eye"></i></button>
                                <button class="text-blue-600 hover:text-blue-800"><i class="fas fa-edit"></i></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Upload & Categories -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-cloud-upload-alt text-blue-600 mr-2"></i>文件上传
                </h3>
                <div onclick="document.getElementById('fileInput').click()" class="border-2 border-dashed border-gray-300 rounded-xl p-12 text-center cursor-pointer hover:border-blue-500 hover:bg-blue-50 transition">
                    <i class="fas fa-file-upload text-5xl text-gray-400 mb-4"></i>
                    <p class="text-gray-700 font-medium mb-1">拖拽文件到此处或点击上传</p>
                    <p class="text-sm text-gray-500">支持 JPG, PNG, PDF, DOCX - 最大 20MB</p>
                    <input type="file" id="fileInput" class="hidden" multiple>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-tags text-blue-600 mr-2"></i>销售分类
                </h3>
                <div class="space-y-4">
                    <div>
                        <div class="flex justify-between mb-2">
                            <span class="text-sm font-semibold text-gray-700">电子产品</span>
                            <span class="text-sm font-bold text-gray-900">50%</span>
                        </div>
                        <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-blue-500 to-blue-600 rounded-full" style="width:50%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between mb-2">
                            <span class="text-sm font-semibold text-gray-700">家用电器</span>
                            <span class="text-sm font-bold text-gray-900">25%</span>
                        </div>
                        <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-green-500 to-green-600 rounded-full" style="width:25%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between mb-2">
                            <span class="text-sm font-semibold text-gray-700">时尚配饰</span>
                            <span class="text-sm font-bold text-gray-900">15%</span>
                        </div>
                        <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-orange-500 to-orange-600 rounded-full" style="width:15%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between mb-2">
                            <span class="text-sm font-semibold text-gray-700">其他</span>
                            <span class="text-sm font-bold text-gray-900">10%</span>
                        </div>
                        <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-purple-500 to-purple-600 rounded-full" style="width:10%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div id="modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4">
            <div class="p-6 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-xl font-bold text-gray-800">新建项目</h3>
                <button onclick="hideModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">项目名称</label>
                    <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="请输入项目名称">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">客户名称</label>
                    <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="请输入客户名称">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">预算金额</label>
                    <input type="number" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="请输入预算">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">项目状态</label>
                    <select class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option>筹备中</option>
                        <option>进行中</option>
                        <option>已完成</option>
                    </select>
                </div>
            </div>
            <div class="p-6 border-t border-gray-200 flex justify-end gap-3">
                <button onclick="hideModal()" class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 font-semibold hover:bg-gray-50 transition">取消</button>
                <button class="px-6 py-2 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg font-semibold hover:shadow-lg transition">创建</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.3.0/dist/chart.umd.min.js"></script>
    <script>
        function showModal() {
            document.getElementById('modal').classList.remove('hidden');
        }
        function hideModal() {
            document.getElementById('modal').classList.add('hidden');
        }

        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: ['1月', '2月', '3月', '4月', '5月', '6月', '7月'],
                datasets: [{
                    label: '营收',
                    data: [40000, 52000, 48000, 68000, 62000, 78000, 72000],
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59,130,246,0.1)',
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

        const trafficCtx = document.getElementById('trafficChart').getContext('2d');
        new Chart(trafficCtx, {
            type: 'doughnut',
            data: {
                labels: ['搜索引擎', '社交媒体', '直接访问', '其他'],
                datasets: [{
                    data: [40, 30, 20, 10],
                    backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6']
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
