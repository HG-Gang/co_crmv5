{{--
表单选择框组件 | Form Select Component

使用示例 | Usage Example:
<x-form-select
    id="status"
    label="状态"
    :options="[
        ['value' => '', 'label' => '请选择'],
        ['value' => 'active', 'label' => '启用'],
        ['value' => 'inactive', 'label' => '禁用']
    ]"
    :required="true"
    selected="active"
/>

Props:
- id: 选择框ID (必需)
- label: 标签文本 (必需)
- options: 选项数组 (必需)
- required: 是否必填 (默认: false)
- help-text: 帮助文本 (可选)
- selected: 默认选中值 (可选)
- disabled: 是否禁用 (默认: false)
- on-change: 变化回调函数 (可选)
--}}

@props([
    'id',
    'label',
    'options' => [],
    'required' => false,
    'helpText' => null,
    'selected' => '',
    'disabled' => false,
    'onChange' => null
])

<div class="mb-3">
    <label for="{{ $id }}" class="form-label">
        {{ $label }}
        @if($required)
            <span class="text-danger">*</span>
        @endif
    </label>
    <select
        class="form-select"
        id="{{ $id }}"
        @if($required) required @endif
        @if($disabled) disabled @endif
        @if($onChange) onchange="{{ $onChange }}()" @endif
    >
        @foreach($options as $option)
            <option
                value="{{ $option['value'] }}"
                @if($option['value'] == $selected) selected @endif
            >
                {{ $option['label'] }}
            </option>
        @endforeach
    </select>
    @if($helpText)
        <div class="form-text">{{ $helpText }}</div>
    @endif
</div>
