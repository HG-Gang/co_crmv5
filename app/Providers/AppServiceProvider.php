<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Schema::defaultStringLength(191);
        
        $viewNamespaces = [
            'front_layui' => resource_path('front/layui'),
            'front_adminlte' => resource_path('front/adminlte'),
            'admin_layui' => resource_path('admin/layui'),
            'admin_adminlte' => resource_path('admin/adminlte'),
        ];

        foreach ($viewNamespaces as $namespace => $path) {
            if (is_dir($path)) {
                view()->addNamespace($namespace, $path);
            }
        }
    }
}
