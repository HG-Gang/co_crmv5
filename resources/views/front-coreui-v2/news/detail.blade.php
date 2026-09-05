@extends('front-coreui-v2.layouts.app')

@section('title', '新闻详情')

@section('content')
<div class="container-lg px-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('front_coreui_v2_page_news') }}">新闻公告</a></li>
                    <li class="breadcrumb-item active">新闻详情</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8 mx-auto">
            <!-- News Content -->
            <div class="card shadow-sm border-0 mb-4" id="newsContent">
                <div class="card-body text-center py-5">
                    <div class="spinner-border spinner-border-sm me-2"></div>
                    加载中...
                </div>
            </div>

            <!-- Related News -->
            <div class="card shadow-sm border-0" id="relatedNewsCard" style="display: none;">
                <div class="card-header bg-transparent border-bottom">
                    <h6 class="mb-0">
                        <i class="cil-newspaper me-2"></i>相关新闻
                    </h6>
                </div>
                <div class="list-group list-group-flush" id="relatedNews"></div>
            </div>
        </div>
    </div>
</div>

<script>
let newsId = null;

document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    newsId = urlParams.get('id');

    if (!newsId) {
        alert('缺少新闻ID参数');
        window.location.href = '{{ route("front_coreui_v2_page_news") }}';
        return;
    }

    loadNews();
    loadRelatedNews();
});

