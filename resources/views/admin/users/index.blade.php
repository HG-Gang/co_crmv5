@extends('admin.layouts.app')

@section('title', __('auth.user_management'))

@section('content')
<div class="page-header">
    <h2>{{ __('auth.user_management') }}</h2>
</div>

<div class="content-card">
    <!-- Theme Switcher -->
    <div style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
        <form class="layui-form" style="display:flex;gap:10px;align-items:center;" method="GET">
            <select name="account_type" lay-filter="accountType" style="width:150px;">
                <option value="">{{ __('messages.all') }}</option>
                <option value="1" {{ request('account_type') == 1 ? 'selected' : '' }}>{{ __('auth.agent') }}</option>
                <option value="2" {{ request('account_type') == 2 ? 'selected' : '' }}>{{ __('auth.customer') }}</option>
            </select>
            <input type="text" name="keyword" value="{{ request('keyword') }}" placeholder="{{ __('messages.search') }}" class="layui-input" style="width:200px;">
            <button class="layui-btn layui-btn-sm" lay-submit>{{ __('messages.search') }}</button>
        </form>
        <div>
            <a href="?theme=layui&{{ http_build_query(request()->except('theme')) }}" class="layui-btn layui-btn-sm {{ $tableTheme === 'layui' ? 'layui-btn-normal' : 'layui-btn-primary' }}">{{ __('messages.layui_theme') }}</a>
            <a href="?theme=modern&{{ http_build_query(request()->except('theme')) }}" class="layui-btn layui-btn-sm {{ $tableTheme === 'modern' ? 'layui-btn-normal' : 'layui-btn-primary' }}">{{ __('messages.modern_theme') }}</a>
        </div>
    </div>

    @if($tableTheme === 'modern')
        <!-- Modern Table -->
        @include('admin.users._table_modern', ['users' => $users])
    @else
        <!-- Layui Classic Table -->
        <table id="userTable" lay-filter="userTable"></table>
    @endif
</div>
@endsection

@section('scripts')
@if($tableTheme === 'layui')
<script>
layui.use(['table', 'form'], function(){
    var table = layui.table;
    table.render({
        elem: '#userTable',
        url: '{{ route("admin.users.data") }}',
        where: {
            account_type: '{{ request("account_type") }}',
            keyword: '{{ request("keyword") }}'
        },
        page: true,
        cols: [[
            {field:'user_id', title:'{{ __("messages.id") }}', width:100, sort:true},
            {field:'user_name', title:'{{ __("auth.user_name") }}', width:150},
            {field:'email', title:'{{ __("auth.email") }}', width:200},
            {field:'phone', title:'{{ __("auth.phone") }}', width:130},
            {field:'account_type', title:'{{ __("auth.register_type") }}', width:120},
            {field:'parent_id', title:'{{ __("messages.parent_agent") }}', width:100},
            {field:'family_tree', title:'{{ __("messages.family_tree") }}', minWidth:200},
            {field:'created_at', title:'{{ __("messages.created_at") }}', width:170},
            {fixed:'right', title:'{{ __("messages.operation") }}', width:100, templet:function(d){
                return '<a class="layui-btn layui-btn-xs" href="/admin/users/'+d.user_id+'">{{ __("messages.view") }}</a>';
            }}
        ]],
        text: { none: '{{ __("messages.no_data") }}' }
    });
});
</script>
@endif
@endsection
