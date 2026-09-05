{{--
通用搜索过滤组件 | Generic Search Filter Component

使用示例 | Usage Example:
<x-search-filter
    :filters="[
        ['type' => 'select', 'id' => 'status', 'label' => '状态', 'options' => [
            ['value' => '', 'label' => '全部'],
            ['value' => 'active', 'label' => '启用'],
            ['value' => 'inactive', 'label' => '禁用']
        ]],
        ['type' => 'text', 'id' => 'keyword', 'label' => '关键词', 'placeholder' => '搜索...'],
        ['type' => 'date', 'id' => 'start_date', 'label' => '开始日期']
    ]"
    search-button-text="搜索"
    reset-button-text="重置"
    on-search="handleSearch"
/>

Props:
- filters: 过滤器配置数组
- search-button-text: 搜索按钮文本 (默认: 搜索)
- reset-button-text: 重置按钮文本 (默认: 重置)
- on-search: 搜索函数名 (JavaScript)
--}}

@props([
    'filters' => [],
    'searchButtonText' => '搜索',
    'resetButtonText' => '重置',
    'onSearch' => 'handleSearch'
])

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <div class="row g-3" id="searchFilterForm">
            @foreach($filters as $filter)
                <div class="col-md-{{ $filter['col'] ?? 3 }}">
                    @if($filter['type'] === 'select')
                        <label class="form-label">{{ $filter['label'] ?? '' }}</label>
                        <select class="form-select" id="{{ $filter['id'] }}" {{ isset($filter['onChange']) ? 'onchange="' . $filter['onChange'] . '"' : '' }}>
                            @foreach($filter['options'] ?? [] as $option)
                                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    @elseif($filter['type'] === 'text')
                        <label class="form-label">{{ $filter['label'] ?? '' }}</label>
                        <input
                            type="text"
                            class="form-control"
                            id="{{ $filter['id'] }}"
                            placeholder="{{ $filter['placeholder'] ?? '' }}"
                        >
                    @elseif($filter['type'] === 'date')
                        <label class="form-label">{{ $filter['label'] ?? '' }}</label>
                        <input
                            type="date"
                            class="form-control"
                            id="{{ $filter['id'] }}"
                        >
                    @elseif($filter['type'] === 'daterange')
                        <label class="form-label">{{ $filter['label'] ?? '' }}</label>
                        <div class="input-group">
                            <input type="date" class="form-control" id="{{ $filter['id'] }}_start">
                            <span class="input-group-text">至</span>
                            <input type="date" class="form-control" id="{{ $filter['id'] }}_end">
                        </div>
                    @endif
                </div>
            @endforeach

            <div class="col-md-12">
                <button class="btn btn-primary" onclick="{{ $onSearch }}()">
                    <i class="cil-magnifying-glass me-2"></i>{{ $searchButtonText }}
                </button>
                <button class="btn btn-secondary" onclick="resetFilters()">
                    <i class="cil-reload me-2"></i>{{ $resetButtonText }}
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function resetFilters() {
    @foreach($filters as $filter)
        @if($filter['type'] === 'daterange')
            document.getElementById('{{ $filter['id'] }}_start').value = '';
            document.getElementById('{{ $filter['id'] }}_end').value = '';
        @else
            const field{{ ucfirst($filter['id']) }} = document.getElementById('{{ $filter['id'] }}');
            if (field{{ ucfirst($filter['id']) }}) {
                @if($filter['type'] === 'select')
                    field{{ ucfirst($filter['id']) }}.selectedIndex = 0;
                @else
                    field{{ ucfirst($filter['id']) }}.value = '';
                @endif
            }
        @endif
    @endforeach

    @if($onSearch)
        {{ $onSearch }}();
    @endif
}
</script>
