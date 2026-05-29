<?php
namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * 控制器根命名空间 | Root controller namespace
     */
    protected $namespace = 'App\\Http\\Controllers';

    public function boot()
    {
        $this->configureRateLimiting();
        $this->routes(function () {
            // 前台API路由 | Front API routes
            // 命名空间: App\Http\Controllers\Front
            Route::prefix('api/front')
                ->middleware('api')
                ->namespace($this->namespace . '\\Front')
                ->group(base_path('routes/front.php'));

            // 后台API路由 | Admin API routes
            // 命名空间: App\Http\Controllers\Admin
            Route::prefix('api/admin')
                ->middleware('api')
                ->namespace($this->namespace . '\\Admin')
                ->group(base_path('routes/admin.php'));

            // Web页面路由 | Web page routes
            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/web.php'));
        });
    }

    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            $user = $request->user();
            return Limit::perMinute(60)->by($user ? $user->id : $request->ip());
        });
    }
}
