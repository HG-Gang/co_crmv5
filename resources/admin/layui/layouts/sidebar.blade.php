<div class="layui-side layui-bg-black">
    <div class="layui-side-scroll">
        <ul class="layui-nav layui-nav-tree" lay-filter="test">
            <li class="layui-nav-item {{ request()->is('admin/dashboard') ? 'layui-this' : '' }}">
                <a href="{{ route('admin.dashboard') }}">{{ __('admin.dashboard') }}</a>
            </li>
            <li class="layui-nav-item {{ request()->is('admin/users*') ? 'layui-this' : '' }}">
                <a href="{{ route('admin.users.index') }}">{{ __('admin.users') }}</a>
            </li>
            <li class="layui-nav-item">
                <a href="javascript:;">{{ __('admin.system') }}</a>
                <dl class="layui-nav-child">
                    <dd><a href="#">{{ __('admin.settings') }}</a></dd>
                    <dd><a href="#">{{ __('admin.logs') }}</a></dd>
                </dl>
            </li>
        </ul>
    </div>
</div>