function loadNews() {
    fetch(`{{ route("front_api_news_detail") }}?id=${newsId}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.news) {
            renderNews(data.news);
        } else {
            showError('新闻不存在或已删除');
        }
    })
    .catch(err => {
        console.error('Load news error:', err);
        showError('加载失败，请稍后重试');
    });
}

function renderNews(news) {
    const container = document.getElementById('newsContent');

    container.innerHTML = `
        <div class="card-body">
            <!-- Meta Info -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="d-flex align-items-center">
                    <span class="badge ${getCategoryBadge(news.category)} me-2">${getCategoryText(news.category)}</span>
                    ${news.is_important ? '<span class="badge bg-danger me-2">重要</span>' : ''}
                    ${news.is_top ? '<span class="badge bg-warning me-2">置顶</span>' : ''}
                </div>
                <small class="text-body-secondary">${formatDateTime(news.published_at)}</small>
            </div>

            <!-- Title -->
            <h2 class="mb-3">${news.title || '-'}</h2>

            <!-- Author & Views -->
            <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                <div class="d-flex align-items-center text-body-secondary small">
                    ${news.author ? `
                    <div class="d-flex align-items-center me-4">
                        <div class="avatar avatar-xs bg-primary bg-opacity-10 text-primary me-2">
                            <i class="cil-user"></i>
                        </div>
                        <span>${news.author}</span>
                    </div>
                    ` : ''}
                    <div class="d-flex align-items-center">
                        <i class="cil-eye me-1"></i>
                        <span>${news.views || 0} 浏览</span>
                    </div>
                </div>
                <div>
                    <button class="btn btn-sm btn-outline-secondary" onclick="shareNews()">
                        <i class="cil-share me-1"></i>分享
                    </button>
                </div>
            </div>

            <!-- Cover Image -->
            ${news.cover_image ? `
            <div class="mb-4">
                <img src="${news.cover_image}" class="img-fluid rounded w-100" alt="${news.title || ''}" style="max-height: 400px; object-fit: cover;">
            </div>
            ` : ''}

            <!-- Summary -->
            ${news.summary ? `
            <div class="alert alert-info mb-4">
                <strong>摘要：</strong>${news.summary}
            </div>
            ` : ''}

            <!-- Content -->
            <div class="news-content mb-4">
                ${news.content || '<p class="text-body-secondary">暂无内容</p>'}
            </div>

            <!-- Tags -->
            ${news.tags && news.tags.length > 0 ? `
            <div class="mb-4">
                <strong class="text-body-secondary small me-2">标签：</strong>
                ${news.tags.map(tag => `<span class="badge bg-light text-dark me-1">#${tag}</span>`).join('')}
            </div>
            ` : ''}

            <!-- Attachments -->
            ${news.attachments && news.attachments.length > 0 ? `
            <div class="card bg-light mb-4">
                <div class="card-body">
                    <h6 class="mb-3">
                        <i class="cil-paperclip me-2"></i>附件下载
                    </h6>
                    <div class="list-group list-group-flush">
                        ${news.attachments.map(att => `
                            <a href="${att.url}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" download>
                                <span>
                                    <i class="cil-file me-2"></i>${att.name || '未命名文件'}
                                </span>
                                <span class="badge bg-primary">${formatFileSize(att.size)}</span>
                            </a>
                        `).join('')}
                    </div>
                </div>
            </div>
            ` : ''}

            <!-- Footer Info -->
            <div class="border-top pt-3 mt-4">
                <div class="row text-body-secondary small">
                    <div class="col-md-6">
                        <i class="cil-calendar me-1"></i>发布时间: ${formatDateTime(news.published_at)}
                    </div>
                    ${news.updated_at && news.updated_at !== news.published_at ? `
                    <div class="col-md-6 text-md-end">
                        <i class="cil-pencil me-1"></i>更新时间: ${formatDateTime(news.updated_at)}
                    </div>
                    ` : ''}
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="d-flex gap-2 mt-4">
                <a href="{{ route('front_coreui_v2_page_news') }}" class="btn btn-secondary">
                    <i class="cil-arrow-left me-2"></i>返回列表
                </a>
                ${news.prev_id ? `
                <a href="{{ route('front_coreui_v2_page_news_detail') }}?id=${news.prev_id}" class="btn btn-outline-primary">
                    <i class="cil-chevron-left me-1"></i>上一篇
                </a>
                ` : ''}
                ${news.next_id ? `
                <a href="{{ route('front_coreui_v2_page_news_detail') }}?id=${news.next_id}" class="btn btn-outline-primary">
                    下一篇<i class="cil-chevron-right ms-1"></i>
                </a>
                ` : ''}
            </div>
        </div>
    `;

    // Update page title
    document.title = `${news.title || '新闻详情'} - ${document.title}`;
}

function loadRelatedNews() {
    fetch(`{{ route("front_api_news_related") }}?id=${newsId}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.news && data.news.length > 0) {
            renderRelatedNews(data.news);
        }
    })
    .catch(err => {
        console.error('Load related news error:', err);
    });
}

function renderRelatedNews(news) {
    const card = document.getElementById('relatedNewsCard');
    const container = document.getElementById('relatedNews');

    const html = news.map(item => `
        <a href="{{ route('front_coreui_v2_page_news_detail') }}?id=${item.id}" class="list-group-item list-group-item-action">
            <div class="d-flex justify-content-between align-items-start">
                <div class="flex-grow-1">
                    <h6 class="mb-1">${item.title || '-'}</h6>
                    <small class="text-body-secondary">${formatDate(item.published_at)}</small>
                </div>
                <span class="badge ${getCategoryBadge(item.category)}">${getCategoryText(item.category)}</span>
            </div>
        </a>
    `).join('');

    container.innerHTML = html;
    card.style.display = 'block';
}

function shareNews() {
    const url = window.location.href;
    if (navigator.share) {
        navigator.share({
            title: document.title,
            url: url
        }).catch(err => console.log('Share error:', err));
    } else {
        navigator.clipboard.writeText(url).then(() => {
            alert('链接已复制到剪贴板');
        }).catch(err => {
            console.error('Copy error:', err);
            alert('复制失败');
        });
    }
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
    return date.toLocaleDateString('zh-CN');
}

function formatDateTime(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleString('zh-CN', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function formatFileSize(bytes) {
    if (!bytes) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function showError(message) {
    document.getElementById('newsContent').innerHTML = `
        <div class="card-body text-center py-5">
            <i class="cil-warning text-danger" style="font-size: 3rem;"></i>
            <p class="text-danger mt-3 mb-0">${message}</p>
            <a href="{{ route('front_coreui_v2_page_news') }}" class="btn btn-secondary mt-3">
                <i class="cil-arrow-left me-2"></i>返回列表
            </a>
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

.avatar-xs {
    width: 28px;
    height: 28px;
    font-size: 0.875rem;
}

.news-content {
    font-size: 1.05rem;
    line-height: 1.8;
}

.news-content p {
    margin-bottom: 1rem;
}

.news-content img {
    max-width: 100%;
    height: auto;
    border-radius: 8px;
    margin: 1rem 0;
}

.news-content h3 {
    margin-top: 2rem;
    margin-bottom: 1rem;
    font-size: 1.5rem;
}

.news-content h4 {
    margin-top: 1.5rem;
    margin-bottom: 0.75rem;
    font-size: 1.25rem;
}

.news-content ul, .news-content ol {
    margin-bottom: 1rem;
    padding-left: 2rem;
}

.news-content blockquote {
    border-left: 4px solid #667eea;
    padding-left: 1rem;
    margin: 1.5rem 0;
    color: #6c757d;
    font-style: italic;
}
</style>
@endsection
