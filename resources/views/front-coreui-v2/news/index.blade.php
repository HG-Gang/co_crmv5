@extends('front-coreui-v2.layouts.app')

@section('title', '新闻公告')

@section('content')
<div class="container-lg px-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-2">新闻公告</h2>
            <p class="text-body-secondary mb-0">了解最新的平台动态和行业资讯</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <select class="form-select" id="categoryFilter" onchange="applyFilters()">
                                <option value="">全部分类</option>
                                <option value="platform">平台公告</option>
                                <option value="market">市场资讯</option>
                                <option value="activity">活动通知</option>
                                <option value="policy">政策法规</option>
                                <option value="education">交易学堂</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group">
                                <input type="text" class="form-control" id="searchKeyword" placeholder="搜索新闻标题或内容">
                                <button class="btn btn-primary" onclick="applyFilters()">
                                    <i class="cil-magnifying-glass me-2"></i>搜索
                                </button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="sortBy" onchange="applyFilters()">
                                <option value="newest">最新发布</option>
                                <option value="popular">最多浏览</option>
                                <option value="important">重要程度</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Important News Banner -->
    <div class="row mb-4" id="importantNews" style="display: none;">
        <div class="col-12">
            <div class="card shadow-sm border-0 border-start border-danger border-4">
                <div class="card-body">
                    <div class="d-flex align-items-start">
                        <div class="avatar bg-danger bg-opacity-10 text-danger me-3">
                            <i class="cil-bell"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="mb-0" id="importantTitle">-</h5>
                                <span class="badge bg-danger">重要</span>
                            </div>
                            <p class="text-body-secondary mb-2" id="importantSummary">-</p>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-body-secondary" id="importantDate">-</small>
                                <a href="#" class="btn btn-sm btn-danger" id="importantLink">
                                    查看详情 <i class="cil-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- News List -->
    <div class="row g-4" id="newsList">
        <div class="col-12 text-center py-5">
            <div class="spinner-border spinner-border-sm me-2"></div>
            加载中...
        </div>
    </div>

    <!-- Pagination -->
    <div class="row mt-4">
        <div class="col-12">
            <nav id="paginationNav" style="display: none;">
                <ul class="pagination justify-content-center" id="pagination"></ul>
            </nav>
        </div>
    </div>
</div>

<script>
let currentPage = 1;

document.addEventListener('DOMContentLoaded', function() {
    loadImportantNews();
    loadNews(1);
});

function loadImportantNews() {
    fetch('{{ route("front_api_news_important") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.news) {
            renderImportantNews(data.news);
        }
    })
    .catch(err => {
        console.error('Load important news error:', err);
    });
}

function renderImportantNews(news) {
    const banner = document.getElementById('importantNews');
    document.getElementById('importantTitle').textContent = news.title || '-';
    document.getElementById('importantSummary').textContent = news.summary || '-';
    document.getElementById('importantDate').textContent = formatDate(news.published_at);
    document.getElementById('importantLink').href = `{{ route("front_coreui_v2_page_news_detail") }}?id=${news.id}`;
    banner.style.display = 'block';
}

