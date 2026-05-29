<!-- Modern Theme Table -->
<style>
    .modern-table { width: 100%; border-collapse: collapse; font-size: 14px; }
    .modern-table thead th { padding: 12px 16px; text-align: left; background: #fafafc; border-bottom: 1px solid #efeff5; color: #333639; font-weight: 500; font-size: 13px; white-space: nowrap; }
    .modern-table tbody td { padding: 12px 16px; border-bottom: 1px solid #efeff5; color: #333639; }
    .modern-table tbody tr:hover { background: #f8f9fb; }
    .modern-table .tag { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 12px; }
    .modern-table .tag-agent { background: #e8f5e9; color: #2e7d32; }
    .modern-table .tag-customer { background: #e3f2fd; color: #1565c0; }
    .modern-table .btn-view { padding: 4px 12px; border: 1px solid #d9d9d9; border-radius: 3px; background: #fff; color: #333; text-decoration: none; font-size: 13px; cursor: pointer; transition: all 0.2s; }
    .modern-table .btn-view:hover { border-color: #18a058; color: #18a058; }
    .modern-pagination { display: flex; justify-content: space-between; align-items: center; margin-top: 16px; padding: 8px 0; }
    .modern-pagination .page-info { color: #999; font-size: 13px; }
    .modern-pagination .page-links a, .modern-pagination .page-links span { display: inline-block; padding: 4px 12px; margin: 0 2px; border: 1px solid #d9d9d9; border-radius: 3px; text-decoration: none; color: #333; font-size: 13px; }
    .modern-pagination .page-links span.current { background: #18a058; color: #fff; border-color: #18a058; }
    .modern-pagination .page-links a:hover { border-color: #18a058; color: #18a058; }
    .tree-path { font-family: monospace; font-size: 12px; color: #666; max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .tree-path:hover { white-space: normal; word-break: break-all; }
</style>

<table class="modern-table">
    <thead>
        <tr>
            <th>{{ __('messages.id') }}</th>
            <th>{{ __('auth.user_name') }}</th>
            <th>{{ __('auth.email') }}</th>
            <th>{{ __('auth.phone') }}</th>
            <th>{{ __('auth.register_type') }}</th>
            <th>{{ __('messages.parent_agent') }}</th>
            <th>{{ __('messages.family_tree') }}</th>
            <th>{{ __('messages.created_at') }}</th>
            <th>{{ __('messages.operation') }}</th>
        </tr>
    </thead>
    <tbody>
    @forelse($users as $user)
        <tr>
            <td>{{ $user->user_id }}</td>
            <td>{{ $user->user_name }}</td>
            <td>{{ $user->login->email ?? '' }}</td>
            <td>{{ $user->phone }}</td>
            <td>
                @if($user->account_type === 1)
                    <span class="tag tag-agent">{{ __('auth.agent') }}</span>
                @else
                    <span class="tag tag-customer">{{ __('auth.customer') }}</span>
                @endif
            </td>
            <td>{{ $user->parent_id ?: '-' }}</td>
            <td><span class="tree-path" title="{{ $user->family_tree }}">{{ $user->family_tree }}</span></td>
            <td>{{ $user->created_at ? $user->created_at->format('Y-m-d H:i') : '' }}</td>
            <td><a href="{{ route('admin.users.show', $user->user_id) }}" class="btn-view">{{ __('messages.view') }}</a></td>
        </tr>
    @empty
        <tr><td colspan="9" style="text-align:center;color:#999;padding:40px;">{{ __('messages.no_data') }}</td></tr>
    @endforelse
    </tbody>
</table>

<div class="modern-pagination">
    <div class="page-info">{{ __('messages.total_records', ['total' => $users->total()]) }}</div>
    <div class="page-links">{{ $users->appends(request()->query())->links('pagination::bootstrap-4') }}</div>
</div>
