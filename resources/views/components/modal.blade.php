{{--
通用模态框组件 | Generic Modal Component

使用示例 | Usage Example:
<x-modal
    id="confirmModal"
    title="确认操作"
    :show-footer="true"
    confirm-text="确认"
    cancel-text="取消"
    on-confirm="handleConfirm"
    size="md"
>
    <p>确定要执行此操作吗？</p>
</x-modal>

Props:
- id: 模态框ID (必需)
- title: 标题 (必需)
- show-footer: 是否显示底部按钮 (默认: true)
- confirm-text: 确认按钮文本 (默认: 确认)
- cancel-text: 取消按钮文本 (默认: 取消)
- on-confirm: 确认回调函数名 (可选)
- size: 尺寸 sm|md|lg|xl (默认: md)
- header-class: 头部额外class (可选)
--}}

@props([
    'id',
    'title',
    'showFooter' => true,
    'confirmText' => '确认',
    'cancelText' => '取消',
    'onConfirm' => null,
    'size' => 'md',
    'headerClass' => ''
])

<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-{{ $size }}">
        <div class="modal-content">
            <div class="modal-header {{ $headerClass }}">
                <h5 class="modal-title">{{ $title }}</h5>
                <button type="button" class="btn-close {{ str_contains($headerClass, 'text-white') ? 'btn-close-white' : '' }}" data-coreui-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                {{ $slot }}
            </div>
            @if($showFooter)
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-coreui-dismiss="modal">
                    <i class="cil-x me-2"></i>{{ $cancelText }}
                </button>
                @if($onConfirm)
                <button type="button" class="btn btn-primary" onclick="{{ $onConfirm }}()">
                    <i class="cil-check me-2"></i>{{ $confirmText }}
                </button>
                @endif
            </div>
            @endif
        </div>
    </div>
</div>
