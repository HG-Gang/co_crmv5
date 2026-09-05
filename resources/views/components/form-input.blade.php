{{--
表单输入组件 | Form Input Component

使用示例 | Usage Example:
<x-form-input
    id="username"
    label="用户名"
    type="text"
    placeholder="请输入用户名"
    :required="true"
    help-text="3-20个字符"
/>

Props:
- id: 输入框ID (必需)
- label: 标签文本 (必需)
- type: 输入类型 text|email|password|number|tel|url (默认: text)
- placeholder: 占位符 (可选)
- required: 是否必填 (默认: false)
- help-text: 帮助文本 (可选)
- value: 默认值 (可选)
- disabled: 是否禁用 (默认: false)
- readonly: 是否只读 (默认: false)
--}}

@props([
    'id',
    'label',
    'type' => 'text',
    'placeholder' => '',
    'required' => false,
    'helpText' => null,
    'value' => '',
    'disabled' => false,
    'readonly' => false
])

<div class="mb-3">
    <label for="{{ $id }}" class="form-label">
        {{ $label }}
        @if($required)
            <span class="text-danger">*</span>
        @endif
    </label>
    <input
        type="{{ $type }}"
        class="form-control"
        id="{{ $id }}"
        placeholder="{{ $placeholder }}"
        value="{{ $value }}"
        @if($required) required @endif
        @if($disabled) disabled @endif
        @if($readonly) readonly @endif
    >
    @if($helpText)
        <div class="form-text">{{ $helpText }}</div>
    @endif
</div>
