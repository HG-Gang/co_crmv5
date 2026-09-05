{{--
通用数据表格组件 | Generic Data Table Component

使用示例 | Usage Example:
<x-data-table
    :columns="[
        ['key' => 'id', 'label' => 'ID', 'width' => '80px'],
        ['key' => 'name', 'label' => '姓名'],
        ['key' => 'email', 'label' => '邮箱'],
        ['key' => 'status', 'label' => '状态', 'type' => 'badge']
    ]"
    :data="$users"
    :striped="true"
    :hover="true"
    table-class="table-sm"
/>

Props:
- columns: 列定义数组，每列包含 key, label, width(可选), type(可选: text|badge|date|currency)
- data: 数据数组
- striped: 是否显示斑马纹 (默认: true)
- hover: 是否悬停高亮 (默认: true)
- table-class: 附加的表格class (可选)
--}}

@props([
    'columns' => [],
    'data' => [],
    'striped' => true,
    'hover' => true,
    'tableClass' => ''
])

<div class="table-responsive">
    <table class="table align-middle mb-0 {{ $striped ? 'table-striped' : '' }} {{ $hover ? 'table-hover' : '' }} {{ $tableClass }}">
        <thead class="table-light">
            <tr>
                @foreach($columns as $column)
                    <th @if(isset($column['width'])) style="width: {{ $column['width'] }}" @endif>
                        {{ $column['label'] ?? '' }}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($data as $row)
                <tr>
                    @foreach($columns as $column)
                        <td>
                            @php
                                $value = $row[$column['key']] ?? '-';
                                $type = $column['type'] ?? 'text';
                            @endphp

                            @if($type === 'badge')
                                @if($value === 'active' || $value === 1 || $value === '已启用')
                                    <span class="badge bg-success">{{ $value }}</span>
                                @elseif($value === 'inactive' || $value === 0 || $value === '已禁用')
                                    <span class="badge bg-danger">{{ $value }}</span>
                                @else
                                    <span class="badge bg-secondary">{{ $value }}</span>
                                @endif
                            @elseif($type === 'date')
                                {{ $value ? date('Y-m-d H:i', strtotime($value)) : '-' }}
                            @elseif($type === 'currency')
                                ${{ number_format($value, 2) }}
                            @else
                                {{ $value }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($columns) }}" class="text-center text-body-secondary py-4">
                        暂无数据
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
