<?php

namespace App\Http\Middleware;

use Closure;
use App\Traits\ApiResponse;
use App\Constants\ResponseCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Permission;

/**
 * Permission Check Middleware
 * 权限检查中间件
 */
class CheckPermission
{
    use ApiResponse;

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Try both admin and user guards
        $user = Auth::guard('admin')->user() ?: Auth::guard('user')->user();

        if (!$user) {
            return $this->error('Permission denied', ResponseCode::PERMISSION_DENIED, 403);
        }

        // Bypass for super admin (id=1 or name='super_admin')
        if ($user->role_id == 1 || ($user->role && $user->role->name === 'super_admin')) {
            return $next($request);
        }

        // Get current route name
        $routeName = $request->route()->getName();

        if (!$routeName) {
            return $next($request);
        }

        // Check if the route is registered in permissions table
        // If not registered, allow by default
        $permissionExists = Permission::where('api_route', $routeName)->exists();
        if (!$permissionExists) {
            return $next($request);
        }

        // Check if the current route name exists in the user's role permissions
        if (!$user->role) {
             return $this->error('Permission denied', ResponseCode::PERMISSION_DENIED, 403);
        }

        $hasPermission = $user->role->permissions()
            ->where('api_route', $routeName)
            ->where('status', 1)
            ->exists();

        if (!$hasPermission) {
            return $this->error('Permission denied', ResponseCode::PERMISSION_DENIED, 403);
        }

        return $next($request);
    }
}
