{{--
通用分页组件 | Generic Pagination Component

使用示例 | Usage Example:
<x-pagination
    :current-page="1"
    :last-page="10"
    :total="100"
    on-page-change="loadData"
/>

Props:
- current-page: 当前页码 (必需)
- last-page: 总页数 (必需)
- total: 总记录数 (可选)
- on-page-change: 页码变化回调函数名 (必需)
- show-info: 是否显示统计信息 (默认: true)
--}}

@props([
    'currentPage' => 1,
    'lastPage' => 1,
    'total' => null,
    'onPageChange' => 'loadData',
    'showInfo' => true
])

@if($lastPage > 1)
<div class="d-flex justify-content-between align-items-center">
    @if($showInfo && $total)
    <div class="text-body-secondary small">
        共 {{ $total }} 条记录，第 {{ $currentPage }} / {{ $lastPage }} 页
    </div>
    @else
    <div></div>
    @endif

    <nav>
        <ul class="pagination mb-0">
            {{-- 上一页 --}}
            <li class="page-item {{ $currentPage <= 1 ? 'disabled' : '' }}">
                <a class="page-link" href="#" onclick="{{ $onPageChange }}({{ $currentPage - 1 }}); return false;">
                    上一页
                </a>
            </li>

            {{-- 页码按钮 --}}
            @php
                $start = max(1, $currentPage - 2);
                $end = min($lastPage, $currentPage + 2);
            @endphp

            @if($start > 1)
                <li class="page-item">
                    <a class="page-link" href="#" onclick="{{ $onPageChange }}(1); return false;">1</a>
                </li>
                @if($start > 2)
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                @endif
            @endif

            @for($i = $start; $i <= $end; $i++)
                <li class="page-item {{ $i == $currentPage ? 'active' : '' }}">
                    <a class="page-link" href="#" onclick="{{ $onPageChange }}({{ $i }}); return false;">
                        {{ $i }}
                    </a>
                </li>
            @endfor

            @if($end < $lastPage)
                @if($end < $lastPage - 1)
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                @endif
                <li class="page-item">
                    <a class="page-link" href="#" onclick="{{ $onPageChange }}({{ $lastPage }}); return false;">
                        {{ $lastPage }}
                    </a>
                </li>
            @endif

            {{-- 下一页 --}}
            <li class="page-item {{ $currentPage >= $lastPage ? 'disabled' : '' }}">
                <a class="page-link" href="#" onclick="{{ $onPageChange }}({{ $currentPage + 1 }}); return false;">
                    下一页
                </a>
            </li>
        </ul>
    </nav>
</div>
@endif
