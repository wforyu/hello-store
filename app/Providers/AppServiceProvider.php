<?php

namespace App\Providers;

use App\Models\Banner;
use App\Models\Setting;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::define('access-pos', fn ($user) => in_array($user->role, ['admin', 'cashier']));
        Gate::define('admin', fn ($user) => $user->role === 'admin');

        View::composer('layouts.store', function ($view) {
            $allSettings = Setting::pluck('value', 'key')->toArray();

            if (isset($allSettings['bank_accounts'])) {
                $decoded = json_decode($allSettings['bank_accounts'], true);
                $allSettings['bank_accounts'] = is_array($decoded) ? $decoded : [];
            }

            $view->with('announcements', Banner::active()->where('type', 'announcement')->get());
            $view->with('popups', Banner::active()->where('type', 'popup')->get());
            $view->with('settings', $allSettings);
        });
    }
}