function loadNews(page) {
    currentPage = page;
    const params = new URLSearchParams({
        page: page,
        category: document.getElementById('categoryFilter').value,
        keyword: document.getElementById('searchKeyword').value,
        sort: document.getElementById('sortBy').value
    });

    fetch(`{{ route("front_api_news_list") }}?${params.toString()}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.news) {
            renderNews(data.news);
            renderPagination(data.pagination || {});
        }
    })
    .catch(err => {
        console.error('Load news error:', err);
        showError('加载失败，请稍后重试');
    });
}

function renderNews(news) {
    const container = document.getElementById('newsList');

    if (news.length === 0) {
        container.innerHTML = `
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-body text-center py-5">
                        <i class="cil-newspaper text-body-secondary" style="font-size: 3rem; opacity: 0.3;"></i>
                        <p class="text-body-secondary mt-3 mb-0">暂无新闻</p>
                    </div>
                </div>
            </div>
        `;
        return;
    }

    const html = news.map(item => `
        <div class="col-12">
            <div class="card shadow-sm border-0 h-100 news-card">
                <div class="card-body">
                    <div class="row">
                        ${item.cover_image ? `
                        <div class="col-md-3">
                            <img src="${item.cover_image}" class="img-fluid rounded" alt="${item.title || ''}" style="max-height: 150px; width: 100%; object-fit: cover;">
                        </div>
                        ` : ''}
                        <div class="${item.cover_image ? 'col-md-9' : 'col-12'}">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div class="d-flex align-items-center">
                                    <span class="badge ${getCategoryBadge(item.category)} me-2">${getCategoryText(item.category)}</span>
                                    ${item.is_top ? '<span class="badge bg-danger me-2">置顶</span>' : ''}
                                    ${item.is_hot ? '<span class="badge bg-warning">热门</span>' : ''}
                                </div>
                                <small class="text-body-secondary">${formatDate(item.published_at)}</small>
                            </div>
                            <h5 class="card-title mb-2">
                                <a href="{{ route('front_coreui_v2_page_news_detail') }}?id=${item.id}" class="text-decoration-none text-dark">
                                    ${item.title || '-'}
                                </a>
                            </h5>
                            <p class="card-text text-body-secondary mb-3">${item.summary || '暂无摘要'}</p>
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center text-body-secondary small">
                                    <i class="cil-eye me-1"></i>
                                    <span class="me-3">${item.views || 0} 浏览</span>
                                    ${item.author ? `<i class="cil-user me-1"></i><span>${item.author}</span>` : ''}
                                </div>
                                <a href="{{ route('front_coreui_v2_page_news_detail') }}?id=${item.id}" class="btn btn-sm btn-outline-primary">
                                    阅读全文 <i class="cil-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `).join('');

    container.innerHTML = html;
}

function renderPagination(pagination) {
    const nav = document.getElementById('paginationNav');
    const ul = document.getElementById('pagination');

    if (!pagination.total || pagination.total <= 1) {
        nav.style.display = 'none';
        return;
    }

    nav.style.display = 'block';
    const currentPage = pagination.current_page || 1;
    const lastPage = pagination.last_page || 1;

    let html = '';

    // Previous
    html += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="loadNews(${currentPage - 1}); return false;">上一页</a>
    </li>`;

    // Pages
    for (let i = 1; i <= lastPage; i++) {
        if (i === 1 || i === lastPage || (i >= currentPage - 2 && i <= currentPage + 2)) {
            html += `<li class="page-item ${i === currentPage ? 'active' : ''}">
                <a class="page-link" href="#" onclick="loadNews(${i}); return false;">${i}</a>
            </li>`;
        } else if (i === currentPage - 3 || i === currentPage + 3) {
            html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
    }

    // Next
    html += `<li class="page-item ${currentPage === lastPage ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="loadNews(${currentPage + 1}); return false;">下一页</a>
    </li>`;

    ul.innerHTML = html;
}

function applyFilters() {
    loadNews(1);
}

function getCategoryBadge(category) {
    const badges = {
        platform: 'bg-primary',
        market: 'bg-info',
        activity: 'bg-success',
        policy: 'bg-warning',
        education: 'bg-secondary'
    };
    return badges[category] || 'bg-secondary';
}

function getCategoryText(category) {
    const texts = {
        platform: '平台公告',
        market: '市场资讯',
        activity: '活动通知',
        policy: '政策法规',
        education: '交易学堂'
    };
    return texts[category] || '其他';
}

function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    const now = new Date();
    const diff = now - date;
    const days = Math.floor(diff / (1000 * 60 * 60 * 24));

    if (days === 0) return '今天';
    if (days === 1) return '昨天';
    if (days < 7) return `${days}天前`;

    return date.toLocaleDateString('zh-CN');
}

function showError(message) {
    document.getElementById('newsList').innerHTML = `
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body text-center py-5">
                    <i class="cil-warning text-danger" style="font-size: 3rem;"></i>
                    <p class="text-danger mt-3 mb-0">${message}</p>
                </div>
            </div>
        </div>
    `;
}
</script>

<style>
.avatar {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    font-size: 1.25rem;
}

.news-card {
    transition: transform 0.2s, box-shadow 0.2s;
}

.news-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}
</style>
@endsection
