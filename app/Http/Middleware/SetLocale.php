<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $sessionLocale = $request->hasSession() ? Session::get('locale') : null;

        // Priority: JS/API header > Session > Browser > Config default.
        // Public page language switching is handled by JavaScript, so this
        // middleware deliberately ignores URL language parameters.
        $locale = $request->header('X-Locale')
            ?? $sessionLocale
            ?? $this->getBrowserLocale($request)
            ?? config('app.locale');

        if (in_array($locale, ['zh-CN', 'en'])) {
            App::setLocale($locale);
            if ($request->hasSession()) {
                Session::put('locale', $locale);
            }
        }

        return $next($request);
    }

    protected function getBrowserLocale(Request $request): ?string
    {
        $acceptLang = $request->header('Accept-Language', '');
        if (strpos($acceptLang, 'zh') !== false) {
            return 'zh-CN';
        }
        if (strpos($acceptLang, 'en') !== false) {
            return 'en';
        }
        return null;
    }
}
