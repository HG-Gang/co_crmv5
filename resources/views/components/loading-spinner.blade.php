{{--
加载状态组件 | Loading Spinner Component

使用示例 | Usage Example:
<x-loading-spinner
    text="加载中..."
    size="md"
    color="primary"
/>

Props:
- text: 加载文本 (默认: 加载中...)
- size: 尺寸 sm|md|lg (默认: md)
- color: 颜色 primary|secondary|success|danger|warning|info (默认: primary)
- center: 是否居中显示 (默认: true)
--}}

@props([
    'text' => '加载中...',
    'size' => 'md',
    'color' => 'primary',
    'center' => true
])

@php
    $sizeClass = match($size) {
        'sm' => 'spinner-border-sm',
        'lg' => 'spinner-border-lg',
        default => ''
    };
@endphp

<div class="{{ $center ? 'text-center py-5' : '' }}">
    <div class="spinner-border text-{{ $color }} {{ $sizeClass }}" role="status">
        <span class="visually-hidden">{{ $text }}</span>
    </div>
    @if($text)
        <p class="text-body-secondary mt-2 mb-0">{{ $text }}</p>
    @endif
</div>
