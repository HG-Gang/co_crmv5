{{--
统计卡片组件 | Stats Card Component

使用示例 | Usage Example:
<x-stats-card
    title="总用户数"
    value="1,234"
    icon="cil-user"
    trend="up"
    trend-value="+12.5%"
    color="primary"
/>

Props:
- title: 卡片标题 (必需)
- value: 主要数值 (必需)
- icon: CoreUI图标名称 (可选)
- trend: 趋势方向 up|down|flat (可选)
- trend-value: 趋势数值 (可选)
- color: 主题色 primary|success|danger|warning|info (默认: primary)
- subtitle: 副标题 (可选)
--}}

@props([
    'title',
    'value',
    'icon' => null,
    'trend' => null,
    'trendValue' => null,
    'color' => 'primary',
    'subtitle' => null
])

<div class="card shadow-sm border-0 h-100">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <div class="flex-grow-1">
                <p class="text-body-secondary small mb-1">{{ $title }}</p>
                <h3 class="mb-0">{{ $value }}</h3>
                @if($subtitle)
                <p class="text-body-secondary small mb-0 mt-1">{{ $subtitle }}</p>
                @endif
            </div>
            @if($icon)
            <div class="avatar bg-{{ $color }} bg-opacity-10 text-{{ $color }}">
                <i class="{{ $icon }}"></i>
            </div>
            @endif
        </div>

        @if($trend && $trendValue)
        <div class="d-flex align-items-center">
            @if($trend === 'up')
                <i class="cil-arrow-top text-success me-1"></i>
                <span class="text-success small">{{ $trendValue }}</span>
            @elseif($trend === 'down')
                <i class="cil-arrow-bottom text-danger me-1"></i>
                <span class="text-danger small">{{ $trendValue }}</span>
            @else
                <i class="cil-minus text-secondary me-1"></i>
                <span class="text-secondary small">{{ $trendValue }}</span>
            @endif
            <span class="text-body-secondary small ms-1">较上期</span>
        </div>
        @endif
    </div>
</div>

<style>
.avatar {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    font-size: 1.5rem;
}
</style>
