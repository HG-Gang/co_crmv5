@extends('admin-tailwind.layouts.app')

@section('title', '新闻管理 - 管理后台')

@section('content')
<!-- Page Header -->
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-3xl font-bold text-slate-800">新闻管理</h1>
        <p class="text-slate-600 mt-2">管理平台公告和新闻资讯</p>
    </div>
    <div class="flex gap-3">
        <button onclick="refreshNews()" class="px-6 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-sync-alt mr-2"></i>刷新
        </button>
        <button onclick="addNews()" class="px-6 py-3 bg-gradient-to-r from-green-500 to-emerald-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-plus mr-2"></i>发布新闻
        </button>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
        <p class="text-sm text-slate-600 mb-2">总新闻数</p>
        <p class="text-3xl font-bold text-slate-800" id="totalNews">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
        <p class="text-sm text-slate-600 mb-2">已发布</p>
        <p class="text-3xl font-bold text-green-600" id="publishedNews">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-yellow-500">
        <p class="text-sm text-slate-600 mb-2">草稿</p>
        <p class="text-3xl font-bold text-yellow-600" id="draftNews">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-purple-500">
        <p class="text-sm text-slate-600 mb-2">总阅读量</p>
        <p class="text-3xl font-bold text-purple-600" id="totalViews">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-orange-500">
        <p class="text-sm text-slate-600 mb-2">今日发布</p>
        <p class="text-3xl font-bold text-orange-600" id="todayNews">0</p>
    </div>
</div>

<!-- Filter Bar -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">搜索</label>
            <input type="text" id="searchKeyword" placeholder="新闻标题或内容" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">分类</label>
            <select id="filterCategory" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部</option>
                <option value="announcement">平台公告</option>
                <option value="market">市场资讯</option>
                <option value="tutorial">教程指南</option>
                <option value="promotion">活动促销</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">状态</label>
            <select id="filterStatus" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部</option>
                <option value="published">已发布</option>
                <option value="draft">草稿</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">排序</label>
            <select id="filterSort" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="date_desc">最新发布</option>
                <option value="date_asc">最早发布</option>
                <option value="views_desc">阅读量从高到低</option>
            </select>
        </div>
        <div class="flex items-end">
            <button onclick="searchNews()" class="w-full px-6 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                <i class="fas fa-search mr-2"></i>搜索
            </button>
        </div>
    </div>
</div>

<!-- News List -->
<div class="space-y-4" id="newsList">
    <div class="text-center py-12">
        <i class="fas fa-spinner fa-spin text-3xl text-slate-500 mb-3"></i>
        <p class="text-slate-600">加载中...</p>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-5xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-gradient-to-r from-blue-500 to-indigo-600 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-bold text-white" id="modalTitle">编辑新闻</h3>
            <button onclick="closeEditModal()" class="text-white hover:text-slate-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6">
            <form id="newsForm">
                <input type="hidden" id="newsId">

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">新闻标题 *</label>
                        <input type="text" id="newsTitle" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">分类 *</label>
                        <select id="newsCategory" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <option value="announcement">平台公告</option>
                            <option value="market">市场资讯</option>
                            <option value="tutorial">教程指南</option>
                            <option value="promotion">活动促销</option>
                        </select>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-semibold text-slate-700 mb-2">摘要</label>
                    <textarea id="newsSummary" rows="2" placeholder="简短描述新闻内容，将显示在列表页" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-semibold text-slate-700 mb-2">新闻内容 *</label>
                    <textarea id="newsContent" rows="12" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono text-sm" required></textarea>
                    <p class="text-xs text-slate-500 mt-1">支持HTML格式</p>
                </div>

                <div class="grid grid-cols-3 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">封面图片URL</label>
                        <input type="text" id="newsCoverImage" placeholder="https://example.com/cover.jpg" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">作者</label>
                        <input type="text" id="newsAuthor" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">排序权重</label>
                        <input type="number" id="newsSort" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-semibold text-slate-700 mb-2">标签</label>
                    <input type="text" id="newsTags" placeholder="用逗号分隔，例如：外汇,黄金,市场分析" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="mb-4 space-y-2">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" id="newsIsPublished" class="w-4 h-4 text-blue-600 rounded focus:ring-2 focus:ring-blue-500">
                        <span class="text-sm font-semibold text-slate-700">立即发布</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" id="newsIsFeatured" class="w-4 h-4 text-blue-600 rounded focus:ring-2 focus:ring-blue-500">
                        <span class="text-sm font-semibold text-slate-700">置顶推荐</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" id="newsAllowComment" class="w-4 h-4 text-blue-600 rounded focus:ring-2 focus:ring-blue-500">
                        <span class="text-sm font-semibold text-slate-700">允许评论</span>
                    </label>
                </div>

                <div class="flex gap-3">
                    <button type="button" onclick="closeEditModal()" class="flex-1 px-6 py-3 border border-slate-300 text-slate-700 font-semibold rounded-lg hover:bg-slate-50 transition">
                        取消
                    </button>
                    <button type="submit" class="flex-1 px-6 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                        保存
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadStats();
    loadNews();

    document.getElementById('newsForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveNews();
    });
});

