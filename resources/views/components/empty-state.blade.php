{{--
空状态组件 | Empty State Component

使用示例 | Usage Example:
<x-empty-state
    icon="cil-inbox"
    message="暂无数据"
    description="当前没有任何记录"
    button-text="添加记录"
    button-link="/add"
/>

Props:
- icon: CoreUI图标名称 (默认: cil-inbox)
- message: 主要提示信息 (必需)
- description: 详细描述 (可选)
- button-text: 按钮文本 (可选)
- button-link: 按钮链接 (可选)
- button-onclick: 按钮点击事件 (可选)
--}}

@props([
    'icon' => 'cil-inbox',
    'message',
    'description' => null,
    'buttonText' => null,
    'buttonLink' => null,
    'buttonOnclick' => null
])

<div class="text-center py-5">
    <i class="{{ $icon }} text-body-secondary" style="font-size: 4rem; opacity: 0.3;"></i>
    <p class="text-body-secondary mt-3 mb-1 fw-semibold">{{ $message }}</p>
    @if($description)
        <p class="text-body-secondary small mb-3">{{ $description }}</p>
    @endif
    @if($buttonText)
        @if($buttonLink)
            <a href="{{ $buttonLink }}" class="btn btn-primary btn-sm mt-2">
                <i class="cil-plus me-2"></i>{{ $buttonText }}
            </a>
        @elseif($buttonOnclick)
            <button class="btn btn-primary btn-sm mt-2" onclick="{{ $buttonOnclick }}">
                <i class="cil-plus me-2"></i>{{ $buttonText }}
            </button>
        @endif
    @endif
</div>
