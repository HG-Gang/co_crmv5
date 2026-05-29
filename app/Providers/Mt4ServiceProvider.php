<?php
namespace App\Providers;

use App\Services\Mt4ManagerService;
use Illuminate\Support\ServiceProvider;

class Mt4ServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('mt4.manager', function ($app) {
            return new Mt4ManagerService(
                config('mt4.host'),
                config('mt4.port'),
                config('mt4.api_key'),
                config('mt4.api_version'),
                config('mt4.timeout')
            );
        });
    }

    public function boot()
    {
        //
    }
}