function loadStats() {
    fetch('{{ route("admin_api_news_stats") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            document.getElementById('totalNews').textContent = data.total || 0;
            document.getElementById('publishedNews').textContent = data.published || 0;
            document.getElementById('draftNews').textContent = data.draft || 0;
            document.getElementById('totalViews').textContent = formatNumber(data.totalViews || 0, 0);
            document.getElementById('todayNews').textContent = data.today || 0;
        }
    })
    .catch(err => console.error('Load stats error:', err));
}

function loadNews() {
    const keyword = document.getElementById('searchKeyword').value;
    const category = document.getElementById('filterCategory').value;
    const status = document.getElementById('filterStatus').value;
    const sort = document.getElementById('filterSort').value;

    const params = new URLSearchParams({
        keyword: keyword,
        category: category,
        status: status,
        sort: sort
    });

    fetch(`{{ route('admin_api_news_list') }}?${params}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            renderNews(data.news || []);
        } else {
            showError(data.message || '加载失败');
        }
    })
    .catch(err => {
        console.error('Load error:', err);
        showError('网络错误，请稍后重试');
    });
}

function renderNews(newsList) {
    const container = document.getElementById('newsList');

    if (newsList.length === 0) {
        container.innerHTML = `
            <div class="bg-white rounded-xl shadow-lg p-12 text-center">
                <i class="fas fa-inbox text-5xl text-slate-300 mb-4"></i>
                <p class="text-slate-600">暂无新闻数据</p>
            </div>
        `;
        return;
    }

    container.innerHTML = newsList.map(n => `
        <div class="bg-white rounded-xl shadow-lg overflow-hidden hover:shadow-xl transition">
            <div class="flex">
                ${n.cover_image ? `
                    <div class="w-64 h-48 bg-gradient-to-br from-slate-100 to-slate-200 flex-shrink-0 overflow-hidden">
                        <img src="${n.cover_image}" alt="${n.title}" class="w-full h-full object-cover">
                    </div>
                ` : `
                    <div class="w-64 h-48 bg-gradient-to-br from-slate-100 to-slate-200 flex-shrink-0 flex items-center justify-center">
                        <i class="fas fa-newspaper text-5xl text-slate-300"></i>
                    </div>
                `}

                <div class="flex-1 p-6">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-2">
                                ${n.is_featured ? '<i class="fas fa-star text-yellow-500"></i>' : ''}
                                <h3 class="text-xl font-bold text-slate-800">${n.title || 'N/A'}</h3>
                            </div>
                            <p class="text-sm text-slate-600 mb-3">${n.summary || '暂无摘要'}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 mb-4">
                        ${getCategoryBadge(n.category)}
                        ${getStatusBadge(n.is_published)}
                        ${n.tags ? n.tags.split(',').slice(0, 3).map(tag =>
                            `<span class="px-2 py-1 bg-slate-100 text-slate-600 text-xs rounded-full">${tag.trim()}</span>`
                        ).join('') : ''}
                    </div>

                    <div class="grid grid-cols-4 gap-4 mb-4">
                        <div class="bg-blue-50 rounded-lg p-3">
                            <p class="text-xs text-blue-600 mb-1">阅读量</p>
                            <p class="text-lg font-bold text-blue-700">${formatNumber(n.views || 0, 0)}</p>
                        </div>
                        <div class="bg-purple-50 rounded-lg p-3">
                            <p class="text-xs text-purple-600 mb-1">评论</p>
                            <p class="text-lg font-bold text-purple-700">${n.comments_count || 0}</p>
                        </div>
                        <div class="bg-green-50 rounded-lg p-3">
                            <p class="text-xs text-green-600 mb-1">作者</p>
                            <p class="text-sm font-bold text-green-700">${n.author || 'Admin'}</p>
                        </div>
                        <div class="bg-orange-50 rounded-lg p-3">
                            <p class="text-xs text-orange-600 mb-1">发布时间</p>
                            <p class="text-xs font-bold text-orange-700">${n.published_at || '-'}</p>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <button onclick="editNews(${n.id})" class="px-4 py-2 bg-blue-100 text-blue-700 text-sm font-semibold rounded-lg hover:bg-blue-200 transition">
                            <i class="fas fa-edit mr-1"></i>编辑
                        </button>
                        <button onclick="togglePublish(${n.id}, ${n.is_published})" class="px-4 py-2 bg-${n.is_published ? 'yellow' : 'green'}-100 text-${n.is_published ? 'yellow' : 'green'}-700 text-sm font-semibold rounded-lg hover:bg-${n.is_published ? 'yellow' : 'green'}-200 transition">
                            <i class="fas fa-${n.is_published ? 'file' : 'paper-plane'} mr-1"></i>${n.is_published ? '转为草稿' : '发布'}
                        </button>
                        <button onclick="deleteNews(${n.id})" class="px-4 py-2 bg-red-100 text-red-700 text-sm font-semibold rounded-lg hover:bg-red-200 transition">
                            <i class="fas fa-trash mr-1"></i>删除
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `).join('');
}

function getCategoryBadge(category) {
    const badges = {
        'announcement': '<span class="px-3 py-1 bg-red-100 text-red-700 text-xs font-semibold rounded-full"><i class="fas fa-bullhorn mr-1"></i>平台公告</span>',
        'market': '<span class="px-3 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded-full"><i class="fas fa-chart-line mr-1"></i>市场资讯</span>',
        'tutorial': '<span class="px-3 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded-full"><i class="fas fa-book mr-1"></i>教程指南</span>',
        'promotion': '<span class="px-3 py-1 bg-purple-100 text-purple-700 text-xs font-semibold rounded-full"><i class="fas fa-gift mr-1"></i>活动促销</span>'
    };
    return badges[category] || '<span class="px-3 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full">其他</span>';
}

function getStatusBadge(isPublished) {
    return isPublished
        ? '<span class="px-3 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded-full">已发布</span>'
        : '<span class="px-3 py-1 bg-yellow-100 text-yellow-700 text-xs font-semibold rounded-full">草稿</span>';
}

function addNews() {
    document.getElementById('modalTitle').textContent = '发布新闻';
    document.getElementById('newsId').value = '';
    document.getElementById('newsTitle').value = '';
    document.getElementById('newsCategory').value = 'announcement';
    document.getElementById('newsSummary').value = '';
    document.getElementById('newsContent').value = '';
    document.getElementById('newsCoverImage').value = '';
    document.getElementById('newsAuthor').value = '';
    document.getElementById('newsSort').value = '0';
    document.getElementById('newsTags').value = '';
    document.getElementById('newsIsPublished').checked = false;
    document.getElementById('newsIsFeatured').checked = false;
    document.getElementById('newsAllowComment').checked = true;
    document.getElementById('editModal').classList.remove('hidden');
}

function editNews(id) {
    fetch(`{{ route('admin_api_news_detail', ['id' => '__ID__']) }}`.replace('__ID__', id), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.news) {
            const n = data.news;
            document.getElementById('modalTitle').textContent = '编辑新闻';
            document.getElementById('newsId').value = n.id;
            document.getElementById('newsTitle').value = n.title;
            document.getElementById('newsCategory').value = n.category;
            document.getElementById('newsSummary').value = n.summary || '';
            document.getElementById('newsContent').value = n.content;
            document.getElementById('newsCoverImage').value = n.cover_image || '';
            document.getElementById('newsAuthor').value = n.author || '';
            document.getElementById('newsSort').value = n.sort || 0;
            document.getElementById('newsTags').value = n.tags || '';
            document.getElementById('newsIsPublished').checked = n.is_published;
            document.getElementById('newsIsFeatured').checked = n.is_featured;
            document.getElementById('newsAllowComment').checked = n.allow_comment;
            document.getElementById('editModal').classList.remove('hidden');
        }
    })
    .catch(err => console.error('Load news error:', err));
}

function saveNews() {
    const id = document.getElementById('newsId').value;
    const data = {
        title: document.getElementById('newsTitle').value,
        category: document.getElementById('newsCategory').value,
        summary: document.getElementById('newsSummary').value,
        content: document.getElementById('newsContent').value,
        cover_image: document.getElementById('newsCoverImage').value,
        author: document.getElementById('newsAuthor').value,
        sort: document.getElementById('newsSort').value,
        tags: document.getElementById('newsTags').value,
        is_published: document.getElementById('newsIsPublished').checked,
        is_featured: document.getElementById('newsIsFeatured').checked,
        allow_comment: document.getElementById('newsAllowComment').checked
    };

    const url = id
        ? `{{ route('admin_api_news_update', ['id' => '__ID__']) }}`.replace('__ID__', id)
        : '{{ route("admin_api_news_create") }}';

    fetch(url, {
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
            alert(id ? '新闻更新成功' : '新闻发布成功');
            closeEditModal();
            loadStats();
            loadNews();
        } else {
            alert(data.message || '保存失败');
        }
    })
    .catch(err => {
        console.error('Save error:', err);
        alert('网络错误，请稍后重试');
    });
}

function togglePublish(id, currentStatus) {
    const action = currentStatus ? '转为草稿' : '发布';
    if (!confirm(`确定要${action}此新闻吗？`)) {
        return;
    }

    fetch(`{{ route('admin_api_news_toggle_publish', ['id' => '__ID__']) }}`.replace('__ID__', id), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert(`新闻${action}成功`);
            loadStats();
            loadNews();
        } else {
            alert(data.message || `${action}失败`);
        }
    })
    .catch(err => {
        console.error('Toggle error:', err);
        alert('网络错误，请稍后重试');
    });
}

function deleteNews(id) {
    if (!confirm('确定要删除此新闻吗？此操作不可恢复。')) {
        return;
    }

    fetch(`{{ route('admin_api_news_delete', ['id' => '__ID__']) }}`.replace('__ID__', id), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert('新闻删除成功');
            loadStats();
            loadNews();
        } else {
            alert(data.message || '删除失败');
        }
    })
    .catch(err => {
        console.error('Delete error:', err);
        alert('网络错误，请稍后重试');
    });
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}

function refreshNews() {
    loadStats();
    loadNews();
}

function searchNews() {
    loadNews();
}

function formatNumber(num, decimals = 2) {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    }).format(num);
}

function showError(message) {
    document.getElementById('newsList').innerHTML = `
        <div class="bg-white rounded-xl shadow-lg p-12 text-center">
            <i class="fas fa-exclamation-circle text-5xl text-red-500 mb-4"></i>
            <p class="text-red-600">${message}</p>
        </div>
    `;
}
</script>
@endsection
