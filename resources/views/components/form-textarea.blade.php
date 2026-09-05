{{--
表单文本域组件 | Form Textarea Component

使用示例 | Usage Example:
<x-form-textarea
    id="description"
    label="描述"
    placeholder="请输入描述"
    :rows="4"
    :required="true"
/>

Props:
- id: 文本域ID (必需)
- label: 标签文本 (必需)
- placeholder: 占位符 (可选)
- rows: 行数 (默认: 3)
- required: 是否必填 (默认: false)
- help-text: 帮助文本 (可选)
- value: 默认值 (可选)
- disabled: 是否禁用 (默认: false)
- readonly: 是否只读 (默认: false)
- maxlength: 最大长度 (可选)
--}}

@props([
    'id',
    'label',
    'placeholder' => '',
    'rows' => 3,
    'required' => false,
    'helpText' => null,
    'value' => '',
    'disabled' => false,
    'readonly' => false,
    'maxlength' => null
])

<div class="mb-3">
    <label for="{{ $id }}" class="form-label">
        {{ $label }}
        @if($required)
            <span class="text-danger">*</span>
        @endif
    </label>
    <textarea
        class="form-control"
        id="{{ $id }}"
        rows="{{ $rows }}"
        placeholder="{{ $placeholder }}"
        @if($required) required @endif
        @if($disabled) disabled @endif
        @if($readonly) readonly @endif
        @if($maxlength) maxlength="{{ $maxlength }}" @endif
    >{{ $value }}</textarea>
    @if($helpText)
        <div class="form-text">{{ $helpText }}</div>
    @endif
</div>
